<?php
require_once "core.php";
require_once "filesystem.php";
require_once "google_token_helper.php";

requireLogin();

$uid = currentUserId();

/* ================= INPUT ================= */

$ids = $_SESSION['download_ids'] ?? [];
unset($_SESSION['download_ids']);

if (!is_array($ids) || empty($ids)) {
    die("No files selected");
}

/* ================= FETCH FILE METADATA ================= */

$ids = array_map('intval', $ids);
$in  = implode(',', $ids);

$res = $conn->query(
    "SELECT n.id, n.name, s.engine_file_id
     FROM fs_nodes n
     JOIN fs_storage s ON s.id = n.storage_id
     WHERE n.uid=$uid
       AND n.type='file'
       AND n.is_deleted=0
       AND n.id IN ($in)"
);

$files = [];
while ($row = $res->fetch_assoc()) {
    $files[] = $row;
}

if (empty($files)) {
    die("Files not found");
}

/* ================= HELPERS ================= */

define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");

function decryptData($uid, $data) {
    $raw = base64_decode($data);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $key = hash("sha256", ENC_SECRET . $uid, true);
    return openssl_decrypt($enc, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}

function fetchFromDrive($token, $fileId) {
    $ch = curl_init("https://www.googleapis.com/drive/v3/files/$fileId?alt=media");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

/* ================= DRIVE TOKEN ================= */

$accessToken = getValidDriveAccessToken($uid);
if (!$accessToken) {
    die("Drive not connected");
}

/* ================= SINGLE FILE DOWNLOAD ================= */

if (count($files) === 1) {

    $file = $files[0];

    $enc = fetchFromDrive($accessToken, $file['engine_file_id']);
    $plain = decryptData($uid, $enc);

    if ($plain === false) {
        die("Decryption failed");
    }

    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"".$file['name']."\"");
    header("Content-Length: ".strlen($plain));

    echo $plain;
    logUserAction("Downloaded: " . $file['name']);

    exit;
}

/* ================= MULTI FILE ZIP ================= */

/* ================= MULTI FILE ZIP ================= */

$zipName = "download_" . date("Ymd_His") . ".zip";
$tmpDir  = __DIR__ . "/tmp_downloads";

if (!is_dir($tmpDir)) {
    die("Temp directory missing");
}

$tmpZip = $tmpDir . "/" . uniqid("dl_", true) . ".zip";

$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create ZIP file");
}

foreach ($files as $file) {

    $enc = fetchFromDrive($accessToken, $file['engine_file_id']);
    $plain = decryptData($uid, $enc);

    if ($plain !== false) {
        $zip->addFromString($file['name'], $plain);
    }
}

$zip->close();

/* ---- LOG ONCE ---- */
logUserAction("Downloaded (ZIP): " . implode(", ", array_column($files, 'name')));

/* ---- SEND ZIP ---- */
header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=\"$zipName\"");
header("Content-Length: " . filesize($tmpZip));

readfile($tmpZip);
unlink($tmpZip);
exit;
