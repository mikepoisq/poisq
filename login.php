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
    header('Location: profile.php');
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
                echo '<script>window.location.href="profile.php";</script>';
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

/* ── БОКОВОЕ МЕНЮ ── */
.side-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.4);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 399; display: none;
  animation: fadeOverlay 0.2s ease;
}
.side-overlay.active { display: block; }
@keyframes fadeOverlay { from { opacity: 0; } to { opacity: 1; } }

.side-menu {
  position: fixed; top: 0; right: -290px; width: 270px; height: 100%;
  background: var(--bg); z-index: 400;
  transition: right 0.3s cubic-bezier(.4,0,.2,1);
  box-shadow: -8px 0 32px rgba(0,0,0,0.12);
  display: flex; flex-direction: column;
  border-radius: 20px 0 0 20px;
}
.side-menu.active { right: 0; }

.side-menu-head {
  padding: 52px 20px 20px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-light);
}
.side-user { display: flex; align-items: center; gap: 12px; }
.side-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-secondary); font-size: 20px;
  flex-shrink: 0;
}
.side-user-name  { font-size: 15px; font-weight: 700; color: var(--text); letter-spacing: -0.2px; }
.side-user-email { font-size: 12.5px; color: var(--text-secondary); margin-top: 1px; font-weight: 500; }

.side-items { flex: 1; overflow-y: auto; padding: 8px 0; }
.side-item {
  display: flex; align-items: center; gap: 13px;
  padding: 13px 20px;
  color: var(--text); text-decoration: none;
  font-size: 14.5px; font-weight: 600;
  transition: background 0.15s;
  letter-spacing: -0.1px;
}
.side-item:active { background: var(--bg-secondary); }
.side-item svg { width: 19px; height: 19px; stroke: var(--text-secondary); fill: none; stroke-width: 2; flex-shrink: 0; }
.side-divider { height: 1px; background: var(--border-light); margin: 6px 20px; }

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
    <span class="header-title">Вход</span>
    <div class="header-side right">
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- OVERLAY + БОКОВОЕ МЕНЮ -->
  <div class="side-overlay" id="menuOverlay" onclick="closeMenu()"></div>
  <div class="side-menu" id="sideMenu">
    <div class="side-menu-head">
      <div class="side-user">
        <div class="side-avatar">👤</div>
        <div>
          <div class="side-user-name">Гость</div>
          <div class="side-user-email">Войдите в аккаунт</div>
        </div>
      </div>
    </div>
    <div class="side-items">
      <a href="index.php" class="side-item">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Главная
      </a>
      <a href="register.php" class="side-item">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Регистрация
      </a>
      <a href="useful.php" class="side-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Полезное
      </a>
      <div class="side-divider"></div>
      <a href="contact.php" class="side-item">
        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Контакт
      </a>
    </div>
  </div>

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

    <form class="auth-form" method="POST" action="login.php">
      <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input type="email" class="field-input" id="email" name="email"
               placeholder="example@mail.com" required
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="field-group">
        <label class="field-label" for="password">Пароль</label>
        <input type="password" class="field-input" id="password" name="password"
               placeholder="Введите пароль" required>
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

  const menuToggle  = document.getElementById('menuToggle');
  const sideMenu    = document.getElementById('sideMenu');
  const menuOverlay = document.getElementById('menuOverlay');

  function openMenu() {
    menuToggle.classList.add('active');
    sideMenu.classList.add('active');
    menuOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu() {
    menuToggle.classList.remove('active');
    sideMenu.classList.remove('active');
    menuOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  menuToggle.addEventListener('click', () => {
    sideMenu.classList.contains('active') ? closeMenu() : openMenu();
  });
</script>
</body>
</html>