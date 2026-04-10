<?php
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

    if (empty($name) || empty($category) || empty($country)) {
        $error = 'Заполните обязательные поля: название, категория, страна';
    } else {
        $password = generatePassword(8);
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$passHash, ADMIN_USER_ID]);

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
                if ($_FILES['photos']['size'][$i] > 5 * 1024 * 1024) continue;
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
                 languages, services, status, is_visible, admin_password,
                 call_status, call_note,
                 created_by_admin, created_by_moderator, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',1,?,?,?,?,?,NOW(),NOW())
        ");
        $stmt->execute([
            ADMIN_USER_ID, $name, $category, $subcategory, $country,
            $cityId ?: null, $description, $photoJson, $phone, $whatsapp, $email,
            $website, $address, $languagesJson, $servicesJson, $password,
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
        <div style="background:white;border-radius:var(--radius-sm);padding:14px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:6px;">Логин для входа</div>
            <div style="font-size:16px;font-weight:700;font-family:monospace;color:#1F2937;"><?php echo ADMIN_USER_EMAIL; ?></div>
        </div>
        <div style="background:white;border-radius:var(--radius-sm);padding:14px;">
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

<form method="POST" id="createForm" enctype="multipart/form-data">
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- Левая колонка -->
    <div>

        <!-- Категория -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">📋 Категория</div></div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Категория *</label>
                    <select name="category" id="categorySelect" class="form-control form-select" onchange="updateSubcategories()" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?php echo $key; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Подкатегория</label>
                    <select name="subcategory" id="subcategorySelect" class="form-control form-select" disabled>
                        <option value="">Сначала выберите категорию</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Основная информация -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">ℹ️ Основная информация</div></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Название сервиса *</label>
                    <input type="text" name="name" class="form-control" placeholder="Например: Доктор Петрова Анна" required maxlength="255">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Описание</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Описание сервиса..."></textarea>
                </div>
            </div>
        </div>

        <!-- Контакты -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">📞 Контакты</div></div>
            <div style="padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Телефон</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+33 6 12 34 56 78">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">WhatsApp</label>
                    <input type="tel" name="whatsapp" class="form-control" placeholder="+33 6 12 34 56 78">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="contact@example.com">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Сайт</label>
                    <input type="url" name="website" class="form-control" placeholder="https://example.com">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Адрес</label>
                    <input type="text" name="address" class="form-control" placeholder="Улица, дом, город">
                </div>
            </div>
        </div>

        <!-- Фото -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">📷 Фотографии</div></div>
            <div style="padding:16px;">
                <input type="file" id="photoInput" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handlePhotoUpload(event)">
                <div id="photoUploadZone" onclick="document.getElementById('photoInput').click()"
                     style="border:2px dashed var(--border);border-radius:var(--radius-sm);padding:20px;text-align:center;cursor:pointer;transition:all .15s;"
                     onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                    <svg style="width:36px;height:36px;margin:0 auto 10px;display:block;stroke:var(--text-light);fill:none;stroke-width:2;" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:4px;">Нажмите для загрузки фото</div>
                    <div style="font-size:12px;color:var(--text-light);">До 5 фото · JPG/PNG/WebP · макс. 5MB каждое</div>
                </div>
                <div id="photoPreview" style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:12px;"></div>
            </div>
        </div>

        <!-- Созвон при создании -->
        <div class="panel" style="margin-bottom:16px;" id="callBlockCreate">
            <div class="panel-header"><div class="panel-title">📞 Созвон с сервисом</div></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:10px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Статус созвона</label>
                    <select name="call_status" id="createCallStatus" class="form-control form-select" onchange="onCreateCallChange()">
                        <option value="not_called">— Не звонили —</option>
                        <option value="no_answer">Не дозвонились</option>
                        <option value="reached">✅ Дозвонились</option>
                        <option value="no_number">Нет номера</option>
                        <option value="other">Другое...</option>
                    </select>
                </div>
                <div id="createCallNoteBlock" style="display:none;">
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Заметка о созвоне</label>
                    <textarea name="call_note" class="form-control" rows="2" placeholder="Комментарий..."></textarea>
                </div>
            </div>
        </div>

        <!-- Услуги -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header">
                <div class="panel-title">💰 Услуги и цены</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addService()">+ Добавить</button>
            </div>
            <div style="padding:16px;">
                <div id="servicesList">
                    <div class="service-row" style="display:flex;gap:8px;margin-bottom:8px;">
                        <input type="text" name="services[0][name]" class="form-control" placeholder="Название услуги" style="flex:1;">
                        <input type="number" name="services[0][price]" class="form-control" placeholder="Цена €" style="width:100px;" min="0">
                        <button type="button" onclick="removeService(this)" class="btn btn-danger btn-sm">✕</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Языки -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">🗣 Языки</div></div>
            <div style="padding:16px;display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach (['ru'=>'🇷🇺 Русский','fr'=>'🇫🇷 Français','en'=>'🇬🇧 English','de'=>'🇩🇪 Deutsch','es'=>'🇪🇸 Español','it'=>'🇮🇹 Italiano'] as $code => $label): ?>
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:14px;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);">
                    <input type="checkbox" name="languages[]" value="<?php echo $code; ?>" <?php echo $code==='ru'?'checked':''; ?> style="accent-color:var(--primary);">
                    <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Правая колонка -->
    <div>

        <!-- Страна и город -->
        <div class="panel" style="margin-bottom:16px;">
            <div class="panel-header"><div class="panel-title">📍 Страна и город</div></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Страна *</label>
                    <select name="country" id="countrySelect" class="form-control form-select" onchange="loadCities(this.value)" required>
                        <option value="">Выберите страну</option>
                        <?php foreach ($countryNames as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Город</label>
                    <select name="city_id" id="citySelect" class="form-control form-select" disabled>
                        <option value="">Сначала выберите страну</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Инфо об аккаунте -->
        <div style="background:var(--primary-light);border:1px solid #BFDBFE;border-radius:var(--radius);padding:16px;margin-bottom:16px;">
            <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:10px;">👑 Сервис администратора</div>
            <div style="font-size:13px;color:var(--text-secondary);line-height:1.6;">
                Сервис будет привязан к аккаунту:<br>
                <strong style="color:var(--text);"><?php echo ADMIN_USER_EMAIL; ?></strong><br><br>
                Для каждого сервиса генерируется уникальный пароль. Сохраните его после создания — он отображается только один раз.
            </div>
        </div>

        <!-- Кнопка -->
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="white" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Создать и опубликовать
        </button>

    </div>
</div>
</form>

<style>
.photo-item-thumb{position:relative;aspect-ratio:1;border-radius:var(--radius-sm);overflow:hidden;border:2px solid var(--border);background:var(--bg);}
.photo-item-thumb img{width:100%;height:100%;object-fit:cover;}
.photo-item-thumb:first-child{border-color:var(--primary);}
.photo-item-thumb:first-child::after{content:"Главное";position:absolute;top:3px;left:3px;background:var(--primary);color:white;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;}
.photo-item-remove{position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.55);color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;line-height:1;}
.photo-item-remove:hover{background:var(--danger);}
</style>
<script>
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
    if (!country) { sel.disabled = true; sel.innerHTML = '<option value="">Сначала выберите страну</option>'; return; }
    sel.disabled = true;
    sel.innerHTML = '<option value="">Загрузка...</option>';
    try {
        const res = await fetch(`/api/get-cities.php?country=${country}`);
        const cities = await res.json();
        sel.innerHTML = '<option value="">Выберите город</option>';
        cities.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name_lat ? `${c.name_lat} (${c.name})` : c.name;
            sel.appendChild(o);
        });
        sel.disabled = false;
    } catch(e) {
        sel.innerHTML = '<option value="">Ошибка загрузки</option>';
    }
}

let photoCount = 0;
const maxPhotos = 5;

function handlePhotoUpload(e) {
    const preview = document.getElementById('photoPreview');
    for (const file of e.target.files) {
        if (photoCount >= maxPhotos) { alert('Максимум 5 фотографий'); break; }
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) { alert('Только JPG, PNG или WebP'); continue; }
        if (file.size > 5 * 1024 * 1024) { alert('Файл ' + file.name + ' превышает 5MB'); continue; }
        const reader = new FileReader();
        reader.onload = function(ev) {
            const item = document.createElement('div');
            item.className = 'photo-item-thumb';
            item.innerHTML = `<img src="${ev.target.result}" alt="">
                <button type="button" class="photo-item-remove" onclick="removePhotoThumb(this)">✕</button>`;
            preview.appendChild(item);
            photoCount++;
        };
        reader.readAsDataURL(file);
    }
    e.target.value = '';
}

function removePhotoThumb(btn) {
    btn.closest('.photo-item-thumb').remove();
    photoCount--;
}

function onCreateCallChange() {
    const val = document.getElementById('createCallStatus').value;
    document.getElementById('createCallNoteBlock').style.display = val === 'other' ? '' : 'none';
    const block = document.getElementById('callBlockCreate');
    block.style.background = val === 'reached'   ? '#ECFDF5' :
                              val === 'no_answer' ? '#FFFBEB' : '';
    block.style.borderColor = val === 'reached'   ? '#6EE7B7' :
                               val === 'no_answer' ? '#FDE68A' : '';
}

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

<?php
$content = ob_get_clean();
renderModLayout('Создать сервис', $content);
?>
