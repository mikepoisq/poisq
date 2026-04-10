<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        if ($action === 'block')   $pdo->prepare("UPDATE users SET is_blocked=1 WHERE id=?")->execute([$userId]);
        if ($action === 'unblock') $pdo->prepare("UPDATE users SET is_blocked=0 WHERE id=?")->execute([$userId]);
    }
    header("Location: /panel-5588/users.php?" . http_build_query(array_diff_key($_GET, ['action'=>''])));
    exit;
}

$search  = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = ["1=1"];
$params = [];
if ($search) {
    $where[]  = "(u.name LIKE ? OR u.email LIKE ?)";
    $like     = "%" . $search . "%";
    $params[] = $like; $params[] = $like;
}
if ($filter === 'blocked') { $where[] = "u.is_blocked = 1"; }
if ($filter === 'active')  { $where[] = "u.is_blocked = 0"; }
$whereSQL = "WHERE " . implode(" AND ", $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at, u.is_blocked,
           COUNT(s.id) as services_count,
           SUM(CASE WHEN s.status='approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN s.status='pending' THEN 1 ELSE 0 END) as pending_count
    FROM users u
    LEFT JOIN services s ON s.user_id = u.id
    $whereSQL
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$blockedUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_blocked=1")->fetchColumn();
$newThisWeek  = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

ob_start();
?>

<div class="stat-grid stat-grid-3" style="margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-card-label">Всего пользователей</div>
        <div class="stat-card-value"><?php echo $totalUsers; ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Новых за 7 дней</div>
        <div class="stat-card-value"><?php echo $newThisWeek; ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-label">Заблокировано</div>
        <div class="stat-card-value"><?php echo $blockedUsers; ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div class="panel-title">Пользователи</div>
        <div class="panel-actions">
            <form method="GET" style="display:flex;gap:6px;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input class="form-control" type="text" name="q"
                    placeholder="Поиск по имени или email..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="width:240px;">
                <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                <?php if ($search): ?>
                <a href="?filter=<?php echo $filter; ?>" class="btn btn-secondary btn-sm">✕</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Фильтры -->
    <div style="padding:12px 18px;border-bottom:1px solid var(--border-light);display:flex;gap:6px;">
        <?php foreach (['all'=>'Все','active'=>'Активные','blocked'=>'Заблокированные'] as $f=>$l): ?>
        <a href="?filter=<?php echo $f; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="chip <?php echo $filter===$f?'active':''; ?>"><?php echo $l; ?></a>
        <?php endforeach; ?>
        <span style="margin-left:auto;font-size:13px;color:var(--text-light);line-height:30px;">Найдено: <?php echo $total; ?></span>
    </div>

    <div style="overflow-x:auto;">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <div class="empty-state-title">Пользователей не найдено</div>
            <div class="empty-state-text">Попробуйте изменить фильтры</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Email</th>
                    <th>Сервисы</th>
                    <th>Статус</th>
                    <th>Регистрация</th>
                    <th style="width:140px">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user):
                $isBlocked = (int)$user['is_blocked'];
                $initial = mb_strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1));
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">
                            <?php echo htmlspecialchars($initial); ?>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($user['name'] ?: '—'); ?></div>
                            <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge badge-blue" style="font-size:10px;">admin</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-size:13px;color:var(--text-secondary);"><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <div style="font-size:13px;">
                        <?php if ((int)$user['services_count'] > 0): ?>
                        <a href="/panel-5588/services.php?q=<?php echo urlencode($user['email']); ?>" style="color:var(--primary);text-decoration:none;font-weight:600;">
                            <?php echo (int)$user['services_count']; ?> всего
                        </a>
                        <?php if ((int)$user['approved_count'] > 0): ?>
                        <span style="color:var(--success);font-size:12px;"> · <?php echo (int)$user['approved_count']; ?> активных</span>
                        <?php endif; ?>
                        <?php if ((int)$user['pending_count'] > 0): ?>
                        <span style="color:var(--warning);font-size:12px;"> · <?php echo (int)$user['pending_count']; ?> на модерации</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span style="color:var(--text-light);">—</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php if ($isBlocked): ?>
                    <span class="badge badge-red">Заблокирован</span>
                    <?php else: ?>
                    <span class="badge badge-green">Активен</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--text-light);"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <a href="/panel-5588/services.php?q=<?php echo urlencode($user['email']); ?>" class="btn btn-secondary btn-sm" title="Сервисы">📋</a>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <?php if ($isBlocked): ?>
                            <input type="hidden" name="action" value="unblock">
                            <button type="submit" class="btn btn-success btn-sm" title="Разблокировать">✅</button>
                            <?php else: ?>
                            <input type="hidden" name="action" value="block">
                            <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Заблокировать пользователя?')" title="Заблокировать">🚫</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?filter=<?php echo $filter; ?>&page=<?php echo max(1,$page-1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page<=1?'disabled':''; ?>">← Назад</a>
        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page===$i?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?filter=<?php echo $filter; ?>&page=<?php echo min($totalPages,$page+1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page>=$totalPages?'disabled':''; ?>">Вперёд →</a>
        <span style="margin-left:auto;font-size:13px;color:var(--text-light);">Стр. <?php echo $page; ?> из <?php echo $totalPages; ?></span>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
renderLayout('Пользователи', $content, $pendingCount, 0, $pendingReviewCount);
?>
