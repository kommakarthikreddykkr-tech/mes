<?php
require_once "google_config.php";

/* =====================================================
   GOOGLE OAUTH HELPERS
   ===================================================== */

/**
 * Generate Google OAuth authorization URL
 * Used during registration / first-time Drive connect
 */
function googleAuthUrl() {

    $params = [
        "client_id"     => GOOGLE_CLIENT_ID,
        "redirect_uri"  => GOOGLE_REDIRECT_URI,
        "response_type" => "code",
        "scope"         => "https://www.googleapis.com/auth/drive.file",
        "access_type"  => "offline",
        "prompt"       => "consent"
    ];

    return "https://accounts.google.com/o/oauth2/v2/auth?"
           . http_build_query($params);
}

/**
 * Exchange authorization code for access + refresh tokens
 */
function googleExchangeCode($code) {

    $post = [
        "code"          => $code,
        "client_id"     => GOOGLE_CLIENT_ID,
        "client_secret" => GOOGLE_CLIENT_SECRET,
        "redirect_uri"  => GOOGLE_REDIRECT_URI,
        "grant_type"    => "authorization_code"
    ];

    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($post),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/x-www-form-urlencoded"
        ]
    ]);

    $res = curl_exec($ch);

    if ($res === false) {
        curl_close($ch);
        return [];
    }

    curl_close($ch);
    return json_decode($res, true);
}
