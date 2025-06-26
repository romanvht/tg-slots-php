<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/bot.php';

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$bot = new TelegramBot(BOT_TOKEN);
$result = $bot->setWebhook();

$db = new Database();
$db->createTables();

echo '<pre>';
print_r($result);
echo '</pre>';
