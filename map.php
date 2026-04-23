<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
require_once __DIR__ . '/config/database.php';

$countryCode    = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? $_SESSION['user_country'] ?? 'fr'));
$citySlug       = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['city_slug'] ?? ''));
$cityFilter     = intval($_GET['city_id'] ?? 0);
$categoryFilter = preg_replace('/[^a-z]/', '', strtolower($_GET['category'] ?? ''));
$searchQuery    = trim(urldecode($_GET['q'] ?? ''));
$ratingFilter   = floatval($_GET['rating'] ?? 0);
$verifiedFilter = isset($_GET['verified']) ? 1 : 0;
$focusId = intval($_GET['focus'] ?? 0);
$languagesFilter = array_values(array_filter(
    array_map('trim', explode(',', $_GET['languages'] ?? '')),
    fn($l) => preg_match('/^[a-z]{2}$/', $l)
));

$isLoggedIn  = isset($_SESSION['user_id']);
$userName    = $isLoggedIn ? $_SESSION['user_name']   : '';
$userAvatar  = $isLoggedIn ? $_SESSION['user_avatar'] : '';

$pdo = getDbConnection();
if (!empty($citySlug) && $cityFilter === 0) {
    $s = $pdo->prepare("SELECT id FROM cities WHERE name_lat = ? AND country_code = ? LIMIT 1");
    $s->execute([$citySlug, $countryCode]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if ($r) $cityFilter = (int)$r['id'];
}

$cityName = '';
if ($cityFilter > 0) {
    $s = $pdo->prepare("SELECT name FROM cities WHERE id = ? LIMIT 1");
    $s->execute([$cityFilter]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    if ($r) $cityName = $r['name'];
}

$countryNames = [
    'fr'=>'Франция','de'=>'Германия','es'=>'Испания','it'=>'Италия','gb'=>'Великобритания',
    'us'=>'США','ca'=>'Канада','au'=>'Австралия','nl'=>'Нидерланды','be'=>'Бельгия',
    'ch'=>'Швейцария','at'=>'Австрия','pt'=>'Португалия','pl'=>'Польша','cz'=>'Чехия',
    'se'=>'Швеция','no'=>'Норвегия','dk'=>'Дания','fi'=>'Финляндия','ae'=>'ОАЭ',
    'il'=>'Израиль','tr'=>'Турция','th'=>'Таиланд','jp'=>'Япония','sg'=>'Сингапур',
    'hk'=>'Гонконг','mx'=>'Мексика','br'=>'Бразилия','ar'=>'Аргентина','ru'=>'Россия',
];
$countryName = $countryNames[$countryCode] ?? strtoupper($countryCode);
$pageTitle = 'Карта сервисов';
if ($searchQuery) $pageTitle = 'Карта: ' . $searchQuery;
elseif ($cityName) $pageTitle .= ' — ' . $cityName;
elseif ($countryName) $pageTitle .= ' — ' . $countryName;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> — Poisq</title>
<link rel="icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/desktop.css">
<style>
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
  --surface: #F5F7FA;
  --success: #10B981;
  --warning: #F59E0B;
  --danger: #EF4444;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
  --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
  --radius: 16px;
  --radius-sm: 10px;
  --radius-xs: 8px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body { font-family: 'Manrope', sans-serif; background: var(--bg, #fff); color: var(--text, #1a1a1a); height: 100%; }
.map-container { display: flex; flex-direction: column; height: 100dvh; position: relative; }
.map-header { flex-shrink: 0; background: var(--bg, #fff); border-bottom: 1px solid var(--border-light, #E8EAED); }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.header-top { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px 6px; }
.btn-grid {
  width: 38px; height: 38px; border-radius: 12px; border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; text-decoration: none;
  transition: background 0.15s, transform 0.1s;
}
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }
.btn-add {
  width: 38px; height: 38px; border-radius: 12px; border: none;
  background: var(--primary-light); color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s, transform 0.1s;
  text-decoration: none; flex-shrink: 0;
}
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-burger {
  width: 38px; height: 38px; display: flex; flex-direction: column;
  justify-content: center; align-items: center; gap: 5px;
  padding: 8px; cursor: pointer; background: none; border: none; border-radius: 12px;
  flex-shrink: 0;
}
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
.header-search { padding: 6px 16px 8px; }
.search-bar {
  display: flex; align-items: center; gap: 10px;
  background: var(--surface, #F5F5F7); border: 1.5px solid var(--border-light, #E8EAED);
  border-radius: 24px; padding: 9px 14px; cursor: pointer;
}
.search-bar svg { width: 18px; height: 18px; stroke: var(--text-light, #9AA0A6); stroke-width: 2.5; fill: none; flex-shrink: 0; }
.search-bar span { flex: 1; font-size: 15px; font-weight: 500; color: var(--text-light, #9AA0A6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.search-bar span.has-query { color: var(--text, #1a1a1a); }
.filters-row { display: flex; gap: 6px; padding: 6px 16px 10px; overflow-x: auto; scrollbar-width: none; }
.filters-row::-webkit-scrollbar { display: none; }
.filter-chip {
  flex-shrink: 0; display: flex; align-items: center; gap: 4px;
  padding: 5px 12px; font-size: 12px; font-weight: 500; font-family: 'Manrope', sans-serif;
  border: 1px solid #DFE1E5; border-radius: 99px;
  background: #fff; color: #4D5156;
  cursor: pointer; white-space: nowrap; transition: all 0.15s; user-select: none;
}
.filter-chip:active { background: #F8F9FA; }
.filter-chip.active { background: #E8F0FE; border-color: #1A73E8; color: #1A73E8; }
.filter-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: #34A853; flex-shrink: 0; }
.filter-chip.map-active { background: #E8F0FE; border-color: #1A73E8; color: #1A73E8; font-weight: 700; }
.map-wrap { flex: 1; position: relative; min-height: 0; }
#map { width: 100%; height: 100%; }
/* POPUP */
.leaflet-popup-content-wrapper {
  border-radius: 16px !important; padding: 0 !important;
  box-shadow: 0 4px 24px rgba(0,0,0,0.18) !important; overflow: hidden;
}
.leaflet-popup-content { margin: 0 !important; width: 270px !important; }
.leaflet-popup-tip-container { display: none; }
.popup-card { font-family: 'Manrope', sans-serif; }
.popup-photo-placeholder { width: 100%; height: 130px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#e8f0fe,#f0e8fe); font-size: 40px; }
.popup-body { padding: 12px 14px 14px; }
.popup-category { font-size: 11px; font-weight: 700; color: #1A73E8; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.6px; }
.popup-name { font-size: 15px; font-weight: 700; color: #1a1a1a; margin-bottom: 6px; line-height: 1.3; }
.popup-desc { font-size: 12px; color: #5f6368; margin-bottom: 10px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.popup-contacts { display: flex; gap: 7px; margin-bottom: 10px; flex-wrap: wrap; }
.popup-contact-btn {
  display: flex; align-items: center; gap: 5px;
  padding: 5px 11px; border-radius: 20px; font-size: 12px; font-weight: 600;
  text-decoration: none; border: none; cursor: pointer; font-family: 'Manrope', sans-serif;
}
.popup-contact-btn.phone { background: #E8F0FE; color: #1A73E8; }
.popup-contact-btn.whatsapp { background: #E6F4EA; color: #1E8E3E; }
.popup-contact-btn svg { width: 13px; height: 13px; flex-shrink: 0; }
.popup-contact-btn.phone svg { stroke: #1A73E8; stroke-width: 2.5; fill: none; }
.popup-contact-btn.whatsapp svg { stroke: none; fill: #1E8E3E; }
.popup-more {
  display: block; width: 100%; padding: 10px;
  background: #3B6CF4; color: #fff !important; border: none; border-radius: 10px;
  font-size: 13px; font-weight: 700; font-family: 'Manrope', sans-serif;
  text-align: center; text-decoration: none; cursor: pointer;
}
.popup-more:hover { background: #2E5CD4; color: #fff !important; }
.map-loading {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  background: rgba(255,255,255,0.96); border-radius: 16px; padding: 20px 28px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12); z-index: 400;
  display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600;
}
.spinner { width: 20px; height: 20px; border: 2.5px solid #E8EAED; border-top-color: #3B6CF4; border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }
.map-count-badge {
  position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
  background: rgba(26,26,26,0.82); color: #fff; padding: 7px 16px; border-radius: 20px;
  font-size: 13px; font-weight: 600; z-index: 400; pointer-events: none;
  backdrop-filter: blur(4px); white-space: nowrap;
}
.map-no-results {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  background: rgba(255,255,255,0.96); border-radius: 16px; padding: 24px 32px;
  text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.12); z-index: 400; pointer-events: none;
}
.map-no-results p { font-size: 14px; color: #5f6368; margin-top: 8px; }
@media (min-width: 768px) {
  .map-container { max-width: 480px; margin: 0 auto; }
  body { background: #f0f0f0; }
}
/* ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ── */
.ann-modal { position: fixed; inset: 0; background: var(--bg-secondary); z-index: 1500; display: none; flex-direction: column; }
.ann-modal.active { display: flex; animation: annSlideUp 0.3s cubic-bezier(.4,0,.2,1); }
@keyframes annSlideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ann-header { display: flex; align-items: center; gap: 10px; padding: 0 16px; height: 58px; background: var(--bg); border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.ann-header-icon { font-size: 20px; }
.ann-title { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; flex: 1; }
.ann-close { width: 34px; height: 34px; border-radius: 50%; border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s; }
.ann-close:active { background: var(--border); }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }
.ann-city { padding: 10px 14px; background: var(--bg); border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 9px 36px 9px 13px; border: 1.5px solid var(--border); border-radius: var(--radius-xs); font-size: 14px; font-weight: 600; color: var(--text); background: var(--bg-secondary); outline: none; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 11px center; font-family: 'Manrope', sans-serif; transition: border-color 0.15s; }
.city-select:focus { border-color: var(--primary); }
.ann-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 14px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 14px; }
.ann-loading .spinner { width: 32px; height: 32px; border-width: 3px; }
.ann-loading p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 15px; font-weight: 800; color: var(--text); letter-spacing: -0.3px; margin-bottom: 10px; padding-left: 2px; }
.ann-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
.ann-item { background: var(--bg); border-radius: var(--radius-xs); overflow: hidden; cursor: pointer; transition: transform 0.15s; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); position: relative; }
.ann-item:active { transform: scale(0.94); }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--bg-secondary); }
.ann-date { position: absolute; top: 5px; right: 5px; background: rgba(59,108,244,0.9); color: white; padding: 3px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 700; backdrop-filter: blur(4px); }
.ann-item-name { font-size: 11.5px; font-weight: 600; color: var(--text); padding: 7px 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }
.ann-add-card { background: var(--bg-secondary); border: 2px dashed var(--border); border-radius: var(--radius-xs); display: flex; flex-direction: column; align-items: center; justify-content: center; aspect-ratio: 1; cursor: pointer; transition: all 0.15s; gap: 5px; padding: 8px; }
.ann-add-card:active { border-color: var(--primary); background: var(--primary-light); transform: scale(0.95); }
.ann-add-card svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.ann-add-card span { font-size: 9.5px; color: var(--text-secondary); text-align: center; line-height: 1.3; font-weight: 600; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 50px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 64px; height: 64px; border-radius: 18px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
.ann-empty-icon svg { width: 30px; height: 30px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.ann-empty h3 { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }
.ann-empty p  { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.6; }
.ann-add-btn { display: inline-flex; align-items: center; gap: 7px; background: var(--primary); color: white; padding: 11px 22px; border-radius: var(--radius-xs); font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; font-family: inherit; box-shadow: 0 2px 10px rgba(59,108,244,0.28); transition: opacity 0.15s, transform 0.1s; }
.ann-add-btn:active { opacity: 0.85; transform: scale(0.97); }
.ann-add-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2.5; }
.ann-add-free { font-size: 11.5px; color: var(--text-light); font-weight: 500; }
/* ── SEARCH OVERLAY ── */
.search-overlay {
  position: fixed; inset: 0;
  background: var(--bg);
  z-index: 1100;
  display: flex; flex-direction: column;
  transform: translateY(-100%);
  transition: transform 0.28s cubic-bezier(.4,0,.2,1);
}
.search-overlay.active { transform: translateY(0); }
.so-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 14px; height: 58px; flex-shrink: 0;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg);
}
.so-back {
  width: 38px; height: 38px; border-radius: 12px;
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s;
}
.so-back:active { transform: scale(0.92); background: var(--border); }
.so-back svg { width: 18px; height: 18px; stroke: var(--text); fill: none; stroke-width: 2.2; }
.so-input-wrap {
  flex: 1; display: flex; align-items: center; gap: 10px;
  background: var(--bg-secondary); border: 1.5px solid var(--border);
  border-radius: 99px; padding: 9px 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.so-input-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,108,244,0.1); }
.so-input-wrap svg { width: 17px; height: 17px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.so-input {
  flex: 1; border: none; outline: none;
  font-size: 16px; font-weight: 500; color: var(--text);
  background: transparent; font-family: 'Manrope', sans-serif;
  -webkit-appearance: none; appearance: none; caret-color: var(--primary);
}
.so-input::placeholder { color: var(--text-light); }
.so-input::-webkit-search-decoration, .so-input::-webkit-search-cancel-button { display: none; }
.so-clear-btn {
  width: 24px; height: 24px; border-radius: 50%; border: none;
  background: var(--border); display: none; align-items: center;
  justify-content: center; cursor: pointer; flex-shrink: 0;
  transition: background 0.15s; padding: 0;
}
.so-clear-btn.visible { display: flex; }
.so-clear-btn svg { width: 11px; height: 11px; stroke: var(--text-secondary); fill: none; stroke-width: 3; }
.so-clear-btn:active { background: var(--primary); }
.so-clear-btn:active svg { stroke: white; }
.so-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
.so-section-label {
  font-size: 11px; font-weight: 700; color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px; padding: 16px 18px 8px;
}
.so-item {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 18px; cursor: pointer;
  transition: background 0.12s; border-bottom: 1px solid var(--border-light);
}
.so-item:last-of-type { border-bottom: none; }
.so-item:active { background: var(--bg-secondary); }
.so-item-body { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.so-item-text { font-size: 14px; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.so-item-sub  { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.so-item-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.so-item-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.so-item-icon.history { background: var(--bg-secondary); color: var(--text-light); }
.so-item-icon.suggest { background: var(--primary-light); color: var(--primary); }
.so-item-text .hl { color: var(--primary); font-weight: 700; }
.so-item-arrow { width: 16px; height: 16px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.so-clear-history {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  padding: 14px 18px; font-size: 13px; font-weight: 600;
  color: var(--text-secondary); cursor: pointer; transition: background 0.12s;
}
.so-clear-history:active { background: var(--bg-secondary); }
.so-clear-history svg { width: 15px; height: 15px; stroke: var(--text-secondary); fill: none; stroke-width: 2; }

/* ── ПОДПИСИ НАД МАРКЕРАМИ ── */
.mk-wrap { display: flex; flex-direction: column; align-items: center; cursor: pointer; }
.mk-label {
  background: #fff; border-radius: 6px; padding: 2px 7px;
  font-size: 10px; font-weight: 700; color: #0F172A; font-family: 'Manrope', sans-serif;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.18); margin-bottom: 3px; pointer-events: none;
}
/* ── НИЖНИЙ ПОПАП ── */
.map-bp {
  position: fixed; bottom: 0; left: 50%; transform: translateX(-50%) translateY(100%);
  width: 100%; max-width: 480px; background: #fff;
  border-radius: 20px 20px 0 0; box-shadow: 0 -4px 32px rgba(0,0,0,0.18);
  z-index: 1000; transition: transform 0.32s cubic-bezier(0.4,0,0.2,1); overflow: hidden;
}
.map-bp.active { transform: translateX(-50%) translateY(0); }
.mbp-drag { width: 36px; height: 4px; background: #DDE1E7; border-radius: 99px; margin: 10px auto 0; }
.mbp-content { padding: 12px 16px 20px; }
.mbp-row1 { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 12px; }
.mbp-photo-wrap {
  width: 72px; height: 72px; border-radius: 14px; overflow: hidden; flex-shrink: 0;
  background: #EEF2FF; display: flex; align-items: center; justify-content: center; font-size: 30px;
}
.mbp-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.mbp-meta { flex: 1; min-width: 0; }
.mbp-subcat { font-size: 11px; font-weight: 700; color: #3B6CF4; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.mbp-name { font-size: 16px; font-weight: 800; color: #0F172A; line-height: 1.25; margin-bottom: 4px; }
.mbp-addr { font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
.mbp-addr svg { width: 12px; height: 12px; flex-shrink: 0; stroke: #64748B; fill: none; stroke-width: 2; }
.mbp-rating-row { display: flex; align-items: center; gap: 5px; margin-top: 5px; }
.mbp-stars { color: #F59E0B; font-size: 13px; letter-spacing: 0.5px; }
.mbp-rating-val { font-size: 13px; font-weight: 700; color: #0F172A; }
.mbp-review-cnt { font-size: 12px; color: #94A3B8; }
.mbp-actions { display: flex; gap: 10px; align-items: center; margin-top: 12px; }
.mbp-icon-btn {
  width: 40px; height: 40px; border-radius: 50%; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  text-decoration: none; -webkit-tap-highlight-color: transparent;
  transition: opacity 0.15s, transform 0.1s;
}
.mbp-icon-btn:active { opacity: 0.75; transform: scale(0.9); }
.mbp-icon-btn svg { width: 18px; height: 18px; }
.mbp-btn-fav { background: #FFF1F2; color: #EF4444; }
.mbp-btn-fav svg { stroke: currentColor; fill: none; stroke-width: 2.2; }
.mbp-btn-fav.active { background: #EF4444; color: #fff; }
.mbp-btn-phone { background: #EEF2FF; color: #3B6CF4; }
.mbp-btn-phone svg { stroke: currentColor; fill: none; stroke-width: 2; }
.mbp-btn-wa { background: #E6F4EA; }
.mbp-btn-wa svg { fill: #1E8E3E; }
.mbp-btn-more {
  flex: 1; display: flex; align-items: center; justify-content: center;
  padding: 12px 16px; border-radius: 14px; font-size: 14px; font-weight: 700;
  font-family: 'Manrope', sans-serif; cursor: pointer; text-decoration: none; border: none;
  background: #3B6CF4; color: #fff; white-space: nowrap;
  transition: opacity 0.15s, transform 0.1s; -webkit-tap-highlight-color: transparent;
}
.mbp-btn-more:active { opacity: 0.85; transform: scale(0.97); }
.leaflet-control-attribution { z-index: 400 !important; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/menu.php'; ?>
<div class="map-container">
  <div class="map-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
            <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
          </svg>
        </button>
      </div>
      <div class="header-logo">
        <a href="/"><img src="/logo.png" alt="Poisq" onerror="this.style.display='none'"></a>
      </div>
      <div style="width:84px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">
        <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </a>
        <button class="btn-burger" id="menuToggle" aria-label="Меню">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
    <div class="header-search">
      <div class="search-bar" onclick="openSearchOverlay()">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        <span id="searchBarText" class="<?php echo $searchQuery ? 'has-query' : ''; ?>">
          <?php echo $searchQuery ? htmlspecialchars($searchQuery) : 'Поиск сервисов...'; ?>
        </span>
      </div>
    </div>
    <div class="filters-row">
      <div class="filter-chip" onclick="goToResults()">Все</div>
      <div class="filter-chip map-active">🗺 Карта</div>
      <div class="filter-chip <?php echo $verifiedFilter ? 'active' : ''; ?>" onclick="toggleChip('verified')"><span class="dot"></span> Проверено</div>
      <div class="filter-chip <?php echo $ratingFilter >= 4.5 ? 'active' : ''; ?>" onclick="toggleChip('rating')">★ 4.5+</div>
      <div class="filter-chip <?php echo !empty($languagesFilter) ? 'active' : ''; ?>" onclick="goToResults()">Языки<?php echo !empty($languagesFilter) ? ' (' . count($languagesFilter) . ')' : ''; ?></div>
      <div class="filter-chip" onclick="goToResults()">Фильтры ⚙</div>
    </div>
  </div>
  <div class="map-wrap">
    <div id="mapLoading" class="map-loading">
      <div class="spinner"></div>
      Загружаем сервисы...
    </div>
    <div id="map"></div>
    <div id="mapCount" class="map-count-badge" style="display:none"></div>
  </div>
</div>

<!-- ── НИЖНИЙ ПОПАП ── -->
<div id="mapBottomPopup" class="map-bp">
  <div class="mbp-drag"></div>
  <div id="mbpContent" class="mbp-content"></div>
</div>

<!-- ── SEARCH OVERLAY ── -->
<div class="search-overlay" id="searchOverlay">
  <div class="so-header">
    <button class="so-back" onclick="closeSearchOverlay()" aria-label="Назад">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </button>
    <div class="so-input-wrap">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="search" class="so-input" id="soInput"
             placeholder="Найти сервис на карте…"
             autocomplete="off" autocorrect="off"
             autocapitalize="off" spellcheck="false"
             inputmode="search">
      <button class="so-clear-btn" id="soClearBtn" aria-label="Очистить">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
  <div class="so-content" id="soContent"></div>
</div>

<!-- ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ── -->
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
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
const countryCode    = '<?php echo $countryCode; ?>';
const cityFilter     = <?php echo $cityFilter; ?>;
const citySlugVal    = '<?php echo $citySlug; ?>';
const categoryFilter = '<?php echo $categoryFilter; ?>';
let searchQuery      = '<?php echo addslashes($searchQuery); ?>';
const ratingFilter   = <?php echo $ratingFilter; ?>;
const verifiedFilter = <?php echo $verifiedFilter; ?>;
const languagesFilter = <?php echo json_encode($languagesFilter); ?>;
let focusId = <?php echo $focusId; ?>;

const CATEGORY_COLORS = {
  health:'#EF4444', legal:'#8B5CF6', family:'#F59E0B',
  education:'#3B82F6', business:'#1D4ED8', shops:'#10B981',
  home:'#F97316', transport:'#6B7280', events:'#EC4899',
  it:'#06B6D4', realestate:'#84CC16', default:'#3B6CF4'
};
const CATEGORY_NAMES = {
  health:'Здоровье и красота', legal:'Юридические', family:'Семья и дети',
  education:'Образование', business:'Бизнес', shops:'Магазины',
  home:'Дом и быт', transport:'Транспорт', events:'События',
  it:'IT услуги', realestate:'Недвижимость'
};
const CATEGORY_ICONS = {
  health:'🏥', legal:'⚖️', family:'👨‍👩‍👧', education:'📚',
  business:'💼', shops:'🛒', home:'🏠', transport:'🚗',
  events:'📷', it:'💻', realestate:'🏢'
};

function makeMarkerIcon(category, name, viewed) {
  const color = viewed ? '#94A3B8' : (CATEGORY_COLORS[category] || CATEGORY_COLORS.default);
  const emoji = CATEGORY_ICONS[category] || '📍';
  const rawLabel = name || '';
  const label = rawLabel.length > 18 ? rawLabel.slice(0, 17) + '…' : rawLabel;
  const pinSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">
    <filter id="sh"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="rgba(0,0,0,0.25)"/></filter>
    <path d="M18 0C9.163 0 2 7.163 2 16c0 11 16 28 16 28S34 27 34 16C34 7.163 26.837 0 18 0z" fill="${color}" filter="url(#sh)"/>
    <circle cx="18" cy="16" r="9" fill="white" fill-opacity="0.92"/>
    <text x="18" y="20" text-anchor="middle" font-size="10">${emoji}</text>
  </svg>`;
  const safeLabel = label.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  const labelHtml = safeLabel ? `<div class="mk-label">${safeLabel}</div>` : '';
  const totalH = safeLabel ? 67 : 44;
  const html = `<div class="mk-wrap">${labelHtml}${pinSvg}</div>`;
  return L.divIcon({ html, className: '', iconSize: [36, totalH], iconAnchor: [18, totalH], popupAnchor: [0, -(totalH + 2)] });
}

const map = L.map('map', { zoomControl: true, attributionControl: false });
L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(map);
L.control.attribution({ prefix: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/">CARTO</a>' }).addTo(map);

const markers = L.markerClusterGroup({
  maxClusterRadius: 50, spiderfyOnMaxZoom: true,
  showCoverageOnHover: false, zoomToBoundsOnClick: true,
  iconCreateFunction: function(cluster) {
    const count = cluster.getChildCount();
    return L.divIcon({
      html: `<div style="background:#3B6CF4;color:#fff;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;font-family:Manrope,sans-serif;box-shadow:0 2px 10px rgba(59,108,244,0.45);">${count}</div>`,
      className: '', iconSize: [38, 38]
    });
  }
});

let currentCityOverride = 0;
let markerById = new Map();

function buildApiUrl() {
  const params = new URLSearchParams();
  params.set('country', countryCode);
  const activeCityId = (typeof currentCityOverride !== 'undefined' && currentCityOverride > 0)
    ? currentCityOverride : cityFilter;
  if (activeCityId > 0) params.set('city_id', activeCityId);
  if (categoryFilter) params.set('category', categoryFilter);
  if (searchQuery && focusId === 0) params.set('q', searchQuery);
  if (ratingFilter > 0) params.set('rating', ratingFilter);
  if (verifiedFilter) params.set('verified', 1);
  if (focusId > 0) params.set('focus', focusId);
  return '/api_get-map-services.php?' + params.toString();
}

function buildPopup(s) {
  const emoji = CATEGORY_ICONS[s.category] || '📍';
  const catName = s.subcategory || CATEGORY_NAMES[s.category] || s.category;
  const photoHtml = s.photo
    ? `<img src="${s.photo}" alt="${s.name}" style="width:100%;height:130px;object-fit:cover;">`
    : `<div class="popup-photo-placeholder">${emoji}</div>`;
  const descHtml = s.description
    ? `<div class="popup-desc">${s.description.replace(/</g,'&lt;')}</div>` : '';
  const addrHtml = s.address ? `<div style="font-size:11px;color:#9AA0A6;margin-bottom:8px;display:flex;align-items:center;gap:4px;"><svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:#9AA0A6;stroke-width:2;fill:none;flex-shrink:0;"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 00-16 0c0 3 2.7 6 8 11.7z"/></svg>${s.address.replace(/</g,'&lt;')}</div>` : '';
  let contacts = '';
  if (s.phone) contacts += `<a href="tel:${s.phone}" class="popup-contact-btn phone"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 013.07 5.18 2 2 0 015.07 3h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L9.09 10.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>Позвонить</a>`;
  if (s.whatsapp) contacts += `<a href="https://wa.me/${s.whatsapp.replace(/\D/g,'')}" target="_blank" class="popup-contact-btn whatsapp"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>WhatsApp</a>`;
  return `<div class="popup-card">${photoHtml}<div class="popup-body"><div class="popup-category">${catName}</div><div class="popup-name">${s.name}</div>${descHtml}${addrHtml}${contacts ? `<div class="popup-contacts">${contacts}</div>` : ''}<a href="/service/${s.id}" class="popup-more">Подробнее →</a></div></div>`;
}

async function loadServices() {
  markers.clearLayers();
  markerById.clear();
  const noRes = document.querySelector('.map-no-results');
  if (noRes) noRes.remove();
  document.getElementById('mapCount').style.display = 'none';
  document.getElementById('mapLoading').style.display = 'flex';
  try {
    const res = await fetch(buildApiUrl());
    const data = await res.json();
    document.getElementById('mapLoading').style.display = 'none';
    if (!data.success || !data.services.length) {
      document.getElementById('map').insertAdjacentHTML('afterend', `<div class="map-no-results"><div style="font-size:36px">📍</div><p>Сервисы не найдены на карте</p></div>`);
      map.setView([48.8566, 2.3522], 5);
      return;
    }
    const bounds = [];
    data.services.forEach(s => {
      const lat = parseFloat(s.lat), lng = parseFloat(s.lng);
      if (!lat || !lng) return;
      const viewed = getViewed().has(String(s.id));
      const marker = L.marker([lat, lng], { icon: makeMarkerIcon(s.category, s.name, viewed) });
      marker.on('click', (function(svc){ return function(e){ showMbp(svc); }; })(s));
      markers.addLayer(marker);
      markerById.set(String(s.id), marker);
      bounds.push([lat, lng]);
    });
    map.addLayer(markers);
    const count = data.services.length;
    const badge = document.getElementById('mapCount');
    badge.textContent = count + (count === 1 ? ' сервис' : count < 5 ? ' сервиса' : ' сервисов') + ' на карте';
    badge.style.display = 'block';
    // focus на конкретный сервис
    if (focusId > 0) {
      const focusSvc = data.services.find(s => s.id == focusId);
      if (focusSvc && parseFloat(focusSvc.lat) && parseFloat(focusSvc.lng)) {
        map.setView([parseFloat(focusSvc.lat), parseFloat(focusSvc.lng)], 16);
        // открываем попап нужного маркера
        markers.eachLayer(function(layer) {
          const ll = layer.getLatLng();
          if (Math.abs(ll.lat - parseFloat(focusSvc.lat)) < 0.0001 &&
              Math.abs(ll.lng - parseFloat(focusSvc.lng)) < 0.0001) {
            setTimeout(() => showMbp(focusSvc), 600);
          }
        });
      } else {
        if (bounds.length === 1) map.setView(bounds[0], 15);
        else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
        else map.setView([48.8566, 2.3522], 5);
      }
    } else if (bounds.length === 1) map.setView(bounds[0], 15);
    else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    else map.setView([48.8566, 2.3522], 5);
  } catch(e) {
    document.getElementById('mapLoading').style.display = 'none';
    console.error(e);
  }
}
loadServices();

function buildResultsUrl() {
  let base = '/' + countryCode + '/';
  if (citySlugVal) base += citySlugVal + '/';
  if (searchQuery) base += encodeURIComponent(searchQuery);
  const params = new URLSearchParams();
  if (categoryFilter) params.set('category', categoryFilter);
  if (ratingFilter > 0) params.set('rating', ratingFilter);
  if (verifiedFilter) params.set('verified', 1);
  if (languagesFilter.length > 0) params.set('languages', languagesFilter.join(','));
  const qs = params.toString();
  return base + (qs ? '?' + qs : '');
}
function goToResults() { window.location.href = buildResultsUrl(); }
function toggleChip(type) {
  const params = new URLSearchParams();
  params.set('country', countryCode);
  if (cityFilter > 0) params.set('city_id', cityFilter);
  if (citySlugVal) params.set('city_slug', citySlugVal);
  if (searchQuery) params.set('q', searchQuery);
  if (categoryFilter) params.set('category', categoryFilter);
  if (type === 'rating') { if (!(ratingFilter >= 4.5)) params.set('rating', 4.5); }
  else if (ratingFilter > 0) params.set('rating', ratingFilter);
  if (type === 'verified') { if (!verifiedFilter) params.set('verified', 1); }
  else if (verifiedFilter) params.set('verified', 1);
  window.location.href = '/map.php?' + params.toString();
}

// ── SEARCH OVERLAY ──
const soInput    = document.getElementById('soInput');
const soClearBtn = document.getElementById('soClearBtn');
const soContent  = document.getElementById('soContent');
const searchOverlay = document.getElementById('searchOverlay');

const HISTORY_KEY = 'poisq_search_history';
const SO_POPULAR = [
  { emoji: '🩺', text: 'Врач' }, { emoji: '⚖️', text: 'Юрист' },
  { emoji: '📚', text: 'Репетитор' }, { emoji: '🌐', text: 'Переводчик' },
  { emoji: '🧠', text: 'Психолог' }, { emoji: '💅', text: 'Красота' },
  { emoji: '🦷', text: 'Стоматолог' }, { emoji: '📸', text: 'Фотограф' },
  { emoji: '💆', text: 'Массаж' }, { emoji: '📊', text: 'Бухгалтер' },
];

function soGetHistory() {
  try { return JSON.parse(localStorage.getItem(HISTORY_KEY)) || []; } catch { return []; }
}
function soSaveHistory(q) {
  if (!q.trim() || q.length < 2) return;
  let h = soGetHistory().filter(x => x !== q);
  h.unshift(q);
  localStorage.setItem(HISTORY_KEY, JSON.stringify(h.slice(0, 8)));
}
function soClearHistory() {
  localStorage.removeItem(HISTORY_KEY);
  renderSoContent('');
}

function openSearchOverlay() {
  history.pushState({ searchOpen: true }, '');
  searchOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  soInput.value = searchQuery;
  soClearBtn.classList.toggle('visible', soInput.value.length > 0);
  soInput.focus();
  renderSoContent(soInput.value);
}

function soHideKeyboard() {
  soInput.blur();
  document.activeElement && document.activeElement.blur();
  const tmp = document.createElement('input');
  tmp.setAttribute('type', 'text');
  tmp.setAttribute('readonly', 'readonly');
  tmp.style.cssText = 'position:fixed;top:-100px;left:-100px;width:1px;height:1px;opacity:0;';
  document.body.appendChild(tmp);
  tmp.focus(); tmp.blur();
  setTimeout(() => document.body.removeChild(tmp), 300);
}
function closeSearchOverlay() {
  soHideKeyboard();
  searchOverlay.classList.remove('active');
  document.body.style.overflow = '';
  soInput.value = '';
  soClearBtn.classList.remove('visible');
}

window.addEventListener('popstate', () => {
  if (searchOverlay.classList.contains('active')) closeSearchOverlay();
});

function soEscHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function soHlMatch(str, q) {
  const i = str.toLowerCase().indexOf(q.toLowerCase());
  if (i === -1) return soEscHtml(str);
  return soEscHtml(str.slice(0,i))
       + `<span class="hl">${soEscHtml(str.slice(i, i+q.length))}</span>`
       + soEscHtml(str.slice(i+q.length));
}

let soSuggestAbort = null;

async function renderSoContent(q) {
  const hist = soGetHistory();
  let html = '';
  if (!q) {
    if (hist.length) {
      html += `<div class="so-section-label">Недавние</div>`;
      html += hist.map(h => `
        <div class="so-item" onclick="soSearch('${soEscHtml(h)}')">
          <div class="so-item-icon history"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg></div>
          <span class="so-item-text">${soEscHtml(h)}</span>
          <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </div>`).join('');
      html += `<div class="so-clear-history" onclick="soClearHistory()">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Очистить историю</div>`;
    }
    soContent.innerHTML = html;
    return;
  }
  const matchHist = hist.filter(h => h.toLowerCase().includes(q.toLowerCase()));
  if (matchHist.length) {
    html += `<div class="so-section-label">Из истории</div>`;
    html += matchHist.slice(0, 3).map(h => `
      <div class="so-item" onclick="soSearch('${soEscHtml(h)}')">
        <div class="so-item-icon history"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg></div>
        <span class="so-item-text">${soHlMatch(h, q)}</span>
        <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
      </div>`).join('');
  }
  html += `<div id="so-live-results"><div style="padding:14px 18px;color:var(--text-light);font-size:13px;font-weight:500">Ищем подсказки…</div></div>`;
  soContent.innerHTML = html;
  if (soSuggestAbort) soSuggestAbort.abort();
  soSuggestAbort = new AbortController();
  try {
    const resp = await fetch(
      `/api/suggest.php?q=${encodeURIComponent(q)}&country=${encodeURIComponent(countryCode)}`,
      { signal: soSuggestAbort.signal }
    );
    const suggestions = await resp.json();
    const liveDiv = document.getElementById('so-live-results');
    if (!liveDiv) return;
    if (!suggestions || !suggestions.length) {
      liveDiv.innerHTML = `<div style="padding:16px 18px;color:var(--text-light);font-size:13px;font-weight:500">Ничего не найдено — попробуйте другой запрос</div>`;
      return;
    }
    let liveHtml = `<div class="so-section-label">Подсказки</div>`;
    suggestions.forEach((s, i) => {
      window._soSuggest = window._soSuggest || [];
      window._soSuggest[i] = s;
      const iconHtml = s.photo
        ? `<div class="so-item-icon suggest" style="background:none;padding:0;overflow:hidden;border-radius:8px;"><img src="${soEscHtml(s.photo)}" style="width:36px;height:36px;object-fit:cover;border-radius:8px;" onerror="this.style.display='none'"></div>`
        : `<div class="so-item-icon suggest"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>`;
      const subtitleParts = [];
      if (s.subtitle) subtitleParts.push(soEscHtml(s.subtitle));
      if (s.rating)   subtitleParts.push('⭐ ' + s.rating);
      const subtitleHtml = subtitleParts.length ? `<span class="so-item-sub">${subtitleParts.join(' · ')}</span>` : '';
      liveHtml += `
        <div class="so-item" onclick="soGoTo(${i})">
          ${iconHtml}
          <div class="so-item-body">
            <span class="so-item-text">${soHlMatch(soEscHtml(s.text), q)}</span>
            ${subtitleHtml}
          </div>
          <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </div>`;
    });
    liveDiv.innerHTML = liveHtml;
  } catch(e) {
    if (e.name === 'AbortError') return;
    const liveDiv = document.getElementById('so-live-results');
    if (liveDiv) liveDiv.innerHTML = '';
  }
}

function soSanitizeQuery(q) {
  return q.replace(/\//g, ' ').replace(/\s+/g, ' ').trim();
}

const CITY_HINTS = {};

async function loadCityHints() {
  try {
    const r = await fetch('/api/get-cities.php?country=' + countryCode);
    const cities = await r.json();
    cities.forEach(c => {
      if (c.name) CITY_HINTS[c.name.toLowerCase()] = c.id;
      if (c.name_lat) CITY_HINTS[c.name_lat.toLowerCase()] = c.id;
    });
  } catch(e) {}
}
loadCityHints();

function detectCityFromQuery(q) {
  const lower = q.toLowerCase();
  for (const [name, id] of Object.entries(CITY_HINTS)) {
    if (lower.includes(name)) return id;
  }
  return 0;
}

function stripCityFromQuery(q) {
  const lower = q.toLowerCase();
  for (const name of Object.keys(CITY_HINTS)) {
    if (lower.includes(name)) {
      return q.replace(new RegExp(name, 'i'), '').replace(/\s+/g, ' ').trim();
    }
  }
  return q;
}

function soUpdateMap(q) {
  focusId = 0;
  const detectedCity = detectCityFromQuery(q);
  currentCityOverride = detectedCity;
  const cleanQ = detectedCity ? stripCityFromQuery(q) : q;
  searchQuery = cleanQ;

  const params = new URLSearchParams(window.location.search);
  if (cleanQ) params.set('q', cleanQ); else params.delete('q');
  if (detectedCity) params.set('city_id', detectedCity); else params.delete('city_id');
  history.replaceState({}, '', '/map.php?' + params.toString());

  const span = document.getElementById('searchBarText');
  if (span) {
    span.textContent = q || 'Поиск сервисов...';
    span.className = q ? 'has-query' : '';
  }
  loadServices();
}

function soSearch(q) {
  if (!q.trim()) return;
  const clean = soSanitizeQuery(q);
  soSaveHistory(clean);
  closeSearchOverlay();
  setTimeout(() => soUpdateMap(clean), 150);
}

function soGoTo(i) {
  const s = (window._soSuggest || [])[i];
  if (!s) return;
  if (s.q) {
    const clean = soSanitizeQuery(s.q);
    soSaveHistory(clean);
    closeSearchOverlay();
    setTimeout(() => soUpdateMap(clean), 150);
  } else {
    closeSearchOverlay();
    setTimeout(() => { window.location.href = s.url; }, 100);
  }
}

let soTimer = null;
soInput.addEventListener('input', () => {
  const q = soInput.value.trim();
  soClearBtn.classList.toggle('visible', q.length > 0);
  clearTimeout(soTimer);
  soTimer = setTimeout(() => renderSoContent(q), 300);
});
soInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); soSearch(soInput.value.trim()); }
});
soClearBtn.addEventListener('click', () => {
  soInput.value = '';
  soClearBtn.classList.remove('visible');
  soInput.focus();
  renderSoContent('');
});

// ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ──
let annCityId = null;

async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;
  try {
    const cr    = await fetch('/api/get-user-country.php');
    const cd    = await cr.json();
    const cc    = cd.country_code || 'fr';
    const cir   = await fetch(`/api/get-cities.php?country=${cc}`);
    const cities = await cir.json();
    const sel   = document.getElementById('citySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name + (c.is_capital == 1 ? ' (столица)' : '');
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch {
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение и попробуйте снова.');
  }
}

function closeAnnModal() {
  document.getElementById('annModal').classList.remove('active');
  document.body.style.overflow = '';
}

async function filterByCity() {
  annCityId = document.getElementById('citySelect').value;
  await loadAnnServices(annCityId);
}

async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;
  try {
    const r  = await fetch(`/api/get-services.php?city_id=${cityId}&days=5`);
    const d  = await r.json();
    const sv = d.services || [];
    if (!sv.length) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
          <h3>Пока нет сервисов</h3>
          <p>В этом городе нет новых сервисов<br>за последние 5 дней</p>
          <button class="ann-add-btn" onclick="annGoAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Добавить сервис
          </button>
          <span class="ann-add-free">бесплатно</span>
        </div>`;
      return;
    }
    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += `<div class="ann-category">
        <div class="ann-cat-title">${cat}</div>
        <div class="ann-grid">
          ${list.map(s => {
            let photo = 'https://via.placeholder.com/200?text=Poisq';
            if (s.photo) {
              try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; }
              catch { photo = s.photo; }
            }
            return `
            <div class="ann-item" onclick="location.href='/service.php?id=${s.id}'">
              <img src="${photo}" alt="${s.name}" loading="lazy" onerror="this.src='https://via.placeholder.com/200?text=Poisq'">
              <div class="ann-date">${annFmtDate(s.created_at)}</div>
              <div class="ann-item-name">${s.name}</div>
            </div>`;
          }).join('')}
          <div class="ann-add-card" onclick="annGoAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Добавить свой сервис</span>
          </div>
        </div>
      </div>`;
    }
    content.innerHTML = html;
  } catch {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}

function annGoAdd() {
  <?php if ($isLoggedIn): ?>
  location.href = '/add-service.php';
  <?php else: ?>
  location.href = '/register.php';
  <?php endif; ?>
}

function annErr(t, p) {
  return `<div class="ann-empty">
    <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <h3>${t}</h3><p>${p}</p>
  </div>`;
}

function annFmtDate(ds) {
  const d = new Date(ds), now = new Date();
  const diff = Math.floor((now - d) / 86400000);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Вчера';
  if (diff < 5)  return diff + ' дн.';
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}

// ── ПРОСМОТРЕННЫЕ МАРКЕРЫ ──
const VIEWED_KEY = 'poisq_viewed_map';
function getViewed() {
  try { return new Set(JSON.parse(localStorage.getItem(VIEWED_KEY)) || []); } catch { return new Set(); }
}
function addViewed(id) {
  const s = getViewed(); s.add(String(id));
  localStorage.setItem(VIEWED_KEY, JSON.stringify([...s]));
}

// ── НИЖНИЙ ПОПАП ──
let _mbpIsOpening = false;

function escH(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showMbp(s) {
  _mbpIsOpening = true;
  setTimeout(() => { _mbpIsOpening = false; }, 150);
  addViewed(s.id);
  const _vm = markerById.get(String(s.id));
  if (_vm) _vm.setIcon(makeMarkerIcon(s.category, s.name, true));

  const emoji = CATEGORY_ICONS[s.category] || '📍';
  const catName = s.subcategory || CATEGORY_NAMES[s.category] || s.category;
  const photoInner = s.photo ? `<img src="${escH(s.photo)}" alt="">` : emoji;

  const addrHtml = s.address
    ? `<div class="mbp-addr"><svg viewBox="0 0 24 24"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 00-16 0c0 3 2.7 6 8 11.7z"/></svg>${escH(s.address)}</div>` : '';

  let ratingHtml = '';
  if (parseFloat(s.rating) > 0) {
    const r = parseFloat(s.rating);
    const full = Math.round(r);
    const stars = '★'.repeat(Math.min(full,5)) + '☆'.repeat(Math.max(0, 5-full));
    ratingHtml = `<div class="mbp-rating-row"><span class="mbp-stars">${stars}</span><span class="mbp-rating-val">${r.toFixed(1)}</span>${parseInt(s.reviews_count) > 0 ? `<span class="mbp-review-cnt">(${s.reviews_count})</span>` : ''}</div>`;
  }

  let btns = `<button class="mbp-icon-btn mbp-btn-fav" id="mbpFavBtn" onclick="mbpToggleFav(${parseInt(s.id)})"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>`;

  if (s.phone) {
    btns += `<a href="tel:${escH(s.phone)}" class="mbp-icon-btn mbp-btn-phone"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 013.07 5.18 2 2 0 015.07 3h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L9.09 10.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg></a>`;
  }

  if (s.whatsapp) {
    const waNum = String(s.whatsapp).replace(/\D/g,'');
    btns += `<a href="https://wa.me/${escH(waNum)}" target="_blank" rel="noopener" class="mbp-icon-btn mbp-btn-wa"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>`;
  }

  btns += `<a href="/service/${parseInt(s.id)}" class="mbp-btn-more" onclick="mbpSaveBackUrl()">Подробнее →</a>`;

  document.getElementById('mbpContent').innerHTML = `
    <div class="mbp-row1">
      <div class="mbp-photo-wrap">${photoInner}</div>
      <div class="mbp-meta">
        <div class="mbp-subcat">${escH(catName)}</div>
        <div class="mbp-name">${escH(s.name)}</div>
        ${addrHtml}
        ${ratingHtml}
      </div>
    </div>
    <div class="mbp-actions">${btns}</div>`;

  document.getElementById('mapBottomPopup').classList.add('active');
}

function closeMbp() {
  document.getElementById('mapBottomPopup').classList.remove('active');
}

function mbpSaveBackUrl() {
  const u = new URL(window.location.href);
  u.searchParams.delete('focus');
  sessionStorage.setItem('mapBackUrl', u.toString());
}

async function mbpToggleFav(id) {
  const btn = document.getElementById('mbpFavBtn');
  if (!btn) return;
  try {
    const fd = new FormData();
    fd.append('service_id', id);
    const res = await fetch('/api/favorites.php', { method: 'POST', body: fd });
    const d = await res.json();
    if (d.success) btn.classList.toggle('active', d.action === 'added');
  } catch(e) {}
}

map.on('click', function() {
  if (_mbpIsOpening) return;
  if (document.getElementById('mapBottomPopup').classList.contains('active')) closeMbp();
});
</script>
</body>
</html>
