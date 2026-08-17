<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle ?? APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow mb-6">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=dashboard"
                class="text-xl font-bold text-blue-600"><?php echo sanitize(APP_NAME); ?></a>
            <div class="flex gap-4 items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=logout"
                        class="text-red-500 hover:underline">Logout</a>
                <?php else: ?>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=login"
                        class="text-gray-700 hover:text-blue-500">Login</a>
                    <a href="<?php echo sanitize(APP_URL); ?>/index.php?op=register"
                        class="text-gray-700 hover:text-blue-500">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main>
