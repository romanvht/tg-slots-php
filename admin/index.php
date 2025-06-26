<?php
require_once '../core/config.php';
require_once '../core/database.php';

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 100;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$offset = ($page - 1) * $limit;

$db = new Database();
$users = $db->getUsers($offset, $limit, $search, $tab);
$totalCount = $db->countUsers($search, $tab);
$totalPages = ceil($totalCount / $limit);

if (isset($_POST['delete']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $db->deleteUser($userId);
    header('Location: index.php?tab=' . htmlspecialchars($tab));
    exit;
}

if (isset($_POST['reset_stats']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    $db->updateUser($userId, [
        'slot_win_time' => null,
        'consecutive_sixes' => 0,
        'last_dice_time' => null,
        'muted_until' => null
    ]);
    header('Location: index.php?tab=' . htmlspecialchars($tab));
    exit;
}

if (isset($_POST['delete_all_users'])) {
    $allUsers = $db->getAllUsers();
    foreach ($allUsers as $user) {
        $db->deleteUser($user['user_id']);
    }
    header('Location: index.php?tab=' . htmlspecialchars($tab));
    exit;
}

if (isset($_POST['reset_all_stats'])) {
    $allUsers = $db->getAllUsers();
    foreach ($allUsers as $user) {
        $db->updateUser($user['user_id'], [
            'slot_win_time' => null,
            'consecutive_sixes' => 0,
            'last_dice_time' => null,
            'muted_until' => null
        ]);
    }
    header('Location: index.php?tab=' . htmlspecialchars($tab));
    exit;
}

$totalUsers = $db->getTotalUsers();
$activeUsers = $db->getActiveUsers();
$mutedUsers = $db->getMutedUsers();
$totalGames  = $db->getTotalGames();
$totalWins   = $db->getTotalWins();
$totalLosses = $db->getTotalLosses();

$tabs = [
    'all' => 'Все',
    'in_game' => 'В игре',
    'muted' => 'В муте',
    'played' => 'Игравшие',
    'not_played' => 'Неигравшие'
];

include 'templates/index.php';
