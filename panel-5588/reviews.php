<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/../config/email.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

$pendingCount       = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = 0;
try {
    $pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {}

// ── AJAX ACTIONS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action   = $_POST['action']    ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $replyId  = (int)($_POST['reply_id']  ?? 0);

    try {
        // ── Одобрить отзыв ──────────────────────────────────────────
        if ($action === 'approve_review' && $reviewId) {
            $pdo->prepare("UPDATE reviews SET status='approved', moderation_comment=NULL WHERE id=?")
                ->execute([$reviewId]);

            $row = $pdo->prepare("
                SELECT r.service_id, r.text, r.rating,
                       s.name AS svc_name,
                       u.email AS user_email, u.name AS user_name
                FROM reviews r
                JOIN services s ON s.id = r.service_id
                JOIN users u ON u.id = r.user_id
                WHERE r.id = ?
            ");
            $row->execute([$reviewId]);
            $info = $row->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                recalculateServiceRating($info['service_id'], $pdo);
                try {
                    sendReviewStatusEmail($info['user_email'], $info['user_name'], $info['svc_name'], 'approved');
                } catch (Exception $e) {}
            }

            echo json_encode(['success' => true]);
            exit;
        }

        // ── Отклонить отзыв ─────────────────────────────────────────
        if ($action === 'reject_review' && $reviewId) {
            $comment = trim($_POST['comment'] ?? '');
            $pdo->prepare("UPDATE reviews SET status='rejected', moderation_comment=? WHERE id=?")
                ->execute([$comment ?: null, $reviewId]);

            $row = $pdo->prepare("
                SELECT r.service_id,
                       s.name AS svc_name,
                       u.email AS user_email, u.name AS user_name
                FROM reviews r
                JOIN services s ON s.id = r.service_id
                JOIN users u ON u.id = r.user_id
                WHERE r.id = ?
            ");
            $row->execute([$reviewId]);
            $info = $row->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                recalculateServiceRating($info['service_id'], $pdo);
                try {
                    sendReviewStatusEmail($info['user_email'], $info['user_name'], $info['svc_name'], 'rejected', $comment);
                } catch (Exception $e) {}
            }

            echo json_encode(['success' => true]);
            exit;
        }

        // ── Удалить отзыв ───────────────────────────────────────────
        if ($action === 'delete_review' && $reviewId) {
            $row = $pdo->prepare("SELECT service_id FROM reviews WHERE id=?");
            $row->execute([$reviewId]);
            $info = $row->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([$reviewId]);

            if ($info) recalculateServiceRating($info['service_id'], $pdo);

            echo json_encode(['success' => true]);
            exit;
        }

        // ── Удалить ответ владельца ─────────────────────────────────
        if ($action === 'delete_reply' && $replyId) {
            $pdo->prepare("DELETE FROM review_owner_replies WHERE id=?")->execute([$replyId]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
    } catch (PDOException $e) {
        error_log('Admin reviews AJAX error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
    }
    exit;
}

// ── FILTERS & PAGINATION ─────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'pending';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses)) $statusFilter = 'pending';

$where  = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[]  = "r.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $like     = '%' . $search . '%';
    $where[]  = "(s.name LIKE ? OR u.name LIKE ? OR r.text LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("
    SELECT COUNT(*) FROM reviews r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.user_id
    $whereSQL
");
$total->execute($params);
$total      = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.text, r.photo, r.status, r.moderation_comment,
           r.created_at, r.edited_until,
           s.id AS service_id, s.name AS service_name,
           u.id AS user_id, u.name AS user_name, u.email AS user_email,
           rop.id AS reply_id, rop.text AS reply_text
    FROM reviews r
    JOIN services s ON s.id = r.service_id
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN review_owner_replies rop ON rop.review_id = r.id
    $whereSQL
    ORDER BY r.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts per status for filter chips (no search applied to counts)
$counts = [];
foreach (['pending', 'approved', 'rejected'] as $s) {
    try {
        $counts[$s] = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='$s'")->fetchColumn();
    } catch (Exception $e) { $counts[$s] = 0; }
}
$counts['all'] = array_sum($counts);

// ── BUILD PAGE ───────────────────────────────────────────────────────────────
$filterQuery = http_build_query(array_filter(['status' => $statusFilter, 'search' => $search]));
ob_start();
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
        <h2 style="font-size:20px;font-weight:800;color:var(--text);margin-bottom:4px;">Отзывы пользователей</h2>
        <p style="font-size:13px;color:var(--text-secondary);">Всего: <?= $counts['all'] ?> · Ожидают: <?= $counts['pending'] ?></p>
    </div>
</div>

<!-- Фильтры -->
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header">
        <div class="filter-chips">
            <?php
            $labels = ['all' => 'Все', 'pending' => 'Ожидают', 'approved' => 'Одобрены', 'rejected' => 'Отклонены'];
            foreach ($labels as $s => $label):
            ?>
            <a href="?status=<?= $s ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
               class="chip <?= $statusFilter === $s ? 'active' : '' ?>">
                <?= $label ?> (<?= $counts[$s] ?? 0 ?>)
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Поиск -->
<form method="get" action="" style="margin-bottom:20px;">
    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
    <div style="position:relative;max-width:420px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-secondary);pointer-events:none;">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
               placeholder="Поиск по сервису, автору, тексту…"
               style="width:100%;padding:9px 12px 9px 36px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;"
               onfocus="this.style.borderColor='var(--primary)'"
               onblur="this.style.borderColor='var(--border)'">
        <?php if ($search): ?>
        <a href="?status=<?= htmlspecialchars($statusFilter) ?>"
           style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--text-secondary);text-decoration:none;font-size:16px;line-height:1;"
           title="Сбросить поиск">✕</a>
        <?php endif; ?>
    </div>
</form>

<!-- Таблица отзывов -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">💬 Отзывы
            <?php if ($statusFilter !== 'all'): ?>
            — <span style="color:var(--text-secondary);font-weight:500;"><?= $labels[$statusFilter] ?></span>
            <?php endif; ?>
            <?php if ($search): ?>
            — <span style="color:var(--primary);font-weight:500;">«<?= htmlspecialchars($search) ?>»</span>
            <?php endif; ?>
        </div>
        <span style="font-size:13px;color:var(--text-secondary);"><?= $total ?> записей</span>
    </div>
    <div class="panel-body">
        <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <div class="empty-state-title">Нет отзывов</div>
            <div class="empty-state-text">В этой категории пока ничего нет</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>Дата</th>
                <th>Сервис</th>
                <th>Автор</th>
                <th>Оценка</th>
                <th>Текст / Фото / Ответ</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr></thead>
            <tbody>
            <?php foreach ($reviews as $rev): ?>
            <tr id="rev-row-<?= $rev['id'] ?>">
                <td style="white-space:nowrap;font-size:12px;color:var(--text-light);">
                    <?= date('d.m.Y', strtotime($rev['created_at'])) ?><br>
                    <?= date('H:i', strtotime($rev['created_at'])) ?>
                </td>
                <td>
                    <a href="https://poisq.com<?= htmlspecialchars(serviceUrl($rev['service_id'], $rev['service_name'])) ?>"
                       target="_blank" style="font-weight:600;font-size:13px;color:var(--primary);text-decoration:none;">
                        <?= htmlspecialchars($rev['service_name']) ?>
                    </a>
                    <div style="font-size:11px;color:var(--text-light);">#<?= $rev['service_id'] ?></div>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($rev['user_name'] ?: '🗑 Пользователь удалён') ?></div>
                    <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($rev['user_email'] ?: '—') ?></div>
                </td>
                <td style="white-space:nowrap;">
                    <span style="color:#F59E0B;font-size:16px;letter-spacing:-1px;">
                        <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                    </span>
                    <span style="font-size:12px;color:var(--text-secondary);margin-left:4px;"><?= $rev['rating'] ?>/5</span>
                </td>
                <td style="max-width:280px;">
                    <div style="font-size:13px;color:var(--text);line-height:1.4;">
                        <?= htmlspecialchars(mb_substr($rev['text'], 0, 120, 'UTF-8')) ?><?= mb_strlen($rev['text'], 'UTF-8') > 120 ? '…' : '' ?>
                    </div>
                    <?php if ($rev['photo']): ?>
                    <img src="<?= htmlspecialchars($rev['photo']) ?>"
                         style="width:50px;height:50px;object-fit:cover;border-radius:6px;margin-top:6px;cursor:pointer;"
                         onclick="window.open('<?= htmlspecialchars($rev['photo']) ?>','_blank')"
                         title="Фото к отзыву">
                    <?php endif; ?>
                    <?php if ($rev['moderation_comment']): ?>
                    <div style="font-size:11px;color:var(--danger);margin-top:4px;">
                        Причина отклонения: <?= htmlspecialchars($rev['moderation_comment']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($rev['reply_id']): ?>
                    <div id="reply-block-<?= $rev['reply_id'] ?>"
                         style="margin-top:8px;padding:8px 10px;background:#F0F7FF;border-left:3px solid var(--primary);border-radius:4px;font-size:12px;color:var(--text);">
                        <div style="font-weight:600;color:var(--primary);margin-bottom:3px;font-size:11px;">💼 Ответ владельца:</div>
                        <?= htmlspecialchars(mb_substr($rev['reply_text'], 0, 150, 'UTF-8')) ?><?= mb_strlen($rev['reply_text'], 'UTF-8') > 150 ? '…' : '' ?>
                        <div style="margin-top:6px;">
                            <button class="btn btn-danger btn-sm" style="font-size:11px;padding:3px 8px;"
                                    onclick="deleteReply(<?= $rev['reply_id'] ?>)">
                                Удалить ответ
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rev['status'] === 'pending'): ?>
                    <span class="badge badge-yellow">⏳ Ожидает</span>
                    <?php elseif ($rev['status'] === 'approved'): ?>
                    <span class="badge badge-green">✓ Одобрен</span>
                    <?php else: ?>
                    <span class="badge badge-red">✗ Отклонён</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($rev['status'] === 'pending'): ?>
                        <button class="btn btn-success btn-sm"
                                onclick="approveReview(<?= $rev['id'] ?>)">
                            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                            Одобрить
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="openRejectModal(<?= $rev['id'] ?>)">
                            <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            Отклонить
                        </button>
                    <?php endif; ?>
                    <?php if ($rev['status'] === 'approved'): ?>
                        <button class="btn btn-danger btn-sm"
                                onclick="deleteReview(<?= $rev['id'] ?>)">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            Удалить
                        </button>
                    <?php endif; ?>
                    <?php if ($rev['status'] === 'rejected'): ?>
                        <button class="btn btn-success btn-sm"
                                onclick="approveReview(<?= $rev['id'] ?>)">
                            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                            Одобрить
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="deleteReview(<?= $rev['id'] ?>)">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                            Удалить
                        </button>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Пагинация -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?= $filterQuery ?>&page=<?= $page - 1 ?>" class="page-link">← Назад</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?<?= $filterQuery ?>&page=<?= $p ?>"
               class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?<?= $filterQuery ?>&page=<?= $page + 1 ?>" class="page-link">Вперёд →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Модалка отклонения отзыва -->
<div id="rejectModal" style="
    display:none; position:fixed; inset:0; z-index:1000;
    background:rgba(0,0,0,0.5);
    align-items:center; justify-content:center;">
    <div style="background:white;border-radius:12px;padding:24px;width:480px;max-width:95vw;">
        <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;">Отклонить отзыв</h3>
        <input type="hidden" id="rejectReviewId">

        <div style="margin-bottom:12px;">
            <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">
                Причина (шаблоны):
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                <?php
                $presets = [
                    'Отзыв содержит недопустимый контент',
                    'Похоже на рекламу или спам',
                    'Отзыв не относится к данному сервису',
                ];
                foreach ($presets as $preset): ?>
                <button type="button" class="chip" onclick="setRejectReason(<?= json_encode($preset) ?>)">
                    <?= htmlspecialchars($preset) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <textarea id="rejectComment" class="form-control"
                      placeholder="Введите причину отклонения или оставьте пустым…"
                      rows="3"></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
            <button class="btn btn-secondary" onclick="closeRejectModal()">Отмена</button>
            <button class="btn btn-danger" onclick="submitReject()">
                <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                Отклонить
            </button>
        </div>
    </div>
</div>

<script>
function showToast(msg, ok = true) {
    let t = document.getElementById('adminToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'adminToast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;z-index:9999;opacity:0;transition:opacity 0.3s;pointer-events:none;';
        document.body.appendChild(t);
    }
    t.style.background = ok ? '#10B981' : '#EF4444';
    t.style.color = 'white';
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.opacity = '0'; }, 3000);
}

async function doAction(data) {
    data.ajax = '1';
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch('', { method: 'POST', body: fd });
    return res.json();
}

async function approveReview(id) {
    if (!confirm('Одобрить отзыв?')) return;
    const data = await doAction({ action: 'approve_review', review_id: id });
    if (data.success) {
        document.getElementById('rev-row-' + id)?.remove();
        showToast('Отзыв одобрен');
    } else {
        showToast(data.error || 'Ошибка', false);
    }
}

async function deleteReview(id) {
    if (!confirm('Удалить отзыв? Действие нельзя отменить.')) return;
    const data = await doAction({ action: 'delete_review', review_id: id });
    if (data.success) {
        document.getElementById('rev-row-' + id)?.remove();
        showToast('Отзыв удалён');
    } else {
        showToast(data.error || 'Ошибка', false);
    }
}

async function deleteReply(id) {
    if (!confirm('Удалить ответ владельца?')) return;
    const data = await doAction({ action: 'delete_reply', reply_id: id });
    if (data.success) {
        document.getElementById('reply-block-' + id)?.remove();
        showToast('Ответ удалён');
    } else {
        showToast(data.error || 'Ошибка', false);
    }
}

function openRejectModal(id) {
    document.getElementById('rejectReviewId').value = id;
    document.getElementById('rejectComment').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
function setRejectReason(text) {
    document.getElementById('rejectComment').value = text;
}
async function submitReject() {
    const id      = document.getElementById('rejectReviewId').value;
    const comment = document.getElementById('rejectComment').value.trim();
    const data = await doAction({ action: 'reject_review', review_id: id, comment });
    if (data.success) {
        closeRejectModal();
        document.getElementById('rev-row-' + id)?.remove();
        showToast('Отзыв отклонён');
    } else {
        showToast(data.error || 'Ошибка', false);
    }
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Комментарии', $content, $pendingCount, 0, $pendingReviewCount);
?>
