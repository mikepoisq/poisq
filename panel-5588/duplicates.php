<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/../config/meilisearch.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

// ── ОБРАБОТКА ДЕЙСТВИЙ ────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $act   = $_POST["action"] ?? "";
    $newId = (int)($_POST["new_id"] ?? 0);
    $oldId = (int)($_POST["old_id"] ?? 0);

    if ($newId > 0) {
        if ($act === "keep_new") {
            // Одобрить новый, удалить старый
            if ($oldId > 0) {
                try { $pdo->prepare("DELETE FROM verification_requests WHERE service_id=?")->execute([$oldId]); } catch(Exception $e){}
                try { $pdo->prepare("DELETE FROM review_owner_replies WHERE review_id IN (SELECT id FROM reviews WHERE service_id=?)")->execute([$oldId]); } catch(Exception $e){}
                try { $pdo->prepare("DELETE FROM reviews WHERE service_id=?")->execute([$oldId]); } catch(Exception $e){}
                try { $pdo->prepare("DELETE FROM favorites WHERE service_id=?")->execute([$oldId]); } catch(Exception $e){}
                meiliDeleteDocument($oldId);
                $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$oldId]);
            }
            $pdo->prepare("UPDATE services SET status='approved', is_visible=1, duplicate_of=NULL, updated_at=NOW() WHERE id=?")->execute([$newId]);
            $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id=c.id WHERE s.id=? LIMIT 1");
            $row->execute([$newId]);
            $svcRow = $row->fetch(PDO::FETCH_ASSOC);
            if ($svcRow) meiliAddDocument(meiliPrepareDoc($svcRow));
            $svc = $pdo->prepare("SELECT s.name, u.email as user_email, u.name as uname FROM services s LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $svc->execute([$newId]);
            $svc = $svc->fetch();
            if ($svc && !empty($svc["user_email"])) {
                require_once __DIR__ . "/../config/email.php";
                sendStatusEmail($svc["user_email"], $svc["uname"], $svc["name"], "approved", "");
            }
        } elseif ($act === "keep_old") {
            // Удалить новый, оставить старый
            try { $pdo->prepare("DELETE FROM favorites WHERE service_id=?")->execute([$newId]); } catch(Exception $e){}
            $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$newId]);
        } elseif ($act === "not_duplicate") {
            // Отправить на обычную модерацию
            $pdo->prepare("UPDATE services SET status='pending', duplicate_of=NULL, updated_at=NOW() WHERE id=?")->execute([$newId]);
        }
    }
    header("Location: /panel-5588/duplicates.php");
    exit;
}

// ── ЗАГРУЖАЕМ ДУБЛИКАТЫ ───────────────────────────────────
$duplicates = $pdo->query("
    SELECT
        n.id as new_id, n.name as new_name, n.category as new_category,
        n.description as new_description, n.phone as new_phone,
        n.whatsapp as new_whatsapp, n.email as new_email,
        n.website as new_website, n.address as new_address,
        n.photo as new_photo, n.created_at as new_created,
        n.duplicate_of,
        u.name as user_name, u.email as user_email,
        cn.name as new_city,
        o.id as old_id, o.name as old_name, o.category as old_category,
        o.description as old_description, o.phone as old_phone,
        o.whatsapp as old_whatsapp, o.email as old_email,
        o.website as old_website, o.address as old_address,
        o.photo as old_photo, o.created_at as old_created, o.status as old_status,
        co.name as old_city
    FROM services n
    LEFT JOIN users u ON n.user_id = u.id
    LEFT JOIN cities cn ON n.city_id = cn.id
    LEFT JOIN services o ON n.duplicate_of = o.id
    LEFT JOIN cities co ON o.city_id = co.id
    WHERE n.status = 'duplicate'
    ORDER BY n.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$duplicatesCount = count($duplicates);

$categories = [
    "health"=>"🏥 Здоровье","legal"=>"⚖️ Юридические","family"=>"👨‍👩‍👧 Семья",
    "shops"=>"🛒 Магазины","home"=>"🏠 Дом","education"=>"📚 Образование",
    "business"=>"💼 Бизнес","transport"=>"🚗 Транспорт","events"=>"📷 События",
    "it"=>"💻 IT","realestate"=>"🏢 Недвижимость"
];

ob_start();
?>

<style>
.dup-grid { display:grid; gap:20px; }
.dup-card {
    background:var(--bg-white); border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden;
}
.dup-warn {
    padding:10px 16px; background:#FFFBEB; border-bottom:1px solid #FDE68A;
    font-size:13px; font-weight:600; color:#92400E; display:flex; align-items:center; gap:8px;
}
body.dark-theme .dup-warn { background:#2A2210; color:#FBBF24; border-color:#3D2E0A; }
.dup-cols { display:grid; grid-template-columns:1fr 1fr; }
.dup-col { padding:16px; }
.dup-col + .dup-col { border-left:1px solid var(--border); }
.col-badge {
    display:inline-flex; align-items:center; gap:5px;
    font-size:11px; font-weight:700; padding:3px 10px;
    border-radius:99px; margin-bottom:12px;
}
.badge-new { background:#DBEAFE; color:#1D4ED8; }
.badge-old { background:var(--border-light); color:var(--text-secondary); }
body.dark-theme .badge-new { background:#1E2D3D; color:#93C5FD; }
.col-name { font-size:16px; font-weight:700; margin-bottom:4px; }
.col-meta { font-size:12px; color:var(--text-light); margin-bottom:10px; line-height:1.6; }
.col-desc {
    font-size:13px; color:var(--text-secondary); line-height:1.5;
    max-height:72px; overflow:hidden; margin-bottom:8px;
}
.col-desc.exp { max-height:none; }
.btn-exp { font-size:11px; color:var(--primary); background:none; border:none; cursor:pointer; padding:0; margin-bottom:10px; }
.col-contacts { font-size:13px; }
.col-contacts .cr { padding:3px 0; display:flex; gap:7px; align-items:flex-start; color:var(--text); }
.col-contacts .cr a { color:var(--primary); text-decoration:none; }
.col-photos { display:flex; gap:4px; margin-bottom:10px; overflow-x:auto; }
.col-photos img { width:72px; height:56px; object-fit:cover; border-radius:7px; flex-shrink:0; }
.user-tag {
    font-size:11px; color:var(--text-secondary);
    padding:6px 10px; background:var(--primary-light);
    border-radius:7px; margin-top:10px;
}
.dup-actions {
    padding:12px 16px; border-top:1px solid var(--border);
    display:flex; gap:8px; flex-wrap:wrap; background:var(--bg);
}
.btn-keep-new {
    flex:1; min-width:160px; padding:11px 14px;
    background:var(--success); color:#fff;
    border:none; border-radius:var(--radius-sm);
    font-size:13px; font-weight:700; cursor:pointer; font-family:inherit;
    transition:background 0.15s;
}
.btn-keep-new:hover { background:#059669; }
.btn-keep-old {
    flex:1; min-width:160px; padding:11px 14px;
    background:var(--bg-white); color:var(--text);
    border:1.5px solid var(--border); border-radius:var(--radius-sm);
    font-size:13px; font-weight:600; cursor:pointer; font-family:inherit;
    transition:background 0.15s;
}
.btn-keep-old:hover { background:var(--bg); }
.btn-not-dup {
    flex:1; min-width:160px; padding:11px 14px;
    background:var(--primary-light); color:var(--primary);
    border:1.5px solid #BFDBFE; border-radius:var(--radius-sm);
    font-size:13px; font-weight:600; cursor:pointer; font-family:inherit;
    transition:background 0.15s;
}
.btn-not-dup:hover { background:#DBEAFE; }
.status-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:3px; vertical-align:middle; }
.dot-approved { background:var(--success); }
.dot-pending  { background:var(--warning); }
@media(max-width:600px) {
    .dup-cols { grid-template-columns:1fr; }
    .dup-col + .dup-col { border-left:none; border-top:1px solid var(--border); }
}
</style>

<?php if (empty($duplicates)): ?>
<div class="empty-state">
    <div class="empty-state-icon">✅</div>
    <div class="empty-state-title">Дублей нет</div>
    <div class="empty-state-text">Все сервисы уникальны</div>
</div>
<?php else: ?>
<div class="dup-grid">
<?php foreach ($duplicates as $d):
    $newPhotos = json_decode($d["new_photo"] ?? "[]", true) ?: [];
    $oldPhotos = json_decode($d["old_photo"] ?? "[]", true) ?: [];
    $cat = $categories[$d["new_category"]] ?? $d["new_category"];
    $oldStatusClass = ($d["old_status"] ?? "") === "approved" ? "dot-approved" : "dot-pending";
    $oldStatusText  = ($d["old_status"] ?? "") === "approved" ? "Активный" : "На модерации";
?>
<div class="dup-card">
    <div class="dup-warn">
        ⚠️ Возможный дубль — совпадение по телефону, email или названию
    </div>
    <div class="dup-cols">

        <!-- НОВЫЙ -->
        <div class="dup-col">
            <span class="col-badge badge-new">● НОВЫЙ</span>
            <?php if (!empty($newPhotos)): ?>
            <div class="col-photos">
                <?php foreach (array_slice($newPhotos,0,3) as $p): ?>
                <img src="<?php echo htmlspecialchars($p); ?>" alt="" onerror="this.style.display='none'">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="col-name"><?php echo htmlspecialchars($d["new_name"]); ?></div>
            <div class="col-meta">
                <?php echo $cat; ?> · <?php echo htmlspecialchars($d["new_city"] ?? "—"); ?><br>
                Добавлен: <?php echo date("d.m.Y H:i", strtotime($d["new_created"])); ?>
            </div>
            <div class="col-desc" id="nd-<?php echo $d["new_id"]; ?>">
                <?php echo nl2br(htmlspecialchars($d["new_description"] ?? "")); ?>
            </div>
            <button class="btn-exp" onclick="toggleDesc('nd-<?php echo $d["new_id"]; ?>')">Читать полностью ↓</button>
            <div class="col-contacts">
                <?php if ($d["new_phone"]): ?><div class="cr">📞 <?php echo htmlspecialchars($d["new_phone"]); ?></div><?php endif; ?>
                <?php if ($d["new_whatsapp"]): ?><div class="cr">💬 <?php echo htmlspecialchars($d["new_whatsapp"]); ?></div><?php endif; ?>
                <?php if ($d["new_email"]): ?><div class="cr">✉️ <?php echo htmlspecialchars($d["new_email"]); ?></div><?php endif; ?>
                <?php if ($d["new_address"]): ?><div class="cr">📍 <?php echo htmlspecialchars($d["new_address"]); ?></div><?php endif; ?>
                <?php if ($d["new_website"]): ?><div class="cr">🌐 <a href="<?php echo htmlspecialchars($d["new_website"]); ?>" target="_blank"><?php echo htmlspecialchars($d["new_website"]); ?></a></div><?php endif; ?>
            </div>
            <div class="user-tag">👤 <?php echo htmlspecialchars($d["user_name"]); ?> · <?php echo htmlspecialchars($d["user_email"]); ?></div>
        </div>

        <!-- СУЩЕСТВУЮЩИЙ -->
        <div class="dup-col">
            <span class="col-badge badge-old">
                <span class="status-dot <?php echo $oldStatusClass; ?>"></span>
                СУЩЕСТВУЮЩИЙ (<?php echo $oldStatusText; ?>)
            </span>
            <?php if ($d["old_id"]): ?>
            <?php if (!empty($oldPhotos)): ?>
            <div class="col-photos">
                <?php foreach (array_slice($oldPhotos,0,3) as $p): ?>
                <img src="<?php echo htmlspecialchars($p); ?>" alt="" onerror="this.style.display='none'">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="col-name"><?php echo htmlspecialchars($d["old_name"] ?? ""); ?></div>
            <div class="col-meta">
                <?php echo ($categories[$d["old_category"]] ?? $d["old_category"]); ?> · <?php echo htmlspecialchars($d["old_city"] ?? "—"); ?><br>
                Добавлен: <?php echo $d["old_created"] ? date("d.m.Y H:i", strtotime($d["old_created"])) : "—"; ?>
                · <a href="<?php echo serviceUrl($d["old_id"], $d["old_name"]); ?>" target="_blank" style="color:var(--primary)">Открыть ↗</a>
            </div>
            <div class="col-desc" id="od-<?php echo $d["new_id"]; ?>">
                <?php echo nl2br(htmlspecialchars($d["old_description"] ?? "")); ?>
            </div>
            <button class="btn-exp" onclick="toggleDesc('od-<?php echo $d["new_id"]; ?>')">Читать полностью ↓</button>
            <div class="col-contacts">
                <?php if ($d["old_phone"]): ?><div class="cr">📞 <?php echo htmlspecialchars($d["old_phone"]); ?></div><?php endif; ?>
                <?php if ($d["old_whatsapp"]): ?><div class="cr">💬 <?php echo htmlspecialchars($d["old_whatsapp"]); ?></div><?php endif; ?>
                <?php if ($d["old_email"]): ?><div class="cr">✉️ <?php echo htmlspecialchars($d["old_email"]); ?></div><?php endif; ?>
                <?php if ($d["old_address"]): ?><div class="cr">📍 <?php echo htmlspecialchars($d["old_address"]); ?></div><?php endif; ?>
                <?php if ($d["old_website"]): ?><div class="cr">🌐 <a href="<?php echo htmlspecialchars($d["old_website"]); ?>" target="_blank"><?php echo htmlspecialchars($d["old_website"]); ?></a></div><?php endif; ?>
            </div>
            <?php else: ?>
            <div style="color:var(--text-light);font-size:13px;padding:20px 0">Оригинал был удалён</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Действия -->
    <div class="dup-actions">
        <form method="POST" style="display:contents" onsubmit="return confirm('Одобрить новый и удалить существующий?')">
            <input type="hidden" name="action" value="keep_new">
            <input type="hidden" name="new_id" value="<?php echo $d["new_id"]; ?>">
            <input type="hidden" name="old_id" value="<?php echo $d["old_id"] ?? 0; ?>">
            <button type="submit" class="btn-keep-new">✅ Оставить новый, удалить старый</button>
        </form>
        <form method="POST" style="display:contents" onsubmit="return confirm('Удалить новый сервис?')">
            <input type="hidden" name="action" value="keep_old">
            <input type="hidden" name="new_id" value="<?php echo $d["new_id"]; ?>">
            <button type="submit" class="btn-keep-old">🗑 Удалить новый, оставить старый</button>
        </form>
        <form method="POST" style="display:contents">
            <input type="hidden" name="action" value="not_duplicate">
            <input type="hidden" name="new_id" value="<?php echo $d["new_id"]; ?>">
            <button type="submit" class="btn-not-dup">↗ Это не дубль → на модерацию</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function toggleDesc(id) {
    const el = document.getElementById(id);
    if (el) {
        const exp = el.classList.toggle('exp');
        const btn = el.nextElementSibling;
        if (btn) btn.textContent = exp ? 'Свернуть ↑' : 'Читать полностью ↓';
    }
}
</script>

<?php
$content = ob_get_clean();
renderLayout('Дубликаты', $content, 0, 0, 0, 0, $duplicatesCount);
?>
