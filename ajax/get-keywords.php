<?php

require_once __DIR__ . '/../init.php';

$projectId = $_SESSION['project_id'] ?? 0;
$keywords = getKeywordsWithPositions($projectId);

jsonResponse(['success' => true, 'data' => $keywords]);
