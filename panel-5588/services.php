<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/layout.php";
requireAuthAny('services');

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
           s.verified, s.verified_until, s.created_by_admin, s.created_by_moderator, s.call_status, s.call_note,
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

<style>
.crown-blue { color: #3B6CF4; font-size: 13px; cursor: default; }
.crown-green { color: #10B981; font-size: 13px; cursor: default; }

.svc-table-wrap { width: 100%; }
.svc-table { table-layout: auto; width: 100%; }

/* фиксированные узкие колонки */
.svc-table .col-id      { width: 40px;  min-width: 40px;  max-width: 40px; }
.svc-table .col-cat     { width: 100px; min-width: 70px;  max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.svc-table .col-city    { width: 90px;  min-width: 60px;  max-width: 90px; overflow: hidden; }
.svc-table .col-owner   { width: 130px; min-width: 90px;  max-width: 130px; overflow: hidden; }
.svc-table .col-status  { width: 90px;  min-width: 80px;  white-space: nowrap; }
.svc-table .col-call    { width: 60px;  min-width: 50px;  text-align: center; }
.svc-table .col-views   { width: 60px;  min-width: 50px; }
.svc-table .col-date    { width: 80px;  min-width: 70px;  white-space: nowrap; }
.svc-table .col-actions { width: 88px;  min-width: 88px;  white-space: nowrap; }

/* название — занимает всё оставшееся место */
.svc-table .col-name    { min-width: 140px; overflow: hidden; }

/* горизонтальный скролл только на узких экранах */
@media (max-width: 1200px) {
    .svc-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .svc-table      { min-width: 860px; }
}
</style>

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
    <div class="panel-body">
        <?php if (empty($services)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Сервисов не найдено</div>
            <div class="empty-state-text">Попробуйте изменить фильтры или поисковый запрос</div>
        </div>
        <?php else: ?>
        <div class="svc-table-wrap">
        <table class="table svc-table">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-name">Название</th>
                    <th class="col-cat">Категория</th>
                    <th class="col-city">Город</th>
                    <th class="col-owner">Владелец</th>
                    <th class="col-status">Статус</th>
                    <th class="col-call">Созвон</th>
                    <th class="col-views">Просм.</th>
                    <th class="col-date">Дата</th>
                    <th class="col-actions">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $svc):
                $sc = $statusConfig[$svc["status"]] ?? ["label"=>$svc["status"],"class"=>"badge-gray"];
            ?>
            <tr>
                <td class="col-id" style="color:var(--text-light);font-size:12px;">#<?php echo $svc['id']; ?></td>
                <td class="col-name" style="max-width:200px;">
                    <div style="display:flex;align-items:center;gap:3px;flex-wrap:nowrap;">
                        <span style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;"><?php echo htmlspecialchars($svc["name"]); ?></span>
                        <?php if ($svc['created_by_moderator'] !== null): ?>
                        <span class="crown-green" title="Создан модератором" style="flex-shrink:0;">👑</span>
                        <?php elseif ($svc['created_by_admin'] !== null): ?>
                        <span class="crown-blue" title="Создан администратором" style="flex-shrink:0;">👑</span>
                        <?php endif; ?>
                        <?php if ($svc['verified'] && ($svc['verified_until'] === null || $svc['verified_until'] >= date('Y-m-d'))): ?>
                        <span style="font-size:10px;background:var(--success-bg);color:#065F46;padding:1px 5px;border-radius:4px;font-weight:700;white-space:nowrap;flex-shrink:0;">✓</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$svc['is_visible'] && $svc['status'] === 'approved'): ?>
                    <div style="font-size:11px;color:var(--text-light);">скрыт</div>
                    <?php endif; ?>
                </td>
                <td class="col-cat" style="font-size:12px;color:var(--text-secondary);"><?php echo $categories[$svc["category"]] ?? $svc["category"]; ?></td>
                <td class="col-city" style="font-size:12px;">
                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($svc["city_name"] ?? "—"); ?></div>
                    <div style="color:var(--text-light);font-size:11px;"><?php echo strtoupper($svc["country_code"]); ?></div>
                </td>
                <td class="col-owner">
                    <div style="font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($svc["user_name"]); ?></div>
                    <div style="font-size:11px;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($svc["user_email"]); ?></div>
                </td>
                <td class="col-status"><span class="badge <?php echo $sc['class']; ?>" style="font-size:11px;padding:2px 7px;white-space:nowrap;"><?php echo $sc['label']; ?></span></td>
                <td class="col-call" style="font-size:15px;text-align:center;">
                    <?php
                    $cs = $svc['call_status'] ?? 'not_called';
                    if ($cs === 'not_called'):     ?>
                    <span style="color:var(--text-light);" title="Не звонили">—</span>
                    <?php elseif ($cs === 'no_answer'): ?>
                    <span style="color:#D97706;" title="Не дозвонились">☎️</span>
                    <?php elseif ($cs === 'reached'): ?>
                    <span style="color:#10B981;" title="Дозвонились">✅</span>
                    <?php elseif ($cs === 'no_number'): ?>
                    <span style="color:var(--text-light);" title="Нет номера">🚫</span>
                    <?php elseif ($cs === 'other'): ?>
                    <span style="color:#3B6CF4;cursor:default;" title="<?php echo htmlspecialchars($svc['call_note'] ?? ''); ?>">📝</span>
                    <?php endif; ?>
                </td>
                <td class="col-views" style="font-size:12px;color:var(--text-secondary);"><?php echo (int)$svc['views']; ?></td>
                <td class="col-date" style="font-size:11px;color:var(--text-light);white-space:nowrap;"><?php echo date("d.m.y", strtotime($svc["created_at"])); ?></td>
                <td class="col-actions">
                    <div style="display:flex;gap:3px;flex-wrap:nowrap;">
                        <a href="/panel-5588/edit.php?id=<?php echo $svc['id']; ?>" class="btn btn-secondary btn-sm" title="Редактировать" style="padding:4px 7px;">✏️</a>
                        <a href="https://poisq.com<?php echo serviceUrl($svc["id"], $svc["name"]); ?>" target="_blank" class="btn btn-secondary btn-sm" title="Открыть" style="padding:4px 7px;">👁</a>
                        <form method="POST" action="/panel-5588/delete.php" onsubmit="return confirm('Удалить сервис «<?php echo addslashes($svc['name']); ?>»?')" style="margin:0;">
                            <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" title="Удалить" style="padding:4px 7px;">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
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
