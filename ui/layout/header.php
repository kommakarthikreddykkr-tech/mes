<?php
/* ui/layout/header.php */
/* Variables expected:
   - $user
   - $view
   - $current
   - $parent
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SecureDrive</title>

<link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2 class="logo">SecureDrive</h2>

    <a class="nav-btn <?= $view === 'files' ? 'active' : '' ?>"
       href="dashboard.php">
        📁 My Files
    </a>

    <a class="nav-btn <?= $view === 'trash' ? 'active' : '' ?>"
       href="dashboard.php?view=trash">
        🗑 Trash
    </a>

    <a class="nav-btn <?= $view === 'logs' ? 'active' : '' ?>"
       href="dashboard.php?view=logs">
        📜 Logs
    </a>
</div>

<!-- MAIN -->
<div class="main">

<!-- TOP BAR -->
<div class="topbar">
    <div class="top-left">
        <?php if ($current !== null && $view === 'files'): ?>
            <a class="back-link"
               href="dashboard.php<?= $parent !== null ? '?f=' . $parent : '' ?>">
                ⬅ Back
            </a>
        <?php else: ?>
            <strong><?= ucfirst($view) ?></strong>
        <?php endif; ?>
    </div>

    <div class="top-right">
        <?= e($user['username']) ?>
        |
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- CONTENT -->
<div class="content">
