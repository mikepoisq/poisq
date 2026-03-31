<?php
// useful.php — Полезное: список статей
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Полезное — Poisq</title>
<meta name="description" content="Статьи и гайды для русскоязычных за рубежом: документы, финансы, здоровье, семья.">
<link rel="canonical" href="https://poisq.com/useful.php">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
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
  --warning:       #F59E0B;
  --danger:        #EF4444;
  --radius:        16px;
  --radius-sm:     12px;
  --radius-xs:     10px;
  --shadow-sm:     0 1px 4px rgba(0,0,0,0.07);
}
html, body { min-height: 100%; overflow-x: hidden; }
body {
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-secondary);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}
.app-container {
  max-width: 430px; margin: 0 auto;
  background: var(--bg);
  min-height: 100vh; display: flex; flex-direction: column;
}

/* ── HEADER ── */
.page-header {
  position: sticky; top: 0; z-index: 100;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
}
.header-top {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 14px; height: 56px;
}
.header-left { display: flex; align-items: center; }
.header-center { display: flex; justify-content: center; }
.header-center img { height: 36px; width: auto; object-fit: contain; }
.header-right { display: flex; align-items: center; gap: 8px; }

.btn-grid {
  width: 38px; height: 38px; border-radius: var(--radius-xs); border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s, transform 0.1s; flex-shrink: 0;
}
.btn-grid svg { width: 20px; height: 20px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }

.btn-burger {
  width: 38px; height: 38px; display: flex; flex-direction: column;
  justify-content: center; align-items: center; gap: 5px;
  padding: 8px; cursor: pointer; background: none; border: none;
  border-radius: var(--radius-xs); flex-shrink: 0;
}
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--text); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--text); }

/* ── HERO ── */
.page-hero { padding: 18px 16px 0; background: var(--bg); }
.hero-title { font-size: 22px; font-weight: 800; color: var(--text); letter-spacing: -0.5px; margin-bottom: 4px; }
.hero-sub { font-size: 14px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; margin-bottom: 14px; }

/* ── ВЫБОР СТРАНЫ ── */
.country-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
  background: var(--bg);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  margin: 0 16px 14px;
  cursor: pointer; transition: background 0.15s;
  box-shadow: var(--shadow-sm);
}
.country-bar:active { background: var(--bg-secondary); }
.country-bar-flag { width: 26px; height: 19px; border-radius: 3px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.15); flex-shrink: 0; }
.country-bar-flag img { width: 100%; height: 100%; object-fit: cover; display: block; }
.country-bar-text { flex: 1; }
.country-bar-name { font-size: 14px; font-weight: 700; color: var(--text); }
.country-bar-hint { font-size: 11px; color: var(--text-light); font-weight: 500; margin-top: 1px; }
.country-bar-chevron svg { width: 18px; height: 18px; stroke: var(--text-light); fill: none; stroke-width: 2.5; }

/* ── ФИЛЬТР ── */
.filter-row {
  display: flex; gap: 8px; overflow-x: auto;
  padding: 0 16px 14px; scrollbar-width: none;
  background: var(--bg); border-bottom: 1px solid var(--border-light);
}
.filter-row::-webkit-scrollbar { display: none; }
.filter-chip {
  flex-shrink: 0; padding: 6px 14px; border-radius: 99px;
  border: 1.5px solid var(--border); background: var(--bg); color: var(--text-secondary);
  font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s; font-family: inherit;
}
.filter-chip.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.filter-chip:active { transform: scale(0.94); }

/* ── СПИСОК СТАТЕЙ ── */
.articles-list { flex: 1; padding: 12px 14px 24px; display: flex; flex-direction: column; gap: 10px; }

/* ── СКЕЛЕТОН ЗАГРУЗКИ ── */
.skeleton-card {
  background: var(--bg); border: 1px solid var(--border-light);
  border-radius: var(--radius); overflow: hidden; display: flex; height: 110px;
}
.skeleton-thumb { width: 110px; height: 110px; background: var(--border-light); flex-shrink: 0; animation: shimmer 1.4s infinite; }
.skeleton-body { flex: 1; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.skeleton-line { background: var(--border-light); border-radius: 6px; animation: shimmer 1.4s infinite; }
@keyframes shimmer {
  0%, 100% { opacity: 1; } 50% { opacity: 0.4; }
}

/* ── КАРТОЧКА СТАТЬИ ── */
.article-card {
  background: var(--bg); border: 1px solid var(--border-light);
  border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-sm);
  text-decoration: none; color: inherit; display: flex;
  transition: transform 0.12s, box-shadow 0.12s; cursor: pointer;
}
.article-card:active { transform: scale(0.98); box-shadow: none; }
.article-thumb { width: 110px; height: 110px; flex-shrink: 0; background: var(--bg-secondary); overflow: hidden; }
.article-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.article-body { flex: 1; padding: 11px 12px 10px; display: flex; flex-direction: column; min-width: 0; }
.article-meta { display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
.article-category { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; flex-shrink: 0; }
.article-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--text-light); flex-shrink: 0; }
.article-read-time { font-size: 11px; color: var(--text-light); font-weight: 600; white-space: nowrap; }
.article-title {
  font-size: 14.5px; font-weight: 800; color: var(--text); line-height: 1.35;
  letter-spacing: -0.2px; margin-bottom: 4px;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.article-excerpt {
  font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; font-weight: 500;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: 1;
}
.article-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 7px; }
.article-date { font-size: 11px; color: var(--text-light); font-weight: 600; }
.article-more { display: flex; align-items: center; gap: 2px; font-size: 12px; color: var(--primary); font-weight: 700; }
.article-more svg { width: 13px; height: 13px; stroke: var(--primary); fill: none; stroke-width: 2.5; }

/* ── ПУСТОЕ СОСТОЯНИЕ ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  padding: 60px 20px; text-align: center; gap: 10px;
}
.empty-state-icon { font-size: 48px; }
.empty-state-title { font-size: 17px; font-weight: 800; color: var(--text); }
.empty-state-sub { font-size: 14px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; }

/* ── FOOTER ── */
.page-footer {
  background: var(--bg); border-top: 1px solid var(--border-light);
  padding: 14px 16px;
  display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 14px;
}
.footer-link { font-size: 12px; color: var(--text-secondary); text-decoration: none; font-weight: 500; }
.footer-link.active { color: var(--primary); font-weight: 700; }
.footer-link:active { color: var(--primary); }

/* ── SIDE MENU ── */
.side-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.4); backdrop-filter: blur(3px); z-index: 399; display: none; }
.side-overlay.active { display: block; }
.side-menu {
  position: fixed; top: 0; right: -290px; width: 270px; height: 100%;
  background: var(--bg); z-index: 400;
  transition: right 0.3s cubic-bezier(.4,0,.2,1);
  box-shadow: -8px 0 32px rgba(0,0,0,0.12);
  display: flex; flex-direction: column; border-radius: 20px 0 0 20px;
}
.side-menu.active { right: 0; }
.side-menu-head { padding: 28px 20px 20px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-light); }
.side-avatar {
  width: 46px; height: 46px; border-radius: 50%; background: var(--primary);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 800; font-size: 18px; margin-bottom: 10px; overflow: hidden;
}
.side-avatar img { width: 100%; height: 100%; object-fit: cover; }
.side-user-name { font-size: 15px; font-weight: 700; color: var(--text); }
.side-user-sub { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
.side-items { flex: 1; overflow-y: auto; padding: 8px 0; }
.side-item {
  display: flex; align-items: center; gap: 13px; padding: 13px 20px;
  color: var(--text); text-decoration: none; font-size: 14.5px; font-weight: 600; transition: background 0.15s;
}
.side-item:active { background: var(--bg-secondary); }
.side-item svg { width: 19px; height: 19px; stroke: var(--text-secondary); fill: none; stroke-width: 2; flex-shrink: 0; }
.side-item.highlight { color: var(--primary); }
.side-item.highlight svg { stroke: var(--primary); }
.side-item.danger { color: var(--danger); }
.side-item.danger svg { stroke: var(--danger); }
.side-divider { height: 1px; background: var(--border-light); margin: 6px 20px; }

/* ── МОДАЛКА СТРАНЫ ── */
.country-modal {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.4);
  backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
  z-index: 600; display: none;
  align-items: flex-start; justify-content: center;
}
.country-modal.active { display: flex; }
.country-modal-sheet {
  background: var(--bg);
  width: 100%; max-width: 430px;
  max-height: 90vh;
  border-radius: 0 0 var(--radius) var(--radius);
  overflow: hidden; display: flex; flex-direction: column;
  animation: slideDown 0.25s cubic-bezier(.4,0,.2,1);
}
@keyframes slideDown { from { transform: translateY(-100%); } to { transform: translateY(0); } }
.cm-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px 14px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.cm-title { font-size: 16px; font-weight: 800; color: var(--text); letter-spacing: -0.3px; }
.cm-close { width: 30px; height: 30px; border-radius: 50%; border: none; background: var(--bg-secondary); color: var(--text-secondary); font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.cm-close:active { background: var(--border); }
.cm-search { padding: 12px 16px; flex-shrink: 0; }
.cm-search-input { width: 100%; padding: 10px 14px; border-radius: var(--radius-xs); border: 1.5px solid var(--border); background: var(--bg-secondary); font-size: 15px; color: var(--text); outline: none; font-family: inherit; transition: border-color 0.15s; }
.cm-search-input:focus { border-color: var(--primary); }
.cm-list { flex: 1; overflow-y: auto; }
.cm-item { display: flex; align-items: center; gap: 13px; padding: 10px 20px; cursor: pointer; transition: background 0.12s; }
.cm-item:active { background: var(--bg-secondary); }
.cm-item-flag { width: 30px; height: 22px; border-radius: 3px; overflow: hidden; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
.cm-item-flag img { width: 100%; height: 100%; object-fit: cover; }
.cm-item-name { font-size: 14.5px; font-weight: 600; color: var(--text); }
.cm-item.selected .cm-item-name { color: var(--primary); }
.cm-item-check { margin-left: auto; width: 18px; height: 18px; color: var(--primary); display: none; }
.cm-item.selected .cm-item-check { display: block; }

/* ── МОДАЛКА РУПОР ── */
.ann-modal { position: fixed; inset: 0; background: var(--bg-secondary); z-index: 500; display: none; flex-direction: column; }
.ann-modal.active { display: flex; animation: slideUp 0.3s cubic-bezier(.4,0,.2,1); }
@keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
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
.spinner { width: 32px; height: 32px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 15px; font-weight: 800; color: var(--text); letter-spacing: -0.3px; margin-bottom: 10px; padding-left: 2px; }
.ann-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
.ann-item { background: var(--bg); border-radius: var(--radius-xs); overflow: hidden; cursor: pointer; transition: transform 0.15s; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); position: relative; }
.ann-item:active { transform: scale(0.94); }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--bg-secondary); }
.ann-date { position: absolute; top: 5px; right: 5px; background: rgba(59,108,244,0.9); color: white; padding: 3px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 700; }
.ann-item-name { font-size: 11.5px; font-weight: 600; color: var(--text); padding: 7px 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }
.ann-add-card { background: var(--bg-secondary); border: 2px dashed var(--border); border-radius: var(--radius-xs); display: flex; flex-direction: column; align-items: center; justify-content: center; aspect-ratio: 1; cursor: pointer; transition: all 0.15s; gap: 5px; padding: 8px; }
.ann-add-card:active { border-color: var(--primary); background: var(--primary-light); transform: scale(0.95); }
.ann-add-card svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.ann-add-card span { font-size: 9.5px; color: var(--text-secondary); text-align: center; line-height: 1.3; font-weight: 600; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 50px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 64px; height: 64px; border-radius: 18px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
.ann-empty-icon svg { width: 30px; height: 30px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.ann-empty h3 { font-size: 16px; font-weight: 700; color: var(--text); }
.ann-empty p { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.6; }

::-webkit-scrollbar { display: none; }
</style>
</head>
<body>
<div class="app-container">

  <!-- ШАПКА -->
  <header class="page-header">
    <div class="header-top">
      <div class="header-left">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24">
            <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
            <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
          </svg>
        </button>
      </div>
      <div class="header-center">
        <a href="/index.php"><img src="/logo.png" alt="Poisq"></a>
      </div>
      <div class="header-right">
        <button class="btn-burger" id="menuToggle" aria-label="Меню">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <!-- HERO -->
  <div class="page-hero">
    <div class="hero-title">Полезное</div>
    <div class="hero-sub">Гайды, советы и инструкции для жизни за рубежом</div>
  </div>

  <!-- ВЫБОР СТРАНЫ -->
  <div class="country-bar" id="countrySelector" onclick="openCountryModal()">
    <div class="country-bar-flag">
      <img src="https://flagcdn.com/w80/fr.png" alt="Франция" id="currentFlag" loading="lazy" data-code="fr">
    </div>
    <div class="country-bar-text">
      <div class="country-bar-name" id="currentCountryName">Франция</div>
      <div class="country-bar-hint">Нажмите чтобы сменить страну</div>
    </div>
    <div class="country-bar-chevron">
      <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    </div>
  </div>

  <!-- ФИЛЬТР -->
  <div class="filter-row" id="filterRow">
    <button class="filter-chip active" onclick="filterByCategory('all', this)">Все</button>
    <button class="filter-chip" onclick="filterByCategory('Финансы', this)">💰 Финансы</button>
    <button class="filter-chip" onclick="filterByCategory('Документы', this)">📋 Документы</button>
    <button class="filter-chip" onclick="filterByCategory('Семья', this)">👨‍👩‍👧 Семья</button>
    <button class="filter-chip" onclick="filterByCategory('Здоровье', this)">🏥 Здоровье</button>
  </div>

  <!-- СПИСОК -->
  <div class="articles-list" id="articlesList">
    <!-- Скелетон при загрузке -->
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:60%"></div><div class="skeleton-line" style="height:16px;width:90%"></div><div class="skeleton-line" style="height:12px;width:80%"></div></div></div>
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:50%"></div><div class="skeleton-line" style="height:16px;width:95%"></div><div class="skeleton-line" style="height:12px;width:70%"></div></div></div>
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:55%"></div><div class="skeleton-line" style="height:16px;width:85%"></div><div class="skeleton-line" style="height:12px;width:75%"></div></div></div>
  </div>

  <!-- FOOTER -->
  <div class="page-footer">
    <a href="/useful.php"  class="footer-link active">Полезное</a>
    <a href="/help.php"    class="footer-link">Помощь</a>
    <a href="/terms.php"   class="footer-link">Условия</a>
    <a href="/about.php"   class="footer-link">О нас</a>
    <a href="/contact.php" class="footer-link">Контакт</a>
  </div>

</div><!-- /app-container -->

<!-- SIDE MENU -->
<div class="side-overlay" id="menuOverlay" onclick="closeMenu()"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-head">
    <div class="side-avatar">
      <?php if ($isLoggedIn && $userAvatar): ?>
        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
      <?php elseif ($isLoggedIn): ?>
        <?php echo $userInitial; ?>
      <?php else: ?>
        👤
      <?php endif; ?>
    </div>
    <div class="side-user-name"><?php echo $isLoggedIn ? htmlspecialchars($userName) : 'Добро пожаловать!'; ?></div>
    <div class="side-user-sub"><?php echo $isLoggedIn ? htmlspecialchars($_SESSION['user_email'] ?? '') : 'Войдите в аккаунт'; ?></div>
  </div>
  <div class="side-items">
    <?php if ($isLoggedIn): ?>
    <a href="/profile.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Личный кабинет
    </a>
    <a href="/add-service.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Добавить сервис
    </a>
    <?php else: ?>
    <a href="/login.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Войти
    </a>
    <a href="/register.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      Регистрация
    </a>
    <a href="/add-service.php" class="side-item">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Добавить сервис
    </a>
    <?php endif; ?>
    <a href="/useful.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Полезное
    </a>
    <div class="side-divider"></div>
    <a href="/contact.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Контакт
    </a>
    <?php if ($isLoggedIn): ?>
    <div class="side-divider"></div>
    <a href="/logout.php" class="side-item danger">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Выйти
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- МОДАЛКА СТРАНЫ -->
<div class="country-modal" id="countryModal" onclick="onCountryOverlay(event)">
  <div class="country-modal-sheet">
    <div class="cm-header">
      <span class="cm-title">Выберите страну</span>
      <button class="cm-close" onclick="closeCountryModal()">✕</button>
    </div>
    <div class="cm-search">
      <input type="text" class="cm-search-input" id="cmSearch" placeholder="Поиск страны…">
    </div>
    <div class="cm-list" id="cmList"></div>
  </div>
</div>

<!-- МОДАЛКА РУПОР -->
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

<script>
// ════════════════════════════════════════
// СТРАНЫ
// ════════════════════════════════════════
const countries = [
  {code:'am',name:'Армения'},{code:'au',name:'Австралия'},{code:'at',name:'Австрия'},
  {code:'az',name:'Азербайджан'},{code:'by',name:'Беларусь'},{code:'be',name:'Бельгия'},
  {code:'br',name:'Бразилия'},{code:'bg',name:'Болгария'},{code:'ca',name:'Канада'},
  {code:'cl',name:'Чили'},{code:'cn',name:'Китай'},{code:'co',name:'Колумбия'},
  {code:'cz',name:'Чехия'},{code:'dk',name:'Дания'},{code:'fi',name:'Финляндия'},
  {code:'fr',name:'Франция'},{code:'ge',name:'Грузия'},{code:'de',name:'Германия'},
  {code:'gr',name:'Греция'},{code:'hk',name:'Гонконг'},{code:'hu',name:'Венгрия'},
  {code:'ie',name:'Ирландия'},{code:'il',name:'Израиль'},{code:'it',name:'Италия'},
  {code:'jp',name:'Япония'},{code:'kz',name:'Казахстан'},{code:'lv',name:'Латвия'},
  {code:'lt',name:'Литва'},{code:'mx',name:'Мексика'},{code:'nl',name:'Нидерланды'},
  {code:'nz',name:'Новая Зеландия'},{code:'no',name:'Норвегия'},{code:'pl',name:'Польша'},
  {code:'pt',name:'Португалия'},{code:'ro',name:'Румыния'},{code:'ru',name:'Россия'},
  {code:'rs',name:'Сербия'},{code:'sg',name:'Сингапур'},{code:'sk',name:'Словакия'},
  {code:'za',name:'ЮАР'},{code:'kr',name:'Южная Корея'},{code:'es',name:'Испания'},
  {code:'se',name:'Швеция'},{code:'ch',name:'Швейцария'},{code:'th',name:'Таиланд'},
  {code:'tr',name:'Турция'},{code:'ua',name:'Украина'},{code:'ae',name:'ОАЭ'},
  {code:'gb',name:'Великобритания'},{code:'us',name:'США'},{code:'uz',name:'Узбекистан'},
];

// Читаем сохранённую страну из localStorage
let currentCode = localStorage.getItem('poisq_country') || 'fr';
let currentName = localStorage.getItem('poisq_country_name') || 'Франция';
let currentCategory = 'all'; // активный фильтр категории

// Применяем флаг при загрузке
document.getElementById('currentFlag').src = 'https://flagcdn.com/w80/' + currentCode + '.png';
document.getElementById('currentFlag').dataset.code = currentCode;
document.getElementById('currentCountryName').textContent = currentName;

// ════════════════════════════════════════
// ЗАГРУЗКА СТАТЕЙ ИЗ API
// ════════════════════════════════════════
const categoryStyle = {
  'Финансы':   'background:#EEF2FF;color:#3B6CF4',
  'Документы': 'background:#FEF3C7;color:#D97706',
  'Семья':     'background:#FCE7F3;color:#DB2777',
  'Здоровье':  'background:#ECFDF5;color:#059669',
};

async function loadArticles(countryCode) {
  const list = document.getElementById('articlesList');

  // Показываем скелетон
  list.innerHTML = `
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:60%;margin-bottom:8px"></div><div class="skeleton-line" style="height:16px;width:90%;margin-bottom:8px"></div><div class="skeleton-line" style="height:12px;width:80%"></div></div></div>
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:50%;margin-bottom:8px"></div><div class="skeleton-line" style="height:16px;width:95%;margin-bottom:8px"></div><div class="skeleton-line" style="height:12px;width:70%"></div></div></div>
    <div class="skeleton-card"><div class="skeleton-thumb"></div><div class="skeleton-body"><div class="skeleton-line" style="height:12px;width:55%;margin-bottom:8px"></div><div class="skeleton-line" style="height:16px;width:85%;margin-bottom:8px"></div><div class="skeleton-line" style="height:12px;width:75%"></div></div></div>`;

  try {
    const resp = await fetch('/api/get-articles.php?country=' + encodeURIComponent(countryCode));
    const data = await resp.json();
    const articles = data.articles || [];

    if (!articles.length) {
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">📭</div>
          <div class="empty-state-title">Статей пока нет</div>
          <div class="empty-state-sub">Для этой страны статьи ещё не добавлены.<br>Скоро появятся!</div>
        </div>`;
      return;
    }

    // Сохраняем все статьи глобально для фильтрации по категории
    window._allArticles = articles;
    renderArticles(articles, currentCategory);

  } catch(e) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">⚠️</div>
        <div class="empty-state-title">Ошибка загрузки</div>
        <div class="empty-state-sub">Проверьте соединение и попробуйте снова.</div>
      </div>`;
  }
}

function renderArticles(articles, category) {
  const list = document.getElementById('articlesList');
  const filtered = category === 'all' ? articles : articles.filter(a => a.category === category);

  if (!filtered.length) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">🔍</div>
        <div class="empty-state-title">Нет статей в этой категории</div>
        <div class="empty-state-sub">Попробуйте выбрать другую категорию.</div>
      </div>`;
    return;
  }

  list.innerHTML = filtered.map(a => {
    const style = categoryStyle[a.category] || 'background:#F1F5F9;color:#64748B';
    const photo = a.photo || 'https://via.placeholder.com/110x110?text=P';
    return `
      <a class="article-card" href="/article/${a.country_code === 'all' ? 'all' : a.country_code}/${a.slug}" data-category="${escHtml(a.category)}">
        <div class="article-thumb">
          <img src="${escHtml(photo)}" alt="${escHtml(a.title)}" loading="lazy" onerror="this.src='https://via.placeholder.com/110x110?text=P'">
        </div>
        <div class="article-body">
          <div class="article-meta">
            <span class="article-category" style="${style}">${escHtml(a.category)}</span>
            <span class="article-dot"></span>
            <span class="article-read-time">${escHtml(a.read_time)}</span>
          </div>
          <div class="article-title">${escHtml(a.title)}</div>
          <div class="article-excerpt">${escHtml(a.excerpt)}</div>
          <div class="article-footer">
            <span class="article-date">${escHtml(a.date)}</span>
            <span class="article-more">Читать <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></span>
          </div>
        </div>
      </a>`;
  }).join('');
}

function escHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ════════════════════════════════════════
// ФИЛЬТР КАТЕГОРИЙ
// ════════════════════════════════════════
function filterByCategory(category, btn) {
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  currentCategory = category;
  if (window._allArticles) renderArticles(window._allArticles, category);
}

// ════════════════════════════════════════
// ВЫБОР СТРАНЫ
// ════════════════════════════════════════
function renderCountryList(list) {
  const cmList = document.getElementById('cmList');
  if (!list.length) {
    cmList.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-light);font-size:14px">Страны не найдены</div>';
    return;
  }
  cmList.innerHTML = list.map(c => `
    <div class="cm-item ${c.code === currentCode ? 'selected' : ''}" data-code="${c.code}" data-name="${c.name}">
      <div class="cm-item-flag"><img src="https://flagcdn.com/w80/${c.code}.png" alt="${c.name}" loading="lazy"></div>
      <span class="cm-item-name">${c.name}</span>
      <svg class="cm-item-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>`).join('');
  cmList.querySelectorAll('.cm-item').forEach(el => {
    el.addEventListener('click', () => selectCountry(el.dataset.code, el.dataset.name));
  });
}

function selectCountry(code, name) {
  currentCode = code;
  currentName = name;
  localStorage.setItem('poisq_country', code);
  localStorage.setItem('poisq_country_name', name);
  localStorage.setItem('poisq_country_manual', '1');
  document.getElementById('currentFlag').src = 'https://flagcdn.com/w80/' + code + '.png';
  document.getElementById('currentFlag').dataset.code = code;
  document.getElementById('currentCountryName').textContent = name;
  closeCountryModal();
  // Сбрасываем фильтр категории и загружаем статьи для новой страны
  currentCategory = 'all';
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  document.querySelector('.filter-chip').classList.add('active');
  loadArticles(code);
}

function openCountryModal() {
  document.getElementById('countryModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  document.getElementById('cmSearch').value = '';
  renderCountryList(countries);
  setTimeout(() => document.getElementById('cmSearch').focus(), 300);
}
function closeCountryModal() {
  document.getElementById('countryModal').classList.remove('active');
  document.body.style.overflow = '';
}
function onCountryOverlay(e) {
  if (e.target === document.getElementById('countryModal')) closeCountryModal();
}
document.getElementById('cmSearch').addEventListener('input', function() {
  renderCountryList(countries.filter(c => c.name.toLowerCase().includes(this.value.toLowerCase())));
});

// ════════════════════════════════════════
// БОКОВОЕ МЕНЮ
// ════════════════════════════════════════
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

// ════════════════════════════════════════
// РУПОР — СВЕЖИЕ СЕРВИСЫ
// ════════════════════════════════════════
let annCityId = null;
async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cc  = currentCode || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc);
    const cities = await cir.json();
    const sel = document.getElementById('citySelect');
    sel.innerHTML = '';
    annCityId = null;
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
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение.');
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
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r  = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5');
    const d  = await r.json();
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
        const dateStr = diff === 0 ? 'Сегодня' : diff === 1 ? 'Вчера' : diff + ' дн.';
        html += '<div class="ann-item" onclick="location.href=\'/service/' + s.id + '\'">' +
          '<img src="' + photo + '" alt="' + s.name + '" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=P\'">' +
          '<div class="ann-date">' + dateStr + '</div>' +
          '<div class="ann-item-name">' + s.name + '</div></div>';
      });
      html += '<div class="ann-add-card" onclick="location.href=\'/add-service.php\'"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>Добавить сервис</span></div>';
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}
function annErr(t, p) {
  return '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><h3>' + t + '</h3><p>' + p + '</p></div>';
}

// ════════════════════════════════════════
// СТАРТ — загружаем статьи при открытии
// ════════════════════════════════════════
loadArticles(currentCode);
</script>
</body>
</html>