<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/database.php';
$pdo = getDbConnection();

// Действия модерации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($id && in_array($action, ['approve','reject'])) {
        if ($action === 'approve') {
            // Переносим в articles
            $sub = $pdo->prepare("SELECT * FROM article_submissions WHERE id=?")->execute([$id]) ? null : null;
            $stmt = $pdo->prepare("SELECT * FROM article_submissions WHERE id=? AND status='pending' LIMIT 1");
            $stmt->execute([$id]);
            $sub = $stmt->fetch();
            if ($sub) {
                // Генерируем slug
                $slug = mb_strtolower($sub['title'], 'UTF-8');
                $ru = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
                $en = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
                $slug = str_replace($ru, $en, $slug);
                $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
                $slug = trim($slug, '-');
                $slug = substr($slug, 0, 80);
                // Уникальность slug
                $base = $slug; $i = 1;
                while ($pdo->prepare("SELECT id FROM articles WHERE slug=?")->execute([$slug]) && $pdo->query("SELECT COUNT(*) FROM articles WHERE slug='$slug'")->fetchColumn() > 0) {
                    $slug = $base . '-' . $i++;
                }
                $pdo->prepare("INSERT INTO articles (title, excerpt, content, category, country_code, slug, photo, author, read_time, status, created_at) VALUES (?,?,?,?,?,?,?,?,'5 мин','published',NOW())")
                    ->execute([$sub['title'], $sub['excerpt'], $sub['body_md'], $sub['category'], $sub['country_code'], $slug, $sub['photo'], $sub['author']]);
                $pdo->prepare("UPDATE article_submissions SET status='approved', moderated_by=?, moderated_at=NOW(), moderation_comment=? WHERE id=?")
                    ->execute([$_SESSION['admin_id'] ?? 0, $comment, $id]);
            }
        } else {
            $pdo->prepare("UPDATE article_submissions SET status='rejected', moderated_by=?, moderated_at=NOW(), moderation_comment=? WHERE id=?")
                ->execute([$_SESSION['admin_id'] ?? 0, $comment, $id]);
        }
        header('Location: /panel-5588/pages/article-submissions.php?msg=' . $action);
        exit;
    }
}

$status = $_GET['status'] ?? 'pending';
$msg    = $_GET['msg']    ?? '';
$stmt = $pdo->prepare("SELECT s.*, u.name as user_name, u.email as user_email FROM article_submissions s LEFT JOIN users u ON s.user_id=u.id WHERE s.status=? ORDER BY s.created_at ASC");
$stmt->execute([$status]);
$submissions = $stmt->fetchAll();
require_once __DIR__ . '/../layout.php';
ob_start();
?>
<div class="panel-content">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h2 style="font-size:20px;font-weight:800;color:#1F2937;">📝 Статьи от пользователей</h2>
  <div style="display:flex;gap:8px;">
    <?php foreach(['pending'=>'⏳ Ожидают','approved'=>'✅ Одобрены','rejected'=>'❌ Отклонены'] as $s=>$label): ?>
    <a href="?status=<?php echo $s; ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
      background:<?php echo $status===$s?'#1F2937':'#F3F4F6'; ?>;color:<?php echo $status===$s?'#fff':'#6B7280'; ?>">
      <?php echo $label; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($msg === 'approve'): ?>
<div style="background:#ECFDF5;border:1px solid #A7F3D0;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;color:#065F46;font-weight:600;">✅ Статья одобрена и опубликована!</div>
<?php elseif ($msg === 'reject'): ?>
<div style="background:#FEF2F2;border:1px solid #FECACA;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;color:#991B1B;font-weight:600;">❌ Статья отклонена.</div>
<?php endif; ?>

<?php if (empty($submissions)): ?>
<div style="text-align:center;padding:60px 20px;color:#9CA3AF;">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5" style="margin-bottom:12px"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
  <div style="font-size:16px;font-weight:600;">Нет статей со статусом «<?php echo htmlspecialchars($status); ?>»</div>
</div>
<?php else: ?>
<?php foreach($submissions as $s): ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin-bottom:16px;">

  <!-- Заголовок и мета -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px;">
    <div style="flex:1">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#6B7280;margin-bottom:4px;">
        <?php echo htmlspecialchars($s['category']); ?> · <?php echo strtoupper(htmlspecialchars($s['country_code'])); ?>
      </div>
      <div style="font-size:18px;font-weight:800;color:#1F2937;line-height:1.2;margin-bottom:6px;">
        <?php echo htmlspecialchars($s['title']); ?>
      </div>
      <div style="font-size:13px;color:#6B7280;line-height:1.5;">
        <?php echo htmlspecialchars($s['excerpt'] ?? ''); ?>
      </div>
    </div>
    <?php if ($s['photo']): ?>
    <img src="<?php echo htmlspecialchars($s['photo']); ?>" style="width:80px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0;" onerror="this.style.display='none'">
    <?php endif; ?>
  </div>

  <!-- Автор и дата -->
  <div style="display:flex;align-items:center;gap:16px;font-size:12px;color:#9CA3AF;margin-bottom:16px;flex-wrap:wrap;">
    <span>✍️ <b style="color:#374151"><?php echo htmlspecialchars($s['author']); ?></b></span>
    <?php if ($s['user_name']): ?>
    <span>👤 <?php echo htmlspecialchars($s['user_name']); ?> (<?php echo htmlspecialchars($s['user_email']); ?>)</span>
    <?php endif; ?>
    <span>📅 <?php echo date('d.m.Y H:i', strtotime($s['created_at'])); ?></span>
    <span>📝 <?php echo mb_strlen($s['body_md']); ?> символов</span>
  </div>

  <!-- Текст (сворачиваемый) -->
  <details style="margin-bottom:16px;">
    <summary style="font-size:13px;font-weight:600;color:#3B6CF4;cursor:pointer;">Читать текст статьи</summary>
    <div style="margin-top:12px;padding:16px;background:#F9FAFB;border-radius:8px;font-size:13px;line-height:1.7;color:#374151;white-space:pre-wrap;max-height:400px;overflow-y:auto;">
      <?php echo htmlspecialchars($s['body_md']); ?>
    </div>
  </details>

  <?php if ($status === 'pending'): ?>
  <!-- Действия -->
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <form method="POST" style="flex:1;min-width:200px;">
      <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
      <input type="hidden" name="action" value="approve">
      <input type="text" name="comment" placeholder="Комментарий (необязательно)"
        style="width:100%;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;margin-bottom:8px;font-family:inherit;">
      <button type="submit" style="width:100%;padding:10px;background:#10B981;color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
        ✅ Одобрить и опубликовать
      </button>
    </form>
    <form method="POST" style="flex:1;min-width:200px;">
      <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
      <input type="hidden" name="action" value="reject">
      <input type="text" name="comment" placeholder="Причина отклонения"
        style="width:100%;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;font-size:13px;margin-bottom:8px;font-family:inherit;">
      <button type="submit" style="width:100%;padding:10px;background:#EF4444;color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
        ❌ Отклонить
      </button>
    </form>
  </div>
  <?php elseif ($s['moderation_comment']): ?>
  <div style="font-size:13px;color:#6B7280;background:#F9FAFB;padding:10px 14px;border-radius:8px;">
    💬 <?php echo htmlspecialchars($s['moderation_comment']); ?>
  </div>
  <?php endif; ?>

</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$pendingArticlesCount = (int)$pdo->query("SELECT COUNT(*) FROM article_submissions WHERE status='pending'")->fetchColumn();
renderLayout('Статьи от пользователей', $content, 0, 0, 0, $pendingArticlesCount);
