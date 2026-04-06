<?php
define('MOD_PANEL', true);
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../config/database.php';
requireModeratorAuth(); // dashboard доступен всем залогиненным модераторам

$pdo   = getDbConnection();
$modId = getModeratorId();

// ──────────────────────────────────────────────
// Агрегированная статистика по периодам
// ──────────────────────────────────────────────
function fetchPeriodStats(PDO $pdo, int $modId, string $fromDate): array {
    $stmt = $pdo->prepare("
        SELECT action, COUNT(*) as cnt
        FROM moderator_stats
        WHERE moderator_id = ? AND stat_date >= ?
        GROUP BY action
    ");
    $stmt->execute([$modId, $fromDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = ['created'=>0,'reached'=>0,'no_answer'=>0,'no_number'=>0,'other'=>0];
    foreach ($rows as $r) {
        if (isset($result[$r['action']])) $result[$r['action']] = (int)$r['cnt'];
    }
    return $result;
}

$today   = fetchPeriodStats($pdo, $modId, date('Y-m-d'));
$week    = fetchPeriodStats($pdo, $modId, date('Y-m-d', strtotime('-6 days')));
$month   = fetchPeriodStats($pdo, $modId, date('Y-m-d', strtotime('-29 days')));

// ──────────────────────────────────────────────
// Таблица по дням (последние 30 дней)
// ──────────────────────────────────────────────
$dailyStmt = $pdo->prepare("
    SELECT stat_date, action, COUNT(*) as cnt
    FROM moderator_stats
    WHERE moderator_id = ? AND stat_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY stat_date, action
    ORDER BY stat_date DESC
");
$dailyStmt->execute([$modId]);
$dailyRaw = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyMap = [];
foreach ($dailyRaw as $r) {
    $d = $r['stat_date'];
    if (!isset($dailyMap[$d])) $dailyMap[$d] = ['created'=>0,'reached'=>0,'no_answer'=>0,'no_number'=>0,'other'=>0];
    $dailyMap[$d][$r['action']] = (int)$r['cnt'];
}

// История входов
$sessions = $pdo->prepare("
    SELECT logged_in_at, ip_address
    FROM moderator_sessions
    WHERE moderator_id = ?
    ORDER BY logged_in_at DESC
    LIMIT 10
");
$sessions->execute([$modId]);
$sessions = $sessions->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="stat-grid stat-grid-3" style="margin-bottom:24px;">
    <?php
    $periods = [
        'Сегодня'   => $today,
        'За неделю' => $week,
        'За месяц'  => $month,
    ];
    foreach ($periods as $label => $s):
    ?>
    <div style="background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.06);">
        <div style="font-size:12px;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;"><?php echo $label; ?></div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div>
                <div style="font-size:26px;font-weight:800;color:#1F2937;line-height:1;"><?php echo $s['created']; ?></div>
                <div style="font-size:11px;color:#9CA3AF;margin-top:2px;">создано</div>
            </div>
            <div>
                <div style="font-size:26px;font-weight:800;color:#10B981;line-height:1;"><?php echo $s['reached']; ?></div>
                <div style="font-size:11px;color:#9CA3AF;margin-top:2px;">дозвонились</div>
            </div>
            <div>
                <div style="font-size:26px;font-weight:800;color:#F59E0B;line-height:1;"><?php echo $s['no_answer']; ?></div>
                <div style="font-size:11px;color:#9CA3AF;margin-top:2px;">не дозвонились</div>
            </div>
            <div>
                <div style="font-size:26px;font-weight:800;color:#6B7280;line-height:1;"><?php echo $s['no_number']; ?></div>
                <div style="font-size:11px;color:#9CA3AF;margin-top:2px;">нет номера</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Таблица по дням -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📅 Статистика по дням (последние 30 дней)</div>
    </div>
    <?php if (empty($dailyMap)): ?>
    <div class="empty-state" style="padding:40px 24px;">
        <div class="empty-state-icon">📊</div>
        <div class="empty-state-title">Данных пока нет</div>
        <div class="empty-state-text">Статистика появится после первых действий</div>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Создано</th>
                <th>✅ Дозвонились</th>
                <th>☎️ Не дозвонились</th>
                <th>🚫 Нет номера</th>
                <th>📝 Другое</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $totals = ['created'=>0,'reached'=>0,'no_answer'=>0,'no_number'=>0,'other'=>0];
        foreach ($dailyMap as $date => $s):
            foreach ($totals as $k => &$v) $v += $s[$k] ?? 0;
            unset($v);
        ?>
        <tr>
            <td style="font-size:13px;font-weight:600;"><?php echo date('d.m.Y', strtotime($date)); ?></td>
            <td><?php echo $s['created'] ?: '—'; ?></td>
            <td style="color:#10B981;font-weight:<?php echo $s['reached']?'600':'400'; ?>"><?php echo $s['reached'] ?: '—'; ?></td>
            <td style="color:#F59E0B;font-weight:<?php echo $s['no_answer']?'600':'400'; ?>"><?php echo $s['no_answer'] ?: '—'; ?></td>
            <td style="color:#9CA3AF;"><?php echo $s['no_number'] ?: '—'; ?></td>
            <td style="color:#3B6CF4;"><?php echo $s['other'] ?: '—'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:#F9FAFB;">
                <td style="font-weight:700;font-size:13px;">Итого</td>
                <td style="font-weight:700;"><?php echo $totals['created']; ?></td>
                <td style="font-weight:700;color:#10B981;"><?php echo $totals['reached']; ?></td>
                <td style="font-weight:700;color:#F59E0B;"><?php echo $totals['no_answer']; ?></td>
                <td style="font-weight:700;color:#9CA3AF;"><?php echo $totals['no_number']; ?></td>
                <td style="font-weight:700;color:#3B6CF4;"><?php echo $totals['other']; ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- История входов -->
<?php if (!empty($sessions)): ?>
<div class="panel">
    <div class="panel-header"><div class="panel-title">🔐 Последние входы</div></div>
    <table class="table">
        <thead><tr><th>Дата / Время</th><th>IP адрес</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
            <td style="font-size:13px;"><?php echo date('d.m.Y H:i', strtotime($s['logged_in_at'])); ?></td>
            <td style="font-size:13px;color:#6B7280;"><?php echo htmlspecialchars($s['ip_address']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
renderModLayout('Моя статистика', $content);
