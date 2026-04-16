<?php
// reset-password.php — Установка нового пароля Poisq
ob_start();
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

require_once 'config/database.php';

// Если уже авторизован — редирект в профиль
if (isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: profile.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = $_POST['email'] ?? '';

// Проверяем токен при загрузке страницы
$validToken = false;
$userName = '';

if (!empty($token)) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            SELECT pr.email, u.name 
            FROM password_resets pr
            JOIN users u ON pr.email = u.email
            WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if ($result) {
            $validToken = true;
            $email = $result['email'];
            $userName = $result['name'];
        } else {
            $error = 'Ссылка недействительна или истекла';
        }
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        $error = 'Ошибка базы данных';
    }
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Введите пароль';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают';
    } else {
        try {
            $pdo = getDbConnection();
            
            // 🔧 Обновляем пароль
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);
            
            // 🔧 Помечаем токен как использованный
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = 'Пароль успешно изменён! Теперь вы можете войти.';
            
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            $error = 'Ошибка обновления пароля';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
<meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Новый пароль — Poisq</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
    <link rel="manifest" href="/manifest.json?v=2">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Poisq">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        :root {
            --primary: #2E73D8; --primary-light: #5EA1F0; --primary-dark: #1A5AB8;
            --text: #1F2937; --text-secondary: #9CA3AF; --text-light: #6B7280;
            --bg: #FFFFFF; --bg-secondary: #F5F5F7; --border: #D1D5DB; --border-light: #E5E7EB;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06); --shadow-md: 0 4px 16px rgba(46,115,216,0.15);
        }
        html { -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto; }
        body {
            -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary); color: var(--text); line-height: 1.5;
            -webkit-font-smoothing: antialiased; touch-action: manipulation;
        }
        .app-container {
            max-width: 430px; margin: 0 auto; background: var(--bg);
            min-height: 100vh; min-height: 100dvh;
            position: relative; display: flex; flex-direction: column;
        }
        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; background: var(--bg);
            border-bottom: 1px solid var(--border-light); flex-shrink: 0; height: 56px;
        }
        .header-left { display: flex; align-items: center; }
        .btn-back {
            width: 40px; height: 40px; border-radius: 12px; border: none;
            background: var(--bg-secondary); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; padding: 0; text-decoration: none;
        }
        .btn-back:active { transform: scale(0.95); background: var(--border); }
        .btn-back svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }
        .header-title { flex: 1; text-align: center; font-size: 17px; font-weight: 600; color: var(--text); }
        .header-spacer { width: 40px; }
        .auth-container {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; padding: 40px 20px 20px;
        }
        .auth-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: #D1FAE5; display: flex;
            align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .auth-icon svg { width: 40px; height: 40px; stroke: var(--success); fill: none; stroke-width: 2; }
        .auth-title {
            font-size: 24px; font-weight: 700; color: var(--text);
            text-align: center; margin-bottom: 8px;
        }
        .auth-subtitle {
            font-size: 15px; color: var(--text-secondary);
            text-align: center; margin-bottom: 32px;
            max-width: 280px; line-height: 1.5;
        }
        .auth-form {
            width: 100%; max-width: 360px; display: flex;
            flex-direction: column; gap: 16px;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 14px; font-weight: 500; color: var(--text); }
        .form-input {
            width: 100%; padding: 14px 16px; border: 1px solid var(--border);
            border-radius: 12px; font-size: 16px; color: var(--text);
            background: var(--bg); outline: none;
            transition: all 0.2s ease;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46,115,216,0.1); }
        .form-input::placeholder { color: var(--text-secondary); }
        .btn-submit {
            width: 100%; padding: 16px; border-radius: 12px; border: none;
            background: var(--primary); color: white; font-size: 16px;
            font-weight: 600; cursor: pointer; transition: all 0.2s ease;
            margin-top: 8px;
        }
        .btn-submit:active { transform: scale(0.98); background: var(--primary-dark); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .auth-link {
            font-size: 14px; color: var(--text-secondary);
            text-align: center; margin-top: 16px;
        }
        .auth-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
        .auth-link a:active { text-decoration: underline; }
        .alert {
            width: 100%; max-width: 360px; padding: 14px 16px;
            border-radius: 12px; font-size: 14px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-error { background: #FEF2F2; color: var(--danger); border: 1px solid #FECACA; }
        .alert-success { background: #F0FDF4; color: var(--success); border: 1px solid #BBF7D0; }
        .alert svg { width: 20px; height: 20px; flex-shrink: 0; }
        .user-email {
            font-size: 14px; color: var(--text-secondary);
            text-align: center; margin-bottom: 20px;
            background: var(--bg-secondary);
            padding: 10px 16px;
            border-radius: 8px;
        }
        .auth-footer {
            background: var(--bg-secondary); border-top: 1px solid var(--border-light);
            flex-shrink: 0; padding: 16px 20px;
        }
        .footer-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 14px; }
        .footer-link { font-size: 12px; color: var(--text); text-decoration: none; }
        .footer-link:active { color: var(--primary); }
        @media (max-width: 380px) {
            .auth-title { font-size: 22px; }
            .auth-subtitle { font-size: 14px; }
            .form-input { padding: 12px 14px; font-size: 15px; }
        }
        ::-webkit-scrollbar { display: none; }
    </style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
    <div class="app-container">
        <header class="header">
            <div class="header-left">
                <a href="login.php" class="btn-back" aria-label="Назад">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
            </div>
            <div class="header-title">Новый пароль</div>
            <div class="header-spacer"></div>
        </header>

        <main class="auth-container">
            <?php if ($success): ?>
                <div class="auth-icon">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h1 class="auth-title">Пароль изменён!</h1>
                <p class="auth-subtitle"><?php echo htmlspecialchars($success); ?></p>
                <a href="login.php" class="btn-submit" style="display: flex; text-decoration: none; justify-content: center; align-items: center;">Войти с новым паролем</a>
            <?php elseif ($error && !$validToken): ?>
                <div class="auth-icon" style="background: #FEE2E2;">
                    <svg viewBox="0 0 24 24" style="stroke: var(--danger);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h1 class="auth-title">Ошибка</h1>
                <p class="auth-subtitle"><?php echo htmlspecialchars($error); ?></p>
                <a href="forgot-password.php" class="btn-submit" style="display: inline-block; text-decoration: none; max-width: 280px;">Запросить новую ссылку</a>
            <?php else: ?>
                <div class="auth-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                </div>
                <h1 class="auth-title">Придумайте новый пароль</h1>
                <p class="auth-subtitle">Для аккаунта: <strong><?php echo htmlspecialchars($email); ?></strong></p>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form class="auth-form" method="POST" action="reset-password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Новый пароль</label>
                        <input type="password" class="form-input" id="password" name="password" 
                            placeholder="Минимум 6 символов" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Повторите пароль</label>
                        <input type="password" class="form-input" id="password_confirm" name="password_confirm" 
                            placeholder="Повторите пароль" required minlength="6">
                    </div>
                    <button type="submit" class="btn-submit">Сохранить новый пароль</button>
                </form>
                
                <p class="auth-link">
                    <a href="login.php">Вернуться ко входу</a>
                </p>
            <?php endif; ?>
        </main>

        <footer class="auth-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Как пользоваться</a>
                <a href="#" class="footer-link">Условия</a>
                <a href="#" class="footer-link">О нас</a>
                <a href="contact.php" class="footer-link">Контакт</a>
            </div>
        </footer>
    </div>
</body>
</html>