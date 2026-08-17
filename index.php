<?php

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['op'])) {
    header('Location: ' . APP_URL . '/index.php?op=dashboard');
    exit;
}

$op = $_GET['op'] ?? 'dashboard';
$page = null;

switch ($op) {
    case 'login':
    case 'register':
    case 'logout':
        require_once __DIR__ . '/libraries/Auth.php';
        $page = new Auth();
        break;
    case 'project':
    case 'add_project':
    case 'archive_project':
    case 'delete_project':
        require_once __DIR__ . '/libraries/Project.php';
        $page = new Project();
        break;
    case 'keywords':
    case 'add_keyword':
    case 'edit_keyword':
    case 'delete_keyword':
        require_once __DIR__ . '/libraries/Keyword.php';
        $page = new Keyword();
        break;
    case 'positions':
    case 'delete_position':
        require_once __DIR__ . '/libraries/Position.php';
        $page = new Position();
        break;
    default:
        require_once __DIR__ . '/libraries/Dashboard.php';
        $page = new Dashboard();
        break;
}

$pageContent = $page->displayPage();

require_once __DIR__ . '/includes/header.php';
echo $pageContent;
require_once __DIR__ . '/includes/footer.php';
