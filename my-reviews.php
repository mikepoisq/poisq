<?php
// my-reviews.php — Отзывы о сервисах владельца
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

function _reviewWord(int $n): string {
    $n  = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return 'отзывов';
    if ($n1 > 1 && $n1 < 5) return 'отзыва';
    if ($n1 === 1) return 'отзыв';
    return 'отзывов';
}

$userId    = (int)$_SESSION['user_id'];
$userName  = $_SESSION['user_name']   ?? 'Пользователь';
$userAvatar = $_SESSION['user_avatar'] ?? '';

try {
    $pdo = getDbConnection();

    // Одобренные сервисы пользователя
    $stmtSvcs = $pdo->prepare("
        SELECT id, name, rating, reviews_count
        FROM services
        WHERE user_id = ? AND status = 'approved'
        ORDER BY name
    ");
    $stmtSvcs->execute([$userId]);
    $myServices = $stmtSvcs->fetchAll(PDO::FETCH_ASSOC);

    if (empty($myServices)) {
        header('Location: profile.php');
        exit;
    }

    // Отзывы по каждому сервису
    foreach ($myServices as &$svc) {
        $stmtRev = $pdo->prepare("
            SELECT r.id, r.rating, r.text, r.photo, r.created_at,
                   u.name  AS author_name,
                   u.avatar AS author_avatar,
                   rop.id     AS reply_id,
                   rop.text   AS reply_text,
                   rop.status AS reply_status
            FROM reviews r
            LEFT JOIN users u   ON u.id = r.user_id
            LEFT JOIN review_owner_replies rop ON rop.review_id = r.id
            WHERE r.service_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC
        ");
        $stmtRev->execute([$svc['id']]);
        $svc['reviews'] = $stmtRev->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($svc);

} catch (PDOException $e) {
    error_log('my-reviews.php DB error: ' . $e->getMessage());
    $myServices = [];
}

$pageTitle = 'Отзывы о моих сервисах';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($pageTitle) ?> — Poisq</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
:root {
    --primary: #2E73D8; --primary-light: #5EA1F0; --primary-dark: #1A5AB8;
    --text: #1F2937; --text-secondary: #9CA3AF; --text-light: #6B7280;
    --bg: #FFFFFF; --bg-secondary: #F5F5F7; --border: #D1D5DB; --border-light: #E5E7EB;
    --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
}
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: var(--bg-secondary); color: var(--text); line-height: 1.5;
    padding-bottom: 40px;
}
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; }

/* Header */
.page-header {
    position: sticky; top: 0; z-index: 100;
    background: var(--bg); border-bottom: 1px solid var(--border-light);
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; height: 56px;
}
.btn-back {
    width: 40px; height: 40px; border-radius: 12px; border: none;
    background: var(--bg-secondary); display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
}
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); fill: none; stroke-width: 2; }
.page-title { font-size: 17px; font-weight: 700; color: var(--text); }

/* Service group */
.svc-group { margin-bottom: 8px; }
.svc-group-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-secondary);
}
.svc-group-name { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
.svc-group-meta { font-size: 12px; color: var(--text-secondary); display: flex; gap: 10px; align-items: center; }
.svc-rating { color: var(--warning); font-size: 14px; }

/* Review card */
.review-card { padding: 14px 16px; border-bottom: 1px solid var(--border-light); }
.review-card:last-child { border-bottom: none; }
.review-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px; }
.review-author-row { display: flex; align-items: center; gap: 10px; }
.rev-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.rev-avatar-ph {
    width: 36px; height: 36px; border-radius: 50%; background: var(--primary);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; flex-shrink: 0;
}
.rev-name { font-size: 14px; font-weight: 600; color: var(--text); }
.rev-date { font-size: 12px; color: var(--text-secondary); }
.rev-stars { font-size: 16px; color: var(--warning); flex-shrink: 0; }
.rev-text { font-size: 14px; color: var(--text); line-height: 1.5; margin-bottom: 8px; }
.rev-photo { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; cursor: pointer; margin-bottom: 8px; display: block; }

/* Owner reply */
.owner-reply-block {
    margin-top: 8px; padding: 10px 12px;
    background: var(--bg-secondary); border-radius: 10px;
    border-left: 3px solid var(--primary);
}
.owner-reply-label {
    font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 4px;
    display: flex; align-items: center; justify-content: space-between;
}
.reply-status-badge {
    font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 99px;
    text-transform: uppercase;
}
.reply-status-badge.pending { background: #FEF3C7; color: #92400E; }
.reply-status-badge.approved { background: #D1FAE5; color: #065F46; }
.owner-reply-text { font-size: 13px; color: var(--text); line-height: 1.5; }

/* Inline reply form */
.reply-form { margin-top: 10px; }
.reply-textarea {
    width: 100%; min-height: 80px;
    border: 1.5px solid var(--border); border-radius: 12px;
    padding: 10px 12px; font-family: inherit; font-size: 14px;
    color: var(--text); background: var(--bg-secondary);
    resize: none; outline: none; transition: border-color 0.2s;
}
.reply-textarea:focus { border-color: var(--primary); background: white; }
.btn-reply-submit {
    margin-top: 8px; padding: 10px 20px; border-radius: 10px; border: none;
    background: var(--primary); color: white;
    font-family: inherit; font-size: 14px; font-weight: 700;
    cursor: pointer; transition: all 0.15s;
}
.btn-reply-submit:active { opacity: 0.85; }
.btn-reply-submit:disabled { opacity: 0.6; }
.btn-open-reply {
    margin-top: 8px; padding: 7px 14px; border-radius: 8px;
    border: 1.5px solid var(--border); background: var(--bg-secondary);
    font-family: inherit; font-size: 13px; font-weight: 600; color: var(--text-light);
    cursor: pointer; transition: all 0.15s;
}
.btn-open-reply:hover { border-color: var(--primary); color: var(--primary); }

.no-reviews {
    padding: 20px 16px; color: var(--text-secondary); font-size: 14px; text-align: center;
}

/* Empty */
.empty-block { text-align: center; padding: 48px 24px; }
.empty-block-icon { font-size: 48px; margin-bottom: 14px; }
.empty-block-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
.empty-block-text { font-size: 14px; color: var(--text-secondary); }

/* Toast */
#toastMsg {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    background: #1F2937; color: white; padding: 10px 20px;
    border-radius: 999px; font-size: 14px; font-weight: 600;
    z-index: 999; opacity: 0; transition: opacity 0.3s;
    white-space: nowrap; pointer-events: none;
}

@media (min-width: 1024px) {
  .app-container {
    max-width: 760px;
    padding-top: 0;
  }
  .page-header { padding: 10px 24px; }
  .svc-group-header { padding: 14px 24px 10px; }
  .review-card { padding: 16px 24px; }
}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">
    <header class="page-header">
        <button class="btn-back" onclick="history.back()">
            <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="page-title">Отзывы о моих сервисах</div>
    </header>

    <?php if (empty($myServices)): ?>
    <div class="empty-block">
        <div class="empty-block-icon">💬</div>
        <div class="empty-block-title">Нет сервисов</div>
        <div class="empty-block-text">У вас нет опубликованных сервисов</div>
    </div>
    <?php else: ?>

    <?php foreach ($myServices as $svc): ?>
    <div class="svc-group">
        <div class="svc-group-header">
            <div class="svc-group-name"><?= htmlspecialchars($svc['name']) ?></div>
            <div class="svc-group-meta">
                <?php if ($svc['reviews_count'] > 0): ?>
                <span class="svc-rating"><?= str_repeat('★', min(5, round($svc['rating']))) ?></span>
                <span><?= number_format($svc['rating'], 1) ?> · <?= $svc['reviews_count'] ?> <?= _reviewWord($svc['reviews_count']) ?></span>
                <?php else: ?>
                <span>Отзывов пока нет</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($svc['reviews'])): ?>
        <div class="no-reviews">Отзывов ещё нет</div>
        <?php else: ?>
        <?php foreach ($svc['reviews'] as $rev): ?>
        <div class="review-card" id="rcard-<?= $rev['id'] ?>">
            <div class="review-card-top">
                <div class="review-author-row">
                    <?php
                    $rName    = $rev['author_name'] ?? 'Пользователь';
                    $rInitial = mb_strtoupper(mb_substr($rName, 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <?php if (!empty($rev['author_avatar'])): ?>
                    <img src="<?= htmlspecialchars($rev['author_avatar']) ?>" class="rev-avatar" alt="">
                    <?php else: ?>
                    <div class="rev-avatar-ph"><?= htmlspecialchars($rInitial) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="rev-name"><?= htmlspecialchars($rName) ?></div>
                        <div class="rev-date"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
                    </div>
                </div>
                <div class="rev-stars">
                    <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                </div>
            </div>

            <div class="rev-text"><?= nl2br(htmlspecialchars($rev['text'])) ?></div>

            <?php if (!empty($rev['photo'])): ?>
            <img src="<?= htmlspecialchars($rev['photo']) ?>" class="rev-photo"
                 onclick="window.open(this.src,'_blank')" alt="Фото к отзыву">
            <?php endif; ?>

            <?php if ($rev['reply_id']): ?>
            <!-- Ответ уже есть -->
            <div class="owner-reply-block">
                <div class="owner-reply-label">
                    Ваш ответ
                    <?php if ($rev['reply_status'] === 'pending'): ?>
                    <span class="reply-status-badge pending">На модерации</span>
                    <?php else: ?>
                    <span class="reply-status-badge approved">Опубликован</span>
                    <?php endif; ?>
                </div>
                <div class="owner-reply-text"><?= nl2br(htmlspecialchars($rev['reply_text'])) ?></div>
            </div>
            <?php else: ?>
            <!-- Кнопка и форма ответа -->
            <button class="btn-open-reply" onclick="toggleReplyForm(<?= $rev['id'] ?>)">
                Ответить на отзыв
            </button>
            <div class="reply-form" id="reply-form-<?= $rev['id'] ?>" style="display:none;">
                <textarea class="reply-textarea" id="reply-text-<?= $rev['id'] ?>"
                          placeholder="Напишите ответ клиенту…" rows="3"></textarea>
                <button class="btn-reply-submit" id="reply-btn-<?= $rev['id'] ?>"
                        onclick="submitReply(<?= $rev['id'] ?>)">
                    Отправить ответ
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

<div id="toastMsg"></div>
<script>
function showToast(msg) {
    const t = document.getElementById('toastMsg');
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity = '0'; }, 3000);
}

function toggleReplyForm(reviewId) {
    const form = document.getElementById('reply-form-' + reviewId);
    const isOpen = form.style.display !== 'none';
    form.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        setTimeout(() => document.getElementById('reply-text-' + reviewId)?.focus(), 50);
    }
}

async function submitReply(reviewId) {
    const text = document.getElementById('reply-text-' + reviewId).value.trim();
    if (text.length < 5) {
        showToast('Ответ слишком короткий');
        return;
    }

    const btn = document.getElementById('reply-btn-' + reviewId);
    btn.disabled = true;
    btn.textContent = 'Отправляем…';

    const fd = new FormData();
    fd.append('action', 'submit_owner_reply');
    fd.append('review_id', reviewId);
    fd.append('text', text);

    try {
        const res  = await fetch('/api/reviews.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            // Заменяем форму на блок ответа со статусом "на модерации"
            const card = document.getElementById('rcard-' + reviewId);
            const replyArea = card.querySelector('.btn-open-reply');
            const form = document.getElementById('reply-form-' + reviewId);

            const replyBlock = document.createElement('div');
            replyBlock.className = 'owner-reply-block';
            replyBlock.innerHTML = `
                <div class="owner-reply-label">
                    Ваш ответ
                    <span class="reply-status-badge pending">На модерации</span>
                </div>
                <div class="owner-reply-text">${text.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}</div>
            `;

            replyArea?.remove();
            form?.remove();
            card.appendChild(replyBlock);

            showToast('Ответ отправлен на модерацию');
        } else {
            showToast(data.error || 'Произошла ошибка');
            btn.disabled = false;
            btn.textContent = 'Отправить ответ';
        }
    } catch (e) {
        showToast('Ошибка соединения');
        btn.disabled = false;
        btn.textContent = 'Отправить ответ';
    }
}
</script>
</body>
</html>
