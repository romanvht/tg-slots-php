<!DOCTYPE html>
<html>
<head>
    <title>Вход в админ-панель</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <div class="container">
        <h2>Вход в админ-панель</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form class="form-login" method="post">
            <div class="form-group">
                <label for="username">Логин</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Войти</button>
        </form>
    </div>
</body>
</html>