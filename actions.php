<?php
require_once "core.php";
require_once "filesystem.php";
require_once "google_token_helper.php";
/* ================= ENCRYPTION ================= */
define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");

requireLogin();

$user = currentUser();
$uid  = $user['id'];

/* =====================================================
   HELPERS
===================================================== */

function redirectBack($parent = null, $view = 'files') {
    if ($view === 'trash') {
        header("Location: dashboard.php?view=trash");
    } else {
        header("Location: dashboard.php" . ($parent !== null ? "?f=$parent" : ""));
    }
    exit;
}

function getNodeNames($uid, $ids) {
    global $conn;
    if (empty($ids)) return [];

    $ids = array_map('intval', $ids);
    $in  = implode(',', $ids);

    $names = [];
    $res = $conn->query(
        "SELECT id, name FROM fs_nodes WHERE uid=$uid AND id IN ($in)"
    );

    while ($r = $res->fetch_assoc()) {
        $names[$r['id']] = $r['name'];
    }
    return $names;
}

/* =====================================================
   INPUT
===================================================== */

$action = $_GET['action'] ?? null;
$bulk   = $_POST['bulk_action'] ?? null;

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ids    = $_POST['ids'] ?? [];

$parent = ($_POST['parent'] ?? '') !== '' ? (int)$_POST['parent'] : null;
$target = ($_POST['target'] ?? '') !== '' ? (int)$_POST['target'] : null;

$fromTrash = ($_POST['from_view'] ?? '') === 'trash';

/* =====================================================
   CREATE FILE / FOLDER
===================================================== */

$type = $_POST['type'] ?? null;
$name = trim($_POST['name'] ?? '');

if ($type === 'folder' && $name !== '') {
    fs_create_folder($uid, $parent, $name);
    logUserAction("Created folder: $name");
    redirectBack($parent);
}

if ($type === 'file' && $name !== '') {

    if (!fs_exists($uid, $parent, $name)) {

        $accessToken = getValidDriveAccessToken($uid);
        if (!$accessToken) die("Drive not connected");

        // create empty encrypted file
        $key = hash("sha256", ENC_SECRET . $uid, true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt("", "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        $payload = base64_encode($iv . $enc);

        $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=media");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/octet-stream"
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $drive = json_decode($res, true);
        if (!isset($drive['id'])) die("Drive file create failed");

        // storage
        $stmt = $conn->prepare(
            "INSERT INTO fs_storage (uid, engine, engine_file_id, checksum)
             VALUES (?, 'gdrive', ?, '')"
        );
        $stmt->bind_param("is", $uid, $drive['id']);
        $stmt->execute();
        $storageId = $stmt->insert_id;
        $stmt->close();

        // node
        $stmt = $conn->prepare(
            "INSERT INTO fs_nodes
             (uid, parent_id, name, type, storage_id, size)
             VALUES (?, ?, ?, 'file', ?, 0)"
        );
        $stmt->bind_param("iisi", $uid, $parent, $name, $storageId);
        $stmt->execute();
        $stmt->close();

        logUserAction("Created file: $name");
    }

    redirectBack($parent);
}

/* =====================================================
   SINGLE ACTIONS
===================================================== */

if ($action === 'rename' && isset($_POST['id'])) {
    fs_rename($uid, (int)$_POST['id'], trim($_POST['new_name']));
    logUserAction("Renamed to: " . trim($_POST['new_name']));
    redirectBack($parent);
}

if ($action === 'trash' && $id) {
    $name = getNodeNames($uid, [$id]);
    fs_delete($uid, $id);
    logUserAction("Moved to trash: " . reset($name));
    redirectBack($parent);
}

if ($action === 'restore' && $id) {
    $name = getNodeNames($uid, [$id]);
    $conn->query("UPDATE fs_nodes SET is_deleted=0 WHERE id=$id AND uid=$uid");
    logUserAction("Restored: " . reset($name));
    redirectBack(null, 'trash');
}

if ($action === 'delete' && $id) {
    $name = getNodeNames($uid, [$id]);
    $conn->query("DELETE FROM fs_nodes WHERE id=$id AND uid=$uid");
    logUserAction("Permanently deleted: " . reset($name));
    redirectBack(null, 'trash');
}

/* =====================================================
   BULK ACTIONS
===================================================== */

if ($bulk && is_array($ids) && count($ids)) {

    $names = array_values(getNodeNames($uid, $ids));
    $accessToken = getValidDriveAccessToken($uid);

    foreach ($ids as $nodeId) {
        $nodeId = (int)$nodeId;

        /* TRASH */
        if ($bulk === 'trash' && !$fromTrash) {
            $conn->query(
                "UPDATE fs_nodes SET is_deleted=1 WHERE id=$nodeId AND uid=$uid"
            );
        }

        /* RESTORE */
        if ($bulk === 'restore') {
            $conn->query(
                "UPDATE fs_nodes SET is_deleted=0 WHERE id=$nodeId AND uid=$uid"
            );
        }

        /* PERMANENT DELETE */
        if ($bulk === 'delete' && $fromTrash) {
            $conn->query(
                "DELETE FROM fs_nodes WHERE id=$nodeId AND uid=$uid"
            );
        }

        /* MOVE */
        if ($bulk === 'move' && $target !== null) {
            $stmt = $conn->prepare(
                "UPDATE fs_nodes SET parent_id=? WHERE id=? AND uid=?"
            );
            $stmt->bind_param("iii", $target, $nodeId, $uid);
            $stmt->execute();
            $stmt->close();
        }

        /* COPY (REAL FILE COPY) */
        if ($bulk === 'copy' && $target !== null) {

            $stmt = $conn->prepare(
                "SELECT n.name, n.size, s.engine_file_id
                 FROM fs_nodes n
                 JOIN fs_storage s ON s.id=n.storage_id
                 WHERE n.id=? AND n.uid=?"
            );
            $stmt->bind_param("ii", $nodeId, $uid);
            $stmt->execute();
            $src = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$src) continue;

            // download encrypted
            $ch = curl_init(
                "https://www.googleapis.com/drive/v3/files/" .
                $src['engine_file_id'] . "?alt=media"
            );
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"]
            ]);
            $encData = curl_exec($ch);
            curl_close($ch);

            // upload new file
            $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=media");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/octet-stream"
                ],
                CURLOPT_POSTFIELDS => $encData
            ]);
            $res = curl_exec($ch);
            curl_close($ch);

            $newDrive = json_decode($res, true);
            if (!isset($newDrive['id'])) continue;

            // storage
            $stmt = $conn->prepare(
                "INSERT INTO fs_storage (uid, engine, engine_file_id, checksum)
                 VALUES (?, 'gdrive', ?, '')"
            );
            $stmt->bind_param("is", $uid, $newDrive['id']);
            $stmt->execute();
            $storageId = $stmt->insert_id;
            $stmt->close();

            // node
            $stmt = $conn->prepare(
                "INSERT INTO fs_nodes
                 (uid, parent_id, name, type, storage_id, size)
                 VALUES (?, ?, ?, 'file', ?, ?)"
            );
            $stmt->bind_param(
                "iisii",
                $uid,
                $target,
                $src['name'],
                $storageId,
                $src['size']
            );
            $stmt->execute();
            $stmt->close();
        }

        /* DOWNLOAD */
        if ($bulk === 'download') {
            $_SESSION['download_ids'] = $ids;
            header("Location: download.php");
            exit;
        }
    }

    /* LOG ONCE */
    if (!empty($names)) {
        $actionText = match ($bulk) {
            'trash'    => 'Moved to trash',
            'restore'  => 'Restored',
            'delete'   => 'Permanently deleted',
            'move'     => 'Moved',
            'copy'     => 'Copied',
            'download' => 'Downloaded',
            default    => ucfirst($bulk)
        };
        logUserAction($actionText . ": " . implode(", ", $names));
    }

    redirectBack($parent, $fromTrash ? 'trash' : 'files');
}

/* =====================================================
   FALLBACK
===================================================== */

redirectBack($parent);
