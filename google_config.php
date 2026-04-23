<?php
/* =====================================================
   GOOGLE API CONFIGURATION
   ===================================================== */

/*
|--------------------------------------------------------------------------
| Google OAuth Credentials
|--------------------------------------------------------------------------
| Get these from:
| https://console.cloud.google.com/apis/credentials
|
| Project type: Web application
| Authorized redirect URI must match exactly
*/

define("GOOGLE_CLIENT_ID", "1086589968384-ar660i8qfqcat5tbid7qujj3petc510t.apps.googleusercontent.com");
define("GOOGLE_CLIENT_SECRET", "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");

/*
|--------------------------------------------------------------------------
| Redirect URI
|--------------------------------------------------------------------------
| This MUST be the same URL you set in Google Cloud Console
| Example (local):
| http://localhost/securedrive/google_callback.php
|
| Example (production):
| https://yourdomain.com/google_callback.php
*/

define("GOOGLE_REDIRECT_URI", "https://mitstest.ct.ws/test/mes/google_callback.php");

/*
|--------------------------------------------------------------------------
| Google Drive Scope (DO NOT CHANGE)
|--------------------------------------------------------------------------
| drive.file = app can access only files it created
| This is safer than full drive access
*/
define("GOOGLE_DRIVE_SCOPE", "https://www.googleapis.com/auth/drive.file");
