<?php
require_once "core.php";
require_once "google_oauth.php";

/*
|--------------------------------------------------------------------------
| VALIDATE FLOW
|--------------------------------------------------------------------------
| This callback is ONLY valid immediately after registration
| We stored UID temporarily in session
*/

if (!isset($_SESSION['pending_google_uid'])) {
    die("Invalid Google OAuth flow");
}

$uid = $_SESSION['pending_google_uid'];
unset($_SESSION['pending_google_uid']);

/*
|--------------------------------------------------------------------------
| CHECK AUTH CODE
|--------------------------------------------------------------------------
*/
if (!isset($_GET['code'])) {
    die("Google authorization failed");
}

/*
|--------------------------------------------------------------------------
| EXCHANGE AUTH CODE FOR TOKENS
|--------------------------------------------------------------------------
*/
$token = googleExchangeCode($_GET['code']);

if (!isset($token['access_token'])) {
    die("Failed to obtain Google token");
}

$accessToken  = $token['access_token'];
$refreshToken = $token['refresh_token'] ?? null;
$expiresAt    = date("Y-m-d H:i:s", time() + ($token['expires_in'] ?? 0));

/*
|--------------------------------------------------------------------------
| CREATE ROOT FOLDER IN GOOGLE DRIVE
|--------------------------------------------------------------------------
| This folder will contain ALL encrypted files for this user
*/
$ch = curl_init("https://www.googleapis.com/drive/v3/files");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "name"     => "SecureDrive",
        "mimeType"=> "application/vnd.google-apps.folder"
    ])
]);

$response = curl_exec($ch);
curl_close($ch);

$folder = json_decode($response, true);

if (!isset($folder['id'])) {
    die("Failed to create Drive folder");
}

$driveRootId = $folder['id'];

/*
|--------------------------------------------------------------------------
| STORE TOKENS IN DATABASE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "INSERT INTO user_drive_tokens
     (uid, access_token, refresh_token, expires_at, drive_root_id)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param(
    "issss",
    $uid,
    $accessToken,
    $refreshToken,
    $expiresAt,
    $driveRootId
);
$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| AUTO LOGIN USER
|--------------------------------------------------------------------------
*/
$_SESSION['uid'] = $uid;

/*
|--------------------------------------------------------------------------
| LOG ACTION
|--------------------------------------------------------------------------
*/
logUserAction("Connected Google Drive");

/*
|--------------------------------------------------------------------------
| REDIRECT TO DASHBOARD
|--------------------------------------------------------------------------
*/
header("Location: dashboard.php");
exit;
