<?php
// 🔧 ВАЖНО: Буферизация вывода должна быть ПЕРВОЙ строкой!
ob_start();

// login.php — Вход Poisq (С ПРОВЕРКОЙ ЧЕРЕЗ БД)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// Подключение к БД
require_once 'config/database.php';

$error = '';

// ── AJAX / JSON режим ─────────────────────────────────────────
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Если уже авторизован
if (isset($_SESSION['user_id'])) {
    if ($isAjax) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'already' => true]);
        exit;
    }
    ob_end_clean();
    $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
    $redirect = preg_match('/^\/[a-zA-Z0-9\/_-]*\.php/', $redirect) ? $redirect : '/profile.php';
    header('Location: ' . $redirect);
    exit;
}

// Обработка формы входа
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Заполните все поля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный email';
    } else {
        try {
            $pdo  = getDbConnection();
            $stmt = $pdo->prepare("SELECT id, email, password_hash, name, avatar FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_avatar']= $user['avatar'];
                // Обновляем last_visit
                try { $pdo->prepare("UPDATE users SET last_visit = NOW() WHERE id = ?")->execute([$user['id']]); } catch (Exception $e) {}

                if (ob_get_length()) ob_end_clean();

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'name'    => $user['name'],
                        'avatar'  => $user['avatar'] ?? '',
                    ]);
                    exit;
                }

                ob_end_clean();
                $_redir = isset($_GET["redirect"]) && preg_match('/^\/[a-zA-Z0-9\/_-]*\.php/', $_GET["redirect"]) ? $_GET["redirect"] : "/profile.php";
                echo '<script>window.location.href="' . addslashes($_redir) . '";</script>';
                exit;

            } else {
                $error = 'Неверный email или пароль';
            }
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            $error = 'Ошибка базы данных. Попробуйте позже.';
        }
    }

    // AJAX — вернуть ошибку JSON
    if ($isAjax) {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Вход — Poisq</title>
<meta name="robots" content="noindex, nofollow">
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
  --shadow-card:0 2px 12px rgba(0,0,0,0.06);
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
  border: none;
  background: var(--bg-secondary);
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
  border-radius: var(--radius-xs);
  transition: background 0.15s;
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

/* ── ФОРМА ВХОДА ── */
.auth-wrap {
  flex: 1;
  display: flex; flex-direction: column;
  align-items: center;
  padding: 36px 24px 28px;
}

.auth-logo {
  height: 64px; width: auto; max-width: 220px;
  object-fit: contain; display: block;
  margin-bottom: 28px;
}

.auth-title {
  font-size: 24px; font-weight: 800; color: var(--text);
  text-align: center; letter-spacing: -0.5px;
  margin-bottom: 6px;
}

.auth-subtitle {
  font-size: 14.5px; color: var(--text-secondary); font-weight: 500;
  text-align: center; margin-bottom: 28px;
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

.forgot-link {
  font-size: 12.5px; font-weight: 600; color: var(--primary);
  text-decoration: none; text-align: right; display: block;
}
.forgot-link:active { opacity: 0.7; }

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

.auth-divider {
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0 4px; width: 100%;
}
.auth-divider::before, .auth-divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border-light);
}
.auth-divider span { font-size: 12.5px; color: var(--text-light); font-weight: 600; }

.auth-register {
  font-size: 14px; color: var(--text-secondary); font-weight: 500;
  text-align: center;
}
.auth-register a { color: var(--primary); font-weight: 700; text-decoration: none; }
.auth-register a:active { opacity: 0.7; }

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

/* ── ПРЕДУПРЕЖДЕНИЕ COOKIE ── */
.cookie-warning {
  display: none; width: 100%;
  margin-top: 10px; padding: 12px 14px;
  background: var(--warning-bg); border: 1px solid #FCD34D;
  border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; color: #78350F; line-height: 1.5;
}

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
  .auth-wrap  { padding: 28px 18px 20px; }
}
::-webkit-scrollbar { display: none; }

@media (min-width: 1024px) {
  body {
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 80px 24px 40px;
  }
  .app-container {
    max-width: 460px;
    width: 100%;
    padding-top: 0;
    border-radius: 20px;
    background: var(--bg);
    box-shadow: 0 4px 32px rgba(0,0,0,0.12);
    overflow: hidden;
  }
  .header { display: none; }
  .auth-wrap { padding: 36px 40px 32px; }
  .auth-logo { height: 36px; margin-bottom: 20px; }
  .auth-title { font-size: 26px; }
  .auth-footer { padding: 16px 40px 24px; }
}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/desktop.css">
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
    <span class="header-title">Вход</span>
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

    <h1 class="auth-title">С возвращением!</h1>
    <p class="auth-subtitle">Войдите в свой аккаунт Poisq</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form class="auth-form" method="POST" action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
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
                   placeholder="Введите пароль" required style="padding-right:44px;">
            <button type="button" onclick="togglePass('password','eyeLogin')"
                    style="position:absolute;right:0;top:0;height:100%;width:48px;background:none;border:none;cursor:pointer;padding:0;color:#9CA3AF;display:flex;align-items:center;justify-content:center;">
                <svg id="eyeLogin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <a href="forgot-password.php" class="forgot-link">Забыли пароль?</a>
      </div>
      <button type="submit" class="btn-submit">Войти</button>
      <div id="cookie-warning" class="cookie-warning">
        ⚠️ В вашем браузере отключены cookie. Для входа необходимо включить cookie.<br><br>
        <b>Opera на Android:</b> Меню → Настройки → Конфиденциальность → Файлы cookie → Разрешить
      </div>
    </form>

    <div class="auth-divider"><span>или</span></div>
    <p class="auth-register">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
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