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
                
                // Уведомление админу о новом пользователе
                try {
                    require_once 'config/email.php';
                    sendAdminNewUserEmail($name, $email);
                } catch (Exception $e) {
                    error_log('Admin notify error: ' . $e->getMessage());
                }
                
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after {
  margin: 0; padding: 0; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

:root {
  --primary:       #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark:  #2952D9;
  --text:          #0F172A;
  --text-secondary:#64748B;
  --text-light:    #94A3B8;
  --bg:            #FFFFFF;
  --bg-secondary:  #F8FAFC;
  --border:        #E2E8F0;
  --border-light:  #F1F5F9;
  --success:       #10B981;
  --success-bg:    #ECFDF5;
  --danger:        #EF4444;
  --danger-bg:     #FEF2F2;
  --radius:    16px;
  --radius-sm: 10px;
  --radius-xs:  8px;
}

html { -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto; }

body {
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-secondary);
  color: var(--text);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  touch-action: manipulation;
  overflow-y: auto;
}

.app-container {
  max-width: 430px;
  margin: 0 auto;
  background: var(--bg);
  min-height: 100vh; min-height: 100dvh;
  display: flex; flex-direction: column;
  position: relative;
}

/* ── ШАПКА ── */
.header {
  display: flex; align-items: center;
  padding: 0 16px;
  height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
  position: sticky; top: 0; z-index: 100;
}

.btn-back {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs); border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; text-decoration: none; flex-shrink: 0;
  transition: background 0.15s, transform 0.1s;
}
.btn-back:active { transform: scale(0.92); background: var(--border); }
.btn-back svg { width: 18px; height: 18px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }

.header-title {
  flex: 1; text-align: center;
  font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.3px;
}

.header-spacer { width: 38px; flex-shrink: 0; }

/* ── КОНТЕНТ ── */
.verify-wrap {
  flex: 1;
  display: flex; flex-direction: column;
  align-items: center;
  padding: 40px 24px 28px;
}

/* Иконка-круг */
.verify-icon {
  width: 76px; height: 76px;
  border-radius: 50%;
  background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(59,108,244,0.15);
}
.verify-icon svg {
  width: 36px; height: 36px;
  stroke: var(--primary); fill: none; stroke-width: 2;
}

.verify-title {
  font-size: 24px; font-weight: 800; color: var(--text);
  text-align: center; letter-spacing: -0.5px; margin-bottom: 8px;
}

.verify-subtitle {
  font-size: 14.5px; color: var(--text-secondary); font-weight: 500;
  text-align: center; margin-bottom: 6px; max-width: 270px; line-height: 1.55;
}

.verify-email {
  font-size: 14.5px; color: var(--primary); font-weight: 700;
  text-align: center; margin-bottom: 28px; letter-spacing: -0.2px;
}

/* ── АЛЕРТЫ ── */
.alert {
  width: 100%; padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 13.5px; font-weight: 500;
  display: flex; align-items: flex-start; gap: 10px;
  margin-bottom: 16px;
}
.alert-error   { background: var(--danger-bg); color: #991B1B; border: 1px solid #FECACA; }
.alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
.alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

/* ── КОД ── */
.code-inputs {
  display: flex; gap: 9px; justify-content: center;
  margin-bottom: 24px;
}

.code-input {
  width: 46px; height: 56px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 22px; font-weight: 800; color: var(--text);
  text-align: center; background: var(--bg);
  outline: none; font-family: 'Manrope', sans-serif;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
  -webkit-appearance: none;
}
.code-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59,108,244,0.1);
}
.code-input.filled {
  border-color: var(--primary);
  background: var(--primary-light);
}
.code-input.error {
  border-color: var(--danger);
  background: var(--danger-bg);
  animation: shake 0.38s ease;
}
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25%       { transform: translateX(-5px); }
  75%       { transform: translateX(5px); }
}

/* ── КНОПКИ ── */
.btn-submit {
  width: 100%; padding: 14px;
  border-radius: var(--radius-xs); border: none;
  background: var(--primary); color: white;
  font-size: 15px; font-weight: 700;
  font-family: 'Manrope', sans-serif;
  cursor: pointer; letter-spacing: -0.1px;
  transition: opacity 0.15s, transform 0.1s;
  margin-bottom: 20px;
}
.btn-submit:active { transform: scale(0.98); opacity: 0.88; }
.btn-submit:disabled { opacity: 0.4; cursor: not-allowed; }

.resend-wrap { text-align: center; }
.resend-text {
  font-size: 13.5px; color: var(--text-secondary); font-weight: 500;
  margin-bottom: 6px;
}
.btn-resend {
  font-size: 14px; font-weight: 700; color: var(--primary);
  background: none; border: none; cursor: pointer;
  font-family: 'Manrope', sans-serif;
  padding: 6px 14px;
  border-radius: var(--radius-xs);
  transition: background 0.15s, opacity 0.15s;
}
.btn-resend:not(:disabled):active { background: var(--primary-light); }
.btn-resend:disabled { color: var(--text-light); cursor: not-allowed; }

/* ── ФУТЕР ── */
.auth-footer {
  background: var(--bg-secondary);
  border-top: 1px solid var(--border-light);
  flex-shrink: 0; padding: 14px 20px 18px;
}
.footer-links {
  display: flex; flex-wrap: wrap;
  justify-content: center; gap: 8px 16px;
}
.footer-link {
  font-size: 12px; font-weight: 500;
  color: var(--text-secondary); text-decoration: none;
}
.footer-link:active { color: var(--primary); }

@media (max-width: 360px) {
  .code-input  { width: 40px; height: 50px; font-size: 20px; }
  .verify-title { font-size: 21px; }
  .verify-wrap  { padding: 32px 16px 20px; }
}
::-webkit-scrollbar { display: none; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">

  <!-- ШАПКА -->
  <header class="header">
    <a href="register.php" class="btn-back" aria-label="Назад">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="header-title">Подтверждение</span>
    <div class="header-spacer"></div>
  </header>

  <!-- КОНТЕНТ -->
  <main class="verify-wrap">

    <div class="verify-icon">
      <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    </div>

    <h1 class="verify-title">Подтвердите email</h1>
    <p class="verify-subtitle">Мы отправили 6-значный код на ваш адрес</p>
    <div class="verify-email"><?php echo htmlspecialchars($email); ?></div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
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
      <div class="resend-wrap">
        <p class="resend-text">Не получили письмо?</p>
        <button type="submit" name="resend" class="btn-resend" id="resendBtn" disabled>
          Отправить код ещё раз (<span id="timer">60</span>)
        </button>
      </div>
    </form>

  </main>

  <footer class="auth-footer">
    <div class="footer-links">
      <a href="/help.php"    class="footer-link">Помощь</a>
      <a href="/terms.php"   class="footer-link">Условия</a>
      <a href="/about.php"   class="footer-link">О нас</a>
      <a href="/contact.php" class="footer-link">Контакт</a>
    </div>
  </footer>

</div>

<script>
  const codeInputs  = document.querySelectorAll('.code-input');
  const fullCodeInput = document.getElementById('fullCode');
  const submitBtn   = document.getElementById('submitBtn');
  const resendBtn   = document.getElementById('resendBtn');
  const timerSpan   = document.getElementById('timer');

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
    fullCodeInput.value = Array.from(codeInputs).map(input => input.value).join('');
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