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

define("GOOGLE_CLIENT_ID", "---GOOGLE_CLIENT_ID---");
define("GOOGLE_CLIENT_SECRET", "---GOOGLE_CLIENT_SECRET---");

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

define("GOOGLE_REDIRECT_URI", "---GOOGLE_REDIRECT_URI---");

/*
|--------------------------------------------------------------------------
| Google Drive Scope (DO NOT CHANGE)
|--------------------------------------------------------------------------
| drive.file = app can access only files it created
| This is safer than full drive access
*/
define("GOOGLE_DRIVE_SCOPE", "---GOOGLE_DRIVE_SCOPE---");
