<?php
// my-services.php — Мои сервисы Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// 🔧 ПРОВЕРКА АВТОРИЗАЦИИ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Пользователь';
$userEmail = $_SESSION['user_email'] ?? '';
$userAvatar = $_SESSION['user_avatar'] ?? '';
$userInitial = strtoupper(substr($userName, 0, 1));

// 🔧 ПОЛУЧАЕМ КОЛИЧЕСТВО СЕРВИСОВ
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE user_id = ?");
    $stmt->execute([$userId]);
    $serviceCount = $stmt->fetch()['count'];
    
    // 🔧 ПОЛУЧАЕМ ВСЕ СЕРВИСЫ ПОЛЬЗОВАТЕЛЯ
    $stmt = $pdo->prepare("
        SELECT * FROM services 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    $services = [];
    $serviceCount = 0;
}

$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Мои сервисы — Poisq</title>
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
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark: #2952D9;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --bg-card: #FFFFFF;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --success: #10B981;
  --success-bg: #ECFDF5;
  --warning: #F59E0B;
  --warning-bg: #FFFBEB;
  --danger: #EF4444;
  --danger-bg: #FEF2F2;
  --pending-color: #8B5CF6;
  --pending-bg: #F5F3FF;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 6px rgba(0,0,0,0.04);
  --shadow-card: 0 2px 12px rgba(0,0,0,0.06);
  --radius: 16px;
  --radius-sm: 10px;
  --radius-xs: 8px;
}

html {
  -webkit-overflow-scrolling: touch;
  overflow-y: auto;
  height: auto;
}

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
  min-height: 100vh;
  min-height: 100dvh;
  position: relative;
  display: flex;
  flex-direction: column;
}

/* ── ШАПКА ── */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
  height: 58px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left { display: flex; align-items: center; gap: 10px; }

.btn-back {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none;
  background: var(--bg-secondary);
  color: var(--text);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; text-decoration: none;
  transition: background 0.15s, transform 0.1s;
}
.btn-back:active { transform: scale(0.92); background: var(--border); }
.btn-back svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.2; }

.header-title { font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }

/* ── УВЕДОМЛЕНИЯ ── */
.alert {
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 13.5px;
  font-weight: 500;
  margin-bottom: 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
.alert-error   { background: var(--danger-bg);  color: #991B1B; border: 1px solid #FECACA; }
.alert svg { width: 17px; height: 17px; flex-shrink: 0; margin-top: 1px; }
.alert-close {
  margin-left: auto; background: none; border: none;
  cursor: pointer; color: inherit; opacity: 0.5; padding: 0;
  font-size: 16px; line-height: 1; flex-shrink: 0;
}

/* ── КОНТЕНТ ── */
.content {
  flex: 1;
  padding: 16px;
  padding-bottom: 32px;
}

/* ── СЛОТ-БЛОК ── */
.slots-block {
  background: var(--bg-secondary);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-bottom: 14px;
  border: 1px solid var(--border-light);
}

.slots-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.slots-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.slots-count {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
}

.slots-count.full { color: var(--danger); }
.slots-count.almost { color: var(--warning); }

.slots-bar {
  height: 5px;
  background: var(--border);
  border-radius: 99px;
  overflow: hidden;
}

.slots-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.5s cubic-bezier(.4,0,.2,1);
  background: var(--primary);
}
.slots-fill.almost { background: var(--warning); }
.slots-fill.full   { background: var(--danger); }

.slots-dots {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}
.slot-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--border);
  transition: background 0.25s;
}
.slot-dot.used { background: var(--primary); }
.slot-dot.almost { background: var(--warning); }
.slot-dot.full { background: var(--danger); }

/* ── КНОПКА ДОБАВИТЬ ── */
.btn-add-service {
  width: 100%;
  padding: 13px 20px;
  border-radius: var(--radius-sm);
  border: none;
  background: var(--primary);
  color: white;
  font-size: 14.5px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 18px;
  transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
  text-decoration: none;
  letter-spacing: -0.1px;
  box-shadow: 0 2px 12px rgba(59,108,244,0.28);
}
.btn-add-service:active {
  transform: scale(0.97);
  background: var(--primary-dark);
  box-shadow: 0 1px 4px rgba(59,108,244,0.15);
}
.btn-add-service.disabled {
  background: var(--border);
  color: var(--text-light);
  cursor: not-allowed;
  pointer-events: none;
  box-shadow: none;
}
.btn-add-service svg {
  width: 17px; height: 17px;
  stroke: currentColor; fill: none; stroke-width: 2.5;
}

/* ── СПИСОК СЕРВИСОВ ── */
.services-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ── КАРТОЧКА СЕРВИСА ── */
.service-card {
  background: var(--bg-card);
  border-radius: var(--radius);
  border: 1px solid var(--border-light);
  overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: transform 0.15s, box-shadow 0.15s;
  animation: fadeUp 0.3s ease both;
}
.service-card:nth-child(1) { animation-delay: 0.05s; }
.service-card:nth-child(2) { animation-delay: 0.1s; }
.service-card:nth-child(3) { animation-delay: 0.15s; }

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.service-card:active {
  transform: scale(0.99);
  box-shadow: var(--shadow-sm);
}

.card-body {
  display: flex;
  gap: 12px;
  padding: 14px;
}

.service-photo {
  width: 80px; height: 96px;
  border-radius: var(--radius-xs);
  overflow: hidden;
  flex-shrink: 0;
  background: var(--bg-secondary);
  position: relative;
}
.service-photo img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}
.service-card:hover .service-photo img { transform: scale(1.04); }

.no-photo {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 4px;
  background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
}
.no-photo svg { width: 24px; height: 24px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.no-photo span { font-size: 9px; color: var(--text-light); font-weight: 600; letter-spacing: 0.3px; }

.service-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.service-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.service-category {
  font-size: 12.5px;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.service-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 11.5px;
  font-weight: 700;
  width: fit-content;
  letter-spacing: 0.1px;
}
.service-status .dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  flex-shrink: 0;
}
.service-status.draft    { background: #F1F5F9; color: #475569; }
.service-status.draft .dot { background: #94A3B8; }
.service-status.pending  { background: var(--pending-bg); color: #5B21B6; }
.service-status.pending .dot { background: var(--pending-color); animation: pulse 1.5s infinite; }
.service-status.active   { background: var(--success-bg); color: #065F46; }
.service-status.active .dot { background: var(--success); }
.service-status.rejected { background: var(--danger-bg); color: #991B1B; }
.service-status.rejected .dot { background: var(--danger); }

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}

.service-date {
  font-size: 11px;
  color: var(--text-light);
  font-weight: 500;
}

.service-views {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--text-light);
  font-weight: 600;
  padding: 5px 8px;
  background: var(--bg-secondary);
  border-radius: 99px;
  border: 1px solid var(--border);
}
.service-views svg {
  width: 13px; height: 13px;
  stroke: var(--text-light); fill: none; stroke-width: 2;
  flex-shrink: 0;
}

/* ── ФУТЕР КАРТОЧКИ ── */
.card-footer {
  padding: 10px 14px 12px;
  border-top: 1px solid var(--border-light);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* ── TOGGLE ── */
.toggle-wrap {
  display: flex; align-items: center; gap: 7px;
  padding: 5px 10px 5px 6px;
  background: var(--bg-secondary);
  border-radius: 99px;
  border: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.15s;
  user-select: none;
}
.toggle-wrap:active { background: var(--border); }

.toggle-switch {
  position: relative;
  width: 32px; height: 18px;
  background: var(--border);
  border-radius: 99px;
  transition: background 0.2s;
  flex-shrink: 0;
}
.toggle-switch.active { background: var(--success); }
.toggle-switch::after {
  content: '';
  position: absolute;
  top: 2px; left: 2px;
  width: 14px; height: 14px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 1px 3px rgba(0,0,0,0.25);
}
.toggle-switch.active::after { transform: translateX(14px); }
.toggle-label {
  font-size: 12px; font-weight: 600;
  color: var(--text-secondary);
}

/* ── КНОПКИ ДЕЙСТВИЙ ── */
.btn-action {
  padding: 6px 11px;
  border-radius: var(--radius-xs);
  font-size: 12.5px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: opacity 0.15s, transform 0.1s;
  letter-spacing: -0.1px;
  white-space: nowrap;
}
.btn-action:active { transform: scale(0.93); opacity: 0.8; }
.btn-action svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2.2; }

.btn-action.primary {
  background: var(--primary-light);
  color: var(--primary);
}
.btn-action.secondary {
  background: var(--bg-secondary);
  color: var(--text-secondary);
  border: 1px solid var(--border);
}
.btn-action.danger  { background: var(--danger-bg);  color: var(--danger); }
.btn-action.warning { background: var(--warning-bg); color: #92400E; }

.btn-icon {
  width: 31px; height: 31px;
  border-radius: var(--radius-xs);
  border: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: opacity 0.15s, transform 0.1s;
  flex-shrink: 0;
}
.btn-icon:active { transform: scale(0.9); opacity: 0.7; }
.btn-icon svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2.2; }
.btn-icon.edit   { background: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border); }
.btn-icon.delete { background: var(--danger-bg); color: var(--danger); }

.btn-spacer { flex: 1; }

/* ── КОММЕНТАРИЙ МОДЕРАТОРА ── */
.moderator-comment {
  margin: 0 14px 12px;
  padding: 10px 12px;
  background: var(--danger-bg);
  border-radius: var(--radius-xs);
  font-size: 12.5px;
  color: #991B1B;
  border-left: 3px solid var(--danger);
  font-weight: 500;
  line-height: 1.5;
}
.moderator-comment .comment-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
  opacity: 0.7;
}

/* ── ПУСТОЕ СОСТОЯНИЕ ── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 56px 24px 40px;
  text-align: center;
}
.empty-icon {
  width: 72px; height: 72px;
  background: var(--bg-secondary);
  border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 18px;
  border: 1px solid var(--border);
}
.empty-icon svg {
  width: 36px; height: 36px;
  stroke: var(--text-light); fill: none; stroke-width: 1.5;
}
.empty-state h3 { font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 8px; letter-spacing: -0.3px; }
.empty-state p  { font-size: 14px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 24px; font-weight: 500; }

/* ── МОДАЛКА ── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 600;
  display: none;
  align-items: flex-end;
  justify-content: center;
  padding: 0 0 env(safe-area-inset-bottom);
  animation: fadeOverlay 0.2s ease;
}
@keyframes fadeOverlay {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.modal-overlay.active { display: flex; }

.modal-sheet {
  background: var(--bg);
  border-radius: 20px 20px 0 0;
  padding: 0;
  width: 100%;
  max-width: 430px;
  animation: slideUp 0.3s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

.modal-handle {
  width: 36px; height: 4px;
  background: var(--border);
  border-radius: 99px;
  margin: 12px auto 0;
}

.modal-body { padding: 20px 24px 28px; }

.modal-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
  font-size: 24px;
}
.modal-icon.danger  { background: var(--danger-bg); }
.modal-icon.warning { background: var(--warning-bg); }
.modal-icon.primary { background: var(--primary-light); }

.modal-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 8px;
  letter-spacing: -0.4px;
}
.modal-text {
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.6;
  margin-bottom: 24px;
  font-weight: 500;
}

.modal-actions { display: flex; flex-direction: column; gap: 8px; }

.modal-btn {
  width: 100%;
  padding: 14px;
  border-radius: var(--radius-sm);
  font-size: 15px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  letter-spacing: -0.2px;
  transition: opacity 0.15s, transform 0.1s;
}
.modal-btn:active { transform: scale(0.98); opacity: 0.85; }
.modal-btn.danger   { background: var(--danger); color: white; }
.modal-btn.warning  { background: var(--warning); color: white; }
.modal-btn.primary  { background: var(--primary); color: white; }
.modal-btn.cancel   { background: var(--bg-secondary); color: var(--text-secondary); font-weight: 600; }

/* ── TOAST ── */
.toast {
  position: fixed;
  bottom: 24px;
  left: 50%; transform: translateX(-50%) translateY(80px);
  background: var(--text);
  color: white;
  padding: 11px 18px;
  border-radius: 99px;
  font-size: 13.5px;
  font-weight: 600;
  z-index: 700;
  white-space: nowrap;
  pointer-events: none;
  transition: transform 0.3s cubic-bezier(.4,0,.2,1), opacity 0.3s;
  opacity: 0;
  max-width: calc(100vw - 32px);
  box-shadow: var(--shadow-md);
}
.toast.show {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}
.toast.success { background: #0F3528; }
.toast.error   { background: #450A0A; }

/* ── LOADING OVERLAY ── */
.loading-overlay {
  position: fixed; inset: 0;
  z-index: 900;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.7);
  backdrop-filter: blur(2px);
}
.loading-overlay.active { display: flex; }
.spinner {
  width: 36px; height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── MISC ── */
@media (max-width: 360px) {
  .service-photo { width: 68px; height: 84px; }
  .service-name  { font-size: 14px; }
  .btn-action    { padding: 5px 9px; font-size: 12px; }
}

::-webkit-scrollbar { display: none; }
/* ── БУРГЕР ── */
.btn-burger {
  width: 38px; height: 38px;
  display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px;
  cursor: pointer; background: var(--bg-secondary); border: none;
  border-radius: var(--radius-xs); transition: background 0.15s;
}
.btn-burger span {
  display: block; width: 18px; height: 2px;
  background: var(--text-secondary); border-radius: 2px;
  transition: all 0.22s cubic-bezier(.4,0,.2,1); transform-origin: center;
}
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--text); }
.btn-burger.active span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--text); }


</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">
  <!-- ШАПКА -->
  <header class="header">
    <div class="header-left">
      <a href="profile.php" class="btn-back" aria-label="Назад">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
      <span class="header-title">Мои сервисы</span>
    </div>
    <button class="btn-burger" id="menuToggle" aria-label="Меню">
      <span></span><span></span><span></span>
    </button>
  </header>

  <?php include __DIR__ . '/includes/menu.php'; ?>

  <!-- КОНТЕНТ -->
  <main class="content">

    <?php if ($successMessage): ?>
    <div class="alert alert-success" id="alertSuccess">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <span><?php echo htmlspecialchars($successMessage); ?></span>
      <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="alert alert-error" id="alertError">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span><?php echo htmlspecialchars($errorMessage); ?></span>
      <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- СЛОТЫ -->
    <?php
      $slotClass = $serviceCount >= 3 ? 'full' : ($serviceCount >= 2 ? 'almost' : '');
      $fillPct   = round(($serviceCount / 3) * 100);
    ?>
    <div class="slots-block">
      <div class="slots-header">
        <span class="slots-label">Слоты</span>
        <span class="slots-count <?php echo $slotClass; ?>"><?php echo $serviceCount; ?> / 3</span>
      </div>
      <div class="slots-bar">
        <div class="slots-fill <?php echo $slotClass; ?>" style="width: <?php echo $fillPct; ?>%"></div>
      </div>
      <div class="slots-dots">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="slot-dot <?php echo $i < $serviceCount ? $slotClass ?: 'used' : ''; ?>"></div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- КНОПКА ДОБАВИТЬ -->
    <?php if ($serviceCount < 3): ?>
    <a href="add-service.php" class="btn-add-service">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Добавить сервис
    </a>
    <?php else: ?>
    <div class="btn-add-service disabled">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Все слоты заняты
    </div>
    <?php endif; ?>

    <!-- СПИСОК СЕРВИСОВ -->
    <div class="services-list">
      <?php if (empty($services)): ?>
      <div class="empty-state">
        <div class="empty-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
        </div>
        <h3>Пока нет сервисов</h3>
        <p>Добавьте первый сервис<br>и он появится в каталоге</p>
        <a href="add-service.php" class="btn-add-service" style="width:auto;margin-bottom:0;">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Добавить сервис
        </a>
      </div>

      <?php else: ?>
      <?php foreach ($services as $service): ?>
      <?php
        $statusConfig = [
          'draft'    => ['text' => 'Черновик',       'class' => 'draft'],
          'pending'  => ['text' => 'На модерации',   'class' => 'pending'],
          'approved' => ['text' => 'Активный',       'class' => 'active'],
          'rejected' => ['text' => 'Отклонён',       'class' => 'rejected'],
        ];
        $status = $statusConfig[$service['status']] ?? $statusConfig['draft'];

        $photoUrl = '';
        if (!empty($service['photo'])) {
          $photos = json_decode($service['photo'], true);
          if (is_array($photos) && !empty($photos[0])) {
            $photoUrl = $photos[0];
          }
        }

        $createdDate = date('d.m.Y', strtotime($service['created_at']));
        $updatedDate = date('d.m.Y', strtotime($service['updated_at']));
        $dateText = $createdDate !== $updatedDate ? "Обновлено $updatedDate" : "Создано $createdDate";
      ?>
      <div class="service-card" data-service-id="<?php echo $service['id']; ?>">
        <div class="card-body">
          <div class="service-photo">
            <?php if ($photoUrl): ?>
              <img src="<?php echo htmlspecialchars($photoUrl); ?>"
                   alt="<?php echo htmlspecialchars($service['name']); ?>"
                   loading="lazy">
            <?php else: ?>
              <div class="no-photo">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span>ФОТО</span>
              </div>
            <?php endif; ?>
          </div>

          <div class="service-info">
            <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
            <div class="service-category">
              <?php echo htmlspecialchars($service['category']); ?>
              <?php if (!empty($service['subcategory'])): ?>
                &nbsp;·&nbsp;<?php echo htmlspecialchars($service['subcategory']); ?>
              <?php endif; ?>
            </div>
            <div class="service-status <?php echo $status['class']; ?>">
              <span class="dot"></span>
              <?php echo $status['text']; ?>
            </div>
            <div class="service-date"><?php echo $dateText; ?></div>
          </div>
        </div>

        <!-- КОММЕНТАРИЙ МОДЕРАТОРА -->
        <?php if ($service['status'] === 'rejected' && !empty($service['moderation_comment'])): ?>
        <div class="moderator-comment">
          <div class="comment-label">Причина отклонения</div>
          <?php echo htmlspecialchars($service['moderation_comment']); ?>
        </div>
        <?php endif; ?>

        <!-- КНОПКИ ДЕЙСТВИЙ -->
        <div class="card-footer">
          <?php if ($service['status'] === 'approved'): ?>
            <div class="toggle-wrap" onclick="toggleVisibility(<?php echo $service['id']; ?>, <?php echo $service['is_visible'] ? 1 : 0; ?>)">
              <div class="toggle-switch <?php echo $service['is_visible'] ? 'active' : ''; ?>"></div>
              <span class="toggle-label"><?php echo $service['is_visible'] ? 'Видим' : 'Скрыт'; ?></span>
            </div>
            <?php if ($service['views'] > 0): ?>
            <div class="service-views">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <?php echo number_format($service['views'], 0, '.', ' '); ?>
            </div>
            <?php endif; ?>
            <span class="btn-spacer"></span>
            <button class="btn-icon edit"   onclick="editService(<?php echo $service['id']; ?>)" title="Редактировать">
              <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </button>
            <button class="btn-icon delete" onclick="confirmDelete(<?php echo $service['id']; ?>)" title="Удалить">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>

          <?php elseif ($service['status'] === 'draft' || $service['status'] === 'rejected'): ?>
            <button class="btn-action primary" onclick="confirmSubmit(<?php echo $service['id']; ?>)">
              <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
              На модерацию
            </button>
            <span class="btn-spacer"></span>
            <button class="btn-icon edit"   onclick="editService(<?php echo $service['id']; ?>)" title="Редактировать">
              <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </button>
            <button class="btn-icon delete" onclick="confirmDelete(<?php echo $service['id']; ?>)" title="Удалить">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>

          <?php elseif ($service['status'] === 'pending'): ?>
            <button class="btn-action warning" onclick="confirmRecall(<?php echo $service['id']; ?>)">
              <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/></svg>
              Отозвать
            </button>
            <span class="btn-spacer"></span>
            <button class="btn-icon delete" onclick="confirmDelete(<?php echo $service['id']; ?>)" title="Удалить">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- МОДАЛКА (нижний шит) -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal-sheet" id="modalSheet">
    <div class="modal-handle"></div>
    <div class="modal-body">
      <div class="modal-icon" id="modalIcon">⚠️</div>
      <div class="modal-title" id="modalTitle">Вы уверены?</div>
      <div class="modal-text"  id="modalText">Это действие нельзя отменить.</div>
      <div class="modal-actions">
        <button class="modal-btn" id="modalConfirmBtn">Подтвердить</button>
        <button class="modal-btn cancel" onclick="closeModal()">Отмена</button>
      </div>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- LOADING -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<script>
// ── TOAST ──────────────────────────────────────────
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.className = 'toast'; }, 2800);
}

// ── LOADING ────────────────────────────────────────
const loader = {
  show: () => document.getElementById('loadingOverlay').classList.add('active'),
  hide: () => document.getElementById('loadingOverlay').classList.remove('active'),
};

// ── MODAL ──────────────────────────────────────────
let _modalCallback = null;

function showModal({ icon = '⚠️', iconType = 'warning', title, text, confirmText, confirmType = 'warning', callback }) {
  document.getElementById('modalIcon').textContent  = icon;
  document.getElementById('modalIcon').className    = 'modal-icon ' + iconType;
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalText').textContent  = text;
  const btn = document.getElementById('modalConfirmBtn');
  btn.textContent = confirmText;
  btn.className   = 'modal-btn ' + confirmType;
  _modalCallback  = callback;
  document.getElementById('modalOverlay').classList.add('active');
}

function closeModal(e) {
  if (e && e.target !== document.getElementById('modalOverlay')) return;
  document.getElementById('modalOverlay').classList.remove('active');
  _modalCallback = null;
}

document.getElementById('modalConfirmBtn').addEventListener('click', () => {
  document.getElementById('modalOverlay').classList.remove('active');
  if (_modalCallback) _modalCallback();
  _modalCallback = null;
});

// ── API HELPER ─────────────────────────────────────
async function apiCall(action, serviceId) {
  loader.show();
  try {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('service_id', serviceId);
    const res  = await fetch('api/service-actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    loader.hide();
    return data;
  } catch {
    loader.hide();
    return { success: false, error: 'Ошибка соединения' };
  }
}

// ── TOGGLE ВИДИМОСТЬ ─────────────────────────────
async function toggleVisibility(id, current) {
  showModal({
    icon: current ? '👁️' : '👁️',
    iconType: 'primary',
    title: current ? 'Скрыть из каталога?' : 'Показать в каталоге?',
    text: current
      ? 'Сервис пропадёт из поиска. Вы сможете снова включить его в любой момент.'
      : 'Сервис снова появится в каталоге и будет доступен клиентам.',
    confirmText: current ? 'Скрыть' : 'Показать',
    confirmType: 'primary',
    callback: async () => {
      const d = await apiCall('toggle_visibility', id);
      if (d.success) {
        showToast(current ? 'Сервис скрыт' : 'Сервис показан', 'success');
        setTimeout(() => location.reload(), 600);
      } else {
        showToast('Ошибка: ' + (d.error || 'неизвестная ошибка'), 'error');
      }
    }
  });
}

// ── ОТПРАВИТЬ НА МОДЕРАЦИЮ ────────────────────────
function confirmSubmit(id) {
  showModal({
    icon: '📤',
    iconType: 'primary',
    title: 'Отправить на модерацию?',
    text: 'После отправки редактирование будет недоступно до завершения проверки.',
    confirmText: 'Отправить',
    confirmType: 'primary',
    callback: async () => {
      const d = await apiCall('submit_for_moderation', id);
      if (d.success) {
        showToast('Отправлено на модерацию', 'success');
        setTimeout(() => location.reload(), 600);
      } else {
        showToast('Ошибка: ' + (d.error || 'неизвестная ошибка'), 'error');
      }
    }
  });
}

// ── ОТОЗВАТЬ С МОДЕРАЦИИ ──────────────────────────
function confirmRecall(id) {
  showModal({
    icon: '↩️',
    iconType: 'warning',
    title: 'Отозвать сервис?',
    text: 'Статус изменится на «Черновик». Вы сможете отредактировать и отправить снова.',
    confirmText: 'Отозвать',
    confirmType: 'warning',
    callback: async () => {
      const d = await apiCall('recall_from_moderation', id);
      if (d.success) {
        showToast('Сервис отозван', 'success');
        setTimeout(() => location.reload(), 600);
      } else {
        showToast('Ошибка: ' + (d.error || 'неизвестная ошибка'), 'error');
      }
    }
  });
}

// ── УДАЛИТЬ ───────────────────────────────────────
function confirmDelete(id) {
  showModal({
    icon: '🗑️',
    iconType: 'danger',
    title: 'Удалить сервис?',
    text: 'Сервис будет удалён без возможности восстановления. Это действие необратимо.',
    confirmText: 'Удалить навсегда',
    confirmType: 'danger',
    callback: async () => {
      const d = await apiCall('delete_service', id);
      if (d.success) {
        showToast('Сервис удалён', 'error');
        setTimeout(() => location.reload(), 600);
      } else {
        showToast('Ошибка: ' + (d.error || 'неизвестная ошибка'), 'error');
      }
    }
  });
}

// ── РЕДАКТИРОВАТЬ ─────────────────────────────────
function editService(id) {
  window.location.href = 'edit-service.php?id=' + id;
}

</script>
</body>
</html>