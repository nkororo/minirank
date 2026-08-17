<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    jsonResponse(['success' => false, 'message' => 'Keyword name is required'], 400);
}

global $db;
$kId = $db->insert('keywords', [
    'project_id' => $_SESSION['project_id'] ?? 0,
    'name' => $name,
]);

jsonResponse([
    'success' => true,
    'data' => [
        'k_id' => $kId,
        'name' => $name,
        'updated_at' => date('Y-m-d H:i:s'),
        'current_position' => 0,
        'trend' => 'stable',
    ],
]);
