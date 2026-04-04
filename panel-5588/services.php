<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

$status  = $_GET["status"] ?? "all";
$search  = trim($_GET["q"] ?? "");
$page    = max(1, (int)($_GET["page"] ?? 1));
$perPage = 20;

$where  = [];
$params = [];
if ($status !== "all") { $where[] = "s.status = ?"; $params[] = $status; }
if ($search) {
    $where[]  = "(s.name LIKE ? OR u.email LIKE ? OR u.name LIKE ?)";
    $like     = "%" . $search . "%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$total = $pdo->prepare("SELECT COUNT(*) FROM services s JOIN users u ON s.user_id=u.id $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, s.name, s.category, s.status, s.is_visible, s.country_code, s.created_at, s.views,
           s.verified, s.verified_until,
           u.name as user_name, u.email as user_email, c.name as city_name
    FROM services s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN cities c ON s.city_id = c.id
    $whereSQL
    ORDER BY s.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();

$statusConfig = [
    "approved" => ["label"=>"Активен",     "class"=>"badge-green"],
    "pending"  => ["label"=>"Модерация",   "class"=>"badge-yellow"],
    "rejected" => ["label"=>"Отклонён",    "class"=>"badge-red"],
    "draft"    => ["label"=>"Черновик",    "class"=>"badge-gray"],
];
$categories = [
    "health"=>"Здоровье","legal"=>"Юридические","family"=>"Семья",
    "shops"=>"Магазины","home"=>"Дом","education"=>"Образование",
    "business"=>"Бизнес","transport"=>"Транспорт","events"=>"События",
    "it"=>"IT","realestate"=>"Недвижимость"
];

ob_start();
?>

<div class="panel">
    <div class="panel-header">
        <div class="panel-title">Все сервисы</div>
        <div class="panel-actions">
            <a href="/panel-5588/create.php" class="btn btn-primary btn-sm">+ Создать сервис</a>
            <!-- Поиск -->
            <form method="GET" style="display:flex;gap:6px;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <input class="form-control" type="text" name="q"
                    placeholder="Поиск по названию, email..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="width:220px;">
                <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                <?php if ($search): ?>
                <a href="?status=<?php echo $status; ?>" class="btn btn-secondary btn-sm">✕</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Фильтры статуса -->
    <div style="padding:12px 18px;border-bottom:1px solid var(--border-light);display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach (["all"=>"Все","pending"=>"Модерация","approved"=>"Активные","rejected"=>"Отклонённые","draft"=>"Черновики"] as $s=>$l): ?>
        <a href="?status=<?php echo $s; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>"
           class="chip <?php echo $status===$s ? 'active' : ''; ?>">
            <?php echo $l; ?>
            <?php if ($s === 'pending' && $pendingCount > 0): ?>
            <span style="background:var(--warning);color:white;font-size:10px;padding:1px 5px;border-radius:99px;margin-left:2px;"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <span style="margin-left:auto;font-size:13px;color:var(--text-light);line-height:30px;">Найдено: <?php echo $total; ?></span>
    </div>

    <!-- Таблица -->
    <div class="panel-body" style="overflow-x:auto;">
        <?php if (empty($services)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Сервисов не найдено</div>
            <div class="empty-state-text">Попробуйте изменить фильтры или поисковый запрос</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px">ID</th>
                    <th>Название</th>
                    <th>Категория</th>
                    <th>Город</th>
                    <th>Владелец</th>
                    <th>Статус</th>
                    <th>Просмотры</th>
                    <th>Дата</th>
                    <th style="width:120px">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $svc):
                $sc = $statusConfig[$svc["status"]] ?? ["label"=>$svc["status"],"class"=>"badge-gray"];
            ?>
            <tr>
                <td style="color:var(--text-light);font-size:12px;">#<?php echo $svc['id']; ?></td>
                <td>
                    <div style="font-weight:600;font-size:13px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo htmlspecialchars($svc["name"]); ?>
                        <?php if ((int)$svc["user_id"] === 11): ?>
                        <span style="font-size:10px;background:var(--primary-light);color:var(--primary);padding:1px 5px;border-radius:4px;font-weight:700;margin-left:3px;">👑</span>
                        <?php endif; ?>
                        <?php if ($svc['verified'] && ($svc['verified_until'] === null || $svc['verified_until'] >= date('Y-m-d'))): ?>
                        <span style="font-size:10px;background:var(--success-bg);color:#065F46;padding:1px 5px;border-radius:4px;font-weight:700;margin-left:3px;">✓ Проверено</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$svc['is_visible'] && $svc['status'] === 'approved'): ?>
                    <div style="font-size:11px;color:var(--text-light);">скрыт</div>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;color:var(--text-secondary);"><?php echo $categories[$svc["category"]] ?? $svc["category"]; ?></td>
                <td style="font-size:13px;">
                    <?php echo htmlspecialchars($svc["city_name"] ?? "—"); ?>
                    <span style="color:var(--text-light);font-size:11px;"><?php echo strtoupper($svc["country_code"]); ?></span>
                </td>
                <td>
                    <div style="font-size:13px;font-weight:500;"><?php echo htmlspecialchars($svc["user_name"]); ?></div>
                    <div style="font-size:11px;color:var(--text-light);"><?php echo htmlspecialchars($svc["user_email"]); ?></div>
                </td>
                <td><span class="badge <?php echo $sc['class']; ?>"><?php echo $sc['label']; ?></span></td>
                <td style="font-size:13px;color:var(--text-secondary);"><?php echo (int)$svc['views']; ?></td>
                <td style="font-size:12px;color:var(--text-light);"><?php echo date("d.m.Y", strtotime($svc["created_at"])); ?></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <a href="/panel-5588/edit.php?id=<?php echo $svc['id']; ?>" class="btn btn-secondary btn-sm" title="Редактировать">✏️</a>
                        <a href="https://poisq.com<?php echo serviceUrl($svc["id"], $svc["name"]); ?>" target="_blank" class="btn btn-secondary btn-sm" title="Открыть">👁</a>
                        <form method="POST" action="/panel-5588/delete.php" onsubmit="return confirm('Удалить сервис «<?php echo addslashes($svc['name']); ?>»?')" style="margin:0;">
                            <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Удалить">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Пагинация -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?status=<?php echo $status; ?>&page=<?php echo max(1,$page-1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page<=1?'disabled':''; ?>">← Назад</a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page===$i?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?status=<?php echo $status; ?>&page=<?php echo min($totalPages,$page+1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page>=$totalPages?'disabled':''; ?>">Вперёд →</a>
        <span style="margin-left:auto;font-size:13px;color:var(--text-light);">Стр. <?php echo $page; ?> из <?php echo $totalPages; ?></span>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
renderLayout('Сервисы', $content, $pendingCount);
?>
