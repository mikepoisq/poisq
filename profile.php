<?php
// profile.php — Личный кабинет Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userName    = $_SESSION['user_name']   ?? 'Пользователь';
$userEmail   = $_SESSION['user_email']  ?? '';
$userAvatar  = $_SESSION['user_avatar'] ?? '';
$userInitial = strtoupper(substr($userName, 0, 1));
$successMessage = $_GET['success'] ?? '';

require_once 'config/database.php';
try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $serviceCount = $stmt->fetch()['count'];

    // Количество одобренных сервисов (для проверки лимита слотов)
    $stmtApproved = $pdo->prepare("SELECT COUNT(*) as count FROM services WHERE user_id = ? AND status = 'approved'");
    $stmtApproved->execute([$_SESSION['user_id']]);
    $approvedCount = $stmtApproved->fetch()['count'];
    $maxSlots = 3;
    $slotsUsed = $approvedCount;
    $slotsLeft = max(0, $maxSlots - $slotsUsed);

    // Настройки уведомлений пользователя
    $stmtNotif = $pdo->prepare("SELECT notify_new_services, notify_views_freq, city_id FROM users WHERE id = ?");
    $stmtNotif->execute([$_SESSION['user_id']]);
    $notifRow = $stmtNotif->fetch();
    $notifyNewServices = $notifRow['notify_new_services'] ?? 0;
    $notifyViewsFreq   = $notifRow['notify_views_freq']   ?? 'off';
    $userCityId        = $notifRow['city_id']             ?? null;

    // Количество избранных сервисов
    $favCount = 0;
    try {
        $stmtFav = $pdo->prepare("SELECT COUNT(*) as count FROM favorites WHERE user_id = ?");
        $stmtFav->execute([$_SESSION['user_id']]);
        $favCount = $stmtFav->fetch()['count'];
    } catch (Exception $e) { $favCount = 0; }

    // Номер WhatsApp поддержки из настроек
    $supportWhatsapp = '';
    try {
        $stmtWa = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'support_whatsapp'");
        $stmtWa->execute();
        $supportWhatsapp = $stmtWa->fetchColumn() ?: '';
    } catch (Exception $e) { $supportWhatsapp = ''; }

} catch (PDOException $e) {
    $serviceCount      = 0;
    $approvedCount     = 0;
    $slotsUsed         = 0;
    $slotsLeft         = 3;
    $maxSlots          = 3;
    $notifyNewServices = 0;
    $notifyViewsFreq   = 'off';
    $supportWhatsapp   = '';
}

// Данные для блока значка Проверено
$approvedServices = [];
$verifRequests    = [];
$verifFormError   = $_GET['verif_error'] ?? '';
$verifSuccess     = !empty($_GET['verif_success']);
try {
    $pdo = getDbConnection();
    $stmtAS = $pdo->prepare("
        SELECT id, name, verified, verified_until
        FROM services
        WHERE user_id = ? AND status = 'approved'
        ORDER BY created_at DESC
    ");
    $stmtAS->execute([$_SESSION['user_id']]);
    $approvedServices = $stmtAS->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($approvedServices)) {
        $svcIds = array_column($approvedServices, 'id');
        $ph     = implode(',', array_fill(0, count($svcIds), '?'));
        $stmtVR = $pdo->prepare("
            SELECT vr1.*
            FROM verification_requests vr1
            INNER JOIN (
                SELECT service_id, MAX(created_at) AS max_ca
                FROM verification_requests
                GROUP BY service_id
            ) vr2 ON vr1.service_id = vr2.service_id AND vr1.created_at = vr2.max_ca
            WHERE vr1.service_id IN ($ph)
        ");
        $stmtVR->execute($svcIds);
        foreach ($stmtVR->fetchAll(PDO::FETCH_ASSOC) as $vr) {
            $verifRequests[$vr['service_id']] = $vr;
        }
    }
} catch (Exception $e) {
    $approvedServices = [];
    $verifRequests    = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Профиль — Poisq</title>
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
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark: #2952D9;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --success: #10B981;
  --success-bg: #ECFDF5;
  --warning: #F59E0B;
  --warning-bg: #FFFBEB;
  --danger: #EF4444;
  --danger-bg: #FEF2F2;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-card: 0 2px 12px rgba(0,0,0,0.06);
  --radius: 16px;
  --radius-sm: 10px;
  --radius-xs: 8px;
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
  position: relative;
  display: flex; flex-direction: column;
}

/* ── ШАПКА ── */
.header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0; height: 58px;
  position: sticky; top: 0; z-index: 100;
}

.header-side { display: flex; align-items: center; width: 44px; }
.header-side.right { justify-content: flex-end; }
.header-title { font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }

.btn-icon-header {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none;
  background: var(--bg-secondary);
  color: var(--text);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}
.btn-icon-header:active { transform: scale(0.92); background: var(--border); }
.btn-icon-header svg { width: 19px; height: 19px; fill: currentColor; }

/* Burger */
.btn-burger {
  width: 38px; height: 38px;
  display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px;
  cursor: pointer; background: var(--bg-secondary); border: none; border-radius: var(--radius-xs);
  transition: background 0.15s;
}
.btn-burger span {
  display: block; width: 18px; height: 2px;
  background: var(--text-secondary); border-radius: 2px;
  transition: all 0.22s cubic-bezier(.4,0,.2,1);
  transform-origin: center;
}
.btn-burger:active { background: var(--border); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--text); }
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
  background: var(--primary);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 800; font-size: 18px;
  flex-shrink: 0; overflow: hidden;
  box-shadow: 0 2px 10px rgba(59,108,244,0.3);
}
.side-avatar img { width: 100%; height: 100%; object-fit: cover; }
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
.side-item.logout { color: var(--danger); margin-top: 4px; }
.side-item.logout svg { stroke: var(--danger); }
.side-divider { height: 1px; background: var(--border-light); margin: 6px 20px; }

/* ── КОНТЕНТ ── */
.profile-container {
  flex: 1;
  background: var(--bg-secondary);
  padding-bottom: 20px;
}

/* ── ALERT ── */
.alert {
  margin: 14px 16px 0;
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 13.5px; font-weight: 500;
  display: flex; align-items: flex-start; gap: 10px;
  animation: slideDown 0.3s ease;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
.alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.alert-close {
  margin-left: auto; background: none; border: none;
  cursor: pointer; color: inherit; opacity: 0.5; padding: 0; font-size: 15px;
}

/* ── ПРОФИЛЬ ШАПКА ── */
.profile-hero {
  background: var(--bg);
  padding: 28px 20px 24px;
  display: flex; flex-direction: column; align-items: center; gap: 12px;
  margin-bottom: 12px;
  border-bottom: 1px solid var(--border-light);
}

.avatar-wrap {
  position: relative; width: 88px; height: 88px;
}
.avatar-main {
  width: 88px; height: 88px;
  border-radius: 50%;
  background: var(--primary);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 800; font-size: 34px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(59,108,244,0.25);
  border: 3px solid var(--bg);
  outline: 2px solid var(--primary-light);
}
.avatar-main img { width: 100%; height: 100%; object-fit: cover; }

.avatar-edit-btn {
  position: absolute; bottom: 0; right: 0;
  width: 28px; height: 28px;
  border-radius: 50%;
  background: var(--primary);
  border: 2.5px solid var(--bg);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(59,108,244,0.35);
  transition: transform 0.15s, background 0.15s;
}
.avatar-edit-btn:active { transform: scale(0.9); background: var(--primary-dark); }
.avatar-edit-btn svg { width: 13px; height: 13px; stroke: white; fill: none; stroke-width: 2.5; }

.profile-name  { font-size: 21px; font-weight: 800; color: var(--text); letter-spacing: -0.5px; }
.profile-email { font-size: 14px; color: var(--text-secondary); font-weight: 500; }

/* ── СЕКЦИИ ── */
.section {
  background: var(--bg);
  border-radius: var(--radius);
  margin: 0 14px 12px;
  overflow: hidden;
  box-shadow: var(--shadow-card);
  border: 1px solid var(--border-light);
}

.section-title {
  font-size: 11.5px; font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px;
  padding: 14px 16px 6px;
}

.s-item {
  display: flex; align-items: center; gap: 13px;
  padding: 13px 16px;
  cursor: pointer; transition: background 0.15s;
  text-decoration: none; color: var(--text);
  position: relative;
}
.s-item:active { background: var(--bg-secondary); }
.s-item + .s-item::before {
  content: '';
  position: absolute; top: 0; left: 58px; right: 0;
  height: 1px; background: var(--border-light);
}

.s-icon {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.s-icon svg { width: 19px; height: 19px; stroke: currentColor; fill: none; stroke-width: 2; }
.s-icon.blue   { background: #EEF2FF; color: var(--primary); }
.s-icon.green  { background: var(--success-bg); color: var(--success); }
.s-icon.orange { background: var(--warning-bg); color: #92400E; }
.s-icon.purple { background: #F5F3FF; color: #7C3AED; }
.s-icon.slate  { background: var(--bg-secondary); color: var(--text-secondary); }

.s-content { flex: 1; min-width: 0; }
.s-label {
  font-size: 14.5px; font-weight: 600; color: var(--text);
  letter-spacing: -0.1px;
}
.s-desc {
  font-size: 12.5px; color: var(--text-secondary); font-weight: 500;
  margin-top: 1px;
}

.s-right { display: flex; align-items: center; gap: 6px; }
.s-badge {
  background: var(--primary);
  color: white;
  font-size: 11.5px; font-weight: 700;
  padding: 2px 7px;
  border-radius: 99px;
  min-width: 22px; text-align: center;
}
.s-badge.empty { background: var(--bg-secondary); color: var(--text-light); }
.s-arrow {
  width: 16px; height: 16px;
  stroke: var(--text-light); fill: none; stroke-width: 2.5;
  flex-shrink: 0;
}

/* ── КНОПКА ВЫХОДА ── */
.logout-btn {
  display: flex; align-items: center; justify-content: center; gap: 9px;
  width: calc(100% - 28px);
  margin: 4px 14px 14px;
  padding: 14px;
  border-radius: var(--radius-sm);
  border: none;
  background: var(--danger-bg);
  color: var(--danger);
  font-size: 14.5px; font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s;
  text-decoration: none;
  letter-spacing: -0.1px;
  font-family: inherit;
}
.logout-btn:active { transform: scale(0.98); opacity: 0.8; }
.logout-btn svg { width: 18px; height: 18px; stroke: var(--danger); fill: none; stroke-width: 2.2; }

/* ── ФУТЕР ── */
.profile-footer {
  padding: 14px 20px 20px;
  background: var(--bg-secondary);
  border-top: 1px solid var(--border-light);
}
.footer-links {
  display: flex; flex-wrap: wrap;
  justify-content: center; gap: 8px 16px;
}
.footer-link {
  font-size: 12px; font-weight: 500;
  color: var(--text-secondary); text-decoration: none;
  transition: color 0.15s;
}
.footer-link:active { color: var(--primary); }

/* ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ── */
.ann-modal {
  position: fixed; inset: 0;
  background: var(--bg-secondary);
  z-index: 500;
  display: none; flex-direction: column;
  max-width: 430px; margin: 0 auto;
  animation: slideFromBottom 0.3s cubic-bezier(.4,0,.2,1);
}
.ann-modal.active { display: flex; }

@keyframes slideFromBottom {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0); opacity: 1; }
}

.ann-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px;
  height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.ann-header-icon { font-size: 20px; }
.ann-title {
  font-size: 16px; font-weight: 700; color: var(--text);
  letter-spacing: -0.3px; flex: 1;
}
.ann-close {
  width: 34px; height: 34px;
  border-radius: 50%;
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s;
}
.ann-close:active { background: var(--border); }
.ann-close svg { width: 17px; height: 17px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }

.ann-city {
  padding: 10px 14px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.city-select {
  width: 100%;
  padding: 9px 38px 9px 13px;
  border: 1px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 600;
  color: var(--text);
  background: var(--bg-secondary);
  outline: none; cursor: pointer;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 11px center;
  font-family: 'Manrope', sans-serif;
  transition: border-color 0.15s;
}
.city-select:focus { border-color: var(--primary); }

.ann-content {
  flex: 1; overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 16px 14px;
}

/* Loading */
.ann-loading {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 60px 20px; gap: 14px;
}
.spinner {
  width: 34px; height: 34px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }

/* Grid сервисов */
.ann-category { margin-bottom: 22px; }
.ann-cat-title {
  font-size: 15px; font-weight: 800; color: var(--text);
  letter-spacing: -0.3px; margin-bottom: 10px; padding-left: 2px;
}
.ann-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
.ann-item {
  background: var(--bg);
  border-radius: var(--radius-xs);
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.15s;
  border: 1px solid var(--border-light);
  position: relative;
  box-shadow: var(--shadow-sm);
}
.ann-item:active { transform: scale(0.94); }
.ann-item img {
  width: 100%; aspect-ratio: 1;
  object-fit: cover; display: block;
  background: var(--bg-secondary);
}
.ann-date-badge {
  position: absolute; top: 6px; right: 6px;
  background: rgba(59,108,244,0.9);
  color: white;
  padding: 3px 7px;
  border-radius: 6px;
  font-size: 10px; font-weight: 700;
  backdrop-filter: blur(4px);
}
.ann-item-name {
  font-size: 11.5px; font-weight: 600; color: var(--text);
  padding: 7px 8px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  text-align: center; line-height: 1.3;
}

/* Добавить карточка */
.ann-add-card {
  background: var(--bg-secondary);
  border: 2px dashed var(--border);
  border-radius: var(--radius-xs);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  aspect-ratio: 1;
  cursor: pointer;
  transition: all 0.15s;
  gap: 5px; padding: 8px;
}
.ann-add-card:active { border-color: var(--primary); background: var(--primary-light); transform: scale(0.95); }
.ann-add-card svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.ann-add-card span { font-size: 9.5px; color: var(--text-secondary); text-align: center; line-height: 1.3; font-weight: 600; }

/* Empty */
.ann-empty {
  display: flex; flex-direction: column; align-items: center;
  padding: 50px 20px; text-align: center; gap: 10px;
}
.ann-empty-icon {
  width: 64px; height: 64px;
  background: var(--bg);
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid var(--border);
  margin-bottom: 6px;
}
.ann-empty-icon svg { width: 30px; height: 30px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.ann-empty h3 { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }
.ann-empty p  { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; margin-bottom: 6px; }
.ann-add-btn {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--primary); color: white;
  padding: 11px 22px; border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 700;
  text-decoration: none; cursor: pointer;
  border: none; font-family: inherit;
  box-shadow: 0 2px 10px rgba(59,108,244,0.28);
  transition: opacity 0.15s, transform 0.1s;
}
.ann-add-btn:active { opacity: 0.85; transform: scale(0.97); }
.ann-add-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2.5; }
.ann-add-free { font-size: 11.5px; color: var(--text-light); font-weight: 500; }

/* ── МОДАЛКА АВАТАРА ── */
.avatar-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.45);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
  z-index: 600;
  display: none; align-items: flex-end; justify-content: center;
  padding-bottom: env(safe-area-inset-bottom);
}
.avatar-modal-overlay.active { display: flex; }

.avatar-modal {
  background: var(--bg);
  border-radius: 24px 24px 0 0;
  width: 100%; max-width: 430px;
  overflow: hidden;
  animation: slideUp 0.32s cubic-bezier(.4,0,.2,1);
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

/* Шапка модалки */
.avm-header {
  display: flex; align-items: center; gap: 14px;
  padding: 18px 20px 16px;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-secondary);
}
.avm-header-avatar {
  width: 48px; height: 48px;
  border-radius: 50%;
  background: var(--primary);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 800; font-size: 18px;
  overflow: hidden; flex-shrink: 0;
  box-shadow: 0 2px 10px rgba(59,108,244,0.25);
  border: 2px solid var(--bg);
  outline: 2px solid var(--primary-light);
}
.avm-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avm-header-text { flex: 1; min-width: 0; }
.avm-header-title { font-size: 15px; font-weight: 700; color: var(--text); letter-spacing: -0.2px; }
.avm-header-sub   { font-size: 12px; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
.avm-close {
  width: 32px; height: 32px;
  border-radius: 50%; border: none;
  background: var(--border-light);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s;
}
.avm-close:active { background: var(--border); }
.avm-close svg { width: 15px; height: 15px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }

/* Пункты */
.avm-items { padding: 8px 0 12px; }

.avm-item {
  display: flex; align-items: center; gap: 16px;
  padding: 15px 20px;
  cursor: pointer;
  transition: background 0.15s;
  border: none; background: none; width: 100%;
  text-align: left; font-family: inherit;
}
.avm-item:active { background: var(--bg-secondary); }

.avm-item-icon {
  width: 42px; height: 42px;
  border-radius: var(--radius-xs);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.avm-item-icon svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }
.avm-item-icon.blue   { background: var(--primary-light); color: var(--primary); }
.avm-item-icon.green  { background: var(--success-bg); color: var(--success); }
.avm-item-icon.red    { background: var(--danger-bg); color: var(--danger); }

.avm-item-label {
  font-size: 15px; font-weight: 600; color: var(--text);
  letter-spacing: -0.1px;
}
.avm-item.delete .avm-item-label { color: var(--danger); }

.avm-divider { height: 1px; background: var(--border-light); margin: 4px 20px; }

/* Скрытый input */
#avatarFileInput,
#avatarFileCamera,
#avatarFileGallery {
  display: none !important;
  position: absolute;
  width: 0; height: 0;
  opacity: 0; pointer-events: none;
}

/* Диалог удаления */
.avm-delete-dialog {
  display: none;
  padding: 20px 20px 16px;
  border-top: 1px solid var(--border-light);
  background: var(--danger-bg);
  animation: fadeIn 0.2s ease;
}
.avm-delete-dialog.active { display: block; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.avm-delete-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 6px; letter-spacing: -0.2px; }
.avm-delete-text  { font-size: 13px; color: var(--text-secondary); font-weight: 500; margin-bottom: 14px; line-height: 1.5; }
.avm-delete-btns  { display: flex; gap: 8px; }
.avm-delete-btns button {
  flex: 1; padding: 12px; border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 700; border: none; cursor: pointer;
  font-family: inherit; transition: opacity 0.15s, transform 0.1s;
}
.avm-delete-btns button:active { transform: scale(0.97); opacity: 0.85; }
.avm-btn-cancel  { background: var(--bg); color: var(--text-secondary); }
.avm-btn-confirm { background: var(--danger); color: white; }

/* Прогресс загрузки */
.avm-upload-progress {
  display: none;
  padding: 14px 20px 18px;
  border-top: 1px solid var(--border-light);
}
.avm-upload-progress.active { display: block; }
.avm-progress-label {
  font-size: 13px; font-weight: 600; color: var(--text-secondary);
  margin-bottom: 8px; display: flex; justify-content: space-between;
}
.avm-progress-bar {
  height: 4px; background: var(--border); border-radius: 99px; overflow: hidden;
}
.avm-progress-fill {
  height: 100%; background: var(--primary); border-radius: 99px;
  transition: width 0.3s ease; width: 0%;
}

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 24px;
  left: 50%; transform: translateX(-50%) translateY(80px);
  background: var(--text); color: white;
  padding: 10px 18px; border-radius: 99px;
  font-size: 13.5px; font-weight: 600;
  z-index: 700; white-space: nowrap;
  pointer-events: none;
  transition: transform 0.3s cubic-bezier(.4,0,.2,1), opacity 0.3s;
  opacity: 0; max-width: calc(100vw - 32px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.18);
}
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

@media (max-width: 360px) {
  .profile-name { font-size: 19px; }
  .avatar-main  { width: 78px; height: 78px; font-size: 30px; }
  .avatar-wrap  { width: 78px; height: 78px; }
}
::-webkit-scrollbar { display: none; }

/* ══════════════════════════════════════════════
   УНИВЕРСАЛЬНАЯ МОДАЛКА (sheet снизу)
══════════════════════════════════════════════ */
.sheet-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.45);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
  z-index: 600;
  display: none; align-items: flex-end; justify-content: center;
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet-overlay.active { display: flex; }

.sheet {
  background: var(--bg);
  border-radius: 24px 24px 0 0;
  width: 100%; max-width: 430px;
  max-height: 92vh;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  animation: slideUp 0.32s cubic-bezier(.4,0,.2,1);
}

.sheet-header {
  display: flex; align-items: center;
  padding: 18px 20px 16px;
  border-bottom: 1px solid var(--border-light);
  position: sticky; top: 0;
  background: var(--bg); z-index: 1;
}
.sheet-title {
  flex: 1;
  font-size: 16px; font-weight: 800; color: var(--text);
  letter-spacing: -0.3px;
}
.sheet-close {
  width: 32px; height: 32px;
  border-radius: 50%; border: none;
  background: var(--border-light);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s;
}
.sheet-close:active { background: var(--border); }
.sheet-close svg { width: 15px; height: 15px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }

.sheet-body { padding: 20px; }

/* Поля формы в модалке */
.field-group { margin-bottom: 16px; }
.field-label {
  font-size: 12px; font-weight: 700; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.5px;
  margin-bottom: 7px; display: block;
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
.field-input.error { border-color: var(--danger); }
.field-hint {
  font-size: 12px; color: var(--text-secondary); font-weight: 500;
  margin-top: 5px; line-height: 1.4;
}
.field-error {
  font-size: 12px; color: var(--danger); font-weight: 600;
  margin-top: 5px;
  display: none;
}
.field-error.show { display: block; }

/* Кнопка действия */
.sheet-btn {
  width: 100%; padding: 14px;
  border-radius: var(--radius-sm);
  border: none; cursor: pointer;
  font-size: 15px; font-weight: 700;
  font-family: 'Manrope', sans-serif;
  transition: opacity 0.15s, transform 0.1s;
  letter-spacing: -0.1px;
}
.sheet-btn:active { transform: scale(0.98); opacity: 0.85; }
.sheet-btn.primary { background: var(--primary); color: white; }
.sheet-btn.danger  { background: var(--danger-bg); color: var(--danger); margin-top: 10px; }

/* Секции внутри модалки аккаунта */
.acct-tabs {
  display: flex; gap: 4px;
  background: var(--bg-secondary);
  border-radius: var(--radius-xs);
  padding: 4px; margin-bottom: 20px;
}
.acct-tab {
  flex: 1; padding: 9px 6px;
  border: none; background: none; border-radius: 7px;
  font-size: 13px; font-weight: 700; color: var(--text-secondary);
  cursor: pointer; font-family: 'Manrope', sans-serif;
  transition: all 0.18s; white-space: nowrap;
}
.acct-tab.active {
  background: var(--bg); color: var(--primary);
  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}
.acct-panel { display: none; }
.acct-panel.active { display: block; }

/* Код верификации */
.code-inputs {
  display: flex; gap: 8px; justify-content: center;
  margin: 16px 0;
}
.code-input {
  width: 46px; height: 54px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 22px; font-weight: 800; color: var(--text);
  text-align: center; background: var(--bg);
  outline: none; font-family: 'Manrope', sans-serif;
  transition: border-color 0.15s;
  -webkit-appearance: none;
}
.code-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,108,244,0.1); }

/* ══════════════════════════════════════════════
   УВЕДОМЛЕНИЯ — переключатели
══════════════════════════════════════════════ */
.notif-item {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border-light);
}
.notif-item:last-child { border-bottom: none; }
.notif-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-xs);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.notif-icon svg { width: 19px; height: 19px; stroke: currentColor; fill: none; stroke-width: 2; }
.notif-icon.orange { background: var(--warning-bg); color: #92400E; }
.notif-icon.blue   { background: var(--primary-light); color: var(--primary); }
.notif-text { flex: 1; }
.notif-label { font-size: 14.5px; font-weight: 700; color: var(--text); letter-spacing: -0.1px; }
.notif-desc  { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; margin-top: 2px; line-height: 1.4; }

/* Селект частоты */
.notif-freq {
  margin-top: 10px;
  display: none;
}
.notif-freq.show { display: block; }
.notif-freq select {
  padding: 9px 32px 9px 12px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 13.5px; font-weight: 600; color: var(--text);
  background: var(--bg-secondary); outline: none;
  font-family: 'Manrope', sans-serif;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 8px center;
  transition: border-color 0.15s;
  cursor: pointer;
}
.notif-freq select:focus { border-color: var(--primary); }

/* Toggle switch */
.toggle {
  position: relative; width: 50px; height: 28px;
  flex-shrink: 0; margin-top: 2px;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-track {
  position: absolute; inset: 0;
  background: var(--border);
  border-radius: 99px;
  cursor: pointer;
  transition: background 0.25s;
}
.toggle input:checked ~ .toggle-track { background: var(--success); }
.toggle-thumb {
  position: absolute; top: 3px; left: 3px;
  width: 22px; height: 22px;
  background: white;
  border-radius: 50%;
  pointer-events: none;
  transition: transform 0.25s cubic-bezier(.4,0,.2,1);
  box-shadow: 0 1px 4px rgba(0,0,0,0.18);
}
.toggle input:checked ~ .toggle-track .toggle-thumb { transform: translateX(22px); }

/* ══════════════════════════════════════════════
   СВЯЗАТЬСЯ / FAQ — аккордеон
══════════════════════════════════════════════ */
.accord-item {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  margin-bottom: 8px;
  overflow: hidden;
  background: var(--bg);
}
.accord-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px;
  cursor: pointer;
  font-size: 14.5px; font-weight: 700; color: var(--text);
  letter-spacing: -0.1px;
  user-select: none;
  transition: background 0.15s;
  gap: 10px;
}
.accord-head:active { background: var(--bg-secondary); }
.accord-arrow {
  width: 20px; height: 20px;
  stroke: var(--text-light); fill: none; stroke-width: 2.5;
  flex-shrink: 0;
  transition: transform 0.25s;
}
.accord-item.open .accord-arrow { transform: rotate(180deg); }
.accord-body {
  display: none;
  padding: 0 16px 16px;
  font-size: 13.5px; color: var(--text-secondary); font-weight: 500;
  line-height: 1.6;
  border-top: 1px solid var(--border-light);
}
.accord-item.open .accord-body { display: block; padding-top: 14px; }

/* Форма связи */
.contact-form { display: flex; flex-direction: column; gap: 12px; }
.contact-textarea {
  width: 100%; padding: 13px 14px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 500; color: var(--text);
  background: var(--bg); outline: none;
  font-family: 'Manrope', sans-serif;
  resize: none; min-height: 90px;
  transition: border-color 0.15s;
}
.contact-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,108,244,0.1); }

/* Горячая линия */
.hotline-wrap { margin-top: 12px; }
.hotline-btn {
  width: 100%; padding: 13px 16px;
  border-radius: var(--radius-sm);
  border: 1.5px solid var(--border);
  background: var(--bg);
  display: flex; align-items: center; gap: 12px;
  cursor: pointer; font-family: 'Manrope', sans-serif;
  font-size: 14.5px; font-weight: 700; color: var(--text);
  transition: all 0.15s;
  letter-spacing: -0.1px;
}
.hotline-btn:active { background: var(--bg-secondary); transform: scale(0.98); }
.hotline-icon {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  background: #FFF0F0;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.hotline-icon svg { width: 19px; height: 19px; }
.hotline-arrow { margin-left: auto; transition: transform 0.25s; }
.hotline-arrow svg { width: 16px; height: 16px; stroke: var(--text-light); fill: none; stroke-width: 2.5; }
.hotline-wrap.open .hotline-arrow { transform: rotate(180deg); }

.wa-reveal {
  display: none;
  margin-top: 10px;
  animation: fadeIn 0.2s ease;
}
.hotline-wrap.open .wa-reveal { display: block; }

.wa-btn {
  width: 100%; padding: 14px;
  border-radius: var(--radius-sm);
  border: none;
  background: #25D366;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  color: white; font-size: 15px; font-weight: 700;
  text-decoration: none; font-family: 'Manrope', sans-serif;
  transition: opacity 0.15s, transform 0.1s;
  letter-spacing: -0.1px;
}
.wa-btn:active { opacity: 0.85; transform: scale(0.98); }
.wa-btn svg { width: 22px; height: 22px; fill: white; }


/* ══ City picker в уведомлениях ══ */
.city-picker {
  background: var(--bg-secondary);
  border-radius: var(--radius-xs);
  padding: 12px;
  margin: -8px 0 12px 0;
  border: 1px solid var(--border-light);
}
.notif-select {
  flex: 1; width: 100%;
  padding: 9px 32px 9px 12px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 13px; font-weight: 600; color: var(--text);
  background: var(--bg); outline: none;
  font-family: "Manrope", sans-serif;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 8px center;
  transition: border-color 0.15s; cursor: pointer;
}
.notif-select:focus { border-color: var(--primary); }
</style>
</head>
<body>
<div class="app-container">

  <!-- ШАПКА -->
  <header class="header">
    <div class="header-side">
      <button class="btn-icon-header" onclick="openAnnModal()" aria-label="Свежие сервисы">
        <svg viewBox="0 0 24 24">
          <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
          <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
          <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
        </svg>
      </button>
    </div>
    <span class="header-title">Профиль</span>
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
        <div class="side-avatar">
          <?php if ($userAvatar): ?>
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
          <?php else: ?>
            <?php echo $userInitial; ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="side-user-name"><?php echo htmlspecialchars($userName); ?></div>
          <div class="side-user-email"><?php echo htmlspecialchars($userEmail); ?></div>
        </div>
      </div>
    </div>
    <div class="side-items">
      <a href="index.php" class="side-item">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Главная
      </a>
      <a href="<?php echo $slotsLeft > 0 ? 'add-service.php' : '#'; ?>"
         class="side-item"
         <?php if ($slotsLeft <= 0): ?>onclick="openSlotsModal(); return false;"<?php endif; ?>>
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить сервис
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
      <div class="side-divider"></div>
      <a href="logout.php" class="side-item logout">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Выйти
      </a>
    </div>
  </div>

  <!-- КОНТЕНТ -->
  <main class="profile-container">

    <?php if ($successMessage): ?>
    <div class="alert alert-success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <span><?php echo htmlspecialchars($successMessage); ?></span>
      <button class="alert-close" onclick="this.closest('.alert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- АВАТАР + ИМЯ -->
    <div class="profile-hero">
      <div class="avatar-wrap">
        <div class="avatar-main">
          <?php if ($userAvatar): ?>
            <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
          <?php else: ?>
            <?php echo $userInitial; ?>
          <?php endif; ?>
        </div>
        <div class="avatar-edit-btn" onclick="openAvatarModal()">
          <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
      </div>
      <div class="profile-name"><?php echo htmlspecialchars($userName); ?></div>
      <div class="profile-email"><?php echo htmlspecialchars($userEmail); ?></div>
    </div>

    <!-- СЕРВИСЫ -->
    <div class="section">
      <div class="section-title">Сервисы</div>
      <a href="<?php echo $slotsLeft > 0 ? 'add-service.php' : '#'; ?>"
         class="s-item"
         <?php if ($slotsLeft <= 0): ?>onclick="openSlotsModal(); return false;"<?php endif; ?>>
        <div class="s-icon blue">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Добавить сервис</div>
          <div class="s-desc">
            <?php if ($slotsLeft > 0): ?>
              Осталось слотов: <?php echo $slotsLeft; ?> из <?php echo $maxSlots; ?>
            <?php else: ?>
              <span style="color:var(--danger, #EF4444);">Все слоты заняты</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="s-right">
          <?php if ($slotsLeft > 0): ?>
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
          <?php else: ?>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#EF4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          <?php endif; ?>
        </div>
      </a>
      <a href="my-services.php" class="s-item">
        <div class="s-icon green">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Мои сервисы</div>
          <div class="s-desc">Управление и статусы</div>
        </div>
        <div class="s-right">
          <span class="s-badge <?php echo $serviceCount == 0 ? 'empty' : ''; ?>"><?php echo $serviceCount; ?></span>
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <a href="#" class="s-item" onclick="openFavoritesModal(); return false;">
        <div class="s-icon" style="background:#FFF0F0; color:#EF4444;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Избранное</div>
          <div class="s-desc">Сохранённые сервисы</div>
        </div>
        <div class="s-right">
          <span class="s-badge <?php echo $favCount == 0 ? 'empty' : ''; ?>" id="favCountBadge"><?php echo $favCount; ?></span>
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <?php if ($approvedCount > 0): ?>
      <a href="my-reviews.php" class="s-item">
        <div class="s-icon" style="background:#EFF6FF; color:#2E73D8;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Отзывы о моих сервисах</div>
          <div class="s-desc">Ответы на отзывы клиентов</div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <?php endif; ?>
    </div>

    <!-- ПОВЫСИТЬ ВИДИМОСТЬ -->
    <?php if (!empty($approvedServices)): ?>
    <?php $today = date('Y-m-d'); ?>
    <div class="section">
      <div class="section-title">Повысить видимость</div>
      <div style="font-size:12.5px;color:var(--text-secondary);padding:8px 16px 4px;font-weight:500;">Показать что ваш профиль надёжный</div>
      <?php foreach ($approvedServices as $asvc):
          $svcId      = $asvc['id'];
          $vr         = $verifRequests[$svcId] ?? null;
          $isVerified = $asvc['verified'] && ($asvc['verified_until'] === null || $asvc['verified_until'] >= $today);
          if ($isVerified) {
              $dotColor = '#10B981';
          } elseif ($vr && $vr['status'] === 'pending') {
              $dotColor = '#F59E0B';
          } elseif ($vr && $vr['status'] === 'rejected') {
              $dotColor = '#EF4444';
          } else {
              $dotColor = '#94A3B8';
          }
      ?>
      <a href="/verification.php?service_id=<?php echo $svcId; ?>" class="s-item">
        <div class="s-icon slate" style="background:transparent;">
          <div style="width:12px;height:12px;border-radius:50%;background:<?php echo $dotColor; ?>;flex-shrink:0;"></div>
        </div>
        <div class="s-content">
          <div class="s-label"><?php echo htmlspecialchars($asvc['name']); ?></div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- НАСТРОЙКИ -->
    <div class="section">
      <div class="section-title">Настройки</div>
      <a href="#" class="s-item" onclick="openSheet('sheetAccount'); return false;">
        <div class="s-icon purple">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Аккаунт</div>
          <div class="s-desc">Имя, email, пароль</div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <a href="#" class="s-item" onclick="openSheet('sheetNotif'); return false;">
        <div class="s-icon orange">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Уведомления</div>
          <div class="s-desc">Email уведомления</div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
    </div>

    <!-- ПОДДЕРЖКА -->
    <div class="section">
      <div class="section-title">Поддержка</div>
      <a href="#" class="s-item" onclick="openSheet('sheetContact'); return false;">
        <div class="s-icon green">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Связаться с нами</div>
          <div class="s-desc">Написать или горячая линия</div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
      <a href="#" class="s-item" onclick="openSheet('sheetFaq'); return false;">
        <div class="s-icon blue">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
        </div>
        <div class="s-content">
          <div class="s-label">Помощь</div>
          <div class="s-desc">Частые вопросы</div>
        </div>
        <div class="s-right">
          <svg class="s-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </div>
      </a>
    </div>

    <!-- ВЫХОД -->
    <a href="logout.php" class="logout-btn">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Выйти из аккаунта
    </a>
  </main>

  <!-- ФУТЕР -->
  <footer class="profile-footer">
    <div class="footer-links">
      <a href="/help.php" class="footer-link">Помощь</a>
<a href="/terms.php" class="footer-link">Условия</a>
<a href="/about.php" class="footer-link">О нас</a>
<a href="/contact.php" class="footer-link">Контакт</a>
    </div>
  </footer>

  <!-- МОДАЛКА СВЕЖИЕ СЕРВИСЫ -->
  <div class="ann-modal" id="annModal">
    <div class="ann-header">
      <span class="ann-header-icon">📢</span>
      <span class="ann-title">Свежие сервисы</span>
      <button class="ann-close" onclick="closeAnnModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="ann-city">
      <select id="citySelect" class="city-select" onchange="filterByCity()">
        <option>Загрузка...</option>
      </select>
    </div>
    <div class="ann-content" id="annContent">
      <div class="ann-loading">
        <div class="spinner"></div>
        <p>Загрузка сервисов...</p>
      </div>
    </div>
  </div>

</div><!-- /app-container -->

<!-- ════════════════════════════════════════
     МОДАЛКА: ИЗБРАННОЕ
════════════════════════════════════════ -->
<div class="ann-modal" id="favModal">
  <div class="ann-header">
    <span class="ann-header-icon">❤️</span>
    <span class="ann-title">Избранное</span>
    <button class="ann-close" onclick="closeFavoritesModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="ann-content" id="favContent">
    <div class="ann-loading">
      <div class="spinner"></div>
      <p>Загрузка...</p>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════
     МОДАЛКА: АККАУНТ
════════════════════════════════════════ -->
<div class="sheet-overlay" id="sheetAccount" onclick="onSheetOverlayClick(event,'sheetAccount')">
  <div class="sheet">
    <div class="sheet-header">
      <span class="sheet-title">Аккаунт</span>
      <button class="sheet-close" onclick="closeSheet('sheetAccount')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="sheet-body">
      <!-- Вкладки -->
      <div class="acct-tabs">
        <button class="acct-tab active" onclick="switchTab('name')">Имя</button>
        <button class="acct-tab" onclick="switchTab('password')">Пароль</button>
        <button class="acct-tab" onclick="switchTab('email')">Email</button>
      </div>

      <!-- Панель: Имя -->
      <div class="acct-panel active" id="panelName">
        <div class="field-group">
          <label class="field-label">Ваше имя</label>
          <input type="text" class="field-input" id="inputName"
            value="<?php echo htmlspecialchars($userName); ?>"
            placeholder="Введите имя" maxlength="100">
          <div class="field-error" id="errName"></div>
        </div>
        <button class="sheet-btn primary" onclick="saveName()">Сохранить</button>
      </div>

      <!-- Панель: Пароль -->
      <div class="acct-panel" id="panelPassword">
        <div class="field-group">
          <label class="field-label">Текущий пароль</label>
          <input type="password" class="field-input" id="inputOldPass" placeholder="Введите текущий пароль">
          <div class="field-error" id="errOldPass"></div>
        </div>
        <div class="field-group">
          <label class="field-label">Новый пароль</label>
          <input type="password" class="field-input" id="inputNewPass" placeholder="Минимум 8 символов">
          <div class="field-error" id="errNewPass"></div>
        </div>
        <div class="field-group">
          <label class="field-label">Повторите новый пароль</label>
          <input type="password" class="field-input" id="inputConfirmPass" placeholder="Повторите пароль">
          <div class="field-error" id="errConfirmPass"></div>
        </div>
        <button class="sheet-btn primary" onclick="savePassword()">Сменить пароль</button>
      </div>

      <!-- Панель: Email (с кодом верификации) -->
      <div class="acct-panel" id="panelEmail">
        <!-- Шаг 1: ввод нового email -->
        <div id="emailStep1">
          <div class="field-group">
            <label class="field-label">Текущий email</label>
            <input type="text" class="field-input" value="<?php echo htmlspecialchars($userEmail); ?>" disabled
              style="background:var(--bg-secondary); color:var(--text-secondary);">
          </div>
          <div class="field-group">
            <label class="field-label">Новый email</label>
            <input type="email" class="field-input" id="inputNewEmail" placeholder="Введите новый email">
            <div class="field-hint">На него придёт код подтверждения</div>
            <div class="field-error" id="errNewEmail"></div>
          </div>
          <button class="sheet-btn primary" onclick="requestEmailCode()">Отправить код</button>
        </div>
        <!-- Шаг 2: ввод кода -->
        <div id="emailStep2" style="display:none;">
          <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5;">
            Мы отправили 6-значный код на <strong id="emailCodeTarget" style="color:var(--text);"></strong>
          </p>
          <div class="code-inputs">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
            <input type="text" class="code-input" maxlength="1" inputmode="numeric">
          </div>
          <div class="field-error" id="errEmailCode" style="text-align:center;"></div>
          <button class="sheet-btn primary" onclick="verifyEmailCode()">Подтвердить</button>
          <button class="sheet-btn danger" onclick="backToEmailStep1()">← Изменить email</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════
     МОДАЛКА: УВЕДОМЛЕНИЯ
════════════════════════════════════════ -->
<div class="sheet-overlay" id="sheetNotif" onclick="onSheetOverlayClick(event,'sheetNotif')">
  <div class="sheet">
    <div class="sheet-header">
      <span class="sheet-title">Уведомления</span>
      <button class="sheet-close" onclick="closeSheet('sheetNotif')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="sheet-body">

      <!-- Уведомление 1: Новые сервисы -->
      <div class="notif-item">
        <div class="notif-icon orange">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </div>
        <div class="notif-text">
          <div class="notif-label">Новые сервисы в моём городе</div>
          <div class="notif-desc">Email когда появляются новые специалисты рядом</div>
        </div>
        <label class="toggle">
          <input type="checkbox" id="toggleNewServices" <?php echo $notifyNewServices ? 'checked' : ''; ?>
            onchange="toggleNewServicesNotif(this.checked)">
          <div class="toggle-track"><div class="toggle-thumb"></div></div>
        </label>
      </div>

      <!-- Выбор города для уведомлений -->
      <div class="city-picker" id="cityPickerWrap" style="display:none;">
        <div style="display:flex;gap:8px;margin-bottom:8px;">
          <select id="notifCountrySelect" class="notif-select" onchange="loadNotifCities()">
            <option value="">Выберите страну...</option>
          </select>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <select id="notifCitySelect" class="notif-select">
            <option value="">Выберите город...</option>
          </select>
          <span id="citySaveStatus" style="font-size:12px;color:var(--success);display:none;">✓</span>
        </div>
        <button onclick="saveNotifCity()" id="saveCityBtn"
          style="margin-top:10px;width:100%;padding:11px;border-radius:8px;border:none;
                 background:var(--primary);color:white;font-size:14px;font-weight:700;
                 font-family:inherit;cursor:pointer;transition:opacity 0.15s;">
          Сохранить город
        </button>
      </div>

      <!-- Уведомление 2: Просмотры -->
      <div class="notif-item">
        <div class="notif-icon blue">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="notif-text">
          <div class="notif-label">Статистика просмотров</div>
          <div class="notif-desc">Сколько раз смотрели ваши сервисы</div>
          <div class="notif-freq <?php echo ($notifyViewsFreq !== 'off') ? 'show' : ''; ?>" id="freqWrap">
            <select id="selectViewsFreq" onchange="saveNotifSetting('views_freq', this.value)">
              <option value="weekly"  <?php echo $notifyViewsFreq === 'weekly'  ? 'selected' : ''; ?>>Раз в неделю</option>
              <option value="monthly" <?php echo $notifyViewsFreq === 'monthly' ? 'selected' : ''; ?>>Раз в месяц</option>
            </select>
          </div>
        </div>
        <label class="toggle">
          <input type="checkbox" id="toggleViewsFreq" <?php echo ($notifyViewsFreq !== 'off') ? 'checked' : ''; ?>
            onchange="toggleViewsFreq(this.checked)">
          <div class="toggle-track"><div class="toggle-thumb"></div></div>
        </label>
      </div>

      <p style="font-size:12px;color:var(--text-light);margin-top:16px;line-height:1.5;text-align:center;">
        Уведомления отправляются на <strong><?php echo htmlspecialchars($userEmail); ?></strong>
      </p>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════
     МОДАЛКА: СВЯЗАТЬСЯ С НАМИ
════════════════════════════════════════ -->
<div class="sheet-overlay" id="sheetContact" onclick="onSheetOverlayClick(event,'sheetContact')">
  <div class="sheet">
    <div class="sheet-header">
      <span class="sheet-title">Связаться с нами</span>
      <button class="sheet-close" onclick="closeSheet('sheetContact')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="sheet-body">

      <!-- Написать сообщение — аккордеон -->
      <div class="accord-item" id="accordMsg">
        <div class="accord-head" onclick="toggleAccord('accordMsg')">
          <span>✉️ Написать администрации</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">
          <div class="contact-form">
            <div class="field-group" style="margin-bottom:0;">
              <label class="field-label">Тема</label>
              <input type="text" class="field-input" id="contactSubject" placeholder="Кратко опишите тему">
            </div>
            <div class="field-group" style="margin-bottom:0;">
              <label class="field-label">Сообщение</label>
              <textarea class="contact-textarea" id="contactMessage" placeholder="Опишите ваш вопрос или предложение..."></textarea>
            </div>
            <button class="sheet-btn primary" onclick="sendContactMessage()">Отправить</button>
          </div>
        </div>
      </div>

      <!-- Горячая линия — аккордеон -->
      <div class="accord-item" id="accordHotline">
        <div class="accord-head" onclick="toggleAccord('accordHotline')">
          <span>🔴 Горячая линия</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">
          <p style="font-size:13.5px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;">
            Свяжитесь с модератором напрямую через WhatsApp. Обычно отвечаем в течение нескольких часов.
          </p>
          <?php if ($supportWhatsapp): ?>
          <a class="wa-btn" href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $supportWhatsapp); ?>?text=Здравствуйте%2C%20у%20меня%20вопрос%20по%20Poisq" target="_blank">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Написать в WhatsApp
          </a>
          <?php else: ?>
          <p style="font-size:13px;color:var(--text-light);text-align:center;padding:10px 0;">
            Горячая линия временно недоступна
          </p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ════════════════════════════════════════
     МОДАЛКА: FAQ
════════════════════════════════════════ -->
<div class="sheet-overlay" id="sheetFaq" onclick="onSheetOverlayClick(event,'sheetFaq')">
  <div class="sheet">
    <div class="sheet-header">
      <span class="sheet-title">Частые вопросы</span>
      <button class="sheet-close" onclick="closeSheet('sheetFaq')">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="sheet-body">

      <?php
      $faq = [
        ['q' => 'Как добавить свой сервис?',
         'a' => 'Нажмите «Добавить сервис» в профиле или на главной странице. Заполните форму с описанием, контактами и фотографиями. После отправки сервис проходит модерацию — обычно это занимает до 24 часов.'],
        ['q' => 'Сколько стоит размещение?',
         'a' => 'Базовое размещение полностью бесплатное. Вы можете разместить до 3 сервисов без ограничений. В будущем появятся платные опции для повышенной видимости.'],
        ['q' => 'Почему мой сервис ещё не опубликован?',
         'a' => 'После отправки сервис проходит ручную модерацию. Это может занять до 24 часов в будние дни. Если сервис отклонён — вы получите email с причиной и сможете исправить и отправить снова.'],
        ['q' => 'Как изменить информацию в сервисе?',
         'a' => 'Перейдите в раздел «Мои сервисы», выберите нужный и нажмите «Редактировать». После изменений сервис снова отправится на модерацию.'],
        ['q' => 'Могу ли я скрыть сервис на время?',
         'a' => 'Да. В разделе «Мои сервисы» у каждого одобренного сервиса есть переключатель видимости. Скрытый сервис не отображается в каталоге, но не удаляется.'],
        ['q' => 'Как найти специалиста в моём городе?',
         'a' => 'На главной странице выберите страну и город, укажите нужную категорию и нажмите «Найти». Также можно использовать кнопку 📢 для просмотра свежих сервисов в вашем городе.'],
        ['q' => 'Мои данные в безопасности?',
         'a' => 'Мы не передаём ваши данные третьим лицам. Email используется только для входа и уведомлений, которые вы сами включаете. Вы можете удалить аккаунт в любой момент, написав нам.'],
        ['q' => 'Как удалить свой аккаунт?',
         'a' => 'Напишите нам через раздел «Связаться с нами» с темой «Удаление аккаунта». Мы удалим все ваши данные в течение 5 рабочих дней.'],
      ];
      foreach ($faq as $i => $item):
      ?>
      <div class="accord-item" id="faq<?php echo $i; ?>">
        <div class="accord-head" onclick="toggleAccord('faq<?php echo $i; ?>')">
          <span><?php echo htmlspecialchars($item['q']); ?></span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body"><?php echo htmlspecialchars($item['a']); ?></div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>


<!-- МОДАЛКА РЕДАКТИРОВАНИЯ АВАТАРА -->
<div class="avatar-modal-overlay" id="avatarModalOverlay" onclick="onAvatarOverlayClick(event)">
  <div class="avatar-modal" id="avatarModal">

    <!-- Шапка -->
    <div class="avm-header">
      <div class="avm-header-avatar" id="avmHeaderAvatar">
        <?php if ($userAvatar): ?>
          <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="" id="avmHeaderImg">
        <?php else: ?>
          <span id="avmHeaderInitial"><?php echo $userInitial; ?></span>
        <?php endif; ?>
      </div>
      <div class="avm-header-text">
        <div class="avm-header-title">Фото профиля</div>
        <div class="avm-header-sub">Редактирование фотографии</div>
      </div>
      <button class="avm-close" onclick="closeAvatarModal()">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Пункты -->
    <div class="avm-items">
      <!-- Сделать фото -->
      <button class="avm-item" onclick="triggerCamera()">
        <div class="avm-item-icon blue">
          <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <span class="avm-item-label">Сделать фото</span>
      </button>

      <div class="avm-divider"></div>

      <!-- Выбрать из галереи -->
      <button class="avm-item" onclick="triggerGallery()">
        <div class="avm-item-icon green">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        </div>
        <span class="avm-item-label">Выбрать фото</span>
      </button>

      <!-- Удалить — только если есть аватар -->
      <?php if ($userAvatar): ?>
      <div class="avm-divider"></div>
      <button class="avm-item delete" id="avmDeleteBtn" onclick="toggleDeleteDialog()">
        <div class="avm-item-icon red">
          <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </div>
        <span class="avm-item-label">Удалить фото</span>
      </button>
      <?php endif; ?>
    </div>

    <!-- Диалог подтверждения удаления -->
    <div class="avm-delete-dialog" id="avmDeleteDialog">
      <div class="avm-delete-title">Удалить фото профиля?</div>
      <div class="avm-delete-text">Фото будет удалено. Вместо него будет отображаться буква вашего имени.</div>
      <div class="avm-delete-btns">
        <button class="avm-btn-cancel" onclick="toggleDeleteDialog()">Отмена</button>
        <button class="avm-btn-confirm" onclick="deleteAvatar()">Удалить</button>
      </div>
    </div>

    <!-- Прогресс загрузки -->
    <div class="avm-upload-progress" id="avmProgress">
      <div class="avm-progress-label">
        <span>Загрузка...</span>
        <span id="avmProgressPct">0%</span>
      </div>
      <div class="avm-progress-bar">
        <div class="avm-progress-fill" id="avmProgressFill"></div>
      </div>
    </div>

  </div>
</div>

<!-- Скрытые инпуты для файлов -->
<input type="file" id="avatarFileCamera"  accept="image/*" capture="user">
<input type="file" id="avatarFileGallery" accept="image/*">

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
// ── TOAST ──────────────────────────────────────
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show';
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.className = 'toast'; }, 2600);
}

// ── БОКОВОЕ МЕНЮ ──────────────────────────────
const menuToggle  = document.getElementById('menuToggle');
const sideMenu    = document.getElementById('sideMenu');
const menuOverlay = document.getElementById('menuOverlay');

menuToggle.addEventListener('click', toggleMenu);

function toggleMenu() {
  const open = sideMenu.classList.toggle('active');
  menuOverlay.classList.toggle('active', open);
  menuToggle.classList.toggle('active', open);
  document.body.style.overflow = open ? 'hidden' : '';
}
function closeMenu() {
  sideMenu.classList.remove('active');
  menuOverlay.classList.remove('active');
  menuToggle.classList.remove('active');
  document.body.style.overflow = '';
}

// ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ─────────────────────
let userCityId = null;

async function openAnnModal() {
  const modal = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';

  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;

  try {
    const cr = await fetch('api/get-user-country.php');
    const cd = await cr.json();
    const countryCode = cd.country_code || 'fr';

    const citr = await fetch(`api/get-cities.php?country=${countryCode}`);
    const cities = await citr.json();

    const sel = document.getElementById('citySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name + (c.is_capital == 1 ? ' (столица)' : '');
      sel.appendChild(opt);
      if (c.is_capital == 1 && !userCityId) userCityId = c.id;
    });
    if (!userCityId && cities.length) userCityId = cities[0].id;
    if (userCityId) sel.value = userCityId;

    await loadAnnServices(userCityId);
  } catch (e) {
    content.innerHTML = annError('Ошибка загрузки', 'Проверьте соединение и попробуйте снова.');
  }
}

function closeAnnModal() {
  document.getElementById('annModal').classList.remove('active');
  document.body.style.overflow = '';
}

async function filterByCity() {
  userCityId = document.getElementById('citySelect').value;
  await loadAnnServices(userCityId);
}

async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;

  try {
    const r    = await fetch(`api/get-services.php?city_id=${cityId}&days=5`);
    const data = await r.json();
    const services = data.services || [];

    if (!services.length) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
          </div>
          <h3>Пока нет сервисов</h3>
          <p>В этом городе нет новых сервисов за последние 5 дней</p>
          <button class="ann-add-btn" onclick="location.href='add-service.php'">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Добавить сервис
          </button>
          <span class="ann-add-free">бесплатно</span>
        </div>`;
      return;
    }

    const byCat = {};
    services.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });

    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += `<div class="ann-category">
        <div class="ann-cat-title">${cat}</div>
        <div class="ann-grid">
          ${list.map(s => {
            let photo = 'https://via.placeholder.com/200?text=Poisq';
            if (s.photo) {
              try {
                const p = JSON.parse(s.photo);
                photo = Array.isArray(p) ? p[0] : s.photo;
              } catch { photo = s.photo; }
            }
            return `
            <div class="ann-item" onclick="location.href='service.php?id=${s.id}'">
              <img src="${photo}" alt="${s.name}" loading="lazy" onerror="this.src='https://via.placeholder.com/200?text=Poisq'">
              <div class="ann-date-badge">${fmtDate(s.created_at)}</div>
              <div class="ann-item-name">${s.name}</div>
            </div>`;
          }).join('')}
          <div class="ann-add-card" onclick="location.href='add-service.php'">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Добавить свой сервис</span>
          </div>
        </div>
      </div>`;
    }
    content.innerHTML = html;
  } catch {
    content.innerHTML = annError('Ошибка', 'Не удалось загрузить данные.');
  }
}

function annError(title, text) {
  return `<div class="ann-empty">
    <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <h3>${title}</h3><p>${text}</p>
  </div>`;
}

// ── МОДАЛКА АВАТАРА ──────────────────────────────

const HAS_AVATAR = <?php echo $userAvatar ? 'true' : 'false'; ?>;

function openAvatarModal() {
  document.getElementById('avatarModalOverlay').classList.add('active');
  document.body.style.overflow = 'hidden';
  // сбрасываем диалог удаления если был открыт
  document.getElementById('avmDeleteDialog').classList.remove('active');
  document.getElementById('avmProgress').classList.remove('active');
}

function closeAvatarModal() {
  document.getElementById('avatarModalOverlay').classList.remove('active');
  document.body.style.overflow = '';
  document.getElementById('avmDeleteDialog').classList.remove('active');
}

function onAvatarOverlayClick(e) {
  if (e.target === document.getElementById('avatarModalOverlay')) closeAvatarModal();
}

function toggleDeleteDialog() {
  const d = document.getElementById('avmDeleteDialog');
  d.classList.toggle('active');
  // скролл к диалогу
  if (d.classList.contains('active')) {
    d.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

// Камера
function triggerCamera() {
  document.getElementById('avatarFileCamera').click();
}

// Галерея
function triggerGallery() {
  document.getElementById('avatarFileGallery').click();
}

// Обработка выбора файла (camera или gallery)
['avatarFileCamera', 'avatarFileGallery'].forEach(id => {
  document.getElementById(id).addEventListener('change', function() {
    if (this.files && this.files[0]) {
      uploadAvatar(this.files[0]);
      this.value = ''; // сброс чтобы можно было выбрать снова
    }
  });
});

async function uploadAvatar(file) {
  // Валидация
  if (!file.type.startsWith('image/')) {
    showToast('Выберите изображение'); return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showToast('Файл слишком большой. Максимум 5 МБ'); return;
  }

  // Показываем превью сразу
  const reader = new FileReader();
  reader.onload = e => updateAvatarPreview(e.target.result);
  reader.readAsDataURL(file);

  // Прогресс
  const progress = document.getElementById('avmProgress');
  const fill     = document.getElementById('avmProgressFill');
  const pct      = document.getElementById('avmProgressPct');
  progress.classList.add('active');

  // Имитация прогресса + реальный upload
  let fake = 0;
  const fakeTimer = setInterval(() => {
    fake = Math.min(fake + Math.random() * 15, 85);
    fill.style.width = fake + '%';
    pct.textContent  = Math.round(fake) + '%';
  }, 200);

  try {
    const fd = new FormData();
    fd.append('action', 'upload_avatar');
    fd.append('avatar', file);

    const res  = await fetch('api/update-avatar.php', { method: 'POST', body: fd });
    const data = await res.json();

    clearInterval(fakeTimer);
    fill.style.width = '100%';
    pct.textContent  = '100%';

    if (data.success) {
      // Обновляем все аватары на странице
      updateAvatarPreview(data.avatar_url);
      setTimeout(() => {
        progress.classList.remove('active');
        closeAvatarModal();
        showToast('Фото профиля обновлено ✓');
      }, 400);
    } else {
      throw new Error(data.error || 'Ошибка загрузки');
    }
  } catch (err) {
    clearInterval(fakeTimer);
    progress.classList.remove('active');
    showToast('Ошибка: ' + err.message);
    // откат превью — перезагружаем страницу чтобы восстановить
  }
}

async function deleteAvatar() {
  try {
    const fd = new FormData();
    fd.append('action', 'delete_avatar');
    const res  = await fetch('api/update-avatar.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeAvatarModal();
      showToast('Фото удалено');
      setTimeout(() => location.reload(), 800);
    } else {
      throw new Error(data.error || 'Ошибка');
    }
  } catch (err) {
    showToast('Ошибка: ' + err.message);
  }
}

function updateAvatarPreview(src) {
  // Большой аватар на странице
  const mainAvatar = document.querySelector('.avatar-main');
  mainAvatar.innerHTML = `<img src="${src}" alt="" style="width:100%;height:100%;object-fit:cover;">`;

  // Мини-аватар в шапке модалки
  const avmHead = document.getElementById('avmHeaderAvatar');
  avmHead.innerHTML = `<img src="${src}" alt="" id="avmHeaderImg">`;

  // Аватар в боковом меню
  const sideAv = document.querySelector('.side-avatar');
  if (sideAv) sideAv.innerHTML = `<img src="${src}" alt="">`;
}

function fmtDate(ds) {
  const d = new Date(ds), now = new Date();
  const diff = Math.floor((now - d) / 86400000);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Вчера';
  if (diff < 5)  return diff + ' дн.';
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}

// ══════════════════════════════════════════════
// ИЗБРАННОЕ
// ══════════════════════════════════════════════
function openFavoritesModal() {
  document.getElementById('favModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  loadFavorites();
}

function closeFavoritesModal() {
  document.getElementById('favModal').classList.remove('active');
  document.body.style.overflow = '';
}

async function loadFavorites() {
  const content = document.getElementById('favContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';

  try {
    const res  = await fetch('api/favorites.php?action=list');
    const data = await res.json();

    if (!data.success || !data.items || data.items.length === 0) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
          </div>
          <h3>Пока пусто</h3>
          <p>Нажимайте ❤️ на карточках сервисов,<br>чтобы сохранять их здесь</p>
          <a href="index.php" class="ann-add-btn" onclick="closeFavoritesModal()">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Найти сервисы
          </a>
        </div>`;
      return;
    }

    // Обновляем счётчик в профиле
    const badge = document.getElementById('favCountBadge');
    if (badge) {
      badge.textContent = data.items.length;
      badge.className = 's-badge' + (data.items.length === 0 ? ' empty' : '');
    }

    // Рендерим список
    let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
    data.items.forEach(item => {
      const photo = item.photo || 'https://via.placeholder.com/80x80?text=Poisq';
      const rating = parseFloat(item.rating || 0).toFixed(1);
      html += `
        <div style="display:flex;align-items:center;gap:12px;background:var(--bg);border-radius:12px;padding:12px;border:1px solid var(--border-light);box-shadow:var(--shadow-sm);">
          <img src="${photo}" alt="" style="width:64px;height:64px;border-radius:10px;object-fit:cover;flex-shrink:0;background:var(--bg-secondary);">
          <div style="flex:1;min-width:0;">
            <div style="font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.name)}</div>
            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px;">${escHtml(item.city_name || '')}${item.city_name ? ' · ' : ''}${escHtml(item.category || '')}</div>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:12px;font-weight:700;color:#F59E0B;">★ ${rating}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
            <a href="service.php?id=${item.id}" style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:var(--primary);color:white;text-decoration:none;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M9 18l6-6-6-6"/></svg>
            </a>
            <button onclick="removeFavorite(${item.id}, this)" style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#FFF0F0;border:none;cursor:pointer;">
              <svg viewBox="0 0 24 24" fill="#EF4444" stroke="#EF4444" stroke-width="2" width="16" height="16"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
          </div>
        </div>`;
    });
    html += '</div>';
    content.innerHTML = html;

  } catch(e) {
    content.innerHTML = '<div class="ann-empty"><p>Ошибка загрузки. Попробуйте ещё раз.</p></div>';
  }
}

async function removeFavorite(serviceId, btn) {
  const card = btn.closest('div[style*="display:flex"]');
  card.style.opacity = '0.5';
  try {
    const fd = new FormData();
    fd.append('service_id', serviceId);
    const res  = await fetch('api/favorites.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      card.remove();
      // Обновляем счётчик
      const badge = document.getElementById('favCountBadge');
      if (badge) {
        const count = Math.max(0, parseInt(badge.textContent) - 1);
        badge.textContent = count;
        badge.className = 's-badge' + (count === 0 ? ' empty' : '');
      }
      // Если список пуст — показываем заглушку
      const content = document.getElementById('favContent');
      if (!content.querySelector('div[style*="display:flex"] img')) {
        loadFavorites();
      }
    }
  } catch(e) {
    card.style.opacity = '1';
  }
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


function openVerifForm(serviceId) {
  const wrap = document.getElementById('verifFormWrap');
  if (!wrap) return;
  const sel = document.getElementById('verifServiceSelect');
  if (sel) sel.value = serviceId;
  wrap.style.display = 'block';
  wrap.scrollIntoView({behavior: 'smooth', block: 'start'});
}
function closeVerifForm() {
  const wrap = document.getElementById('verifFormWrap');
  if (wrap) wrap.style.display = 'none';
}

function openSheet(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeSheet(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}
function onSheetOverlayClick(e, id) {
  if (e.target === document.getElementById(id)) closeSheet(id);
}

// ══════════════════════════════════════════════
// АККАУНТ — вкладки
// ══════════════════════════════════════════════
function switchTab(tab) {
  document.querySelectorAll('.acct-tab').forEach((t, i) => {
    t.classList.toggle('active', ['name','password','email'][i] === tab);
  });
  document.querySelectorAll('.acct-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
}

// Сохранение имени
async function saveName() {
  const name = document.getElementById('inputName').value.trim();
  const err  = document.getElementById('errName');
  err.classList.remove('show');
  if (!name || name.length < 2) { err.textContent = 'Имя должно содержать минимум 2 символа'; err.classList.add('show'); return; }
  try {
    const fd = new FormData();
    fd.append('action', 'update_name');
    fd.append('name', name);
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.querySelector('.profile-name').textContent = name;
      document.querySelector('.side-user-name').textContent = name;
      closeSheet('sheetAccount');
      showToast('Имя обновлено ✓');
    } else { err.textContent = data.error || 'Ошибка'; err.classList.add('show'); }
  } catch(e) { err.textContent = 'Ошибка соединения'; err.classList.add('show'); }
}

// Смена пароля
async function savePassword() {
  const oldP = document.getElementById('inputOldPass').value;
  const newP = document.getElementById('inputNewPass').value;
  const cnfP = document.getElementById('inputConfirmPass').value;
  ['errOldPass','errNewPass','errConfirmPass'].forEach(id => document.getElementById(id).classList.remove('show'));

  if (!oldP) { showFieldErr('errOldPass', 'Введите текущий пароль'); return; }
  if (newP.length < 8) { showFieldErr('errNewPass', 'Минимум 8 символов'); return; }
  if (newP !== cnfP)   { showFieldErr('errConfirmPass', 'Пароли не совпадают'); return; }

  try {
    const fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('old_password', oldP);
    fd.append('new_password', newP);
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.getElementById('inputOldPass').value = '';
      document.getElementById('inputNewPass').value = '';
      document.getElementById('inputConfirmPass').value = '';
      closeSheet('sheetAccount');
      showToast('Пароль изменён ✓');
    } else { showFieldErr('errOldPass', data.error || 'Ошибка'); }
  } catch(e) { showFieldErr('errOldPass', 'Ошибка соединения'); }
}

function showFieldErr(id, msg) {
  const el = document.getElementById(id);
  el.textContent = msg; el.classList.add('show');
}

// Смена email — шаг 1: запрос кода
async function requestEmailCode() {
  const email = document.getElementById('inputNewEmail').value.trim();
  const err   = document.getElementById('errNewEmail');
  err.classList.remove('show');
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    err.textContent = 'Введите корректный email'; err.classList.add('show'); return;
  }
  try {
    const fd = new FormData();
    fd.append('action', 'request_email_change');
    fd.append('new_email', email);
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.getElementById('emailCodeTarget').textContent = email;
      document.getElementById('emailStep1').style.display = 'none';
      document.getElementById('emailStep2').style.display = 'block';
      // Фокус на первый инпут кода
      document.querySelector('.code-input').focus();
      initCodeInputs();
    } else { err.textContent = data.error || 'Ошибка'; err.classList.add('show'); }
  } catch(e) { err.textContent = 'Ошибка соединения'; err.classList.add('show'); }
}

// Автопереключение между цифрами кода
function initCodeInputs() {
  const inputs = document.querySelectorAll('.code-input');
  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/[^0-9]/g, '').slice(-1);
      if (inp.value && i < inputs.length - 1) inputs[i+1].focus();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) inputs[i-1].focus();
    });
  });
}

// Смена email — шаг 2: подтверждение кода
async function verifyEmailCode() {
  const inputs = document.querySelectorAll('.code-input');
  const code   = Array.from(inputs).map(i => i.value).join('');
  const err    = document.getElementById('errEmailCode');
  err.classList.remove('show');
  if (code.length < 6) { err.textContent = 'Введите все 6 цифр'; err.classList.add('show'); return; }
  try {
    const fd = new FormData();
    fd.append('action', 'verify_email_change');
    fd.append('code', code);
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.querySelector('.profile-email').textContent = data.new_email;
      document.querySelector('.side-user-email').textContent = data.new_email;
      closeSheet('sheetAccount');
      showToast('Email успешно изменён ✓');
      // Сброс формы
      backToEmailStep1();
      document.getElementById('inputNewEmail').value = '';
    } else { err.textContent = data.error || 'Неверный код'; err.classList.add('show'); }
  } catch(e) { err.textContent = 'Ошибка соединения'; err.classList.add('show'); }
}

function backToEmailStep1() {
  document.getElementById('emailStep1').style.display = 'block';
  document.getElementById('emailStep2').style.display = 'none';
  document.querySelectorAll('.code-input').forEach(i => i.value = '');
}

// ══════════════════════════════════════════════
// УВЕДОМЛЕНИЯ
// ══════════════════════════════════════════════
async function saveNotifSetting(type, value) {
  try {
    const fd = new FormData();
    fd.append('action', 'save_notification');
    fd.append('type', type);
    fd.append('value', value ? '1' : '0');
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) showToast('Настройки сохранены ✓');
    else showToast('Ошибка сохранения');
  } catch(e) { showToast('Ошибка соединения'); }
}

function toggleViewsFreq(checked) {
  const wrap = document.getElementById('freqWrap');
  wrap.classList.toggle('show', checked);
  const freq = checked ? document.getElementById('selectViewsFreq').value : 'off';
  saveNotifSetting('views_freq_full', freq);
}

document.getElementById('selectViewsFreq')?.addEventListener('change', function() {
  if (document.getElementById('toggleViewsFreq').checked) {
    saveNotifSetting('views_freq_full', this.value);
  }
});

// ══════════════════════════════════════════════
// УВЕДОМЛЕНИЯ — ВЫБОР ГОРОДА
// ══════════════════════════════════════════════

const COUNTRIES = {
  "fr":"Франция","de":"Германия","es":"Испания","it":"Италия","gb":"Великобритания",
  "us":"США","ca":"Канада","au":"Австралия","nl":"Нидерланды","be":"Бельгия",
  "ch":"Швейцария","at":"Австрия","pt":"Португалия","gr":"Греция","pl":"Польша",
  "cz":"Чехия","se":"Швеция","no":"Норвегия","dk":"Дания","fi":"Финляндия",
  "ie":"Ирландия","nz":"Новая Зеландия","ae":"ОАЭ","il":"Израиль","tr":"Турция",
  "th":"Таиланд","jp":"Япония","kr":"Южная Корея","sg":"Сингапур","hk":"Гонконг",
  "mx":"Мексика","br":"Бразилия","ar":"Аргентина","cl":"Чили","co":"Колумбия",
  "za":"ЮАР","ru":"Россия","ua":"Украина","by":"Беларусь","kz":"Казахстан"
};

// Текущий city_id пользователя из БД
let currentUserCityId = <?php echo json_encode($userCityId ?? null); ?>;

async function toggleNewServicesNotif(checked) {
  // Показываем/скрываем выбор города
  const wrap = document.getElementById("cityPickerWrap");
  wrap.style.display = checked ? "block" : "none";

  // Сохраняем настройку
  await saveNotifSetting("new_services", checked);

  // Если включили — загружаем страны и города
  if (checked) {
    await initCityPicker();
  }
}

async function initCityPicker() {
  const countrySelect = document.getElementById("notifCountrySelect");

  // Заполняем страны
  countrySelect.innerHTML = "<option value=\"\">Выберите страну...</option>";
  for (const [code, name] of Object.entries(COUNTRIES)) {
    const opt = document.createElement("option");
    opt.value = code;
    opt.textContent = name;
    countrySelect.appendChild(opt);
  }

  // Определяем страну по IP
  try {
    const r = await fetch("api/get-user-country.php");
    const d = await r.json();
    const code = d.country_code || "fr";
    countrySelect.value = code;
    await loadNotifCities();
  } catch(e) {
    countrySelect.value = "fr";
    await loadNotifCities();
  }
}

async function loadNotifCities() {
  const country = document.getElementById("notifCountrySelect").value;
  const citySelect = document.getElementById("notifCitySelect");

  if (!country) return;

  citySelect.innerHTML = "<option value=\"\">Загрузка...</option>";

  try {
    const r = await fetch("api/get-cities.php?country=" + country);
    const cities = await r.json();

    citySelect.innerHTML = "<option value=\"\">Выберите город...</option>";
    cities.forEach(c => {
      const opt = document.createElement("option");
      opt.value = c.id;
      opt.textContent = c.name + (c.is_capital == 1 ? " (столица)" : "");
      citySelect.appendChild(opt);
    });

    // Если у пользователя уже сохранён город — выбираем его
    if (currentUserCityId) {
      citySelect.value = currentUserCityId;
    }
  } catch(e) {
    citySelect.innerHTML = "<option value=\"\">Ошибка загрузки</option>";
  }
}

async function saveNotifCity() {
  const cityId = document.getElementById("notifCitySelect").value;
  const status = document.getElementById("citySaveStatus");
  if (!cityId) return;

  try {
    const fd = new FormData();
    fd.append("action", "save_city");
    fd.append("city_id", cityId);
    const res = await fetch("api/update-profile.php", { method: "POST", body: fd });
    const data = await res.json();
    if (data.success) {
      currentUserCityId = parseInt(cityId);
      status.style.display = "inline";
      setTimeout(() => status.style.display = "none", 2000);
      showToast("Город сохранён ✓");
    } else {
      showToast("Ошибка: " + (data.error || "неизвестная"));
    }
  } catch(e) {
    showToast("Ошибка соединения");
  }
}

// Инициализация при открытии шторки — если уведомления уже включены
document.addEventListener("DOMContentLoaded", function() {
  const toggle = document.getElementById("toggleNewServices");
  if (toggle && toggle.checked) {
    initCityPicker();
  }
});

// ══════════════════════════════════════════════
// АККОРДЕОН
// ══════════════════════════════════════════════
function toggleAccord(id) {
  const el = document.getElementById(id);
  el.classList.toggle('open');
}

// ══════════════════════════════════════════════
// СВЯЗАТЬСЯ С НАМИ
// ══════════════════════════════════════════════
async function sendContactMessage() {
  const subject = document.getElementById('contactSubject').value.trim();
  const message = document.getElementById('contactMessage').value.trim();
  if (!message) { showToast('Введите сообщение'); return; }
  try {
    const fd = new FormData();
    fd.append('action', 'send_contact');
    fd.append('subject', subject);
    fd.append('message', message);
    const res  = await fetch('api/update-profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.getElementById('contactSubject').value = '';
      document.getElementById('contactMessage').value = '';
      // Закрываем аккордеон
      document.getElementById('accordMsg').classList.remove('open');
      closeSheet('sheetContact');
      showToast('Сообщение отправлено ✓');
    } else { showToast(data.error || 'Ошибка отправки'); }
  } catch(e) { showToast('Ошибка соединения'); }
}

</script>

<!-- МОДАЛКА: СЛОТЫ ЗАПОЛНЕНЫ -->
<div id="slotsModal" style="
  display:none; position:fixed; inset:0; z-index:600;
  background:rgba(0,0,0,0.5);
  align-items:flex-end; justify-content:center;
">
  <div style="
    background:#fff; width:100%; max-width:430px;
    border-radius:24px 24px 0 0;
    padding:32px 24px 40px;
    animation: slideUp 0.3s ease-out;
  ">
    <!-- Иконка -->
    <div style="text-align:center; margin-bottom:20px;">
      <div style="
        width:72px; height:72px; border-radius:50%;
        background:#FEF2F2; margin:0 auto 16px;
        display:flex; align-items:center; justify-content:center;
      ">
        <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="#EF4444" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div style="font-size:20px; font-weight:700; color:#1F2937; margin-bottom:8px;">
        Все слоты заняты
      </div>
      <div style="font-size:15px; color:#6B7280; line-height:1.5;">
        Вы уже разместили <strong><?php echo $slotsUsed; ?> из <?php echo $maxSlots; ?></strong> доступных сервисов.
        Максимальное количество одобренных сервисов — <strong><?php echo $maxSlots; ?></strong>.
      </div>
    </div>

    <!-- Слоты-кружки -->
    <div style="display:flex; justify-content:center; gap:12px; margin:20px 0 28px;">
      <?php for ($i = 1; $i <= $maxSlots; $i++): ?>
      <div style="
        width:52px; height:52px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        font-size:20px;
        <?php if ($i <= $slotsUsed): ?>
          background:#FEE2E2; border:2px solid #EF4444;
        <?php else: ?>
          background:#F3F4F6; border:2px dashed #D1D5DB;
        <?php endif; ?>
      ">
        <?php echo $i <= $slotsUsed ? '✓' : ''; ?>
      </div>
      <?php endfor; ?>
    </div>

    <!-- Что можно сделать -->
    <div style="
      background:#F0FDF4; border-radius:12px; padding:14px 16px;
      margin-bottom:20px; font-size:13px; color:#065F46; line-height:1.5;
    ">
      💡 Чтобы освободить слот — удалите один из существующих сервисов в разделе <strong>«Мои сервисы»</strong>
    </div>

    <!-- Кнопки -->
    <a href="my-services.php" style="
      display:block; width:100%; padding:14px;
      background:#2E73D8; color:white; border-radius:12px;
      text-align:center; font-size:15px; font-weight:600;
      text-decoration:none; margin-bottom:10px;
    ">
      Перейти в Мои сервисы
    </a>
    <button onclick="closeSlotsModal()" style="
      display:block; width:100%; padding:14px;
      background:#F3F4F6; color:#374151; border-radius:12px;
      border:none; font-size:15px; font-weight:500; cursor:pointer;
    ">
      Закрыть
    </button>
  </div>
</div>

<style>
@keyframes slideUp {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}

/* ══ City picker в уведомлениях ══ */
.city-picker {
  background: var(--bg-secondary);
  border-radius: var(--radius-xs);
  padding: 12px;
  margin: -8px 0 12px 0;
  border: 1px solid var(--border-light);
}
.notif-select {
  flex: 1; width: 100%;
  padding: 9px 32px 9px 12px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 13px; font-weight: 600; color: var(--text);
  background: var(--bg); outline: none;
  font-family: "Manrope", sans-serif;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 8px center;
  transition: border-color 0.15s; cursor: pointer;
}
.notif-select:focus { border-color: var(--primary); }
</style>
<script>
function openSlotsModal() {
  const modal = document.getElementById('slotsModal');
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeSlotsModal() {
  const modal = document.getElementById('slotsModal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
}
document.getElementById('slotsModal').addEventListener('click', function(e) {
  if (e.target === this) closeSlotsModal();
});
</script>
</body>
</html>