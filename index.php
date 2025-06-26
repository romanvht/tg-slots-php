<?php
require_once 'core/config.php';
require_once 'core/bot.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (DEBUG) {
    $input = file_get_contents('php://input');
    error_log("Webhook data: $input");
}

try {
    $bot = new TelegramBot(BOT_TOKEN);
    $bot->processWebhook();
    $bot->close();
} catch (Exception $e) {
    error_log("Bot error: " . $e->getMessage());
}

http_response_code(200);
