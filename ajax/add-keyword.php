<?php

require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    jsonResponse(['success' => false, 'message' => 'Keyword name is required'], 400);
}

global $db;
$projectId = (int) ($_SESSION['project_id'] ?? 0);

$kId = $db->insert('keywords', [
    'project_id' => $projectId,
    'name' => $name,
]);

/* Insert today's initial position for the new keyword */
$position = random_int(POSITION_MIN, POSITION_MAX);
$today = date('Y-m-d');
$db->getPdo()->prepare(
    'INSERT INTO `positions` (`keyword_id`, `position`, `date`)
     VALUES (?, ?, ?)
     ON CONFLICT(`keyword_id`, `date`) DO UPDATE SET `position` = excluded.`position`'
)->execute([$kId, $position, $today]);

jsonResponse([
    'success' => true,
    'data' => [
        'k_id' => $kId,
        'name' => $name,
        'updated_at' => date('Y-m-d H:i:s'),
        'current_position' => $position,
        'previous_position' => null,
        'trend' => 'stable',
    ],
]);
