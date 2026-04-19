<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

$totalServices      = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$pendingCount       = $pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingVerifCount  = 0;
try {
    $pendingVerifCount = (int)$pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $pendingVerifCount = 0; }
$pendingReviewCount = 0;
try {
    $pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $pendingReviewCount = 0; }
$pendingArticlesCount = 0;
try {
    $pendingArticlesCount = (int)$pdo->query("SELECT COUNT(*) FROM article_submissions WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $pendingArticlesCount = 0; }
$approvedCount  = $pdo->query("SELECT COUNT(*) FROM services WHERE status='approved'")->fetchColumn();
$rejectedCount  = $pdo->query("SELECT COUNT(*) FROM services WHERE status='rejected'")->fetchColumn();
$draftCount     = $pdo->query("SELECT COUNT(*) FROM services WHERE status='draft'")->fetchColumn();
$duplicatesCount = 0;
try {
    $duplicatesCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='duplicate'")->fetchColumn();
} catch (Exception $e) { $duplicatesCount = 0; }
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$blockedUsers   = $pdo->query("SELECT COUNT(*) FROM users WHERE is_blocked=1")->fetchColumn();
$newToday       = $pdo->query("SELECT COUNT(*) FROM services WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$viewsToday     = $pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$viewsWeek      = $pdo->query("SELECT COUNT(*) FROM page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$viewsMonth     = $pdo->query("SELECT COUNT(*) FROM page_views WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$searchToday    = $pdo->query("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$searchFound    = $pdo->query("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE() AND status='found'")->fetchColumn();
$searchNotFound = $pdo->query("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE() AND status='not_found'")->fetchColumn();

$recentServices = $pdo->query("
    SELECT s.id, s.name, s.status, s.category, s.created_at,
           u.name as user_name, c.name as city_name, s.country_code
    FROM services s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN cities c ON s.city_id = c.id
    WHERE s.status = 'pending'
    ORDER BY s.created_at ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentUsers = $pdo->query("
    SELECT id, name, email, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentSearches = $pdo->query("
    SELECT query, country_code, results_count, status, created_at
    FROM search_logs
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$categories = [
    "health"=>"Здоровье","legal"=>"Юридические","family"=>"Семья",
    "shops"=>"Магазины","home"=>"Дом","education"=>"Образование",
    "business"=>"Бизнес","transport"=>"Транспорт","events"=>"События",
    "it"=>"IT","realestate"=>"Недвижимость"
];

ob_start();
?>

<!-- Новые отзывы -->
<?php if ($pendingReviewCount > 0): ?>
<a href="/panel-5588/reviews.php?status=pending" style="display:block;text-decoration:none;margin-bottom:16px;">
    <div class="stat-card blue" style="display:flex;align-items:center;gap:14px;padding:14px 18px;">
        <div style="font-size:28px;">💬</div>
        <div>
            <div class="stat-card-label">Новых отзывов</div>
            <div class="stat-card-value" style="font-size:22px;"><?php echo $pendingReviewCount; ?></div>
        </div>
        <div style="margin-left:auto;font-size:20px;">→</div>
    </div>
</a>
<?php endif; ?>

<!-- Значок Проверено -->
<?php if ($pendingArticlesCount > 0): ?>
<a href="/panel-5588/pages/article-submissions.php" style="display:block;text-decoration:none;margin-bottom:16px;">
  <div class="stat-card" style="background:#FFF7ED;border:1.5px solid #FED7AA;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:24px;">📝</span>
      <div>
        <div class="stat-card-label">Статьи от пользователей</div>
        <div class="stat-card-value" style="font-size:22px;color:#EA580C;"><?php echo $pendingArticlesCount; ?> <span style="font-size:13px;font-weight:500">ожидают</span></div>
      </div>
    </div>
  </div>
</a>
<?php endif; ?>
<?php if ($pendingVerifCount > 0): ?>
<a href="/panel-5588/verifications.php?status=pending" style="display:block;text-decoration:none;margin-bottom:16px;">
    <div class="stat-card yellow" style="display:flex;align-items:center;gap:14px;padding:14px 18px;">
        <div style="font-size:28px;">⭐</div>
        <div>
            <div class="stat-card-label">Ждут значка Проверено</div>
            <div class="stat-card-value" style="font-size:22px;"><?php echo $pendingVerifCount; ?></div>
        </div>
        <div style="margin-left:auto;color:var(--warning);font-size:20px;">→</div>
    </div>
</a>
<?php endif; ?>

<!-- Статистика сервисов -->
<div class="stat-grid stat-grid-4">
    <div class="stat-card yellow">
        <div class="stat-card-label">На модерации</div>
        <div class="stat-card-value"><?php echo $pendingCount; ?></div>
        <div class="stat-card-sub">Ожидают проверки</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Опубликовано</div>
        <div class="stat-card-value"><?php echo $approvedCount; ?></div>
        <div class="stat-card-sub">Активных сервисов</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-card-label">Пользователей</div>
        <div class="stat-card-value"><?php echo $totalUsers; ?></div>
        <div class="stat-card-sub"><?php echo $blockedUsers; ?> заблокировано</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Всего сервисов</div>
        <div class="stat-card-value"><?php echo $totalServices; ?></div>
        <div class="stat-card-sub"><?php echo $draftCount; ?> черновиков</div>
    </div>
</div>

<!-- Посещения и поиск -->
<div class="stat-grid" style="grid-template-columns: repeat(6,1fr);">
    <div class="stat-card blue">
        <div class="stat-card-label">Визиты сегодня</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $viewsToday; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">За 7 дней</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $viewsWeek; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">За 30 дней</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $viewsMonth; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Запросов сегодня</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $searchToday; ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Найдено</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $searchFound; ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-label">Не найдено</div>
        <div class="stat-card-value" style="font-size:22px"><?php echo $searchNotFound; ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Ожидают модерации -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🔍 На модерации</div>
        <a href="/panel-5588/moderate.php" class="btn btn-primary btn-sm">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Все (<?php echo $pendingCount; ?>)
        </a>
    </div>
    <div class="panel-body">
        <?php if (empty($recentServices)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <div class="empty-state-title">Всё проверено!</div>
            <div class="empty-state-text">Нет сервисов на модерации</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>Сервис</th>
                <th>Категория</th>
                <th>Дата</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentServices as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:13px"><?php echo htmlspecialchars($s['name']); ?></div>
                    <div style="font-size:11px;color:var(--text-light)"><?php echo htmlspecialchars($s['user_name']); ?> · <?php echo htmlspecialchars($s['city_name'] ?? ''); ?></div>
                </td>
                <td><span style="font-size:12px;color:var(--text-secondary)"><?php echo $categories[$s['category']] ?? $s['category']; ?></span></td>
                <td><span style="font-size:12px;color:var(--text-light)"><?php echo date('d.m H:i', strtotime($s['created_at'])); ?></span></td>
                <td><a href="/panel-5588/edit.php?id=<?php echo $s['id']; ?>" class="btn btn-secondary btn-sm">✏️</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Последние запросы -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🔎 Поисковые запросы</div>
        <a href="/panel-5588/analytics.php" class="btn btn-secondary btn-sm">Аналитика →</a>
    </div>
    <div class="panel-body">
        <?php if (empty($recentSearches)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <div class="empty-state-title">Данных пока нет</div>
            <div class="empty-state-text">Запросы появятся после поисков на сайте</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>Запрос</th>
                <th>Страна</th>
                <th>Статус</th>
                <th>Время</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentSearches as $s): ?>
            <tr>
                <td style="font-size:13px;font-weight:500;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?php echo htmlspecialchars($s['query'] ?: '—'); ?>
                </td>
                <td><span style="font-size:12px;font-weight:700;color:var(--text-secondary)"><?php echo strtoupper($s['country_code']); ?></span></td>
                <td>
                    <?php if ($s['status'] === 'found'): ?>
                    <span class="badge badge-green">✓ <?php echo $s['results_count']; ?></span>
                    <?php else: ?>
                    <span class="badge badge-red">✗ 0</span>
                    <?php endif; ?>
                </td>
                <td><span style="font-size:12px;color:var(--text-light)"><?php echo date('H:i', strtotime($s['created_at'])); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</div>

<!-- Новые пользователи -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">👥 Новые пользователи</div>
        <a href="/panel-5588/users.php" class="btn btn-secondary btn-sm">Все пользователи →</a>
    </div>
    <div class="panel-body">
        <table class="table">
            <thead><tr>
                <th>Имя</th>
                <th>Email</th>
                <th>Дата регистрации</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">
                            <?php echo mb_strtoupper(mb_substr($u['name'] ?: $u['email'], 0, 1)); ?>
                        </div>
                        <span style="font-size:13px;font-weight:600"><?php echo htmlspecialchars($u['name'] ?: '—'); ?></span>
                    </div>
                </td>
                <td style="font-size:13px;color:var(--text-secondary)"><?php echo htmlspecialchars($u['email']); ?></td>
                <td style="font-size:12px;color:var(--text-light)"><?php echo date('d.m.Y H:i', strtotime($u['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
renderLayout('Дашборд', $content, (int)$pendingCount, (int)$pendingVerifCount, (int)$pendingReviewCount, 0, (int)$duplicatesCount);
?>
