<?php

require_once __DIR__ . '/../init.php';

$keywords = getKeywordsWithPositions($_SESSION['user_id']);

jsonResponse(['success' => true, 'data' => $keywords]);
