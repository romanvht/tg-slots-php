<?php
require_once 'config.php';
require_once 'database.php';

class TelegramBot {
    private $token;
    private $db;

    public function __construct($token) {
        $this->token = $token;
        $this->db = new Database();
    }

    public function processWebhook() {
        $update = json_decode(file_get_contents('php://input'), true);

        if (DEBUG) {
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . ' - ' . print_r($update, true) . "\n\n", FILE_APPEND);
        }

        if (isset($update['message'])) {
            $this->processMessage($update['message']);
        }
    }

    private function processMessage($message) {
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId !== GAME_GROUP_ID) {
            return;
        }

        $threadId = $message['message_thread_id'] ?? null;
        if ($threadId !== ALLOWED_THREAD_ID) {
            return;
        }

        if (isset($message['forward_date'])) {
            return;
        }

        if (isset($message['text']) && trim($message['text']) === '/leaders') {
            $this->handleLeaderCommand();
            return;
        }

        if (isset($message['dice']) && $message['dice']['emoji'] === '🎰') {
            $this->handleSlotMachine($message);
            return;
        }

        if (isset($message['dice']) && $message['dice']['emoji'] === '🎲') {
            $this->handleDiceRoll($message);
            return;
        }
    }

    private function checkSlotWin($value) {
        if ($value === 64) {
            return true;
        }

        $i = $value - 1;
        $left = $i % 4;
        $center = intdiv($i, 4) % 4;
        $right = intdiv($i, 16) % 4;

        return ($left === $center && $center === $right);
    }

    private function handleSlotMachine($message) {
        $userData = $message['from'];
        $diceValue = $message['dice']['value'];

        $user = $this->db->createUser($userData);
        if (!$user || $user['slot_win_time']) {
            $this->deleteMessage(GAME_GROUP_ID, $message['message_id']);
            return;
        }

        if ($this->checkSlotWin($diceValue)) {
            $this->handleSlotWin($user);
        }
    }

    private function handleDiceRoll($message) {
        $userData = $message['from'];
        $diceValue = $message['dice']['value'];

        $user = $this->db->getUser($userData['id']);
        if (!$user || !$user['slot_win_time']) {
            $this->deleteMessage(GAME_GROUP_ID, $message['message_id']);
            return;
        }

        $time = time();
        $attempt = $user['dice_attempts'] + 1;
        $win = false;

        if ($diceValue == REQUIRED_DICE_VALUE) {
            $count = ($user['consecutive_sixes'] ?? 0) + 1;
            $this->db->updateUser($user['user_id'], [
                'consecutive_sixes' => $count,
                'last_dice_time'    => $time,
                'dice_attempts'     => $attempt
            ]);

            if ($count >= REQUIRED_DICE_COUNT) {
                $this->handleGameWin($user);
                $win = true;
            }
        } else {
            $this->db->updateUser($user['user_id'], [
                'consecutive_sixes' => 0,
                'last_dice_time'    => $time,
                'dice_attempts'     => $attempt
            ]);
        }

        if (!$win && $attempt >= DICE_ATTEMPTS_LIMIT) {
            $this->handleGameOver($user);
        }
    }

    private function handleSlotWin($user) {
        $this->db->saveSlotWin($user['user_id']);

        $name = empty($user['username']) ? $user['first_name'] : $user['username'];
        $msg  = "😈 @{$name} депнул!\n";
        $msg .= "У тебя " . DICE_ATTEMPTS_LIMIT . " бросков и 10 минут, чтобы 2 раза подряд выбросить 6 на кубике 🎲\n";
        $msg .= "Если не успеешь — рандомный мут тут и в BBD от 1 до 24 часов.\n\n";
        $msg .= "Вперед, удачи!";

        $this->sendMessage(GAME_GROUP_ID, $msg, ['message_thread_id' => ALLOWED_THREAD_ID]);
    }

    private function handleGameWin($user) {
        $this->db->clearUser($user['user_id']);
        $this->db->addWin($user['user_id']);

        $name     = empty($user['username']) ? $user['first_name'] : $user['username'];
        $wins     = $user['wins'] + 1;
        $losses   = $user['losses'];
        $msg      = "🎲 @{$name} успешно выбросил " . REQUIRED_DICE_COUNT . "x" . REQUIRED_DICE_VALUE . "! Бросков: {$user['dice_attempts']}\n";
        $msg .= "Сегодня без мута! 🎉\n\n";
        $msg .= "Побед: <b>{$wins}</b> | Поражений: <b>{$losses}</b>";

        $this->sendMessage(GAME_GROUP_ID, $msg, ['message_thread_id' => ALLOWED_THREAD_ID]);
        $this->handleLeaderCommand();
    }

    private function handleGameOver($user, $cron = false) {
        $dur = $this->randomMuteDuration();
        $fmt = $this->formatMuteDuration($dur);

        $this->muteUser($user, $dur);
        $this->db->muteUser($user['user_id'], $dur);
        $this->db->addLose($user['user_id']);

        $name = empty($user['username']) ? $user['first_name'] : $user['username'];
        if ($cron == false) $msg = "🔒 @{$name} исчерпал все попытки.\nГладим траву {$fmt} 🌿\n\n";
        else $msg  = "🔒 @{$name} не успел выполнить задание.\nГладим траву {$fmt} 🌿\n\n";
        $msg .= "Побед: <b>{$user['wins']}</b> | Поражений: <b>" . ($user['losses'] + 1) . "</b>";
        $this->sendMessage(GAME_GROUP_ID, $msg, ['message_thread_id' => ALLOWED_THREAD_ID]);
    }

    private function getTrophyEmoji($pos) {
        if ($pos === 1) return '🥇';
        if ($pos === 2) return '🥈';
        if ($pos === 3) return '🥉';
        return '🏅';
    }

    private function handleLeaderCommand() {
        $board = $this->db->getLeaderboard(20);
        $msg   = "🏆 <b>Топ-20 деперов</b>\n\n";

        if (empty($board)) {
            $msg .= "Пока никто не депал";
        } else {
            foreach ($board as $i => $p) {
                $pos    = $i + 1;
                $trophy = $this->getTrophyEmoji($pos);
                $uname  = !empty($p['username']) ? $p['username'] : $p['first_name'];
                $msg   .= "{$trophy} <b>{$uname}</b> - Побед: <b>{$p['wins']}</b> | Поражений: <b>{$p['losses']}</b> (<b>{$p['win_rate']}%</b>)\n";
            }
        }
        $msg .= "\n🎰 Депай в слоты, чтобы попасть в рейтинг!";

        $this->sendMessage(GAME_GROUP_ID, $msg, ['message_thread_id' => ALLOWED_THREAD_ID]);
    }

    private function randomMuteDuration() {
        $min = 1;
        $max = floor(MUTE_DURATION / 3600);
        return random_int($min, $max) * 3600;
    }
    
    private function formatMuteDuration($seconds) {
        $hours = floor($seconds / 3600);
        $last = $hours % 10;
        $lastTwo = $hours % 100;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return "{$hours} часов";
        } elseif ($last === 1) {
            return "{$hours} час";
        } elseif ($last >= 2 && $last <= 4) {
            return "{$hours} часа";
        } else {
            return "{$hours} часов";
        }
    }

    public function processCronJobs() {
        $toUnmute  = $this->db->getUsersToUnmute();
        foreach ($toUnmute as $u) {
            $this->unmuteUser($u);
            $this->db->clearUser($u['user_id']);

            $name  = empty($u['username']) ? $u['first_name'] : $u['username'];
            $msg   = "🔓 @{$name} больше не в муте.\nС возвращением! 🎉\n\n";
            $msg  .= "Побед: <b>{$u['wins']}</b> | Поражений: <b>{$u['losses']}</b>";
            $this->sendMessage(GAME_GROUP_ID, $msg, ['message_thread_id' => ALLOWED_THREAD_ID]);
        }

        $toPunish = $this->db->getUsersToPunish();
        foreach ($toPunish as $u) {
            $this->handleGameOver($u, true);
        }
    }

    private function muteUser($u, $dur) {
        $until = time() + $dur;
        foreach (GROUPS_FOR_MUTE as $cid) {
            $this->apiRequest('restrictChatMember', [
                'chat_id'      => $cid,
                'user_id'      => $u['user_id'],
                'until_date'   => $until,
                'permissions'  => json_encode([
                    'can_send_messages' => false,
                    'can_send_media_messages' => false,
                    'can_send_polls' => false,
                    'can_send_other_messages' => false,
                    'can_add_web_page_previews' => false,
                    'can_invite_users' => false,
                    'can_change_info' => false,
                    'can_pin_messages' => false
                ])
            ]);
        }
    }

    private function unmuteUser($u) {
        foreach (GROUPS_FOR_MUTE as $cid) {
            $this->apiRequest('restrictChatMember', [
                'chat_id'     => $cid,
                'user_id'     => $u['user_id'],
                'permissions' => json_encode([
                    'can_send_messages' => true,
                    'can_send_media_messages' => true,
                    'can_send_polls' => true,
                    'can_send_other_messages' => true,
                    'can_add_web_page_previews' => true,
                    'can_invite_users' => true,
                    'can_change_info' => false,
                    'can_pin_messages' => false
                ])
            ]);
        }
    }

    private function sendMessage($chatId, $text, $params = []) {
        $data = array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ], $params);
        return $this->apiRequest('sendMessage', $data);
    }

    private function deleteMessage($chatId, $messageId) {
        return $this->apiRequest('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId
        ]);
    }

    private function apiRequest($method, $params = []) {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        $resp = curl_exec($curl);
        if (curl_error($curl)) {
            error_log('API Error: ' . curl_error($curl));
        }
        curl_close($curl);
        return json_decode($resp, true);
    }

    public function setWebhook() {
        return $this->apiRequest('setWebhook', ['url' => WEBHOOK_URL]);
    }

    public function delWebhook() {
        return $this->apiRequest('deleteWebhook', ['drop_pending_updates' => true]);
    }

    public function close() {
        $this->db->close();
    }
}
