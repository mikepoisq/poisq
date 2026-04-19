<?php
// panel-5588/layout.php — Общий шаблон админки
if (!defined('ADMIN_PANEL')) die('Direct access not allowed');

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    'dashboard'  => ['icon' => 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z', 'label' => 'Дашборд'],
    'moderate'   => ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Модерация'],
    'duplicates' => ['icon' => 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z', 'label' => 'Дубликаты'],
    'services'   => ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Сервисы'],
    'users'      => ['icon' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75', 'label' => 'Пользователи'],

    'analytics'     => ['icon' => 'M18 20V10M12 20V4M6 20v-6', 'label' => 'Аналитика'],
    'verifications' => ['icon' => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z', 'label' => 'Проверки'],
    'reviews'       => ['icon' => 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z', 'label' => 'Комментарии'],

];

function renderLayout(string $pageTitle, string $content, int $pendingCount = 0, int $pendingVerifCount = 0, int $pendingReviewCount = 0, int $pendingArticlesCount = 0, int $duplicatesCount = 0): void {
    // Всегда считаем свежо из БД
    try {
        $_db = getDbConnection();
        $pendingArticlesCount = (int)$_db->query("SELECT COUNT(*) FROM article_submissions WHERE status='pending'")->fetchColumn();
        $duplicatesCount = (int)$_db->query("SELECT COUNT(*) FROM services WHERE status='duplicate'")->fetchColumn();
    } catch (Exception $_e) { $pendingArticlesCount = 0; $duplicatesCount = 0; }
    global $currentPage, $navItems;
    $isAdmin    = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
    $isModerator = !$isAdmin && function_exists('isModeratorLoggedIn') && isModeratorLoggedIn();
    $modPerms   = $isModerator ? getModeratorPermissions() : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> — Poisq Admin</title>
<link rel="icon" type="image/png" href="/panel-5588/favicon-admin.png">
<style>
:root {
    --primary: #2E73D8;
    --primary-light: #EFF6FF;
    --primary-dark: #1A5AB8;
    --success: #10B981;
    --success-bg: #ECFDF5;
    --warning: #F59E0B;
    --warning-bg: #FFFBEB;
    --danger: #EF4444;
    --danger-bg: #FEF2F2;
    --text: #1F2937;
    --text-secondary: #6B7280;
    --text-light: #9CA3AF;
    --bg: #F5F5F7;
    --bg-white: #FFFFFF;
    --border: #E5E7EB;
    --border-light: #F3F4F6;
    --sidebar-w: 220px;
    --header-h: 56px;
    --radius: 10px;
    --radius-sm: 7px;
    --shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
}
body.dark-theme {
    --primary: #5B9BD5;
    --primary-light: #1E2D3D;
    --primary-dark: #7CB8F0;
    --success: #34D399;
    --success-bg: #1A2E28;
    --warning: #FBBF24;
    --warning-bg: #2A2210;
    --danger: #F87171;
    --danger-bg: #2D1A1A;
    --text: #E2E8F0;
    --text-secondary: #94A3B8;
    --text-light: #64748B;
    --bg: #1A1F2E;
    --bg-white: #242B3D;
    --border: #2E3748;
    --border-light: #2A3142;
    --shadow: 0 1px 3px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ── SIDEBAR ── */
.sidebar {
    width: var(--sidebar-w);
    background: var(--bg-white);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
    transition: transform 0.25s ease;
}
.sidebar-logo {
    padding: 20px 18px 16px;
    border-bottom: 1px solid var(--border-light);
    display: flex; align-items: center; gap: 10px;
}
.sidebar-logo-mark {
    width: 32px; height: 32px;
    background: var(--primary);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.sidebar-logo-mark svg { width: 18px; height: 18px; stroke: white; fill: none; stroke-width: 2.5; }
.sidebar-logo-text { font-size: 17px; font-weight: 800; color: var(--text); letter-spacing: -0.5px; }
.sidebar-logo-sub { font-size: 11px; color: var(--text-light); }

.sidebar-nav { flex: 1; padding: 10px 10px; overflow-y: auto; }
.nav-label {
    font-size: 10px; font-weight: 700; color: var(--text-light);
    text-transform: uppercase; letter-spacing: 0.8px;
    padding: 10px 10px 6px;
}
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-secondary);
    font-size: 14px; font-weight: 500;
    transition: all 0.15s;
    margin-bottom: 2px;
    position: relative;
}
.nav-item:hover { background: var(--bg); color: var(--text); }
.nav-item.active {
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
}
.nav-item svg {
    width: 18px; height: 18px;
    stroke: currentColor; fill: none; stroke-width: 2;
    flex-shrink: 0;
}
.nav-badge {
    margin-left: auto;
    background: var(--danger);
    color: white;
    font-size: 11px; font-weight: 700;
    padding: 1px 6px;
    border-radius: 99px;
    min-width: 20px; text-align: center;
}
.nav-item.active .nav-badge { background: var(--primary); }

.sidebar-footer {
    padding: 10px;
    border-top: 1px solid var(--border-light);
}
.sidebar-admin {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px;
    border-radius: var(--radius-sm);
}
.sidebar-admin-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
    flex-shrink: 0;
}
.sidebar-admin-name { font-size: 13px; font-weight: 600; color: var(--text); }
.sidebar-admin-role { font-size: 11px; color: var(--text-light); }
.btn-logout {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--danger);
    font-size: 13px; font-weight: 500;
    transition: background 0.15s;
    margin-top: 2px;
}
.btn-logout:hover { background: var(--danger-bg); }
.btn-logout svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }

/* ── MAIN ── */
.main-wrap {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-width: 0;
}

/* ── TOPBAR ── */
.topbar {
    height: var(--header-h);
    background: var(--bg-white);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 24px;
    position: sticky; top: 0; z-index: 50;
    gap: 12px;
}
.topbar-title { font-size: 16px; font-weight: 700; color: var(--text); flex: 1; }
.topbar-menu-btn {
    display: none;
    width: 36px; height: 36px;
    border: none; background: none;
    cursor: pointer; align-items: center; justify-content: center;
    border-radius: var(--radius-sm);
}
.topbar-menu-btn svg { width: 20px; height: 20px; stroke: var(--text); fill: none; stroke-width: 2; }
.topbar-actions { display: flex; align-items: center; gap: 8px; }
.topbar-time { font-size: 13px; color: var(--text-light); }
.topbar-site {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; color: var(--primary);
    text-decoration: none;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--bg-white);
    transition: background 0.15s;
}
.topbar-site:hover { background: var(--primary-light); }
.topbar-site svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; }

/* ── CONTENT ── */
.content {
    padding: 24px;
    flex: 1;
    max-width: 1100px;
}

/* ── ОБЩИЕ КОМПОНЕНТЫ ── */

/* Карточки статистики */
.stat-grid { display: grid; gap: 14px; margin-bottom: 24px; }
.stat-grid-4 { grid-template-columns: repeat(4, 1fr); }
.stat-grid-3 { grid-template-columns: repeat(3, 1fr); }
.stat-grid-2 { grid-template-columns: repeat(2, 1fr); }
.stat-card {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    box-shadow: var(--shadow);
}
.stat-card-label { font-size: 12px; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }
.stat-card-value { font-size: 28px; font-weight: 800; color: var(--text); line-height: 1; }
.stat-card-sub { font-size: 12px; color: var(--text-light); margin-top: 4px; }
.stat-card.yellow .stat-card-value { color: var(--warning); }
.stat-card.green  .stat-card-value { color: var(--success); }
.stat-card.red    .stat-card-value { color: var(--danger); }
.stat-card.blue   .stat-card-value { color: var(--primary); }
.stat-card.gray   .stat-card-value { color: var(--text-light); }

/* Секция с таблицей */
.panel {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 20px;
}
.panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg);
    gap: 12px;
}
.panel-title { font-size: 14px; font-weight: 700; color: var(--text); }
.panel-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.panel-body { padding: 0; }

/* Таблица */
.table { width: 100%; border-collapse: collapse; }
.table th {
    text-align: left; font-size: 11px; font-weight: 700;
    color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px;
    padding: 10px 16px; background: var(--bg);
    border-bottom: 1px solid var(--border-light);
}
.table td {
    padding: 12px 16px; font-size: 14px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: #FAFAFA; }
body.dark-theme .table tr:hover td { background: #2A3344 !important; }
body.dark-theme .table td { background: var(--bg-white) !important; color: var(--text) !important; }
body.dark-theme .table th { background: #1E2535 !important; color: var(--text-secondary) !important; }

/* Кнопки */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 600;
    border: none; cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    font-family: inherit;
    white-space: nowrap;
}
.btn:active { transform: scale(0.97); }
.btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-success { background: var(--success); color: white; }
.btn-success:hover { background: #059669; }
.btn-danger  { background: var(--danger-bg); color: var(--danger); border: 1px solid #FECACA; }
.btn-danger:hover  { background: #FEE2E2; }
.btn-secondary { background: var(--bg-white); color: var(--text); border: 1px solid var(--border); }
.btn-secondary:hover { background: var(--bg); }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-lg { padding: 10px 20px; font-size: 15px; }

/* Бейджи статусов */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 12px; font-weight: 600;
    white-space: nowrap;
}
.badge-yellow { background: var(--warning-bg); color: #92400E; }
.badge-green  { background: var(--success-bg); color: #065F46; }
.badge-red    { background: var(--danger-bg);  color: #991B1B; }
.badge-gray   { background: var(--border-light); color: var(--text-secondary); }
.badge-blue   { background: var(--primary-light); color: var(--primary-dark); }

/* Форма элементы */
.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-family: inherit;
    color: var(--text);
    background: var(--bg-white);
    outline: none;
    transition: border-color 0.15s;
}
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46,115,216,0.1); }
.form-select {
    padding-right: 32px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    -webkit-appearance: none; appearance: none;
    cursor: pointer;
}

/* Алерты */
.alert { padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
.alert svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; }
.alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
.alert-danger  { background: var(--danger-bg);  color: #991B1B; border: 1px solid #FECACA; }

/* Пустое состояние */
.empty-state { text-align: center; padding: 48px 24px; }
.empty-state-icon { font-size: 40px; margin-bottom: 12px; }
.empty-state-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
.empty-state-text { font-size: 14px; color: var(--text-secondary); }

/* Пагинация */
.pagination { display: flex; align-items: center; gap: 4px; padding: 14px 18px; border-top: 1px solid var(--border-light); }
.page-link {
    padding: 6px 12px; border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 600;
    border: 1px solid var(--border);
    background: var(--bg-white); color: var(--text-secondary);
    text-decoration: none; transition: all 0.15s; cursor: pointer;
}
.page-link:hover { background: var(--bg); color: var(--text); }
.page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-link.disabled { opacity: 0.4; pointer-events: none; }

/* Фильтры-чипы */
.filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
    padding: 5px 12px; border-radius: 99px;
    font-size: 13px; font-weight: 600;
    border: 1px solid var(--border);
    background: var(--bg-white); color: var(--text-secondary);
    text-decoration: none; cursor: pointer; transition: all 0.15s;
    white-space: nowrap;
}
.chip:hover { border-color: var(--primary); color: var(--primary); }
.chip.active { background: var(--primary); color: white; border-color: var(--primary); }

/* Сайдбар-оверлей для мобайл */
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 99;
}

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 4px 0 24px rgba(0,0,0,0.12);
    }
    .sidebar-overlay.open { display: block; }
    .main-wrap { margin-left: 0; }
    .topbar-menu-btn { display: flex; }
    .content { padding: 16px; }
    .stat-grid-4 { grid-template-columns: repeat(2, 1fr); }
    .stat-grid-3 { grid-template-columns: repeat(2, 1fr); }
    .table { display: block; overflow-x: auto; }
}
@media (max-width: 480px) {
    .stat-grid-4, .stat-grid-3, .stat-grid-2 { grid-template-columns: 1fr 1fr; }
}
/* ── ТЁМНАЯ ТЕМА: исправление цветов текста ── */
body.dark-theme input,
body.dark-theme textarea,
body.dark-theme select,
body.dark-theme .form-control,
body.dark-theme .form-select {
    background: #2E3748 !important;
    color: #E2E8F0 !important;
    border-color: #3D4D63 !important;
}
body.dark-theme input::placeholder,
body.dark-theme textarea::placeholder {
    color: #64748B !important;
}
body.dark-theme .btn-secondary {
    background: #2E3748 !important;
    color: #E2E8F0 !important;
    border-color: #3D4D63 !important;
}
body.dark-theme .btn-secondary:hover {
    background: #374357 !important;
}
body.dark-theme .reason-btn {
    background: #2E3748 !important;
    color: #E2E8F0 !important;
    border-color: #3D4D63 !important;
}
body.dark-theme .reason-btn:hover {
    background: #374357 !important;
}
body.dark-theme table th,
body.dark-theme table td {
    border-color: #2E3748 !important;
    color: #E2E8F0 !important;
}
body.dark-theme .alert {
    border-color: #3D4D63 !important;
}
body.dark-theme .alert-success {
    background: #1A2E28 !important;
    color: #34D399 !important;
}
body.dark-theme .alert-danger {
    background: #2D1A1A !important;
    color: #F87171 !important;
}
body.dark-theme label {
    color: #94A3B8 !important;
}
body.dark-theme .badge-gray {
    background: #2E3748 !important;
    color: #94A3B8 !important;
}
body.dark-theme input[type="checkbox"] {
    accent-color: #5B9BD5;
}
/* таблицы */
body.dark-theme tbody tr {
    background: var(--bg-white) !important;
    color: var(--text) !important;
}
body.dark-theme tbody tr:hover {
    background: #2A3344 !important;
}
body.dark-theme tbody tr:nth-child(even) {
    background: #222938 !important;
}
body.dark-theme td, body.dark-theme th {
    color: var(--text) !important;
    border-color: var(--border) !important;
}
/* Все инлайн div/td/tr со светлым фоном — текст всегда светлый */
body.dark-theme td *,
body.dark-theme tr *,
body.dark-theme .panel *,
body.dark-theme .panel-header *,
body.dark-theme .stat-card * {
    color: inherit;
}
/* Перебиваем все инлайн background на элементах */
body.dark-theme div[style],
body.dark-theme td[style],
body.dark-theme tr[style],
body.dark-theme span[style] {
    color: var(--text) !important;
}
/* Но сохраняем цвета для статус-бейджей и кнопок */
body.dark-theme .badge-green,
body.dark-theme .badge-yellow,
body.dark-theme .badge-red,
body.dark-theme .badge-blue,
body.dark-theme .btn-success,
body.dark-theme .btn-danger,
body.dark-theme .btn-primary {
    color: inherit !important;
}
body.dark-theme [style*="color:#10B981"],
body.dark-theme [style*="color: #10B981"],
body.dark-theme [style*="color:#EF4444"],
body.dark-theme [style*="color: #EF4444"],
body.dark-theme [style*="color:#F59E0B"],
body.dark-theme [style*="color: #F59E0B"],
body.dark-theme [style*="color:#2E73D8"],
body.dark-theme [style*="color:var(--primary)"],
body.dark-theme [style*="color:var(--success)"],
body.dark-theme [style*="color:var(--danger)"],
body.dark-theme [style*="color:var(--warning)"] {
    color: inherit !important;
}
</style>
</head>
<body>

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        </div>
        <div>
            <div class="sidebar-logo-text">Poisq</div>
            <div class="sidebar-logo-sub">Admin panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($isAdmin): ?>
        <!-- ═══════ ПОЛНАЯ НАВИГАЦИЯ СУПЕР-АДМИНА ═══════ -->
        <div class="nav-label">Основное</div>
        <?php foreach ($navItems as $page => $item): ?>
        <a href="/panel-5588/<?php echo $page === 'dashboard' ? 'dashboard.php' : $page . '.php'; ?>"
           class="nav-item <?php echo $currentPage === $page ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="<?php echo $item['icon']; ?>"/></svg>
            <?php echo $item['label']; ?>
            <?php if ($page === 'moderate' && $pendingCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
            <?php if ($page === 'verifications' && $pendingVerifCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingVerifCount; ?></span>
            <?php endif; ?>
            <?php if ($page === 'reviews' && $pendingReviewCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingReviewCount; ?></span>
            <?php endif; ?>
            <?php if ($page === 'duplicates' && $duplicatesCount > 0): ?>
            <span class="nav-badge" style="background:#F59E0B"><?php echo $duplicatesCount; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div class="nav-label" style="margin-top:10px">Настройки</div>
        <a href="/panel-5588/settings/categories.php"
           class="nav-item <?php echo $currentPage === 'categories' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
            Категории сервисов
        </a>
        <a href="/panel-5588/settings/cities.php"
           class="nav-item <?php echo $currentPage === 'cities' && strpos($_SERVER['PHP_SELF'],'settings')!==false ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
            Города и страны
        </a>

        <div class="nav-label" style="margin-top:10px">Страницы</div>
        <a href="/panel-5588/pages/articles.php"
           class="nav-item <?php echo in_array($currentPage, ['articles','article-edit']) ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            Статьи (Полезное)
        </a>
        <a href="/panel-5588/pages/article-submissions.php"
           class="nav-item <?php echo $currentPage === 'article-submissions' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Статьи от юзеров
            <?php if ($pendingArticlesCount > 0): ?>
            <span class="nav-badge" style="background:#EA580C"><?php echo $pendingArticlesCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="/panel-5588/pages/faq.php"
           class="nav-item <?php echo $currentPage === 'faq' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round" stroke-width="3"/></svg>
            FAQ (Помощь)
        </a>
        <a href="/panel-5588/pages/terms.php"
           class="nav-item <?php echo $currentPage === 'terms' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Условия
        </a>
        <a href="/panel-5588/pages/about.php"
           class="nav-item <?php echo $currentPage === 'about' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16" stroke-linecap="round" stroke-width="3"/></svg>
            О нас
        </a>

        <div class="nav-label" style="margin-top:10px">Модераторы</div>
        <a href="/panel-5588/moderators.php"
           class="nav-item <?php echo $currentPage === 'moderators' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Модераторы
        </a>
        <a href="/panel-5588/moderator-stats.php"
           class="nav-item <?php echo $currentPage === 'moderator-stats' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            Статистика
        </a>
        <a href="/panel-5588/moderator-earnings.php"
           class="nav-item <?php echo $currentPage === 'moderator-earnings' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Заработок
        </a>

        <?php else: ?>
        <!-- ═══════ НАВИГАЦИЯ МОДЕРАТОРА ═══════ -->
        <div class="nav-label">Работа</div>
        <?php if (in_array('moderation', $modPerms)): ?>
        <a href="/panel-5588/moderate.php"
           class="nav-item <?php echo $currentPage === 'moderate' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Модерация
            <?php if ($pendingCount > 0): ?><span class="nav-badge"><?php echo $pendingCount; ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (in_array('services', $modPerms)): ?>
        <a href="/panel-5588/services.php"
           class="nav-item <?php echo $currentPage === 'services' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Сервисы
        </a>
        <?php endif; ?>
        <?php if (in_array('services_create', $modPerms)): ?>
        <a href="/panel-5588/create.php"
           class="nav-item <?php echo $currentPage === 'create' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Создать сервис
        </a>
        <?php endif; ?>
        <?php if (in_array('cities', $modPerms)): ?>
        <a href="/panel-5588/settings/cities.php"
           class="nav-item <?php echo $currentPage === 'cities' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
            Города и страны
        </a>
        <?php endif; ?>
        <?php if (in_array('articles', $modPerms)): ?>
        <a href="/panel-5588/pages/articles.php"
           class="nav-item <?php echo in_array($currentPage,['articles','article-edit']) ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            Статьи (Полезное)
        </a>
        <?php endif; ?>
        <?php if (in_array('faq', $modPerms)): ?>
        <a href="/panel-5588/pages/article-submissions.php"
           class="nav-item <?php echo $currentPage === 'article-submissions' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Статьи от юзеров
            <?php if ($pendingArticlesCount > 0): ?>
            <span class="nav-badge" style="background:#EA580C"><?php echo $pendingArticlesCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="/panel-5588/pages/faq.php"
           class="nav-item <?php echo $currentPage === 'faq' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round" stroke-width="3"/></svg>
            FAQ (Помощь)
        </a>
        <?php endif; ?>
        <?php if (in_array('my_stats', $modPerms)): ?>
        <div class="nav-label" style="margin-top:10px">Статистика</div>
        <a href="/mod/dashboard.php" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            Моя статистика
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <?php if ($isModerator): ?>
        <div class="sidebar-admin">
            <div class="sidebar-admin-avatar" style="background:#D1FAE5;color:#065F46;">
                <?php echo htmlspecialchars(mb_strtoupper(mb_substr(getModeratorName(), 0, 1))); ?>
            </div>
            <div>
                <div class="sidebar-admin-name"><?php echo htmlspecialchars(getModeratorName()); ?></div>
                <div class="sidebar-admin-role">Модератор</div>
            </div>
        </div>
        <a href="/mod/logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Выйти
        </a>
        <?php else: ?>
        <div class="sidebar-admin">
            <div class="sidebar-admin-avatar">A</div>
            <div>
                <div class="sidebar-admin-name">Admin</div>
                <div class="sidebar-admin-role">Super admin</div>
            </div>
        </div>
        <a href="/panel-5588/logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Выйти
        </a>
        <?php endif; ?>
    </div>
</aside>

<!-- MAIN -->
<div class="main-wrap">
    <!-- TOPBAR -->
    <header class="topbar">
        <button class="topbar-menu-btn" onclick="toggleSidebar()" id="menuBtn">
            <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></div>
        <div class="topbar-actions">
            <span class="topbar-time" id="topbarTime"></span>
            <button onclick="toggleTheme()" id="themeBtn" title="Переключить тему"
                style="background:none;border:1px solid var(--border);border-radius:7px;padding:6px 10px;cursor:pointer;font-size:16px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;transition:all 0.2s;">
                <span id="themeIcon">🌙</span>
            </button>
            <a href="https://poisq.com" target="_blank" class="topbar-site">
                <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Открыть сайт
            </a>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">
        <?php echo $content; ?>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
// Время в топбаре
// Тёмная тема
function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('poisq_admin_theme', isDark ? 'dark' : 'light');
    document.getElementById('themeIcon').textContent = isDark ? '☀️' : '🌙';
}
(function() {
    if (localStorage.getItem('poisq_admin_theme') === 'dark') {
        document.body.classList.add('dark-theme');
        const btn = document.getElementById('themeIcon');
        if (btn) btn.textContent = '☀️';
    }
})();
// Время в топбаре
function updateTime() {
    const now = new Date();
    document.getElementById('topbarTime').textContent =
        now.toLocaleTimeString('ru-RU', {hour:'2-digit', minute:'2-digit'});
}
updateTime();
setInterval(updateTime, 60000);
</script>
<?php
} // end renderLayout
