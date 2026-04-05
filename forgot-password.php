<?php
// forgot-password.php — Запрос восстановления пароля Poisq
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

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } else {
        try {
            $pdo = getDbConnection();
            
            // 🔧 Проверяем существует ли пользователь
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // 🔧 Генерируем токен
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // 🔧 Помечаем старые токены как использованные
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
                $stmt->execute([$email]);
                
                // 🔧 Сохраняем новый токен
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (email, token, expires_at, used)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt->execute([$email, $token, $expiresAt]);
                
                // 🔧 ОТПРАВЛЯЕМ EMAIL С ССЫЛКОЙ
                try {
                    require_once 'config/email.php';
                    $resetLink = 'https://poisq.com/reset-password.php?token=' . $token;
                    $emailSent = sendPasswordResetEmail($email, $resetLink, $user['name']);
                    
                    if ($emailSent) {
                        $success = 'Инструкция по восстановлению отправлена на ваш email';
                    } else {
                        $error = 'Ошибка отправки email. Попробуйте позже.';
                    }
                } catch (Exception $e) {
                    $error = 'Ошибка отправки email. Попробуйте позже.';
                    error_log('Password Reset Email Error: ' . $e->getMessage());
                }
            } else {
                // 🔧 Не показываем что email не найден (безопасность)
                $success = 'Если email зарегистрирован, вы получите инструкцию';
            }
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            $error = 'Ошибка базы данных. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Восстановление пароля — Poisq</title>
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
            background: #E8F0FE; display: flex;
            align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .auth-icon svg { width: 40px; height: 40px; stroke: var(--primary); fill: none; stroke-width: 2; }
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
            <div class="header-title">Восстановление</div>
            <div class="header-spacer"></div>
        </header>

        <main class="auth-container">
            <div class="auth-icon">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            
            <h1 class="auth-title">Забыли пароль?</h1>
            <p class="auth-subtitle">Введите ваш email и мы отправим инструкцию по восстановлению</p>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form class="auth-form" method="POST" action="forgot-password.php">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-input" id="email" name="email" 
                        placeholder="example@mail.com" required 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn-submit">Отправить инструкцию</button>
            </form>
            
            <p class="auth-link">
                Вспомнили пароль? <a href="login.php">Войти</a>
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