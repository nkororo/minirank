<?php

/**
 * AJAX Handler: Get Keywords
 *
 * Returns the full list of keywords with positions and trends
 * for the currently selected project.
 */

require_once __DIR__ . '/../init.php';
$keywords = getKeywordsWithPositions($projectId);

jsonResponse(['success' => true, 'data' => $keywords]);
