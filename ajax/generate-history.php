<?php

/**
 * AJAX Handler: Generate 30-Day Position History
 *
 * Seeds or re-seeds 30 days of historical position rankings for all keywords
 * in the current project. Uses the shared generatePositionHistory() function.
 */

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

try {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    if ($projectId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid project ID'], 400);
    }

    $userId = $_SESSION['user_id'] ?? 0;

    /* Verify project ownership */
    $project = $db->fetchOne(
        'SELECT `project_id`, `status` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
        [$projectId, $userId]
    );

    if (!$project) {
        jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
    }

    if ($project['status'] === 'archived') {
        jsonResponse(['success' => false, 'message' => 'Cannot generate history for an archived project'], 400);
    }

    /* Generate 30-day position history */
    $historyResult = generatePositionHistory($projectId);

    if ($historyResult['keywords_count'] === 0) {
        jsonResponse(['success' => false, 'message' => 'No keywords found. Add keywords first.'], 400);
    }

    /* Fetch updated keywords with positions, trends, and project stats */
    $keywordsData = getKeywordsWithPositions($projectId);
    $statsData = getProjectKeywordStats($projectId);

    jsonResponse([
        'success' => true,
        'message' => '30-day history generated successfully.',
        'data'    => [
            'keywords_count'    => $historyResult['keywords_count'],
            'records_generated' => $historyResult['records_generated'],
            'keywords'          => $keywordsData,
            'stats'             => $statsData,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
