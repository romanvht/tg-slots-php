<?php
require_once 'core/config.php';
require_once 'core/bot.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    $bot = new TelegramBot(BOT_TOKEN);
    $bot->processCronJobs();
    $bot->close();
} catch (Exception $e) {
    error_log("Cron job error: " . $e->getMessage());
}
