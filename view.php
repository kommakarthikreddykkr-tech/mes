<?php
require_once "core.php";
require_once "filesystem.php";

requireLogin();
$uid = currentUserId();

define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");

/* ================= BACK ================= */
$back = $_GET['back'] ?? 'dashboard.php';
$back = htmlspecialchars($back, ENT_QUOTES);

/* ================= HELPERS ================= */

function decryptData($uid, $data) {
    $raw = base64_decode($data);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $key = hash("sha256", ENC_SECRET . $uid, true);
    return openssl_decrypt($enc, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}

function getDriveToken($uid) {
    global $conn;
    $stmt = $conn->prepare(
        "SELECT access_token FROM user_drive_tokens WHERE uid=?"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $t['access_token'] ?? null;
}

function fetchFromDrive($token, $fileId) {
    $ch = curl_init("https://www.googleapis.com/drive/v3/files/$fileId?alt=media");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $token"]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function mimeFromExtension($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match($ext) {
        'jpg','jpeg' => 'image/jpeg',
        'png'        => 'image/png',
        'gif'        => 'image/gif',
        'webp'       => 'image/webp',
        'pdf'        => 'application/pdf',
        'mp4'        => 'video/mp4',
        'mp3'        => 'audio/mpeg',
        'txt','md'   => 'text/plain',
        default      => null
    };
}

/* ================= INPUT ================= */

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("Invalid file");

/* ================= FILE INFO ================= */

$stmt = $conn->prepare(
    "SELECT n.name, s.engine_file_id
     FROM fs_nodes n
     JOIN fs_storage s ON s.id = n.storage_id
     WHERE n.id=? AND n.uid=? AND n.type='file' AND n.is_deleted=0"
);
$stmt->bind_param("ii", $id, $uid);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) die("File not found");

$mime = mimeFromExtension($file['name']);
if (!$mime) die("This file cannot be viewed");

/* ================= LOAD FILE ================= */

$token = getDriveToken($uid);
if (!$token) die("Drive not connected");

$encData = fetchFromDrive($token, $file['engine_file_id']);
$content = decryptData($uid, $encData);
if ($content === false) die("Decryption failed");

/* ================= BASE64 ================= */

$base64 = base64_encode($content);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= e($file['name']) ?></title>

<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden; /* 🔒 NO SCROLL ANYWHERE */
}

body{
    background:#000;
    display:flex;
    flex-direction:column;
}

.header{
    background:#111;
    color:#fff;
    padding:10px 16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-shrink: 0;
}

.viewer{
    flex:1;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#000;
    overflow:hidden; /* 🔒 NO SCROLL */
}

/* IMAGES */
.viewer img{
    max-width:100%;
    max-height:100%;
    object-fit:contain;
}

/* VIDEO */
.viewer video{
    max-width:100%;
    max-height:100%;
    object-fit:contain;
}

/* AUDIO */
.viewer audio{
    width:80%;
}

/* PDF */
.viewer iframe{
    width:100%;
    height:100%;
    border:none;
}

/* TEXT */
.viewer pre{
    width:100%;
    height:100%;
    margin:0;
    padding:20px;
    box-sizing:border-box;
    overflow:auto; /* only text can scroll */
    color:#eee;
    background:#000;
}

</style>
</head>
<body>

<div class="header">
    <div><?= e($file['name']) ?></div>
    <div>
        <a href="<?= $back ?>"><button class="close">✖ Close</button></a>
    </div>
</div>

<div class="viewer">
<?php if (str_starts_with($mime, 'image/')): ?>
    <img src="data:<?= $mime ?>;base64,<?= $base64 ?>">
<?php elseif ($mime === 'application/pdf'): ?>
    <iframe src="data:application/pdf;base64,<?= $base64 ?>"></iframe>
<?php elseif (str_starts_with($mime, 'video/')): ?>
    <video controls src="data:<?= $mime ?>;base64,<?= $base64 ?>"></video>
<?php elseif (str_starts_with($mime, 'audio/')): ?>
    <audio controls src="data:<?= $mime ?>;base64,<?= $base64 ?>"></audio>
<?php else: ?>
    <pre><?= htmlspecialchars($content) ?></pre>
<?php endif; ?>
</div>

<script>
document.addEventListener("keydown", e => {
    if (e.key === "Escape") {
        window.location.href = "<?= $back ?>";
    }
});
</script>

</body>
</html>
