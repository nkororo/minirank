<?php

/**
 * AJAX Handler: Generate 30-Day Position History
 *
 * Seeds or re-seeds 30 days of historical position rankings for all keywords
 * in the current project. Uses the shared seedProjectHistory() function.
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../libraries/seeder.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

try {
    $projectId = (int) ($_SESSION['project_id'] ?? 0);
    if ($projectId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'No project selected. Navigate to a project first.',
        ], 400);
    }

    $userId = $_SESSION['user_id'] ?? 0;

    /* Verify project ownership */
    $project = $db->fetchOne(
        'SELECT `project_id`, `status` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
        [$projectId, $userId]
    );

    if (!$project) {
        jsonResponse([
            'success' => false,
            'message' => 'Project not found or access denied.',
        ], 404);
    }

    if ($project['status'] === 'archived') {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot generate history for an archived project. Restore it first.',
        ], 400);
    }

    /* Execute shared seeder logic */
    $historyResult = seedProjectHistory($projectId);

    if ($historyResult['keywords_count'] === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'No keywords found for this project. Add keywords before generating history.',
        ], 400);
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
