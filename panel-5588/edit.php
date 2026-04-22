<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/helpers.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();
$serviceId = (int)($_GET['id'] ?? 0);
if (!$serviceId) { header("Location: /panel-5588/services.php"); exit; }

$stmt = $pdo->prepare("
    SELECT s.*, s.created_by_admin, s.call_status, s.call_note,
           u.name as user_name, u.email as user_email, c.name as city_name
    FROM services s LEFT JOIN users u ON s.user_id=u.id
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
    $category    = trim($_POST['category']    ?? $service['category']);
    $subcategory = trim($_POST['subcategory'] ?? $service['subcategory'] ?? '');
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
        // Обработка фото
        $currentPhotos = json_decode($service['photo'] ?? '[]', true) ?: [];
        // Удаляем отмеченные фото
        $deletePhotos = $_POST['delete_photos'] ?? [];
        if (!empty($deletePhotos)) {
            foreach ($deletePhotos as $dp) {
                $filePath = __DIR__ . '/../' . ltrim($dp, '/');
                if (file_exists($filePath)) @unlink($filePath);
            }
            $currentPhotos = array_values(array_filter($currentPhotos, fn($p) => !in_array($p, $deletePhotos)));
        }
        // Загружаем новые фото
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $count = min(count($_FILES['photos']['name']), 5 - count($currentPhotos));
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp  = $_FILES['photos']['tmp_name'][$i];
                $info = @getimagesize($tmp);
                if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp'])) continue;
                if ($_FILES['photos']['size'][$i] > 10 * 1024 * 1024) continue;
                $fileName   = uniqid('photo_') . '.jpg';
                $targetPath = $uploadDir . $fileName;
                $src = imagecreatefromstring(file_get_contents($tmp));
                $width = imagesx($src); $height = imagesy($src);
                if ($width > 800) {
                    $ratio = 800 / $width;
                    $dst   = imagecreatetruecolor(800, (int)($height * $ratio));
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, 800, (int)($height * $ratio), $width, $height);
                    imagejpeg($dst, $targetPath, 85);
                    imagedestroy($dst);
                } else {
                    imagejpeg($src, $targetPath, 85);
                }
                imagedestroy($src);
                $currentPhotos[] = '/uploads/' . $fileName;
            }
        }
        $photoJson = json_encode(array_values($currentPhotos), JSON_UNESCAPED_UNICODE);

        // Обработка часов работы
        $hoursRaw = $_POST['hours'] ?? [];
        $hoursData = [];
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) {
            $hoursData[$d] = ['open'=>$hoursRaw[$d]['open']??'','close'=>$hoursRaw[$d]['close']??'','break_start'=>$hoursRaw[$d]['break_start']??'','break_end'=>$hoursRaw[$d]['break_end']??''];
        }
        $hoursJson = json_encode($hoursData, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("
            UPDATE services SET
                name=?, description=?, phone=?, whatsapp=?, email=?,
                website=?, address=?, status=?, is_visible=?,
                moderation_comment=?, photo=?, hours=?, category=?, subcategory=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$name, $description, $phone, $whatsapp, $email,
                     $website, $address, $newStatus, $is_visible, $mod_comment, $photoJson, $hoursJson, $category, $subcategory, $serviceId]);

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

        $stmt = $pdo->prepare("SELECT s.*, u.name as user_name, u.email as user_email, c.name as city_name FROM services s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN cities c ON s.city_id=c.id WHERE s.id=?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$statuses = ['draft'=>'Черновик','pending'=>'На модерации','approved'=>'Одобрен','rejected'=>'Отклонён'];
$categories = [
    'health'     => ['name'=>'🏥 Здоровье и красота',        'subs'=>['Врачи','Стоматология','Психология','Альтернативная медицина','Салоны красоты','Фитнес и спорт','Аптеки']],
    'legal'      => ['name'=>'⚖️ Юридические услуги',        'subs'=>['Иммиграция','Семейное право','Недвижимость','Бизнес право','Нотариус','Консультации']],
    'family'     => ['name'=>'👨‍👩‍👧 Семья и дети',           'subs'=>['Няни','Репетиторы','Детские кружки','Бэбиситтеры','Детские праздники','Детские товары']],
    'shops'      => ['name'=>'🛒 Магазины и продукты',       'subs'=>['Русские магазины','Доставка продуктов','Рестораны','Пекарни','Мясные лавки','Онлайн магазины']],
    'home'       => ['name'=>'🏠 Дом и быт',                 'subs'=>['Уборка','Ремонт','Переезды','Химчистка','Животные','Сад и огород']],
    'education'  => ['name'=>'📚 Образование',               'subs'=>['Языковые курсы','Русский язык','Школьные предметы','Музыка','Профессиональные курсы','Онлайн обучение']],
    'business'   => ['name'=>'💼 Бизнес и финансы',          'subs'=>['Бухгалтерия','Налоги','Страхование','Бизнес консультации','Переводы денег']],
    'transport'  => ['name'=>'🚗 Транспорт и авто',          'subs'=>['Авто сервис','Автошкола','Такси/Трансфер','Аренда авто','Покупка авто']],
    'events'     => ['name'=>'📷 События и развлечения',     'subs'=>['Фотографы','Видеографы','Праздники','Туризм','Развлечения','Культура']],
    'it'         => ['name'=>'💻 IT и онлайн услуги',        'subs'=>['Веб разработка','Дизайн','Ремонт техники','SMM/Маркетинг','Консультации']],
    'realestate' => ['name'=>'🏢 Недвижимость',              'subs'=>['Аренда','Покупка','Продажа','Управление','Ипотека']],
    'messengers' => ['name'=>'💬 Группы ВатсАп и Телеграм', 'subs'=>['WhatsApp группы','Telegram каналы','Telegram группы','Чаты и сообщества']],
];
$photos = json_decode($service['photo'] ?? '[]', true) ?: [];
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
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
    <a href="/panel-5588/services.php" style="color:var(--primary);text-decoration:none;">Сервисы</a>
    <span>›</span>
    <span style="color:var(--text);">Редактировать #<?php echo $serviceId; ?></span>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- Левая колонка: форма -->
    <div>
        <form method="POST" enctype="multipart/form-data" id="mainEditForm">

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
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Категория *</label>
                        <select name="category" id="editCategorySelect" class="form-control form-select" onchange="updateEditSubcategories()" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $key => $cat): ?>
                            <option value="<?php echo $key; ?>" <?php echo $service['category']===$key?'selected':''; ?>><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Подкатегория</label>
                        <select name="subcategory" id="editSubcategorySelect" class="form-control form-select">
                            <option value="">Выберите подкатегорию</option>
                            <?php if (!empty($service['category']) && isset($categories[$service['category']])): ?>
                                <?php foreach ($categories[$service['category']]['subs'] as $sub): ?>
                                <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo $service['subcategory']===$sub?'selected':''; ?>><?php echo htmlspecialchars($sub); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Описание</label>
                    <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Адрес</label>
                    <small style="color:#10B981;font-size:11px;display:block;margin-bottom:4px;">Для подсказок сначала выберите страну и город</small>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($service['address'] ?? ''); ?>" data-country="<?php echo htmlspecialchars($service['country_code'] ?? ''); ?>">
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

        <!-- Фото -->
        <div class="panel">
            <div class="panel-header"><div class="panel-title">Фотографии (<?php echo count($photos); ?>/5)</div></div>
            <div style="padding:14px;">
                <?php if (!empty($photos)): ?>
                <div id="currentPhotos" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
                    <?php foreach ($photos as $i => $p): ?>
                    <div class="photo-edit-item" id="photo_<?php echo $i; ?>">
                        <img src="<?php echo htmlspecialchars($p); ?>" alt=""
                             onerror="this.parentElement.style.opacity='0.3'">
                        <?php if ($i === 0): ?>
                        <div class="photo-edit-badge">Главное</div>
                        <?php endif; ?>
                        <button type="button" class="photo-edit-remove"
                                data-photo="<?php echo htmlspecialchars($p); ?>"
                                title="Удалить фото">✕</button>
                        <input type="hidden" name="keep_photos[]" value="<?php echo htmlspecialchars($p); ?>" id="keep_<?php echo $i; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (count($photos) < 5): ?>
                <div id="photoUploadZone" onclick="document.getElementById('newPhotoInput').click()"
                     style="border:2px dashed var(--border);border-radius:var(--radius-sm);padding:20px;text-align:center;cursor:pointer;color:var(--text-secondary);font-size:13px;transition:all .2s;"
                     onmouseenter="this.style.borderColor='var(--primary)'"
                     onmouseleave="this.style.borderColor='var(--border)'">
                    📷 Нажмите чтобы добавить фото (макс. <?php echo 5 - count($photos); ?> шт., до 10 МБ каждое)
                </div>
                <input type="file" id="newPhotoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                       style="display:none;" onchange="handleNewPhotos(event)">
                <div id="newPhotoPreview" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px;"></div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Часы работы -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">🕐 Часы работы</div></div>
            <div style="padding:16px;">
                <div id="hoursContainer">
                <?php
                $days = ['mon'=>'Понедельник','tue'=>'Вторник','wed'=>'Среда','thu'=>'Четверг','fri'=>'Пятница','sat'=>'Суббота','sun'=>'Воскресенье'];
                $hoursData = json_decode($service['hours'] ?? '{}', true) ?: [];
                foreach ($days as $key => $dayName):
                    $dayHours = $hoursData[$key] ?? [];
                ?>
                <div class="hours-row" data-day="<?php echo $key; ?>">
                    <div class="hours-day"><?php echo $dayName; ?></div>
                    <div class="hours-main-row">
                        <div class="hours-time">
                            <input type="text" name="hours[<?php echo $key; ?>][open]" class="hours-open" placeholder="09:00" maxlength="5" value="<?php echo htmlspecialchars($dayHours['open'] ?? ''); ?>">
                            <span>—</span>
                            <input type="text" name="hours[<?php echo $key; ?>][close]" class="hours-close" placeholder="18:00" maxlength="5" value="<?php echo htmlspecialchars($dayHours['close'] ?? ''); ?>">
                        </div>
                        <div class="hours-flags">
                            <label class="hours-flag-btn <?php echo ($dayHours['open']??'')==='00:00'&&($dayHours['close']??'')==='23:59' ? 'active' : ''; ?>" title="Круглосуточно">
                                <input type="checkbox" class="hours-24h-checkbox" onchange="toggle24h(this)" <?php echo ($dayHours['open']??'')==='00:00'&&($dayHours['close']??'')==='23:59' ? 'checked' : ''; ?>>
                                <span>24ч</span>
                            </label>
                            <label class="hours-closed">
                                <input type="checkbox" class="hours-closed-checkbox" onchange="toggleHoursRow(this)" <?php echo empty($dayHours['open'])&&empty($dayHours['close']) ? 'checked' : ''; ?>>
                                Вых.
                            </label>
                        </div>
                    </div>
                    <?php if (!empty($dayHours['break_start'])): ?>
                    <div class="hours-break-row" style="display:flex;">
                    <?php else: ?>
                    <div class="hours-break-row" style="display:none;">
                    <?php endif; ?>
                        <span class="hours-break-label">Перерыв:</span>
                        <div class="hours-time">
                            <input type="text" name="hours[<?php echo $key; ?>][break_start]" class="hours-break-start" placeholder="чч:мм" maxlength="5" value="<?php echo htmlspecialchars($dayHours['break_start'] ?? ''); ?>">
                            <span>—</span>
                            <input type="text" name="hours[<?php echo $key; ?>][break_end]" class="hours-break-end" placeholder="чч:мм" maxlength="5" value="<?php echo htmlspecialchars($dayHours['break_end'] ?? ''); ?>">
                        </div>
                        <button type="button" class="hours-break-remove" onclick="removeBreak(this)" title="Убрать перерыв">✕</button>
                    </div>
                    <button type="button" class="btn-add-break" onclick="addBreak(this)" <?php echo !empty($dayHours['break_start']) ? 'style="display:none;"' : ''; ?>>+ перерыв</button>
                </div>
                <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                    <button type="button" class="btn-copy-hours" onclick="copyHoursToAll()">📋 Скопировать на все дни</button>
                    <button type="button" class="btn-copy-hours" onclick="setAll24h()" style="background:#EFF6FF;border-color:#BFDBFE;color:#1D4ED8;">🕐 Все круглосуточно</button>
                </div>
            </div>
        </div>
        <!-- Прогресс загрузки -->
        <div id="uploadProgress" style="display:none;margin-bottom:12px;">
            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:6px;">📤 Загрузка фото...</div>
            <div style="background:var(--border);border-radius:99px;height:6px;overflow:hidden;">
                <div id="uploadProgressBar" style="height:100%;background:var(--primary);width:0%;transition:width 0.3s;border-radius:99px;"></div>
            </div>
        </div>
        <!-- Кнопки -->
        <div style="display:flex;gap:10px;">
            <a href="/panel-5588/services.php" class="btn btn-secondary">← Назад</a>
            <button type="submit" id="submitBtn" class="btn btn-primary" style="flex:1;justify-content:center;">
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
                        <span style="font-weight:500;"><?php echo $service['subcategory'] ?: ($categories[$service['category']]['name'] ?? $service['category']); ?></span>
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

    </div>
</div>

<style>
.hours-row{display:flex;flex-direction:column;gap:4px;margin-bottom:8px;padding:10px 0;border-bottom:1px solid var(--border-light);}
.hours-row:last-child{border-bottom:none;margin-bottom:0;}
.hours-day{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;}
.hours-main-row{display:flex;align-items:center;gap:8px;}
.hours-time{flex:1;display:flex;gap:6px;align-items:center;}
.hours-time input{flex:1;padding:8px;border:1px solid var(--border);border-radius:8px;font-size:13px;min-width:0;background:var(--bg-white);color:var(--text);width:60px;text-align:center;}
.hours-flags{display:flex;gap:6px;align-items:center;flex-shrink:0;}
.hours-flag-btn{display:flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:var(--primary);cursor:pointer;padding:5px 8px;border:1px solid var(--primary);border-radius:6px;background:#EFF6FF;user-select:none;}
.hours-flag-btn input{display:none;}
.hours-flag-btn.active{background:var(--primary);color:white;}
.hours-break-row{display:flex;align-items:center;gap:6px;margin-top:4px;padding:6px 8px;background:#FFFBEB;border-radius:8px;border:1px solid #FDE68A;}
.hours-break-label{font-size:12px;color:#F59E0B;font-weight:600;white-space:nowrap;flex-shrink:0;}
.hours-break-remove{font-size:12px;background:none;border:none;color:var(--text-secondary);cursor:pointer;padding:2px 4px;}
.btn-add-break{font-size:12px;color:var(--text-secondary);background:none;border:none;cursor:pointer;padding:2px 0;text-decoration:underline;align-self:flex-start;}
.hours-row.is-closed .hours-main-row .hours-time input,.hours-row.is-closed .btn-add-break{opacity:0.4;pointer-events:none;}
.hours-row.is-24h .hours-main-row .hours-time input{opacity:0.4;pointer-events:none;}
.hours-closed{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);cursor:pointer;}
.btn-copy-hours{font-size:12px;padding:6px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg-secondary);cursor:pointer;color:var(--text-secondary);}
</style>
<script>
document.addEventListener('input', function(e) {
    if (!e.target.matches('.hours-open,.hours-close,.hours-break-start,.hours-break-end')) return;
    var v = e.target.value.replace(/[^0-9]/g, '');
    if (v.length >= 3) v = v.substring(0,2) + ':' + v.substring(2,4);
    e.target.value = v;
});
function toggleHoursRow(checkbox) {
    var row = checkbox.closest('.hours-row');
    var o = row.querySelector('.hours-open'), c = row.querySelector('.hours-close');
    var b = row.querySelector('.hours-24h-checkbox');
    if (checkbox.checked) {
        row.classList.add('is-closed'); o.disabled=true; c.disabled=true; o.value=''; c.value='';
        if (b) { b.checked=false; row.classList.remove('is-24h'); b.closest('.hours-flag-btn').classList.remove('active'); }
    } else { row.classList.remove('is-closed'); o.disabled=false; c.disabled=false; }
}
function toggle24h(checkbox) {
    var row = checkbox.closest('.hours-row');
    var o = row.querySelector('.hours-open'), c = row.querySelector('.hours-close');
    var cc = row.querySelector('.hours-closed-checkbox');
    if (checkbox.checked) {
        row.classList.add('is-24h'); checkbox.closest('.hours-flag-btn').classList.add('active');
        o.value='00:00'; c.value='23:59'; o.disabled=true; c.disabled=true;
        if (cc) { cc.checked=false; row.classList.remove('is-closed'); }
    } else {
        row.classList.remove('is-24h'); checkbox.closest('.hours-flag-btn').classList.remove('active');
        o.disabled=false; c.disabled=false; o.value=''; c.value='';
    }
}
function addBreak(btn) {
    btn.closest('.hours-row').querySelector('.hours-break-row').style.display='flex';
    btn.style.display='none';
}
function removeBreak(btn) {
    var row = btn.closest('.hours-row');
    var br = row.querySelector('.hours-break-row');
    br.style.display='none';
    br.querySelector('.hours-break-start').value='';
    br.querySelector('.hours-break-end').value='';
    row.querySelector('.btn-add-break').style.display='';
}
function setAll24h() {
    document.querySelectorAll('.hours-row').forEach(function(row) {
        var cb = row.querySelector('.hours-24h-checkbox');
        if (cb) { cb.checked=true; toggle24h(cb); }
    });
}
function copyHoursToAll() {
    var first = document.querySelector('.hours-row');
    var ot=first.querySelector('.hours-open').value, ct=first.querySelector('.hours-close').value;
    var isCl=first.querySelector('.hours-closed-checkbox').checked, is24=first.querySelector('.hours-24h-checkbox').checked;
    var br=first.querySelector('.hours-break-row'), hasBr=br.style.display!=='none';
    var bs=first.querySelector('.hours-break-start').value, be=first.querySelector('.hours-break-end').value;
    document.querySelectorAll('.hours-row').forEach(function(row) {
        var o=row.querySelector('.hours-open'), c=row.querySelector('.hours-close');
        var cc=row.querySelector('.hours-closed-checkbox'), cb=row.querySelector('.hours-24h-checkbox');
        var brr=row.querySelector('.hours-break-row'), abb=row.querySelector('.btn-add-break');
        if (is24) { cb.checked=true; toggle24h(cb); }
        else if (isCl) { cc.checked=true; toggleHoursRow(cc); }
        else {
            cc.checked=false; cb.checked=false; row.classList.remove('is-closed','is-24h');
            cb.closest('.hours-flag-btn').classList.remove('active');
            o.disabled=false; c.disabled=false; o.value=ot; c.value=ct;
            if (hasBr) { brr.style.display='flex'; brr.querySelector('.hours-break-start').value=bs; brr.querySelector('.hours-break-end').value=be; if(abb) abb.style.display='none'; }
        }
    });
}
document.querySelectorAll('.hours-row').forEach(function(row) {
    var cc=row.querySelector('.hours-closed-checkbox'), cb=row.querySelector('.hours-24h-checkbox');
    if (cc&&cc.checked) { row.classList.add('is-closed'); row.querySelector('.hours-open').disabled=true; row.querySelector('.hours-close').disabled=true; }
    if (cb&&cb.checked) { row.classList.add('is-24h'); cb.closest('.hours-flag-btn').classList.add('active'); row.querySelector('.hours-open').disabled=true; row.querySelector('.hours-close').disabled=true; }
});
</script>
<style>
.photo-edit-item{position:relative;aspect-ratio:1;border-radius:var(--radius-sm);overflow:hidden;border:2px solid var(--border);background:var(--bg);}
.photo-edit-item img{width:100%;height:100%;object-fit:cover;}
.photo-edit-item.marked-delete{opacity:0.3;border-color:var(--danger);}
.photo-edit-badge{position:absolute;top:4px;left:4px;background:var(--primary);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;}
.photo-edit-remove{position:absolute;top:3px;right:3px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.6);color:white;border:none;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;}
.photo-edit-remove:hover{background:var(--danger);}
.photo-new-item{position:relative;aspect-ratio:1;border-radius:var(--radius-sm);overflow:hidden;border:2px solid var(--success);background:var(--bg);}
.photo-new-item img{width:100%;height:100%;object-fit:cover;}
.photo-new-remove{position:absolute;top:3px;right:3px;width:22px;height:22px;border-radius:50%;background:rgba(0,0,0,.6);color:white;border:none;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;}
.photo-new-remove:hover{background:var(--danger);}
</style>
<script>
// Отмечаем фото для удаления
// Удаление фото через event delegation
// Навешиваем обработчики после загрузки DOM
// Прогресс бар при загрузке
document.getElementById('mainEditForm').addEventListener('submit', function(e) {
    // Снимаем disabled с полей часов чтобы они отправились в POST
    document.querySelectorAll('.hours-open, .hours-close, .hours-break-start, .hours-break-end').forEach(function(el) {
        el.disabled = false;
    });
    var fileInput = document.getElementById('newPhotoInput');
    if (!fileInput || !fileInput.files.length) return;
    e.preventDefault();
    var form = this;
    var btn = document.getElementById('submitBtn');
    var progress = document.getElementById('uploadProgress');
    var bar = document.getElementById('uploadProgressBar');
    btn.disabled = true;
    btn.textContent = 'Сохранение...';
    progress.style.display = 'block';
    var xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var pct = Math.round(e.loaded / e.total * 100);
            bar.style.width = pct + '%';
        }
    });
    xhr.addEventListener('load', function() {
        window.location.reload();
    });
    xhr.addEventListener('error', function() {
        btn.disabled = false;
        btn.textContent = 'Сохранить изменения';
        progress.style.display = 'none';
        alert('Ошибка загрузки');
    });
    xhr.open('POST', form.action || window.location.href);
    xhr.send(new FormData(form));
});

(function initPhotoDelete() {
    var btns = document.querySelectorAll('.photo-edit-remove');
    if (!btns.length) { setTimeout(initPhotoDelete, 100); return; }
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var photoPath = btn.getAttribute('data-photo');
            var item = btn.closest('.photo-edit-item');
            var keepInput = item.querySelector('input[name="keep_photos[]"]');
            if (item.classList.contains('marked-delete')) {
                item.classList.remove('marked-delete');
                btn.textContent = '✕';
                btn.title = 'Удалить фото';
                if (keepInput) keepInput.disabled = false;
                var form = document.getElementById('mainEditForm');
                var inputs = form.querySelectorAll('input[name="delete_photos[]"]');
                inputs.forEach(function(inp) { if (inp.value === photoPath) inp.remove(); });
            } else {
                item.classList.add('marked-delete');
                btn.textContent = '↩';
                btn.title = 'Отменить удаление';
                if (keepInput) keepInput.disabled = true;
                var delInput = document.createElement('input');
                delInput.type = 'hidden';
                delInput.name = 'delete_photos[]';
                delInput.value = photoPath;
                document.getElementById('mainEditForm').appendChild(delInput);
            }
        });
    });
})();

// Превью новых фото
function handleNewPhotos(e) {
    const preview = document.getElementById('newPhotoPreview');
    const files = Array.from(e.target.files);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = ev => {
            const item = document.createElement('div');
            item.className = 'photo-new-item';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'photo-new-remove';
            btn.textContent = '✕';
            btn.onclick = function() { this.closest('.photo-new-item').remove(); };
            var img = document.createElement('img');
            img.src = ev.target.result;
            item.appendChild(img);
            item.appendChild(btn);
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}
</script>
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
<script>
const editCategoriesData = <?php echo json_encode(array_map(fn($c) => $c['subs'], $categories)); ?>;
const editCategoriesKeys = <?php echo json_encode(array_keys($categories)); ?>;
function updateEditSubcategories() {
    const cat = document.getElementById('editCategorySelect').value;
    const sub = document.getElementById('editSubcategorySelect');
    sub.innerHTML = '<option value="">Выберите подкатегорию</option>';
    if (cat) {
        const idx = editCategoriesKeys.indexOf(cat);
        if (idx >= 0) {
            editCategoriesData[editCategoriesKeys[idx]].forEach(s => {
                const o = document.createElement('option');
                o.value = s; o.textContent = s;
                sub.appendChild(o);
            });
            sub.disabled = false;
        }
    } else {
        sub.disabled = true;
    }
}
</script>
<script src="/assets/js/address-autocomplete.js"></script>
<?php
$content = ob_get_clean();
renderLayout('Редактировать сервис #' . $serviceId, $content, $pendingCount, 0, $pendingReviewCount);
?>
