<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

// ── Фильтры ──
$filterModId = (int)($_GET['mod'] ?? 0);
$period      = $_GET['period'] ?? 'week';
$dateFrom    = $_GET['from'] ?? '';
$dateTo      = $_GET['to']   ?? '';

switch ($period) {
    case 'today':  $fromDate = date('Y-m-d'); $toDate = date('Y-m-d'); break;
    case 'week':   $fromDate = date('Y-m-d', strtotime('-6 days')); $toDate = date('Y-m-d'); break;
    case 'month':  $fromDate = date('Y-m-d', strtotime('-29 days')); $toDate = date('Y-m-d'); break;
    case 'custom': $fromDate = $dateFrom ?: date('Y-m-d'); $toDate = $dateTo ?: date('Y-m-d'); break;
    default:       $fromDate = date('Y-m-d', strtotime('-6 days')); $toDate = date('Y-m-d');
}

// Список модераторов для dropdown
$allMods = $pdo->query("SELECT id, name FROM moderators ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Статистика ──
$params = [$fromDate, $toDate];
$modFilter = '';
if ($filterModId > 0) {
    $modFilter = 'AND ms.moderator_id = ?';
    $params[] = $filterModId;
}

$statsQuery = $pdo->prepare("
    SELECT ms.stat_date, m.id as mod_id, m.name as mod_name,
           SUM(CASE WHEN ms.action='created'  THEN 1 ELSE 0 END) as created,
           SUM(CASE WHEN ms.action='reached'  THEN 1 ELSE 0 END) as reached,
           SUM(CASE WHEN ms.action IN ('no_answer','no_number','other') THEN 1 ELSE 0 END) as not_reached
    FROM moderator_stats ms
    JOIN moderators m ON ms.moderator_id = m.id
    WHERE ms.stat_date BETWEEN ? AND ?
    $modFilter
    GROUP BY ms.stat_date, ms.moderator_id
    ORDER BY ms.stat_date DESC, m.name
");
$statsQuery->execute($params);
$statsRows = $statsQuery->fetchAll(PDO::FETCH_ASSOC);

// Итого
$totals = ['created'=>0,'reached'=>0,'not_reached'=>0];
foreach ($statsRows as $r) {
    $totals['created']     += (int)$r['created'];
    $totals['reached']     += (int)$r['reached'];
    $totals['not_reached'] += (int)$r['not_reached'];
}

// ── История входов ──
$loginParams = [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'];
$loginFilter = '';
if ($filterModId > 0) {
    $loginFilter = 'AND ms.moderator_id = ?';
    $loginParams[] = $filterModId;
}
$logins = $pdo->prepare("
    SELECT ms.logged_in_at, ms.ip_address, m.name as mod_name
    FROM moderator_sessions ms
    JOIN moderators m ON ms.moderator_id = m.id
    WHERE ms.logged_in_at BETWEEN ? AND ?
    $loginFilter
    ORDER BY ms.logged_in_at DESC
    LIMIT 50
");
$logins->execute($loginParams);
$logins = $logins->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

ob_start();
?>

<!-- Фильтры -->
<form method="GET" style="margin-bottom:20px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Модератор</label>
            <select name="mod" class="form-control form-select" style="width:180px;">
                <option value="0">Все модераторы</option>
                <?php foreach ($allMods as $m): ?>
                <option value="<?php echo $m['id']; ?>" <?php echo $filterModId===$m['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($m['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">Период</label>
            <div style="display:flex;gap:4px;">
                <?php foreach(['today'=>'Сегодня','week'=>'Неделя','month'=>'Месяц','custom'=>'Дата'] as $p=>$l): ?>
                <button type="submit" name="period" value="<?php echo $p; ?>"
                    class="btn btn-sm <?php echo $period===$p?'btn-primary':'btn-secondary'; ?>"
                    onclick="this.form.elements['mod'].value=document.querySelector('select[name=mod]').value">
                    <?php echo $l; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($period === 'custom'): ?>
        <div>
            <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">С</label>
            <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>" style="width:145px;">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:4px;">По</label>
            <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>" style="width:145px;">
        </div>
        <div style="padding-bottom:2px;">

            <button type="submit" name="period" value="custom" class="btn btn-primary btn-sm">🔍 Показать</button>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Итоговые карточки -->
<div class="stat-grid stat-grid-3" style="margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-card-label">Создано сервисов</div>
        <div class="stat-card-value"><?php echo $totals['created']; ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">✅ Дозвонились</div>
        <div class="stat-card-value"><?php echo $totals['reached']; ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-card-label">☎️ Не дозвонились</div>
        <div class="stat-card-value"><?php echo $totals['not_reached']; ?></div>
    </div>
</div>

<!-- Таблица статистики -->
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header">
        <div class="panel-title">Статистика за период: <?php echo date('d.m.Y', strtotime($fromDate)); ?> — <?php echo date('d.m.Y', strtotime($toDate)); ?></div>
    </div>
    <?php if (empty($statsRows)): ?>
    <div class="empty-state" style="padding:40px 24px;">
        <div class="empty-state-icon">📊</div>
        <div class="empty-state-title">Данных за этот период нет</div>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Модератор</th>
                <th>Создано</th>
                <th>✅ Дозвонились</th>
                <th>☎️ Не дозвонились</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statsRows as $r): ?>
        <tr>
            <td style="font-size:13px;font-weight:600;"><?php echo date('d.m.Y', strtotime($r['stat_date'])); ?></td>
            <td>
                <a href="?mod=<?php echo $r['mod_id']; ?>&period=<?php echo $period; ?>&from=<?php echo urlencode($fromDate); ?>&to=<?php echo urlencode($toDate); ?>"
                   style="font-weight:600;color:var(--primary);text-decoration:none;">
                    <?php echo htmlspecialchars($r['mod_name']); ?>
                </a>
            </td>
            <td style="font-weight:600;"><?php echo (int)$r['created'] ?: '—'; ?></td>
            <td style="color:#10B981;font-weight:<?php echo $r['reached']?'600':'400'; ?>"><?php echo (int)$r['reached'] ?: '—'; ?></td>
            <td style="color:#F59E0B;font-weight:<?php echo $r['not_reached']?'600':'400'; ?>"><?php echo (int)$r['not_reached'] ?: '—'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#F9FAFB;font-weight:700;">
                <td colspan="2">Итого</td>
                <td><?php echo $totals['created']; ?></td>
                <td style="color:#10B981;"><?php echo $totals['reached']; ?></td>
                <td style="color:#F59E0B;"><?php echo $totals['not_reached']; ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- История входов -->
<?php if (!empty($logins)): ?>
<div class="panel">
    <div class="panel-header"><div class="panel-title">🔐 История входов</div></div>
    <table class="table">
        <thead><tr><th>Дата / Время</th><th>Модератор</th><th>IP адрес</th></tr></thead>
        <tbody>
        <?php foreach ($logins as $l): ?>
        <tr>
            <td style="font-size:13px;"><?php echo date('d.m.Y H:i', strtotime($l['logged_in_at'])); ?></td>
            <td style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($l['mod_name']); ?></td>
            <td style="font-size:13px;color:#6B7280;"><?php echo htmlspecialchars($l['ip_address']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
.form-select{padding-right:32px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;-webkit-appearance:none;appearance:none;cursor:pointer;}
</style>

<?php
$content = ob_get_clean();
renderLayout('Статистика модераторов', $content, $pendingCount, 0, $pendingReviewCount);
?>
