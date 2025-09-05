<?php
require_once 'core/config.php';
require_once 'core/bot.php';

ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/cron.log");

$lockFile = __DIR__ . '/cron.lock';
$lockHandle = fopen($lockFile, 'w');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Скрипт уже выполняется. Выход." . PHP_EOL;
    exit(1);
}

register_shutdown_function(function() use ($lockHandle, $lockFile) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    unlink($lockFile);
});

try {
    $bot = new TelegramBot(BOT_TOKEN);
    $bot->processCronJobs();
    $bot->close();
} catch (Exception $e) {
    error_log("Cron job error: " . $e->getMessage());
}
