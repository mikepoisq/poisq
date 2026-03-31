<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
requireAdmin();

$pdo = getDbConnection();

$status = $_GET["status"] ?? "all";
$search = trim($_GET["q"] ?? "");
$page   = max(1, (int)($_GET["page"] ?? 1));
$perPage = 20;

$where  = [];
$params = [];

if ($status !== "all") {
    $where[]  = "s.status = ?";
    $params[] = $status;
}
if ($search) {
    $where[]  = "(s.name LIKE ? OR u.email LIKE ?)";
    $like     = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$total = $pdo->prepare("SELECT COUNT(*) FROM services s JOIN users u ON s.user_id=u.id $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT s.id, s.name, s.category, s.status, s.is_visible, s.country_code, s.created_at,
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

$statusLabels = [
    "approved" => ["label"=>"Активен",    "color"=>"#10B981", "bg"=>"#ECFDF5"],
    "pending"  => ["label"=>"На модерации","color"=>"#F59E0B", "bg"=>"#FFFBEB"],
    "rejected" => ["label"=>"Отклонён",   "color"=>"#EF4444", "bg"=>"#FEF2F2"],
    "draft"    => ["label"=>"Черновик",   "color"=>"#9CA3AF", "bg"=>"#F9FAFB"],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Сервисы — Poisq Admin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; background:#F5F5F7; color:#1F2937; }
.header {
    background:#fff; padding:14px 16px;
    border-bottom:1px solid #E5E7EB;
    display:flex; align-items:center; gap:12px;
    position:sticky; top:0; z-index:10;
}
.back { font-size:20px; text-decoration:none; color:#374151; }
.header-title { font-size:17px; font-weight:700; }
.main { padding:12px; max-width:600px; margin:0 auto; }
.search-bar { display:flex; gap:8px; margin-bottom:12px; }
.search-input {
    flex:1; padding:10px 14px; border:1.5px solid #D1D5DB;
    border-radius:10px; font-size:15px; outline:none;
}
.search-input:focus { border-color:#2E73D8; }
.btn-search {
    padding:10px 16px; background:#2E73D8; color:#fff;
    border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer;
}
.filters { display:flex; gap:6px; overflow-x:auto; margin-bottom:12px; padding-bottom:2px; }
.filter-btn {
    padding:7px 14px; border-radius:99px; font-size:13px; font-weight:600;
    border:1.5px solid #E5E7EB; background:#fff; color:#6B7280;
    cursor:pointer; white-space:nowrap; text-decoration:none;
}
.filter-btn.active { border-color:#2E73D8; background:#EFF6FF; color:#2E73D8; }
.total { font-size:13px; color:#9CA3AF; margin-bottom:10px; }
.card {
    background:#fff; border-radius:12px; padding:14px;
    margin-bottom:10px; box-shadow:0 1px 4px rgba(0,0,0,0.06);
}
.card-top { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; }
.card-name { font-size:15px; font-weight:700; flex:1; }
.status-badge {
    font-size:11px; font-weight:700; padding:3px 8px; border-radius:99px; white-space:nowrap;
}
.card-meta { font-size:12px; color:#9CA3AF; margin-bottom:8px; }
.card-user { font-size:12px; color:#6B7280; }
.card-actions { display:flex; gap:8px; margin-top:10px; }
.btn-view {
    padding:8px 14px; background:#EFF6FF; color:#2E73D8;
    border:none; border-radius:8px; font-size:13px; font-weight:600;
    text-decoration:none; cursor:pointer;
}
.btn-del {
    padding:8px 14px; background:#FEF2F2; color:#EF4444;
    border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
}
.pagination { display:flex; justify-content:center; gap:6px; margin-top:16px; flex-wrap:wrap; }
.page-btn {
    padding:8px 14px; border-radius:8px; font-size:14px; font-weight:600;
    border:1.5px solid #E5E7EB; background:#fff; color:#374151; text-decoration:none;
}
.page-btn.active { background:#2E73D8; color:#fff; border-color:#2E73D8; }
</style>
</head>
<body>
<div class="header">
    <a href="/panel-5588/dashboard.php" class="back">←</a>
    <div class="header-title">Все сервисы</div>
</div>
<div class="main">
    <form method="GET" class="search-bar">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
        <input class="search-input" type="text" name="q" placeholder="Поиск по названию или email..." value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn-search" type="submit">🔍</button>
    </form>
    <div class="filters">
        <?php foreach (["all"=>"Все","pending"=>"Модерация","approved"=>"Активные","rejected"=>"Отклонённые","draft"=>"Черновики"] as $s=>$l): ?>
        <a href="?status=<?php echo $s; ?><?php echo $search ? "&q=".urlencode($search) : ""; ?>"
           class="filter-btn <?php echo $status===$s ? "active" : ""; ?>"><?php echo $l; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="total">Найдено: <?php echo $total; ?></div>
    <?php foreach ($services as $svc):
        $sl = $statusLabels[$svc["status"]] ?? ["label"=>$svc["status"],"color"=>"#9CA3AF","bg"=>"#F9FAFB"];
    ?>
    <div class="card">
        <div class="card-top">
            <div class="card-name"><?php echo htmlspecialchars($svc["name"]); ?></div>
            <span class="status-badge" style="color:<?php echo $sl["color"]; ?>;background:<?php echo $sl["bg"]; ?>">
                <?php echo $sl["label"]; ?>
            </span>
        </div>
        <div class="card-meta">
            <?php echo htmlspecialchars($svc["category"]); ?> &bull;
            <?php echo htmlspecialchars($svc["city_name"] ?? ""); ?>,
            <?php echo strtoupper($svc["country_code"]); ?> &bull;
            <?php echo date("d.m.Y", strtotime($svc["created_at"])); ?>
        </div>
        <div class="card-user">👤 <?php echo htmlspecialchars($svc["user_name"]); ?> — <?php echo htmlspecialchars($svc["user_email"]); ?></div>
        <div class="card-actions">
            <a href="https://poisq.com<?php echo serviceUrl($svc["id"], $svc["name"]); ?>" target="_blank" class="btn-view">👁 Открыть</a>
            <?php if ($svc["status"] === "pending"): ?>
            <a href="/panel-5588/moderate.php" class="btn-view">📋 Модерировать</a>
            <?php endif; ?>
            <form method="POST" action="/panel-5588/delete.php" onsubmit="return confirm('Удалить сервис?')">
                <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                <button type="submit" class="btn-del">🗑</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
        <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?><?php echo $search ? "&q=".urlencode($search) : ""; ?>"
           class="page-btn <?php echo $page===$i ? "active" : ""; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>