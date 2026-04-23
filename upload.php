<?php
require_once "core.php";
require_once "filesystem.php";
require_once "google_token_helper.php";

requireLogin();

$uid = currentUserId();

/* ================= CONFIG ================= */

define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");
define("CHUNK_SIZE", 5 * 1024 * 1024); // 5 MB per chunk

/* ================= HELPERS ================= */

function encryptChunk($uid, $data) {
    $key = hash("sha256", ENC_SECRET . $uid, true);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    return $iv . $enc; // raw binary
}

/* ================= DRIVE HELPERS ================= */

function startResumableUpload($token, $fileName, $parentId) {
    $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "name" => $fileName,
            "parents" => [$parentId]
        ])
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if (!preg_match('/Location:\s*(.+)/i', $res, $m)) {
        return null;
    }
    return trim($m[1]);
}

function uploadChunk($uploadUrl, $token, $data, $offset, $total) {
    $end = $offset + strlen($data) - 1;

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Length: " . strlen($data),
            "Content-Range: bytes $offset-$end/$total"
        ],
        CURLOPT_POSTFIELDS => $data
    ]);

    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

/* ================= INPUT ================= */

if (!isset($_FILES['files'])) {
    die("No files received");
}

$parent = isset($_POST['parent']) && $_POST['parent'] !== ''
    ? (int)$_POST['parent']
    : null;

/* ================= DRIVE TOKEN ================= */

$accessToken = getValidDriveAccessToken($uid);
if (!$accessToken) {
    die("Google Drive not connected");
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
    die("Drive root missing");
}

/* ================= PROCESS FILES ================= */

foreach ($_FILES['files']['tmp_name'] as $i => $tmpPath) {

    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }

    $originalName = $_FILES['files']['name'][$i];
    $size         = $_FILES['files']['size'][$i];

    /* --- Prevent duplicate --- */
    if (fs_exists($uid, $parent, $originalName)) {
        continue;
    }

    /* --- Create Drive file --- */
    $uploadUrl = startResumableUpload(
        $accessToken,
        bin2hex(random_bytes(8)) . ".enc",
        $driveRoot
    );

    if (!$uploadUrl) {
        die("Failed to start Drive upload");
    }

    /* --- Stream encrypt + upload --- */
    $fp = fopen($tmpPath, "rb");
    $offset = 0;

    while (!feof($fp)) {
        $plainChunk = fread($fp, CHUNK_SIZE);
        if ($plainChunk === false) break;

        $encChunk = encryptChunk($uid, $plainChunk);
        uploadChunk(
            $uploadUrl,
            $accessToken,
            $encChunk,
            $offset,
            "*" // unknown final size is OK
        );
        $offset += strlen($encChunk);
    }
    fclose($fp);

    /* --- Save storage --- */
    $stmt = $conn->prepare(
        "INSERT INTO fs_storage (uid, engine, engine_file_id, checksum)
         VALUES (?, 'gdrive', ?, '')"
    );
    $fakeId = uniqid("gdrive_", true);
    $stmt->bind_param("is", $uid, $fakeId);
    $stmt->execute();
    $storageId = $stmt->insert_id;
    $stmt->close();

    /* --- Save node --- */
    $stmt = $conn->prepare(
        "INSERT INTO fs_nodes (uid, parent_id, name, type, storage_id, size)
         VALUES (?, ?, ?, 'file', ?, ?)"
    );
    $stmt->bind_param("iisii", $uid, $parent, $originalName, $storageId, $size);
    $stmt->execute();
    $stmt->close();

    logUserAction("Uploaded file: $originalName");
}

header("Location: dashboard.php" . ($parent !== null ? "?f=$parent" : ""));
exit;
