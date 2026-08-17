<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle ?? APP_NAME); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <link rel="stylesheet" href="<?php echo sanitize(APP_URL); ?>/assets/styles.css">
    <script src="<?php echo sanitize(APP_URL); ?>/assets/TablePaginator.js" defer></script>
    <?php
    /* Load page-specific JavaScript */
    $currentOp = $_GET['op'] ?? 'dashboard';
    if ($currentOp === 'project') {
        echo '<script src="' . sanitize(APP_URL) . '/assets/script.js" defer></script>';
    } elseif ($currentOp === 'dashboard' || in_array($currentOp, ['add_project', 'archive_project', 'delete_project'], true)) {
        echo '<script src="' . sanitize(APP_URL) . '/assets/dashboard.js" defer></script>';
    }
    ?>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=dashboard"
                class="navbar-brand"><?php echo sanitize(APP_NAME); ?></a>
            <div class="navbar-links">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=logout"
                        class="link link--danger">Logout</a>
                <?php else: ?>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=login"
                        class="link link--muted">Login</a>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=register"
                        class="link link--muted">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main>
