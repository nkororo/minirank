<?php

/**
 * Shared Seeder Logic
 *
 * Provides seedProjectHistory() which inserts demo keywords (if none exist)
 * and generates 30 days of daily position history for all keywords in a project.
 */

/**
 * Seed demo keywords and generate 30-day position history for a project.
 *
 * - Inserts 10 demo keywords if the project has none.
 * - Generates position records from T-29 to today for each keyword.
 * - Returns summary counts for the caller to display or return.
 *
 * @param int $projectId The project to seed
 * @return array{keywords_count: int, records_generated: int, keywords_inserted: int}
 */
function seedProjectHistory(int $projectId): array
{
    global $db;

    /* Demo keywords to insert if none exist */
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

    $keywordsInserted = 0;

    if (empty($existing)) {
        /* Insert demo keywords */
        foreach ($demoKeywords as $name) {
            $db->insert('keywords', [
                'project_id' => $projectId,
                'name' => $name,
            ]);
        }
        $keywordsInserted = count($demoKeywords);
    }

    /* Generate 30-day position history */
    $historyResult = generatePositionHistory($projectId);

    return [
        'keywords_count'    => $historyResult['keywords_count'],
        'records_generated' => $historyResult['records_generated'],
        'keywords_inserted' => $keywordsInserted,
    ];
}
