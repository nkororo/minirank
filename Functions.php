<?php

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date string from YYYY-MM-DD to "DD Month YYYY".
 * Handles both DATE and DATETIME inputs.
 */
function formatDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date('d F Y', $timestamp);
}

function isPublicOp(string $op): bool
{
    global $PUBLIC_OPS;
    return in_array($op, $PUBLIC_OPS, true);
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function getKeywordsWithPositions(int $projectId): array
{
    global $db;

    $rows = $db->fetchAll(
        'SELECT
            k.`k_id`,
            k.`name`,
            k.`updated_at`,
            latest.`position` AS `current_position`,
            week_ago.`position` AS `previous_position`
        FROM `keywords` k
        LEFT JOIN (
            SELECT `keyword_id`, `position`,
                   ROW_NUMBER() OVER (PARTITION BY `keyword_id` ORDER BY `date` DESC) AS `rn`
            FROM `positions`
        ) latest ON latest.`keyword_id` = k.`k_id` AND latest.`rn` = 1
        LEFT JOIN (
            SELECT `keyword_id`, `position`,
                   ROW_NUMBER() OVER (PARTITION BY `keyword_id` ORDER BY `date` DESC) AS `rn`
            FROM `positions`
            WHERE `date` <= DATE(\'now\', \'-7 days\')
        ) week_ago ON week_ago.`keyword_id` = k.`k_id` AND week_ago.`rn` = 1
        WHERE k.`project_id` = ?
        ORDER BY k.`updated_at` DESC',
        [$projectId]
    );

    foreach ($rows as &$row) {
        $row['trend'] = calculateTrend($row['current_position'], $row['previous_position']);
    }

    return $rows;
}

/**
 * Calculate the 7-day trend for a keyword.
 *
 * @param  ?int  $current     Latest position (null/0 = dropped out)
 * @param  ?int  $sevenDaysAgo Position from 7 days ago (null/0 = no history)
 * @return string 'improved', 'declined', or 'stable'
 */
function calculateTrend(?int $current, ?int $sevenDaysAgo): string
{
    $hasCurrent = $current !== null && $current > 0;
    $hasPrevious = $sevenDaysAgo !== null && $sevenDaysAgo > 0;

    /* New entry: had no position 7 days ago, now ranked */
    if (!$hasPrevious && $hasCurrent) {
        return 'improved';
    }

    /* Dropped out: was ranked 7 days ago, now null/0 */
    if ($hasPrevious && !$hasCurrent) {
        return 'declined';
    }

    /* Both null/0 or both equal: stable */
    if (!$hasCurrent && !$hasPrevious) {
        return 'stable';
    }

    if ($current < $sevenDaysAgo) {
        return 'improved';
    }

    if ($current > $sevenDaysAgo) {
        return 'declined';
    }

    return 'stable';
}

/**
 * Get keyword statistics for a single project:
 * - total_keywords: exact count of keywords
 * - top_keywords: up to 5 keywords with best (lowest) position > 0
 * - best_trending: keyword name with most positive trend in last 30 days
 */
function getProjectKeywordStats(int $projectId): array
{
    global $db;

    /* Total keyword count */
    $countRow = $db->fetchOne(
        'SELECT COUNT(`k_id`) AS `total_keywords`
         FROM `keywords`
         WHERE `project_id` = ?',
        [$projectId]
    );
    $totalKeywords = $countRow ? (int) $countRow['total_keywords'] : 0;

    /* Top 5 keywords (lowest current position > 0) */
    $topRows = $db->fetchAll(
        'SELECT
            k.`name`,
            latest.`position`
        FROM `keywords` k
        INNER JOIN (
            SELECT `keyword_id`, `position`,
                   ROW_NUMBER() OVER (PARTITION BY `keyword_id` ORDER BY `date` DESC) AS `rn`
            FROM `positions`
        ) latest ON latest.`keyword_id` = k.`k_id` AND latest.`rn` = 1
        WHERE k.`project_id` = ?
          AND latest.`position` > 0
        ORDER BY latest.`position` ASC
        LIMIT 5',
        [$projectId]
    );
    $topKeywords = [];
    foreach ($topRows as $row) {
        $topKeywords[] = [
            'name' => $row['name'],
            'position' => (int) $row['position'],
        ];
    }

    /* Best trending keyword (most improved in last 30 days) */
    $trendRow = $db->fetchOne(
        'SELECT
            k.`name`,
            cur.`position` AS `current_position`,
            old.`position` AS `old_position`
        FROM `keywords` k
        INNER JOIN (
            SELECT `keyword_id`, `position`,
                   ROW_NUMBER() OVER (PARTITION BY `keyword_id` ORDER BY `date` DESC) AS `rn`
            FROM `positions`
        ) cur ON cur.`keyword_id` = k.`k_id` AND cur.`rn` = 1
        LEFT JOIN (
            SELECT `keyword_id`, `position`,
                   ROW_NUMBER() OVER (PARTITION BY `keyword_id` ORDER BY `date` DESC) AS `rn`
            FROM `positions`
            WHERE `date` <= DATE(\'now\', \'-30 days\')
        ) old ON old.`keyword_id` = k.`k_id` AND old.`rn` = 1
        WHERE k.`project_id` = ?
          AND cur.`position` IS NOT NULL
          AND old.`position` IS NOT NULL
        ORDER BY (old.`position` - cur.`position`) DESC
        LIMIT 1',
        [$projectId]
    );
    $bestTrending = $trendRow ? $trendRow['name'] : null;

    return [
        'total_keywords' => $totalKeywords,
        'top_keywords' => $topKeywords,
        'best_trending' => $bestTrending,
    ];
}
