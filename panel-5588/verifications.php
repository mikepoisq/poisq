<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

// ── AJAX обработка действий ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/../config/email.php';

    $reqId   = (int)($_POST['request_id'] ?? 0);
    $action  = $_POST['ajax_action'];
    $comment = trim($_POST['comment'] ?? '');

    $stmt = $pdo->prepare("
        SELECT vr.*, s.name as service_name, s.country_code, s.id as svc_id,
               u.name as user_name, u.email as user_email
        FROM verification_requests vr
        JOIN services s ON vr.service_id = s.id
        JOIN users u ON vr.user_id = u.id
        WHERE vr.id = ? AND vr.status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$reqId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['ok' => false, 'error' => 'Заявка не найдена']);
        exit;
    }

    if ($action === 'approve') {
        $until = date('Y-m-d', strtotime('+3 months'));

        $pdo->prepare("UPDATE verification_requests SET status='approved', reviewed_at=NOW() WHERE id=?")
            ->execute([$reqId]);
        $pdo->prepare("UPDATE services SET verified=1, verified_until=? WHERE id=?")
            ->execute([$until, $req['svc_id']]);

        $svcUrl = 'https://poisq.com/service/' . $req['svc_id'];
        sendVerificationApprovedEmail($req['user_email'], $req['user_name'], $req['service_name'], $svcUrl, $until);

        echo json_encode(['ok' => true, 'status' => 'approved', 'until' => $until]);
    } elseif ($action === 'reject') {
        if (!$comment) {
            echo json_encode(['ok' => false, 'error' => 'Укажите причину']);
            exit;
        }

        $pdo->prepare("UPDATE verification_requests SET status='rejected', admin_comment=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$comment, $reqId]);
        $pdo->prepare("UPDATE services SET verified=0 WHERE id=?")
            ->execute([$req['svc_id']]);

        sendVerificationRejectedEmail($req['user_email'], $req['user_name'], $req['service_name'], $comment);

        echo json_encode(['ok' => true, 'status' => 'rejected']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Неизвестное действие']);
    }
    exit;
}

// ── Фильтрация ───────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'all';
$allowed      = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filterStatus, $allowed)) $filterStatus = 'all';

$where  = [];
$params = [];
if ($filterStatus !== 'all') {
    $where[]  = "vr.status = ?";
    $params[] = $filterStatus;
}
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$requests = $pdo->prepare("
    SELECT vr.id, vr.service_id, vr.document_path, vr.document_original_name,
           vr.status, vr.admin_comment, vr.created_at, vr.reviewed_at,
           s.name AS service_name, s.country_code, s.verified, s.verified_until,
           u.id AS user_id, u.name AS user_name, u.email AS user_email
    FROM verification_requests vr
    JOIN services s ON vr.service_id = s.id
    JOIN users u ON vr.user_id = u.id
    $whereSql
    ORDER BY FIELD(vr.status,'pending','rejected','approved'), vr.created_at ASC
");
$requests->execute($params);
$requests = $requests->fetchAll(PDO::FETCH_ASSOC);

$pendingVerifCount  = (int)$pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();
$pendingCount       = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

ob_start();
?>

<div class="panel">
  <div class="panel-header">
    <div class="panel-title">⭐ Заявки на значок Проверено</div>
    <div class="panel-actions">
      <?php foreach (['all'=>'Все','pending'=>'Ожидают','approved'=>'Одобрены','rejected'=>'Отклонены'] as $s=>$l): ?>
      <a href="?status=<?php echo $s; ?>" class="chip <?php echo $filterStatus===$s?'active':''; ?>">
        <?php echo $l; ?>
        <?php if ($s==='pending' && $pendingVerifCount > 0): ?>
        <span style="background:var(--warning);color:white;font-size:10px;padding:1px 5px;border-radius:99px;margin-left:2px;"><?php echo $pendingVerifCount; ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (empty($requests)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">⭐</div>
    <div class="empty-state-title">Заявок нет</div>
    <div class="empty-state-text">Нет заявок с выбранным статусом</div>
  </div>
  <?php else: ?>
  <div class="panel-body" style="overflow-x:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Дата</th>
          <th>Пользователь</th>
          <th>Сервис</th>
          <th>Документ</th>
          <th>Статус</th>
          <th style="width:160px">Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
      <tr id="row-<?php echo $r['id']; ?>">
        <td style="font-size:12px;color:var(--text-light);white-space:nowrap;">
          <?php echo date('d.m.Y', strtotime($r['created_at'])); ?><br>
          <span style="font-size:11px;"><?php echo date('H:i', strtotime($r['created_at'])); ?></span>
        </td>
        <td>
          <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($r['user_name']); ?></div>
          <div style="font-size:11px;color:var(--text-light);"><?php echo htmlspecialchars($r['user_email']); ?></div>
        </td>
        <td>
          <div style="font-size:13px;font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <a href="https://poisq.com/service/<?php echo $r['service_id']; ?>" target="_blank"
               style="color:var(--primary);text-decoration:none;"><?php echo htmlspecialchars($r['service_name']); ?></a>
          </div>
          <div style="font-size:11px;color:var(--text-light);">#<?php echo $r['service_id']; ?> · <?php echo strtoupper($r['country_code']); ?></div>
          <?php if ($r['verified'] && $r['verified_until']): ?>
          <div style="font-size:11px;color:var(--success);">✓ до <?php echo date('d.m.Y', strtotime($r['verified_until'])); ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['document_path']): ?>
          <a href="/panel-5588/get-document.php?file=<?php echo urlencode(basename($r['document_path'])); ?>" target="_blank" class="btn btn-secondary btn-sm">
            📄 Просмотреть
          </a>
          <?php if ($r['document_original_name']): ?>
          <div style="font-size:11px;color:var(--text-light);margin-top:4px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($r['document_original_name']); ?></div>
          <?php endif; ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
          <span class="badge badge-yellow">⏳ Ожидает</span>
          <?php elseif ($r['status'] === 'approved'): ?>
          <span class="badge badge-green">✓ Одобрено</span>
          <?php if ($r['reviewed_at']): ?>
          <div style="font-size:11px;color:var(--text-light);margin-top:4px;"><?php echo date('d.m.Y', strtotime($r['reviewed_at'])); ?></div>
          <?php endif; ?>
          <?php else: ?>
          <span class="badge badge-red">✕ Отклонено</span>
          <?php if ($r['admin_comment']): ?>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:4px;max-width:140px;line-height:1.4;"><?php echo htmlspecialchars($r['admin_comment']); ?></div>
          <?php endif; ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
          <div style="display:flex;flex-direction:column;gap:6px;">
            <button class="btn btn-success btn-sm" onclick="approveRequest(<?php echo $r['id']; ?>)">
              <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
              Одобрить
            </button>
            <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?php echo $r['id']; ?>)">
              <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
              Отклонить
            </button>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Модалка отклонения -->
<div id="rejectOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--bg-white);border-radius:var(--radius);padding:24px;width:100%;max-width:440px;margin:20px;">
    <div style="font-size:16px;font-weight:700;margin-bottom:16px;">Причина отклонения</div>
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
      <?php foreach ([
        'Документ нечитаемый',
        'Документ не подходит (нужен диплом или лицензия)',
        'Документ не соответствует сервису',
        'Другое',
      ] as $reason): ?>
      <button class="reason-btn" onclick="selectReason(this, '<?php echo addslashes($reason); ?>')"
        style="text-align:left;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);font-size:13px;cursor:pointer;font-family:inherit;transition:all 0.15s;">
        <?php echo $reason; ?>
      </button>
      <?php endforeach; ?>
    </div>
    <textarea id="rejectComment" placeholder="Или напишите свою причину..."
      style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;resize:none;height:80px;outline:none;margin-bottom:14px;"></textarea>
    <div style="display:flex;gap:8px;">
      <button onclick="closeRejectModal()" class="btn btn-secondary" style="flex:1;justify-content:center;">Отмена</button>
      <button onclick="submitReject()" class="btn btn-danger" style="flex:1;justify-content:center;background:var(--danger);color:white;">Отклонить</button>
    </div>
  </div>
</div>

<script>
let currentRejectId = null;

function approveRequest(id) {
    if (!confirm('Одобрить заявку #' + id + '? Значок будет выдан на 3 месяца.')) return;
    fetch('/panel-5588/verifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=approve&request_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const row = document.getElementById('row-' + id);
            row.querySelector('td:nth-child(5)').innerHTML =
                '<span class="badge badge-green">✓ Одобрено</span><div style="font-size:11px;color:var(--text-light);margin-top:4px;">до ' + formatDate(data.until) + '</div>';
            row.querySelector('td:nth-child(6)').innerHTML = '';
        } else {
            alert(data.error || 'Ошибка');
        }
    })
    .catch(() => alert('Ошибка соединения'));
}

function openRejectModal(id) {
    currentRejectId = id;
    document.getElementById('rejectComment').value = '';
    document.querySelectorAll('.reason-btn').forEach(b => {
        b.style.background = 'var(--bg)';
        b.style.borderColor = 'var(--border)';
    });
    document.getElementById('rejectOverlay').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectOverlay').style.display = 'none';
    currentRejectId = null;
}

function selectReason(btn, text) {
    document.querySelectorAll('.reason-btn').forEach(b => {
        b.style.background = 'var(--bg)';
        b.style.borderColor = 'var(--border)';
    });
    btn.style.background = 'var(--danger-bg)';
    btn.style.borderColor = 'var(--danger)';
    document.getElementById('rejectComment').value = text;
}

function submitReject() {
    const comment = document.getElementById('rejectComment').value.trim();
    if (!comment) { alert('Укажите причину отклонения'); return; }
    fetch('/panel-5588/verifications.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=reject&request_id=' + currentRejectId + '&comment=' + encodeURIComponent(comment)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const row = document.getElementById('row-' + currentRejectId);
            row.querySelector('td:nth-child(5)').innerHTML =
                '<span class="badge badge-red">✕ Отклонено</span><div style="font-size:11px;color:var(--text-secondary);margin-top:4px;max-width:140px;line-height:1.4;">' +
                escHtml(comment) + '</div>';
            row.querySelector('td:nth-child(6)').innerHTML = '';
            closeRejectModal();
        } else {
            alert(data.error || 'Ошибка');
        }
    })
    .catch(() => alert('Ошибка соединения'));
}

document.getElementById('rejectOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return d + '.' + m + '.' + y;
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php
$content = ob_get_clean();
renderLayout('Проверки', $content, $pendingCount, $pendingVerifCount, $pendingReviewCount);
?>
