<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/../config/meilisearch.php";
requireAdmin();

$pdo = getDbConnection();

// Обработка действий
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action    = $_POST["action"] ?? "";
    $serviceId = (int)($_POST["service_id"] ?? 0);

    if ($serviceId > 0) {
        if ($action === "approve") {
            $pdo->prepare("UPDATE services SET status='approved', is_visible=1 WHERE id=?")->execute([$serviceId]);
            // Добавляем в Meilisearch
            $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id = c.id WHERE s.id = ? LIMIT 1");
            $row->execute([$serviceId]);
            $svcRow = $row->fetch(PDO::FETCH_ASSOC);
            if ($svcRow) meiliAddDocument(meiliPrepareDoc($svcRow));
            // Email пользователю
            $svc = $pdo->prepare("SELECT s.name, u.email, u.name as uname FROM services s JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $svc->execute([$serviceId]);
            $svc = $svc->fetch();
            if ($svc) {
                require_once __DIR__ . "/../config/email.php";
                sendStatusEmail($svc["email"], $svc["uname"], $svc["name"], "approved", "");
            }
        } elseif ($action === "reject") {
            $comment = trim($_POST["comment"] ?? "");
            $pdo->prepare("UPDATE services SET status='rejected', is_visible=0, moderation_comment=? WHERE id=?")->execute([$comment, $serviceId]);
            // Удаляем из Meilisearch
            meiliDeleteDocument($serviceId);
            $svc = $pdo->prepare("SELECT s.name, u.email, u.name as uname FROM services s JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $svc->execute([$serviceId]);
            $svc = $svc->fetch();
            if ($svc) {
                require_once __DIR__ . "/../config/email.php";
                sendStatusEmail($svc["email"], $svc["uname"], $svc["name"], "rejected", $comment);
            }
        }
    }
    header("Location: /panel-5588/moderate.php");
    exit;
}

// Загружаем pending сервисы
$services = $pdo->query("
    SELECT s.*, u.name as user_name, u.email as user_email, c.name as city_name
    FROM services s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN cities c ON s.city_id = c.id
    WHERE s.status = 'pending'
    ORDER BY s.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = [
    "health"=>"🏥 Здоровье","legal"=>"⚖️ Юридические","family"=>"👨‍👩‍👧 Семья",
    "shops"=>"🛒 Магазины","home"=>"🏠 Дом и быт","education"=>"📚 Образование",
    "business"=>"💼 Бизнес","transport"=>"🚗 Транспорт","events"=>"📷 События",
    "it"=>"💻 IT","realestate"=>"🏢 Недвижимость"
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Модерация — Poisq Admin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background:#F5F5F7; color:#1F2937; }
.header {
    background:#fff; padding:14px 16px;
    border-bottom:1px solid #E5E7EB;
    display:flex; align-items:center; gap:12px;
    position:sticky; top:0; z-index:10;
}
.back { font-size:20px; text-decoration:none; color:#374151; }
.header-title { font-size:17px; font-weight:700; flex:1; }
.count { background:#F59E0B; color:#fff; font-size:12px; font-weight:700; padding:2px 8px; border-radius:99px; }
.main { padding:12px; max-width:600px; margin:0 auto; }
.empty { text-align:center; padding:60px 20px; color:#9CA3AF; font-size:16px; }
.card {
    background:#fff; border-radius:14px; margin-bottom:14px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); overflow:hidden;
}
.card-photos { display:flex; gap:4px; padding:10px 10px 0; overflow-x:auto; }
.card-photos img { width:90px; height:70px; object-fit:cover; border-radius:8px; flex-shrink:0; }
.card-body { padding:14px; }
.card-name { font-size:17px; font-weight:700; margin-bottom:4px; }
.card-meta { font-size:12px; color:#9CA3AF; margin-bottom:10px; }
.card-desc { font-size:14px; color:#374151; line-height:1.5; margin-bottom:12px; max-height:80px; overflow:hidden; }
.card-desc.expanded { max-height:none; }
.btn-expand { font-size:12px; color:#2E73D8; background:none; border:none; cursor:pointer; padding:0; margin-bottom:12px; }
.contacts { background:#F9FAFB; border-radius:10px; padding:10px 12px; margin-bottom:12px; }
.contact-row { display:flex; align-items:center; gap:8px; font-size:13px; padding:3px 0; }
.contact-row a { color:#2E73D8; text-decoration:none; }
.user-info { font-size:12px; color:#6B7280; margin-bottom:12px; padding:8px 10px; background:#F0F9FF; border-radius:8px; }
.actions { display:flex; gap:8px; }
.btn-approve {
    flex:1; padding:13px; background:#10B981; color:#fff;
    border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer;
}
.btn-reject {
    flex:1; padding:13px; background:#FEF2F2; color:#EF4444;
    border:1.5px solid #FECACA; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer;
}
.btn-approve:active { background:#059669; }
.btn-reject:active { background:#FEE2E2; }
/* Модалка отклонения */
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:flex-end; }
.overlay.active { display:flex; }
.modal { background:#fff; border-radius:20px 20px 0 0; padding:24px 20px; width:100%; }
.modal-title { font-size:17px; font-weight:700; margin-bottom:16px; }
.reason-list { margin-bottom:14px; }
.reason-btn {
    display:block; width:100%; text-align:left; padding:11px 14px;
    background:#F9FAFB; border:1.5px solid #E5E7EB; border-radius:10px;
    font-size:14px; cursor:pointer; margin-bottom:8px;
}
.reason-btn.selected { border-color:#EF4444; background:#FEF2F2; color:#DC2626; }
textarea {
    width:100%; padding:12px; border:1.5px solid #D1D5DB; border-radius:10px;
    font-size:14px; resize:none; height:80px; outline:none; font-family:inherit;
}
textarea:focus { border-color:#EF4444; }
.modal-actions { display:flex; gap:8px; margin-top:12px; }
.btn-cancel { flex:1; padding:13px; background:#F5F5F7; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; }
.btn-confirm-reject { flex:1; padding:13px; background:#EF4444; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; }
</style>
</head>
<body>
<div class="header">
    <a href="/panel-5588/dashboard.php" class="back">←</a>
    <div class="header-title">Модерация</div>
    <?php if (count($services) > 0): ?>
    <span class="count"><?php echo count($services); ?></span>
    <?php endif; ?>
</div>
<div class="main">
<?php if (empty($services)): ?>
    <div class="empty">✅ Нет сервисов на модерации</div>
<?php else: ?>
    <?php foreach ($services as $svc):
        $photos = json_decode($svc["photo"] ?? "[]", true) ?: [];
        $cat    = $categories[$svc["category"]] ?? $svc["category"];
    ?>
    <div class="card">
        <?php if (!empty($photos)): ?>
        <div class="card-photos">
            <?php foreach (array_slice($photos, 0, 4) as $p): ?>
            <img src="<?php echo htmlspecialchars($p); ?>" alt="" onerror="this.style.display='none'">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="card-body">
            <div class="card-name"><?php echo htmlspecialchars($svc["name"]); ?></div>
            <div class="card-meta">
                <?php echo $cat; ?> &bull;
                <?php echo htmlspecialchars($svc["city_name"] ?? ""); ?>,
                <?php echo strtoupper($svc["country_code"]); ?> &bull;
                <?php echo date("d.m.Y H:i", strtotime($svc["created_at"])); ?>
            </div>
            <div class="card-desc" id="desc-<?php echo $svc["id"]; ?>">
                <?php echo nl2br(htmlspecialchars($svc["description"] ?? "")); ?>
            </div>
            <button class="btn-expand" onclick="toggleDesc(<?php echo $svc['id']; ?>)">Читать полностью</button>
            <div class="contacts">
                <?php if ($svc["phone"]): ?>
                <div class="contact-row">📞 <a href="tel:<?php echo htmlspecialchars($svc['phone']); ?>"><?php echo htmlspecialchars($svc["phone"]); ?></a></div>
                <?php endif; ?>
                <?php if ($svc["whatsapp"]): ?>
                <div class="contact-row">💬 <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/','',$svc['whatsapp']); ?>"><?php echo htmlspecialchars($svc["whatsapp"]); ?></a></div>
                <?php endif; ?>
                <?php if ($svc["email"]): ?>
                <div class="contact-row">✉️ <?php echo htmlspecialchars($svc["email"]); ?></div>
                <?php endif; ?>
                <?php if ($svc["address"]): ?>
                <div class="contact-row">📍 <?php echo htmlspecialchars($svc["address"]); ?></div>
                <?php endif; ?>
                <?php if ($svc["website"]): ?>
                <div class="contact-row">🌐 <a href="<?php echo htmlspecialchars($svc['website']); ?>" target="_blank"><?php echo htmlspecialchars($svc["website"]); ?></a></div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                👤 <?php echo htmlspecialchars($svc["user_name"]); ?> — <?php echo htmlspecialchars($svc["user_email"]); ?>
            </div>
            <div class="actions">
                <form method="POST" style="flex:1">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                    <button type="submit" class="btn-approve" style="width:100%">✅ Одобрить</button>
                </form>
                <button class="btn-reject" onclick="openReject(<?php echo $svc['id']; ?>)">❌ Отклонить</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Модалка отклонения -->
<div class="overlay" id="rejectOverlay" onclick="if(event.target===this)closeReject()">
    <div class="modal">
        <div class="modal-title">Причина отклонения</div>
        <div class="reason-list">
            <button class="reason-btn" onclick="selectReason(this, 'Недостаточно информации в описании')">Недостаточно информации в описании</button>
            <button class="reason-btn" onclick="selectReason(this, 'Некорректные контактные данные')">Некорректные контактные данные</button>
            <button class="reason-btn" onclick="selectReason(this, 'Фото низкого качества или отсутствуют')">Фото низкого качества или отсутствуют</button>
            <button class="reason-btn" onclick="selectReason(this, 'Нарушение правил (спам/мошенничество)')">Нарушение правил (спам/мошенничество)</button>
        </div>
        <textarea id="rejectComment" placeholder="Или напишите свою причину..."></textarea>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="service_id" id="rejectServiceId">
            <input type="hidden" name="comment" id="rejectCommentHidden">
        </form>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeReject()">Отмена</button>
            <button class="btn-confirm-reject" onclick="submitReject()">Отклонить</button>
        </div>
    </div>
</div>

<script>
function toggleDesc(id) {
    const el = document.getElementById("desc-" + id);
    el.classList.toggle("expanded");
}
function openReject(id) {
    document.getElementById("rejectServiceId").value = id;
    document.getElementById("rejectComment").value = "";
    document.querySelectorAll(".reason-btn").forEach(b => b.classList.remove("selected"));
    document.getElementById("rejectOverlay").classList.add("active");
}
function closeReject() {
    document.getElementById("rejectOverlay").classList.remove("active");
}
function selectReason(btn, text) {
    document.querySelectorAll(".reason-btn").forEach(b => b.classList.remove("selected"));
    btn.classList.add("selected");
    document.getElementById("rejectComment").value = text;
}
function submitReject() {
    const comment = document.getElementById("rejectComment").value.trim();
    if (!comment) { alert("Укажите причину отклонения"); return; }
    document.getElementById("rejectCommentHidden").value = comment;
    document.getElementById("rejectForm").submit();
}
</script>
</body>
</html>