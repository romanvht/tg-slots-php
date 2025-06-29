<?php
require_once 'config.php';

class Database {
    private $db;
    
    public function __construct() {
        if (!file_exists(DB_FILE)) {
            $this->db = new SQLite3(DB_FILE);
            $this->createTables();
        } else {
            $this->db = new SQLite3(DB_FILE);
        }
    }
    
    public function createTables() {
        $this->db->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                username TEXT,
                first_name TEXT,
                last_name TEXT,
                dice_attempts INTEGER DEFAULT 0,
                slot_win_time INTEGER DEFAULT NULL,
                consecutive_sixes INTEGER DEFAULT 0,
                last_dice_time INTEGER DEFAULT NULL,
                muted_until INTEGER DEFAULT NULL,
                wins INTEGER DEFAULT 0,
                losses INTEGER DEFAULT 0,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ');
    }
    
    public function getUser($userId) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function createUser($userData) {
        $existingUser = $this->getUser($userData['id']);
        if ($existingUser) {
            return $existingUser;
        }
        
        $time = time();
        $stmt = $this->db->prepare('
            INSERT INTO users (
                user_id, username, first_name, last_name, wins, losses, created_at, updated_at
            ) VALUES (
                :user_id, :username, :first_name, :last_name, 0, 0, :created_at, :updated_at
            )
        ');
        
        $stmt->bindValue(':user_id', $userData['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':username', $userData['username'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':first_name', $userData['first_name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':last_name', $userData['last_name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $time, SQLITE3_INTEGER);
        $stmt->bindValue(':updated_at', $time, SQLITE3_INTEGER);
        
        $stmt->execute();
        return $this->getUser($userData['id']);
    }
    
    public function updateUser($userId, $data) {
        $updates = [];
        $time = time();
        
        foreach ($data as $key => $value) {
            $updates[] = "$key = :$key";
        }
        
        $updates[] = "updated_at = :updated_at";
        
        $stmt = $this->db->prepare('
            UPDATE users 
            SET ' . implode(', ', $updates) . ' 
            WHERE user_id = :user_id
        ');
        
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':updated_at', $time, SQLITE3_INTEGER);
        
        foreach ($data as $key => $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue(":$key", $value, $type);
        }
        
        return $stmt->execute();
    }
    
    public function deleteUser($userId) {
        $stmt = $this->db->prepare('DELETE FROM users WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        return $stmt->execute();
    }
      
    public function addWin($userId) {
        $user = $this->getUser($userId);
        $wins = ($user['wins'] ?? 0) + 1;
        return $this->updateUser($userId, ['wins' => $wins]);
    }

    public function addLose($userId) {
        $user = $this->getUser($userId);
        $losses = ($user['losses'] ?? 0) + 1;
        return $this->updateUser($userId, ['losses' => $losses]);
    }

    public function saveSlotWin($userId) {
        $time = time();
        return $this->updateUser($userId, [
            'slot_win_time' => $time,
            'muted_until' => null,
            'consecutive_sixes' => 0,
            'last_dice_time' => null,
            'dice_attempts' => 0
        ]);
    }

    public function muteUser($userId, $duration) {
        $muteUntil = time() + $duration;
        return $this->updateUser($userId, [
            'muted_until' => $muteUntil,
            'slot_win_time' => null,
            'consecutive_sixes' => 0,
            'last_dice_time' => null,
            'dice_attempts' => 0
        ]);
    }
    
    public function clearUser($userId) {
        return $this->updateUser($userId, [
            'slot_win_time' => null,
            'muted_until' => null,
            'consecutive_sixes' => 0,
            'last_dice_time' => null,
            'dice_attempts' => 0
        ]);
    }
    
    public function getLeaderboard($limit = 10) {
        $stmt = $this->db->prepare('
            SELECT user_id, username, first_name, last_name, wins, losses, (wins + losses) as total_games,
                CASE 
                    WHEN (wins + losses) > 0 THEN ROUND((wins * 100.0 / (wins + losses)), 1)
                    ELSE 0 
                END as win_rate
            FROM users 
            WHERE wins > 0 OR losses > 0
            ORDER BY wins DESC, win_rate DESC
            LIMIT :limit
        ');
        
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $leaderboard = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $leaderboard[] = $row;
        }
        
        return $leaderboard;
    }
    
    public function getUsersToUnmute() {
        $time = time();
        $result = $this->db->query("
            SELECT * FROM users 
            WHERE muted_until IS NOT NULL 
            AND muted_until <= $time
        ");
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    public function getUsersToPunish() {
        $time = time();
        $timeLimit = $time - DICE_TIME_LIMIT;
        
        $result = $this->db->query("
            SELECT * FROM users 
            WHERE slot_win_time IS NOT NULL 
            AND slot_win_time <= $timeLimit
            AND (consecutive_sixes IS NULL OR consecutive_sixes < ".REQUIRED_DICE_COUNT.")
            AND (muted_until IS NULL OR muted_until < $time)
        ");
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    public function getAllUsers() {
        $result = $this->db->query("SELECT * FROM users ORDER BY updated_at DESC");
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        return $users;
    }

    public function getUsers($offset = 0, $limit = 100, $search = '', $tab = 'all') {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(username LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR user_id LIKE :search_exact)";
            $params[':search'] = "%$search%";
            $params[':search_exact'] = $search;
        }

        if ($tab === 'in_game') {
            $where[] = "slot_win_time IS NOT NULL AND slot_win_time > " . (time() - DICE_TIME_LIMIT);
        } elseif ($tab === 'muted') {
            $where[] = "muted_until IS NOT NULL AND muted_until > " . time();
        } elseif ($tab === 'played') {
            $where[] = "(wins > 0 OR losses > 0)";
        } elseif ($tab === 'not_played') {
            $where[] = "(wins = 0 AND losses = 0)";
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM users $whereStr ORDER BY updated_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);

        foreach ($params as $k => $v) {
            $type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($k, $v, $type);
        }

        $result = $stmt->execute();
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }

    public function countUsers($search = '', $tab = 'all') {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(username LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR user_id LIKE :search_exact)";
            $params[':search'] = "%$search%";
            $params[':search_exact'] = $search;
        }

        if ($tab === 'in_game') {
            $where[] = "slot_win_time IS NOT NULL AND slot_win_time > " . (time() - DICE_TIME_LIMIT);
        } elseif ($tab === 'muted') {
            $where[] = "muted_until IS NOT NULL AND muted_until > " . time();
        } elseif ($tab === 'played') {
            $where[] = "(wins > 0 OR losses > 0)";
        } elseif ($tab === 'not_played') {
            $where[] = "(wins = 0 AND losses = 0)";
        }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) as cnt FROM users $whereStr";
        $stmt = $this->db->prepare($sql);

        foreach ($params as $k => $v) {
            $type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
            $stmt->bindValue($k, $v, $type);
        }
        
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row['cnt'] ?? 0;
    }

    public function getTotalUsers() {
        $row = $this->db->querySingle("SELECT COUNT(*) as cnt FROM users", true);
        return $row['cnt'] ?? 0;
    }

    public function getActiveUsers() {
        $row = $this->db->querySingle("SELECT COUNT(*) as cnt FROM users WHERE slot_win_time IS NOT NULL", true);
        return $row['cnt'] ?? 0;
    }

    public function getMutedUsers() {
        $row = $this->db->querySingle("SELECT COUNT(*) as cnt FROM users WHERE muted_until IS NOT NULL", true);
        return $row['cnt'] ?? 0;
    }

    public function getTotalGames() {
        $row = $this->db->querySingle("SELECT SUM(wins + losses) as cnt FROM users", true);
        return $row['cnt'] ?? 0;
    }

    public function getTotalWins() {
        $row = $this->db->querySingle("SELECT SUM(wins) as cnt FROM users", true);
        return $row['cnt'] ?? 0;
    }

    public function getTotalLosses() {
        $row = $this->db->querySingle("SELECT SUM(losses) as cnt FROM users", true);
        return $row['cnt'] ?? 0;
    }
    
    public function close() {
        $this->db->close();
    }
}