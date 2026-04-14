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
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Poisq">
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
  --warning:       #F59E0B;
  --warning-bg:    #FFFBEB;
  --danger:        #EF4444;
  --danger-bg:     #FEF2F2;
  --shadow-sm:  0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
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
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px;
  height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
  position: sticky; top: 0; z-index: 100;
}

.header-side { display: flex; align-items: center; width: 44px; }
.header-side.right { justify-content: flex-end; }
.header-title { font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }

.btn-grid {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }

.btn-burger {
  width: 38px; height: 38px;
  display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px;
  cursor: pointer; background: var(--bg-secondary); border: none;
  border-radius: var(--radius-xs); transition: background 0.15s;
}
.btn-burger span {
  display: block; width: 18px; height: 2px;
  background: var(--text-secondary); border-radius: 2px;
  transition: all 0.22s cubic-bezier(.4,0,.2,1);
  transform-origin: center;
}
.btn-burger:active { background: var(--border); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg);  background: var(--text); }
.btn-burger.active span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--text); }

/* ── ФОРМА ── */
.auth-wrap {
  flex: 1;
  display: flex; flex-direction: column;
  align-items: center;
  padding: 32px 24px 28px;
}

.auth-logo {
  height: 64px; width: auto; max-width: 220px;
  object-fit: contain; display: block; margin-bottom: 24px;
}

.auth-title {
  font-size: 24px; font-weight: 800; color: var(--text);
  text-align: center; letter-spacing: -0.5px; margin-bottom: 6px;
}

.auth-subtitle {
  font-size: 14px; color: var(--text-secondary); font-weight: 500;
  text-align: center; margin-bottom: 24px; max-width: 300px; line-height: 1.55;
}

.auth-form {
  width: 100%; display: flex; flex-direction: column; gap: 14px;
}

.field-group { display: flex; flex-direction: column; gap: 6px; }

.field-label {
  font-size: 12px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.5px;
}

.field-input {
  width: 100%; padding: 13px 14px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 15px; font-weight: 500; color: var(--text);
  background: var(--bg); outline: none;
  font-family: 'Manrope', sans-serif;
  transition: border-color 0.15s, box-shadow 0.15s;
  -webkit-appearance: none;
}
.field-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59,108,244,0.1);
}
.field-input::placeholder { color: var(--text-light); font-weight: 500; }

.btn-submit {
  width: 100%; padding: 14px;
  border-radius: var(--radius-xs); border: none;
  background: var(--primary); color: white;
  font-size: 15px; font-weight: 700;
  font-family: 'Manrope', sans-serif;
  cursor: pointer; letter-spacing: -0.1px;
  transition: opacity 0.15s, transform 0.1s;
  margin-top: 4px;
}
.btn-submit:active { transform: scale(0.98); opacity: 0.88; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.cookie-warning {
  display: none; width: 100%;
  margin-top: 2px; padding: 12px 14px;
  background: var(--warning-bg); border: 1px solid #FCD34D;
  border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; color: #78350F; line-height: 1.5;
}

.auth-divider {
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0 4px; width: 100%;
}
.auth-divider::before, .auth-divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border-light);
}
.auth-divider span { font-size: 12.5px; color: var(--text-light); font-weight: 600; }

.auth-login {
  font-size: 14px; color: var(--text-secondary); font-weight: 500; text-align: center;
}
.auth-login a { color: var(--primary); font-weight: 700; text-decoration: none; }
.auth-login a:active { opacity: 0.7; }

/* ── ALERT ── */
.alert {
  width: 100%; padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 13.5px; font-weight: 500;
  display: flex; align-items: flex-start; gap: 10px;
  margin-bottom: 4px;
}
.alert-error { background: var(--danger-bg); color: #991B1B; border: 1px solid #FECACA; }
.alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

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
  .auth-title { font-size: 21px; }
  .auth-wrap  { padding: 24px 18px 20px; }
}
::-webkit-scrollbar { display: none; }

/* ── БЛОК ПРЕИМУЩЕСТВ ── */
.benefits-block {
  width: 100%;
  margin-bottom: 20px;
  display: flex; flex-direction: column; gap: 8px;
}
.benefit-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 10px 14px;
  border-left: 2.5px solid var(--primary);
  border-radius: 0 10px 10px 0;
  background: var(--bg-secondary);
}
.benefit-item:nth-child(1) { border-color: #378ADD; }
.benefit-item:nth-child(2) { border-color: #1D9E75; }
.benefit-item:nth-child(3) { border-color: #D4537E; }
.benefit-icon {
  font-size: 16px; flex-shrink: 0; line-height: 1; margin-top: 2px;
}
.benefit-title {
  font-size: 13px; font-weight: 700; color: var(--text); letter-spacing: -0.1px;
}
.benefit-desc {
  font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-top: 2px; line-height: 1.35;
}
.benefit-note {
  font-size: 12px; font-weight: 600; color: var(--text-secondary);
  text-align: center; padding-top: 4px;
}

</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">

  <!-- ШАПКА -->
  <header class="header">
    <div class="header-side">
      <button class="btn-grid" aria-label="Сервисы">
        <svg viewBox="0 0 24 24">
          <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
          <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
          <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
        </svg>
      </button>
    </div>
    <span class="header-title">Регистрация</span>
    <div class="header-side right">
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <?php include __DIR__ . '/includes/menu.php'; ?>

  <!-- ФОРМА -->
  <main class="auth-wrap">
    <a href="index.php">
      <img src="logo.png" alt="Poisq" class="auth-logo" onerror="this.style.display='none'">
    </a>

    <h1 class="auth-title">Создать аккаунт</h1>
    <p class="auth-subtitle">Присоединяйтесь к Poisq и найдите русскоговорящих специалистов за границей</p>


    <div class="benefits-block">
      <div class="benefit-item">
        <div class="benefit-icon">🆓</div>
        <div class="benefit-text">
          <div class="benefit-title">Публикуйте бесплатно</div>
          <div class="benefit-desc">Добавляйте свои сервисы — это ничего не стоит</div>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🔖</div>
        <div class="benefit-text">
          <div class="benefit-title">Сохраняйте в избранное</div>
          <div class="benefit-desc">Нужные сервисы всегда под рукой</div>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">⭐</div>
        <div class="benefit-text">
          <div class="benefit-title">Оставляйте отзывы</div>
          <div class="benefit-desc">Влияйте на сообщество и помогайте другим</div>
        </div>
      </div>
      <div class="benefit-note">⚡ Регистрация займёт 30 секунд</div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form class="auth-form" method="POST" action="register.php">
      <div class="field-group">
        <label class="field-label" for="name">Имя</label>
        <input type="text" class="field-input" id="name" name="name"
               placeholder="Ваше имя" required
               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
      </div>
      <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input type="email" class="field-input" id="email" name="email"
               placeholder="example@mail.com" required
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); 
?>">
      </div>
      <div class="field-group">
        <label class="field-label" for="password">Пароль</label>
        <div style="position:relative;">
            <input type="password" class="field-input" id="password" name="password"
                   placeholder="Минимум 6 символов" required minlength="6" style="padding-right:44px;">
            <button type="button" onclick="togglePass('password','eye1')"
                    style="position:absolute;right:0;top:0;height:100%;width:48px;background:none;border:none;cursor:pointer;padding:0;color:#9CA3AF;display:flex;align-items:center;justify-content:center;">
                <svg id="eye1" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
      </div>
      <div class="field-group">
        <label class="field-label" for="password_confirm">Повторить пароль</label>
        <div style="position:relative;">
            <input type="password" class="field-input" id="password_confirm" name="password_confirm"
                   placeholder="Повторите пароль" required minlength="6" style="padding-right:44px;">
            <button type="button" onclick="togglePass('password_confirm','eye2')"
                    style="position:absolute;right:0;top:0;height:100%;width:48px;background:none;border:none;cursor:pointer;padding:0;color:#9CA3AF;display:flex;align-items:center;justify-content:center;">
                <svg id="eye2" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
      </div>
      <button type="submit" class="btn-submit">Зарегистрироваться</button>
      <div id="cookie-warning" class="cookie-warning">
        ⚠️ В вашем браузере отключены cookie. Для регистрации необходимо включить cookie.<br><br>
        <b>Opera на Android:</b> Меню → Настройки → Конфиденциальность → Файлы cookie → Разрешить
      </div>
    </form>

    <div class="auth-divider"><span>или</span></div>
    <p class="auth-login">Уже есть аккаунт? <a href="login.php">Войти</a></p>
  </main>

  <footer class="auth-footer">
    <div class="footer-links">
      <a href="/useful.php"  class="footer-link">Полезное</a>
      <a href="/help.php"    class="footer-link">Помощь</a>
      <a href="/terms.php"   class="footer-link">Условия</a>
      <a href="/about.php"   class="footer-link">О нас</a>
      <a href="/contact.php" class="footer-link">Контакт</a>
    </div>
  </footer>

</div>

<script>
  // Проверка cookie
  document.cookie = 'cookietest=1; SameSite=Lax';
  if (document.cookie.indexOf('cookietest') === -1) {
    document.getElementById('cookie-warning').style.display = 'block';
  } else {
    document.cookie = 'cookietest=1; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  }

</script>

<script>
function togglePass(inputId, eyeId) {
    var inp = document.getElementById(inputId);
    var eye = document.getElementById(eyeId);
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
        eye.parentElement.style.color = '#2E73D8';
    } else {
        inp.type = 'password';
        eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        eye.parentElement.style.color = '#9CA3AF';
    }
}
</script>
</body>
</html>