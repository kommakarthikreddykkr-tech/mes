<?php
require_once "google_config.php";

/*
  Returns a valid access token (auto refreshes if expired)
*/
function getValidDriveAccessToken($uid) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT access_token, refresh_token, expires_at
         FROM user_drive_tokens WHERE uid=?"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    // Token still valid
    if (strtotime($row['expires_at']) > time() + 60) {
        return $row['access_token'];
    }

    // Refresh token
    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            "client_id"     => GOOGLE_CLIENT_ID,
            "client_secret" => GOOGLE_CLIENT_SECRET,
            "refresh_token" => $row['refresh_token'],
            "grant_type"    => "refresh_token"
        ])
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $token = json_decode($res, true);

    if (!isset($token['access_token'])) {
        return null;
    }

    $newAccessToken = $token['access_token'];
    $expiresAt = date("Y-m-d H:i:s", time() + $token['expires_in']);

    // Update DB
    $stmt = $conn->prepare(
        "UPDATE user_drive_tokens
         SET access_token=?, expires_at=?
         WHERE uid=?"
    );
    $stmt->bind_param("ssi", $newAccessToken, $expiresAt, $uid);
    $stmt->execute();
    $stmt->close();

    return $newAccessToken;
}
