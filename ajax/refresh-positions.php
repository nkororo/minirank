<?php

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
    $pdo = $db->getPdo();

    /* Verify project ownership */
    $project = $db->fetchOne(
        'SELECT `project_id`, `status` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
        [$projectId, $userId]
    );

    if (!$project) {
        jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
    }

    if ($project['status'] === 'archived') {
        jsonResponse(['success' => false, 'message' => 'Cannot refresh positions for an archived project'], 400);
    }

    $demoKeywords = [
        'seo tools',
        'rank tracker',
        'best coffee machine',
        'online shop',
        'auto repair',
        'digital marketing',
        'web hosting',
        'keyword research',
        'analytics dashboard',
        'local seo',
    ];

    /* Check existing keywords for this project */
    $existing = $db->fetchAll(
        'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
        [$projectId]
    );

    if (empty($existing)) {
        /* No keywords — insert 10 demo keywords */
        foreach ($demoKeywords as $name) {
            $db->insert('keywords', [
                'project_id' => $projectId,
                'name'    => $name,
            ]);
        }

        $keywords = $db->fetchAll(
            'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
            [$projectId]
        );
    } else {
        $keywords = $existing;
    }

    $keywordsCount = count($keywords);
    $recordsGenerated = 0;

    // Generate 30-day position history inside a single transaction
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare(
        'INSERT INTO `positions` (`keyword_id`, `position`, `date`)
         VALUES (?, ?, ?)
         ON CONFLICT(`keyword_id`, `date`) DO UPDATE SET `position` = excluded.`position`'
    );

    for ($day = 0; $day < 30; $day++) {
        $date = date('Y-m-d', strtotime("-{$day} days"));

        foreach ($keywords as $kw) {
            $position = random_int(POSITION_MIN, POSITION_MAX);
            $insertStmt->execute([
                $kw['k_id'],
                $position,
                $date,
            ]);
            $recordsGenerated++;
        }
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Positions refreshed and 30-day history generated successfully.',
        'data'    => [
            'keywords_count'     => $keywordsCount,
            'records_generated'  => $recordsGenerated,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
