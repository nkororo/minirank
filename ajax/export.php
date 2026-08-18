<?php

/**
 * AJAX Handler: Export Keywords to CSV
 *
 * Generates a CSV file download containing all keywords with their
 * current positions and trend indicators for the authenticated user.
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=keywords_export_' . date('Y-m-d') . '.csv');

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
