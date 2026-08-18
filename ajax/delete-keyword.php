<?php

/**
 * AJAX Handler: Delete Keyword
 *
 * Deletes a keyword and all its associated position history inside a transaction.
 * Returns the updated keyword list and stats for immediate UI refresh.
 */

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$kId = (int) ($_POST['id'] ?? 0);
if ($kId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid keyword ID'], 400);
}

global $db;
$pdo = $db->getPdo();
$projectId = $_SESSION['project_id'] ?? 0;

/* Verify keyword belongs to this project before deletion */
$keyword = $db->fetchOne(
    'SELECT `k_id` FROM `keywords` WHERE `k_id` = ? AND `project_id` = ?',
    [$kId, $projectId]
);

if (!$keyword) {
    jsonResponse(['success' => false, 'message' => 'Keyword not found'], 404);
}

try {
    /* Delete keyword and its positions inside a transaction */
    $pdo->beginTransaction();

    /* Delete all position history for this keyword */
    $db->delete('positions', '`keyword_id` = ?', [$kId]);

    /* Delete the keyword itself */
    $db->delete('keywords', '`k_id` = ? AND `project_id` = ?', [$kId, $projectId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

/* Fetch updated keywords with positions, trends, and project stats */
$keywordsData = getKeywordsWithPositions($projectId);
$statsData = getProjectKeywordStats($projectId);

jsonResponse([
    'success' => true,
    'message' => 'Keyword deleted successfully.',
    'data'    => [
        'keywords' => $keywordsData,
        'stats'    => $statsData,
    ],
]);
