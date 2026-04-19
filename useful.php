<?php
// useful.php — Полезное: список статей (редизайн Claude Design, апрель 2026)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
$isLoggedIn  = isset($_SESSION['user_id']);
if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo_lv = getDbConnection();
        $pdo_lv->prepare("UPDATE users SET last_visit = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}
}
$userName    = $isLoggedIn ? ($_SESSION['user_name']   ?? '') : '';
$userAvatar  = $isLoggedIn ? ($_SESSION['user_avatar'] ?? '') : '';
$userInitial = $userName ? strtoupper(mb_substr($userName, 0, 1, 'UTF-8')) : '';

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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Полезное — Poisq</title>
<meta name="description" content="Статьи и гайды для русскоязычных за рубежом: документы, финансы, здоровье, семья.">
<link rel="canonical" href="https://poisq.com/useful.php">
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,700;0,800;1,400;1,500&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
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
  --warning: #F59E0B;
  --danger: #EF4444;
  --radius-sm: 12px;
  --radius-xs: 10px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
  --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
  --serif: 'Playfair Display', Georgia, serif;
  --mono: 'JetBrains Mono', ui-monospace, monospace;
  /* редакционные токены */
  --ink:   #0F172A;
  --muted: #64748B;
  --body:  #334155;
  --hair:  rgba(0,0,0,0.07);
  --accent: #3B6CF4;
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; background: var(--bg-secondary); color: var(--text); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }

/* ── HEADER (из help.php) ── */
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; padding: 10px 14px; height: 56px; gap: 10px; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.header-actions { width: 84px; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.btn-grid { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s; }
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }
.btn-add { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s, transform 0.1s; text-decoration: none; flex-shrink: 0; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── MASTHEAD редакционный ── */
.masthead { padding: 28px 20px 20px; background: var(--bg); }
.masthead-overline { font-family: var(--mono); font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--text-light); margin-bottom: 10px; }
.masthead h1 { font-size: 28px; font-weight: 800; line-height: 1.1; letter-spacing: -0.5px; color: var(--text); margin-bottom: 10px; }
.masthead-sub { font-size: 14px; line-height: 1.5; color: var(--text-secondary); margin-bottom: 16px; }
.country-inline { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding: 6px 0; }
.country-inline-label { color: var(--text-light); }
.country-inline-value { color: var(--text); font-weight: 600; border-bottom: 1px solid var(--text); padding-bottom: 1px; }
.country-inline:active .country-inline-value { opacity: 0.6; }

/* ── TABS ── */
.search-bar { display: flex; align-items: center; gap: 10px; margin: 0 16px 16px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px; background: var(--bg-secondary); transition: border-color 0.15s; }
.search-bar:focus-within { border-color: var(--primary); background: var(--bg); }
.search-bar input { flex: 1; border: none; background: none; outline: none; font-size: 14px; color: var(--text); }
.search-bar input::placeholder { color: var(--text-light); }
.tabs-row { display: flex; gap: 0; padding: 0 16px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; border-bottom: 1px solid var(--border-light); touch-action: pan-x; }
.tabs-row::-webkit-scrollbar { display: none; }
.tab-item { flex-shrink: 0; padding: 12px 14px; position: relative; font-size: 13px; font-weight: 500; color: var(--text-secondary); cursor: pointer; white-space: nowrap; background: none; border: none; transition: color 0.15s; }
.tab-item.active { font-weight: 700; color: var(--primary); }
.tab-item.active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: var(--primary); }

/* ── SECTION DIVIDER ── */
.section-divider { display: flex; align-items: center; gap: 14px; padding: 24px 20px 8px; }
.divider-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-light); white-space: nowrap; }
.divider-line { flex: 1; height: 1px; background: var(--border); }

/* ── LEAD STORY ── */
.lead-story { padding: 20px 16px; cursor: pointer; text-decoration: none; color: inherit; display: block; transition: opacity 0.12s; }
.lead-story:active { opacity: 0.8; }
.lead-badge { font-family: var(--mono); font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: var(--primary); margin-bottom: 12px; }
.lead-cover { width: 100%; height: 200px; object-fit: cover; display: block; border-radius: var(--radius-sm); }
.lead-cover-placeholder { width: 100%; height: 200px; background: var(--bg-secondary); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 13px; }
.lead-meta { display: flex; align-items: center; gap: 8px; margin: 14px 0 8px; }
.lead-category { font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: var(--primary); }
.lead-read-time { font-size: 11px; color: var(--text-light); }
.lead-title { font-size: 20px; font-weight: 800; line-height: 1.2; letter-spacing: -0.3px; color: var(--text); margin-bottom: 10px; }
.lead-excerpt { font-size: 14px; line-height: 1.55; color: var(--text-secondary); margin-bottom: 12px; }
.lead-author { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-light); }
.author-avatar { width: 22px; height: 22px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: var(--text-secondary); flex-shrink: 0; overflow: hidden; }
.author-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── STORY ROW ── */
.story-row { display: flex; gap: 12px; align-items: flex-start; padding: 16px; border-top: 1px solid var(--border-light); cursor: pointer; text-decoration: none; color: inherit; transition: background 0.12s; }
.story-row:active { background: var(--bg-secondary); }
.story-text { flex: 1; min-width: 0; }
.story-category { font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: var(--primary); margin-bottom: 4px; display: block; }
.story-title { font-size: 15px; font-weight: 700; line-height: 1.3; color: var(--text); margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.story-excerpt { font-size: 12px; line-height: 1.5; color: var(--text-secondary); margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.story-meta-row { font-size: 11px; color: var(--text-light); display: flex; align-items: center; gap: 5px; }
.story-thumb { width: 80px; height: 80px; flex-shrink: 0; object-fit: cover; display: block; border-radius: var(--radius-xs); background: var(--bg-secondary); }
.story-thumb-placeholder { width: 80px; height: 80px; flex-shrink: 0; border-radius: var(--radius-xs); background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; }

/* ── СКЕЛЕТОН ── */
.skel-block { background: var(--border-light); border-radius: 6px; animation: shimmer 1.4s infinite; }
@keyframes shimmer { 0%,100%{opacity:1} 50%{opacity:0.4} }
.skeleton-lead { padding: 20px 16px; }
.skeleton-row { padding: 16px; border-top: 1px solid var(--border-light); display: flex; gap: 12px; }

/* ── ПОИСК найдено ── */
.search-found { padding: 12px 16px 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-light); }

/* ── ПУСТОЕ СОСТОЯНИЕ ── */
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 60px 20px; text-align: center; gap: 10px; }
.empty-icon { font-size: 40px; margin-bottom: 4px; }
.empty-title { font-size: 18px; font-weight: 800; color: var(--text); }
.empty-sub { font-size: 14px; color: var(--text-secondary); line-height: 1.5; max-width: 260px; }
.empty-reset { margin-top: 6px; font-size: 13px; font-weight: 700; color: var(--primary); border: none; background: none; cursor: pointer; text-decoration: underline; }

/* ── FOOTER ── */
.page-footer { padding: 16px 16px 32px; border-top: 1px solid var(--border-light); display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }

/* ── МОДАЛКА СТРАНЫ ── */
.country-modal { position: fixed; inset: 0; background: rgba(15,23,42,0.4); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); z-index: 600; display: none; align-items: flex-start; justify-content: center; }
.country-modal.active { display: flex; }
.country-sheet { width: 100%; max-width: 430px; background: var(--bg); border-radius: 0 0 16px 16px; padding: 0; max-height: 90vh; display: flex; flex-direction: column; animation: slideDown 0.25s cubic-bezier(.4,0,.2,1); }
@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
.sheet-handle { display: none; }
.sheet-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.sheet-title { font-size: 17px; font-weight: 800; color: var(--text); }
.sheet-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; color: var(--text-light); transition: background 0.15s; flex-shrink: 0; }
.sheet-close:active { background: var(--border); }
.sheet-search-wrap { padding: 0 16px 12px; flex-shrink: 0; }
.sheet-search-inner { display: flex; align-items: center; gap: 10px; border: 1.5px solid var(--border); border-radius: 12px; padding: 10px 14px; background: var(--bg-secondary); }
.sheet-search-inner:focus-within { border-color: var(--primary); }
.sheet-search-inner input { flex: 1; border: none; background: none; outline: none; font-size: 14px; color: var(--text); }
.sheet-search-inner input::placeholder { color: var(--text-light); }
.country-list { overflow-y: auto; flex: 1; padding: 0 0 20px; }
.country-item { display: flex; align-items: center; gap: 12px; padding: 11px 16px; cursor: pointer; border: none; background: none; width: 100%; text-align: left; transition: background 0.1s; }
.country-item:active { background: var(--bg-secondary); }
.country-item.selected .country-item-name { font-weight: 700; color: var(--primary); }
.country-flag-sm { width: 28px; height: 20px; border-radius: 3px; overflow: hidden; flex-shrink: 0; }
.country-flag-sm img { width: 100%; height: 100%; object-fit: cover; display: block; }
.country-item-name { font-size: 15px; color: var(--text); flex: 1; }
.country-item-check { color: var(--primary); flex-shrink: 0; }

/* ── ANN MODAL (из help.php) ── */
.ann-modal { position: fixed; inset: 0; z-index: 500; background: var(--bg); transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; max-width: 430px; margin: 0 auto; }
.ann-modal.active { transform: translateY(0); }
.ann-header { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.ann-header-icon { font-size: 20px; }
.ann-title { flex: 1; font-size: 17px; font-weight: 800; color: var(--text); }
.ann-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.ann-city { padding: 12px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid var(--border); font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; font-weight: 600; background: var(--bg-secondary); color: var(--text); outline: none; appearance: none; }
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

@media (min-width: 1024px) {
  .app-container { max-width: 720px; padding-top: 64px; }
  .page-header { display: none; }
}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/desktop.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">

  <!-- HEADER (точно как help.php) -->
  <div class="page-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24">
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

  <!-- MASTHEAD -->
  <div class="masthead">
    <div class="masthead-overline">Раздел · Полезное</div>
    <h1>Полезное</h1>
    <p class="masthead-sub">Гайды, советы и наблюдения для русскоязычных по всему миру</p>
    <div class="country-inline" onclick="openCountryModal()">
      <span class="country-inline-label">Страна:</span>
      <span class="country-inline-value" id="inlineCountryName">Франция</span>
      <svg width="10" height="6" viewBox="0 0 10 6" fill="none">
        <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
  </div>

  <!-- TABS -->
  <div class="search-bar">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
      <circle cx="6" cy="6" r="4.5" stroke="#94A3B8" stroke-width="1.3"/>
      <path d="M9.5 9.5l3 3" stroke="#94A3B8" stroke-width="1.3" stroke-linecap="round"/>
    </svg>
    <input type="text" id="searchInput" placeholder="Поиск по статьям" autocomplete="off">
  </div>
  <div class="tabs-row" id="tabsRow">
    <button class="tab-item active" data-cat="all">Все</button>
    <?php
    try {
        if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
        $cats = $pdo->query("SELECT DISTINCT category FROM articles WHERE status='published' AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cats as $cat) {
            echo '<button class="tab-item" data-cat="'.htmlspecialchars($cat).'">'
                .htmlspecialchars($cat).'</button>';
        }
    } catch (Exception $e) {}
    ?>
  </div>

  <!-- КОНТЕНТ -->
  <div id="mainContent" style="flex:1">
    <div id="skeletonWrap">
      <div class="skeleton-lead">
        <div class="skel-block" style="width:100px;height:10px;margin-bottom:12px"></div>
        <div class="skel-block" style="width:100%;height:200px;border-radius:12px;margin-bottom:14px"></div>
        <div class="skel-block" style="width:60%;height:12px;margin-bottom:8px"></div>
        <div class="skel-block" style="width:85%;height:26px;margin-bottom:8px"></div>
        <div class="skel-block" style="width:100%;height:14px;margin-bottom:6px"></div>
        <div class="skel-block" style="width:80%;height:14px"></div>
      </div>
      <?php for($i=0;$i<3;$i++): ?>
      <div class="skeleton-row">
        <div style="flex:1;display:flex;flex-direction:column;gap:8px">
          <div class="skel-block" style="width:60px;height:10px"></div>
          <div class="skel-block" style="width:90%;height:16px"></div>
          <div class="skel-block" style="width:100%;height:12px"></div>
          <div class="skel-block" style="width:70%;height:12px"></div>
        </div>
        <div class="skel-block" style="width:80px;height:80px;border-radius:10px;flex-shrink:0"></div>
      </div>
      <?php endfor; ?>
    </div>
    <div id="articlesWrap" style="display:none"></div>
  </div>

  <!-- FOOTER -->
  <div class="page-footer">
    <a href="/useful.php" class="footer-link active">Полезное</a>
    <a href="/help.php" class="footer-link">Помощь</a>
    <a href="/terms.php" class="footer-link">Условия</a>
    <a href="/about.php" class="footer-link">О нас</a>
    <a href="/contact.php" class="footer-link">Контакт</a>
  </div>

</div><!-- /app-container -->

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- COUNTRY MODAL -->
<div class="country-modal" id="countryModal" onclick="onModalOverlay(event)">
  <div class="country-sheet" onclick="event.stopPropagation()">
    <div class="sheet-handle"></div>
    <div class="sheet-header"><span class="sheet-title">Выберите страну</span><button class="sheet-close" onclick="closeCountryModal()">✕</button></div>
    <div class="sheet-search-wrap">
      <div class="sheet-search-inner">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
          <circle cx="6" cy="6" r="4.5" stroke="#94A3B8" stroke-width="1.3"/>
          <path d="M9.5 9.5l3 3" stroke="#94A3B8" stroke-width="1.3" stroke-linecap="round"/>
        </svg>
        <input type="text" id="countrySearch" placeholder="Поиск страны" autocomplete="off">
      </div>
    </div>
    <div class="country-list" id="countryList"></div>
  </div>
</div>

<!-- ANN MODAL (точно как help.php) -->
<div class="ann-modal" id="annModal">
  <div class="ann-header">
    <span class="ann-header-icon">📢</span>
    <span class="ann-title">Свежие сервисы</span>
    <button class="ann-close" onclick="closeAnnModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="ann-city">
    <select id="annCitySelect" class="city-select" onchange="filterByCity()">
      <option>Загрузка...</option>
    </select>
  </div>
  <div class="ann-content" id="annContent">
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div>
  </div>
</div>

<script>
// ── ДАННЫЕ ──
const COUNTRIES_LIST = <?php
$_cl = [];
try {
    if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
    foreach ($pdo->query("SELECT code, name_ru FROM countries WHERE is_active=1 ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC) as $_r) {
        $_cl[] = ['code' => $_r['code'], 'name' => $_r['name_ru']];
    }
} catch (Exception $_e) {}
if (empty($_cl)) {
    $_cl = [
        ['code'=>'ae','name'=>'ОАЭ'],['code'=>'ar','name'=>'Аргентина'],['code'=>'au','name'=>'Австралия'],
        ['code'=>'at','name'=>'Австрия'],['code'=>'be','name'=>'Бельгия'],['code'=>'br','name'=>'Бразилия'],
        ['code'=>'by','name'=>'Беларусь'],['code'=>'ca','name'=>'Канада'],['code'=>'ch','name'=>'Швейцария'],
        ['code'=>'cl','name'=>'Чили'],['code'=>'co','name'=>'Колумбия'],['code'=>'cz','name'=>'Чехия'],
        ['code'=>'de','name'=>'Германия'],['code'=>'dk','name'=>'Дания'],['code'=>'es','name'=>'Испания'],
        ['code'=>'fi','name'=>'Финляндия'],['code'=>'fr','name'=>'Франция'],['code'=>'gb','name'=>'Великобритания'],
        ['code'=>'gr','name'=>'Греция'],['code'=>'hk','name'=>'Гонконг'],['code'=>'ie','name'=>'Ирландия'],
        ['code'=>'il','name'=>'Израиль'],['code'=>'it','name'=>'Италия'],['code'=>'jp','name'=>'Япония'],
        ['code'=>'kr','name'=>'Южная Корея'],['code'=>'kz','name'=>'Казахстан'],['code'=>'mx','name'=>'Мексика'],
        ['code'=>'nl','name'=>'Нидерланды'],['code'=>'no','name'=>'Норвегия'],['code'=>'nz','name'=>'Новая Зеландия'],
        ['code'=>'pl','name'=>'Польша'],['code'=>'pt','name'=>'Португалия'],['code'=>'ru','name'=>'Россия'],
        ['code'=>'se','name'=>'Швеция'],['code'=>'sg','name'=>'Сингапур'],['code'=>'th','name'=>'Таиланд'],
        ['code'=>'tr','name'=>'Турция'],['code'=>'ua','name'=>'Украина'],['code'=>'us','name'=>'США'],['code'=>'za','name'=>'ЮАР'],
    ];
}
echo json_encode($_cl, JSON_UNESCAPED_UNICODE);
?>;

const CAT_LABELS = {all:'Все',health:'Здоровье',finance:'Финансы',housing:'Жильё',bureaucracy:'Бюрократия',culture:'Культура',education:'Образование',work:'Работа',children:'Дети'};

let currentCode = localStorage.getItem('poisq_country') || 'fr';
let currentName = localStorage.getItem('poisq_country_name') || 'Франция';
let currentCat  = 'all';
let allArticles = [];
let searchTimer = null;

// ── ПОИСК ──
document.getElementById('searchInput').addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q = this.value.trim().toLowerCase();
  searchTimer = setTimeout(() => {
    if (!q) { renderArticles(allArticles, currentCat); return; }
    const filtered = allArticles.filter(a =>
      (a.title||'').toLowerCase().includes(q) ||
      (a.excerpt||'').toLowerCase().includes(q)
    );
    renderSearchResults(filtered, q);
  }, 200);
});

document.getElementById('inlineCountryName').textContent = currentName;

// ── ТАБЫ ──
document.getElementById('tabsRow').addEventListener('click', function(e) {
  const btn = e.target.closest('.tab-item');
  if (!btn) return;
  document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  currentCat = btn.dataset.cat;
  renderArticles(allArticles, currentCat);
  btn.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
});

// ── ЗАГРУЗКА СТАТЕЙ ──
async function loadArticles(countryCode) {
  document.getElementById('skeletonWrap').style.display = 'block';
  document.getElementById('articlesWrap').style.display = 'none';
  try {
    const resp = await fetch('/api/get-articles.php?country=' + encodeURIComponent(countryCode));
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const data = await resp.json();
    allArticles = Array.isArray(data) ? data : (data.articles || data || []);
  } catch(e) { allArticles = []; }
  document.getElementById('skeletonWrap').style.display = 'none';
  document.getElementById('articlesWrap').style.display = 'block';
  renderArticles(allArticles, currentCat);
}

// ── РЕНДЕР ──
function renderSearchResults(articles, q) {
  const wrap = document.getElementById('articlesWrap');
  if (!articles.length) {
    wrap.innerHTML = '<div class="empty-state"><div class="empty-icon">🔍</div><div class="empty-title">Ничего не найдено</div><div class="empty-sub">Попробуйте другой запрос</div></div>';
    return;
  }
  wrap.innerHTML = '<div class="search-found">Найдено: ' + articles.length + '</div>' + articles.map((a,i) => renderRow(a, i===0)).join('');
}

function renderArticles(articles, cat) {
  const filtered = cat === 'all' ? articles : articles.filter(a => (a.category||'') === cat);
  const wrap = document.getElementById('articlesWrap');
  if (!filtered.length) { wrap.innerHTML = renderEmpty(cat); return; }
  const lead = filtered[0];
  const rest  = filtered.slice(1);
  let html = renderLead(lead);
  if (rest.length) {
    html += '<div class="section-divider"><span class="divider-label">Читают сейчас</span><div class="divider-line"></div></div>';
    html += rest.map((a,i) => renderRow(a, i===0)).join('');
  }
  wrap.innerHTML = html;
}

function renderLead(a) {
  const url  = '/article/' + (currentCode||'all') + '/' + (a.slug||a.id);
  const cat  = CAT_LABELS[a.category] || a.category || '';
  const time = a.read_time || '5 мин';
  const date = a.date || formatDate(a.created_at);
  const cover = a.photo
    ? '<img class="lead-cover" src="'+esc(a.photo)+'" alt="'+esc(a.title)+'" loading="eager">'
    : '<div class="lead-cover-placeholder">📰</div>';
  return '<a class="lead-story" href="'+url+'">'+
    '<div class="lead-badge">◆ Материал раздела</div>'+
    cover+
    '<div class="lead-meta"><span class="lead-category">'+esc(cat)+'</span><span class="lead-read-time">· '+esc(time)+' чтения</span>'+(a.likes>0?'<span class="lead-read-time">· 👍 '+a.likes+'</span>':'')+'</div>'+
    '<h2 class="lead-title">'+esc(a.title)+'</h2>'+
    (a.excerpt ? '<p class="lead-excerpt">'+esc(a.excerpt)+'</p>' : '')+
    '<div class="lead-author">'+(a.author ? '<div class="author-avatar">'+esc(a.author.charAt(0).toUpperCase())+'</div><span>'+esc(a.author)+'</span><span>·</span>' : '')+'<span>'+date+'</span></div>'+
    '</a>';
}

function renderRow(a, first) {
  const url  = '/article/' + (currentCode||'all') + '/' + (a.slug||a.id);
  const cat  = CAT_LABELS[a.category] || a.category || '';
  const time = a.read_time || '5 мин';
  const date = a.date || formatDate(a.created_at);
  const thumb = a.photo
    ? '<img class="story-thumb" src="'+esc(a.photo)+'" alt="'+esc(a.title)+'" loading="lazy">'
    : '<div class="story-thumb-placeholder">📰</div>';
  return '<a class="story-row" href="'+url+'"'+(first?' style="border-top:none"':'')+ '>'+
    '<div class="story-text">'+
    '<span class="story-category">'+esc(cat)+'</span>'+
    '<div class="story-title">'+esc(a.title)+'</div>'+
    (a.excerpt ? '<div class="story-excerpt">'+esc(a.excerpt)+'</div>' : '')+
    '<div class="story-meta-row">'+(a.author?'<span>'+esc(a.author)+'</span><span>·</span>':'')+' <span>'+date+'</span><span>·</span><span>'+esc(time)+' чтения</span>'+(a.likes>0?'<span>·</span><span>👍 '+a.likes+'</span>':'')+'</div>'+
    '</div>'+thumb+'</a>';
}

function renderEmpty(cat) {
  const label = cat==='all' ? 'в этой стране' : 'в рубрике «'+(CAT_LABELS[cat]||cat)+'»';
  return '<div class="empty-state"><div class="empty-icon">📖</div><div class="empty-title">Пока пусто</div><div class="empty-sub">Статей '+label+' ещё нет. Скоро появятся!</div></div>';
}

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatDate(d) {
  if (!d) return '';
  try { return new Date(d).toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric'}); } catch(e) { return d; }
}

// ── МОДАЛКА СТРАНЫ ──
function openCountryModal() {
  renderCountryList(COUNTRIES_LIST);
  document.getElementById('countryModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('countrySearch').focus(), 300);
}
function closeCountryModal() { document.getElementById('countryModal').classList.remove('active'); document.body.style.overflow=''; }
function onModalOverlay(e) { if (e.target===document.getElementById('countryModal')) closeCountryModal(); }
document.getElementById('countrySearch').addEventListener('input', function() {
  renderCountryList(COUNTRIES_LIST.filter(c => c.name.toLowerCase().includes(this.value.toLowerCase())));
});
function renderCountryList(list) {
  const el = document.getElementById('countryList');
  if (!list.length) { el.innerHTML='<div style="padding:20px;text-align:center;font-size:14px;color:var(--text-light)">Не найдено</div>'; return; }
  el.innerHTML = list.map(c =>
    '<button class="country-item'+(c.code===currentCode?' selected':'')+'" onclick="selectCountry(\''+ c.code +'\',\''+ c.name.replace(/\'/g,'\\\'')+'\')">'+
    '<div class="country-flag-sm"><img src="https://flagcdn.com/w80/'+c.code+'.png" alt="'+esc(c.name)+'" loading="lazy"></div>'+
    '<span class="country-item-name">'+esc(c.name)+'</span>'+
    (c.code===currentCode ? '<svg class="country-item-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>' : '')+
    '</button>'
  ).join('');
}
function selectCountry(code, name) {
  currentCode = code; currentName = name;
  localStorage.setItem('poisq_country', code);
  localStorage.setItem('poisq_country_name', name);
  localStorage.setItem('poisq_country_manual', '1');
  document.getElementById('inlineCountryName').textContent = name;
  closeCountryModal();
  loadArticles(code);
}

// ── ANN MODAL (из help.php) ──
let annCityId = null;
async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr  = await fetch('/api/get-user-country.php');
    const cd  = await cr.json();
    const cc  = cd.country_code || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc);
    const cities = await cir.json();
    const sel = document.getElementById('annCitySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name_lat ? c.name_lat + ' (' + c.name + ')' : c.name;
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch(e) {
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение.');
  }
}
function closeAnnModal() { document.getElementById('annModal').classList.remove('active'); document.body.style.overflow=''; }
async function filterByCity() {
  annCityId = document.getElementById('annCitySelect').value;
  await loadAnnServices(annCityId);
}
async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5');
    const d = await r.json();
    const sv = d.services || [];
    if (!sv.length) {
      content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>В этом городе нет новых сервисов за последние 5 дней</p></div>';
      return;
    }
    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += '<div class="ann-category"><div class="ann-cat-title">' + cat + '</div><div class="ann-grid">';
      list.forEach(s => {
        let photo = 'https://via.placeholder.com/200?text=Poisq';
        if (s.photo) { try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; } catch(e) { photo = s.photo; } }
        const now = new Date(), d2 = new Date(s.created_at);
        const diff = Math.floor((now - d2) / 86400000);
        const dateStr = diff === 0 ? 'Сегодня' : diff === 1 ? 'Вчера' : diff < 5 ? diff + ' дн.' : d2.toLocaleDateString('ru-RU', {day:'numeric',month:'short'});
        html += '<div class="ann-item" onclick="location.href=\''+ '/service/' + s.id +'\'"><img src="' + photo + '" alt="' + s.name + '" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'"><div class="ann-date">' + dateStr + '</div><div class="ann-item-name">' + s.name + '</div></div>';
      });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) { content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.'); }
}
function annErr(t, p) {
  return '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><h3>'+t+'</h3><p>'+p+'</p></div>';
}

// ── СТАРТ ──
loadArticles(currentCode);
</script>
</body>
</html>