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
        ORDER BY k.`created_at` DESC',
        [$projectId]
    );

    foreach ($rows as &$row) {
        $row['trend'] = calculateTrend($row['current_position'], $row['previous_position']);
    }

    return $rows;
}

function calculateTrend(?int $current, ?int $previous): string
{
    if ($current === null || $previous === null) {
        return 'stable';
    }

    if ($current < $previous) {
        return 'improved';
    }

    if ($current > $previous) {
        return 'declined';
    }

    return 'stable';
}
