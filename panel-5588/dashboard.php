<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
requireAdmin();

$pdo = getDbConnection();

$totalServices  = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$pendingCount   = $pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$approvedCount  = $pdo->query("SELECT COUNT(*) FROM services WHERE status='approved'")->fetchColumn();
$rejectedCount  = $pdo->query("SELECT COUNT(*) FROM services WHERE status='rejected'")->fetchColumn();
$draftCount     = $pdo->query("SELECT COUNT(*) FROM services WHERE status='draft'")->fetchColumn();
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newToday       = $pdo->query("SELECT COUNT(*) FROM services WHERE DATE(created_at) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Дашборд — Poisq Admin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #F5F5F7; color: #1F2937; min-height: 100vh;
}
.header {
    background: #fff; padding: 14px 16px;
    border-bottom: 1px solid #E5E7EB;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.header-logo { font-size: 20px; font-weight: 800; color: #2E73D8; }
.header-sub { font-size: 11px; color: #9CA3AF; }
.logout { font-size: 13px; color: #EF4444; text-decoration: none; font-weight: 600; padding: 6px 12px; border-radius: 8px; background: #FEF2F2; }
.main { padding: 16px; max-width: 600px; margin: 0 auto; }
.section-title { font-size: 12px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 10px; }
.cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.card {
    background: #fff; border-radius: 12px; padding: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.card-value { font-size: 28px; font-weight: 800; color: #1F2937; }
.card-label { font-size: 12px; color: #9CA3AF; margin-top: 2px; }
.card.pending .card-value { color: #F59E0B; }
.card.approved .card-value { color: #10B981; }
.card.rejected .card-value { color: #EF4444; }
.card.draft .card-value { color: #9CA3AF; }
.btn {
    display: block; width: 100%; padding: 15px;
    border-radius: 12px; font-size: 16px; font-weight: 700;
    text-align: center; text-decoration: none; margin-bottom: 10px;
    transition: opacity 0.15s;
}
.btn:active { opacity: 0.8; }
.btn-primary { background: #2E73D8; color: #fff; }
.btn-secondary { background: #fff; color: #374151; border: 1.5px solid #E5E7EB; }
.badge {
    display: inline-block; background: #EF4444; color: #fff;
    font-size: 12px; font-weight: 700; padding: 2px 7px;
    border-radius: 99px; margin-left: 6px; vertical-align: middle;
}
</style>
</head>
<body>
<div class="header">
    <div>
        <div class="header-logo">Poisq</div>
        <div class="header-sub">Панель управления</div>
    </div>
    <a href="/panel-5588/logout.php" class="logout">Выйти</a>
</div>
<div class="main">
    <div class="section-title">Быстрые действия</div>
    <a href="/panel-5588/moderate.php" class="btn btn-primary">
        🔍 На модерацию
        <?php if ($pendingCount > 0): ?>
        <span class="badge"><?php echo $pendingCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="/panel-5588/services.php" class="btn btn-secondary">📋 Все сервисы</a>

    <div class="section-title">Статистика</div>
    <div class="cards">
        <div class="card pending">
            <div class="card-value"><?php echo $pendingCount; ?></div>
            <div class="card-label">Ожидают модерации</div>
        </div>
        <div class="card approved">
            <div class="card-value"><?php echo $approvedCount; ?></div>
            <div class="card-label">Опубликовано</div>
        </div>
        <div class="card rejected">
            <div class="card-value"><?php echo $rejectedCount; ?></div>
            <div class="card-label">Отклонено</div>
        </div>
        <div class="card draft">
            <div class="card-value"><?php echo $draftCount; ?></div>
            <div class="card-label">Черновики</div>
        </div>
    </div>
    <div class="cards" style="margin-top:10px">
        <div class="card">
            <div class="card-value"><?php echo $totalServices; ?></div>
            <div class="card-label">Всего сервисов</div>
        </div>
        <div class="card">
            <div class="card-value"><?php echo $totalUsers; ?></div>
            <div class="card-label">Пользователей</div>
        </div>
    </div>
    <?php if ($newToday > 0): ?>
    <div style="margin-top:10px; background:#EFF6FF; border-radius:12px; padding:14px 16px; font-size:14px; color:#1D4ED8; font-weight:600;">
        🆕 Сегодня добавлено: <?php echo $newToday; ?> сервис(ов)
    </div>
    <?php endif; ?>
</div>
</body>
</html>