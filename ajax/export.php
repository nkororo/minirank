<?php

/**
 * AJAX Handler: Export Keywords to CSV
 *
 * Generates a CSV file download containing all keywords with their
 * current positions and trend indicators for the authenticated user.
 * Requires a valid session and project_id GET parameter.
 */

require_once __DIR__ . '/../init.php';

global $db;

/* Fetch and validate project_id from GET */
$projectId = (int) ($_GET['project_id'] ?? 0);

if ($projectId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid project_id.';
    exit;
}

/* Verify project exists and belongs to authenticated user */
$userId = $_SESSION['user_id'];
$project = $db->fetchOne(
    'SELECT `project_id`, `name` FROM `projects` WHERE `project_id` = ? AND `user_id` = ?',
    [$projectId, $userId]
);

if (!$project) {
    http_response_code(403);
    echo 'Project not found or access denied.';
    exit;
}

/* Fetch keywords with latest position, 7-day history, and trend */
$keywords = getKeywordsWithPositions($projectId);

/* Build sanitized filename: keywords_{projectName}_{date}.csv */
$sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']);
$filename = 'keywords_' . $sanitizedName . '_' . date('Y-m-d') . '.csv';

/* Stream CSV with UTF-8 BOM */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($output, ['Keyword', 'Position', 'Trend']);

foreach ($keywords as $kw) {
    fputcsv($output, [
        $kw['name'],
        $kw['current_position'] ?? '-',
        $kw['trend'],
    ]);
}

fclose($output);
exit;
