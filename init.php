<?php

session_start();

require_once __DIR__ . '/parameters.php';

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

require_once __DIR__ . '/Functions.php';
require_once __DIR__ . '/arrays.php';
require_once __DIR__ . '/libraries/Database.php';

$db = new Database();

$currentOp = $_GET['op'] ?? 'dashboard';
if (!isset($_SESSION['user_id']) && !isPublicOp($currentOp)) {
    header('Location: ' . APP_URL . '/index.php?op=login');
    exit;
}
