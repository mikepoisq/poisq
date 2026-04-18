<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../layout.php';
requireAdmin();

$pdo = getDbConnection();

$filterCountry  = trim($_GET['country'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus   = $_GET['status'] ?? '';
$search         = trim($_GET['q'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;

$where  = [];
$params = [];
if ($filterCountry)  { $where[] = 'country_code = ?'; $params[] = $filterCountry; }
if ($filterCategory) { $where[] = 'category = ?';     $params[] = $filterCategory; }
if ($filterStatus)   { $where[] = 'status = ?';       $params[] = $filterStatus; }
if ($search) {
    $where[]  = '(title LIKE ? OR excerpt LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int)$pdo->prepare("SELECT COUNT(*) FROM articles $whereSQL")->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM articles $whereSQL") : null;
$totalStmt  = $pdo->prepare("SELECT COUNT(*) FROM articles $whereSQL");
$totalStmt->execute($params);
$total      = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT id, title, category, country_code, status, photo, sort_order, created_at, updated_at, author FROM articles $whereSQL ORDER BY sort_order ASC, created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Фильтр категорий
$categories = $pdo->query("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
// Фильтр стран
$countriesList = $pdo->query("SELECT DISTINCT country_code FROM articles ORDER BY country_code")->fetchAll(PDO::FETCH_COLUMN);

ob_start();
?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📚 Статьи — Полезное (<?php echo $total; ?>)</div>
        <div class="panel-actions">
            <a href="article-edit.php" class="btn btn-primary btn-sm">+ Новая статья</a>
            <a href="https://poisq.com/useful.php" target="_blank" class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Открыть сайт
            </a>
        </div>
    </div>

    <!-- Фильтры -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border-light);display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <form method="GET" style="display:contents">
            <input type="text" name="q" class="form-control" placeholder="Поиск по заголовку…"
                value="<?php echo htmlspecialchars($search); ?>" style="width:200px">

            <select name="status" class="form-control form-select" style="width:140px">
                <option value="">Все статусы</option>
                <option value="published" <?php echo $filterStatus==='published'?'selected':''; ?>>Опубликована</option>
                <option value="draft"     <?php echo $filterStatus==='draft'?'selected':''; ?>>Черновик</option>
            </select>

            <select name="category" class="form-control form-select" style="width:150px">
                <option value="">Все рубрики</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory===$cat?'selected':''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="country" class="form-control form-select" style="width:120px">
                <option value="">Все страны</option>
                <?php foreach ($countriesList as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterCountry===$c?'selected':''; ?>>
                    <?php echo strtoupper($c); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">🔍 Найти</button>
            <?php if ($search || $filterStatus || $filterCategory || $filterCountry): ?>
            <a href="articles.php" class="btn btn-secondary btn-sm">✕ Сбросить</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($articles)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <div class="empty-state-title">Статьи не найдены</div>
        <div class="empty-state-text">Попробуйте изменить фильтры или <a href="article-edit.php">добавьте первую статью</a></div>
    </div>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th style="width:60px">Фото</th>
            <th>Заголовок</th>
            <th>Рубрика</th>
            <th>Страна</th>
            <th style="width:90px">Статус</th>
            <th style="width:60px">Порядок</th>
            <th style="width:90px">Дата</th>
            <th style="width:80px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
        <tr>
            <td>
                <?php if ($a['photo']): ?>
                <img src="<?php echo htmlspecialchars($a['photo']); ?>" alt=""
                    style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:block">
                <?php else: ?>
                <div style="width:48px;height:48px;background:var(--border-light);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:18px">📄</div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-size:13px;font-weight:600;color:var(--text);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?php echo htmlspecialchars($a['title']); ?>
                </div>
                <?php if ($a['slug'] ?? ''): ?>
                <div style="font-size:11px;color:var(--text-light)">/<?php echo htmlspecialchars($a['country_code']); ?>/<?php echo htmlspecialchars($a['slug'] ?? ''); ?></div>
                <?php endif; ?>
                <?php if (!empty($a['author'])): ?><span style="display:inline-block;margin-top:3px;font-size:10px;font-weight:600;background:#FFF7ED;color:#EA580C;border:1px solid #FED7AA;padding:1px 6px;border-radius:4px">✍️ <?php echo htmlspecialchars($a['author']); ?></span><?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-secondary)"><?php echo htmlspecialchars($a['category'] ?? '—'); ?></td>
            <td><span style="font-size:12px;font-weight:700;color:var(--text-secondary)"><?php echo strtoupper($a['country_code']); ?></span></td>
            <td>
                <span class="badge <?php echo $a['status']==='published' ? 'badge-green' : 'badge-gray'; ?>">
                    <?php echo $a['status']==='published' ? 'Опубликована' : 'Черновик'; ?>
                </span>
            </td>
            <td style="font-size:13px;font-weight:600;color:var(--text-secondary)"><?php echo $a['sort_order']; ?></td>
            <td style="font-size:11px;color:var(--text-light)"><?php echo date('d.m.Y', strtotime($a['created_at'])); ?></td>
            <td>
                <div style="display:flex;gap:4px">
                    <a href="article-edit.php?id=<?php echo $a['id']; ?>" class="btn btn-secondary btn-sm" title="Редактировать">✏️</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&category=<?php echo urlencode($filterCategory); ?>&country=<?php echo $filterCountry; ?>" class="page-link">← Назад</a>
        <?php endif; ?>
        <span style="font-size:13px;color:var(--text-secondary);padding:6px 10px">Стр. <?php echo $page; ?> / <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&category=<?php echo urlencode($filterCategory); ?>&country=<?php echo $filterCountry; ?>" class="page-link">Далее →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
renderLayout('Статьи — Полезное', $content);
