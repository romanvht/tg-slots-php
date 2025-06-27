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
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $messageId = $message['message_id'];
        
        if (isset($message['forward_date'])) {
            return;
        }
        
        $messageThreadId = $message['message_thread_id'] ?? null;
        if ($messageThreadId !== ALLOWED_THREAD_ID) {
            return;
        }
        
        if (isset($message['text'])) {
            $text = trim($message['text']);
            
            if ($text === '/leaders') {
                $this->handleLeaderCommand($chatId);
                return;
            }
        }
        
        if (isset($message['dice']) && $message['dice']['emoji'] === '🎰') {
            $this->handleSlotMachine($message, $chatId);
            return;
        }
        
        if (isset($message['dice']) && $message['dice']['emoji'] === '🎲') {
            $this->handleDiceRoll($message, $chatId);
            return;
        }
    }
    
    private function handleSlotMachine($message, $chatId) {
        $userId = $message['from']['id'];
        $value = $message['dice']['value'];
        
        $user = $this->db->createUser($message['from'], $chatId);

        if (!$user || $user['slot_win_time']) {
            $this->deleteMessage($chatId, $message['message_id']);
            return;
        }
        
        if ($this->checkSlotWin($value)) {
            $this->handleSlotWin($user, $chatId);
        }
    }
    
    private function handleDiceRoll($message, $chatId) {
        $userId = $message['from']['id'];
        $diceValue = $message['dice']['value'];
        
        $user = $this->db->getUser($userId);
        
        if (!$user || !$user['slot_win_time']) {
            $this->deleteMessage($chatId, $message['message_id']);
            return;
        }
        
        $time = time();
        
        if ($diceValue == REQUIRED_DICE_VALUE) {
            $consecutiveSixes = ($user['consecutive_sixes'] ?? 0) + 1;
            
            $this->db->updateUser($user['user_id'], [
                'consecutive_sixes' => $consecutiveSixes,
                'last_dice_time' => $time
            ]);
            
            if ($consecutiveSixes >= REQUIRED_DICE_COUNT) {
                $this->handleGameWin($user, $chatId);
            }
        } else {
            $this->db->updateUser($user['user_id'], [
                'consecutive_sixes' => 0,
                'last_dice_time' => $time
            ]);
        }
    }

    private function checkSlotWin($diceValue) {
        if ($diceValue === 64) {
            return true;
        }

        $i = $diceValue - 1;

        $left   = $i % 4;
        $center = intdiv($i, 4)  % 4;
        $right  = intdiv($i, 16) % 4;

        return ($left === $center && $center === $right);
    }
    
    private function getTrophyEmoji($position) {
        switch ($position) {
            case 1: return "🥇";
            case 2: return "🥈";
            case 3: return "🥉";
            default: return "🏅";
        }
    }
    
    private function handleSlotWin($user, $chatId) {
        $this->db->saveSlotWin($user['user_id']);
        
        $message = "😈 @" . (empty($user['username']) ? $user['first_name'] : $user['username']) . " депнул!\n";
        $message .= "У тебя 10 минут, чтобы 2 раза подряд выбросить 6 на кубике 🎲 \n";
        $message .= "Если не успеешь - рандомный мут от 1 до 24 часов.\n\n";
        $message .= "Вперед, удачи!";
        
        $this->sendMessage($chatId, $message, ['message_thread_id' => ALLOWED_THREAD_ID]);
    }
    
    private function handleGameWin($user, $chatId) {
        $this->db->clearUser($user['user_id']);
        $this->db->addWin($user['user_id']);

        $message = "🎲 @" . (empty($user['username']) ? $user['first_name'] : $user['username']) . " успешно выбросил " . REQUIRED_DICE_COUNT . " раза подряд по 6.\n";
        $message .= "Сегодня без мута! 🎉\n\n";
        $message .= "Побед: <b>" . ($user['wins'] + 1) . "</b> | Поражений: <b>{$user['losses']}</b>";
        $this->sendMessage($chatId, $message, ['message_thread_id' => ALLOWED_THREAD_ID]);

        $this->handleLeaderCommand($chatId);
    }
    
    private function handleLeaderCommand($chatId) {
        $leaderboard = $this->db->getLeaderboard(20);
        $message = "🏆 <b>Топ-20 деперов</b>\n\n";

        if (empty($leaderboard)) {
            $message .= "Пока никто не депал\n";
        } else {            
            foreach ($leaderboard as $index => $player) {
                $position = $index + 1;
                $trophy = $this->getTrophyEmoji($position);
                $username = !empty($player['username']) ? $player['username'] : $player['first_name'];
                
                $message .= "{$trophy} <b>{$username}</b> - Побед: <b>{$player['wins']}</b> | Поражений: <b>{$player['losses']}</b> (<b>{$player['win_rate']}%</b>)\n";
            }
        }
        
        $message .= "\n🎰 Депай в слоты, чтобы попасть в рейтинг!";
        $this->sendMessage($chatId, $message, ['message_thread_id' => ALLOWED_THREAD_ID]);
    }
    
    private function randomMuteDuration() {
        $min = 1;
        $max = floor(MUTE_DURATION / 3600);
        $random = random_int($min, $max);
        
        return $random * 3600;
    }
    
    private function formatMuteDuration($seconds) {
        $hours = floor($seconds / 3600);
        
        $last = $hours % 10;
        $lastTwo = $hours % 100;
        
        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return "{$hours} часов";
        }
        elseif ($last == 1) {
            return "{$hours} час";
        }
        elseif ($last >= 2 && $last <= 4) {
            return "{$hours} часа";
        }
        else {
            return "{$hours} часов";
        }
    }
    
    public function processCronJobs() {
        $usersToUnmute = $this->db->getUsersToUnmute();
        foreach ($usersToUnmute as $user) {
            $this->unmuteUser($user);
            $this->db->clearUser($user['user_id']);
  
            $message = "🔓 @" . (empty($user['username']) ? $user['first_name'] : $user['username']) . " больше не в муте.\n";
            $message .= "С возвращением! 🎉 \n\n";
            $message .= "Побед: <b>{$user['wins']}</b> | Поражений: <b>{$user['losses']}</b>";
            $this->sendMessage($user['chat_id'], $message, ['message_thread_id' => ALLOWED_THREAD_ID]);
        }
        
        $usersToPunish = $this->db->getUsersToPunish();
        foreach ($usersToPunish as $user) {
            $duration = $this->randomMuteDuration();
            $formatted = $this->formatMuteDuration($duration);
            
            $this->muteUser($user, $duration);
            $this->db->muteUser($user['user_id'], $duration);
            $this->db->addLose($user['user_id']);

            $message = "🔒 @" . (empty($user['username']) ? $user['first_name'] : $user['username']) . " не успел выполнить задание.\n";
            $message .= "Гладим траву {$formatted} 🌿 \n\n";
            $message .= "Побед: <b>{$user['wins']}</b> | Поражений: <b>" . ($user['losses'] + 1) . "</b>";
            $this->sendMessage($user['chat_id'], $message, ['message_thread_id' => ALLOWED_THREAD_ID]);
        }
    }
    
    private function muteUser($user, $dur = null) {
        $chatId = $user['chat_id'];
        $userId = $user['user_id'];
        $duration = $dur ?? MUTE_DURATION;
        $untilDate = time() + $duration;

        $this->apiRequest('restrictChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'until_date' => $untilDate,
            'permissions' => json_encode([
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
    
    private function unmuteUser($user) {
        $chatId = $user['chat_id'];
        $userId = $user['user_id'];

        $this->apiRequest('restrictChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
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
    
    private function sendMessage($chatId, $text, $params = []) {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ], $params);
        
        return $this->apiRequest('sendMessage', $params);
    }
    
    private function deleteMessage($chatId, $messageId) {
        return $this->apiRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }
    
    private function apiRequest($method, $params = []) {
        $url = "https://api.telegram.org/bot{$this->token}/$method";
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        
        if ($error) {
            error_log("API Request Error: $error");
        }
        
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    public function setWebhook() {
        return $this->apiRequest('setWebhook', [
            'url' => WEBHOOK_URL
        ]);
    }
    
    public function close() {
        $this->db->close();
    }
}