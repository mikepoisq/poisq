<?php
// /mod/layout.php — Шаблон панели модератора
if (!defined('MOD_PANEL')) die('Direct access not allowed');

function renderModLayout(string $pageTitle, string $content): void {
    $perms = getModeratorPermissions();
    $name  = getModeratorName();
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    $curPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> — Poisq Mod</title>
<style>
:root {
    --primary:#2E73D8;--primary-light:#EFF6FF;--primary-dark:#1A5AB8;
    --success:#10B981;--success-bg:#ECFDF5;--warning:#F59E0B;--warning-bg:#FFFBEB;
    --danger:#EF4444;--danger-bg:#FEF2F2;
    --text:#1F2937;--text-secondary:#6B7280;--text-light:#9CA3AF;
    --bg:#F5F5F7;--bg-white:#FFFFFF;--border:#E5E7EB;--border-light:#F3F4F6;
    --sidebar-w:220px;--header-h:56px;
    --radius:10px;--radius-sm:7px;--shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
.sidebar{width:var(--sidebar-w);background:var(--bg-white);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;}
.sidebar-logo{padding:20px 18px 16px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:10px;}
.sidebar-logo-mark{width:32px;height:32px;background:#10B981;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sidebar-logo-mark svg{width:18px;height:18px;stroke:white;fill:none;stroke-width:2.5;}
.sidebar-logo-text{font-size:17px;font-weight:800;color:var(--text);letter-spacing:-.5px;}
.sidebar-logo-sub{font-size:11px;color:var(--text-light);}
.sidebar-nav{flex:1;padding:10px;overflow-y:auto;}
.nav-label{font-size:10px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.8px;padding:10px 10px 6px;}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:var(--radius-sm);text-decoration:none;color:var(--text-secondary);font-size:14px;font-weight:500;transition:all .15s;margin-bottom:2px;}
.nav-item:hover{background:var(--bg);color:var(--text);}
.nav-item.active{background:var(--primary-light);color:var(--primary);font-weight:600;}
.nav-item svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.sidebar-footer{padding:10px;border-top:1px solid var(--border-light);}
.sidebar-admin{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:var(--radius-sm);}
.sidebar-admin-avatar{width:30px;height:30px;border-radius:50%;background:#D1FAE5;color:#065F46;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.sidebar-admin-name{font-size:13px;font-weight:600;color:var(--text);}
.sidebar-admin-role{font-size:11px;color:var(--text-light);}
.btn-logout{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--radius-sm);text-decoration:none;color:var(--danger);font-size:13px;font-weight:500;transition:background .15s;margin-top:2px;}
.btn-logout:hover{background:var(--danger-bg);}
.btn-logout svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}
.main-wrap{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;min-width:0;}
.topbar{height:var(--header-h);background:var(--bg-white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;position:sticky;top:0;z-index:50;gap:12px;}
.topbar-title{font-size:16px;font-weight:700;color:var(--text);flex:1;}
.topbar-badge{background:#D1FAE5;color:#065F46;font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;}
.content{padding:24px;flex:1;max-width:1100px;}
.stat-grid{display:grid;gap:14px;margin-bottom:24px;}
.stat-grid-4{grid-template-columns:repeat(4,1fr);}
.stat-grid-3{grid-template-columns:repeat(3,1fr);}
.stat-grid-2{grid-template-columns:repeat(2,1fr);}
.stat-card{background:var(--bg-white);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;box-shadow:var(--shadow);}
.stat-card-label{font-size:12px;font-weight:600;color:var(--text-light);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;}
.stat-card-value{font-size:28px;font-weight:800;color:var(--text);line-height:1;}
.stat-card-sub{font-size:12px;color:var(--text-light);margin-top:4px;}
.stat-card.green .stat-card-value{color:var(--success);}
.stat-card.blue .stat-card-value{color:var(--primary);}
.stat-card.yellow .stat-card-value{color:var(--warning);}
.panel{background:var(--bg-white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}
.panel-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-light);background:var(--bg);gap:12px;}
.panel-title{font-size:14px;font-weight:700;color:var(--text);}
.table{width:100%;border-collapse:collapse;}
.table th{text-align:left;font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;padding:10px 16px;background:var(--bg);border-bottom:1px solid var(--border-light);}
.table td{padding:12px 16px;font-size:14px;border-bottom:1px solid var(--border-light);vertical-align:middle;}
.table tr:last-child td{border-bottom:none;}
.table tr:hover td{background:#FAFAFA;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius-sm);font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:all .15s;font-family:inherit;white-space:nowrap;}
.btn-primary{background:var(--primary);color:white;}.btn-primary:hover{background:var(--primary-dark);}
.btn-secondary{background:var(--bg-white);color:var(--text);border:1px solid var(--border);}.btn-secondary:hover{background:var(--bg);}
.btn-sm{padding:5px 10px;font-size:12px;}
.form-control{width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;color:var(--text);background:var(--bg-white);outline:none;transition:border-color .15s;}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(46,115,216,.1);}
.alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:14px;font-weight:500;margin-bottom:16px;display:flex;align-items:center;gap:10px;}
.alert-danger{background:var(--danger-bg);color:#991B1B;border:1px solid #FECACA;}
.empty-state{text-align:center;padding:48px 24px;}
.empty-state-icon{font-size:40px;margin-bottom:12px;}
.empty-state-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px;}
.empty-state-text{font-size:14px;color:var(--text-secondary);}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main-wrap{margin-left:0}.content{padding:16px}.stat-grid-4{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <div class="sidebar-logo-text">Poisq</div>
            <div class="sidebar-logo-sub">Moderator panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Работа</div>

        <?php if (in_array('moderation', $perms)): ?>
        <a href="/panel-5588/moderate.php" class="nav-item <?php echo $curPage==='moderate'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Модерация
        </a>
        <?php endif; ?>

        <?php if (in_array('services', $perms)): ?>
        <a href="/panel-5588/services.php" class="nav-item <?php echo $curPage==='services'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Сервисы
        </a>
        <?php endif; ?>

        <?php if (in_array('services_create', $perms)): ?>
        <a href="/panel-5588/create.php" class="nav-item <?php echo $curPage==='create'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Создать сервис
        </a>
        <?php endif; ?>

        <?php if (in_array('cities', $perms)): ?>
        <a href="/panel-5588/settings/cities.php" class="nav-item <?php echo $curPage==='cities'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
            Города и страны
        </a>
        <?php endif; ?>

        <?php if (in_array('articles', $perms)): ?>
        <a href="/panel-5588/pages/articles.php" class="nav-item <?php echo $curPage==='articles'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            Статьи (Полезное)
        </a>
        <?php endif; ?>

        <?php if (in_array('faq', $perms)): ?>
        <a href="/panel-5588/pages/faq.php" class="nav-item <?php echo $curPage==='faq'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round" stroke-width="3"/></svg>
            FAQ (Помощь)
        </a>
        <?php endif; ?>

        <?php if (in_array('my_stats', $perms)): ?>
        <div class="nav-label" style="margin-top:10px">Статистика</div>
        <a href="/mod/dashboard.php" class="nav-item <?php echo $curPage==='dashboard'?'active':''; ?>">
            <svg viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            Моя статистика
        </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-admin">
            <div class="sidebar-admin-avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div>
                <div class="sidebar-admin-name"><?php echo htmlspecialchars($name); ?></div>
                <div class="sidebar-admin-role">Модератор</div>
            </div>
        </div>
        <a href="/mod/logout.php" class="btn-logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Выйти
        </a>
    </div>
</aside>

<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-title"><?php echo htmlspecialchars($pageTitle); ?></div>
        <div>
            <span class="topbar-badge">Модератор</span>
        </div>
    </header>
    <main class="content">
        <?php echo $content; ?>
    </main>
</div>

</body>
</html>
<?php
} // end renderModLayout
