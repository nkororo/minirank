<?php

/**
 * AJAX Handler: Get Project Statistics
 *
 * Returns keyword statistics for the currently selected project,
 * including total count, top 3 keywords, and best 7-day trend.
 */

require_once __DIR__ . '/../init.php';

$projectId = $_SESSION['project_id'] ?? 0;
if ($projectId <= 0) {
    jsonResponse(['success' => false, 'message' => 'No project selected'], 400);
}

$stats = getProjectKeywordStats($projectId);
jsonResponse(['success' => true, 'data' => $stats]);
