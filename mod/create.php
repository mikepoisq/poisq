<?php
session_start();
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/layout.php';
requireModeratorAuth('services_create');

$pdo = getDbConnection();

define('ADMIN_USER_ID', 11);
define('ADMIN_USER_EMAIL', 'new@poisq.com');

$success = '';
$error   = '';
$createdService = null;

function generatePassword(int $len = 8): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $pass = '';
    for ($i = 0; $i < $len; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $category    = trim($_POST['category']    ?? '');
    $subcategory = trim($_POST['subcategory'] ?? '');
    $country     = trim($_POST['country']     ?? '');
    $cityId      = (int)($_POST['city_id']    ?? 0);
    $description = trim($_POST['description'] ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $whatsapp    = trim($_POST['whatsapp']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $website     = trim($_POST['website']     ?? '');
    $address     = trim($_POST['address']     ?? '');
    $languages   = $_POST['languages']        ?? ['ru'];
    $services    = $_POST['services']         ?? [];
    $callStatus  = trim($_POST['call_status'] ?? 'not_called');
    $callNote    = trim($_POST['call_note']   ?? '');
    $allowedCallStatuses = ['not_called','reached','no_answer','no_number','other'];
    if (!in_array($callStatus, $allowedCallStatuses)) $callStatus = 'not_called';

    if (empty($name) || empty($category) || empty($country) || empty($cityId)) {
        $error = 'Заполните обязательные поля: название, категория, страна, город';
    } else {
        $password = generatePassword(8);
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$passHash, ADMIN_USER_ID]);

        // Обработка часов работы
        $hoursRaw = $_POST['hours'] ?? [];
        $hoursData = [];
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) {
            $hoursData[$d] = ['open'=>$hoursRaw[$d]['open']??'','close'=>$hoursRaw[$d]['close']??'','break_start'=>$hoursRaw[$d]['break_start']??'','break_end'=>$hoursRaw[$d]['break_end']??''];
        }
        $hoursJson = json_encode($hoursData, JSON_UNESCAPED_UNICODE);
        $languagesJson = json_encode($languages, JSON_UNESCAPED_UNICODE);
        $servicesJson  = json_encode(array_values(array_filter($services, fn($s) => !empty($s['name']))), JSON_UNESCAPED_UNICODE);

        // Загрузка фото
        $photoPaths = [];
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $count = min(count($_FILES['photos']['name']), 5);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp  = $_FILES['photos']['tmp_name'][$i];
                $info = @getimagesize($tmp);
                if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp'])) continue;
                if ($_FILES['photos']['size'][$i] > 10 * 1024 * 1024) continue;
                $fileName   = uniqid('photo_') . '.jpg';
                $targetPath = $uploadDir . $fileName;
                $src    = imagecreatefromstring(file_get_contents($tmp));
                $width  = imagesx($src); $height = imagesy($src);
                if ($width > 800) {
                    $ratio  = 800 / $width;
                    $dst    = imagecreatetruecolor(800, (int)($height * $ratio));
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, 800, (int)($height * $ratio), $width, $height);
                    imagejpeg($dst, $targetPath, 85);
                    imagedestroy($dst);
                } else {
                    imagejpeg($src, $targetPath, 85);
                }
                imagedestroy($src);
                $photoPaths[] = '/uploads/' . $fileName;
            }
        }
        $photoJson = $photoPaths ? json_encode($photoPaths, JSON_UNESCAPED_UNICODE) : null;

        // Всегда создаётся модератором
        $createdByMod = getModeratorId();

        $stmt = $pdo->prepare("
            INSERT INTO services
                (user_id, name, category, subcategory, country_code, city_id,
                 description, photo, phone, whatsapp, email, website, address,
                 hours, languages, services, status, is_visible, admin_password,
                 call_status, call_note,
                 created_by_admin, created_by_moderator, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',1,?,?,?,?,?,NOW(),NOW())
        ");
        $stmt->execute([
            ADMIN_USER_ID, $name, $category, $subcategory, $country,
            $cityId ?: null, $description, $photoJson, $phone, $whatsapp, $email,
            $website, $address, $hoursJson, $languagesJson, $servicesJson, $password,
            $callStatus, $callNote ?: null,
            null, $createdByMod
        ]);
        $newId = (int)$pdo->lastInsertId();

        recordModeratorStat($createdByMod, $newId, 'created');

        if (file_exists(__DIR__ . '/../config/meilisearch.php')) {
            require_once __DIR__ . '/../config/meilisearch.php';
            $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id=c.id WHERE s.id=?");
            $row->execute([$newId]);
            $svcRow = $row->fetch(PDO::FETCH_ASSOC);
            if ($svcRow) meiliAddDocument(meiliPrepareDoc($svcRow));
        }

        $createdService = [
            'id'       => $newId,
            'name'     => $name,
            'password' => $password,
            'url'      => serviceUrl($newId, $name),
        ];
        $success = 'Сервис успешно создан!';
    }
}

$categories = [
    'health'     => ['name'=>'🏥 Здоровье и красота',    'subs'=>['Врачи','Стоматология','Психология','Альтернативная медицина','Салоны красоты','Фитнес и спорт','Аптеки']],
    'legal'      => ['name'=>'⚖️ Юридические услуги',    'subs'=>['Иммиграция','Семейное право','Недвижимость','Бизнес право','Нотариус','Консультации']],
    'family'     => ['name'=>'👨‍👩‍👧 Семья и дети',       'subs'=>['Няни','Репетиторы','Детские кружки','Бэбиситтеры','Детские праздники','Детские товары']],
    'shops'      => ['name'=>'🛒 Магазины и продукты',   'subs'=>['Русские магазины','Доставка продуктов','Рестораны','Пекарни','Мясные лавки','Онлайн магазины']],
    'home'       => ['name'=>'🏠 Дом и быт',             'subs'=>['Уборка','Ремонт','Переезды','Химчистка','Животные','Сад и огород']],
    'education'  => ['name'=>'📚 Образование',           'subs'=>['Языковые курсы','Русский язык','Школьные предметы','Музыка','Профессиональные курсы','Онлайн обучение']],
    'business'   => ['name'=>'💼 Бизнес и финансы',      'subs'=>['Бухгалтерия','Налоги','Страхование','Бизнес консультации','Переводы денег']],
    'transport'  => ['name'=>'🚗 Транспорт и авто',      'subs'=>['Авто сервис','Автошкола','Такси/Трансфер','Аренда авто','Покупка авто']],
    'events'     => ['name'=>'📷 События и развлечения', 'subs'=>['Фотографы','Видеографы','Праздники','Туризм','Развлечения','Культура']],
    'it'         => ['name'=>'💻 IT и онлайн услуги',    'subs'=>['Веб разработка','Дизайн','Ремонт техники','SMM/Маркетинг','Консультации']],
    'realestate' => ['name'=>'🏢 Недвижимость',              'subs'=>['Аренда','Покупка','Продажа','Управление','Ипотека']],
    'messengers' => ['name'=>'💬 Группы ВатсАп и Телеграм', 'subs'=>['WhatsApp группы','Telegram каналы','Telegram группы','Чаты и сообщества']],
];

$countryNames = [
    'ad'=>'🇦🇩 Андорра','ar'=>'🇦🇷 Аргентина','at'=>'🇦🇹 Австрия','au'=>'🇦🇺 Австралия',
    'ae'=>'🇦🇪 ОАЭ','be'=>'🇧🇪 Бельгия','br'=>'🇧🇷 Бразилия','by'=>'🇧🇾 Беларусь',
    'ca'=>'🇨🇦 Канада','ch'=>'🇨🇭 Швейцария','cl'=>'🇨🇱 Чили','co'=>'🇨🇴 Колумбия',
    'cz'=>'🇨🇿 Чехия','de'=>'🇩🇪 Германия','dk'=>'🇩🇰 Дания','es'=>'🇪🇸 Испания',
    'fi'=>'🇫🇮 Финляндия','fr'=>'🇫🇷 Франция','gb'=>'🇬🇧 Великобритания','gr'=>'🇬🇷 Греция',
    'hk'=>'🇭🇰 Гонконг','ie'=>'🇮🇪 Ирландия','il'=>'🇮🇱 Израиль','it'=>'🇮🇹 Италия',
    'jp'=>'🇯🇵 Япония','kz'=>'🇰🇿 Казахстан','kr'=>'🇰🇷 Корея','mx'=>'🇲🇽 Мексика',
    'nl'=>'🇳🇱 Нидерланды','no'=>'🇳🇴 Норвегия','nz'=>'🇳🇿 Новая Зеландия','pl'=>'🇵🇱 Польша',
    'pt'=>'🇵🇹 Португалия','ru'=>'🇷🇺 Россия','se'=>'🇸🇪 Швеция','sg'=>'🇸🇬 Сингапур',
    'th'=>'🇹🇭 Таиланд','tr'=>'🇹🇷 Турция','ua'=>'🇺🇦 Украина','us'=>'🇺🇸 США',
    'za'=>'🇿🇦 ЮАР',
];

ob_start();
?>

<!-- Хлебные крошки -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:13px;color:var(--text-secondary);">
    <a href="/mod/services.php" style="color:var(--primary);text-decoration:none;">Сервисы</a>
    <span>›</span>
    <span>Создать сервис</span>
</div>

<?php if ($createdService): ?>
<!-- Успешно создан -->
<div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:var(--radius);padding:24px;margin-bottom:20px;">
    <div style="font-size:18px;font-weight:800;color:#065F46;margin-bottom:16px;">✅ Сервис успешно создан!</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div style="background:var(--bg-white);border-radius:var(--radius-sm);padding:14px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:6px;">Логин для входа</div>
            <div style="font-size:16px;font-weight:700;font-family:monospace;color:#1F2937;"><?php echo ADMIN_USER_EMAIL; ?></div>
        </div>
        <div style="background:var(--bg-white);border-radius:var(--radius-sm);padding:14px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:6px;">Пароль (сохраните!)</div>
            <div style="font-size:20px;font-weight:800;font-family:monospace;color:var(--primary);letter-spacing:2px;"><?php echo htmlspecialchars($createdService['password']); ?></div>
        </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="https://poisq.com<?php echo $createdService['url']; ?>" target="_blank" class="btn btn-secondary">
            👁 Открыть сервис
        </a>
        <a href="/mod/create.php" class="btn btn-primary">
            + Создать ещё
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<style>
/* ===== АККОРДЕОН ===== */
.accordion-panel { border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 12px; overflow: hidden; }
.accordion-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 13px 16px; cursor: pointer; user-select: none;
    background: var(--bg-secondary); border-bottom: 1px solid transparent;
    transition: background .15s;
}
.accordion-header:hover { background: var(--bg); }
.accordion-header.open { border-bottom-color: var(--border); }
.accordion-title { font-size: 14px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; }
.accordion-arrow { font-size: 12px; color: var(--text-secondary); transition: transform .2s; }
.accordion-header.open .accordion-arrow { transform: rotate(180deg); }
.accordion-body { display: none; padding: 16px; }
.accordion-body.open { display: block; }

/* ===== ЧАСЫ ===== */
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

/* ===== ФОТО ===== */
.photo-btn-compact {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 14px; border: 1.5px dashed var(--border);
    border-radius: var(--radius-sm); cursor: pointer;
    font-size: 13px; color: var(--text-secondary);
    background: var(--bg-secondary); transition: all .15s;
}
.photo-btn-compact:hover { border-color: var(--primary); color: var(--primary); background: #F5F0FF; }
.photo-preview-strip { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.photo-item-thumb { position: relative; width: 52px; height: 52px; border-radius: 6px; overflow: hidden; border: 2px solid var(--border); background: var(--bg); flex-shrink: 0; }
.photo-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-item-thumb.is-main { border-color: var(--primary); }
.photo-item-badge { position:absolute; top:1px; left:2px; color:var(--primary); font-size:9px; font-weight:700; pointer-events:none; }
.photo-item-remove { position:absolute; top:1px; right:1px; width:16px; height:16px; border-radius:50%; background:rgba(0,0,0,.55); color:white; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:9px; line-height:1; }
.photo-item-remove:hover { background: var(--danger); }
.photo-item-setmain { position:absolute; bottom:1px; left:50%; transform:translateX(-50%); white-space:nowrap; background:rgba(0,0,0,.55); color:white; font-size:8px; font-weight:600; padding:1px 4px; border-radius:3px; border:none; cursor:pointer; }
.photo-item-setmain:hover { background:var(--primary); }

/* ===== ЯЗЫКИ компактные ===== */
.lang-check-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; padding: 4px 9px;
    border: 1px solid var(--border); border-radius: 20px;
    cursor: pointer; color: var(--text-secondary);
    transition: all .12s; white-space: nowrap;
}
.lang-check-label input { display: none; }
.lang-check-label:has(input:checked) { border-color: var(--primary); background: #EFF6FF; color: var(--primary); font-weight: 600; }

/* Пудровые фоны блоков */
.panel-lavender { background: #F5F0FF !important; }
.panel-blue     { background: #F0F7FF !important; }
.panel-pink     { background: #FFF0F5 !important; }
.panel-white    { background: var(--bg-white) !important; }

/* Тёмная тема */
body.dark-theme .panel-lavender { background: #2D2040 !important; }
body.dark-theme .panel-blue     { background: #1A2535 !important; }
body.dark-theme .panel-pink     { background: #2D1A20 !important; }
</style>

<form method="POST" id="createForm" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">

    <!-- ===== ЛЕВАЯ КОЛОНКА ===== -->
    <div>

        <!-- Категория -->
        <div class="panel panel-lavender" style="margin-bottom:12px;">
            <div class="panel-header" style="background:transparent;border-bottom-color:#E8E0FF;">
                <div class="panel-title">📋 Категория</div>
            </div>
            <div style="padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Категория *</label>
                    <select name="category" id="categorySelect" class="form-control form-select" onchange="updateSubcategories()" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?php echo $key; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Подкатегория</label>
                    <select name="subcategory" id="subcategorySelect" class="form-control form-select" disabled>
                        <option value="">Сначала выберите категорию</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Основная информация -->
        <div class="panel panel-blue" style="margin-bottom:12px;">
            <div class="panel-header" style="background:transparent;border-bottom-color:#DBEAFE;">
                <div class="panel-title">ℹ️ Основная информация</div>
            </div>
            <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Название сервиса *</label>
                    <input type="text" name="name" class="form-control" placeholder="Например: Доктор Петрова Анна" required maxlength="255">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Описание</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Описание сервиса..."></textarea>
                </div>
            </div>
        </div>

        <!-- Контакты -->
        <div class="panel" style="margin-bottom:12px;">
            <div class="panel-header"><div class="panel-title">📞 Контакты</div></div>
            <div style="padding:14px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Телефон</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+33 6 12 34 56 78">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">WhatsApp</label>
                    <input type="tel" name="whatsapp" class="form-control" placeholder="+33 6 12 34 56 78">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="contact@example.com">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Сайт</label>
                    <input type="url" name="website" class="form-control" placeholder="https://example.com">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Адрес</label>
                    <small style="color:#10B981;font-size:11px;display:block;margin-bottom:4px;">Для подсказок сначала выберите страну и город</small>
                    <input type="text" name="address" class="form-control" placeholder="Улица, дом, город">
                </div>
            </div>
        </div>

        <!-- Созвон при создании -->
        <div class="panel panel-pink" style="margin-bottom:12px;" id="callBlockCreate">
            <div class="panel-header" style="background:transparent;border-bottom-color:#FECDD3;">
                <div class="panel-title">📞 Созвон с сервисом <span style="color:#EF4444;">*</span></div>
            </div>
            <div style="padding:14px;display:flex;flex-direction:column;gap:10px;">
                <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:8px 12px;font-size:12px;color:#92400E;">
                    ⚠️ <b>Обязательное поле.</b> Если оставить «Не звонили» — сервис не учитывается в статистике созвонов.
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Статус созвона <span style="color:#EF4444;">*</span></label>
                    <select name="call_status" id="createCallStatus" class="form-control form-select" onchange="onCreateCallChange()">
                        <option value="not_called">— Не звонили —</option>
                        <option value="no_answer">Не дозвонились</option>
                        <option value="reached">✅ Дозвонились</option>
                        <option value="no_number">Нет номера</option>
                        <option value="other">Другое...</option>
                    </select>
                </div>
                <div id="createCallNoteBlock" style="display:none;">
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Заметка о созвоне</label>
                    <textarea name="call_note" class="form-control" rows="2" placeholder="Комментарий..."></textarea>
                </div>
            </div>
        </div>

    </div><!-- /левая колонка -->

    <!-- ===== ПРАВАЯ КОЛОНКА ===== -->
    <div>

        <!-- Страна и город -->
        <div class="panel panel-lavender" style="margin-bottom:12px;">
            <div class="panel-header" style="background:transparent;border-bottom-color:#E8E0FF;">
                <div class="panel-title">📍 Страна и город</div>
            </div>
            <div style="padding:14px;display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Страна *</label>
                    <select name="country" id="countrySelect" class="form-control form-select" onchange="loadCities(this.value)" required>
                        <option value="">Выберите страну</option>
                        <?php foreach ($countryNames as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:5px;">Город <span style="color:#EF4444;">*</span></label>
                    <select name="city_id" id="citySelect" class="form-control form-select" disabled>
                        <option value="">Сначала выберите страну</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Инфо об аккаунте -->
        <div style="background:var(--primary-light);border:1px solid #BFDBFE;border-radius:var(--radius);padding:12px 14px;margin-bottom:12px;font-size:12px;">
            <div style="font-weight:700;color:var(--primary);margin-bottom:6px;">👑 Сервис администратора</div>
            <div style="color:var(--text-secondary);line-height:1.55;">
                Привязан к: <strong style="color:var(--text);"><?php echo ADMIN_USER_EMAIL; ?></strong><br>
                Уникальный пароль генерируется при создании. Сохраните его — показывается один раз.
            </div>
        </div>

        <!-- Фото (компактно) -->
        <div class="panel" style="margin-bottom:12px;">
            <div class="panel-header"><div class="panel-title">📷 Фото</div></div>
            <div style="padding:12px 14px;">
                <input type="file" id="photoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handlePhotoUpload(event)">
                <label class="photo-btn-compact" onclick="document.getElementById('photoInput').click()">
                    📷 Добавить фото <span style="font-size:11px;opacity:.7;">(до 5 шт.)</span>
                </label>
                <div id="photoPreview" class="photo-preview-strip"></div>
            </div>
        </div>

        <!-- Языки (компактно) -->
        <div class="panel" style="margin-bottom:12px;">
            <div class="panel-header"><div class="panel-title">🗣 Языки</div></div>
            <div style="padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach (['ru'=>'🇷🇺 Рус','fr'=>'🇫🇷 FR','en'=>'🇬🇧 EN','de'=>'🇩🇪 DE','es'=>'🇪🇸 ES','it'=>'🇮🇹 IT'] as $code => $label): ?>
                <label class="lang-check-label">
                    <input type="checkbox" name="languages[]" value="<?php echo $code; ?>" <?php echo $code==='ru'?'checked':''; ?>>
                    <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Кнопка создать -->
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="white" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Создать и опубликовать
        </button>

    </div><!-- /правая колонка -->

</div><!-- /grid -->

<!-- ===== АККОРДЕОНЫ: на всю ширину ===== -->

<!-- Часы работы -->
<div class="accordion-panel" style="margin-top:4px;">
    <div class="accordion-header" onclick="toggleAccordion(this)">
        <span class="accordion-title">🕐 Часы работы</span>
        <span class="accordion-arrow">▼</span>
    </div>
    <div class="accordion-body">
        <div id="hoursContainer">
        <?php
        $days = ['mon'=>'Понедельник','tue'=>'Вторник','wed'=>'Среда','thu'=>'Четверг','fri'=>'Пятница','sat'=>'Суббота','sun'=>'Воскресенье'];
        $hoursData = $hoursData ?? [];
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

<!-- Услуги и цены -->
<div class="accordion-panel">
    <div class="accordion-header" onclick="toggleAccordion(this)">
        <span class="accordion-title">💰 Услуги и цены</span>
        <span class="accordion-arrow">▼</span>
    </div>
    <div class="accordion-body">
        <div id="servicesList">
            <div class="service-row" style="display:flex;gap:8px;margin-bottom:8px;">
                <input type="text" name="services[0][name]" class="form-control" placeholder="Название услуги" style="flex:1;">
                <input type="number" name="services[0][price]" class="form-control" placeholder="Цена €" style="width:100px;" min="0">
                <button type="button" onclick="removeService(this)" class="btn btn-danger btn-sm">✕</button>
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addService()" style="margin-top:4px;">+ Добавить услугу</button>
    </div>
</div>

</form>

<script>
/* ===== Аккордеон ===== */
function toggleAccordion(header) {
    header.classList.toggle('open');
    var body = header.nextElementSibling;
    body.classList.toggle('open');
}

/* ===== Часы ===== */
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

/* ===== Категории / города ===== */
const categoriesData = <?php echo json_encode(array_map(fn($c) => $c['subs'], $categories)); ?>;
const categoriesKeys = <?php echo json_encode(array_keys($categories)); ?>;

function updateSubcategories() {
    const cat = document.getElementById('categorySelect').value;
    const sub = document.getElementById('subcategorySelect');
    sub.innerHTML = '<option value="">Выберите подкатегорию</option>';
    if (cat) {
        const idx = categoriesKeys.indexOf(cat);
        if (idx >= 0) {
            categoriesData[categoriesKeys[idx]].forEach(s => {
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

async function loadCities(country) {
    const sel = document.getElementById('citySelect');
    if (!country) {
        sel.disabled = true;
        sel.innerHTML = '<option value="">Сначала выберите страну</option>';
        sel.style.borderColor = '';
        return;
    }
    sel.disabled = true;
    sel.innerHTML = '<option value="">Загрузка...</option>';
    try {
        const res = await fetch(`/api/get-cities.php?country=${country}`);
        const cities = await res.json();
        sel.innerHTML = '<option value="">Выберите город *</option>';
        cities.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name_lat ? `${c.name_lat} (${c.name})` : c.name;
            sel.appendChild(o);
        });
        sel.disabled = false;
        sel.style.borderColor = '#EF4444';
        sel.style.boxShadow = '0 0 0 2px rgba(239,68,68,0.15)';
    } catch(e) {
        sel.innerHTML = '<option value="">Ошибка загрузки</option>';
    }
}
document.addEventListener('change', function(e) {
    if (e.target.id === 'citySelect' && e.target.value) {
        e.target.style.borderColor = '#10B981';
        e.target.style.boxShadow = '0 0 0 2px rgba(16,185,129,0.15)';
    }
});

/* ===== Фото ===== */
let photoCount = 0;
const maxPhotos = 5;
let storedFiles = [];

function updatePhotoOrder() {
    const items = document.querySelectorAll('#photoPreview .photo-item-thumb');
    items.forEach((it, idx) => {
        it.classList.toggle('is-main', idx === 0);
        const badge = it.querySelector('.photo-item-badge');
        const setMain = it.querySelector('.photo-item-setmain');
        if (idx === 0) {
            if (!badge) { const b = document.createElement('span'); b.className = 'photo-item-badge'; b.textContent = '★'; it.appendChild(b); }
            if (setMain) setMain.remove();
        } else {
            if (badge) badge.remove();
            if (!setMain) { const s = document.createElement('button'); s.type = 'button'; s.className = 'photo-item-setmain'; s.textContent = '★ Главное'; s.onclick = function(){ setMainPhoto(this); }; it.appendChild(s); }
        }
    });
}

function setMainPhoto(btn) {
    const preview = document.getElementById('photoPreview');
    const item = btn.closest('.photo-item-thumb');
    preview.insertBefore(item, preview.firstChild);
    const items = document.querySelectorAll('#photoPreview .photo-item-thumb');
    const newFiles = [];
    items.forEach(it => {
        const idx = parseInt(it.dataset.fileIndex);
        if (!isNaN(idx) && storedFiles[idx] !== null) newFiles.push(storedFiles[idx]);
        else newFiles.push(null);
    });
    storedFiles = newFiles;
    items.forEach((it, i) => { it.dataset.fileIndex = i; });
    updatePhotoOrder();
}

function handlePhotoUpload(e) {
    const preview = document.getElementById('photoPreview');
    for (const file of e.target.files) {
        if (photoCount >= maxPhotos) { alert('Максимум 5 фотографий'); break; }
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) { alert('Только JPG, PNG или WebP'); continue; }
        if (file.size > 10 * 1024 * 1024) { alert('Файл ' + file.name + ' превышает 10MB'); continue; }
        storedFiles.push(file);
        const fileIndex = storedFiles.length - 1;
        const reader = new FileReader();
        reader.onload = function(ev) {
            const item = document.createElement('div');
            item.className = 'photo-item-thumb';
            item.dataset.fileIndex = fileIndex;
            item.innerHTML = `<img src="${ev.target.result}" alt=""><button type="button" class="photo-item-remove" onclick="removePhotoThumb(this)">✕</button>`;
            preview.appendChild(item);
            photoCount++;
            updatePhotoOrder();
        };
        reader.readAsDataURL(file);
    }
    e.target.value = '';
}

function removePhotoThumb(btn) {
    const item = btn.closest('.photo-item-thumb');
    const fileIndex = parseInt(item.dataset.fileIndex);
    if (!isNaN(fileIndex)) storedFiles[fileIndex] = null;
    item.remove();
    photoCount--;
    updatePhotoOrder();
}

document.getElementById('createForm').addEventListener('submit', function() {
    // Снимаем disabled с полей часов чтобы они отправились в POST
    document.querySelectorAll('.hours-open, .hours-close, .hours-break-start, .hours-break-end').forEach(function(el) {
        el.disabled = false;
    });
    const dt = new DataTransfer();
    storedFiles.forEach(function(file) {
        if (file !== null) dt.items.add(file);
    });
    document.getElementById('photoInput').files = dt.files;
});
/* ===== Созвон ===== */
function onCreateCallChange() {
    const val = document.getElementById('createCallStatus').value;
    document.getElementById('createCallNoteBlock').style.display = val === 'other' ? '' : 'none';
    const block = document.getElementById('callBlockCreate');
    block.style.background = val === 'reached'   ? '#ECFDF5' :
                              val === 'no_answer' ? '#FFFBEB' : '#FFF0F5';
    block.style.borderColor = val === 'reached'   ? '#6EE7B7' :
                               val === 'no_answer' ? '#FDE68A' : '';
}

/* ===== Услуги ===== */
let svcCount = 1;
function addService() {
    if (svcCount >= 20) return;
    const list = document.getElementById('servicesList');
    const row = document.createElement('div');
    row.className = 'service-row';
    row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;';
    row.innerHTML = `
        <input type="text" name="services[${svcCount}][name]" class="form-control" placeholder="Название услуги" style="flex:1;">
        <input type="number" name="services[${svcCount}][price]" class="form-control" placeholder="Цена €" style="width:100px;" min="0">
        <button type="button" onclick="removeService(this)" class="btn btn-danger btn-sm">✕</button>`;
    list.appendChild(row);
    svcCount++;
}
function removeService(btn) {
    const rows = document.querySelectorAll('.service-row');
    if (rows.length > 1) btn.closest('.service-row').remove();
}
</script>

<style>
<style>
.dup-inline {
    display:none; margin-top:6px;
    border:1.5px solid #FDE68A; border-radius:10px;
    background:#FFFBEB; overflow:hidden;
}
body.dark-theme .dup-inline { background:#2A2210; border-color:#92400E; }
.dup-inline-header {
    padding:7px 12px; font-size:11px; font-weight:700;
    color:#92400E; display:flex; align-items:center; gap:6px;
}
body.dark-theme .dup-inline-header { color:#FBBF24; }
.dup-inline-item {
    display:flex; gap:10px; padding:8px 12px;
    border-top:1px solid #FDE68A;
    text-decoration:none; color:inherit;
    transition:background .1s; align-items:center;
}
body.dark-theme .dup-inline-item { border-color:#3D2E0A; }
.dup-inline-item:hover { background:#FEF3C7; }
body.dark-theme .dup-inline-item:hover { background:#1A1500; }
.dup-inline-thumb {
    width:40px; height:40px; border-radius:7px;
    object-fit:cover; flex-shrink:0; background:#F3F4F6;
}
.dup-inline-thumb-empty {
    width:40px; height:40px; border-radius:7px;
    background:#FEF3C7; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:18px;
}
.dup-inline-info { flex:1; min-width:0; }
.dup-inline-name {
    font-size:13px; font-weight:700; color:#1F2937;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
body.dark-theme .dup-inline-name { color:#F9FAFB; }
.dup-inline-meta { font-size:11px; color:#6B7280; margin-top:1px; line-height:1.5; }
.dup-inline-status {
    display:inline-block; font-size:10px; font-weight:700;
    padding:1px 6px; border-radius:99px; margin-top:2px;
}
.dh-status-approved  { background:#D1FAE5; color:#065F46; }
.dh-status-pending   { background:#FEF3C7; color:#92400E; }
.dh-status-duplicate { background:#FEE2E2; color:#991B1B; }
</style>

<script>
(function() {
    var timers = {};
    var lastVals = {};
    var cache = {};
    var API = '/mod/api_duplicate_check.php';
    var EDIT_BASE = '/mod/edit.php';
    var statusLabel = { approved: 'Активный', pending: 'На модерации', duplicate: 'Дубль' };
    var statusClass  = { approved: 'dh-status-approved', pending: 'dh-status-pending', duplicate: 'dh-status-duplicate' };
    var fieldMap = {
        name:    { param: 'name',    minLen: 4 },
        phone:   { param: 'phone',   minLen: 7 },
        email:   { param: 'email',   minLen: 6 },
        address: { param: 'address', minLen: 6 }
    };

    function getOrCreateHint(fieldName, inputEl) {
        var hintId = 'dup-hint-' + fieldName;
        var hint = document.getElementById(hintId);
        if (!hint) {
            hint = document.createElement('div');
            hint.id = hintId;
            hint.className = 'dup-inline';
            hint.innerHTML = '<div class="dup-inline-header">\u26a0\ufe0f \u041f\u043e\u0445\u043e\u0436\u0438\u0435 \u0441\u0435\u0440\u0432\u0438\u0441\u044b \u0443\u0436\u0435 \u0435\u0441\u0442\u044c<button onclick="this.closest(\'.dup-inline\').style.display=\'none\'" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:14px;color:#92400E;line-height:1;padding:0 2px;">\u2715</button></div><div class="dup-inline-list"></div>';
            inputEl.parentNode.insertBefore(hint, inputEl.nextSibling);
        }
        return hint;
    }

    function renderItems(items, fieldName) {
        var inputEl = document.querySelector('[name="' + fieldName + '"]');
        if (!inputEl) return;
        var hint = getOrCreateHint(fieldName, inputEl);
        var list = hint.querySelector('.dup-inline-list');
        if (!items || items.length === 0) { hint.style.display = 'none'; return; }
        list.innerHTML = items.map(function(it) {
            var thumb = it.photo
                ? '<img class="dup-inline-thumb" src="' + it.photo + '" alt="" onerror="this.style.display=\'none\'">'
                : '<div class="dup-inline-thumb-empty">\ud83c\udfe2</div>';
            var meta = [];
            if (it.phone)   meta.push('\ud83d\udcde ' + it.phone);
            if (it.email)   meta.push('\u2709\ufe0f ' + it.email);
            if (it.address) meta.push('\ud83d\udccd ' + it.address.substring(0,40) + (it.address.length>40?'\u2026':''));
            if (it.city)    meta.push('\ud83c\udfd9 ' + it.city + (it.country?' ('+it.country.toUpperCase()+')':''));
            var sl = statusLabel[it.status] || it.status;
            var sc = statusClass[it.status] || '';
            return '<a class="dup-inline-item" href="' + EDIT_BASE + '?id=' + it.id + '" target="_blank">'
                + thumb + '<div class="dup-inline-info">'
                + '<div class="dup-inline-name">' + it.name + '</div>'
                + (meta.length ? '<div class="dup-inline-meta">' + meta.join(' &middot; ') + '</div>' : '')
                + '<span class="dup-inline-status ' + sc + '">' + sl + '</span>'
                + '</div></a>';
        }).join('');
        hint.style.display = 'block';
    }

    function doCheck(fieldName, val, param) {
        if (val === lastVals[fieldName]) return;
        lastVals[fieldName] = val;
        var cacheKey = fieldName + ':' + val;
        if (cache[cacheKey] !== undefined) { renderItems(cache[cacheKey], fieldName); return; }
        fetch(API + '?' + param + '=' + encodeURIComponent(val))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = Array.isArray(data) ? data : [];
                cache[cacheKey] = items;
                renderItems(items, fieldName);
            }).catch(function() {});
    }

    function bindField(fieldName, cfg) {
        var el = document.querySelector('[name="' + fieldName + '"]');
        if (!el) return;
        function handler() {
            var val = el.value.trim();
            if (val.length < cfg.minLen || (fieldName === 'email' && val.indexOf('@') < 0)) {
                var hint = document.getElementById('dup-hint-' + fieldName);
                if (hint) hint.style.display = 'none';
                return;
            }
            clearTimeout(timers[fieldName]);
            timers[fieldName] = setTimeout(function() { doCheck(fieldName, val, cfg.param); }, 600);
        }
        el.addEventListener('input', handler);
        el.addEventListener('change', handler);
    }

    function init() {
        Object.keys(fieldMap).forEach(function(fn) { bindField(fn, fieldMap[fn]); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
})();
</script>

</script>

<script src="/assets/js/address-autocomplete.js"></script>
<?php
$content = ob_get_clean();
renderModLayout('Создать сервис', $content);
?>
