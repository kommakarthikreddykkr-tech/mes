<?php
require_once "core.php";
require_once "filesystem.php";

requireLogin();

$user = currentUser();
$uid  = $user['id'];

/* ================= VIEW STATE ================= */

$view = $_GET['view'] ?? 'files';   // files | trash | logs
$current = isset($_GET['f']) ? (int)$_GET['f'] : null;
$parent  = $current !== null ? fs_parent($uid, $current) : null;

/* ================= DATA ================= */

if ($view === 'trash') {
    $nodes = fs_trash_list($uid);
} elseif ($view === 'logs') {
    $logs = fs_logs($uid);
} else {
    $nodes = fs_list($uid, $current);
}

/* ================= LAYOUT ================= */

require "ui/layout/header.php";

/* ================= CONTENT ================= */

if ($view === 'files') {

    require "ui/files/toolbar.php";
    require "ui/files/list.php";

} elseif ($view === 'trash') {

    require "ui/trash/list.php";

} elseif ($view === 'logs') {

    echo '<div class="files">';
    if ($logs->num_rows === 0) {
        echo '<div class="empty">No logs</div>';
    } else {
        while ($l = $logs->fetch_assoc()) {
            echo '<div class="row">';
            echo '<div></div>';
            echo '<div class="name">' . e($l['action']) . '</div>';
            echo '<div>' . e($l['created_at']) . '</div>';
            echo '<div>' . e($l['ip']) . '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
}

/* ================= MODALS ================= */

require "ui/modals/all.php";

/* ================= FOOTER ================= */

require "ui/layout/footer.php";
