<?php
// contact.php — Контакт
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
$isLoggedIn  = isset($_SESSION['user_id']);
$userName    = $isLoggedIn ? ($_SESSION['user_name']   ?? '') : '';
$userAvatar  = $isLoggedIn ? ($_SESSION['user_avatar'] ?? '') : '';
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';

// Проверка слотов
$slotsLeft = 3;
if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDbConnection();
        $st = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ? AND status = 'approved'");
        $st->execute([$_SESSION['user_id']]);
        $slotsLeft = max(0, 3 - (int)$st->fetchColumn());
    } catch (Exception $e) { $slotsLeft = 3; }
}

// WhatsApp поддержки
$supportWhatsapp = '';
try {
    if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
    $stWa = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'support_whatsapp'");
    $stWa->execute();
    $supportWhatsapp = $stWa->fetchColumn() ?: '';
} catch (Exception $e) { $supportWhatsapp = ''; }

// Обработка формы
$formSent    = false;
$formError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $formError = 'Пожалуйста, заполните все поля.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = 'Введите корректный email-адрес.';
    } elseif (mb_strlen($message) < 10) {
        $formError = 'Сообщение слишком короткое.';
    } else {
        // Отправка письма через PHPMailer
        $sent = false;
        try {
            require_once __DIR__ . '/config/email.php';
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
            require_once __DIR__ . '/phpmailer/Exception.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host        = SMTP_HOST;
            $mail->SMTPAuth    = true;
            $mail->Username    = SMTP_USER;
            $mail->Password    = SMTP_PASS;
            $mail->SMTPSecure  = SMTP_SECURE;
            $mail->Port        = SMTP_PORT;
            $mail->CharSet     = 'UTF-8';
            $mail->Encoding    = 'base64';
            $mail->SMTPAutoTLS = false;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->Timeout     = 30;

            $mail->setFrom('noreply@poisq.com', 'Poisq');
            $mail->addAddress('support@poisq.com', 'Poisq Support');
            $mail->addReplyTo($email, $name);

            $mail->Subject = 'Обращение с сайта от ' . $name;
            $mail->Body    = "Имя: $name\nEmail: $email\n\nСообщение:\n$message";
            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            // Fallback: mail()
            $headers  = "From: noreply@poisq.com\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
            $body     = "Имя: $name\nEmail: $email\n\nСообщение:\n$message";
            $sent     = mail('support@poisq.com', 'Обращение с сайта от ' . $name, $body, $headers);
        }
        if ($sent) {
            $formSent = true;
        } else {
            $formError = 'Не удалось отправить сообщение. Попробуйте написать нам напрямую на support@poisq.com';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Контакт — Poisq</title>
<meta name="description" content="Свяжитесь с командой Poisq. Poisq Solutions Ltd, 5 rue Henri-Christine, 1205 Женева. support@poisq.com">
<link rel="canonical" href="https://poisq.com/contact.php">
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Poisq">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary: #3B6CF4; --primary-light: #EEF2FF; --primary-dark: #2952D9;
  --text: #0F172A; --text-secondary: #64748B; --text-light: #94A3B8;
  --bg: #FFFFFF; --bg-secondary: #F8FAFC;
  --border: #E2E8F0; --border-light: #F1F5F9;
  --success: #10B981; --danger: #EF4444;
  --radius-sm: 12px; --radius-xs: 10px;
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; background: var(--bg-secondary); color: var(--text); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; padding: 10px 14px; height: 56px; gap: 10px; }
.btn-grid { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s; }
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.btn-add { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s, transform 0.1s; text-decoration: none; flex-shrink: 0; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* CONTENT */
.page-content { flex: 1; padding: 24px 16px 40px; }
.page-hero { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 8px 0 28px; }
.hero-icon { width: 64px; height: 64px; border-radius: 20px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
.hero-icon svg { width: 32px; height: 32px; stroke: var(--primary); fill: none; stroke-width: 1.8; }
.hero-title { font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.5px; }
.hero-sub { font-size: 14px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; }

/* INFO CARDS */
.info-cards { display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; }
.info-card { display: flex; align-items: center; gap: 14px; padding: 16px; background: var(--bg-secondary); border-radius: 14px; text-decoration: none; transition: all 0.15s; }
.info-card:active { transform: scale(0.98); }
.info-card-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ic-blue { background: var(--primary-light); }
.ic-blue svg { stroke: var(--primary); }
.ic-green { background: #F0FDF4; }
.ic-green svg { stroke: #16A34A; }
.ic-orange { background: #FFF7ED; }
.ic-orange svg { stroke: #EA580C; }
.ic-slate { background: var(--border-light); }
.ic-slate svg { stroke: var(--text-secondary); }
.info-card-icon svg { width: 20px; height: 20px; fill: none; stroke-width: 2; }
.info-card-text { flex: 1; min-width: 0; }
.info-card-label { font-size: 11px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.info-card-value { font-size: 14px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.info-card-sub { font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-top: 1px; }

/* SECTION DIVIDER */
.section-label { font-size: 13px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 14px; }

/* FORM */
.contact-form { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 700; color: var(--text-secondary); letter-spacing: 0.3px; }
.form-input, .form-textarea {
  width: 100%; padding: 13px 16px;
  background: var(--bg-secondary); border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; font-size: 14px; font-weight: 500; color: var(--text);
  outline: none; transition: border-color 0.2s, background 0.2s;
  appearance: none; -webkit-appearance: none;
}
.form-input:focus, .form-textarea:focus { border-color: var(--primary); background: var(--bg); box-shadow: 0 0 0 3px rgba(59,108,244,0.08); }
.form-input::placeholder, .form-textarea::placeholder { color: var(--text-light); }
.form-textarea { resize: none; height: 120px; line-height: 1.6; }
.form-submit {
  width: 100%; padding: 15px;
  background: var(--primary); color: white; border: none;
  border-radius: var(--radius-sm);
  font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; font-size: 15px; font-weight: 800;
  cursor: pointer; transition: all 0.15s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.form-submit svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2.5; }
.form-submit:active { transform: scale(0.98); background: var(--primary-dark); }
.form-submit:disabled { opacity: 0.6; cursor: not-allowed; }

/* ERROR / SUCCESS */
.form-error {
  padding: 12px 16px; border-radius: var(--radius-sm);
  background: #FFF1F2; border: 1px solid #FECDD3;
  font-size: 13px; font-weight: 600; color: var(--danger);
  display: flex; align-items: center; gap: 8px;
}
.form-error svg { width: 16px; height: 16px; stroke: var(--danger); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.form-success {
  padding: 20px; border-radius: 16px;
  background: #F0FDF4; border: 1px solid #BBF7D0;
  text-align: center;
}
.form-success-icon { font-size: 36px; margin-bottom: 10px; }
.form-success-title { font-size: 16px; font-weight: 800; color: #15803D; margin-bottom: 6px; }
.form-success-sub { font-size: 13px; color: #16A34A; font-weight: 500; }

/* MAP */
.map-section { margin-bottom: 28px; }
.map-container {
  border-radius: 16px; overflow: hidden;
  border: 1px solid var(--border-light);
  position: relative; background: #E8EEF4;
  height: 200px;
}
.map-placeholder {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  background: linear-gradient(135deg, #dbe4f0 0%, #c8d8ec 50%, #b8cce0 100%);
  position: relative; overflow: hidden;
}
/* Простая SVG-карта Женевы */
.map-placeholder svg.map-bg { position: absolute; inset: 0; width: 100%; height: 100%; }
.map-pin {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -100%);
  z-index: 2;
}
.map-pin-dot {
  width: 14px; height: 14px; border-radius: 50%;
  background: var(--primary); border: 3px solid white;
  box-shadow: 0 2px 8px rgba(59,108,244,0.5);
  position: relative; z-index: 2;
}
.map-pin-shadow {
  width: 20px; height: 6px; background: rgba(0,0,0,0.15);
  border-radius: 50%; margin: 2px auto 0;
  filter: blur(2px);
}
.map-label {
  position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
  background: white; border-radius: 8px; padding: 6px 12px;
  font-size: 12px; font-weight: 700; color: var(--text);
  box-shadow: 0 2px 8px rgba(0,0,0,0.12);
  white-space: nowrap; z-index: 2;
  display: flex; align-items: center; gap: 5px;
}
.map-label svg { width: 12px; height: 12px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.map-open-btn {
  position: absolute; top: 10px; right: 10px;
  background: white; border: none; border-radius: 8px;
  padding: 6px 10px; cursor: pointer;
  font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; font-size: 11px; font-weight: 700;
  color: var(--primary); display: flex; align-items: center; gap: 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.12); text-decoration: none;
  z-index: 3;
}
.map-open-btn svg { width: 12px; height: 12px; stroke: var(--primary); fill: none; stroke-width: 2.5; }

/* HOURS */
.hours-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-light); }
.hours-row:last-child { border-bottom: none; }
.hours-day { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.hours-time { font-size: 13px; font-weight: 700; color: var(--text); }
.hours-closed { font-size: 13px; font-weight: 600; color: var(--text-light); }

/* FOOTER */
.page-footer { padding: 16px 16px 32px; border-top: 1px solid var(--border-light); display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; transition: color 0.15s; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }

/* ANN MODAL */
.ann-modal { position: fixed; inset: 0; z-index: 500; background: var(--bg); transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; max-width: 430px; margin: 0 auto; }
.ann-modal.active { transform: translateY(0); }
.ann-header { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.ann-header-icon { font-size: 20px; }
.ann-title { flex: 1; font-size: 17px; font-weight: 800; color: var(--text); }
.ann-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.ann-city { padding: 12px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid var(--border); font-family: inherit; font-size: 14px; font-weight: 600; background: var(--bg-secondary); color: var(--text); outline: none; appearance: none; }
.ann-content { flex: 1; overflow-y: auto; padding: 16px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; padding: 48px 0; gap: 12px; }
.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-light); font-weight: 500; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 48px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; }
.ann-empty-icon svg { width: 26px; height: 26px; stroke: var(--text-light); fill: none; stroke-width: 1.8; }
.ann-empty h3 { font-size: 17px; font-weight: 800; color: var(--text); }
.ann-empty p { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 10px; }
.ann-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
.ann-item { border-radius: 12px; overflow: hidden; background: var(--bg-secondary); cursor: pointer; }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.ann-date { font-size: 10px; color: var(--text-light); font-weight: 600; padding: 6px 8px 2px; }
.ann-item-name { font-size: 12px; font-weight: 700; color: var(--text); padding: 0 8px 8px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* АККОРДЕОН */
.accord-item { border: 1px solid var(--border-light); border-radius: var(--radius-sm); margin-bottom: 8px; overflow: hidden; background: var(--bg); }
.accord-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; cursor: pointer; font-size: 14px; font-weight: 700; color: var(--text); letter-spacing: -0.1px; transition: background 0.15s; gap: 10px; }
.accord-head:active { background: var(--bg-secondary); }
.accord-arrow { width: 20px; height: 20px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; transition: transform 0.25s; }
.accord-item.open > .accord-head .accord-arrow { transform: rotate(180deg); }
.accord-body { max-height: 0; overflow: hidden; transition: max-height 0.28s ease; padding: 0 16px; font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.6; }
.accord-item.open > .accord-body { padding-top: 14px; padding-bottom: 16px; border-top: 1px solid var(--border-light); }

/* КНОПКА WHATSAPP */
.wa-btn { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; padding: 14px; border-radius: var(--radius-sm); border: none; background: #25D366; color: white; font-size: 14.5px; font-weight: 700; font-family: inherit; cursor: pointer; text-decoration: none; transition: opacity 0.15s, transform 0.1s; letter-spacing: -0.1px; }
.wa-btn:active { opacity: 0.85; transform: scale(0.98); }
.wa-btn svg { width: 22px; height: 22px; fill: white; flex-shrink: 0; }

/* КНОПКА ВТОРИЧНАЯ */
.btn-outlined { display: block; width: 100%; padding: 13px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--bg); color: var(--text); font-size: 14px; font-weight: 700; font-family: inherit; cursor: pointer; text-align: center; text-decoration: none; transition: border-color 0.15s, background 0.15s; letter-spacing: -0.1px; }
.btn-outlined:active { background: var(--bg-secondary); border-color: var(--text-secondary); }

@media (min-width: 1024px) {
  .app-container { max-width: 720px; padding-top: 64px; }
  .page-header { display: none; }
  .page-content { padding: 32px 24px 48px; }
}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/desktop.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">

  <div class="page-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24">
            <circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/>
            <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
          </svg>
        </button>
      </div>
      <div class="header-logo">
        <a href="/"><img src="/logo.png" alt="Poisq" onerror="this.style.display='none'"></a>
      </div>
      <div style="width:84px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">
        <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
        <button class="btn-add" onclick="openSlotsModal()" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <?php else: ?>
        <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </a>
        <?php endif; ?>
        <button class="btn-burger" id="menuToggle" aria-label="Меню">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </div>

  <div class="page-content">

    <div class="page-hero">
      <div class="hero-icon">
        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div class="hero-title">Свяжитесь с нами</div>
      <div class="hero-sub">Poisq Solutions Ltd — мы всегда рады помочь</div>
    </div>

    <!-- INFO CARDS -->
    <div class="info-cards">
      <a href="mailto:support@poisq.com" class="info-card">
        <div class="info-card-icon ic-blue">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <div class="info-card-text">
          <div class="info-card-label">Email</div>
          <div class="info-card-value">support@poisq.com</div>
          <div class="info-card-sub">Среднее время ответа: 24 часа</div>
        </div>
      </a>

      <div class="info-card">
        <div class="info-card-icon ic-slate">
          <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="info-card-text">
          <div class="info-card-label">Адрес</div>
          <div class="info-card-value">5 rue Henri-Christine</div>
          <div class="info-card-sub">1205 Женева, Швейцария</div>
        </div>
      </div>

      <div class="info-card">
        <div class="info-card-icon ic-green">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="info-card-text">
          <div class="info-card-label">Часы поддержки</div>
          <div class="info-card-value">Пн–Пт, 09:00–18:00</div>
          <div class="info-card-sub">По женевскому времени (CET/CEST)</div>
        </div>
      </div>
    </div>

    <!-- CONTACT FORM -->
    <div class="section-label">Написать нам</div>

    <?php if ($formSent): ?>
    <div class="form-success">
      <div class="form-success-icon">✅</div>
      <div class="form-success-title">Сообщение отправлено!</div>
      <div class="form-success-sub">Мы ответим вам в течение 24 часов на указанный email.</div>
    </div>
    <?php else: ?>

    <?php if ($formError): ?>
    <div class="form-error" style="margin-bottom:12px">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($formError); ?>
    </div>
    <?php endif; ?>

    <form class="contact-form" method="POST" action="/contact.php">
      <div class="form-group">
        <label class="form-label" for="contactName">Ваше имя</label>
        <input class="form-input" type="text" id="contactName" name="name"
          placeholder="Как вас зовут?"
          value="<?php echo htmlspecialchars($_POST['name'] ?? ($isLoggedIn ? $userName : '')); ?>"
          required>
      </div>
      <div class="form-group">
        <label class="form-label" for="contactEmail">Email</label>
        <input class="form-input" type="email" id="contactEmail" name="email"
          placeholder="your@email.com"
          value="<?php echo htmlspecialchars($_POST['email'] ?? ($isLoggedIn ? ($_SESSION['user_email'] ?? '') : '')); ?>"
          required>
      </div>
      <div class="form-group">
        <label class="form-label" for="contactMessage">Сообщение</label>
        <textarea class="form-textarea" id="contactMessage" name="message"
          placeholder="Опишите ваш вопрос или предложение..."
          required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
      </div>
      <button type="submit" class="form-submit" id="submitBtn">
        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Отправить сообщение
      </button>
    </form>
    <?php endif; ?>

    <!-- MAP -->
    <div class="map-section" style="margin-top:32px">
      <div class="section-label" style="margin-bottom:12px">Наш офис</div>
      <div class="map-container">
        <div class="map-placeholder">
          <!-- Простая декоративная SVG карта -->
          <svg class="map-bg" viewBox="0 0 430 200" xmlns="http://www.w3.org/2000/svg">
            <!-- Фон воды (Женевское озеро) -->
            <ellipse cx="215" cy="160" rx="280" ry="90" fill="#B8D4E8" opacity="0.6"/>
            <!-- Улицы -->
            <line x1="0" y1="90" x2="430" y2="90" stroke="white" stroke-width="12" opacity="0.5"/>
            <line x1="0" y1="120" x2="430" y2="120" stroke="white" stroke-width="8" opacity="0.4"/>
            <line x1="120" y1="0" x2="120" y2="200" stroke="white" stroke-width="8" opacity="0.4"/>
            <line x1="200" y1="0" x2="200" y2="200" stroke="white" stroke-width="12" opacity="0.5"/>
            <line x1="300" y1="0" x2="280" y2="200" stroke="white" stroke-width="6" opacity="0.3"/>
            <line x1="60" y1="0" x2="80" y2="200" stroke="white" stroke-width="6" opacity="0.3"/>
            <!-- Блоки зданий -->
            <rect x="130" y="50" width="50" height="30" rx="4" fill="white" opacity="0.35"/>
            <rect x="210" y="50" width="70" height="30" rx="4" fill="white" opacity="0.35"/>
            <rect x="30" y="50" width="70" height="30" rx="4" fill="white" opacity="0.25"/>
            <rect x="320" y="50" width="80" height="30" rx="4" fill="white" opacity="0.25"/>
            <rect x="130" y="100" width="50" height="10" rx="2" fill="white" opacity="0.3"/>
            <rect x="210" y="100" width="60" height="10" rx="2" fill="white" opacity="0.3"/>
          </svg>
          <!-- Пин -->
          <div class="map-pin">
            <div class="map-pin-dot"></div>
            <div class="map-pin-shadow"></div>
          </div>
          <!-- Лейбл -->
          <div class="map-label">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            5 rue Henri-Christine, Genève
          </div>
          <!-- Кнопка открыть в картах -->
          <a href="https://maps.google.com/?q=5+rue+Henri-Christine+1205+Geneva+Switzerland" target="_blank" class="map-open-btn">
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Открыть
          </a>
        </div>
      </div>
    </div>

    <!-- HOURS TABLE -->
    <div style="margin-top:24px">
      <div class="section-label" style="margin-bottom:12px">Часы работы поддержки</div>
      <div style="background:var(--bg-secondary);border-radius:14px;padding:4px 16px;">
        <div class="hours-row"><span class="hours-day">Понедельник</span><span class="hours-time">09:00 — 18:00</span></div>
        <div class="hours-row"><span class="hours-day">Вторник</span><span class="hours-time">09:00 — 18:00</span></div>
        <div class="hours-row"><span class="hours-day">Среда</span><span class="hours-time">09:00 — 18:00</span></div>
        <div class="hours-row"><span class="hours-day">Четверг</span><span class="hours-time">09:00 — 18:00</span></div>
        <div class="hours-row"><span class="hours-day">Пятница</span><span class="hours-time">09:00 — 18:00</span></div>
        <div class="hours-row"><span class="hours-day">Суббота</span><span class="hours-closed">Выходной</span></div>
        <div class="hours-row"><span class="hours-day">Воскресенье</span><span class="hours-closed">Выходной</span></div>
      </div>
    </div>

    <!-- НУЖНА ПОМОЩЬ -->
    <div style="margin-top:24px">
      <div class="accord-item" id="accordHelp">
        <div class="accord-head" onclick="toggleAccord('accordHelp')">
          <span>Нужна помощь?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">
          <p style="margin-bottom:14px;">Сначала загляните в раздел Помощь — там ответы на большинство вопросов</p>
          <a href="/help.php" class="btn-outlined" style="margin-bottom:14px;">Открыть раздел Помощь</a>

          <!-- Второй уровень — написать напрямую -->
          <div class="accord-item" id="accordDirect" style="margin-bottom:0;">
            <div class="accord-head" onclick="toggleAccord('accordDirect')">
              <span>Не нашли ответ? Написать напрямую</span>
              <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
            </div>
            <div class="accord-body">
              <p style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:4px;">Горячая линия</p>
              <p style="margin-bottom:14px;">Для срочных и важных вопросов. Отвечаем в рабочее время.</p>
              <?php if ($supportWhatsapp): ?>
              <a class="wa-btn" href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $supportWhatsapp); ?>?text=Здравствуйте%2C%20у%20меня%20вопрос%20по%20Poisq" target="_blank">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Написать в WhatsApp
              </a>
              <?php else: ?>
              <p style="font-size:13px;color:var(--text-light);text-align:center;padding:8px 0;">Горячая линия временно недоступна</p>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div>

  <div class="page-footer">
    <a href="/useful.php" class="footer-link">Полезное</a>
            <a href="/help.php" class="footer-link">Помощь</a>
    <a href="/terms.php" class="footer-link">Условия</a>
    <a href="/about.php" class="footer-link">О нас</a>
    <a href="/contact.php" class="footer-link active">Контакт</a>
  </div>

</div>

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- ANN MODAL -->
<div class="ann-modal" id="annModal">
  <div class="ann-header"><span class="ann-header-icon">📢</span><span class="ann-title">Свежие сервисы</span><button class="ann-close" onclick="closeAnnModal()"><svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div>
  <div class="ann-city"><select id="annCitySelect" class="city-select" onchange="filterByCity()"><option>Загрузка...</option></select></div>
  <div class="ann-content" id="annContent"><div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div></div>
</div>

<script>
function toggleAccord(id) {
  const el = document.getElementById(id);
  const body = el.querySelector(':scope > .accord-body');
  const opening = !el.classList.contains('open');

  // При открытии внешнего — закрыть внутренний
  if (id === 'accordHelp' && opening) {
    const inner = document.getElementById('accordDirect');
    if (inner && inner.classList.contains('open')) {
      inner.classList.remove('open');
      const innerBody = inner.querySelector(':scope > .accord-body');
      innerBody.style.maxHeight = '0';
    }
  }

  el.classList.toggle('open');

  if (opening) {
    body.style.maxHeight = body.scrollHeight + 'px';
    body.addEventListener('transitionend', function onEnd() {
      body.removeEventListener('transitionend', onEnd);
      if (el.classList.contains('open')) body.style.maxHeight = 'none';
    });
  } else {
    // Зафиксировать текущую высоту, потом схлопнуть
    body.style.maxHeight = body.scrollHeight + 'px';
    requestAnimationFrame(() => requestAnimationFrame(() => {
      body.style.maxHeight = '0';
    }));
  }
}

// Submit лоадинг
const form = document.querySelector('.contact-form');
const submitBtn = document.getElementById('submitBtn');
if (form && submitBtn) {
  form.addEventListener('submit', () => {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div style="width:18px;height:18px;border:2px solid rgba(255,255,255,0.4);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite"></div> Отправка...';
  });
}

let annCityId = null;
async function openAnnModal() {
  const modal = document.getElementById('annModal'), content = document.getElementById('annContent');
  modal.classList.add('active'); document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr = await fetch('/api/get-user-country.php'), cd = await cr.json(), cc = cd.country_code || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc), cities = await cir.json();
    const sel = document.getElementById('annCitySelect'); sel.innerHTML = '';
    cities.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name_lat ? c.name_lat + ' (' + c.name + ')' : c.name; sel.appendChild(o); if (c.is_capital == 1 && !annCityId) annCityId = c.id; });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch(e) { document.getElementById('annContent').innerHTML = '<div class="ann-empty"><h3>Ошибка загрузки</h3></div>'; }
}
function closeAnnModal() { document.getElementById('annModal').classList.remove('active'); document.body.style.overflow = ''; }
async function filterByCity() { annCityId = document.getElementById('annCitySelect').value; await loadAnnServices(annCityId); }
async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5'), d = await r.json(), sv = d.services || [];
    if (!sv.length) { content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>Нет новых за последние 5 дней</p></div>'; return; }
    const byCat = {}; sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += '<div class="ann-category"><div class="ann-cat-title">' + cat + '</div><div class="ann-grid">';
      list.forEach(s => { let photo = 'https://via.placeholder.com/200?text=Poisq'; if (s.photo) { try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; } catch(e) {} } const now = new Date(), d2 = new Date(s.created_at), diff = Math.floor((now-d2)/86400000); const ds = diff===0?'Сегодня':diff===1?'Вчера':diff<5?diff+' дн.':d2.toLocaleDateString('ru-RU',{day:'numeric',month:'short'}); html += '<div class="ann-item" onclick="location.href=\'/service/'+s.id+'\'"><img src="'+photo+'" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'"><div class="ann-date">'+ds+'</div><div class="ann-item-name">'+s.name+'</div></div>'; });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) { content.innerHTML = '<div class="ann-empty"><h3>Ошибка</h3></div>'; }
}
</script>
<?php if ($isLoggedIn && $slotsLeft <= 0): ?>
<div id="slotsModal" style="display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,0.5);align-items:flex-end;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:430px;border-radius:24px 24px 0 0;padding:32px 24px 40px;animation:slideUpSlots 0.3s ease-out;">
    <div style="text-align:center;margin-bottom:20px;">
      <div style="width:64px;height:64px;border-radius:50%;background:#FEF2F2;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#EF4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="font-size:19px;font-weight:700;color:#1F2937;margin-bottom:8px;">Все слоты заняты</div>
      <div style="font-size:14px;color:#6B7280;line-height:1.6;">Вы разместили максимальное количество сервисов (3 из 3).<br>Удалите один из существующих, чтобы добавить новый.</div>
    </div>
    <div style="background:#F0FDF4;border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#065F46;line-height:1.5;">💡 Перейдите в <strong>«Мои сервисы»</strong>, чтобы удалить или управлять сервисами</div>
    <a href="/my-services.php" style="display:block;width:100%;padding:14px;background:#3B6CF4;color:white;border-radius:12px;text-align:center;font-size:15px;font-weight:600;text-decoration:none;margin-bottom:10px;">Перейти в Мои сервисы</a>
    <button onclick="closeSlotsModal()" style="display:block;width:100%;padding:14px;background:#F3F4F6;color:#374151;border-radius:12px;border:none;font-size:15px;cursor:pointer;">Закрыть</button>
  </div>
</div>
<style>@keyframes slideUpSlots{from{transform:translateY(100%)}to{transform:translateY(0)}}</style>
<script>
function openSlotsModal(){document.getElementById("slotsModal").style.display="flex";document.body.style.overflow="hidden";}
function closeSlotsModal(){document.getElementById("slotsModal").style.display="none";document.body.style.overflow="";}
document.getElementById("slotsModal").addEventListener("click",function(e){if(e.target===this)closeSlotsModal();});
</script>
<?php endif; ?>
</body>
</html>
