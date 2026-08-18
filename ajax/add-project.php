<?php

/**
 * AJAX Handler: Add Project
 *
 * Creates a new project for the authenticated user with an active status.
 * Returns the new project data for immediate UI update.
 */

require_once __DIR__ . '/../init.php';

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
        'updated_at' => date('Y-m-d H:i:s'),
        'keywords_count' => 0,
        'total_keywords' => 0,
        'top_3' => [],
        'best_trend_7d' => null,
    ],
]);
