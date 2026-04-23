<?php
/* ================= BASIC CONFIG ================= */

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials



/* ================= DATABASE CONNECTION ================= */

define("DB_HOST", "sql100.infinityfree.com");   // 👈 exact from panel
define("DB_USER", "if0_40756525");               // 👈 exact
define("DB_PASS", "HuGoiDeWVa1");      // 👈 exact
define("DB_NAME", "if0_40756525_mes");            // 👈 exact

/* ================= CONNECTION ================= */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed");
}
if ($conn->connect_error) {
    die("Database connection failed");
}

// Charset
$conn->set_charset("utf8mb4");

/* ================= AUTH HELPERS ================= */

// Check login
function isLoggedIn() {
    return isset($_SESSION['uid']);
}

// Force login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: auth.php");
        exit;
    }
}

// Current user id
function currentUserId() {
    return $_SESSION['uid'] ?? null;
}

// Current user data
function currentUser() {
    global $conn;

    if (!isLoggedIn()) return null;

    $stmt = $conn->prepare(
        "SELECT id, username, email, blocked
         FROM users
         WHERE id=?"
    );
    $stmt->bind_param("i", $_SESSION['uid']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user;
}

/* ================= LOGGING ================= */

// User action log
function logUserAction($action) {
    global $conn;

    if (!isLoggedIn()) return;

    $uid = $_SESSION['uid'];
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $conn->prepare(
        "INSERT INTO user_action_logs (uid, username, ip, action)
         VALUES (?, (SELECT username FROM users WHERE id=?), ?, ?)"
    );
    $stmt->bind_param("iiss", $uid, $uid, $ip, $action);
    $stmt->execute();
    $stmt->close();
}

// Login log
function logUserLogin($action = "login") {
    global $conn;

    if (!isLoggedIn()) return;

    $uid = $_SESSION['uid'];
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $conn->prepare(
        "INSERT INTO user_login_logs (uid, username, ip, action)
         VALUES (?, (SELECT username FROM users WHERE id=?), ?, ?)"
    );
    $stmt->bind_param("iiss", $uid, $uid, $ip, $action);
    $stmt->execute();
    $stmt->close();
}

// Admin log (future-safe)
function logAdminAction($aid, $username, $action) {
    global $conn;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $conn->prepare(
        "INSERT INTO admin_action_logs (aid, username, ip, action)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $aid, $username, $ip, $action);
    $stmt->execute();
    $stmt->close();
}

/* ================= SECURITY ================= */

// Escape output (XSS safe)
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
