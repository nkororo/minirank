<?php

/**
 * AJAX Handler: Refresh Today's Positions
 *
 * For each existing keyword in the project, generates a single new position
 * for today's date. Uses upsert to enforce uniqueness on (keyword_id, date).
 */

require_once __DIR__ . '/../init.php';

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
    $pdo = $db->getPdo();

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
            'message' => 'Cannot refresh positions for an archived project. Restore it first.',
        ], 400);
    }

    /* Fetch existing keywords for this project */
    $keywords = $db->fetchAll(
        'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
        [$projectId]
    );

    if (empty($keywords)) {
        jsonResponse([
            'success' => false,
            'message' => 'No keywords found for this project. Add keywords first.',
        ], 400);
    }

    $keywordsCount = count($keywords);
    $recordsGenerated = 0;
    $today = date('Y-m-d');

    /* Upsert today's position for each keyword */
    $insertStmt = $pdo->prepare(
        'INSERT INTO `positions` (`keyword_id`, `position`, `date`)
         VALUES (?, ?, ?)
         ON CONFLICT(`keyword_id`, `date`) DO UPDATE SET `position` = excluded.`position`'
    );

    $pdo->beginTransaction();

    foreach ($keywords as $kw) {
        $position = random_int(POSITION_MIN, POSITION_MAX);
        $insertStmt->execute([
            $kw['k_id'],
            $position,
            $today,
        ]);
        $recordsGenerated++;
    }

    $pdo->commit();

    /* Fetch updated keywords with positions, trends, and project stats */
    $keywordsData = getKeywordsWithPositions($projectId);
    $statsData = getProjectKeywordStats($projectId);

    jsonResponse([
        'success' => true,
        'message' => 'Today\'s positions refreshed successfully.',
        'data'    => [
            'keywords_count'    => $keywordsCount,
            'records_generated' => $recordsGenerated,
            'keywords'          => $keywordsData,
            'stats'             => $statsData,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
