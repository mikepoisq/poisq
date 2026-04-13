<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

// ── AJAX: записать выплату ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    header('Content-Type: application/json');
    $modId        = (int)($_POST['moderator_id'] ?? 0);
    $amount       = round((float)($_POST['amount'] ?? 0), 2);
    $svcCount     = (int)($_POST['services_count'] ?? 0);
    $periodFrom   = $_POST['period_from'] ?? '';
    $periodTo     = $_POST['period_to']   ?? '';
    $note         = trim($_POST['note'] ?? '');
    $adminId      = SUPER_ADMIN_ID;

    if ($modId <= 0 || $amount < 0 || !$periodFrom || !$periodTo) {
        echo json_encode(['ok' => false, 'error' => 'Неверные параметры']);
        exit;
    }
    $pdo->prepare("INSERT INTO moderator_payments (moderator_id, amount, services_count, period_from, period_to, paid_by_admin_id, note)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$modId, $amount, $svcCount, $periodFrom, $periodTo, $adminId, $note ?: null]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ── Фильтры ───────────────────────────────────────────────────────────────
$period   = $_GET['period'] ?? 'current';
$dateFrom = $_GET['from']   ?? '';
$dateTo   = $_GET['to']     ?? '';

switch ($period) {
    case 'month':
        $fromDate = date('Y-m-01'); $toDate = date('Y-m-d'); break;
    case 'last_month':
        $fromDate = date('Y-m-01', strtotime('first day of last month'));
        $toDate   = date('Y-m-t',  strtotime('last day of last month')); break;
    case 'custom':
        $fromDate = $dateFrom ?: date('Y-m-01'); $toDate = $dateTo ?: date('Y-m-d'); break;
    default: // 'current' — показываем всё; фильтрация per-moderator ниже
        $fromDate = ''; $toDate = date('Y-m-d');
}

// ── Все модераторы ─────────────────────────────────────────────────────────
$allMods = $pdo->query("SELECT m.*, r.rate_reached, r.rate_not_reached
    FROM moderators m
    LEFT JOIN moderator_rates r ON r.moderator_id = m.id
    ORDER BY m.name")->fetchAll(PDO::FETCH_ASSOC);

// ── Статистика и расчёт для каждого модератора ────────────────────────────
$modData = [];
foreach ($allMods as $m) {
    $mid = (int)$m['id'];
    $rateReached    = (float)($m['rate_reached']     ?? 0.50);
    $rateNotReached = (float)($m['rate_not_reached'] ?? 0.30);

    // Определяем период "с последней выплаты" для этого модератора
    $lastPayStmt = $pdo->prepare("SELECT period_to FROM moderator_payments WHERE moderator_id=? ORDER BY paid_at DESC LIMIT 1");
    $lastPayStmt->execute([$mid]);
    $lastPayDate = $lastPayStmt->fetchColumn();

    if ($period === 'current') {
        // Текущий = с дня после последней выплаты
        if ($lastPayDate) {
            $effFrom = date('Y-m-d', strtotime($lastPayDate . ' +1 day'));
        } else {
            $firstStmt = $pdo->prepare("SELECT MIN(DATE(created_at)) FROM services WHERE created_by_moderator=?");
            $firstStmt->execute([$mid]);
            $effFrom = $firstStmt->fetchColumn() ?: date('Y-m-d');
        }
        $effTo = date('Y-m-d');
    } else {
        $effFrom = $fromDate;
        $effTo   = $toDate;
    }

    // Статистика за период
    $statStmt = $pdo->prepare("
        SELECT
            COUNT(*) as created,
            SUM(CASE WHEN call_status = 'reached' THEN 1 ELSE 0 END) as reached,
            SUM(CASE WHEN call_status IN ('no_answer','no_number','other') THEN 1 ELSE 0 END) as not_reached
        FROM services
        WHERE created_by_moderator = ? AND DATE(created_at) BETWEEN ? AND ?
    ");
    $statStmt->execute([$mid, $effFrom, $effTo]);
    $stat = $statStmt->fetch(PDO::FETCH_ASSOC);
    $created    = (int)($stat['created']     ?? 0);
    $reached    = (int)($stat['reached']     ?? 0);
    $notReached = (int)($stat['not_reached'] ?? 0);
    $amount     = round($reached * $rateReached + $notReached * $rateNotReached, 2);

    // История выплат
    $histStmt = $pdo->prepare("SELECT * FROM moderator_payments WHERE moderator_id=? ORDER BY paid_at DESC LIMIT 5");
    $histStmt->execute([$mid]);
    $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    // Всего выплачено
    $totalPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM moderator_payments WHERE moderator_id=?");
    $totalPaidStmt->execute([$mid]);
    $totalPaid = (float)$totalPaidStmt->fetchColumn();

    $modData[] = [
        'id'           => $mid,
        'name'         => $m['name'],
        'email'        => $m['email'],
        'is_active'    => $m['is_active'],
        'rate_reached'     => $rateReached,
        'rate_not_reached' => $rateNotReached,
        'eff_from'     => $effFrom,
        'eff_to'       => $effTo,
        'last_pay'     => $lastPayDate,
        'created'      => $created,
        'reached'      => $reached,
        'not_reached'  => $notReached,
        'amount'       => $amount,
        'total_paid'   => $totalPaid,
        'history'      => $history,
    ];
}

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

ob_start();
?>

<!-- Фильтры -->
<div class="panel" style="margin-bottom:20px;">
    <div style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:6px;">Период</div>
            <div style="display:flex;gap:4px;">
                <?php foreach([
                    'current'    => 'Текущий',
                    'month'      => 'Этот месяц',
                    'last_month' => 'Прошлый месяц',
                    'custom'     => 'Даты',
                ] as $p => $l): ?>
                <a href="?period=<?php echo $p; ?>"
                   class="btn btn-sm <?php echo $period===$p?'btn-primary':'btn-secondary'; ?>">
                    <?php echo $l; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($period === 'custom'): ?>
        <form method="GET" style="display:flex;gap:8px;align-items:flex-end;">
            <input type="hidden" name="period" value="custom">
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;">С</div>
                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>" style="width:140px;">
            </div>
            <div>
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:5px;">По</div>
                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>" style="width:140px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Показать</button>
        </form>
        <?php endif; ?>
        <?php if ($period === 'current'): ?>
        <div style="margin-left:auto;font-size:13px;color:var(--text-secondary);">
            Показывает суммы с последней выплаты для каждого модератора
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="alertBox" style="margin-bottom:12px;"></div>

<!-- Карточки модераторов -->
<?php foreach ($modData as $m): ?>
<div class="panel" style="margin-bottom:20px;" id="mod-card-<?php echo $m['id']; ?>">
    <div class="panel-header">
        <div>
            <div class="panel-title" style="display:flex;align-items:center;gap:8px;">
                <?php echo htmlspecialchars($m['name']); ?>
                <span class="badge <?php echo $m['is_active'] ? 'badge-green' : 'badge-gray'; ?>" style="font-size:11px;">
                    <?php echo $m['is_active'] ? 'Активен' : 'Неактивен'; ?>
                </span>
            </div>
            <div style="font-size:12px;color:var(--text-light);margin-top:2px;">
                <?php echo htmlspecialchars($m['email']); ?> ·
                <?php echo date('d.m.Y', strtotime($m['eff_from'])); ?> — <?php echo date('d.m.Y', strtotime($m['eff_to'])); ?>
                <?php if ($m['last_pay']): ?>
                · последняя выплата: <?php echo date('d.m.Y', strtotime($m['last_pay'])); ?>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="text-align:right;">
                <div style="font-size:24px;font-weight:900;color:<?php echo $m['amount'] > 0 ? 'var(--success)' : 'var(--text-light)'; ?>;">
                    <?php echo number_format($m['amount'], 2); ?>€
                </div>
                <div style="font-size:11px;color:var(--text-light);">к оплате · всего выплачено: <?php echo number_format($m['total_paid'], 2); ?>€</div>
            </div>
            <?php if ($m['amount'] > 0): ?>
            <button class="btn btn-success"
                    onclick="openPayModal(<?php echo $m['id']; ?>, '<?php echo addslashes($m['name']); ?>', <?php echo $m['amount']; ?>, <?php echo $m['created']; ?>, '<?php echo $m['eff_from']; ?>', '<?php echo $m['eff_to']; ?>')">
                ✅ Оплатить
            </button>
            <?php else: ?>
            <button class="btn btn-secondary" disabled style="opacity:0.5;">✅ Оплатить</button>
            <?php endif; ?>
        </div>
    </div>

    <div style="padding:14px 18px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;border-bottom:1px solid var(--border-light);">
        <div style="display:flex;gap:20px;">
            <div style="text-align:center;">
                <div style="font-size:22px;font-weight:800;color:var(--text);"><?php echo $m['created']; ?></div>
                <div style="font-size:11px;color:var(--text-light);">создано</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:22px;font-weight:800;color:var(--success);"><?php echo $m['reached']; ?></div>
                <div style="font-size:11px;color:var(--text-light);">✅ дозвонились</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:22px;font-weight:800;color:var(--warning);"><?php echo $m['not_reached']; ?></div>
                <div style="font-size:11px;color:var(--text-light);">☎️ не дозвонились</div>
            </div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;font-size:13px;color:var(--text-secondary);">
            <span style="background:var(--success-bg);color:#065F46;padding:3px 10px;border-radius:6px;font-weight:700;">
                ✅ <?php echo number_format($m['rate_reached'], 2); ?>€/дозвон
            </span>
            <span style="background:var(--warning-bg);color:#92400E;padding:3px 10px;border-radius:6px;font-weight:700;">
                ☎️ <?php echo number_format($m['rate_not_reached'], 2); ?>€/остальные
            </span>
            <a href="/panel-5588/moderators.php?edit=<?php echo $m['id']; ?>" class="btn btn-secondary btn-sm">⚙️ Ставки</a>
        </div>
    </div>

    <!-- История выплат этого модератора -->
    <?php if (!empty($m['history'])): ?>
    <div style="padding:10px 18px;">
        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;">История выплат</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($m['history'] as $pay): ?>
            <div style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:6px 12px;font-size:12px;">
                <span style="font-weight:700;color:var(--success);"><?php echo number_format((float)$pay['amount'], 2); ?>€</span>
                <span style="color:var(--text-secondary);margin:0 4px;">·</span>
                <span style="color:var(--text-secondary);"><?php echo date('d.m.Y', strtotime($pay['period_from'])); ?> — <?php echo date('d.m.Y', strtotime($pay['period_to'])); ?></span>
                <span style="color:var(--text-light);margin-left:4px;"><?php echo date('d.m.Y', strtotime($pay['paid_at'])); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div style="padding:10px 18px;font-size:12px;color:var(--text-light);">Выплат ещё не было</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($modData)): ?>
<div class="panel">
    <div class="empty-state">
        <div class="empty-state-icon">👤</div>
        <div class="empty-state-title">Нет модераторов</div>
        <div class="empty-state-text">Добавьте модераторов в разделе «Модераторы»</div>
    </div>
</div>
<?php endif; ?>

<!-- Модалка оплаты -->
<div id="payOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-white);border-radius:var(--radius);padding:24px;width:100%;max-width:460px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;">✅ Подтверждение выплаты</div>
        <div id="payModName" style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Период с</label>
                <input type="date" id="payFrom" class="form-control">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Период по</label>
                <input type="date" id="payTo" class="form-control">
            </div>
        </div>

        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Сумма (€)</label>
            <input type="number" id="payAmount" class="form-control" step="0.01" min="0">
        </div>

        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Примечание (необязательно)</label>
            <input type="text" id="payNote" class="form-control" placeholder="Способ оплаты, комментарий...">
        </div>

        <input type="hidden" id="payModId">
        <input type="hidden" id="paySvcCount">

        <div style="display:flex;gap:8px;">
            <button onclick="closePayModal()" class="btn btn-secondary" style="flex:1;justify-content:center;">Отмена</button>
            <button onclick="submitPay()" class="btn btn-success" style="flex:1;justify-content:center;" id="paySubmitBtn">✅ Подтвердить выплату</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => box.innerHTML = '', 4000);
}

function openPayModal(modId, modName, amount, svcCount, periodFrom, periodTo) {
    document.getElementById('payModId').value    = modId;
    document.getElementById('paySvcCount').value = svcCount;
    document.getElementById('payAmount').value   = amount.toFixed(2);
    document.getElementById('payFrom').value     = periodFrom;
    document.getElementById('payTo').value       = periodTo;
    document.getElementById('payNote').value     = '';
    document.getElementById('payModName').textContent = modName;
    document.getElementById('payOverlay').style.display = 'flex';
}

function closePayModal() {
    document.getElementById('payOverlay').style.display = 'none';
}

async function submitPay() {
    const modId    = document.getElementById('payModId').value;
    const amount   = document.getElementById('payAmount').value;
    const svcCount = document.getElementById('paySvcCount').value;
    const from     = document.getElementById('payFrom').value;
    const to       = document.getElementById('payTo').value;
    const note     = document.getElementById('payNote').value;
    const btn      = document.getElementById('paySubmitBtn');

    if (!from || !to || !amount) { alert('Заполните все поля'); return; }
    btn.disabled = true; btn.textContent = '...';

    try {
        const fd = new FormData();
        fd.append('action', 'pay');
        fd.append('moderator_id', modId);
        fd.append('amount', amount);
        fd.append('services_count', svcCount);
        fd.append('period_from', from);
        fd.append('period_to', to);
        fd.append('note', note);
        const res = await fetch('/panel-5588/moderator-earnings.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.ok) {
            closePayModal();
            showAlert('✅ Выплата зафиксирована! Страница обновится...', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            alert('Ошибка: ' + data.error);
        }
    } catch(e) {
        alert('Ошибка сети');
    }
    btn.disabled = false; btn.textContent = '✅ Подтвердить выплату';
}

document.getElementById('payOverlay').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Заработок модераторов', $content, $pendingCount, 0, $pendingReviewCount);
?>
