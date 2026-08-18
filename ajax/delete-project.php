<?php

/**
 * AJAX Handler: Delete Project
 *
 * Permanently deletes a project, all its keywords, and all associated position
 * history inside a single transaction. Returns updated projects list and stats.
 */

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
$pdo = $db->getPdo();
$userId = $_SESSION['user_id'];

/* Verify project ownership */
$project = $db->fetchOne(
    'SELECT `project_id` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
    [$projectId, $userId]
);

if (!$project) {
    jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
}

try {
    /* Delete project, keywords, and positions inside a transaction */
    $pdo->beginTransaction();

    /* Delete all positions for keywords belonging to this project */
    $db->query(
        'DELETE FROM `positions` WHERE `keyword_id` IN (
            SELECT `k_id` FROM `keywords` WHERE `project_id` = ?
        )',
        [$projectId]
    );

    /* Delete all keywords belonging to this project */
    $db->delete('keywords', '`project_id` = ?', [$projectId]);

    /* Delete the project itself */
    $db->delete('projects', '`project_id` = ? AND `user_id` = ?', [$projectId, $userId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/* Fetch updated projects list for the dashboard */
$projects = $db->fetchAll(
    'SELECT
        p.`project_id`,
        p.`name`,
        p.`domain`,
        p.`status`,
        p.`updated_at`,
        COUNT(k.`k_id`) AS `keywords_count`
    FROM `projects` p
    LEFT JOIN `keywords` k ON k.`project_id` = p.`project_id`
    WHERE p.`user_id` = ?
    GROUP BY
        p.`project_id`,
        p.`name`,
        p.`domain`,
        p.`status`,
        p.`updated_at`
    ORDER BY p.`updated_at` DESC',
    [$userId]
);

jsonResponse([
    'success' => true,
    'message' => 'Project deleted successfully.',
    'data'    => [
        'projects' => $projects,
    ],
]);
