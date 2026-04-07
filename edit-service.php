<?php
// edit-service.php — Редактирование сервиса Poisq
header('Content-Type: text/html; charset=utf-8');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$userId    = $_SESSION['user_id'];
$userName  = $_SESSION['user_name']   ?? 'Пользователь';
$userEmail = $_SESSION['user_email']  ?? '';
$userAvatar= $_SESSION['user_avatar'] ?? '';
$userInitial = strtoupper(substr($userName, 0, 1));

// ── ПОЛУЧАЕМ ID СЕРВИСА ──────────────────────────────────────────────────────
$serviceId = intval($_GET['id'] ?? 0);
if (!$serviceId) {
    header('Location: my-services.php');
    exit;
}

// ── ЗАГРУЖАЕМ СЕРВИС ИЗ БД (только свой!) ───────────────────────────────────
try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND user_id = ?");
    $stmt->execute([$serviceId, $userId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Edit Service load error: ' . $e->getMessage());
    $service = null;
}

if (!$service) {
    header('Location: my-services.php?error=' . urlencode('Сервис не найден'));
    exit;
}

// Редактировать нельзя если сервис на модерации
if ($service['status'] === 'pending') {
    header('Location: my-services.php?error=' . urlencode('Сервис на модерации — редактирование недоступно'));
    exit;
}

// ── ДЕКОДИРУЕМ JSON-ПОЛЯ ─────────────────────────────────────────────────────
$existingPhotos = [];
if (!empty($service['photo'])) {
    $decoded = json_decode($service['photo'], true);
    if (is_array($decoded)) $existingPhotos = $decoded;
}
$existingHours    = !empty($service['hours'])    ? json_decode($service['hours'],    true) : [];
$existingLanguages= !empty($service['languages']) ? json_decode($service['languages'],true) : ['ru'];
$existingServices = !empty($service['services']) ? json_decode($service['services'], true) : [];
$existingSocial   = !empty($service['social'])   ? json_decode($service['social'],   true) : [];

// ── КАТЕГОРИИ И ПОДКАТЕГОРИИ ─────────────────────────────────────────────────
$categories = [];
try {
    $pdo2 = getDbConnection();
    $dbCats = $pdo2->query("SELECT slug, name FROM service_categories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $dbSubs = $pdo2->query("SELECT category_slug, name FROM service_subcategories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $subMap = [];
    foreach ($dbSubs as $s) $subMap[$s['category_slug']][] = $s['name'];
    foreach ($dbCats as $c) {
        $categories[$c['slug']] = [
            'name'          => $c['name'],
            'subcategories' => $subMap[$c['slug']] ?? [],
        ];
    }
} catch (Exception $e) {
    error_log('Edit categories DB error: ' . $e->getMessage());
}

// ── СПИСОК СТРАН ─────────────────────────────────────────────────────────────
$countries = [
    ['code'=>'fr','name'=>'Франция','flag'=>'🇫🇷'],       ['code'=>'de','name'=>'Германия','flag'=>'🇩🇪'],
    ['code'=>'es','name'=>'Испания','flag'=>'🇪🇸'],       ['code'=>'it','name'=>'Италия','flag'=>'🇮🇹'],
    ['code'=>'gb','name'=>'Великобритания','flag'=>'🇬🇧'],['code'=>'us','name'=>'США','flag'=>'🇺🇸'],
    ['code'=>'ca','name'=>'Канада','flag'=>'🇨🇦'],        ['code'=>'au','name'=>'Австралия','flag'=>'🇦🇺'],
    ['code'=>'ru','name'=>'Россия','flag'=>'🇷🇺'],        ['code'=>'ua','name'=>'Украина','flag'=>'🇺🇦'],
    ['code'=>'by','name'=>'Беларусь','flag'=>'🇧🇾'],      ['code'=>'kz','name'=>'Казахстан','flag'=>'🇰🇿'],
    ['code'=>'nl','name'=>'Нидерланды','flag'=>'🇳🇱'],    ['code'=>'be','name'=>'Бельгия','flag'=>'🇧🇪'],
    ['code'=>'ch','name'=>'Швейцария','flag'=>'🇨🇭'],     ['code'=>'at','name'=>'Австрия','flag'=>'🇦🇹'],
    ['code'=>'pt','name'=>'Португалия','flag'=>'🇵🇹'],    ['code'=>'gr','name'=>'Греция','flag'=>'🇬🇷'],
    ['code'=>'pl','name'=>'Польша','flag'=>'🇵🇱'],        ['code'=>'cz','name'=>'Чехия','flag'=>'🇨🇿'],
    ['code'=>'se','name'=>'Швеция','flag'=>'🇸🇪'],        ['code'=>'no','name'=>'Норвегия','flag'=>'🇳🇴'],
    ['code'=>'dk','name'=>'Дания','flag'=>'🇩🇰'],         ['code'=>'fi','name'=>'Финляндия','flag'=>'🇫🇮'],
    ['code'=>'ie','name'=>'Ирландия','flag'=>'🇮🇪'],      ['code'=>'nz','name'=>'Новая Зеландия','flag'=>'🇳🇿'],
    ['code'=>'ae','name'=>'ОАЭ','flag'=>'🇦🇪'],           ['code'=>'il','name'=>'Израиль','flag'=>'🇮🇱'],
    ['code'=>'tr','name'=>'Турция','flag'=>'🇹🇷'],        ['code'=>'th','name'=>'Таиланд','flag'=>'🇹🇭'],
    ['code'=>'jp','name'=>'Япония','flag'=>'🇯🇵'],        ['code'=>'kr','name'=>'Южная Корея','flag'=>'🇰🇷'],
    ['code'=>'sg','name'=>'Сингапур','flag'=>'🇸🇬'],      ['code'=>'hk','name'=>'Гонконг','flag'=>'🇭🇰'],
    ['code'=>'mx','name'=>'Мексика','flag'=>'🇲🇽'],       ['code'=>'br','name'=>'Бразилия','flag'=>'🇧🇷'],
    ['code'=>'ar','name'=>'Аргентина','flag'=>'🇦🇷'],     ['code'=>'cl','name'=>'Чили','flag'=>'🇨🇱'],
    ['code'=>'co','name'=>'Колумбия','flag'=>'🇨🇴'],      ['code'=>'za','name'=>'ЮАР','flag'=>'🇿🇦'],
];

// Разбиваем сохранённый телефон на код страны и номер для формы редактирования
$storedPhone = $service['phone'] ?? '';
$editPhoneDial = '+33';
$editPhoneNum  = $storedPhone;
if (str_starts_with($storedPhone, '+')) {
    foreach (['+375', '+380', '+998', '+996', '+994', '+993', '+992', '+977', '+974', '+972', '+971', '+966', '+965', '+964', '+963', '+961', '+960', '+962', '+856', '+855', '+853', '+852', '+850', '+84', '+82', '+81', '+66', '+65', '+64', '+63', '+62', '+61', '+60', '+58', '+57', '+56', '+55', '+54', '+53', '+52', '+51', '+49', '+48', '+47', '+46', '+45', '+44', '+43', '+41', '+40', '+39', '+36', '+34', '+33', '+32', '+31', '+30', '+27', '+20', '+7', '+1'] as $dial) {
        if (str_starts_with($storedPhone, $dial)) {
            $editPhoneDial = $dial;
            $editPhoneNum  = substr($storedPhone, strlen($dial));
            break;
        }
    }
}

// Находим флаг текущей страны
$currentCountryFlag = '🏳️';
$currentCountryName = $service['country_code'];
foreach ($countries as $c) {
    if ($c['code'] === $service['country_code']) {
        $currentCountryFlag = $c['flag'];
        $currentCountryName = $c['name'];
        break;
    }
}

// ── ОБРАБОТКА POST (СОХРАНЕНИЕ) ──────────────────────────────────────────────
$error = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';

    // Кастомный город
    $customCityName = trim($_POST['custom_city'] ?? '');
    $cityId = intval($_POST['city_id'] ?? 0);

    if (!empty($customCityName) && $cityId == 0) {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? AND country_code = ?");
            $checkStmt->execute([$customCityName, trim($_POST['country'] ?? '')]);
            $existingCity = $checkStmt->fetch();
            if ($existingCity) {
                $cityId = $existingCity['id'];
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO cities (name, country_code, is_capital, sort_order, status) VALUES (?, ?, 0, 999, 'pending')");
                $insertStmt->execute([$customCityName, trim($_POST['country'] ?? '')]);
                $cityId = $pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log('Custom city error: ' . $e->getMessage());
        }
    }

    $formData = [
        'country'     => trim($_POST['country']     ?? ''),
        'city_id'     => $cityId,
        'category'    => trim($_POST['category']    ?? ''),
        'subcategory' => trim($_POST['subcategory'] ?? ''),
        'name'        => trim($_POST['name']        ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'phone'       => (function() {
            $num = trim($_POST['phone'] ?? '');
            $dial = trim($_POST['phone_country'] ?? '');
            if ($num === '') return '';
            return (str_starts_with($num, '+')) ? $num : $dial . $num;
        })(),
        'whatsapp'    => trim($_POST['whatsapp']    ?? ''),
        'email'       => trim($_POST['email']       ?? ''),
        'website'     => trim($_POST['website']     ?? ''),
        'address'     => trim($_POST['address']     ?? ''),
        'group_link'  => trim($_POST['group_link']  ?? ''),
        'services'    => $_POST['services']         ?? [],
        'hours'       => $_POST['hours']            ?? [],
        'languages'   => $_POST['languages']        ?? [],
        'social'      => [
            'instagram' => trim($_POST['instagram'] ?? ''),
            'facebook'  => trim($_POST['facebook']  ?? ''),
            'vk'        => trim($_POST['vk']        ?? ''),
            'telegram'  => trim($_POST['telegram']  ?? ''),
        ],
        'status' => $action === 'publish' ? 'pending' : 'draft',
    ];

    // Валидация
    $errors = [];
    if (empty($formData['country']))    $errors[] = 'Выберите страну';
    if (empty($formData['city_id']) && empty($customCityName)) $errors[] = 'Выберите или введите город';
    if (empty($formData['category']))   $errors[] = 'Выберите категорию';
    if (empty($formData['subcategory']))$errors[] = 'Выберите подкатегорию';
    if (empty($formData['name']) || strlen($formData['name']) < 3) $errors[] = 'Название должно быть не менее 3 символов';
    if (empty($formData['description']) || strlen($formData['description']) < 100) $errors[] = 'Описание должно быть не менее 100 символов';
    $isMessengers = ($formData['category'] === 'messengers');
    if ($isMessengers && empty($formData['group_link'])) $errors[] = 'Введите ссылку на группу';
    if (!$isMessengers && empty($formData['phone']))      $errors[] = 'Введите телефон';
    if (!$isMessengers && (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL))) $errors[] = 'Введите корректный email';
    if (!$isMessengers && empty($formData['address']))    $errors[] = 'Введите адрес';
    if (!$isMessengers && (empty($formData['services']) || !is_array($formData['services']))) $errors[] = 'Добавьте хотя бы одну услугу';

    if (empty($errors)) {
        try {
            // 1. Обработка НОВЫХ фото (добавленных)
            $photoPaths = $existingPhotos; // сохраняем старые

            // Удалить отмеченные фото
            $deletePhotos = $_POST['delete_photos'] ?? [];
            if (!empty($deletePhotos)) {
                foreach ($deletePhotos as $photoPath) {
                    $fullPath = __DIR__ . $photoPath;
                    if (file_exists($fullPath)) @unlink($fullPath);
                }
                $photoPaths = array_values(array_diff($photoPaths, $deletePhotos));
            }

            // Загрузить новые фото
            if (!empty($_FILES['photos']['name'][0])) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $count = min(count($_FILES['photos']['name']), 5 - count($photoPaths));
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName  = $_FILES['photos']['tmp_name'][$i];
                        $fileName = uniqid('photo_') . '.jpg';
                        $imgInfo  = getimagesize($tmpName);
                        if ($imgInfo && in_array($imgInfo['mime'], ['image/jpeg','image/png','image/webp'])) {
                            $src = imagecreatefromstring(file_get_contents($tmpName));
                            $w = imagesx($src); $h = imagesy($src);
                            $maxW = 800;
                            if ($w > $maxW) {
                                $ratio = $maxW / $w;
                                $dst = imagecreatetruecolor($maxW, (int)($h * $ratio));
                                imagecopyresampled($dst, $src, 0,0,0,0, $maxW,(int)($h*$ratio),$w,$h);
                                imagejpeg($dst, $uploadDir.$fileName, 85);
                                imagedestroy($dst);
                            } else {
                                imagejpeg($src, $uploadDir.$fileName, 85);
                            }
                            imagedestroy($src);
                            $photoPaths[] = '/uploads/' . $fileName;
                        }
                    }
                }
            }

            // 2. JSON-поля
            $hoursJson     = json_encode($formData['hours'],     JSON_UNESCAPED_UNICODE);
            $languagesJson = json_encode($formData['languages'], JSON_UNESCAPED_UNICODE);
            $servicesJson  = json_encode($formData['services'],  JSON_UNESCAPED_UNICODE);
            $socialJson    = json_encode($formData['social'],    JSON_UNESCAPED_UNICODE);
            $photoJson     = $photoPaths ? json_encode(array_values($photoPaths), JSON_UNESCAPED_UNICODE) : null;

            // 3. UPDATE
            $stmt = $pdo->prepare("
                UPDATE services SET
                    name=?, category=?, subcategory=?, city_id=?, country_code=?,
                    description=?, photo=?, phone=?, whatsapp=?, email=?, website=?,
                    address=?, group_link=?, hours=?, languages=?, services=?, social=?,
                    status=?, updated_at=NOW()
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([
                $formData['name'],
                $formData['category'],
                $formData['subcategory'],
                $formData['city_id'] > 0 ? $formData['city_id'] : null,
                $formData['country'],
                $formData['description'],
                $photoJson,
                $formData['phone'],
                $formData['whatsapp'],
                $formData['email'],
                $formData['website'],
                $formData['address'],
                $formData['group_link'],
                $hoursJson,
                $languagesJson,
                $servicesJson,
                $socialJson,
                $formData['status'],
                $serviceId,
                $userId,
            ]);

            $msg = $action === 'publish'
                ? 'Сервис отправлен на модерацию!'
                : 'Изменения сохранены как черновик';

            header('Location: my-services.php?success=' . urlencode($msg));
            exit;

        } catch (PDOException $e) {
            error_log('Edit Service save error: ' . $e->getMessage());
            $error = 'Ошибка сохранения. Попробуйте позже.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$days = ['mon'=>'Понедельник','tue'=>'Вторник','wed'=>'Среда','thu'=>'Четверг','fri'=>'Пятница','sat'=>'Суббота','sun'=>'Воскресенье'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Редактировать сервис — Poisq</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{
  --primary:#2E73D8;--primary-light:#EEF4FF;--primary-dark:#1A5AB8;
  --text:#1F2937;--text-secondary:#9CA3AF;--text-light:#6B7280;
  --bg:#FFFFFF;--bg-secondary:#F5F5F7;--border:#D1D5DB;--border-light:#E5E7EB;
  --success:#10B981;--warning:#F59E0B;--danger:#EF4444;
  --shadow-sm:0 2px 8px rgba(0,0,0,0.06);
}
html{-webkit-overflow-scrolling:touch;overflow-y:auto;height:auto}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg-secondary);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;touch-action:manipulation;overflow-y:auto}
.app-container{max-width:430px;margin:0 auto;background:var(--bg);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}

/* ── ШАПКА ── */
.header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg);border-bottom:1px solid var(--border-light);height:56px;position:sticky;top:0;z-index:100}
.header-left{display:flex;align-items:center;gap:12px}
.header-right{display:flex;align-items:center}
.btn-back{width:40px;height:40px;border-radius:12px;border:none;background:var(--bg-secondary);color:var(--text);display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.btn-back:active{transform:scale(0.95);background:var(--border)}
.btn-back svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2}
.header-title{font-size:17px;font-weight:600;color:var(--text)}
.btn-burger{width:40px;height:40px;display:flex;flex-direction:column;justify-content:center;align-items:center;gap:5px;padding:8px;cursor:pointer;background:none;border:none;border-radius:12px}
.btn-burger span{display:block;width:22px;height:2.5px;background:#6B7280;border-radius:2px;transition:all 0.2s ease}
.btn-burger.active span:nth-child(1){transform:translateY(7.5px) rotate(45deg)}
.btn-burger.active span:nth-child(2){opacity:0}
.btn-burger.active span:nth-child(3){transform:translateY(-7.5px) rotate(-45deg)}

/* ── БОКОВОЕ МЕНЮ ── */
.side-menu{position:fixed;top:0;right:-100%;width:280px;height:100vh;background:var(--bg);z-index:400;transition:right 0.3s ease;box-shadow:-4px 0 20px rgba(0,0,0,0.15);display:flex;flex-direction:column}
.side-menu.active{right:0}
.side-menu-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:399;display:none}
.side-menu-overlay.active{display:block}
.side-menu-header{padding:20px;background:var(--bg-secondary);border-bottom:1px solid var(--border-light)}
.user-info{display:flex;align-items:center;gap:12px}
.user-avatar{width:50px;height:50px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:18px;flex-shrink:0}
.user-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
.user-name{font-size:16px;font-weight:600;color:var(--text)}
.user-email{font-size:13px;color:var(--text-secondary)}
.side-menu-items{flex:1;overflow-y:auto;padding:10px 0}
.menu-item{display:flex;align-items:center;gap:14px;padding:14px 20px;color:var(--text);text-decoration:none;font-size:15px;font-weight:500;transition:background 0.15s}
.menu-item:active{background:var(--bg-secondary)}
.menu-item svg{width:22px;height:22px;stroke:var(--text-secondary);fill:none;stroke-width:2;flex-shrink:0}
.menu-divider{height:1px;background:var(--border-light);margin:10px 0}

/* ── ХЛЕБНЫЕ КРОШКИ ── */
.breadcrumbs{padding:12px 16px;background:var(--bg-secondary);border-bottom:1px solid var(--border-light)}
.breadcrumb-item{font-size:13px;color:var(--text-secondary);text-decoration:none}
.breadcrumb-separator{margin:0 8px;color:var(--text-light)}
.breadcrumb-current{font-size:13px;color:var(--text);font-weight:500}

/* ── ФОРМА ── */
.form-container{flex:1;padding:20px 16px 100px}
.alert{padding:14px 16px;border-radius:12px;font-size:14px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px}
.alert-error{background:#FEF2F2;color:var(--danger);border:1px solid #FECACA}
.alert-success{background:#F0FDF4;color:var(--success);border:1px solid #BBF7D0}
.alert svg{width:20px;height:20px;flex-shrink:0;margin-top:1px}
.form-section{background:var(--bg);border-radius:16px;margin-bottom:16px;overflow:hidden;border:1px solid var(--border-light)}
.section-header{padding:16px;background:var(--bg-secondary);border-bottom:1px solid var(--border-light)}
.section-title{font-size:15px;font-weight:600;color:var(--text)}
.section-content{padding:16px}
.form-group{margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-label{display:block;font-size:14px;font-weight:500;color:var(--text);margin-bottom:6px}
.form-label .required{color:var(--danger)}
.form-input,.form-select,.form-textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;font-size:15px;color:var(--text);background:var(--bg);outline:none;transition:all 0.2s;-webkit-appearance:none;appearance:none}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(46,115,216,0.1)}
.form-input.error,.form-select.error,.form-textarea.error{border-color:var(--danger)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--text-secondary)}
.form-textarea{min-height:120px;resize:vertical}
.form-select{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:40px}
.form-hint{font-size:12px;color:var(--text-secondary);margin-top:4px}
.form-error{font-size:12px;color:var(--danger);margin-top:4px}

/* ── СТРАНА ── */
.country-selector{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;cursor:pointer;background:var(--bg);transition:all 0.2s}
.country-selector.error{border-color:var(--danger)}
.country-flag{width:28px;height:20px;border-radius:4px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px}
.country-name{flex:1;font-size:15px;color:var(--text);font-weight:500}
.country-arrow{width:20px;height:20px;fill:var(--text-secondary)}
.country-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:500;display:none;align-items:flex-start;justify-content:center}
.country-modal.active{display:flex}
.country-modal-content{background:white;width:100%;max-width:430px;max-height:90vh;border-radius:0 0 24px 24px;overflow:hidden;display:flex;flex-direction:column;animation:slideDown 0.25s ease-out}
@keyframes slideDown{from{transform:translateY(-100%)}to{transform:translateY(0)}}
.country-modal-header{padding:16px 20px;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center;background:white}
.country-modal-title{font-size:18px;font-weight:700;color:var(--text)}
.country-modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--bg-secondary);cursor:pointer;font-size:20px;color:var(--text-light)}
.country-search{padding:12px 20px;border-bottom:1px solid var(--border-light);background:white}
.country-search-input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:12px;font-size:16px;outline:none}
.country-search-input:focus{border-color:var(--primary)}
.country-list{overflow-y:auto;padding:4px 0;flex:1;-webkit-overflow-scrolling:touch}
.country-item{display:flex;align-items:center;gap:14px;padding:10px 20px;cursor:pointer;transition:background 0.15s}
.country-item:active{background:var(--bg-secondary)}
.country-item-flag{width:32px;height:24px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.country-item-name{font-size:15px;color:var(--text);flex:1}
.country-item-check{width:20px;height:20px;stroke:var(--primary);fill:none;stroke-width:2;opacity:0}
.country-item.selected .country-item-check{opacity:1}

/* ── ЧАСЫ РАБОТЫ ── */
.hours-row {
display: flex;
flex-direction: column;
gap: 4px;
margin-bottom: 8px;
padding: 10px 0;
border-bottom: 1px solid var(--border-light);
}
.hours-row:last-child {
border-bottom: none;
margin-bottom: 0;
}
.hours-day {
font-size: 14px;
font-weight: 600;
color: var(--text);
margin-bottom: 4px;
}
.hours-main-row {
display: flex;
align-items: center;
gap: 8px;
}
.hours-time {
flex: 1;
display: flex;
gap: 6px;
align-items: center;
}
.hours-time input {
flex: 1;
padding: 8px 8px;
border: 1px solid var(--border);
border-radius: 8px;
font-size: 13px;
min-width: 0;
}
.hours-flags {
display: flex;
gap: 6px;
align-items: center;
flex-shrink: 0;
}
.hours-flag-btn {
display: flex;
align-items: center;
gap: 4px;
font-size: 12px;
font-weight: 600;
color: var(--primary);
cursor: pointer;
padding: 5px 8px;
border: 1px solid var(--primary);
border-radius: 6px;
background: #EFF6FF;
user-select: none;
}
.hours-flag-btn input { display: none; }
.hours-flag-btn.active { background: var(--primary); color: white; }
.hours-break-row {
display: flex;
align-items: center;
gap: 6px;
margin-top: 4px;
padding: 6px 8px;
background: #FFFBEB;
border-radius: 8px;
border: 1px solid #FDE68A;
}
.hours-break-label {
font-size: 12px;
color: var(--warning);
font-weight: 600;
white-space: nowrap;
flex-shrink: 0;
}
.hours-break-remove {
font-size: 12px;
background: none;
border: none;
color: var(--text-secondary);
cursor: pointer;
padding: 2px 4px;
flex-shrink: 0;
}
.btn-add-break {
font-size: 12px;
color: var(--text-secondary);
background: none;
border: none;
cursor: pointer;
padding: 2px 0;
text-decoration: underline;
align-self: flex-start;
}
.btn-add-break:active { color: var(--primary); }
.hours-row.is-closed .hours-main-row .hours-time input,
.hours-row.is-closed .btn-add-break { opacity: 0.4; pointer-events: none; }
.hours-row.is-24h .hours-main-row .hours-time input { opacity: 0.4; pointer-events: none; }
.hours-closed {
display: flex;
align-items: center;
gap: 6px;
font-size: 13px;
color: var(--text-secondary);
}
.btn-copy-hours {
width: 100%;
margin-top: 12px;
padding: 10px;
background: var(--bg-secondary);
border: 1px solid var(--border);
border-radius: 8px;
font-size: 13px;
font-weight: 500;
color: var(--text);
cursor: pointer;
}
.btn-copy-hours:active { background: var(--border); }

/* ── УСЛУГИ ── */
.services-list{margin-bottom:12px}
.service-row{display:flex;gap:8px;margin-bottom:8px;align-items:center}
.service-row input{flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px}
.service-row input[type="number"]{width:100px;flex:none}
.btn-remove-service{width:36px;height:36px;border-radius:8px;border:none;background:#FEE2E2;color:var(--danger);cursor:pointer;display:flex;align-items:center;justify-content:center}
.btn-add-service{width:100%;padding:12px;background:var(--bg-secondary);border:2px dashed var(--border);border-radius:12px;font-size:14px;font-weight:500;color:var(--text-secondary);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-add-service:active{background:var(--border-light);border-color:var(--primary);color:var(--primary)}

/* ── ЯЗЫКИ ── */
.languages-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.language-checkbox{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:14px}
.language-checkbox input{width:18px;height:18px;accent-color:var(--primary)}

/* ── ФОТО ── */
.photo-upload{border:2px dashed var(--border);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all 0.2s}
.photo-upload:active{border-color:var(--primary);background:#E8F0FE}
.photo-upload-icon{width:48px;height:48px;margin:0 auto 12px;stroke:var(--text-light)}
.photo-upload-text{font-size:14px;color:var(--text-secondary)}
.photo-upload-hint{font-size:12px;color:var(--text-light);margin-top:4px}
.photo-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px}
.photo-item{position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:var(--bg-secondary)}
.photo-item img{width:100%;height:100%;object-fit:cover}
.photo-item-main{border:2px solid var(--primary)}
.photo-item-badge{position:absolute;top:4px;left:4px;background:var(--primary);color:white;font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px}
.photo-item-remove{position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,0.6);color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center}
.photo-item-remove:active{background:var(--danger)}
.photo-item-existing .photo-delete-checkbox{position:absolute;bottom:4px;right:4px;width:18px;height:18px;accent-color:var(--danger)}

/* ── СОЦСЕТИ ── */
.social-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.social-input-wrapper{position:relative}
.social-input-wrapper svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;stroke:var(--text-light)}
.social-input-wrapper input{padding-left:40px}

/* ── КНОПКИ СНИЗУ ── */
.form-actions{position:fixed;bottom:0;left:0;right:0;background:var(--bg);border-top:1px solid var(--border-light);padding:12px 16px;z-index:100;display:flex;gap:8px;max-width:430px;margin:0 auto}
.btn{flex:1;padding:14px 20px;border-radius:12px;border:none;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.15s}
.btn:active{transform:scale(0.98)}
.btn-secondary{background:var(--bg-secondary);color:var(--text)}
.btn-secondary:active{background:var(--border)}
.btn-primary{background:var(--primary);color:white}
.btn-primary:active{background:var(--primary-dark)}

::-webkit-scrollbar{display:none}

/* ── DIAL CODE PICKER ── */
.dial-picker { position: relative; width: 130px; flex: none; }
.dial-trigger {
  width: 100%; min-height: 50px;
  display: flex; align-items: center; gap: 5px;
  padding: 10px 10px; border: 1px solid var(--border);
  border-radius: 12px; background: var(--bg);
  cursor: pointer; font-family: inherit;
  transition: border-color 0.2s, box-shadow 0.2s; -webkit-appearance: none;
}
.dial-trigger:focus { outline: none; }
.dial-trigger.open {
  border-color: var(--primary); box-shadow: 0 0 0 3px rgba(46,115,216,0.1);
}
.dial-trigger-flag { font-size: 17px; flex-shrink: 0; }
.dial-trigger-code {
  font-size: 14px; font-weight: 500; color: var(--text);
  flex: 1; text-align: left; white-space: nowrap;
}
.dial-trigger-arrow {
  width: 14px; height: 14px; stroke: var(--text-secondary);
  fill: none; stroke-width: 2.5; flex-shrink: 0; transition: transform 0.2s;
}
.dial-trigger.open .dial-trigger-arrow { transform: rotate(180deg); }
.dial-dropdown {
  position: absolute; top: calc(100% + 4px); left: 0;
  min-width: 250px; background: var(--bg);
  border: 1px solid var(--border); border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.13);
  z-index: 300; display: none; flex-direction: column; overflow: hidden;
}
.dial-dropdown.open { display: flex; }
.dial-search-wrap {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; border-bottom: 1px solid var(--border-light); flex-shrink: 0;
}
.dial-search-wrap svg { width: 14px; height: 14px; stroke: var(--text-secondary); fill: none; stroke-width: 2; flex-shrink: 0; }
.dial-search-input {
  flex: 1; border: none; outline: none; font-size: 14px;
  color: var(--text); background: transparent; font-family: inherit;
}
.dial-search-input::placeholder { color: var(--text-secondary); }
.dial-list { max-height: 220px; overflow-y: auto; padding: 4px 0; -webkit-overflow-scrolling: touch; }
.dial-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; cursor: pointer; transition: background 0.12s;
}
.dial-item:hover { background: var(--bg-secondary); }
.dial-item.selected { background: rgba(46,115,216,0.07); }
.dial-item-flag { font-size: 15px; flex-shrink: 0; width: 22px; text-align: center; }
.dial-item-name { flex: 1; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dial-item-code { font-size: 12px; color: var(--text-secondary); font-weight: 500; flex-shrink: 0; }
.dial-divider { height: 1px; background: var(--border-light); margin: 4px 0; }
.dial-group-label {
  font-size: 10.5px; font-weight: 700; color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px; padding: 5px 12px 2px;
}
.dial-empty { padding: 20px 12px; text-align: center; font-size: 13px; color: var(--text-secondary); }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">

<!-- ШАПКА -->
<header class="header">
  <div class="header-left">
    <a href="my-services.php" class="btn-back" aria-label="Назад">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="header-title">Редактировать</span>
  </div>
  <div class="header-right">
    <button class="btn-burger" id="menuToggle" aria-label="Меню">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- БОКОВОЕ МЕНЮ -->
<div class="side-menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-header">
    <div class="user-info">
      <div class="user-avatar">
        <?php if ($userAvatar): ?><img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar"><?php else: ?><?php echo $userInitial; ?><?php endif; ?>
      </div>
      <div>
        <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
        <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
      </div>
    </div>
  </div>
  <div class="side-menu-items">
    <a href="profile.php"      class="menu-item"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Личный кабинет</a>
    <a href="my-services.php"  class="menu-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>Мои сервисы</a>
    <a href="index.php"        class="menu-item"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Главная</a>
    <div class="menu-divider"></div>
    <a href="logout.php"       class="menu-item"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Выйти</a>
  </div>
</div>

<!-- ХЛЕБНЫЕ КРОШКИ -->
<nav class="breadcrumbs">
  <a href="my-services.php" class="breadcrumb-item">Мои сервисы</a>
  <span class="breadcrumb-separator">/</span>
  <span class="breadcrumb-current">Редактировать</span>
</nav>

<!-- ФОРМА -->
<main class="form-container">

  <?php if ($error): ?>
  <div class="alert alert-error">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><?php echo $error; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" action="edit-service.php?id=<?php echo $serviceId; ?>" id="serviceForm" enctype="multipart/form-data">
    <input type="hidden" name="action" id="formAction" value="draft">

    <!-- ── КАТЕГОРИЯ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">📋 Категория</h2></div>
      <div class="section-content">
        <div class="form-group">
          <label class="form-label">Категория <span class="required">*</span></label>
          <select class="form-select" id="category" name="category" onchange="updateSubcategories()">
            <option value="">Выберите категорию</option>
            <?php foreach ($categories as $key => $cat): ?>
            <option value="<?php echo $key; ?>" <?php echo $service['category'] === $key ? 'selected' : ''; ?>>
              <?php echo $cat['name']; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-error" id="categoryError"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Подкатегория <span class="required">*</span></label>
          <select class="form-select" id="subcategory" name="subcategory">
            <option value="">Сначала выберите категорию</option>
            <?php if (!empty($service['category']) && isset($categories[$service['category']])): ?>
              <?php foreach ($categories[$service['category']]['subcategories'] as $sub): ?>
              <option value="<?php echo htmlspecialchars($sub); ?>" <?php echo $service['subcategory'] === $sub ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sub); ?>
              </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <div class="form-error" id="subcategoryError"></div>
        </div>
      </div>
    </div>
    <!-- ССЫЛКА НА ГРУППУ (мессенджеры) -->
    <div class="form-section" id="groupLinkSection" style="display:none;">
      <div class="section-content">
        <div class="form-group">
          <label class="form-label">Ссылка на группу <span class="required">*</span></label>
          <input type="url" class="form-input" id="groupLink" name="group_link"
            value="<?php echo htmlspecialchars($service['group_link'] ?? ''); ?>"
            placeholder="https://chat.whatsapp.com/... или https://t.me/...">
          <div class="form-error" id="groupLinkError"></div>
        </div>
      </div>
    </div>

    <!-- ── ОСНОВНАЯ ИНФОРМАЦИЯ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">ℹ️ Основная информация</h2></div>
      <div class="section-content">
        <div class="form-group">
          <label class="form-label" id="nameLabelText">Название сервиса <span class="required">*</span></label>
          <input type="text" class="form-input" id="name" name="name"
            value="<?php echo htmlspecialchars($service['name']); ?>"
            placeholder="Например: Доктор Петрова Анна" minlength="3" maxlength="100">
          <div class="form-error" id="nameError"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Описание <span class="required">*</span></label>
          <textarea class="form-textarea" id="description" name="description" minlength="100" maxlength="2000"><?php echo htmlspecialchars($service['description']); ?></textarea>
          <div class="form-hint"><span id="descCount"><?php echo strlen($service['description']); ?></span>/100 мин. символов</div>
          <div class="form-error" id="descriptionError"></div>
        </div>
      </div>
    </div>

    <!-- ── СТРАНА, ГОРОД, КОНТАКТЫ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">📍 Страна, город и контакты</h2></div>
      <div class="section-content">

        <!-- СТРАНА -->
        <div class="form-group">
          <label class="form-label">Страна <span class="required">*</span></label>
          <div class="country-selector" id="countrySelector" onclick="openCountryModal()">
            <div class="country-flag" id="selectedCountryFlag"><?php echo $currentCountryFlag; ?></div>
            <span class="country-name" id="selectedCountryName"><?php echo htmlspecialchars($currentCountryName); ?></span>
            <svg class="country-arrow" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
            <input type="hidden" id="country" name="country" value="<?php echo htmlspecialchars($service['country_code']); ?>">
          </div>
          <div class="form-error" id="countryError"></div>
        </div>

        <!-- ГОРОД -->
        <div class="form-group">
          <label class="form-label">Город <span class="required">*</span></label>
          <select class="form-select" id="citySelect" name="city_id">
            <option value="">Загрузка...</option>
          </select>
          <input type="hidden" id="cityHidden" value="<?php echo intval($service['city_id'] ?? 0); ?>">
          <div class="form-hint">Город где находится сервис</div>
          <div class="form-error" id="cityError"></div>
          <div id="customCityToggle" style="display:none;margin-top:10px;">
            <button type="button" onclick="toggleCustomCity()" id="btnCustomCity"
              style="width:100%;padding:11px 16px;border-radius:12px;border:2px dashed var(--primary);background:var(--primary-light);color:var(--primary);font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Моего города нет в списке
            </button>
          </div>
          <div id="customCityBlock" style="display:none;margin-top:10px;">
            <div style="background:var(--primary);border-radius:12px;padding:12px;margin-bottom:10px;">
              <p style="font-size:13px;color:#fff;font-weight:600;margin-bottom:4px;">📍 Укажите ваш город</p>
              <p style="font-size:12px;color:rgba(255,255,255,0.85);">Город будет добавлен после проверки</p>
            </div>
            <input type="text" class="form-input" id="customCityInput" name="custom_city" placeholder="Введите название города" autocomplete="off">
            <input type="hidden" id="customCityHidden" name="city_id_hidden" value="0">
            <button type="button" onclick="cancelCustomCity()" style="margin-top:8px;background:none;border:none;color:var(--text-secondary);font-size:13px;cursor:pointer;padding:4px 0;">← Вернуться к списку</button>
          </div>
        </div>

        <!-- ТЕЛЕФОН -->
        <div id="phoneEmailBlock">
        <div class="form-group">
          <label class="form-label">Телефон <span class="required">*</span></label>
          <div style="display:flex;gap:8px;">
            <div class="dial-picker" id="dialPicker">
              <button type="button" class="dial-trigger" id="dialTrigger" onclick="toggleDialDropdown(event)">
                <span class="dial-trigger-flag" id="dialSelectedFlag">🇫🇷</span>
                <span class="dial-trigger-code" id="dialSelectedCode"><?php echo htmlspecialchars($editPhoneDial); ?></span>
                <svg class="dial-trigger-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="dial-dropdown" id="dialDropdown">
                <div class="dial-search-wrap">
                  <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                  <input type="text" class="dial-search-input" id="dialSearch" placeholder="Страна или +код…" oninput="filterDialList(this.value)" autocomplete="off" autocorrect="off" autocapitalize="off">
                </div>
                <div class="dial-list" id="dialList"></div>
              </div>
              <input type="hidden" name="phone_country" id="phoneCountry" value="<?php echo htmlspecialchars($editPhoneDial); ?>">
            </div>
            <input type="tel" class="form-input" id="phone" name="phone"
              value="<?php echo htmlspecialchars($editPhoneNum); ?>" placeholder="6 12 34 56 78">
          </div>
          <div class="form-error" id="phoneError"></div>
        </div>

        <!-- WHATSAPP -->
        <div class="form-group">
          <label class="form-label">WhatsApp (опционально)</label>
          <input type="tel" class="form-input" id="whatsapp" name="whatsapp"
            value="<?php echo htmlspecialchars($service['whatsapp'] ?? ''); ?>" placeholder="Тот же номер или другой">
        </div>

        <!-- EMAIL -->
        <div class="form-group">
          <label class="form-label">Email <span class="required">*</span></label>
          <input type="email" class="form-input" id="email" name="email"
            value="<?php echo htmlspecialchars($service['email']); ?>" placeholder="example@mail.com">
          <div class="form-error" id="emailError"></div>
        </div>

        <!-- САЙТ -->
        <div class="form-group">
          <label class="form-label">Веб-сайт (опционально)</label>
          <input type="url" class="form-input" id="website" name="website"
            value="<?php echo htmlspecialchars($service['website'] ?? ''); ?>" placeholder="https://example.com">
        </div>

        </div><!-- /phoneEmailBlock -->

        <!-- АДРЕС -->
        <div id="addressBlock">
        <div class="form-group">
          <label class="form-label">Адрес <span class="required">*</span></label>
          <input type="text" class="form-input" id="address" name="address"
            value="<?php echo htmlspecialchars($service['address'] ?? ''); ?>" placeholder="Улица, дом, город, страна">
          <div class="form-error" id="addressError"></div>
        </div>
        </div><!-- /addressBlock -->

      </div>
    </div>

    <!-- ── ЧАСЫ РАБОТЫ ── -->
    <div class="form-section" id="hoursSection">
      <div class="section-header"><h2 class="section-title">🕐 Часы работы</h2></div>
      <div class="section-content">
        <div id="hoursContainer">
          <?php foreach ($days as $key => $name): ?>
          <?php
            $open  = $existingHours[$key]['open']  ?? '';
            $close = $existingHours[$key]['close'] ?? '';
            $closed = (empty($open) && empty($close));
            $breakStart = $existingHours[$key]['break_start'] ?? '';
            $breakEnd   = $existingHours[$key]['break_end']   ?? '';
            $hasBreak   = (!empty($breakStart) && !empty($breakEnd));
            $is24h = ($open === '00:00' && $close === '23:59') || ($open === '0:00' && $close === '23:59');
          ?>
          <div class="hours-row<?php echo $closed ? ' is-closed' : ''; ?><?php echo $is24h ? ' is-24h' : ''; ?>" data-day="<?php echo $key; ?>">
            <div class="hours-day"><?php echo $name; ?></div>
            <div class="hours-main-row">
              <div class="hours-time">
                <input type="time" name="hours[<?php echo $key; ?>][open]"  class="hours-open"  value="<?php echo htmlspecialchars($open); ?>"  <?php echo ($closed || $is24h) ? 'disabled' : ''; ?>>
                <span>—</span>
                <input type="time" name="hours[<?php echo $key; ?>][close]" class="hours-close" value="<?php echo htmlspecialchars($close); ?>" <?php echo ($closed || $is24h) ? 'disabled' : ''; ?>>
              </div>
              <div class="hours-flags">
                <label class="hours-flag-btn<?php echo $is24h ? ' active' : ''; ?>" title="Круглосуточно">
                  <input type="checkbox" class="hours-24h-checkbox" onchange="toggle24h(this)" <?php echo $is24h ? 'checked' : ''; ?>>
                  <span>24ч</span>
                </label>
                <label class="hours-closed">
                  <input type="checkbox" class="hours-closed-checkbox" onchange="toggleHoursRow(this)" <?php echo $closed ? 'checked' : ''; ?>>
                  Вых.
                </label>
              </div>
            </div>
            <div class="hours-break-row" style="<?php echo $hasBreak ? 'display:flex;' : 'display:none;'; ?>">
              <span class="hours-break-label">Перерыв:</span>
              <div class="hours-time">
                <input type="time" name="hours[<?php echo $key; ?>][break_start]" class="hours-break-start" value="<?php echo htmlspecialchars($breakStart); ?>">
                <span>—</span>
                <input type="time" name="hours[<?php echo $key; ?>][break_end]" class="hours-break-end" value="<?php echo htmlspecialchars($breakEnd); ?>">
              </div>
              <button type="button" class="hours-break-remove" onclick="removeBreak(this)" title="Убрать перерыв">✕</button>
            </div>
            <button type="button" class="btn-add-break" onclick="addBreak(this)"<?php echo $hasBreak ? ' style="display:none;"' : ''; ?>>+ перерыв</button>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
          <button type="button" class="btn-copy-hours" onclick="copyHoursToAll()">
            📋 Скопировать на все дни
          </button>
          <button type="button" class="btn-copy-hours" onclick="setAll24h()" style="background:#EFF6FF; border-color:#BFDBFE; color:#1D4ED8;">
            🕐 Все круглосуточно
          </button>
        </div>
      </div>
    </div>

    <!-- ── ЯЗЫКИ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">🗣 Языки</h2></div>
      <div class="section-content">
        <div class="languages-grid">
          <?php $langList = ['ru'=>'🇷🇺 Русский','fr'=>'🇫🇷 Français','en'=>'🇬🇧 English','de'=>'🇩🇪 Deutsch','es'=>'🇪🇸 Español']; ?>
          <?php foreach ($langList as $code => $label): ?>
          <label class="language-checkbox">
            <input type="checkbox" name="languages[]" value="<?php echo $code; ?>" <?php echo in_array($code, $existingLanguages) ? 'checked' : ''; ?>>
            <?php echo $label; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── УСЛУГИ И ЦЕНЫ ── -->
    <div class="form-section" id="servicesPricesSection">
      <div class="section-header"><h2 class="section-title">💰 Услуги и цены</h2></div>
      <div class="section-content">
        <div class="services-list" id="servicesList">
          <?php if (!empty($existingServices)): ?>
            <?php foreach ($existingServices as $i => $svc): ?>
            <div class="service-row">
              <input type="text"   name="services[<?php echo $i; ?>][name]"  value="<?php echo htmlspecialchars($svc['name']  ?? ''); ?>" placeholder="Название услуги">
              <input type="number" name="services[<?php echo $i; ?>][price]" value="<?php echo htmlspecialchars($svc['price'] ?? ''); ?>" placeholder="€" min="0" step="0.01" style="width:100px;flex:none">
              <button type="button" class="btn-remove-service" onclick="removeService(this)">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="service-row">
              <input type="text"   name="services[0][name]"  placeholder="Название услуги">
              <input type="number" name="services[0][price]" placeholder="€" min="0" step="0.01" style="width:100px;flex:none">
              <button type="button" class="btn-remove-service" onclick="removeService(this)">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-add-service" onclick="addService()">
          <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Добавить услугу
        </button>
        <div class="form-hint">Минимум 1 услуга, максимум 20</div>
        <div class="form-error" id="servicesError"></div>
      </div>
    </div>

    <!-- ── ФОТОГРАФИИ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">📷 Фотографии</h2></div>
      <div class="section-content">

        <?php if (!empty($existingPhotos)): ?>
        <div style="margin-bottom:12px;">
          <p style="font-size:13px;color:var(--text-secondary);margin-bottom:8px;">Текущие фото (нажмите ✕ чтобы удалить):</p>
          <div class="photo-preview" id="existingPhotosPreview">
            <?php foreach ($existingPhotos as $i => $photoPath): ?>
            <div class="photo-item photo-item-existing" id="existing-<?php echo $i; ?>">
              <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Фото <?php echo $i+1; ?>">
              <?php if ($i === 0): ?><div class="photo-item-badge">Основное</div><?php endif; ?>
              <button type="button" class="photo-item-remove" onclick="markDeletePhoto('<?php echo htmlspecialchars($photoPath); ?>', 'existing-<?php echo $i; ?>')">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
              <input type="hidden" name="keep_photos[]" value="<?php echo htmlspecialchars($photoPath); ?>" id="keep-<?php echo $i; ?>">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <input type="file" id="photoInput" name="photos[]" multiple accept="image/*" style="display:none;" onchange="handlePhotoUpload(event)">
        <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
          <svg class="photo-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
          </svg>
          <div class="photo-upload-text">Нажмите для загрузки новых фото</div>
          <div class="photo-upload-hint">До <?php echo 5 - count($existingPhotos); ?> новых фото, JPG/PNG/WebP, макс. 5MB</div>
        </div>
        <div class="photo-preview" id="photoPreview"></div>
        <input type="hidden" name="photo_count" id="photoCount" value="0">
      </div>
    </div>

    <!-- ── СОЦИАЛЬНЫЕ СЕТИ ── -->
    <div class="form-section">
      <div class="section-header"><h2 class="section-title">🌐 Социальные сети</h2></div>
      <div class="section-content">
        <div class="social-grid">
          <div class="social-input-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
            <input type="url" class="form-input" name="instagram" placeholder="Instagram" value="<?php echo htmlspecialchars($existingSocial['instagram'] ?? ''); ?>">
          </div>
          <div class="social-input-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
            <input type="url" class="form-input" name="facebook" placeholder="Facebook" value="<?php echo htmlspecialchars($existingSocial['facebook'] ?? ''); ?>">
          </div>
          <div class="social-input-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <input type="tel" class="form-input" name="telegram" placeholder="Telegram" value="<?php echo htmlspecialchars($existingSocial['telegram'] ?? ''); ?>">
          </div>
          <div class="social-input-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <input type="url" class="form-input" name="vk" placeholder="VK" value="<?php echo htmlspecialchars($existingSocial['vk'] ?? ''); ?>">
          </div>
        </div>
      </div>
    </div>

  </form>
</main>

<!-- МОДАЛКА ВЫБОРА СТРАНЫ -->
<div class="country-modal" id="countryModal">
  <div class="country-modal-content">
    <div class="country-modal-header">
      <span class="country-modal-title">Выберите страну</span>
      <button class="country-modal-close" id="closeCountryModal">&times;</button>
    </div>
    <div class="country-search">
      <input type="text" class="country-search-input" id="countrySearch" placeholder="Поиск страны...">
    </div>
    <div class="country-list" id="countryList"></div>
  </div>
</div>

<!-- КНОПКИ ВНИЗУ -->
<div class="form-actions">
  <button type="button" class="btn btn-secondary" onclick="saveDraft()">💾 Сохранить</button>
  <button type="button" class="btn btn-primary"   onclick="submitForm()">📤 На модерацию</button>
</div>

</div><!-- /app-container -->

<script>
// ── ДАННЫЕ ───────────────────────────────────────────────────────────────────
const countries    = <?php echo json_encode($countries); ?>;
const subcategories= <?php echo json_encode($categories); ?>;
const savedCityId  = <?php echo intval($service['city_id'] ?? 0); ?>;
const savedCountry = '<?php echo htmlspecialchars($service['country_code']); ?>';
let serviceCount   = <?php echo max(count($existingServices), 1); ?>;
let photoCount     = 0;
const maxPhotos    = <?php echo max(0, 5 - count($existingPhotos)); ?>;
let deletedPhotos  = [];

// ── БОКОВОЕ МЕНЮ ─────────────────────────────────────────────────────────────
document.getElementById('menuToggle').addEventListener('click', () => {
  document.getElementById('menuToggle').classList.toggle('active');
  document.getElementById('sideMenu').classList.toggle('active');
  document.getElementById('menuOverlay').classList.toggle('active');
});
function closeMenu() {
  document.getElementById('menuToggle').classList.remove('active');
  document.getElementById('sideMenu').classList.remove('active');
  document.getElementById('menuOverlay').classList.remove('active');
}

// ── ИНИЦИАЛИЗАЦИЯ: загружаем города для текущей страны ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (savedCountry) {
    loadCities(savedCountry, savedCityId);
  }
  // Подкатегории уже предзаполнены в PHP, просто включаем select
  const cat = document.getElementById('category').value;
  if (cat) {
    document.getElementById('subcategory').disabled = false;
  }
  // Счётчик описания
  document.getElementById('descCount').textContent = document.getElementById('description').value.length;
  // Инициализируем dial с сохранённым кодом страны
  setPhoneDialCode('<?php echo $editPhoneDial; ?>');
  // Применяем режим мессенджера если нужно
  applyMessengerMode(document.getElementById('category').value === 'messengers');
});

// ── СТРАНА ───────────────────────────────────────────────────────────────────
function openCountryModal() {
  const list = document.getElementById('countryList');
  const sel  = document.getElementById('country').value;
  list.innerHTML = countries.map(c => `
    <div class="country-item ${c.code === sel ? 'selected' : ''}"
      onclick="selectCountry('${c.code}','${c.name}','${c.flag}')">
      <div class="country-item-flag">${c.flag}</div>
      <span class="country-item-name">${c.name}</span>
      <svg class="country-item-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
    </div>
  `).join('');
  const searchInput = document.getElementById('countrySearch');
  searchInput.value = '';
  searchInput.oninput = function() {
    const q = this.value.toLowerCase();
    list.querySelectorAll('.country-item').forEach(item => {
      item.style.display = item.querySelector('.country-item-name').textContent.toLowerCase().includes(q) ? 'flex' : 'none';
    });
  };
  document.getElementById('countryModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  setTimeout(() => searchInput.focus(), 300);
}
function closeCountryModal() {
  document.getElementById('countryModal').classList.remove('active');
  document.body.style.overflow = '';
}
function selectCountry(code, name, flag) {
  document.getElementById('country').value          = code;
  document.getElementById('selectedCountryFlag').textContent = flag;
  document.getElementById('selectedCountryName').textContent = name;
  loadCities(code, 0);
  closeCountryModal();
  // Автоподстановка телефонного кода страны
  const phoneCodes = {
    'fr': '+33', 'de': '+49', 'es': '+34', 'it': '+39', 'gb': '+44',
    'us': '+1', 'ca': '+1', 'au': '+61', 'ru': '+7', 'ua': '+380',
    'by': '+375', 'kz': '+7', 'nl': '+31', 'be': '+32', 'ch': '+41',
    'at': '+43', 'pt': '+351', 'gr': '+30', 'pl': '+48', 'cz': '+420',
    'se': '+46', 'no': '+47', 'dk': '+45', 'fi': '+358', 'ie': '+353',
    'nz': '+64', 'ae': '+971', 'il': '+972', 'tr': '+90', 'th': '+66',
    'jp': '+81', 'kr': '+82', 'sg': '+65', 'hk': '+852', 'mx': '+52',
    'br': '+55', 'ar': '+54', 'cl': '+56', 'co': '+57', 'za': '+27',
  };
  if (phoneCodes[code]) {
    setPhoneDialCode(phoneCodes[code]);
  }
}
document.getElementById('closeCountryModal').addEventListener('click', closeCountryModal);
document.getElementById('countryModal').addEventListener('click', e => { if (e.target === document.getElementById('countryModal')) closeCountryModal(); });

// ── ГОРОДА ───────────────────────────────────────────────────────────────────
async function loadCities(countryCode, preselectId = 0) {
  const sel = document.getElementById('citySelect');
  sel.disabled = true;
  sel.innerHTML = '<option value="">Загрузка городов...</option>';
  document.getElementById('customCityToggle').style.display = 'none';
  sel.style.display = '';
  sel.name = 'city_id';

  try {
    const res    = await fetch(`api/get-cities.php?country=${countryCode}`);
    const cities = await res.json();
    sel.innerHTML = '<option value="">Выберите город</option>';

    if (!cities.length) {
      sel.innerHTML = '<option value="">Нет городов в базе</option>';
      sel.disabled  = true;
      document.getElementById('customCityToggle').style.display = 'block';
      return;
    }

    cities.forEach(city => {
      const opt   = document.createElement('option');
      opt.value   = city.id;
      opt.textContent = city.name_lat
        ? `${city.name_lat} (${city.name})${city.is_capital == 1 ? ' ★' : ''}`
        : `${city.name}${city.is_capital == 1 ? ' ★' : ''}`;
      if (preselectId && city.id == preselectId) opt.selected = true;
      sel.appendChild(opt);
    });
    // Если город выбран — обнуляем hidden (он больше не нужен)
    if (preselectId) document.getElementById('cityHidden').value = '';

    sel.disabled = false;
    document.getElementById('customCityToggle').style.display = 'block';

  } catch(e) {
    sel.innerHTML = '<option value="">Ошибка загрузки</option>';
    sel.disabled  = true;
    document.getElementById('customCityToggle').style.display = 'block';
  }
}

function toggleCustomCity() {
  document.getElementById('customCityBlock').style.display  = 'block';
  document.getElementById('citySelect').style.display       = 'none';
  document.getElementById('citySelect').name                = 'city_id_disabled';
  document.getElementById('customCityHidden').name          = 'city_id';
  document.getElementById('btnCustomCity').style.display    = 'none';
  document.getElementById('customCityInput').focus();
}
function cancelCustomCity() {
  document.getElementById('customCityBlock').style.display  = 'none';
  document.getElementById('citySelect').style.display       = '';
  document.getElementById('citySelect').name                = 'city_id';
  document.getElementById('customCityHidden').name          = 'city_id_hidden';
  document.getElementById('btnCustomCity').style.display    = 'flex';
  document.getElementById('customCityInput').value          = '';
}

// ── ПОДКАТЕГОРИИ ─────────────────────────────────────────────────────────────
function updateSubcategories() {
  const cat  = document.getElementById('category').value;
  const sel  = document.getElementById('subcategory');
  sel.innerHTML = '<option value="">Выберите подкатегорию</option>';
  if (cat && subcategories[cat]) {
    sel.disabled = false;
    subcategories[cat].subcategories.forEach(sub => {
      const opt = document.createElement('option');
      opt.value = sub; opt.textContent = sub;
      sel.appendChild(opt);
    });
  } else {
    sel.disabled = true;
  }
  applyMessengerMode(cat === 'messengers');
}

// ── СЧЁТЧИК ОПИСАНИЯ ─────────────────────────────────────────────────────────
document.getElementById('description').addEventListener('input', function() {
  document.getElementById('descCount').textContent = this.value.length;
});

// ── ЧАСЫ РАБОТЫ ──────────────────────────────────────────────────────────────
function toggleHoursRow(checkbox) {
const row = checkbox.closest('.hours-row');
const openInput = row.querySelector('.hours-open');
const closeInput = row.querySelector('.hours-close');
const btn24h = row.querySelector('.hours-24h-checkbox');
if (checkbox.checked) {
row.classList.add('is-closed');
openInput.disabled = true; closeInput.disabled = true;
openInput.value = ''; closeInput.value = '';
if (btn24h) { btn24h.checked = false; row.classList.remove('is-24h'); btn24h.closest('.hours-flag-btn').classList.remove('active'); }
} else {
row.classList.remove('is-closed');
openInput.disabled = false; closeInput.disabled = false;
}
hasUnsavedChanges = true;
}
function toggle24h(checkbox) {
const row = checkbox.closest('.hours-row');
const openInput = row.querySelector('.hours-open');
const closeInput = row.querySelector('.hours-close');
const closedCheckbox = row.querySelector('.hours-closed-checkbox');
if (checkbox.checked) {
row.classList.add('is-24h');
checkbox.closest('.hours-flag-btn').classList.add('active');
openInput.value = '00:00'; closeInput.value = '23:59';
openInput.disabled = true; closeInput.disabled = true;
if (closedCheckbox) { closedCheckbox.checked = false; row.classList.remove('is-closed'); }
} else {
row.classList.remove('is-24h');
checkbox.closest('.hours-flag-btn').classList.remove('active');
openInput.disabled = false; closeInput.disabled = false;
openInput.value = ''; closeInput.value = '';
}
hasUnsavedChanges = true;
}
function addBreak(btn) {
const row = btn.closest('.hours-row');
const breakRow = row.querySelector('.hours-break-row');
if (breakRow.style.display === 'none' || !breakRow.style.display) {
breakRow.style.display = 'flex';
btn.style.display = 'none';
}
hasUnsavedChanges = true;
}
function removeBreak(btn) {
const row = btn.closest('.hours-row');
const breakRow = row.querySelector('.hours-break-row');
breakRow.style.display = 'none';
breakRow.querySelector('.hours-break-start').value = '';
breakRow.querySelector('.hours-break-end').value = '';
row.querySelector('.btn-add-break').style.display = '';
hasUnsavedChanges = true;
}
function setAll24h() {
document.querySelectorAll('.hours-row').forEach(row => {
const cb = row.querySelector('.hours-24h-checkbox');
if (cb) { cb.checked = true; toggle24h(cb); }
});
}
function copyHoursToAll() {
const firstRow = document.querySelector('.hours-row');
const openTime = firstRow.querySelector('.hours-open').value;
const closeTime = firstRow.querySelector('.hours-close').value;
const isClosed = firstRow.querySelector('.hours-closed-checkbox').checked;
const is24h = firstRow.querySelector('.hours-24h-checkbox').checked;
const hasBreak = firstRow.querySelector('.hours-break-row').style.display !== 'none';
const breakStart = firstRow.querySelector('.hours-break-start').value;
const breakEnd = firstRow.querySelector('.hours-break-end').value;
document.querySelectorAll('.hours-row').forEach(row => {
const openInput = row.querySelector('.hours-open');
const closeInput = row.querySelector('.hours-close');
const closedCb = row.querySelector('.hours-closed-checkbox');
const cb24h = row.querySelector('.hours-24h-checkbox');
const breakRow = row.querySelector('.hours-break-row');
const breakStartInput = row.querySelector('.hours-break-start');
const breakEndInput = row.querySelector('.hours-break-end');
const addBreakBtn = row.querySelector('.btn-add-break');
if (is24h) {
cb24h.checked = true; toggle24h(cb24h);
} else if (isClosed) {
closedCb.checked = true; toggleHoursRow(closedCb);
} else {
closedCb.checked = false; cb24h.checked = false;
row.classList.remove('is-closed','is-24h');
cb24h.closest('.hours-flag-btn').classList.remove('active');
openInput.disabled = false; closeInput.disabled = false;
openInput.value = openTime; closeInput.value = closeTime;
if (hasBreak && breakStart && breakEnd) {
breakRow.style.display = 'flex';
breakStartInput.value = breakStart; breakEndInput.value = breakEnd;
if (addBreakBtn) addBreakBtn.style.display = 'none';
} else {
breakRow.style.display = 'none';
breakStartInput.value = ''; breakEndInput.value = '';
if (addBreakBtn) addBreakBtn.style.display = '';
}
}
});
hasUnsavedChanges = true;
}

// ── УСЛУГИ ───────────────────────────────────────────────────────────────────
function addService() {
  if (serviceCount >= 20) { alert('Максимум 20 услуг'); return; }
  const list = document.getElementById('servicesList');
  const row  = document.createElement('div');
  row.className = 'service-row';
  row.innerHTML = `
    <input type="text"   name="services[${serviceCount}][name]"  placeholder="Название услуги">
    <input type="number" name="services[${serviceCount}][price]" placeholder="€" min="0" step="0.01" style="width:100px;flex:none">
    <button type="button" class="btn-remove-service" onclick="removeService(this)">
      <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>`;
  list.appendChild(row);
  serviceCount++;
}
function removeService(btn) {
  if (document.querySelectorAll('.service-row').length <= 1) { alert('Должна быть хотя бы одна услуга'); return; }
  btn.closest('.service-row').remove();
}

// ── ФОТО: удаление существующих ──────────────────────────────────────────────
function markDeletePhoto(path, blockId) {
  deletedPhotos.push(path);
  // добавляем hidden input с пометкой на удаление
  const inp = document.createElement('input');
  inp.type = 'hidden'; inp.name = 'delete_photos[]'; inp.value = path;
  document.getElementById('serviceForm').appendChild(inp);
  // скрываем блок
  const block = document.getElementById(blockId);
  if (block) block.style.opacity = '0.3';
  // убираем keep input
  const keepId = 'keep-' + blockId.replace('existing-','');
  const keepInp = document.getElementById(keepId);
  if (keepInp) keepInp.disabled = true;
}

// ── ФОТО: загрузка новых ─────────────────────────────────────────────────────
function handlePhotoUpload(event) {
  const preview = document.getElementById('photoPreview');
  for (let file of event.target.files) {
    if (photoCount >= maxPhotos) { alert('Достигнут лимит фотографий'); break; }
    if (!file.type.match('image.*')) { alert('Только изображения (JPG, PNG, WebP)'); continue; }
    if (file.size > 5*1024*1024) { alert('Максимальный размер 5MB'); continue; }
    const reader = new FileReader();
    reader.onload = e => {
      const item = document.createElement('div');
      item.className = 'photo-item';
      item.innerHTML = `
        <img src="${e.target.result}" alt="Photo">
        <button type="button" class="photo-item-remove" onclick="this.closest('.photo-item').remove(); photoCount--; document.getElementById('photoCount').value = photoCount;">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>`;
      preview.appendChild(item);
      photoCount++;
      document.getElementById('photoCount').value = photoCount;
    };
    reader.readAsDataURL(file);
  }
}

// ── ВАЛИДАЦИЯ ─────────────────────────────────────────────────────────────────
function validateForm(skipRequired = false) {
  document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
  document.querySelectorAll('.form-input,.form-select,.form-textarea,.country-selector').forEach(el => el.classList.remove('error'));
  let valid = true;
  if (!skipRequired) {
    if (!document.getElementById('country').value)      { document.getElementById('countryError').textContent = 'Выберите страну'; document.getElementById('countrySelector').classList.add('error'); valid = false; }
    const cityVal = document.getElementById('citySelect').value || document.getElementById('cityHidden').value;
    if (!cityVal) { document.getElementById('cityError').textContent = 'Выберите город'; document.getElementById('citySelect').classList.add('error'); valid = false; }
    if (!document.getElementById('category').value)     { document.getElementById('categoryError').textContent = 'Выберите категорию'; document.getElementById('category').classList.add('error'); valid = false; }
    if (!document.getElementById('subcategory').value)  { document.getElementById('subcategoryError').textContent = 'Выберите подкатегорию'; document.getElementById('subcategory').classList.add('error'); valid = false; }
    const nm = document.getElementById('name').value;
    if (nm.length < 3) { document.getElementById('nameError').textContent = 'Минимум 3 символа'; document.getElementById('name').classList.add('error'); valid = false; }
    if (document.getElementById('description').value.length < 100) { document.getElementById('descriptionError').textContent = 'Минимум 100 символов'; document.getElementById('description').classList.add('error'); valid = false; }
    const isMess = document.getElementById('category').value === 'messengers';
    if (isMess && !document.getElementById('groupLink').value) {
      document.getElementById('groupLinkError').textContent = 'Введите ссылку на группу';
      document.getElementById('groupLink').classList.add('error');
      valid = false;
    }
    if (!isMess && !document.getElementById('phone').value) { document.getElementById('phoneError').textContent = 'Введите телефон'; document.getElementById('phone').classList.add('error'); valid = false; }
    const em = document.getElementById('email').value;
    if (!isMess && (!em || !em.includes('@'))) { document.getElementById('emailError').textContent = 'Введите корректный email'; document.getElementById('email').classList.add('error'); valid = false; }
    if (!isMess && !document.getElementById('address').value) { document.getElementById('addressError').textContent = 'Введите адрес'; document.getElementById('address').classList.add('error'); valid = false; }
    if (!isMess && !document.querySelectorAll('.service-row').length) { document.getElementById('servicesError').textContent = 'Добавьте хотя бы одну услугу'; valid = false; }
  }
  return valid;
}

// ── СОХРАНИТЬ ЧЕРНОВИК ────────────────────────────────────────────────────────
function saveDraft() {
  if (!validateForm(true)) return;
  document.getElementById('formAction').value = 'draft';
  enableHoursFields();
  document.getElementById('serviceForm').submit();
}

// ── ОТПРАВИТЬ НА МОДЕРАЦИЮ ────────────────────────────────────────────────────
function enableHoursFields() {
  document.querySelectorAll('.hours-open, .hours-close, .hours-break-start, .hours-break-end').forEach(inp => {
    inp.disabled = false;
  });
}
function submitForm() {
  if (!validateForm()) return;
  if (confirm('Отправить сервис на модерацию? После отправки редактирование будет недоступно до проверки.')) {
    document.getElementById('formAction').value = 'publish';
    enableHoursFields();
    document.getElementById('serviceForm').submit();
  }
}

// ── МЕССЕНДЖЕРЫ ──────────────────────────────────────────────────────────────
function applyMessengerMode(isMessenger) {
  // Ссылка на группу
  document.getElementById('groupLinkSection').style.display = isMessenger ? 'block' : 'none';
  // Телефон и Email
  document.getElementById('phoneEmailBlock').style.display = isMessenger ? 'none' : 'block';
  const phoneInput = document.getElementById('phone');
  const emailInput = document.getElementById('email');
  if (phoneInput) phoneInput.required = !isMessenger;
  if (emailInput) emailInput.required = !isMessenger;
  // Адрес
  document.getElementById('addressBlock').style.display = isMessenger ? 'none' : 'block';
  const addressInput = document.getElementById('address');
  if (addressInput) addressInput.required = !isMessenger;
  // Услуги и цены
  document.getElementById('servicesPricesSection').style.display = isMessenger ? 'none' : 'block';
  // Часы работы
  document.getElementById('hoursSection').style.display = isMessenger ? 'none' : 'block';
  // Название: сервис vs группа
  const nameLabel = document.getElementById('nameLabelText');
  if (nameLabel) nameLabel.innerHTML = isMessenger
    ? 'Название группы <span class="required">*</span>'
    : 'Название сервиса <span class="required">*</span>';
  // Ссылка обязательна для мессенджеров
  const groupLink = document.getElementById('groupLink');
  if (groupLink) groupLink.required = isMessenger;
}

// ── Dial code picker ──────────────────────────────────────────────────────────
const DIAL_POPULAR = ['ru','by','ua','kz','fr','de','es','it','gb','us','ca','au'];
const DIAL_ALL = [
  {code:'ru',flag:'🇷🇺',name:'Россия',dial:'+7'},
  {code:'by',flag:'🇧🇾',name:'Беларусь',dial:'+375'},
  {code:'ua',flag:'🇺🇦',name:'Украина',dial:'+380'},
  {code:'kz',flag:'🇰🇿',name:'Казахстан',dial:'+7'},
  {code:'fr',flag:'🇫🇷',name:'Франция',dial:'+33'},
  {code:'de',flag:'🇩🇪',name:'Германия',dial:'+49'},
  {code:'es',flag:'🇪🇸',name:'Испания',dial:'+34'},
  {code:'it',flag:'🇮🇹',name:'Италия',dial:'+39'},
  {code:'gb',flag:'🇬🇧',name:'Великобритания',dial:'+44'},
  {code:'us',flag:'🇺🇸',name:'США',dial:'+1'},
  {code:'ca',flag:'🇨🇦',name:'Канада',dial:'+1'},
  {code:'au',flag:'🇦🇺',name:'Австралия',dial:'+61'},
  {code:'at',flag:'🇦🇹',name:'Австрия',dial:'+43'},
  {code:'az',flag:'🇦🇿',name:'Азербайджан',dial:'+994'},
  {code:'dz',flag:'🇩🇿',name:'Алжир',dial:'+213'},
  {code:'ao',flag:'🇦🇴',name:'Ангола',dial:'+244'},
  {code:'ad',flag:'🇦🇩',name:'Андорра',dial:'+376'},
  {code:'ag',flag:'🇦🇬',name:'Антигуа и Барбуда',dial:'+1268'},
  {code:'ar',flag:'🇦🇷',name:'Аргентина',dial:'+54'},
  {code:'am',flag:'🇦🇲',name:'Армения',dial:'+374'},
  {code:'af',flag:'🇦🇫',name:'Афганистан',dial:'+93'},
  {code:'bd',flag:'🇧🇩',name:'Бангладеш',dial:'+880'},
  {code:'bb',flag:'🇧🇧',name:'Барбадос',dial:'+1246'},
  {code:'bh',flag:'🇧🇭',name:'Бахрейн',dial:'+973'},
  {code:'bz',flag:'🇧🇿',name:'Белиз',dial:'+501'},
  {code:'be',flag:'🇧🇪',name:'Бельгия',dial:'+32'},
  {code:'bj',flag:'🇧🇯',name:'Бенин',dial:'+229'},
  {code:'bg',flag:'🇧🇬',name:'Болгария',dial:'+359'},
  {code:'bo',flag:'🇧🇴',name:'Боливия',dial:'+591'},
  {code:'ba',flag:'🇧🇦',name:'Босния и Герцеговина',dial:'+387'},
  {code:'bw',flag:'🇧🇼',name:'Ботсвана',dial:'+267'},
  {code:'br',flag:'🇧🇷',name:'Бразилия',dial:'+55'},
  {code:'bn',flag:'🇧🇳',name:'Бруней',dial:'+673'},
  {code:'bf',flag:'🇧🇫',name:'Буркина-Фасо',dial:'+226'},
  {code:'bi',flag:'🇧🇮',name:'Бурунди',dial:'+257'},
  {code:'bt',flag:'🇧🇹',name:'Бутан',dial:'+975'},
  {code:'vu',flag:'🇻🇺',name:'Вануату',dial:'+678'},
  {code:'va',flag:'🇻🇦',name:'Ватикан',dial:'+379'},
  {code:'hu',flag:'🇭🇺',name:'Венгрия',dial:'+36'},
  {code:'ve',flag:'🇻🇪',name:'Венесуэла',dial:'+58'},
  {code:'vn',flag:'🇻🇳',name:'Вьетнам',dial:'+84'},
  {code:'ga',flag:'🇬🇦',name:'Габон',dial:'+241'},
  {code:'ht',flag:'🇭🇹',name:'Гаити',dial:'+509'},
  {code:'gy',flag:'🇬🇾',name:'Гайана',dial:'+592'},
  {code:'gm',flag:'🇬🇲',name:'Гамбия',dial:'+220'},
  {code:'gh',flag:'🇬🇭',name:'Гана',dial:'+233'},
  {code:'gt',flag:'🇬🇹',name:'Гватемала',dial:'+502'},
  {code:'gn',flag:'🇬🇳',name:'Гвинея',dial:'+224'},
  {code:'gw',flag:'🇬🇼',name:'Гвинея-Бисау',dial:'+245'},
  {code:'hn',flag:'🇭🇳',name:'Гондурас',dial:'+504'},
  {code:'hk',flag:'🇭🇰',name:'Гонконг',dial:'+852'},
  {code:'gd',flag:'🇬🇩',name:'Гренада',dial:'+1473'},
  {code:'gr',flag:'🇬🇷',name:'Греция',dial:'+30'},
  {code:'ge',flag:'🇬🇪',name:'Грузия',dial:'+995'},
  {code:'dk',flag:'🇩🇰',name:'Дания',dial:'+45'},
  {code:'dj',flag:'🇩🇯',name:'Джибути',dial:'+253'},
  {code:'dm',flag:'🇩🇲',name:'Доминика',dial:'+1767'},
  {code:'do',flag:'🇩🇴',name:'Доминиканская Республика',dial:'+1809'},
  {code:'eg',flag:'🇪🇬',name:'Египет',dial:'+20'},
  {code:'zm',flag:'🇿🇲',name:'Замбия',dial:'+260'},
  {code:'zw',flag:'🇿🇼',name:'Зимбабве',dial:'+263'},
  {code:'il',flag:'🇮🇱',name:'Израиль',dial:'+972'},
  {code:'in',flag:'🇮🇳',name:'Индия',dial:'+91'},
  {code:'id',flag:'🇮🇩',name:'Индонезия',dial:'+62'},
  {code:'jo',flag:'🇯🇴',name:'Иордания',dial:'+962'},
  {code:'iq',flag:'🇮🇶',name:'Ирак',dial:'+964'},
  {code:'ir',flag:'🇮🇷',name:'Иран',dial:'+98'},
  {code:'ie',flag:'🇮🇪',name:'Ирландия',dial:'+353'},
  {code:'is',flag:'🇮🇸',name:'Исландия',dial:'+354'},
  {code:'ye',flag:'🇾🇪',name:'Йемен',dial:'+967'},
  {code:'cv',flag:'🇨🇻',name:'Кабо-Верде',dial:'+238'},
  {code:'kh',flag:'🇰🇭',name:'Камбоджа',dial:'+855'},
  {code:'cm',flag:'🇨🇲',name:'Камерун',dial:'+237'},
  {code:'qa',flag:'🇶🇦',name:'Катар',dial:'+974'},
  {code:'ke',flag:'🇰🇪',name:'Кения',dial:'+254'},
  {code:'cy',flag:'🇨🇾',name:'Кипр',dial:'+357'},
  {code:'cn',flag:'🇨🇳',name:'Китай',dial:'+86'},
  {code:'co',flag:'🇨🇴',name:'Колумбия',dial:'+57'},
  {code:'km',flag:'🇰🇲',name:'Коморы',dial:'+269'},
  {code:'cg',flag:'🇨🇬',name:'Конго',dial:'+242'},
  {code:'cd',flag:'🇨🇩',name:'Конго Д.Р.',dial:'+243'},
  {code:'xk',flag:'🇽🇰',name:'Косово',dial:'+383'},
  {code:'cr',flag:'🇨🇷',name:'Коста-Рика',dial:'+506'},
  {code:'ci',flag:'🇨🇮',name:'Кот-д\'Ивуар',dial:'+225'},
  {code:'cu',flag:'🇨🇺',name:'Куба',dial:'+53'},
  {code:'kw',flag:'🇰🇼',name:'Кувейт',dial:'+965'},
  {code:'kg',flag:'🇰🇬',name:'Кыргызстан',dial:'+996'},
  {code:'la',flag:'🇱🇦',name:'Лаос',dial:'+856'},
  {code:'lv',flag:'🇱🇻',name:'Латвия',dial:'+371'},
  {code:'ls',flag:'🇱🇸',name:'Лесото',dial:'+266'},
  {code:'lr',flag:'🇱🇷',name:'Либерия',dial:'+231'},
  {code:'lb',flag:'🇱🇧',name:'Ливан',dial:'+961'},
  {code:'ly',flag:'🇱🇾',name:'Ливия',dial:'+218'},
  {code:'li',flag:'🇱🇮',name:'Лихтенштейн',dial:'+423'},
  {code:'lt',flag:'🇱🇹',name:'Литва',dial:'+370'},
  {code:'lu',flag:'🇱🇺',name:'Люксембург',dial:'+352'},
  {code:'mu',flag:'🇲🇺',name:'Маврикий',dial:'+230'},
  {code:'mr',flag:'🇲🇷',name:'Мавритания',dial:'+222'},
  {code:'mg',flag:'🇲🇬',name:'Мадагаскар',dial:'+261'},
  {code:'mw',flag:'🇲🇼',name:'Малави',dial:'+265'},
  {code:'my',flag:'🇲🇾',name:'Малайзия',dial:'+60'},
  {code:'mv',flag:'🇲🇻',name:'Мальдивы',dial:'+960'},
  {code:'ml',flag:'🇲🇱',name:'Мали',dial:'+223'},
  {code:'mt',flag:'🇲🇹',name:'Мальта',dial:'+356'},
  {code:'ma',flag:'🇲🇦',name:'Марокко',dial:'+212'},
  {code:'mx',flag:'🇲🇽',name:'Мексика',dial:'+52'},
  {code:'md',flag:'🇲🇩',name:'Молдова',dial:'+373'},
  {code:'mc',flag:'🇲🇨',name:'Монако',dial:'+377'},
  {code:'mn',flag:'🇲🇳',name:'Монголия',dial:'+976'},
  {code:'mz',flag:'🇲🇿',name:'Мозамбик',dial:'+258'},
  {code:'mm',flag:'🇲🇲',name:'Мьянма',dial:'+95'},
  {code:'na',flag:'🇳🇦',name:'Намибия',dial:'+264'},
  {code:'np',flag:'🇳🇵',name:'Непал',dial:'+977'},
  {code:'ne',flag:'🇳🇪',name:'Нигер',dial:'+227'},
  {code:'ng',flag:'🇳🇬',name:'Нигерия',dial:'+234'},
  {code:'nl',flag:'🇳🇱',name:'Нидерланды',dial:'+31'},
  {code:'ni',flag:'🇳🇮',name:'Никарагуа',dial:'+505'},
  {code:'nz',flag:'🇳🇿',name:'Новая Зеландия',dial:'+64'},
  {code:'no',flag:'🇳🇴',name:'Норвегия',dial:'+47'},
  {code:'ae',flag:'🇦🇪',name:'ОАЭ',dial:'+971'},
  {code:'om',flag:'🇴🇲',name:'Оман',dial:'+968'},
  {code:'pk',flag:'🇵🇰',name:'Пакистан',dial:'+92'},
  {code:'pa',flag:'🇵🇦',name:'Панама',dial:'+507'},
  {code:'pg',flag:'🇵🇬',name:'Папуа Новая Гвинея',dial:'+675'},
  {code:'py',flag:'🇵🇾',name:'Парагвай',dial:'+595'},
  {code:'pe',flag:'🇵🇪',name:'Перу',dial:'+51'},
  {code:'pl',flag:'🇵🇱',name:'Польша',dial:'+48'},
  {code:'pt',flag:'🇵🇹',name:'Португалия',dial:'+351'},
  {code:'rw',flag:'🇷🇼',name:'Руанда',dial:'+250'},
  {code:'ro',flag:'🇷🇴',name:'Румыния',dial:'+40'},
  {code:'sv',flag:'🇸🇻',name:'Сальвадор',dial:'+503'},
  {code:'ws',flag:'🇼🇸',name:'Самоа',dial:'+685'},
  {code:'sm',flag:'🇸🇲',name:'Сан-Марино',dial:'+378'},
  {code:'st',flag:'🇸🇹',name:'Сан-Томе и Принсипи',dial:'+239'},
  {code:'sa',flag:'🇸🇦',name:'Саудовская Аравия',dial:'+966'},
  {code:'sn',flag:'🇸🇳',name:'Сенегал',dial:'+221'},
  {code:'rs',flag:'🇷🇸',name:'Сербия',dial:'+381'},
  {code:'sg',flag:'🇸🇬',name:'Сингапур',dial:'+65'},
  {code:'sy',flag:'🇸🇾',name:'Сирия',dial:'+963'},
  {code:'sk',flag:'🇸🇰',name:'Словакия',dial:'+421'},
  {code:'si',flag:'🇸🇮',name:'Словения',dial:'+386'},
  {code:'so',flag:'🇸🇴',name:'Сомали',dial:'+252'},
  {code:'sd',flag:'🇸🇩',name:'Судан',dial:'+249'},
  {code:'sr',flag:'🇸🇷',name:'Суринам',dial:'+597'},
  {code:'sl',flag:'🇸🇱',name:'Сьерра-Леоне',dial:'+232'},
  {code:'tj',flag:'🇹🇯',name:'Таджикистан',dial:'+992'},
  {code:'th',flag:'🇹🇭',name:'Таиланд',dial:'+66'},
  {code:'tw',flag:'🇹🇼',name:'Тайвань',dial:'+886'},
  {code:'tz',flag:'🇹🇿',name:'Танзания',dial:'+255'},
  {code:'tg',flag:'🇹🇬',name:'Того',dial:'+228'},
  {code:'to',flag:'🇹🇴',name:'Тонга',dial:'+676'},
  {code:'tt',flag:'🇹🇹',name:'Тринидад и Тобаго',dial:'+1868'},
  {code:'tn',flag:'🇹🇳',name:'Тунис',dial:'+216'},
  {code:'tm',flag:'🇹🇲',name:'Туркменистан',dial:'+993'},
  {code:'tr',flag:'🇹🇷',name:'Турция',dial:'+90'},
  {code:'ug',flag:'🇺🇬',name:'Уганда',dial:'+256'},
  {code:'uz',flag:'🇺🇿',name:'Узбекистан',dial:'+998'},
  {code:'uy',flag:'🇺🇾',name:'Уругвай',dial:'+598'},
  {code:'fj',flag:'🇫🇯',name:'Фиджи',dial:'+679'},
  {code:'ph',flag:'🇵🇭',name:'Филиппины',dial:'+63'},
  {code:'fi',flag:'🇫🇮',name:'Финляндия',dial:'+358'},
  {code:'hr',flag:'🇭🇷',name:'Хорватия',dial:'+385'},
  {code:'cf',flag:'🇨🇫',name:'ЦАР',dial:'+236'},
  {code:'td',flag:'🇹🇩',name:'Чад',dial:'+235'},
  {code:'me',flag:'🇲🇪',name:'Черногория',dial:'+382'},
  {code:'cz',flag:'🇨🇿',name:'Чехия',dial:'+420'},
  {code:'cl',flag:'🇨🇱',name:'Чили',dial:'+56'},
  {code:'ch',flag:'🇨🇭',name:'Швейцария',dial:'+41'},
  {code:'se',flag:'🇸🇪',name:'Швеция',dial:'+46'},
  {code:'lk',flag:'🇱🇰',name:'Шри-Ланка',dial:'+94'},
  {code:'ec',flag:'🇪🇨',name:'Эквадор',dial:'+593'},
  {code:'gq',flag:'🇬🇶',name:'Экваториальная Гвинея',dial:'+240'},
  {code:'er',flag:'🇪🇷',name:'Эритрея',dial:'+291'},
  {code:'sz',flag:'🇸🇿',name:'Эсватини',dial:'+268'},
  {code:'ee',flag:'🇪🇪',name:'Эстония',dial:'+372'},
  {code:'et',flag:'🇪🇹',name:'Эфиопия',dial:'+251'},
  {code:'za',flag:'🇿🇦',name:'ЮАР',dial:'+27'},
  {code:'kr',flag:'🇰🇷',name:'Южная Корея',dial:'+82'},
  {code:'ss',flag:'🇸🇸',name:'Южный Судан',dial:'+211'},
  {code:'jm',flag:'🇯🇲',name:'Ямайка',dial:'+1876'},
  {code:'jp',flag:'🇯🇵',name:'Япония',dial:'+81'},
];
const DIAL_POPULAR_SET = new Set(DIAL_POPULAR);

let _dialSelected = DIAL_ALL.find(c => c.code === 'fr');
let _dialOpen = false;

function renderDialList(query) {
  const list = document.getElementById('dialList');
  const q = query.trim().toLowerCase();
  let items;
  if (q) {
    items = DIAL_ALL.filter(c =>
      c.name.toLowerCase().includes(q) ||
      c.dial.includes(q) ||
      c.dial.replace('+','').startsWith(q.replace('+',''))
    );
    list.innerHTML = items.length
      ? items.map(dialItemHTML).join('')
      : '<div class="dial-empty">Ничего не найдено</div>';
  } else {
    const popular = DIAL_ALL.filter(c => DIAL_POPULAR_SET.has(c.code));
    const rest    = DIAL_ALL.filter(c => !DIAL_POPULAR_SET.has(c.code));
    list.innerHTML =
      '<div class="dial-group-label">Популярные</div>' +
      popular.map(dialItemHTML).join('') +
      '<div class="dial-divider"></div>' +
      rest.map(dialItemHTML).join('');
  }
}
function dialItemHTML(c) {
  const sel = _dialSelected && _dialSelected.code === c.code ? ' selected' : '';
  return `<div class="dial-item${sel}" onclick="selectDialItem('${c.code}')">
    <span class="dial-item-flag">${c.flag}</span>
    <span class="dial-item-name">${c.name}</span>
    <span class="dial-item-code">${c.dial}</span>
  </div>`;
}
function selectDialItem(code) {
  const c = DIAL_ALL.find(x => x.code === code);
  if (!c) return;
  _dialSelected = c;
  document.getElementById('dialSelectedFlag').textContent = c.flag;
  document.getElementById('dialSelectedCode').textContent = c.dial;
  document.getElementById('phoneCountry').value = c.dial;
  document.getElementById('dialSearch').value = '';
  closeDialDropdown();
}
function setPhoneDialCode(dialCode) {
  const c = DIAL_ALL.find(x => x.dial === dialCode);
  if (c) {
    _dialSelected = c;
    document.getElementById('dialSelectedFlag').textContent = c.flag;
    document.getElementById('dialSelectedCode').textContent = c.dial;
  } else {
    document.getElementById('dialSelectedCode').textContent = dialCode;
  }
  document.getElementById('phoneCountry').value = dialCode;
}
function toggleDialDropdown(e) {
  e.stopPropagation();
  _dialOpen ? closeDialDropdown() : openDialDropdown();
}
function openDialDropdown() {
  _dialOpen = true;
  document.getElementById('dialDropdown').classList.add('open');
  document.getElementById('dialTrigger').classList.add('open');
  renderDialList('');
  setTimeout(() => document.getElementById('dialSearch').focus(), 60);
}
function closeDialDropdown() {
  _dialOpen = false;
  document.getElementById('dialDropdown').classList.remove('open');
  document.getElementById('dialTrigger').classList.remove('open');
}
function filterDialList(q) { renderDialList(q); }
document.addEventListener('click', function(e) {
  if (_dialOpen && !document.getElementById('dialPicker').contains(e.target)) closeDialDropdown();
});
</script>
</body>
</html>