<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAuthAny('moderation');

$pdo = getDbConnection();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action    = $_POST["action"] ?? "";
    $serviceId = (int)($_POST["service_id"] ?? 0);
    if ($serviceId > 0) {
        if ($action === "approve") {
            $pdo->prepare("UPDATE services SET status='approved', is_visible=1 WHERE id=?")->execute([$serviceId]);
            if (file_exists(__DIR__ . '/../config/meilisearch.php')) {
                require_once __DIR__ . '/../config/meilisearch.php';
                $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id = c.id WHERE s.id = ?");
                $row->execute([$serviceId]);
                $svcRow = $row->fetch(PDO::FETCH_ASSOC);
                if ($svcRow) meiliAddDocument(meiliPrepareDoc($svcRow));
            }
            $svc = $pdo->prepare("SELECT s.name, u.email, u.name as uname FROM services s JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $svc->execute([$serviceId]);
            $svc = $svc->fetch();
            if ($svc) { require_once __DIR__ . "/../config/email.php"; sendStatusEmail($svc["email"], $svc["uname"], $svc["name"], "approved", ""); }
        } elseif ($action === "reject") {
            $comment = trim($_POST["comment"] ?? "");
            $pdo->prepare("UPDATE services SET status='rejected', is_visible=0, moderation_comment=? WHERE id=?")->execute([$comment, $serviceId]);
            if (file_exists(__DIR__ . '/../config/meilisearch.php')) { require_once __DIR__ . '/../config/meilisearch.php'; meiliDeleteDocument($serviceId); }
            $svc = $pdo->prepare("SELECT s.name, u.email, u.name as uname FROM services s JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $svc->execute([$serviceId]);
            $svc = $svc->fetch();
            if ($svc) { require_once __DIR__ . "/../config/email.php"; sendStatusEmail($svc["email"], $svc["uname"], $svc["name"], "rejected", $comment); }
        }
    }
    header("Location: /panel-5588/moderate.php");
    exit;
}

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();

$services = $pdo->query("
    SELECT s.*, s.created_by_admin, s.created_by_moderator, s.call_status, s.call_note,
           u.name as user_name, u.email as user_email,
           c.name as city_name, c.status as city_status
    FROM services s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN cities c ON s.city_id = c.id
    WHERE s.status = 'pending'
    ORDER BY s.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = [
    "health"=>"Здоровье","legal"=>"Юридические","family"=>"Семья",
    "shops"=>"Магазины","home"=>"Дом","education"=>"Образование",
    "business"=>"Бизнес","transport"=>"Транспорт","events"=>"События",
    "it"=>"IT","realestate"=>"Недвижимость"
];

ob_start();
?>

<style>
.crown-blue { color: #3B6CF4; font-size: 14px; margin-left: 4px; cursor: default; }
.crown-green { color: #10B981; font-size: 14px; margin-left: 4px; cursor: default; }
</style>

<?php if (empty($services)): ?>
<div class="panel">
    <div class="empty-state" style="padding:64px 24px;">
        <div class="empty-state-icon">✅</div>
        <div class="empty-state-title">Нет сервисов на модерации</div>
        <div class="empty-state-text">Все сервисы проверены. Возвращайтесь позже.</div>
    </div>
</div>
<?php else: ?>

<div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:14px;color:var(--text-secondary);">Ожидают проверки:</span>
    <span style="font-size:14px;font-weight:700;color:var(--warning)"><?php echo count($services); ?> сервисов</span>
    <span style="font-size:13px;color:var(--text-light)">· Сортировка: сначала старые</span>
</div>

<?php foreach ($services as $svc):
    $photos = json_decode($svc["photo"] ?? "[]", true) ?: [];
    $cat    = $categories[$svc["category"]] ?? $svc["category"];
    $langs  = json_decode($svc["languages"] ?? "[]", true) ?: [];
    $svcs   = json_decode($svc["services"] ?? "[]", true) ?: [];
    $social = json_decode($svc["social"] ?? "{}", true) ?: [];
?>
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <?php echo htmlspecialchars($svc["name"]); ?>
                <?php if ($svc['created_by_moderator'] !== null): ?>
                <span class="crown-green" title="Создан модератором">👑</span>
                <?php elseif ($svc['created_by_admin'] !== null): ?>
                <span class="crown-blue" title="Создан администратором">👑</span>
                <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--text-light);margin-top:2px;">
                #<?php echo $svc['id']; ?> ·
                <?php echo $cat; ?> ·
                <?php echo htmlspecialchars($svc["city_name"] ?? ""); ?>, <?php echo strtoupper($svc["country_code"]); ?>
                <?php if (($svc['city_status'] ?? '') === 'pending'): ?>
                <span style="background:#FEF3C7;color:#92400E;font-size:11px;font-weight:700;padding:1px 6px;border-radius:4px;border:1px solid #FCD34D" id="city-badge-<?php echo $svc['id']; ?>">⚠️ pending</span>
                <?php endif; ?> ·
                <?php echo date("d.m.Y H:i", strtotime($svc["created_at"])); ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/panel-5588/edit.php?id=<?php echo $svc['id']; ?>" class="btn btn-secondary btn-sm">✏️ Редактировать</a>
            <a href="https://poisq.com/service/<?php echo $svc['id']; ?>-preview" target="_blank" class="btn btn-secondary btn-sm">👁 Превью</a>
        </div>
    </div>

    <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <!-- Левая колонка -->
        <div>
            <!-- Фото -->
            <?php if (!empty($photos)): ?>
            <div style="display:flex;gap:6px;margin-bottom:14px;overflow-x:auto;padding-bottom:4px;">
                <?php foreach (array_slice($photos, 0, 5) as $p): ?>
                <img src="<?php echo htmlspecialchars($p); ?>" alt=""
                     style="width:80px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0;border:1px solid var(--border)"
                     onerror="this.style.display='none'">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Описание -->
            <div style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;max-height:120px;overflow:hidden;" id="desc-<?php echo $svc['id']; ?>">
                <?php echo nl2br(htmlspecialchars($svc["description"] ?? "")); ?>
            </div>
            <button onclick="toggleDesc(<?php echo $svc['id']; ?>)" style="font-size:12px;color:var(--primary);background:none;border:none;cursor:pointer;padding:0;margin-bottom:14px;">
                Читать полностью ↓
            </button>

            <!-- Услуги -->
            <?php if (!empty($svcs)): ?>
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Услуги</div>
                <?php foreach (array_slice($svcs, 0, 4) as $sv): ?>
                <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border-light);">
                    <span><?php echo htmlspecialchars($sv['name'] ?? ''); ?></span>
                    <span style="color:var(--text-secondary)"><?php echo htmlspecialchars($sv['price'] ?? ''); ?>€</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Правая колонка -->
        <div>
            <!-- Контакты -->
            <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px;margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Контакты</div>
                <?php if ($svc["phone"]): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;">
                    <span style="color:var(--text-light);width:16px">📞</span>
                    <a href="tel:<?php echo htmlspecialchars($svc['phone']); ?>" style="color:var(--primary);text-decoration:none"><?php echo htmlspecialchars($svc["phone"]); ?></a>
                </div>
                <?php endif; ?>
                <?php if ($svc["whatsapp"]): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;">
                    <span style="color:var(--text-light);width:16px">💬</span>
                    <span><?php echo htmlspecialchars($svc["whatsapp"]); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($svc["email"]): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;">
                    <span style="color:var(--text-light);width:16px">✉️</span>
                    <span><?php echo htmlspecialchars($svc["email"]); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($svc["address"]): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;">
                    <span style="color:var(--text-light);width:16px">📍</span>
                    <span><?php echo htmlspecialchars($svc["address"]); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($svc["website"]): ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;">
                    <span style="color:var(--text-light);width:16px">🌐</span>
                    <a href="<?php echo htmlspecialchars($svc['website']); ?>" target="_blank" style="color:var(--primary);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($svc["website"]); ?></a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Созвон -->
            <?php
            $callStatus = $svc['call_status'] ?? 'not_called';
            $callNote   = $svc['call_note']   ?? '';
            $callBg     = $callStatus === 'reached'   ? 'background:#ECFDF5;border-color:#6EE7B7;'
                        : ($callStatus === 'no_answer' ? 'background:#FFFBEB;border-color:#FDE68A;' : 'background:var(--bg);');
            ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:14px;<?php echo $callBg; ?>" id="callBlock-<?php echo $svc['id']; ?>">
                <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">📞 Созвон с сервисом</div>
                <select id="callStatus-<?php echo $svc['id']; ?>" class="form-control form-select" style="margin-bottom:8px;" onchange="onCallChange(<?php echo $svc['id']; ?>)">
                    <option value="not_called" <?php echo $callStatus==='not_called'?'selected':''; ?>>— Не звонили —</option>
                    <option value="no_answer"  <?php echo $callStatus==='no_answer'?'selected':''; ?>>Не дозвонились</option>
                    <option value="reached"    <?php echo $callStatus==='reached'?'selected':''; ?>>✅ Дозвонились</option>
                    <option value="no_number"  <?php echo $callStatus==='no_number'?'selected':''; ?>>Нет номера</option>
                    <option value="other"      <?php echo $callStatus==='other'?'selected':''; ?>>Другое...</option>
                </select>
                <div id="callNote-<?php echo $svc['id']; ?>" style="<?php echo $callStatus==='other'?'':'display:none;'; ?>margin-bottom:8px;">
                    <textarea id="callNoteText-<?php echo $svc['id']; ?>" class="form-control" rows="2" placeholder="Заметка (только в админке)..."><?php echo htmlspecialchars($callNote); ?></textarea>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="saveCallStatus(<?php echo $svc['id']; ?>)" id="callBtn-<?php echo $svc['id']; ?>">Сохранить</button>
                    <span id="callMsg-<?php echo $svc['id']; ?>" style="font-size:12px;display:none;color:#10B981;font-weight:600;">✅ Сохранено</span>
                </div>
            </div>

            <!-- Владелец -->
            <div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:12px;margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:var(--primary-dark);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Владелец</div>
                <div style="font-size:13px;font-weight:600;color:var(--text)"><?php echo htmlspecialchars($svc["user_name"]); ?></div>
                <div style="font-size:12px;color:var(--text-secondary)"><?php echo htmlspecialchars($svc["user_email"]); ?></div>
            </div>

            <!-- Pending city warning -->
            <?php if (($svc['city_status'] ?? '') === 'pending'): ?>
            <div id="city-warn-<?php echo $svc['id']; ?>" style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                <div style="font-size:13px;color:#92400E;font-weight:600">
                    ⚠️ Город не в базе: «<?php echo htmlspecialchars($svc['city_name'] ?? ''); ?>»
                </div>
                <button class="btn btn-sm" style="background:#F59E0B;color:white;border:none"
                    onclick="approveCity(<?php echo $svc['city_id']; ?>, <?php echo $svc['id']; ?>)">
                    + Добавить город в БД
                </button>
            </div>
            <?php endif; ?>

            <!-- Языки -->
            <?php if (!empty($langs)): ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
                <?php foreach ($langs as $l): ?>
                <span class="badge badge-blue"><?php echo htmlspecialchars($l); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Действия -->
            <div style="display:flex;gap:8px;">
                <form method="POST" style="flex:1">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                    <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;">
                        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                        Одобрить
                    </button>
                </form>
                <button class="btn btn-danger" style="flex:1;justify-content:center;" onclick="openReject(<?php echo $svc['id']; ?>)">
                    <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    Отклонить
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Модалка отклонения -->
<div id="rejectOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--bg-white);border-radius:var(--radius);padding:24px;width:100%;max-width:440px;margin:20px;">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px;">Причина отклонения</div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
            <?php foreach ([
                'Недостаточно информации в описании',
                'Некорректные контактные данные',
                'Фото низкого качества или отсутствуют',
                'Нарушение правил (спам/мошенничество)',
            ] as $reason): ?>
            <button class="reason-btn" onclick="selectReason(this, '<?php echo addslashes($reason); ?>')"
                style="text-align:left;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);font-size:13px;cursor:pointer;font-family:inherit;transition:all 0.15s;">
                <?php echo $reason; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <textarea id="rejectComment" placeholder="Или напишите свою причину..."
            style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;resize:none;height:80px;outline:none;margin-bottom:14px;"></textarea>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="service_id" id="rejectServiceId">
            <input type="hidden" name="comment" id="rejectCommentHidden">
        </form>
        <div style="display:flex;gap:8px;">
            <button onclick="closeReject()" class="btn btn-secondary" style="flex:1;justify-content:center;">Отмена</button>
            <button onclick="submitReject()" class="btn btn-danger" style="flex:1;justify-content:center;background:var(--danger);color:white;">Отклонить</button>
        </div>
    </div>
</div>

<script>
function toggleDesc(id) {
    const el = document.getElementById("desc-" + id);
    el.style.maxHeight = el.style.maxHeight === 'none' ? '120px' : 'none';
}
function openReject(id) {
    document.getElementById("rejectServiceId").value = id;
    document.getElementById("rejectComment").value = "";
    document.querySelectorAll(".reason-btn").forEach(b => b.style.background = 'var(--bg)');
    document.getElementById("rejectOverlay").style.display = "flex";
}
function closeReject() {
    document.getElementById("rejectOverlay").style.display = "none";
}
function selectReason(btn, text) {
    document.querySelectorAll(".reason-btn").forEach(b => b.style.background = 'var(--bg)');
    btn.style.background = 'var(--danger-bg)';
    btn.style.borderColor = 'var(--danger)';
    document.getElementById("rejectComment").value = text;
}
function submitReject() {
    const comment = document.getElementById("rejectComment").value.trim();
    if (!comment) { alert("Укажите причину отклонения"); return; }
    document.getElementById("rejectCommentHidden").value = comment;
    document.getElementById("rejectForm").submit();
}
document.getElementById("rejectOverlay").addEventListener("click", function(e) {
    if (e.target === this) closeReject();
});

function onCallChange(id) {
    const val = document.getElementById('callStatus-' + id).value;
    document.getElementById('callNote-' + id).style.display = val === 'other' ? '' : 'none';
    const bg = val === 'reached'   ? 'background:#ECFDF5;border-color:#6EE7B7;'
             : val === 'no_answer' ? 'background:#FFFBEB;border-color:#FDE68A;' : 'background:var(--bg);';
    document.getElementById('callBlock-' + id).style.cssText =
        'border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:14px;' + bg;
}

async function saveCallStatus(id) {
    const status = document.getElementById('callStatus-' + id).value;
    const noteEl = document.getElementById('callNoteText-' + id);
    const note   = noteEl ? noteEl.value : '';
    const btn    = document.getElementById('callBtn-' + id);
    btn.disabled = true;
    btn.textContent = '...';
    try {
        const fd = new FormData();
        fd.append('service_id', id);
        fd.append('call_status', status);
        fd.append('call_note', note);
        const res = await fetch('/panel-5588/api-call-status.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            const msg = document.getElementById('callMsg-' + id);
            msg.style.display = 'inline';
            setTimeout(() => msg.style.display = 'none', 3000);
        } else {
            alert('Ошибка: ' + (data.error || '?'));
        }
    } catch(e) {
        alert('Ошибка сети');
    }
    btn.disabled = false;
    btn.textContent = 'Сохранить';
}

async function approveCity(cityId, svcId) {
    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('id', cityId);
    const res = await fetch('/panel-5588/settings/cities.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
        const warn = document.getElementById('city-warn-'+svcId);
        const badge = document.getElementById('city-badge-'+svcId);
        if (warn) warn.remove();
        if (badge) badge.remove();
    } else {
        alert('Ошибка: ' + data.error);
    }
}
</script>

<?php
$content = ob_get_clean();
renderLayout('Модерация', $content, $pendingCount);
?>
