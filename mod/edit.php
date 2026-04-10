<?php
session_start();
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/layout.php';
requireModeratorAuth('services');

$pdo = getDbConnection();
$serviceId = (int)($_GET['id'] ?? 0);
if (!$serviceId) { header("Location: /panel-5588/services.php"); exit; }

$stmt = $pdo->prepare("
    SELECT s.*, s.created_by_admin, s.call_status, s.call_note,
           u.name as user_name, u.email as user_email, c.name as city_name
    FROM services s JOIN users u ON s.user_id=u.id
    LEFT JOIN cities c ON s.city_id=c.id
    WHERE s.id=?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$service) { header("Location: /panel-5588/services.php"); exit; }

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $whatsapp    = trim($_POST['whatsapp']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $website     = trim($_POST['website']     ?? '');
    $address     = trim($_POST['address']     ?? '');
    $newStatus   = trim($_POST['status']      ?? 'draft');
    $is_visible  = isset($_POST['is_visible']) ? 1 : 0;
    $mod_comment = trim($_POST['moderation_comment'] ?? '');
    $oldStatus   = $service['status'];

    if (empty($name)) {
        $error = 'Название не может быть пустым';
    } else {
        $pdo->prepare("
            UPDATE services SET
                name=?, description=?, phone=?, whatsapp=?, email=?,
                website=?, address=?, status=?, is_visible=?,
                moderation_comment=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$name, $description, $phone, $whatsapp, $email,
                     $website, $address, $newStatus, $is_visible, $mod_comment, $serviceId]);

        if (file_exists(__DIR__ . '/../config/meilisearch.php')) {
            require_once __DIR__ . '/../config/meilisearch.php';
            if ($newStatus === 'approved' && $is_visible) {
                $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id=c.id WHERE s.id=?");
                $row->execute([$serviceId]);
                $svcRow = $row->fetch(PDO::FETCH_ASSOC);
                if ($svcRow) meiliAddDocument(meiliPrepareDoc($svcRow));
            } else {
                meiliDeleteDocument($serviceId);
            }
        }

        if ($newStatus !== $oldStatus && in_array($newStatus, ['approved','rejected'])) {
            require_once __DIR__ . '/../config/email.php';
            sendStatusEmail($service['user_email'], $service['user_name'], $name, $newStatus, $mod_comment);
        }

        $success = 'Изменения сохранены';
        if ($newStatus === 'approved' && $oldStatus !== 'approved') $success .= ' — письмо отправлено ✅';
        if ($newStatus === 'rejected' && $oldStatus !== 'rejected') $success .= ' — письмо с причиной отправлено ✅';

        $stmt = $pdo->prepare("SELECT s.*, u.name as user_name, u.email as user_email, c.name as city_name FROM services s JOIN users u ON s.user_id=u.id LEFT JOIN cities c ON s.city_id=c.id WHERE s.id=?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$statuses = ['draft'=>'Черновик','pending'=>'На модерации','approved'=>'Одобрен','rejected'=>'Отклонён'];
$categories = [
    "health"=>"🏥 Здоровье","legal"=>"⚖️ Юридические","family"=>"👨‍👩‍👧 Семья",
    "shops"=>"🛒 Магазины","home"=>"🏠 Дом","education"=>"📚 Образование",
    "business"=>"💼 Бизнес","transport"=>"🚗 Транспорт","events"=>"📷 События",
    "it"=>"💻 IT","realestate"=>"🏢 Недвижимость"
];
$photos = json_decode($service['photo'] ?? '[]', true) ?: [];
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

$statusBadge = [
    'draft'    => 'badge-gray',
    'pending'  => 'badge-yellow',
    'approved' => 'badge-green',
    'rejected' => 'badge-red',
];

ob_start();
?>

<!-- Хлебные крошки -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:13px;color:var(--text-secondary);">
    <a href="/mod/services.php" style="color:var(--primary);text-decoration:none;">Сервисы</a>
    <span>›</span>
    <span style="color:var(--text);">Редактировать #<?php echo $serviceId; ?></span>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" flex-shrink="0" style="flex-shrink:0"><path d="M20 6L9 17l-5-5"/></svg>
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- Левая колонка: форма -->
    <div>
        <form method="POST">

        <!-- Статус -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header">
                <div class="panel-title">Статус и видимость</div>
                <span class="badge <?php echo $statusBadge[$service['status']] ?? 'badge-gray'; ?>">
                    <?php echo $statuses[$service['status']] ?? $service['status']; ?>
                </span>
            </div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Статус</label>
                    <select name="status" id="statusSelect" class="form-control form-select" onchange="checkEmailHint()">
                        <?php foreach ($statuses as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $service['status']===$val?'selected':''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Показывать на сайте</label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:8px 0;">
                        <input type="checkbox" name="is_visible" id="visibleToggle" <?php echo $service['is_visible']?'checked':''; ?>
                            style="width:18px;height:18px;accent-color:var(--primary);cursor:pointer;">
                        <span style="font-size:14px;font-weight:500;" id="visibleLabel">
                            <?php echo $service['is_visible'] ? 'Виден пользователям' : 'Скрыт'; ?>
                        </span>
                    </label>
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Комментарий модератора</label>
                    <textarea name="moderation_comment" class="form-control" rows="3"
                        placeholder="Причина отклонения или комментарий для пользователя..."><?php echo htmlspecialchars($service['moderation_comment'] ?? ''); ?></textarea>
                    <div id="emailHint" style="display:none;margin-top:8px;padding:10px 12px;background:var(--warning-bg);border-radius:var(--radius-sm);font-size:12px;color:#92400E;border:1px solid #FDE68A;">
                        📧 При сохранении пользователю автоматически отправится письмо на <strong><?php echo htmlspecialchars($service['user_email']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Основная информация -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">Основная информация</div></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Название *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($service['name']); ?>" required>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Описание</label>
                    <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Адрес</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($service['address'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Контакты -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">Контакты</div></div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Телефон</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($service['phone'] ?? ''); ?>">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">WhatsApp</label>
                    <input type="tel" name="whatsapp" class="form-control" value="<?php echo htmlspecialchars($service['whatsapp'] ?? ''); ?>">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($service['email'] ?? ''); ?>">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Сайт</label>
                    <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($service['website'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Созвон -->
        <?php
        $callStatus  = $service['call_status']  ?? 'not_called';
        $callNote    = $service['call_note']     ?? '';
        $callBg      = $callStatus === 'reached'   ? 'background:#ECFDF5;border-color:#6EE7B7;'
                     : ($callStatus === 'no_answer' ? 'background:#FFFBEB;border-color:#FDE68A;' : '');
        ?>
        <div class="panel" style="margin-bottom:16px;border:1px solid var(--border);<?php echo $callBg; ?>" id="callBlock">
            <div class="panel-header"><div class="panel-title">📞 Созвон с сервисом</div></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Статус созвона</label>
                    <select id="callStatusSelect" class="form-control form-select" onchange="onCallStatusChange()">
                        <option value="not_called" <?php echo $callStatus==='not_called'?'selected':''; ?>>— Не звонили —</option>
                        <option value="no_answer"  <?php echo $callStatus==='no_answer'?'selected':''; ?>>Не дозвонились</option>
                        <option value="reached"    <?php echo $callStatus==='reached'?'selected':''; ?>>✅ Дозвонились</option>
                        <option value="no_number"  <?php echo $callStatus==='no_number'?'selected':''; ?>>Нет номера</option>
                        <option value="other"      <?php echo $callStatus==='other'?'selected':''; ?>>Другое...</option>
                    </select>
                </div>
                <div id="callNoteBlock" style="<?php echo $callStatus==='other'?'':'display:none;'; ?>">
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Заметка о созвоне</label>
                    <textarea id="callNoteText" class="form-control" rows="3" placeholder="Комментарий (виден только в админке)..."><?php echo htmlspecialchars($callNote); ?></textarea>
                </div>
                <div>
                    <button type="button" onclick="saveCallStatus(<?php echo $serviceId; ?>)" class="btn btn-secondary btn-sm" id="callSaveBtn">
                        Сохранить статус созвона
                    </button>
                    <span id="callSaveMsg" style="font-size:12px;margin-left:10px;display:none;color:#10B981;font-weight:600;">✅ Сохранено</span>
                </div>
            </div>
        </div>

        <!-- Кнопки -->
        <div style="display:flex;gap:10px;">
            <a href="/mod/services.php" class="btn btn-secondary">← Назад</a>
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="white" fill="none" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Сохранить изменения
            </button>
        </div>

        </form>
    </div>

    <!-- Правая колонка: инфо -->
    <div>
        <!-- Владелец -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">Владелец</div></div>
            <div style="padding:14px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <div style="width:38px;height:38px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;flex-shrink:0;">
                        <?php echo mb_strtoupper(mb_substr($service['user_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:600;"><?php echo htmlspecialchars($service['user_name']); ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);"><?php echo htmlspecialchars($service['user_email']); ?></div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-secondary);">Категория</span>
                        <span style="font-weight:500;"><?php echo $categories[$service['category']] ?? $service['category']; ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-secondary);">Город</span>
                        <span style="font-weight:500;"><?php echo htmlspecialchars($service['city_name'] ?? '—'); ?>, <?php echo strtoupper($service['country_code']); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-secondary);">Создан</span>
                        <span style="font-weight:500;"><?php echo date('d.m.Y H:i', strtotime($service['created_at'])); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-secondary);">Обновлён</span>
                        <span style="font-weight:500;"><?php echo date('d.m.Y H:i', strtotime($service['updated_at'] ?? $service['created_at'])); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-secondary);">Просмотры</span>
                        <span style="font-weight:500;"><?php echo (int)$service['views']; ?></span>
                    </div>
                    <?php if (!empty($service['admin_password'])): ?>
                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border-light);">
                        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;">👑 Доступ владельца</div>
                        <div style="background:var(--bg);border-radius:var(--radius-sm);padding:10px 12px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:12px;color:var(--text-secondary);">Логин</span>
                                <span style="font-size:12px;font-weight:600;font-family:monospace;">new@poisq.com</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:12px;color:var(--text-secondary);">Пароль</span>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:14px;font-weight:800;font-family:monospace;color:var(--primary);letter-spacing:2px;" id="adminPass">••••••••</span>
                                    <button type="button" onclick="togglePass()" style="font-size:11px;padding:3px 8px;border:1px solid var(--border);border-radius:4px;background:white;cursor:pointer;color:var(--text-secondary);" id="passBtn">Показать</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="copyPass()" style="margin-top:8px;width:100%;padding:7px;border:1px solid var(--border);border-radius:var(--radius-sm);background:white;font-size:12px;font-weight:600;cursor:pointer;color:var(--text-secondary);">
                            📋 Скопировать пароль
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-light);display:flex;flex-direction:column;gap:8px;">
                    <a href="https://poisq.com<?php echo serviceUrl($serviceId, $service['name']); ?>" target="_blank" class="btn btn-secondary" style="width:100%;justify-content:center;">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Открыть на сайте
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($service['user_email']); ?>?subject=<?php echo urlencode('Poisq: ' . $service['name']); ?>"
                       class="btn btn-secondary" style="width:100%;justify-content:center;">
                        <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Написать владельцу
                    </a>
                </div>
            </div>
        </div>

        <!-- Ссылка на группу (только для messengers) -->
        <?php
        $groupLink = trim($service['group_link'] ?? '');
        $isTg = $groupLink && (strpos($groupLink,'t.me')!==false || strpos($groupLink,'telegram')!==false);
        $isWa = $groupLink && strpos($groupLink,'whatsapp')!==false;
        if ($service['category'] === 'messengers' && $groupLink):
        ?>
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">💬 Ссылка на группу</div></div>
            <div style="padding:14px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span style="font-size:20px;"><?php echo $isTg ? '✈️' : ($isWa ? '💬' : '🔗'); ?></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:11px;color:var(--text-secondary);margin-bottom:2px;">
                            <?php echo $isTg ? 'Telegram' : ($isWa ? 'WhatsApp' : 'Ссылка'); ?>
                        </div>
                        <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo htmlspecialchars($groupLink); ?>
                        </div>
                    </div>
                </div>
                <a href="<?php echo htmlspecialchars($groupLink); ?>" target="_blank"
                   class="btn" style="width:100%;justify-content:center;background:<?php echo $isTg ? '#2AABEE' : ($isWa ? '#25D366' : 'var(--primary)'); ?>;color:white;border:none;">
                    <?php echo $isTg ? '✈️ Открыть в Telegram' : ($isWa ? '💬 Открыть в WhatsApp' : '🔗 Открыть ссылку'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Фото -->
        <?php if (!empty($photos)): ?>
        <div class="panel">
            <div class="panel-header"><div class="panel-title">Фотографии (<?php echo count($photos); ?>)</div></div>
            <div style="padding:14px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                <?php foreach ($photos as $i => $p): ?>
                <div style="position:relative;aspect-ratio:1;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);">
                    <img src="<?php echo htmlspecialchars($p); ?>" alt=""
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.parentElement.style.display='none'">
                    <?php if ($i === 0): ?>
                    <div style="position:absolute;top:4px;left:4px;background:var(--primary);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;">Главное</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const adminPassword = '<?php echo addslashes($service["admin_password"] ?? ""); ?>';
function togglePass() {
    const el = document.getElementById('adminPass');
    const btn = document.getElementById('passBtn');
    if (el.textContent === '••••••••') {
        el.textContent = adminPassword;
        btn.textContent = 'Скрыть';
    } else {
        el.textContent = '••••••••';
        btn.textContent = 'Показать';
    }
}
function copyPass() {
    navigator.clipboard.writeText(adminPassword).then(() => {
        const btn = event.target;
        btn.textContent = '✅ Скопировано!';
        setTimeout(() => btn.textContent = '📋 Скопировать пароль', 2000);
    });
}
const currentStatus = '<?php echo $service['status']; ?>';
function checkEmailHint() {
    const sel = document.getElementById('statusSelect').value;
    const hint = document.getElementById('emailHint');
    hint.style.display = (sel !== currentStatus && (sel === 'approved' || sel === 'rejected')) ? 'block' : 'none';
}
document.getElementById('visibleToggle').addEventListener('change', function() {
    document.getElementById('visibleLabel').textContent = this.checked ? 'Виден пользователям' : 'Скрыт';
});
checkEmailHint();

function onCallStatusChange() {
    const val = document.getElementById('callStatusSelect').value;
    document.getElementById('callNoteBlock').style.display = val === 'other' ? '' : 'none';
    const bg = val === 'reached' ? 'background:#ECFDF5;border-color:#6EE7B7;'
             : val === 'no_answer' ? 'background:#FFFBEB;border-color:#FDE68A;' : '';
    document.getElementById('callBlock').style.cssText = 'margin-bottom:16px;border:1px solid var(--border);' + bg;
}

async function saveCallStatus(serviceId) {
    const status = document.getElementById('callStatusSelect').value;
    const note   = document.getElementById('callNoteText')?.value ?? '';
    const btn    = document.getElementById('callSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Сохранение...';
    try {
        const fd = new FormData();
        fd.append('service_id', serviceId);
        fd.append('call_status', status);
        fd.append('call_note', note);
        const res = await fetch('/panel-5588/api-call-status.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            const msg = document.getElementById('callSaveMsg');
            msg.style.display = 'inline';
            setTimeout(() => msg.style.display = 'none', 3000);
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch(e) {
        alert('Ошибка сети');
    }
    btn.disabled = false;
    btn.textContent = 'Сохранить статус созвона';
}
</script>

<?php
$content = ob_get_clean();
renderModLayout('Редактировать сервис #' . $serviceId, $content);
?>
