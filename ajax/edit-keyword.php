<?php

require_once __DIR__ . '/../init.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$kId = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if ($kId <= 0 || $name === '') {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

global $db;
$userId = $_SESSION['user_id'];

$keyword = $db->fetchOne(
    'SELECT `k_id` FROM `keywords` WHERE `k_id` = ? AND `user_id` = ?',
    [$kId, $userId]
);

if (!$keyword) {
    jsonResponse(['success' => false, 'message' => 'Keyword not found'], 404);
}

$db->update('keywords', ['name' => $name], '`k_id` = ? AND `user_id` = ?', [$kId, $userId]);

jsonResponse(['success' => true, 'data' => ['name' => $name]]);
