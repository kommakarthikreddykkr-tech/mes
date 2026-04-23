<?php
require_once "core.php";
require_once "filesystem.php";
require_once "google_token_helper.php";

requireLogin();

$uid = currentUserId();

define("ENC_SECRET", "CHANGE_THIS_TO_RANDOM_SECRET");

/* ================= FILE ID ================= */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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

/* ================= EDITABLE TYPES ================= */

$editable = [
    'txt','md','html','css','js','php',
    'json','xml','csv'
];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $editable)) {
    die("This file cannot be edited");
}

/* ================= CODEMIRROR MODE ================= */

$cmModeMap = [
    'php'  => 'application/x-httpd-php',
    'js'   => 'javascript',
    'css'  => 'css',
    'html' => 'htmlmixed',
    'json' => 'application/json',
    'xml'  => 'xml',
    'md'   => 'markdown',
    'txt'  => 'text/plain',
    'csv'  => 'text/plain'
];

$cmMode = $cmModeMap[$ext] ?? 'text/plain';

/* ================= DRIVE TOKEN ================= */

$accessToken = getValidDriveAccessToken($uid);
if (!$accessToken) die("Drive not connected");

/* ================= HELPERS ================= */

function decryptData($uid, $data) {
    $raw = base64_decode($data);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $key = hash("sha256", ENC_SECRET . $uid, true);
    return openssl_decrypt($enc, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}

function encryptData($uid, $data) {
    $key = hash("sha256", ENC_SECRET . $uid, true);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
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

function uploadToDrive($token, $fileId, $data) {
    $ch = curl_init("https://www.googleapis.com/upload/drive/v3/files/$fileId?uploadType=media");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/octet-stream"
        ],
        CURLOPT_POSTFIELDS => $data
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/* ================= SAVE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    $enc = encryptData($uid, $content);
    uploadToDrive($accessToken, $file['engine_file_id'], $enc);
    logUserAction("Edited file: ".$file['name']);
    header("Location: dashboard.php");
    exit;
}

/* ================= LOAD ================= */

$encData = fetchFromDrive($accessToken, $file['engine_file_id']);
$plain = decryptData($uid, $encData);
if ($plain === false) die("Decryption failed");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit <?= e($file['name']) ?></title>

<link rel="stylesheet" href="assets/editor/codemirror.css">
<link rel="stylesheet" href="assets/editor/theme/dracula.css">
<link rel="stylesheet" href="assets/editor/addon/dialog/dialog.css">
<link rel="stylesheet" href="assets/editor/addon/fold/foldgutter.css">
<link rel="stylesheet" href="assets/editor/addon/display/fullscreen.css">

<style>
body{
    margin:0;
    height:100vh;
    display:flex;
    flex-direction:column;
    background:#1e1e1e;
}

.header{
    background:#111;
    color:#fff;
    padding:10px 16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

#editor-wrapper {
    flex: 1;                 /* TAKE ALL AVAILABLE SPACE */
    position: relative;
}

.CodeMirror {
    height: 100% !important; /* ✅ THIS MAKES IT FULL SCREEN */
}

button{
    padding:6px 12px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-weight:600;
}

.save{background:#22c55e;color:#000;}
.cancel{background:#ef4444;color:#fff;}

</style>

<script src="assets/editor/codemirror.js"></script>

<script src="assets/editor/addon/search/search.js"></script>
<script src="assets/editor/addon/search/searchcursor.js"></script>
<script src="assets/editor/addon/dialog/dialog.js"></script>
<script src="assets/editor/addon/edit/matchbrackets.js"></script>
<script src="assets/editor/addon/edit/closebrackets.js"></script>
<script src="assets/editor/addon/fold/foldcode.js"></script>
<script src="assets/editor/addon/fold/foldgutter.js"></script>
<script src="assets/editor/addon/fold/brace-fold.js"></script>
<script src="assets/editor/addon/display/fullscreen.js"></script>

<script src="assets/editor/mode/xml/xml.js"></script>
<script src="assets/editor/mode/javascript/javascript.js"></script>
<script src="assets/editor/mode/css/css.js"></script>
<script src="assets/editor/mode/htmlmixed/htmlmixed.js"></script>
<script src="assets/editor/mode/php/php.js"></script>
<script src="assets/editor/mode/markdown/markdown.js"></script>
<script src="assets/editor/mode/clike/clike.js"></script>

</head>
<body>

<div class="header">
    <div><?= e($file['name']) ?></div>
    <div>
        <button class="save" onclick="save()">Save (Ctrl+S)</button>
        <a href="dashboard.php"><button class="cancel">Close</button></a>
    </div>
</div>

<form method="post" id="editorForm" style="flex:1">
<div id="editor-wrapper">
    <textarea id="editor" name="content"><?= htmlspecialchars($plain) ?></textarea>
</div>

</form>

<script>
const editor = CodeMirror.fromTextArea(
    document.getElementById("editor"),
    {
        mode: "<?= $cmMode ?>",
        theme: "dracula",
        lineNumbers: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        lineWrapping: true,
        foldGutter: true,
        gutters: ["CodeMirror-linenumbers","CodeMirror-foldgutter"],
        indentUnit: 4,
        tabSize: 4,
        extraKeys: {
            "Ctrl-S": save,
            "Cmd-S": save,
            "F11": cm => cm.setOption("fullScreen", !cm.getOption("fullScreen")),
            "Esc": cm => cm.getOption("fullScreen") && cm.setOption("fullScreen", false),
            "Ctrl-F": "findPersistent",
            "Ctrl-H": "replace"
        }
    }
);
 document.addEventListener("keydown", function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        e.preventDefault();
        save();
    }
});


function save(){
    editor.save(); // ✅ CORRECT INSTANCE
    document.getElementById("editorForm").submit();
}

</script>

</body>
</html>
