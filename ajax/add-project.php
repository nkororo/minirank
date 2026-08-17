<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$name = trim($_POST['name'] ?? '');
$domain = trim($_POST['domain'] ?? '');

if ($name === '') {
    jsonResponse(['success' => false, 'message' => 'Project name is required'], 400);
}

global $db;
$userId = $_SESSION['user_id'];

$projectId = $db->insert('projects', [
    'user_id' => $userId,
    'name' => $name,
    'domain' => $domain,
    'status' => 'active',
]);

jsonResponse([
    'success' => true,
    'data' => [
        'project_id' => $projectId,
        'name' => $name,
        'domain' => $domain,
        'status' => 'active',
        'keywords_count' => 0,
    ],
]);
