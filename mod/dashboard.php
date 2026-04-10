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
// "не дозвонились" = no_answer + no_number + other
// ──────────────────────────────────────────────
function fetchPeriodStats(PDO $pdo, int $modId, string $fromDate, string $toDate = ''): array {
    if ($toDate) {
        $stmt = $pdo->prepare("
            SELECT action, COUNT(*) as cnt
            FROM moderator_stats
            WHERE moderator_id = ? AND stat_date BETWEEN ? AND ?
            GROUP BY action
        ");
        $stmt->execute([$modId, $fromDate, $toDate]);
    } else {
        $stmt = $pdo->prepare("
            SELECT action, COUNT(*) as cnt
            FROM moderator_stats
            WHERE moderator_id = ? AND stat_date >= ?
            GROUP BY action
        ");
        $stmt->execute([$modId, $fromDate]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = ['created'=>0,'reached'=>0,'no_answer'=>0,'no_number'=>0,'other'=>0];
    foreach ($rows as $r) {
        if (isset($result[$r['action']])) $result[$r['action']] = (int)$r['cnt'];
    }
    $result['not_reached'] = $result['no_answer'] + $result['no_number'] + $result['other'];
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

// ──────────────────────────────────────────────
// Заработок: ставки и история выплат
// ──────────────────────────────────────────────
$rateStmt = $pdo->prepare("SELECT * FROM moderator_rates WHERE moderator_id = ?");
$rateStmt->execute([$modId]);
$rates = $rateStmt->fetch(PDO::FETCH_ASSOC) ?: ['rate_reached' => 0.50, 'rate_not_reached' => 0.30];

// Последняя выплата
$lastPayStmt = $pdo->prepare("SELECT * FROM moderator_payments WHERE moderator_id = ? ORDER BY paid_at DESC LIMIT 1");
$lastPayStmt->execute([$modId]);
$lastPay = $lastPayStmt->fetch(PDO::FETCH_ASSOC);

// Текущий период = с (period_to + 1 день) после последней выплаты
if ($lastPay) {
    $currentFrom = date('Y-m-d', strtotime($lastPay['period_to'] . ' +1 day'));
} else {
    // Берём с самой первой записи в статистике
    $firstStmt = $pdo->prepare("SELECT MIN(stat_date) FROM moderator_stats WHERE moderator_id = ?");
    $firstStmt->execute([$modId]);
    $firstDate = $firstStmt->fetchColumn();
    $currentFrom = $firstDate ?: date('Y-m-d');
}
$currentTo = date('Y-m-d');
$currentStats = fetchPeriodStats($pdo, $modId, $currentFrom, $currentTo);
$currentEarnings = round(
    $currentStats['reached'] * (float)$rates['rate_reached'] +
    $currentStats['not_reached'] * (float)$rates['rate_not_reached'],
    2
);

// Фильтр по периоду для заработка
$earnPeriod = $_GET['earn_period'] ?? 'current';
$earnFrom   = $_GET['earn_from'] ?? '';
$earnTo     = $_GET['earn_to']   ?? '';

switch ($earnPeriod) {
    case 'current':
        $earnFromDate = $currentFrom; $earnToDate = $currentTo; break;
    case 'month':
        $earnFromDate = date('Y-m-01'); $earnToDate = date('Y-m-d'); break;
    case 'last_month':
        $earnFromDate = date('Y-m-01', strtotime('first day of last month'));
        $earnToDate   = date('Y-m-t',  strtotime('last day of last month')); break;
    case 'custom':
        $earnFromDate = $earnFrom ?: $currentFrom;
        $earnToDate   = $earnTo   ?: $currentTo; break;
    default:
        $earnFromDate = $currentFrom; $earnToDate = $currentTo;
}
$filteredStats = fetchPeriodStats($pdo, $modId, $earnFromDate, $earnToDate);
$filteredEarnings = round(
    $filteredStats['reached'] * (float)$rates['rate_reached'] +
    $filteredStats['not_reached'] * (float)$rates['rate_not_reached'],
    2
);

// История выплат
$payHistStmt = $pdo->prepare("SELECT * FROM moderator_payments WHERE moderator_id = ? ORDER BY paid_at DESC LIMIT 20");
$payHistStmt->execute([$modId]);
$payHistory = $payHistStmt->fetchAll(PDO::FETCH_ASSOC);

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

<!-- Сводка по периодам -->
<div class="stat-grid stat-grid-3" style="margin-bottom:24px;">
    <?php
    $periods = [
        'Сегодня'   => $today,
        'За неделю' => $week,
        'За месяц'  => $month,
    ];
    foreach ($periods as $label => $s):
    ?>
    <div style="background:var(--bg-white);border:1px solid var(--border);border-radius:10px;padding:16px 18px;box-shadow:var(--shadow);">
        <div style="font-size:12px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.4px;margin-bottom:12px;"><?php echo $label; ?></div>
        <div style="display:flex;gap:20px;flex-wrap:wrap;">
            <div>
                <div style="font-size:26px;font-weight:800;color:var(--text);line-height:1;"><?php echo $s['created']; ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:2px;">создано</div>
            </div>
            <div>
                <div style="font-size:26px;font-weight:800;color:var(--success);line-height:1;"><?php echo $s['reached']; ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:2px;">дозвонились</div>
            </div>
            <div>
                <div style="font-size:26px;font-weight:800;color:var(--warning);line-height:1;"><?php echo $s['not_reached']; ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:2px;">не дозвонились</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════
     БЛОК ЗАРАБОТКА
     ══════════════════════════════════ -->
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header">
        <div class="panel-title">💶 Мой заработок</div>
        <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);">
            <span>Ставки:</span>
            <span style="background:var(--success-bg);color:#065F46;padding:2px 8px;border-radius:6px;font-weight:700;font-size:12px;">✅ <?php echo number_format((float)$rates['rate_reached'], 2); ?>€</span>
            <span style="background:var(--warning-bg);color:#92400E;padding:2px 8px;border-radius:6px;font-weight:700;font-size:12px;">☎️ <?php echo number_format((float)$rates['rate_not_reached'], 2); ?>€</span>
        </div>
    </div>
    <div style="padding:16px;">

        <!-- Текущий период (с последней выплаты) -->
        <div style="background:var(--primary-light);border:1px solid #BFDBFE;border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:16px;">
            <div style="font-size:12px;font-weight:700;color:var(--primary-dark);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:10px;">
                Текущий период (с <?php echo date('d.m.Y', strtotime($currentFrom)); ?>)
                <?php if ($lastPay): ?>
                <span style="font-size:11px;font-weight:400;color:var(--text-secondary);text-transform:none;"> · последняя выплата <?php echo date('d.m.Y', strtotime($lastPay['paid_at'])); ?></span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--text);line-height:1;"><?php echo $currentStats['created']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">создано</div>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--success);line-height:1;"><?php echo $currentStats['reached']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">дозвонились</div>
                </div>
                <div>
                    <div style="font-size:22px;font-weight:800;color:var(--warning);line-height:1;"><?php echo $currentStats['not_reached']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">не дозвонились</div>
                </div>
                <div style="margin-left:auto;">
                    <div style="font-size:28px;font-weight:900;color:var(--primary);line-height:1;"><?php echo number_format($currentEarnings, 2); ?>€</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">к оплате</div>
                </div>
            </div>
        </div>

        <!-- Фильтр по периоду -->
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;">
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;">Период</div>
                <div style="display:flex;gap:4px;">
                    <?php foreach([
                        'current'    => 'Текущий',
                        'month'      => 'Этот месяц',
                        'last_month' => 'Прошлый месяц',
                        'custom'     => 'Даты',
                    ] as $p => $l): ?>
                    <a href="?earn_period=<?php echo $p; ?>"
                       class="btn btn-sm <?php echo $earnPeriod===$p?'btn-primary':'btn-secondary'; ?>">
                        <?php echo $l; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($earnPeriod === 'custom'): ?>
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;">С</div>
                <input type="date" name="earn_from" class="form-control" value="<?php echo htmlspecialchars($earnFromDate); ?>" style="width:140px;">
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;">По</div>
                <input type="date" name="earn_to" class="form-control" value="<?php echo htmlspecialchars($earnToDate); ?>" style="width:140px;">
            </div>
            <input type="hidden" name="earn_period" value="custom">
            <div style="padding-bottom:2px;">
                <button type="submit" class="btn btn-primary btn-sm">🔍 Показать</button>
            </div>
            <?php endif; ?>
        </form>

        <!-- Результат по выбранному периоду -->
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
            <div style="font-size:12px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:10px;">
                <?php echo date('d.m.Y', strtotime($earnFromDate)); ?> — <?php echo date('d.m.Y', strtotime($earnToDate)); ?>
            </div>
            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <div style="font-size:20px;font-weight:800;color:var(--text);line-height:1;"><?php echo $filteredStats['created']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">создано</div>
                </div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:var(--success);line-height:1;"><?php echo $filteredStats['reached']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">дозвонились × <?php echo number_format((float)$rates['rate_reached'], 2); ?>€</div>
                </div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:var(--warning);line-height:1;"><?php echo $filteredStats['not_reached']; ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">не дозвонились × <?php echo number_format((float)$rates['rate_not_reached'], 2); ?>€</div>
                </div>
                <div style="margin-left:auto;">
                    <div style="font-size:24px;font-weight:900;color:var(--primary);line-height:1;"><?php echo number_format($filteredEarnings, 2); ?>€</div>
                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">итого</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- История выплат -->
<?php if (!empty($payHistory)): ?>
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header"><div class="panel-title">💳 История выплат</div></div>
    <table class="table">
        <thead><tr>
            <th>Дата выплаты</th>
            <th>Период</th>
            <th>Создано</th>
            <th>Сумма</th>
            <th>Примечание</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payHistory as $pay): ?>
        <tr>
            <td style="font-size:13px;font-weight:600;"><?php echo date('d.m.Y', strtotime($pay['paid_at'])); ?></td>
            <td style="font-size:12px;color:var(--text-secondary);">
                <?php echo date('d.m.Y', strtotime($pay['period_from'])); ?> — <?php echo date('d.m.Y', strtotime($pay['period_to'])); ?>
            </td>
            <td style="font-size:13px;"><?php echo (int)$pay['services_count']; ?></td>
            <td style="font-size:14px;font-weight:700;color:var(--success);"><?php echo number_format((float)$pay['amount'], 2); ?>€</td>
            <td style="font-size:12px;color:var(--text-secondary);"><?php echo htmlspecialchars($pay['note'] ?? ''); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Таблица по дням -->
<div class="panel" style="margin-bottom:20px;">
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
                <th>💶 Заработок</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $totals = ['created'=>0,'reached'=>0,'not_reached'=>0,'earn'=>0.0];
        foreach ($dailyMap as $date => $s):
            $nr = ($s['no_answer'] ?? 0) + ($s['no_number'] ?? 0) + ($s['other'] ?? 0);
            $dayEarn = round($s['reached'] * (float)$rates['rate_reached'] + $nr * (float)$rates['rate_not_reached'], 2);
            $totals['created']     += $s['created'] ?? 0;
            $totals['reached']     += $s['reached'] ?? 0;
            $totals['not_reached'] += $nr;
            $totals['earn']        += $dayEarn;
        ?>
        <tr>
            <td style="font-size:13px;font-weight:600;"><?php echo date('d.m.Y', strtotime($date)); ?></td>
            <td><?php echo ($s['created'] ?? 0) ?: '—'; ?></td>
            <td style="color:var(--success);font-weight:<?php echo ($s['reached']??0)?'600':'400'; ?>"><?php echo ($s['reached'] ?? 0) ?: '—'; ?></td>
            <td style="color:var(--warning);font-weight:<?php echo $nr?'600':'400'; ?>"><?php echo $nr ?: '—'; ?></td>
            <td style="color:var(--primary);font-weight:600;"><?php echo $dayEarn > 0 ? number_format($dayEarn, 2).'€' : '—'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg);">
                <td style="font-weight:700;font-size:13px;">Итого</td>
                <td style="font-weight:700;"><?php echo $totals['created']; ?></td>
                <td style="font-weight:700;color:var(--success);"><?php echo $totals['reached']; ?></td>
                <td style="font-weight:700;color:var(--warning);"><?php echo $totals['not_reached']; ?></td>
                <td style="font-weight:700;color:var(--primary);"><?php echo number_format($totals['earn'], 2); ?>€</td>
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
            <td style="font-size:13px;color:var(--text-secondary);"><?php echo htmlspecialchars($s['ip_address']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
renderModLayout('Моя статистика', $content);
