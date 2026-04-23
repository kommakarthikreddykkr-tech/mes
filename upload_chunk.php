<?php
require_once "core.php";
require_once "filesystem.php";
require_once "google_token_helper.php";

requireLogin();

header("Content-Type: application/json");

define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");
define("TMP_DIR", __DIR__ . "/tmp_uploads");

if (!is_dir(TMP_DIR)) {
    mkdir(TMP_DIR, 0777, true);
}

$uid = currentUserId();

/* ================= VALIDATE ================= */

if (!isset($_FILES['chunk'])) {
    echo json_encode(["success"=>false,"error"=>"No chunk received"]);
    exit;
}

$chunk     = file_get_contents($_FILES['chunk']['tmp_name']);
$offset    = (int)($_POST['offset'] ?? 0);
$index     = (int)($_POST['index'] ?? 0);
$totalSize = (int)($_POST['size'] ?? 0);
$name      = $_POST['name'] ?? '';
$parent    = $_POST['parent'] !== '' ? (int)$_POST['parent'] : null;

/* ================= ENCRYPT ================= */

function encryptChunk($uid, $data) {
    $key = hash("sha256", ENC_SECRET . $uid, true);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    return $iv . $enc;
}

$encrypted = encryptChunk($uid, $chunk);

/* ================= DRIVE TOKEN ================= */

$accessToken = getValidDriveAccessToken($uid);
if (!$accessToken) {
    echo json_encode(["success"=>false,"error"=>"Drive not connected"]);
    exit;
}

/* ================= DRIVE ROOT ================= */

$stmt = $conn->prepare(
    "SELECT drive_root_id FROM user_drive_tokens WHERE uid=?"
);
$stmt->bind_param("i", $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$driveRoot = $row['drive_root_id'] ?? null;
if (!$driveRoot) {
    echo json_encode(["success"=>false,"error"=>"Drive root missing"]);
    exit;
}

/* ================= RESUMABLE SESSION ================= */

$sessionFile = TMP_DIR . "/drive_" . md5($uid.$name);

if ($index === 0) {

    $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "name" => bin2hex(random_bytes(8)) . ".enc",
            "parents" => [$driveRoot]
        ])
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if (!preg_match('/Location:\s*(.+)/i', $res, $m)) {
        echo json_encode(["success"=>false,"error"=>"Failed to start Drive upload"]);
        exit;
    }

    file_put_contents($sessionFile, trim($m[1]));
}

$uploadUrl = file_get_contents($sessionFile);

$start = $offset;
$end   = $offset + strlen($encrypted) - 1;

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $accessToken",
        "Content-Length: " . strlen($encrypted),
        "Content-Range: bytes $start-$end/*"
    ],
    CURLOPT_POSTFIELDS => $encrypted
]);

$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ================= FINALIZE ================= */

if ($http === 200 || $http === 201) {

    unlink($sessionFile);

    // Save DB entries ONCE
    if (!fs_exists($uid, $parent, $name)) {

        $stmt = $conn->prepare(
            "INSERT INTO fs_storage (uid, engine, engine_file_id, checksum)
             VALUES (?, 'gdrive', ?, '')"
        );
        $fakeId = uniqid("gdrive_", true);
        $stmt->bind_param("is", $uid, $fakeId);
        $stmt->execute();
        $storageId = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO fs_nodes (uid, parent_id, name, type, storage_id, size)
             VALUES (?, ?, ?, 'file', ?, ?)"
        );
        $stmt->bind_param("iisii", $uid, $parent, $name, $storageId, $totalSize);
        $stmt->execute();
        $stmt->close();

        logUserAction("Uploaded file: $name");
    }
}

echo json_encode(["success"=>true]);
