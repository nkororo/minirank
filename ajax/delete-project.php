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
    'SELECT `project_id` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
    [$projectId, $userId]
);

if (!$project) {
    jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
}

/* Delete positions for all keywords in this project */
$keywordIds = $db->fetchAll(
    'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
    [$projectId]
);

foreach ($keywordIds as $kw) {
    $db->delete('positions', '`keyword_id` = ?', [$kw['k_id']]);
}

/* Delete keywords in this project */
$db->delete('keywords', '`project_id` = ?', [$projectId]);

/* Delete the project itself */
$db->delete('projects', '`project_id` = ? AND `user_id` = ?', [$projectId, $userId]);

jsonResponse(['success' => true, 'data' => null]);
