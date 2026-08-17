<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$projectId = (int) ($_POST['project_id'] ?? 0);
if ($projectId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid project ID'], 400);
}

global $db;
$userId = $_SESSION['user_id'];

$project = $db->fetchOne(
    'SELECT `project_id`, `status` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
    [$projectId, $userId]
);

if (!$project) {
    jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
}

$newStatus = $project['status'] === 'active' ? 'archived' : 'active';
$db->update('projects', [
    'status' => $newStatus,
    'updated_at' => date('Y-m-d H:i:s'),
], '`project_id` = ? AND `user_id` = ?', [$projectId, $userId]);

jsonResponse(['success' => true, 'data' => ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]]);
