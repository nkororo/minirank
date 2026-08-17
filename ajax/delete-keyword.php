<?php

require_once __DIR__ . '/../init.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!(isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$kId = (int) ($_POST['id'] ?? 0);
if ($kId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid keyword ID'], 400);
}

global $db;
$userId = $_SESSION['user_id'];
$db->delete('keywords', '`k_id` = ? AND `user_id` = ?', [$kId, $userId]);

jsonResponse(['success' => true, 'data' => null]);
