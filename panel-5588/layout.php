<?php
// panel-5588/layout.php — Общий шаблон админки
if (!defined('ADMIN_PANEL')) die('Direct access not allowed');

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$navItems = [
    'dashboard'  => ['icon' => 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z', 'label' => 'Дашборд'],
    'moderate'   => ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Модерация'],
    'services'   => ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Сервисы'],
    'users'      => ['icon' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75', 'label' => 'Пользователи'],
    'cities'     => ['icon' => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z', 'label' => 'Города'],
    'analytics'  => ['icon' => 'M18 20V10M12 20V4M6 20v-6', 'label' => 'Аналитика'],
];

function renderLayout(string $pageTitle, string $content, int $pendingCount = 0): void {
    global $currentPage, $navItems;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> — Poisq Admin</title>
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
        <div class="nav-label">Основное</div>
        <?php foreach ($navItems as $page => $item): ?>
        <a href="/panel-5588/<?php echo $page === 'dashboard' ? 'dashboard.php' : $page . '.php'; ?>"
           class="nav-item <?php echo $currentPage === $page ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="<?php echo $item['icon']; ?>"/></svg>
            <?php echo $item['label']; ?>
            <?php if ($page === 'moderate' && $pendingCount > 0): ?>
            <span class="nav-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
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
