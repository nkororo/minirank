<?php

/**
 * AJAX Handler: Edit Keyword
 *
 * Updates an existing keyword's name and timestamp. Verifies ownership
 * via the current project before modification.
 */

require_once __DIR__ . '/../init.php';

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$kId = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if ($kId <= 0 || $name === '') {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

global $db;
$projectId = $_SESSION['project_id'] ?? 0;

$keyword = $db->fetchOne(
    'SELECT `k_id` FROM `keywords` WHERE `k_id` = ? AND `project_id` = ?',
    [$kId, $projectId]
);

if (!$keyword) {
    jsonResponse(['success' => false, 'message' => 'Keyword not found'], 404);
}

$db->update('keywords', [
    'name' => $name,
    'updated_at' => date('Y-m-d H:i:s'),
], '`k_id` = ? AND `project_id` = ?', [$kId, $projectId]);

jsonResponse(['success' => true, 'data' => ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')]]);
