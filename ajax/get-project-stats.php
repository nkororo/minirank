<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$projectId = $_SESSION['project_id'] ?? 0;
if ($projectId <= 0) {
    jsonResponse(['success' => false, 'message' => 'No project selected'], 400);
}

$stats = getProjectKeywordStats($projectId);
jsonResponse(['success' => true, 'data' => $stats]);
