<?php
define('BOT_TOKEN', '');
define('WEBHOOK_URL', '');
define('ADMIN_USERNAME', '');
define('ADMIN_PASSWORD', '');

define('DB_FILE', __DIR__ . '/db.sqlite');

define('GAME_GROUP_ID', 0); // Основная группа бота
define('GROUPS_FOR_MUTE', [0]); // В каких группах мутить
define('ALLOWED_THREAD_ID', null); // В какой теме группы бот будет писать

define('DICE_TIME_LIMIT', 600);
define('MUTE_DURATION', 86400);
define('DICE_ATTEMPTS_LIMIT', 60);
define('REQUIRED_DICE_VALUE', 6);
define('REQUIRED_DICE_COUNT', 2);

define('DEBUG', false);