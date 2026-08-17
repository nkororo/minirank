<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

try {
    $userId = $_SESSION['user_id'] ?? 1;
    $pdo = $db->getPdo();

    // Default demo keywords to seed when user has none
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

    // Check existing keywords for this user
    $existing = $db->fetchAll(
        'SELECT `k_id` FROM `keywords` WHERE `user_id` = ?',
        [$userId]
    );

    if (empty($existing)) {
        // Case A: No keywords — insert 10 demo keywords
        foreach ($demoKeywords as $name) {
            $db->insert('keywords', [
                'user_id' => $userId,
                'name'    => $name,
            ]);
        }

        $keywords = $db->fetchAll(
            'SELECT `k_id` FROM `keywords` WHERE `user_id` = ?',
            [$userId]
        );
    } else {
        // Case B: Keywords already exist — use them
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
