<?php

require_once __DIR__ . '/../init.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    jsonResponse(['success' => false, 'message' => 'Keyword name is required'], 400);
}

global $db;
$kId = $db->insert('keywords', [
    'user_id' => $_SESSION['user_id'],
    'name' => $name,
]);

jsonResponse([
    'success' => true,
    'data' => [
        'k_id' => $kId,
        'name' => $name,
        'current_position' => 0,
        'trend' => 'stable',
    ],
]);
