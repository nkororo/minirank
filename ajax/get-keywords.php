<?php

/**
 * AJAX Handler: Get Keywords
 *
 * Returns the full list of keywords with positions and trends
 * for the currently selected project.
 */

require_once __DIR__ . '/../init.php';

/* Read project ID from session (set by Project::view()) */
$projectId = (int) ($_SESSION['project_id'] ?? 0);
$keywords = getKeywordsWithPositions($projectId);

jsonResponse(['success' => true, 'data' => $keywords]);
