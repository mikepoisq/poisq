<?php
// 🔧 ВАЖНО: Буферизация вывода должна быть ПЕРВОЙ строкой!
ob_start();

// register.php — Регистрация Poisq (С ВЕРИФИКАЦИЕЙ ЧЕРЕЗ БД И EMAIL)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// Подключение к БД
require_once 'config/database.php';

// Если уже авторизован — редирект в профиль
if (isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo '<script>window.location.href="profile.php";</script>';
    exit;
}

$error = '';

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    
    // Валидация
    if (empty($email) || empty($password) || empty($name)) {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают';
    } else {
        try {
            $pdo = getDbConnection();
            
            // 🔧 Проверяем есть ли уже такой email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Этот email уже зарегистрирован';
            } else {
                // ✅ УСПЕШНАЯ ВАЛИДАЦИЯ — готовим к верификации
                
                // 🔧 Сохраняем данные в сессию для верификации
                $_SESSION['verify_email'] = $email;
                $_SESSION['verify_name'] = $name;
                $_SESSION['verify_password'] = password_hash($password, PASSWORD_DEFAULT);
                
                // 🔧 ГЕНЕРИРУЕМ КОД ОДИН РАЗ (ИСПРАВЛЕНИЕ ПРОБЛЕМЫ #2)
                $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // 🔧 Сохраняем код в сессию (для email и для БД)
                $_SESSION['verify_code'] = $code;
                
                // 🔧 Сохраняем код в БД
                $stmt = $pdo->prepare("
                    INSERT INTO verification_codes (email, code, expires_at, used)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt->execute([$email, $code, $expiresAt]);
                
                // 🔧 ОТПРАВЛЯЕМ EMAIL С КОДОМ (используем тот же код из сессии)
                try {
                    require_once 'config/email.php';
                    $emailSent = sendVerificationEmail($email, $code, $name);
                    
                    if (!$emailSent) {
                        error_log('Email не отправлен, но код сохранён в БД');
                        // Не прерываем регистрацию, код всё равно в БД
                    }
                } catch (Exception $e) {
                    error_log('Email Error: ' . $e->getMessage());
                    // Если PHPMailer не найден или ошибка — код всё равно в БД
                }
                
                // 🔧 Очищаем буфер и закрываем сессию перед редиректом
                if (ob_get_length()) ob_end_clean();
                                
                // 🔧 Редирект на страницу верификации
                echo '<script>window.location.href="verify.php";</script>';
                exit;
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
    <title>Регистрация — Poisq</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
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
        .header-left, .header-right { display: flex; align-items: center; gap: 6px; flex: 1; }
        .header-right { justify-content: flex-end; }
        .btn-apps {
            width: 40px; height: 40px; border-radius: 12px; border: none;
            background: var(--bg-secondary); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; padding: 0;
        }
        .btn-apps svg { width: 20px; height: 20px; fill: currentColor; }
        .btn-apps:active { transform: scale(0.95); background: var(--primary); }
        .btn-apps:active svg { fill: white; }
        .btn-burger {
            width: 40px; height: 40px; display: flex; flex-direction: column;
            justify-content: center; align-items: center; gap: 5px;
            padding: 8px; cursor: pointer; background: none; border: none; border-radius: 12px;
        }
        .btn-burger span { display: block; width: 22px; height: 2.5px; background: #6B7280; border-radius: 2px; transition: all 0.2s ease; }
        .btn-burger:active { background: var(--primary); }
        .btn-burger:active span { background: white; }
        .btn-burger.active span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
        .btn-burger.active span:nth-child(2) { opacity: 0; }
        .btn-burger.active span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }
        .side-menu {
            position: fixed; top: 0; right: -100%; width: 280px; height: 100vh;
            background: var(--bg); z-index: 400; transition: right 0.3s ease;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15); display: flex; flex-direction: column;
        }
        .side-menu.active { right: 0; }
        .side-menu-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 399; display: none;
        }
        .side-menu-overlay.active { display: block; }
        .side-menu-header { padding: 20px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-light); }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 50%; background: var(--primary);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 18px; flex-shrink: 0;
        }
        .user-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .user-details { flex: 1; }
        .user-name { font-size: 16px; font-weight: 600; color: var(--text); }
        .user-email { font-size: 13px; color: var(--text-secondary); }
        .side-menu-items { flex: 1; overflow-y: auto; padding: 10px 0; }
        .menu-item {
            display: flex; align-items: center; gap: 14px; padding: 14px 20px;
            color: var(--text); text-decoration: none; font-size: 15px;
            font-weight: 500; transition: background 0.15s ease;
        }
        .menu-item:active { background: var(--bg-secondary); }
        .menu-item svg { width: 22px; height: 22px; stroke: var(--text-secondary); flex-shrink: 0; }
        .menu-divider { height: 1px; background: var(--border-light); margin: 10px 0; }
        .auth-container {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; padding: 40px 20px 20px;
        }
        .auth-logo {
            height: 60px; width: auto; max-width: 200px;
            object-fit: contain; margin-bottom: 24px;
        }
        .auth-title {
            font-size: 24px; font-weight: 700; color: var(--text);
            text-align: center; margin-bottom: 8px;
        }
        .auth-subtitle {
            font-size: 15px; color: var(--text-secondary);
            text-align: center; margin-bottom: 32px; max-width: 280px;
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
        .auth-divider {
            display: flex; align-items: center; gap: 12px; margin: 8px 0;
        }
        .auth-divider::before, .auth-divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border-light);
        }
        .auth-divider span { font-size: 13px; color: var(--text-secondary); }
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
</head>
<body>
    <div class="app-container">
        <header class="header">
            <div class="header-left">
                <button class="btn-apps" onclick="openAnnouncementsModal()" aria-label="Объявления">
                    <svg viewBox="0 0 24 24">
                        <circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/>
                        <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                        <circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
                    </svg>
                </button>
            </div>
            <div class="header-right">
                <button class="btn-burger" id="menuToggle" aria-label="Меню">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </header>

        <div class="side-menu-overlay" id="menuOverlay"></div>
        <div class="side-menu" id="sideMenu">
            <div class="side-menu-header">
                <div class="user-info">
                    <div class="user-avatar">👤</div>
                    <div class="user-details">
                        <div class="user-name">Гость</div>
                        <div class="user-email">Войдите в аккаунт</div>
                    </div>
                </div>
            </div>
            <div class="side-menu-items">
                <a href="index.php" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>Главная
                </a>
                <a href="login.php" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>Вход
                </a>
                <a href="useful.php" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>Полезное
                </a>
                <div class="menu-divider"></div>
                <a href="contact.php" class="menu-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>Контакт
                </a>
            </div>
        </div>

        <main class="auth-container">
            <a href="index.php" style="display: block; margin-bottom: 24px;">
                <img src="logo.png" alt="Poisq" class="auth-logo" onerror="this.style.display='none'">
            </a>
            <h1 class="auth-title">Создать аккаунт</h1>
            <p class="auth-subtitle">Присоединяйтесь к Poisq и найдите русскоговорящих специалистов за границей</p>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form class="auth-form" method="POST" action="register.php">
                <div class="form-group">
                    <label class="form-label" for="name">Имя</label>
                    <input type="text" class="form-input" id="name" name="name" placeholder="Ваше имя" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-input" id="email" name="email" placeholder="example@mail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Пароль</label>
                    <input type="password" class="form-input" id="password" name="password" placeholder="Минимум 6 символов" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Повторить пароль</label>
                    <input type="password" class="form-input" id="password_confirm" name="password_confirm" placeholder="Повторите пароль" required minlength="6">
                </div>
                <div id="cookie-warning" style="display:none;margin-top:12px;padding:12px 16px;background:#FEF3C7;border:1px solid #F59E0B;border-radius:12px;font-size:13px;color:#92400E;">⚠️ В вашем браузере отключены cookie. Для регистрации необходимо включить cookie.<br><br><b>Opera на Android:</b> Меню → Настройки → Конфиденциальность → Файлы cookie → Разрешить</div>
        <button type="submit" class="btn-submit">Зарегистрироваться</button>
            </form>
            
            <div class="auth-divider"><span>или</span></div>
            <p class="auth-link">Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </main>

        <footer class="auth-footer">
            <div class="footer-links">
                <a href="/help.php" class="footer-link">Помощь</a>
<a href="/terms.php" class="footer-link">Условия</a>
<a href="/about.php" class="footer-link">О нас</a>
<a href="/contact.php" class="footer-link">Контакт</a>
            </div>
        </footer>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');
        function toggleMenu() {
            menuToggle.classList.toggle('active');
            sideMenu.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            document.body.style.overflow = sideMenu.classList.contains('active') ? 'hidden' : '';
        }
        menuToggle.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', toggleMenu);
        function openAnnouncementsModal() { alert('Модалка объявлений (будет позже)'); }
    </script>
    <script>
        document.cookie = 'cookietest=1; SameSite=Lax';
        if (document.cookie.indexOf('cookietest') === -1) {
            document.getElementById('cookie-warning').style.display = 'block';
        } else {
            document.cookie = 'cookietest=1; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }
    </script>
</body>
</html>