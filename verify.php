<?php
// 🔧 ВАЖНО: Буферизация вывода должна быть ПЕРВОЙ строкой!
ob_start();

// verify.php — Подтверждение email Poisq (С ПРОВЕРКОЙ ЧЕРЕЗ БД)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// Подключение к БД
require_once 'config/database.php';

// Если нет email в сессии — редирект на регистрацию
if (!isset($_SESSION['verify_email'])) {
    ob_end_clean();
    header('Location: register.php');
    exit;
}

// Если уже авторизован — редирект на главную
if (isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: index.php');
    exit;
}

$email = $_SESSION['verify_email'];
$name = $_SESSION['verify_name'] ?? '';
$error = '';
$success = '';

// Обработка формы проверки кода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = str_replace(' ', '', $_POST['code']);
    
    try {
        $pdo = getDbConnection();
        
        // 🔧 Проверяем код в БД
        $stmt = $pdo->prepare("
            SELECT id, email, code, expires_at, used 
            FROM verification_codes 
            WHERE email = ? AND code = ? AND used = 0
        ");
        $stmt->execute([$email, $code]);
        $verification = $stmt->fetch();
        
        if ($verification) {
            // 🔧 Проверяем не истёк ли срок (15 минут)
            if (strtotime($verification['expires_at']) > time()) {
                // ✅ Код верный — создаём пользователя
                $hashedPassword = $_SESSION['verify_password'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password_hash, name, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$email, $hashedPassword, $name]);
                $userId = $pdo->lastInsertId();
                
                // 🔧 Помечаем код как использованный
                $stmt = $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
                $stmt->execute([$verification['id']]);
                
                // 🔧 Создаём сессию пользователя
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_avatar'] = '';
                
                // 🔧 Очищаем временные данные верификации
                unset($_SESSION['verify_email']);
                unset($_SESSION['verify_name']);
                unset($_SESSION['verify_password']);
                unset($_SESSION['verify_code']);
                
                // 🔧 ВАЖНО: Очищаем буфер и закрываем сессию перед редиректом
                if (ob_get_length()) ob_end_clean();
                                
                // 🔧 Редирект на профиль
                header('Location: profile.php');
                exit;
                
            } else {
                $error = 'Срок действия кода истёк. Запросите новый.';
            }
        } else {
            $error = 'Неверный код. Проверьте 6 цифр из письма.';
        }
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        $error = 'Ошибка базы данных. Попробуйте позже.';
    }
}

// Обработка повторной отправки кода
if (isset($_POST['resend'])) {
    try {
        $pdo = getDbConnection();
        
        // 🔧 Генерируем новый код
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // 🔧 Помечаем старый код как использованный
        $stmt = $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE email = ? AND used = 0");
        $stmt->execute([$email]);
        
        // 🔧 Сохраняем новый код
        $stmt = $pdo->prepare("
            INSERT INTO verification_codes (email, code, expires_at, used)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$email, $code, $expiresAt]);
        
        // 🔧 ОТПРАВЛЯЕМ EMAIL С НОВЫМ КОДОМ
        try {
            require_once 'config/email.php';
            $emailSent = sendVerificationEmail($email, $code, $name);
            
            if ($emailSent) {
                $success = 'Новый код отправлен на ваш email!';
            } else {
                $error = 'Ошибка отправки кода. Попробуйте позже.';
            }
        } catch (Exception $e) {
            $error = 'Ошибка отправки кода. Попробуйте позже.';
            error_log('Email Error: ' . $e->getMessage());
        }
        
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        $error = 'Ошибка отправки кода. Попробуйте позже.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Подтверждение email — Poisq</title>
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
        .verify-container {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; padding: 40px 20px 20px;
        }
        .verify-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: #E8F0FE; display: flex;
            align-items: center; justify-content: center;
            margin-bottom: 24px;
        }
        .verify-icon svg { width: 40px; height: 40px; stroke: var(--primary); fill: none; stroke-width: 2; }
        .verify-title {
            font-size: 24px; font-weight: 700; color: var(--text);
            text-align: center; margin-bottom: 8px;
        }
        .verify-subtitle {
            font-size: 15px; color: var(--text-secondary);
            text-align: center; margin-bottom: 32px;
            max-width: 280px; line-height: 1.5;
        }
        .verify-email {
            font-size: 15px; color: var(--primary);
            font-weight: 600; text-align: center;
            margin-bottom: 32px;
        }
        .code-inputs {
            display: flex; gap: 8px; justify-content: center;
            margin-bottom: 24px; flex-wrap: wrap;
        }
        .code-input {
            width: 44px; height: 56px; border: 2px solid var(--border);
            border-radius: 12px; font-size: 24px; font-weight: 700;
            text-align: center; color: var(--text); background: var(--bg);
            outline: none; transition: all 0.2s ease;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
        }
        .code-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(46,115,216,0.1);
        }
        .code-input.filled { border-color: var(--primary); background: #E8F0FE; }
        .code-input.error { border-color: var(--danger); animation: shake 0.4s ease; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        .btn-submit {
            width: 100%; max-width: 280px; padding: 16px;
            border-radius: 12px; border: none;
            background: var(--primary); color: white;
            font-size: 16px; font-weight: 600;
            cursor: pointer; transition: all 0.2s ease;
            margin-bottom: 16px;
        }
        .btn-submit:active { transform: scale(0.98); background: var(--primary-dark); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .resend-container { text-align: center; margin-top: 8px; }
        .resend-text { font-size: 14px; color: var(--text-secondary); margin-bottom: 8px; }
        .btn-resend {
            font-size: 14px; color: var(--primary);
            font-weight: 600; background: none; border: none;
            cursor: pointer; padding: 8px 16px;
        }
        .btn-resend:disabled { color: var(--text-light); cursor: not-allowed; }
        .btn-resend:active:not(:disabled) { text-decoration: underline; }
        .alert {
            width: 100%; max-width: 320px; padding: 14px 16px;
            border-radius: 12px; font-size: 14px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-error { background: #FEF2F2; color: var(--danger); border: 1px solid #FECACA; }
        .alert-success { background: #F0FDF4; color: var(--success); border: 1px solid #BBF7D0; }
        .alert svg { width: 20px; height: 20px; flex-shrink: 0; }
        .verify-footer {
            background: var(--bg-secondary); border-top: 1px solid var(--border-light);
            flex-shrink: 0; padding: 16px 20px;
        }
        .footer-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 14px; }
        .footer-link { font-size: 12px; color: var(--text); text-decoration: none; }
        .footer-link:active { color: var(--primary); }
        @media (max-width: 380px) {
            .code-input { width: 38px; height: 50px; font-size: 20px; }
            .verify-title { font-size: 22px; }
        }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="header">
            <div class="header-left">
                <a href="register.php" class="btn-back" aria-label="Назад">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </a>
            </div>
            <div class="header-title">Подтверждение</div>
            <div class="header-spacer"></div>
        </header>

        <main class="verify-container">
            <div class="verify-icon">
                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            
            <h1 class="verify-title">Подтвердите email</h1>
            <p class="verify-subtitle">Мы отправили 6-значный код на ваш email</p>
            <div class="verify-email"><?php echo htmlspecialchars($email); ?></div>
            
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
            
            <form method="POST" action="verify.php" id="verifyForm">
                <div class="code-inputs">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="one-time-code" data-index="0">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="1">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="2">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="3">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="4">
                    <input type="tel" class="code-input" maxlength="1" inputmode="numeric" autocomplete="off" data-index="5">
                </div>
                
                <input type="hidden" name="code" id="fullCode">
                
                <button type="submit" class="btn-submit" id="submitBtn" disabled>Подтвердить</button>
            </form>
            
            <form method="POST" action="verify.php">
                <div class="resend-container">
                    <p class="resend-text">Не пришло письмо?</p>
                    <button type="submit" name="resend" class="btn-resend" id="resendBtn" disabled>
                        Отправить код ещё раз (<span id="timer">60</span>)
                    </button>
                </div>
            </form>
        </main>

        <footer class="verify-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Как пользоваться</a>
                <a href="#" class="footer-link">Условия</a>
                <a href="#" class="footer-link">О нас</a>
                <a href="contact.php" class="footer-link">Контакт</a>
            </div>
        </footer>
    </div>

    <script>
        const codeInputs = document.querySelectorAll('.code-input');
        const fullCodeInput = document.getElementById('fullCode');
        const submitBtn = document.getElementById('submitBtn');
        const resendBtn = document.getElementById('resendBtn');
        const timerSpan = document.getElementById('timer');
        
        codeInputs[0].focus();
        
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (!/^\d*$/.test(value)) { e.target.value = ''; return; }
                if (value.length === 1 && index < 5) {
                    codeInputs[index + 1].focus();
                    e.target.classList.add('filled');
                }
                updateSubmitButton();
                updateFullCode();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    codeInputs[index - 1].focus();
                    codeInputs[index - 1].value = '';
                    codeInputs[index - 1].classList.remove('filled');
                    updateSubmitButton();
                    updateFullCode();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                if (pasteData.length === 6) {
                    pasteData.split('').forEach((char, i) => {
                        if (codeInputs[i]) {
                            codeInputs[i].value = char;
                            codeInputs[i].classList.add('filled');
                        }
                    });
                    codeInputs[5].focus();
                    updateSubmitButton();
                    updateFullCode();
                }
            });
        });
        
        function updateSubmitButton() {
            const allFilled = Array.from(codeInputs).every(input => input.value.length === 1);
            submitBtn.disabled = !allFilled;
        }
        
        function updateFullCode() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            fullCodeInput.value = code;
        }
        
        let timeLeft = 60;
        const timer = setInterval(() => {
            timeLeft--;
            timerSpan.textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Отправить код ещё раз';
            }
        }, 1000);
        
        window.addEventListener('beforeunload', () => { clearInterval(timer); });
    </script>
</body>
</html>