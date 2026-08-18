<?php

/**
 * Sanitize a string value for safe HTML output.
 *
 * Wraps htmlspecialchars() to prevent XSS when rendering dynamic user content.
 *
 * @param string $value The raw string to escape.
 * @return string The escaped string safe for HTML context.
 */
function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date string from YYYY-MM-DD to "DD Month YYYY".
 * Handles both DATE and DATETIME inputs.
 *
 * @param string $date A date string in Y-m-d or Y-m-d H:i:s format.
 * @return string The formatted date, or the original string on failure.
 */
function formatDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date('d F Y', $timestamp);
}

/**
 * Check if the given operation code is publicly accessible without authentication.
 *
 * @param string $op The operation code to check.
 * @return bool True if the operation is public, false otherwise.
 */
function isPublicOp(string $op): bool
{
    global $PUBLIC_OPS;
    return in_array($op, $PUBLIC_OPS, true);
}

/**
 * Generate or retrieve the CSRF token for the current session.
 *
 * Creates a cryptographically secure random token on first call, then
 * returns the cached value for subsequent uses within the same session.
 *
 * @return string The CSRF token string.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Send a JSON response and terminate execution.
 *
 * Sets the HTTP status code, Content-Type header, encodes the data
 * as JSON, and calls exit to halt further processing.
 *
 * @param array $data       The response payload to encode as JSON.
 * @param int   $statusCode HTTP status code (default 200).
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Get keywords with positions for a given project.
 *
 * Retrieves each keyword along with its latest position and the position from
 * 7 days ago (for trend calculation). Results are ordered by most recently updated.
 *
 * @param int $projectId The project to fetch keywords for.
 * @return array Array of keyword rows with 'current_position', 'previous_position', and 'trend'.
 */
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
 * Generate 30 days of position history (T-29 to today) for all keywords in a project.
 * Uses upsert to enforce uniqueness on (keyword_id, date).
 *
 * @return array{keywords_count: int, records_generated: int}
 */
function generatePositionHistory(int $projectId): array
{
    global $db;
    $pdo = $db->getPdo();

    /* Fetch all keywords for this project */
    $keywords = $db->fetchAll(
        'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
        [$projectId]
    );

    if (empty($keywords)) {
        return ['keywords_count' => 0, 'records_generated' => 0];
    }

    $keywordsCount = count($keywords);
    $recordsGenerated = 0;

    /* Deduplicate existing positions: keep only the latest row per (keyword_id, date) */
    $pdo->exec('
        DELETE FROM `positions` WHERE `p_id` NOT IN (
            SELECT MAX(`p_id`) FROM `positions` GROUP BY `keyword_id`, `date`
        )
    ');

    /* Generate 30-day position history inside a single transaction */
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

    return ['keywords_count' => $keywordsCount, 'records_generated' => $recordsGenerated];
}

/**
 * Get keyword statistics for a single project:
 * - total_keywords: exact count of keywords
 * - top_3: up to 3 keywords with best (lowest) position > 0
 * - best_trend_7d: keyword with best 7-day average rank, plus its avg score
 *
 * @param int $projectId The project to compute statistics for.
 * @return array{total_keywords: int, top_3: array, best_trend_7d: ?array}
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

    /* Top 3 keywords (lowest current position > 0) */
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
        LIMIT 3',
        [$projectId]
    );
    $top3 = [];
    foreach ($topRows as $row) {
        $top3[] = [
            'name' => $row['name'],
            'position' => (int) $row['position'],
        ];
    }

    /* 7-Day Best Trend: keyword with best (lowest) average position over last 7 days */
    $trendRow = $db->fetchOne(
        'SELECT
            k.`name`,
            ROUND(AVG(p.`position`), 1) AS `avg_position`
        FROM `keywords` k
        INNER JOIN `positions` p ON p.`keyword_id` = k.`k_id`
        WHERE k.`project_id` = ?
          AND p.`date` >= DATE(\'now\', \'-7 days\')
          AND p.`position` > 0
        GROUP BY k.`k_id`, k.`name`
        ORDER BY `avg_position` ASC
        LIMIT 1',
        [$projectId]
    );
    $bestTrend = null;
    if ($trendRow) {
        $bestTrend = [
            'name' => $trendRow['name'],
            'avg_position' => (float) $trendRow['avg_position'],
        ];
    }

    return [
        'total_keywords' => $totalKeywords,
        'top_3' => $top3,
        'best_trend_7d' => $bestTrend,
    ];
}
