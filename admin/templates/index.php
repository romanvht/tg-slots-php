<!DOCTYPE html>
<html>
<head>
    <title>Панель управления ботом</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <div class="container">
        <div class="logout">
            <a href="logout.php">Выход</a>
        </div>
        
        <h1>Панель управления ботом</h1>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $totalUsers; ?></div>
                <div class="stat-label">Игроков</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $activeUsers; ?></div>
                <div class="stat-label">В игре</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $mutedUsers; ?></div>
                <div class="stat-label">В муте</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalGames; ?></div>
                <div class="stat-label">Игр</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalWins; ?></div>
                <div class="stat-label">Побед</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalLosses; ?></div>
                <div class="stat-label">Поражений</div>
            </div>
        </div>

        <div class="tabs">
            <form method="get" style="display: flex; gap: 10px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Имя, ник или ID" style="padding:5px; width:200px;">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab); ?>">
                <button type="submit">Поиск</button>
                <?php if ($search): ?>
                    <a class="btn" href="index.php?tab=<?= htmlspecialchars($tab); ?>" style="color:red;">Сбросить</a>
                <?php endif; ?>
            </form>

            <div class="tab-bar">
                <?php foreach ($tabs as $key => $label): ?>
                    <?php $activeClass = ($tab === $key) ? 'tab-link active' : 'tab-link'; ?>
                    <a href="index.php?tab=<?= $key . ($search ? '&search='.urlencode($search) : ''); ?>" class="<?= $activeClass; ?>"><?= $label; ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя пользователя</th>
                    <th>Имя</th>
                    <th>🏆 Побед</th>
                    <th>💀 Поражений</th>
                    <th>📊 Всего игр</th>
                    <th>📈 % побед</th>
                    <th>Выигрыш в слоты</th>
                    <th>Шестерок</th>
                    <th>Попытка</th>
                    <th>Мут до</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php 
                    $isMuted = $user['muted_until'] !== null && $user['muted_until'] > time();
                    $isInGame = $user['slot_win_time'] !== null && $user['slot_win_time'] > (time() - DICE_TIME_LIMIT);
                    $totalGames = ($user['wins'] ?? 0) + ($user['losses'] ?? 0);
                    $winRate = $totalGames > 0 ? round(($user['wins'] ?? 0) * 100 / $totalGames, 1) : 0;
                    
                    $rowClass = '';
                    if ($isMuted) $rowClass = 'muted';
                    elseif ($isInGame) $rowClass = 'winner';
                    
                    $winRateClass = '';
                    if ($winRate >= 70) $winRateClass = 'high';
                    elseif ($winRate >= 40) $winRateClass = 'medium';
                    elseif ($totalGames > 0) $winRateClass = 'low';
                    ?>
                    <tr class="<?= $rowClass; ?>">
                        <td><?= htmlspecialchars($user['user_id']); ?></td>
                        <td><?= htmlspecialchars($user['username'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></td>
                        <td><strong><?= $user['wins'] ?? 0; ?></strong></td>
                        <td><strong><?= $user['losses'] ?? 0; ?></strong></td>
                        <td><?= $totalGames; ?></td>
                        <td><span class="win-rate <?= $winRateClass; ?>"><?= $winRate; ?>%</span></td>
                        <td class="time">
                            <?php if ($user['slot_win_time']): ?>
                                <?= date('Y-m-d H:i:s', $user['slot_win_time']); ?>
                                <?php if ($isInGame): ?>
                                    <br><small style="color: #28a745;">🎮 В игре</small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $user['consecutive_sixes'] ?? 0; ?></td>
                        <td><?= $user['dice_attempts'] ?? 0; ?></td>
                        <td class="time">
                            <?php if ($user['muted_until']): ?>
                                <?= date('Y-m-d H:i:s', $user['muted_until']); ?>
                                <?php if ($isMuted): ?>
                                    <br><small style="color: #dc3545;">🔒 В муте</small>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="post" onsubmit="return confirmAction('Сбросить статистику пользователя <?= htmlspecialchars($user['username'] ?? $user['user_id']); ?>?')">
                                <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                <button type="submit" name="reset_stats" class="btn-reset">Сбросить</button>
                            </form>
                            <form method="post" onsubmit="return confirmAction('Удалить пользователя <?= htmlspecialchars($user['username'] ?? $user['user_id']); ?>?')">
                                <input type="hidden" name="user_id" value="<?= $user['user_id']; ?>">
                                <button type="submit" name="delete" class="btn-delete">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($users)): ?>
            <p style="text-align: center; margin-top: 50px; font-size: 18px; color: #666;">
                Пользователи не найдены
            </p>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="nav">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $url = 'index.php?page=' . $i . '&tab=' . urlencode($tab) . ($search ? '&search='.urlencode($search) : ''); ?>
                    <?php if ($i == $page): ?>
                        <span class="btn btn-reset"><?= $i; ?></span>
                    <?php else: ?>
                        <a href="<?= $url; ?>" class="btn"><?= $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="mass-actions">
            <form method="post" style="display: inline;" onsubmit="return confirmAction('Сбросить статистику всех пользователей?')">
                <button type="submit" name="reset_all_stats" class="btn-warning">Сбросить всю статистику</button>
            </form>
            <form method="post" style="display: inline;" onsubmit="return confirmAction('ВНИМАНИЕ! Это действие удалит ВСЕХ пользователей из базы данных. Вы уверены?')">
                <button type="submit" name="delete_all_users" class="btn-danger">Удалить всех пользователей</button>
            </form>
        </div>
    </div>
    
    <script>
        function confirmAction(message) {
            return confirm(message);
        }
    </script>
</body>
</html>
