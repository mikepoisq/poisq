<?php
// add-service.php — Добавление сервиса Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// 🔧 ПРОВЕРКА АВТОРИЗАЦИИ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$userName = $_SESSION['user_name'] ?? 'Пользователь';
$userEmail = $_SESSION['user_email'] ?? '';
$userAvatar = $_SESSION['user_avatar'] ?? '';
$userInitial = strtoupper(substr($userName, 0, 1));

// 🔧 ОПРЕДЕЛЕНИЕ СТРАНЫ ПО IP
function getCountryByIP() {
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost', ''])) {
        return ['code' => 'fr', 'name' => 'Франция'];
    }
    $cacheFile = sys_get_temp_dir() . '/poisq_geo_' . md5($ip);
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }
    $apiUrl = 'https://ipwhois.app/json/' . urlencode($ip);
    $context = stream_context_create(['http' => ['timeout' => 3, 'user_agent' => 'Poisq/1.0']]);
    $response = @file_get_contents($apiUrl, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['country_code'])) {
            $code = strtolower($data['country_code']);
            $name = getCountryName($code);
            $result = ['code' => $code, 'name' => $name];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
        }
    }
    return ['code' => 'fr', 'name' => 'Франция'];
}

function getCountryName($code) {
    $map = [
        'fr' => 'Франция', 'de' => 'Германия', 'es' => 'Испания', 'it' => 'Италия',
        'gb' => 'Великобритания', 'us' => 'США', 'ca' => 'Канада', 'au' => 'Австралия',
        'ru' => 'Россия', 'ua' => 'Украина', 'by' => 'Беларусь', 'kz' => 'Казахстан',
        'nl' => 'Нидерланды', 'be' => 'Бельгия', 'ch' => 'Швейцария', 'at' => 'Австрия',
        'pt' => 'Португалия', 'gr' => 'Греция', 'pl' => 'Польша', 'cz' => 'Чехия',
        'se' => 'Швеция', 'no' => 'Норвегия', 'dk' => 'Дания', 'fi' => 'Финляндия',
        'ie' => 'Ирландия', 'nz' => 'Новая Зеландия', 'ae' => 'ОАЭ', 'il' => 'Израиль',
        'tr' => 'Турция', 'th' => 'Таиланд', 'jp' => 'Япония', 'kr' => 'Южная Корея',
        'sg' => 'Сингапур', 'hk' => 'Гонконг', 'mx' => 'Мексика', 'br' => 'Бразилия',
        'ar' => 'Аргентина', 'cl' => 'Чили', 'co' => 'Колумбия', 'za' => 'ЮАР'
    ];
    return $map[$code] ?? $code;
}

$detectedCountry = getCountryByIP();
$error = '';
$formData = [];

// 🔧 КАТЕГОРИИ И ПОДКАТЕГОРИИ — загружаем из БД
$categories = [];
try {
    $pdo = getDbConnection();
    $dbCats = $pdo->query("SELECT slug, name, icon FROM service_categories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $dbSubs = $pdo->query("SELECT category_slug, name FROM service_subcategories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $subMap = [];
    foreach ($dbSubs as $s) $subMap[$s['category_slug']][] = $s['name'];
    foreach ($dbCats as $c) {
        $categories[$c['slug']] = [
            'name'          => $c['name'],
            'subcategories' => $subMap[$c['slug']] ?? [],
        ];
    }
} catch (Exception $e) {
    error_log('Categories DB error: ' . $e->getMessage());
    // Fallback: базовый набор если БД недоступна
    $categories = [
        'health'     => ['name' => '🏥 Здоровье и красота',       'subcategories' => []],
        'legal'      => ['name' => '⚖️ Юридические услуги',         'subcategories' => []],
        'messengers' => ['name' => '💬 Группы ВатсАп и Телеграм',  'subcategories' => []],
    ];
}

// 🔧 СПИСОК СТРАН — загружаем из БД
$countries = [];
try {
    $pdo = getDbConnection();
    $dbRows = $pdo->query("SELECT code, name_ru FROM countries WHERE is_active=1 ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbRows as $r) {
        $countries[] = ['code' => $r['code'], 'name' => $r['name_ru'], 'flag' => ''];
    }
} catch (Exception $e) {
    error_log('Countries DB error: ' . $e->getMessage());
}
// Fallback если БД недоступна
if (empty($countries)) {
    $countries = [
        ['code'=>'fr','name'=>'Франция','flag'=>''],['code'=>'de','name'=>'Германия','flag'=>''],
        ['code'=>'es','name'=>'Испания','flag'=>''],['code'=>'gb','name'=>'Великобритания','flag'=>''],
        ['code'=>'us','name'=>'США','flag'=>''],['code'=>'ru','name'=>'Россия','flag'=>''],
        ['code'=>'ua','name'=>'Украина','flag'=>''],['code'=>'tr','name'=>'Турция','flag'=>''],
    ];
}

// 🔧 ОБРАБОТКА ФОРМЫ (POST-запрос)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🔧 ОПРЕДЕЛЯЕМ ДЕЙСТВИЕ ЧЕРЕЗ СКРЫТЫЙ INPUT
    $action = $_POST['action'] ?? 'draft';

    // Если пользователь ввёл свой город — сохраняем его в БД и получаем ID
    $customCityName = trim($_POST['custom_city'] ?? '');
    // city_id приходит только из select (hidden input теперь называется custom_city_id)
    $cityId = intval($_POST['city_id'] ?? 0);

    if (!empty($customCityName) && $cityId == 0) {
        try {
            $pdo = getDbConnection();
            // Проверяем не существует ли уже такой город
            $checkStmt = $pdo->prepare("SELECT id FROM cities WHERE name = ? AND country_code = ?");
            $checkStmt->execute([$customCityName, trim($_POST['country'] ?? '')]);
            $existingCity = $checkStmt->fetch();
            if ($existingCity) {
                $cityId = $existingCity['id'];
            } else {
                // Добавляем новый город со статусом pending
                $insertStmt = $pdo->prepare("INSERT INTO cities (name, country_code, is_capital, sort_order, status) VALUES (?, ?, 0, 999, 'pending')");
                $insertStmt->execute([$customCityName, trim($_POST['country'] ?? '')]);
                $cityId = $pdo->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log('Custom city error: ' . $e->getMessage());
        }
    }

    $formData = [
        'country' => trim($_POST['country'] ?? ''),
        'city_id' => $cityId,
        'category' => trim($_POST['category'] ?? ''),
        'subcategory' => trim($_POST['subcategory'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'phone' => (function() {
            $num = trim($_POST['phone'] ?? '');
            $dial = trim($_POST['phone_country'] ?? '');
            if ($num === '') return '';
            return (str_starts_with($num, '+')) ? $num : $dial . $num;
        })(),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'email' => $userEmail,
        'website' => trim($_POST['website'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'status' => $action === 'publish' ? 'pending' : 'draft',
        'services' => $_POST['services'] ?? [],
        'hours' => $_POST['hours'] ?? [],
        'languages' => $_POST['languages'] ?? [],
        'social' => [
            'instagram' => trim($_POST['instagram'] ?? ''),
            'facebook' => trim($_POST['facebook'] ?? ''),
            'vk' => trim($_POST['vk'] ?? ''),
            'telegram' => trim($_POST['telegram'] ?? '')
        ],
        'group_link' => trim($_POST['group_link'] ?? '')
    ];

    $isMessengers = ($formData['category'] === 'messengers');

    // Валидация
    $errors = [];
    if (empty($formData['country'])) $errors[] = 'Выберите страну';
    // Город обязателен — либо из списка, либо свой
    if (empty($formData['city_id']) && empty($customCityName)) $errors[] = 'Выберите или введите город';
    if (empty($formData['category'])) $errors[] = 'Выберите категорию';
    if (empty($formData['subcategory'])) $errors[] = 'Выберите подкатегорию';
    if (empty($formData['name']) || strlen($formData['name']) < 3) $errors[] = 'Название должно быть не менее 3 символов';
    if (empty($formData['description']) || strlen($formData['description']) < 100) $errors[] = 'Описание должно быть не менее 100 символов';
    if (!$isMessengers && empty($formData['phone'])) $errors[] = 'Введите телефон';
    if (!$isMessengers && empty($formData['address'])) $errors[] = 'Введите адрес';
    if (!$isMessengers && (empty($formData['services']) || !is_array($formData['services']))) $errors[] = 'Добавьте хотя бы одну услугу';
    if ($isMessengers && empty($formData['group_link'])) $errors[] = 'Введите ссылку на группу';
    if (!isset($_POST['agreement'])) $errors[] = 'Необходимо согласие с условиями';
    
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            
            // 🔧 1. ОБРАБОТКА ФОТО
            $photoPaths = [];
            if (!empty($_FILES['photos']['name'][0])) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                
                $count = min(count($_FILES['photos']['name']), 5);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['photos']['tmp_name'][$i];
                        $fileName = uniqid('photo_') . '.jpg';
                        $targetPath = $uploadDir . $fileName;
                        
                        $imgInfo = getimagesize($tmpName);
                        if ($imgInfo && in_array($imgInfo['mime'], ['image/jpeg','image/png','image/webp'])) {
                            $src = imagecreatefromstring(file_get_contents($tmpName));
                            $width = imagesx($src); $height = imagesy($src);
                            $maxWidth = 800;
                            if ($width > $maxWidth) {
                                $ratio = $maxWidth / $width;
                                $newHeight = $height * $ratio;
                                $dst = imagecreatetruecolor($maxWidth, $newHeight);
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
                                imagejpeg($dst, $targetPath, 85);
                                imagedestroy($dst); imagedestroy($src);
                            } else {
                                imagejpeg($src, $targetPath, 85);
                                imagedestroy($src);
                            }
                            $photoPaths[] = '/uploads/' . $fileName;
                        }
                    }
                }
            }
            
            // 🔧 2. ПОДГОТОВКА JSON-полей
            $hoursJson = json_encode($formData['hours'], JSON_UNESCAPED_UNICODE);
            $languagesJson = json_encode($formData['languages'], JSON_UNESCAPED_UNICODE);
            $servicesJson = json_encode($formData['services'], JSON_UNESCAPED_UNICODE);
            $socialJson = json_encode($formData['social'], JSON_UNESCAPED_UNICODE);
            
            // 🔧 3. INSERT в таблицу services
            $stmt = $pdo->prepare("
                INSERT INTO services (
                    user_id, name, category, subcategory, city_id, country_code,
                    description, photo, phone, whatsapp, email, website, address,
                    hours, languages, services, social, status, group_link, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $formData['name'],
                $formData['category'],
                $formData['subcategory'],
                $formData['city_id'] > 0 ? $formData['city_id'] : null,
                $formData['country'],
                $formData['description'],
                $photoPaths ? json_encode($photoPaths, JSON_UNESCAPED_UNICODE) : null,
                $formData['phone'],
                $formData['whatsapp'],
                $formData['email'],
                $formData['website'],
                $isMessengers ? null : $formData['address'],
                $isMessengers ? null : $hoursJson,
                $languagesJson,
                $isMessengers ? null : $servicesJson,
                $socialJson,
                $formData['status'],
                $isMessengers ? $formData['group_link'] : null
            ]);
            
            // 🔧 4. РАЗНЫЕ СООБЩЕНИЯ ДЛЯ ЧЕРНОВИКА И ПУБЛИКАЦИИ
            if ($action === 'publish') {
                $successMessage = 'Отправлено на модерацию. 48ч максимум';
                // Отправляем уведомление админу
                try {
                    $newServiceId = $pdo->lastInsertId();
                    require_once __DIR__ . '/config/email.php';
                    $cityNameForEmail = '';
                    if (!empty($formData['city_id'])) {
                        $cs = $pdo->prepare("SELECT name FROM cities WHERE id = ? LIMIT 1");
                        $cs->execute([$formData['city_id']]);
                        $cityNameForEmail = $cs->fetchColumn() ?: '—';
                    }
                    sendAdminModerationEmail(
                        $newServiceId,
                        $formData['name'],
                        $formData['category'] ?? '—',
                        $cityNameForEmail ?: '—',
                        $userName,
                        $userEmail
                    );
                } catch (Exception $e) {
                    error_log('Add service moderation email error: ' . $e->getMessage());
                }
                // ── ПРОВЕРКА ДУБЛЕЙ ──────────────────────────────────
                $duplicateId = null;
                if (!empty($formData['phone'])) {
                    $d = $pdo->prepare("SELECT id FROM services WHERE phone = ? AND id != ? AND status IN ('approved','pending') LIMIT 1");
                    $d->execute([$formData['phone'], $newServiceId]);
                    if ($row = $d->fetch()) $duplicateId = $row['id'];
                }

                if (!$duplicateId && !empty($formData['name']) && !empty($formData['city_id'])) {
                    $nameLike = '%' . mb_substr(trim($formData['name']), 0, 15) . '%';
                    $d = $pdo->prepare("SELECT id FROM services WHERE name LIKE ? AND city_id = ? AND id != ? AND status IN ('approved','pending') LIMIT 1");
                    $d->execute([$nameLike, $formData['city_id'], $newServiceId]);
                    if ($row = $d->fetch()) $duplicateId = $row['id'];
                }
                if ($duplicateId) {
                    $pdo->prepare("UPDATE services SET status = 'duplicate', duplicate_of = ? WHERE id = ?")->execute([$duplicateId, $newServiceId]);
                }
            } else {
                $successMessage = 'Черновик сохранён! Вы можете вернуться и отредактировать позже.';
            }
            
            header('Location: profile.php?success=' . urlencode($successMessage));
            exit;
            
        } catch (PDOException $e) {
            error_log('Add Service DB Error: ' . $e->getMessage());
            $error = 'Ошибка сохранения. Попробуйте позже.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Добавить сервис — Poisq</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Poisq">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
--primary: #2E73D8; --primary-light: #5EA1F0; --primary-dark: #1A5AB8;
--text: #1F2937; --text-secondary: #9CA3AF; --text-light: #6B7280;
--bg: #FFFFFF; --bg-secondary: #F5F5F7; --border: #D1D5DB; --border-light: #E5E7EB;
--success: #10B981; --warning: #F59E0B; --danger: #EF4444;
--shadow-sm: 0 2px 8px rgba(0,0,0,0.06); --shadow-md: 0 4px 16px rgba(46,115,216,0.15);
}
html { -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto; }
body {
-webkit-overflow-scrolling: touch; overflow-y: auto; height: auto;
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
background: var(--bg-secondary); color: var(--text); line-height: 1.5;
-webkit-font-smoothing: antialiased; touch-action: manipulation;
}
.app-container {
max-width: 430px; margin: 0 auto; background: var(--bg);
min-height: 100vh; min-height: 100dvh;
position: relative; display: flex; flex-direction: column;
}
.header {
display: flex; align-items: center; justify-content: space-between;
padding: 10px 14px; background: var(--bg);
border-bottom: 1px solid var(--border-light); flex-shrink: 0; height: 56px;
}
.header-left { display: flex; align-items: center; gap: 12px; }
.header-right { display: flex; align-items: center; }
.btn-back {
width: 40px; height: 40px; border-radius: 12px; border: none;
background: var(--bg-secondary); color: var(--text);
display: flex; align-items: center; justify-content: center;
cursor: pointer; padding: 0; text-decoration: none;
}
.btn-back:active { transform: scale(0.95); background: var(--border); }
.btn-back svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }
.header-title { font-size: 17px; font-weight: 600; color: var(--text); }
.btn-burger {
width: 40px; height: 40px; display: flex; flex-direction: column;
justify-content: center; align-items: center; gap: 5px;
padding: 8px; cursor: pointer; background: none; border: none; border-radius: 12px;
}
.btn-burger span { display: block; width: 22px; height: 2.5px; background: #6B7280; border-radius: 2px; transition: all 0.2s ease; }
.btn-burger:active { background: var(--primary); }
.btn-burger:active span { background: white; }
.btn-burger.active span:nth-child(1) { transform: translateY(7.5px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7.5px) rotate(-45deg); }
.breadcrumbs {
padding: 12px 16px;
background: var(--bg-secondary);
border-bottom: 1px solid var(--border-light);
}
.breadcrumb-item {
font-size: 13px;
color: var(--text-secondary);
text-decoration: none;
}
.breadcrumb-item:hover { color: var(--primary); }
.breadcrumb-separator { margin: 0 8px; color: var(--text-light); }
.breadcrumb-current { font-size: 13px; color: var(--text); font-weight: 500; }
.form-container {
flex: 1;
padding: 20px 16px 100px;
}
.alert {
padding: 14px 16px;
border-radius: 12px;
font-size: 14px;
margin-bottom: 16px;
display: flex;
align-items: center;
gap: 10px;
}
.alert-error { background: #FEF2F2; color: var(--danger); border: 1px solid #FECACA; }
.alert-success { background: #F0FDF4; color: var(--success); border: 1px solid #BBF7D0; }
.alert svg { width: 20px; height: 20px; flex-shrink: 0; }
.form-section {
background: var(--bg);
border-radius: 16px;
margin-bottom: 16px;
overflow: hidden;
}
.section-header {
padding: 16px;
background: var(--bg-secondary);
border-bottom: 1px solid var(--border-light);
}
.section-title {
font-size: 15px;
font-weight: 600;
color: var(--text);
}
.section-content {
padding: 16px;
}
.form-group {
margin-bottom: 16px;
}
.form-group:last-child {
margin-bottom: 0;
}
.form-label {
display: block;
font-size: 14px;
font-weight: 500;
color: var(--text);
margin-bottom: 6px;
}
.form-label .required {
color: var(--danger);
}
.form-input, .form-select, .form-textarea {
width: 100%;
padding: 12px 14px;
border: 1px solid var(--border);
border-radius: 12px;
font-size: 15px;
color: var(--text);
background: var(--bg);
outline: none;
transition: all 0.2s ease;
-webkit-appearance: none;
-moz-appearance: none;
appearance: none;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
border-color: var(--primary);
box-shadow: 0 0 0 3px rgba(46,115,216,0.1);
}
.form-input.error, .form-select.error, .form-textarea.error {
border-color: var(--danger);
}
.form-input::placeholder, .form-textarea::placeholder {
color: var(--text-secondary);
}
.form-textarea {
min-height: 120px;
resize: vertical;
}
.form-select {
cursor: pointer;
background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
background-repeat: no-repeat;
background-position: right 12px center;
padding-right: 40px;
}
.form-hint {
font-size: 12px;
color: var(--text-secondary);
margin-top: 4px;
}
.form-error {
font-size: 12px;
color: var(--danger);
margin-top: 4px;
}
.country-selector, .city-selector {
display: flex;
align-items: center;
gap: 12px;
padding: 12px 14px;
border: 1px solid var(--border);
border-radius: 12px;
cursor: pointer;
background: var(--bg);
transition: all 0.2s ease;
}
.country-selector:focus-within, .city-selector:focus-within {
border-color: var(--primary);
box-shadow: 0 0 0 3px rgba(46,115,216,0.1);
}
.country-selector.error, .city-selector.error {
border-color: var(--danger);
}
.country-flag, .city-flag {
width: 28px;
height: 20px;
border-radius: 4px;
overflow: hidden;
box-shadow: 0 1px 3px rgba(0,0,0,0.15);
flex-shrink: 0;
display: flex;
align-items: center;
justify-content: center;
font-size: 18px;
}
.country-flag img, .city-flag img {
width: 100%; height: 100%; object-fit: cover; display: block;
}
.country-name, .city-name {
flex: 1;
font-size: 15px;
color: var(--text);
font-weight: 500;
}
.country-arrow, .city-arrow {
width: 20px;
height: 20px;
fill: var(--text-secondary);
}
.country-modal {
position: fixed;
top: 0; left: 0; right: 0; bottom: 0;
background: rgba(0,0,0,0.5);
z-index: 500;
display: none;
align-items: flex-start;
justify-content: center;
}
.country-modal.active { display: flex; }
.country-modal-content {
background: white;
width: 100%;
max-width: 430px;
max-height: 90vh;
border-radius: 0 0 24px 24px;
overflow: hidden;
display: flex;
flex-direction: column;
transform: translateZ(0);
-webkit-transform: translateZ(0);
animation: slideDown 0.25s ease-out;
}
@keyframes slideDown {
from { transform: translateY(-100%); }
to { transform: translateY(0); }
}
.country-modal-header {
padding: 16px 20px;
border-bottom: 1px solid var(--border-light);
display: flex;
justify-content: space-between;
align-items: center;
flex-shrink: 0;
position: sticky;
top: 0;
background: white;
z-index: 10;
}
.country-modal-title {
font-size: 18px;
font-weight: 700;
color: var(--text);
}
.country-modal-close {
width: 32px;
height: 32px;
border-radius: 50%;
border: none;
background: var(--bg-secondary);
cursor: pointer;
font-size: 20px;
color: var(--text-light);
}
.country-search {
padding: 12px 20px;
border-bottom: 1px solid var(--border-light);
flex-shrink: 0;
position: sticky;
top: 0;
background: white;
z-index: 5;
}
.country-search-input {
width: 100%;
padding: 10px 14px;
border: 1px solid var(--border);
border-radius: 12px;
font-size: 16px;
outline: none;
}
.country-search-input:focus {
border-color: var(--primary);
}
.country-list {
overflow-y: auto;
padding: 4px 0;
flex: 1;
-webkit-overflow-scrolling: touch;
}
.country-item {
display: flex;
align-items: center;
gap: 14px;
padding: 10px 20px;
cursor: pointer;
transition: background 0.15s ease;
}
.country-item:active {
background: var(--bg-secondary);
}
.country-item-flag {
width: 32px;
height: 24px;
border-radius: 4px;
overflow: hidden;
flex-shrink: 0;
display: flex;
align-items: center;
justify-content: center;
font-size: 20px;
}
.country-item-flag img {
width: 100%; height: 100%; object-fit: cover; display: block;
}
.country-item-name {
font-size: 15px;
color: var(--text);
flex: 1;
}
.country-item-check {
width: 20px;
height: 20px;
stroke: var(--primary);
fill: none;
stroke-width: 2;
opacity: 0;
}
.country-item.selected .country-item-check {
opacity: 1;
}
/* ── Dial code picker ── */
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
.services-list {
margin-bottom: 12px;
}
.service-row {
display: flex;
gap: 8px;
margin-bottom: 8px;
align-items: center;
}
.service-row input {
flex: 1;
padding: 10px 12px;
border: 1px solid var(--border);
border-radius: 8px;
font-size: 14px;
}
.service-row input[type="number"] {
width: 100px;
flex: none;
}
.btn-remove-service {
width: 36px;
height: 36px;
border-radius: 8px;
border: none;
background: #FEE2E2;
color: var(--danger);
cursor: pointer;
display: flex;
align-items: center;
justify-content: center;
}
.btn-remove-service:active { background: #FECACA; }
.btn-add-service {
width: 100%;
padding: 12px;
background: var(--bg-secondary);
border: 2px dashed var(--border);
border-radius: 12px;
font-size: 14px;
font-weight: 500;
color: var(--text-secondary);
cursor: pointer;
display: flex;
align-items: center;
justify-content: center;
gap: 8px;
}
.btn-add-service:active { background: var(--border-light); border-color: var(--primary); color: var(--primary); }
.languages-grid {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 8px;
}
.language-checkbox {
display: flex;
align-items: center;
gap: 8px;
padding: 10px 12px;
border: 1px solid var(--border);
border-radius: 8px;
cursor: pointer;
font-size: 14px;
}
.language-checkbox:active { background: var(--bg-secondary); }
.language-checkbox input {
width: 18px;
height: 18px;
accent-color: var(--primary);
}
.photo-upload {
border: 2px dashed var(--border);
border-radius: 12px;
padding: 20px;
text-align: center;
cursor: pointer;
transition: all 0.2s ease;
}
.photo-upload:active { border-color: var(--primary); background: #E8F0FE; }
.photo-upload-icon {
width: 48px;
height: 48px;
margin: 0 auto 12px;
stroke: var(--text-light);
}
.photo-upload-text {
font-size: 14px;
color: var(--text-secondary);
}
.photo-upload-hint {
font-size: 12px;
color: var(--text-light);
margin-top: 4px;
}
.photo-preview {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 8px;
margin-top: 16px;
}
.photo-item {
position: relative;
aspect-ratio: 1;
border-radius: 8px;
overflow: hidden;
background: var(--bg-secondary);
}
.photo-item img {
width: 100%;
height: 100%;
object-fit: cover;
}
.photo-item-main {
border: 2px solid var(--primary);
}
.photo-item-badge {
position: absolute;
top: 4px;
left: 4px;
background: var(--primary);
color: white;
font-size: 10px;
font-weight: 600;
padding: 2px 6px;
border-radius: 4px;
}
.photo-item-remove {
position: absolute;
top: 4px;
right: 4px;
width: 24px;
height: 24px;
border-radius: 50%;
background: rgba(0,0,0,0.6);
color: white;
border: none;
cursor: pointer;
display: flex;
align-items: center;
justify-content: center;
}
.photo-item-remove:active { background: var(--danger); }
.social-grid {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 12px;
}
.social-input-wrapper {
position: relative;
}
.social-input-wrapper svg {
position: absolute;
left: 12px;
top: 50%;
transform: translateY(-50%);
width: 18px;
height: 18px;
stroke: var(--text-light);
}
.social-input-wrapper input {
padding-left: 40px;
}
.checkbox-group {
display: flex;
align-items: flex-start;
gap: 10px;
padding: 12px;
background: var(--bg-secondary);
border-radius: 12px;
margin-top: 16px;
}
.checkbox-group input {
width: 18px;
height: 18px;
margin-top: 2px;
accent-color: var(--primary);
}
.checkbox-group label {
font-size: 13px;
color: var(--text);
line-height: 1.5;
}
.checkbox-group a {
color: var(--primary);
text-decoration: none;
}
.form-actions {
position: fixed;
bottom: 0;
left: 0;
right: 0;
background: var(--bg);
border-top: 1px solid var(--border-light);
padding: 12px 16px;
z-index: 100;
display: flex;
gap: 8px;
max-width: 430px;
margin: 0 auto;
}
.btn {
flex: 1;
padding: 14px 20px;
border-radius: 12px;
border: none;
font-size: 15px;
font-weight: 600;
cursor: pointer;
transition: all 0.15s ease;
}
.btn:active { transform: scale(0.98); }
.btn-secondary {
background: var(--bg-secondary);
color: var(--text);
}
.btn-secondary:active { background: var(--border); }
.btn-primary {
background: var(--primary);
color: white;
}
.btn-primary:active { background: var(--primary-dark); }
@media (max-width: 380px) {
.header-title { font-size: 16px; }
.form-input, .form-select { font-size: 14px; padding: 10px 12px; }
.languages-grid { grid-template-columns: 1fr; }
.social-grid { grid-template-columns: 1fr; }
}
::-webkit-scrollbar { display: none; }

@media (min-width: 1024px) {
  .app-container {
    max-width: 800px;
    padding-top: 64px;
  }
  .header { display: none; }

  .breadcrumbs { padding: 14px 24px; }

  .form-container { padding: 24px 24px 100px; }

  .section-header { padding: 16px 20px; }
  .section-content { padding: 20px; }
  .form-section { border-radius: 16px; }

  .form-actions {
    max-width: 800px;
    padding: 14px 24px;
  }

  .languages-grid { grid-template-columns: repeat(4, 1fr); }
  .social-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/desktop.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">
<header class="header">
<div class="header-left">
<button onclick="history.back()" class="btn-back" aria-label="Назад">
<svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
</button>
<span class="header-title">Добавить сервис</span>
</div>
<div class="header-right">
<button class="btn-burger" id="menuToggle" aria-label="Меню">
<span></span><span></span><span></span>
</button>
</div>
</header>
<?php include __DIR__ . '/includes/menu.php'; ?>
<nav class="breadcrumbs">
<a href="profile.php" class="breadcrumb-item">Личный кабинет</a>
<span class="breadcrumb-separator">/</span>
<span class="breadcrumb-current">Добавить сервис</span>
</nav>
<main class="form-container">
<?php if ($error): ?>
<div class="alert alert-error">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
</svg>
<div><?php echo $error; ?></div>
</div>
<div class="alert" style="background:#FFF7ED; color:#92400E; border:1px solid #FCD34D; font-size:13px;">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;">
<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
</svg>
<div>⚠️ Фотографии не сохраняются при ошибке — пожалуйста, загрузите их снова</div>
</div>
<?php endif; ?>
<form method="POST" action="add-service.php" id="serviceForm" enctype="multipart/form-data">
<!-- 🔧 СКРЫТЫЙ INPUT ДЛЯ ДЕЙСТВИЯ -->
<input type="hidden" name="action" id="formAction" value="draft">

<div class="form-section">
<div class="section-header">
<h2 class="section-title">📋 Категория</h2>
</div>
<div class="section-content">
<div class="form-group">
<label class="form-label">Категория <span class="required">*</span></label>
<select class="form-select" id="category" name="category" required onchange="updateSubcategories()">
<option value="">Выберите категорию</option>
<?php foreach ($categories as $key => $cat): ?>
<option value="<?php echo $key; ?>"><?php echo $cat['name']; ?></option>
<?php endforeach; ?>
</select>
<div class="form-error" id="categoryError"></div>
</div>
<div class="form-group">
<label class="form-label">Подкатегория <span class="required">*</span></label>
<select class="form-select" id="subcategory" name="subcategory" required disabled>
<option value="">Сначала выберите категорию</option>
</select>
<div class="form-error" id="subcategoryError"></div>
</div>

<!-- Поле ссылки на группу — только для категории messengers -->
<div class="form-group" id="groupLinkBlock" style="display:none;">
<label class="form-label">Ссылка на группу <span class="required">*</span></label>
<div style="position:relative;">
<svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:18px;height:18px;stroke:var(--text-light);fill:none;stroke-width:2;" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
<input type="url" class="form-input" id="groupLink" name="group_link"
  style="padding-left:40px;"
  placeholder="https://t.me/... или https://chat.whatsapp.com/..."
  value="<?php echo htmlspecialchars($formData['group_link'] ?? ''); ?>">
</div>
<div class="form-hint">Пригласительная ссылка на вашу группу</div>
<div class="form-error" id="groupLinkError"></div>
</div>
</div>
</div>
<div class="form-section">
<div class="section-header">
<h2 class="section-title">ℹ️ Основная информация</h2>
</div>
<div class="section-content">
<div class="form-group">
<label class="form-label" id="nameLabelText">Название сервиса <span class="required">*</span></label>
<input type="text" class="form-input" id="name" name="name"
placeholder="Например: Доктор Петрова Анна"
value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
required minlength="3" maxlength="100">
<div class="form-hint">Минимум 3 символа</div>
<div class="form-error" id="nameError"></div>
</div>
<div class="form-group">
<label class="form-label">Описание <span class="required">*</span></label>
<textarea class="form-textarea" id="description" name="description"
placeholder="Расскажите о вашем сервисе подробно..."
required minlength="100" maxlength="2000"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
<div class="form-hint"><span id="descCount">0</span>/100 мин. символов</div>
<div class="form-error" id="descriptionError"></div>
</div>
</div>
</div>
<div class="form-section">
<div class="section-header">
<h2 class="section-title">📍 Страна, город и контакты</h2>
</div>
<div class="section-content">
<div class="form-group">
<label class="form-label">Страна <span class="required">*</span></label>
<div class="country-selector" id="countrySelector" onclick="openCountryModal()">
<div class="country-flag" id="selectedCountryFlag"><img src="https://flagcdn.com/w80/fr.png" alt="Франция" style="width:100%;height:100%;object-fit:cover;display:block"></div>
<span class="country-name" id="selectedCountryName">Франция</span>
<svg class="country-arrow" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
<input type="hidden" id="country" name="country" value="fr">
</div>
<div class="form-hint">Где находится ваш сервис</div>
<div class="form-error" id="countryError"></div>
</div>
<div class="form-group">
<label class="form-label">Город <span class="required">*</span></label>
<select class="form-select" id="citySelect" name="city_id" required disabled>
<option value="">Сначала выберите страну</option>
</select>
<div class="form-hint">Город где находится сервис</div>
<div class="form-error" id="cityError"></div>

<!-- Кнопка "мой город не в списке" -->
<div id="customCityToggle" style="display:none; margin-top:10px;">
  <button type="button" onclick="toggleCustomCity()" id="btnCustomCity"
    style="width:100%; padding:11px 16px; border-radius:12px; border:2px dashed var(--primary);
    background:var(--primary-light); color:var(--primary); font-size:14px; font-weight:600;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Моего города нет в списке
  </button>
</div>

<!-- Поле для ввода своего города (скрыто по умолчанию) -->
<div id="customCityBlock" style="display:none; margin-top:10px;">
  <div style="background:var(--primary); border-radius:12px; padding:12px; margin-bottom:10px;">
    <p style="font-size:13px; color:#ffffff; font-weight:600; margin-bottom:4px;">📍 Укажите ваш город</p>
    <p style="font-size:12px; color:rgba(255,255,255,0.85);">Город будет добавлен после проверки и появится в списке для других пользователей</p>
  </div>
  <input type="text" class="form-input" id="customCityInput" name="custom_city"
    placeholder="Введите название города" autocomplete="off">
  <input type="hidden" id="customCityHidden" name="custom_city_id" value="0">
  <button type="button" onclick="cancelCustomCity()"
    style="margin-top:8px; background:none; border:none; color:var(--text-secondary);
    font-size:13px; cursor:pointer; padding:4px 0;">
    ← Вернуться к списку городов
  </button>
</div>
</div>
<div id="phoneEmailBlock">
<div class="form-group">
<label class="form-label">Телефон <span class="required">*</span></label>
<div style="display: flex; gap: 8px;">
<div class="dial-picker" id="dialPicker">
  <button type="button" class="dial-trigger" id="dialTrigger" onclick="toggleDialDropdown(event)">
    <span class="dial-trigger-flag" id="dialSelectedFlag">🇫🇷</span>
    <span class="dial-trigger-code" id="dialSelectedCode">+33</span>
    <svg class="dial-trigger-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
  </button>
  <div class="dial-dropdown" id="dialDropdown">
    <div class="dial-search-wrap">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" class="dial-search-input" id="dialSearch" placeholder="Страна или +код…" oninput="filterDialList(this.value)" autocomplete="off" autocorrect="off" autocapitalize="off">
    </div>
    <div class="dial-list" id="dialList"></div>
  </div>
  <input type="hidden" name="phone_country" id="phoneCountry" value="+33">
</div>
<input type="tel" class="form-input" id="phone" name="phone"
value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
placeholder="6 12 34 56 78" required>
</div>
<div class="form-error" id="phoneError"></div>
</div>
<div class="form-group">
<label class="form-label">WhatsApp 📲 <span style="color:#10B981;font-weight:600;font-size:12px;">Рекомендуем — больше просмотров!</span></label>
<div style="position:relative;">
<input type="tel" class="form-input" id="whatsapp" name="whatsapp"
value="<?php echo htmlspecialchars($formData['whatsapp'] ?? ''); ?>"
placeholder="Тот же номер или другой"
style="padding-right:110px;">
<button type="button"
onclick="var dial=document.getElementById('phoneCountry').value||'';var num=document.getElementById('phone').value||'';document.getElementById('whatsapp').value=dial+num;"
style="position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:12px;color:#2E73D8;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:4px 10px;cursor:pointer;white-space:nowrap;font-family:inherit;">
☎️ Тот же
</button>
</div>
</div>

</div><!-- /phoneEmailBlock -->
<div class="form-group">
<label class="form-label">Веб-сайт (опционально)</label>
<input type="url" class="form-input" id="website" name="website"
value="<?php echo htmlspecialchars($formData['website'] ?? ''); ?>"
placeholder="https://example.com">
</div>
<div class="form-group" id="addressBlock">
<label class="form-label">Адрес <span class="required">*</span></label>
<input type="text" class="form-input" id="address" name="address"
value="<?php echo htmlspecialchars($formData['address'] ?? ''); ?>"
placeholder="Улица, дом, город, страна" required>
<div class="form-error" id="addressError"></div>
</div>
</div>
</div>
<div class="form-section" id="hoursSection">
<div class="section-header">
<h2 class="section-title">🕐 Часы работы</h2>
</div>
<div class="section-content">
<div id="hoursContainer">
<?php
$days = ['mon' => 'Понедельник', 'tue' => 'Вторник', 'wed' => 'Среда', 'thu' => 'Четверг', 'fri' => 'Пятница', 'sat' => 'Суббота', 'sun' => 'Воскресенье'];
foreach ($days as $key => $name):
?>
<div class="hours-row" data-day="<?php echo $key; ?>">
<div class="hours-day"><?php echo $name; ?></div>
<div class="hours-main-row">
  <div class="hours-time">
    <input type="time" name="hours[<?php echo $key; ?>][open]" class="hours-open">
    <span>—</span>
    <input type="time" name="hours[<?php echo $key; ?>][close]" class="hours-close">
  </div>
  <div class="hours-flags">
    <label class="hours-flag-btn" title="Круглосуточно">
      <input type="checkbox" class="hours-24h-checkbox" onchange="toggle24h(this)">
      <span>24ч</span>
    </label>
    <label class="hours-closed">
      <input type="checkbox" class="hours-closed-checkbox" onchange="toggleHoursRow(this)">
      Вых.
    </label>
  </div>
</div>
<div class="hours-break-row" style="display:none;">
  <span class="hours-break-label">Перерыв:</span>
  <div class="hours-time">
    <input type="time" name="hours[<?php echo $key; ?>][break_start]" class="hours-break-start">
    <span>—</span>
    <input type="time" name="hours[<?php echo $key; ?>][break_end]" class="hours-break-end">
  </div>
  <button type="button" class="hours-break-remove" onclick="removeBreak(this)" title="Убрать перерыв">✕</button>
</div>
<button type="button" class="btn-add-break" onclick="addBreak(this)">+ перерыв</button>
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
<div class="form-section">
<div class="section-header">
<h2 class="section-title">🗣 Языки</h2>
</div>
<div class="section-content">
<div class="languages-grid">
<label class="language-checkbox">
<input type="checkbox" name="languages[]" value="ru" checked>
🇷🇺 Русский
</label>
<label class="language-checkbox">
<input type="checkbox" name="languages[]" value="fr">
🇫🇷 Français
</label>
<label class="language-checkbox">
<input type="checkbox" name="languages[]" value="en">
🇬🇧 English
</label>
<label class="language-checkbox">
<input type="checkbox" name="languages[]" value="de">
🇩🇪 Deutsch
</label>
<label class="language-checkbox">
<input type="checkbox" name="languages[]" value="es">
🇪🇸 Español
</label>
</div>
</div>
</div>
<div class="form-section" id="servicesPricesSection">
<div class="section-header">
<h2 class="section-title">💰 Услуги и цены</h2>
</div>
<div class="section-content">
<div class="services-list" id="servicesList">
<div class="service-row">
<input type="text" name="services[0][name]" placeholder="Название услуги" required>
<input type="number" name="services[0][price]" placeholder="€" min="0" step="0.01" required>
<button type="button" class="btn-remove-service" onclick="removeService(this)">
<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
</button>
</div>
</div>
<button type="button" class="btn-add-service" onclick="addService()">
<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
Добавить услугу
</button>
<div class="form-hint">Минимум 1 услуга, максимум 20</div>
<div class="form-error" id="servicesError"></div>
</div>
</div>
<div class="form-section">
<div class="section-header">
<h2 class="section-title">📷 Фотографии</h2>
</div>
<div class="section-content">
<input type="file" id="photoInput" name="photos[]" multiple accept="image/*" style="display: none;" onchange="handlePhotoUpload(event)">
<div class="photo-upload" onclick="document.getElementById('photoInput').click()">
<svg class="photo-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
</svg>
<div class="photo-upload-text">Нажмите для загрузки фото</div>
<div class="photo-upload-hint">До 5 фото, JPG/PNG/WebP, макс. 5MB каждое</div>
</div>
<div class="photo-preview" id="photoPreview"></div>
<input type="hidden" name="photo_count" id="photoCount" value="0">
</div>
</div>
<div class="form-section">
<div class="section-header">
<h2 class="section-title">🌐 Социальные сети</h2>
</div>
<div class="section-content">
<div class="social-grid">
<div class="social-input-wrapper">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
<input type="url" class="form-input" name="instagram" placeholder="Instagram">
</div>
<div class="social-input-wrapper">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
<input type="url" class="form-input" name="facebook" placeholder="Facebook">
</div>
<div class="social-input-wrapper">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
<input type="tel" class="form-input" name="telegram" placeholder="Telegram">
</div>
<div class="social-input-wrapper">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<input type="url" class="form-input" name="vk" placeholder="VK">
</div>
</div>
</div>
</div>
<div class="checkbox-group">
<input type="checkbox" id="agreement" name="agreement" required>
<label for="agreement">
Я подтверждаю достоверность информации и соглашаюсь с
<a href="#">условиями использования</a> и
<a href="#">политикой конфиденциальности</a> Poisq
</label>
</div>
</form>
</main>
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
<div class="form-actions">
<button type="button" class="btn btn-secondary" onclick="saveDraft()">💾 Черновик</button>
<button type="button" class="btn btn-primary" onclick="submitForm()">📤 Отправить</button>
</div>
</div>
<script>
let serviceCount = 1;
let photoCount = 0;
const maxPhotos = 5;
let hasUnsavedChanges = false;
const countries = <?php echo json_encode($countries); ?>;
const detectedCountry = '<?php echo $detectedCountry['code']; ?>';
// Данные для восстановления формы после ошибки
const savedFormData = {
  country: '<?php echo htmlspecialchars($formData['country'] ?? $detectedCountry['code']); ?>',
  city_id: '<?php echo intval($formData['city_id'] ?? 0); ?>',
  category: '<?php echo htmlspecialchars($formData['category'] ?? ''); ?>',
  subcategory: '<?php echo htmlspecialchars($formData['subcategory'] ?? ''); ?>'
};
function openCountryModal() {
const modal = document.getElementById('countryModal');
const list = document.getElementById('countryList');
const searchInput = document.getElementById('countrySearch');
const selectedCountry = document.getElementById('country').value;
list.innerHTML = countries.map(country => `
<div class="country-item ${country.code === selectedCountry ? 'selected' : ''}"
onclick="selectCountry('${country.code}', '${country.name}')">
<div class="country-item-flag"><img src="https://flagcdn.com/w80/${country.code}.png" alt="${country.name}" loading="lazy"></div>
<span class="country-item-name">${country.name}</span>
<svg class="country-item-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
</div>
`).join('');
searchInput.value = '';
searchInput.addEventListener('input', function() {
const query = this.value.toLowerCase();
const items = list.querySelectorAll('.country-item');
items.forEach(item => {
const name = item.querySelector('.country-item-name').textContent.toLowerCase();
item.style.display = name.includes(query) ? 'flex' : 'none';
});
});
modal.classList.add('active');
document.body.style.overflow = 'hidden';
setTimeout(() => searchInput.focus(), 300);
}
function closeCountryModal() {
document.getElementById('countryModal').classList.remove('active');
document.body.style.overflow = '';
}
function selectCountry(code, name) {
document.getElementById('country').value = code;
document.getElementById('selectedCountryFlag').innerHTML = '<img src="https://flagcdn.com/w80/' + code + '.png" alt="' + name + '" style="width:100%;height:100%;object-fit:cover;display:block">';
document.getElementById('selectedCountryName').textContent = name;
document.getElementById('countrySelector').classList.remove('error');
document.getElementById('countryError').textContent = '';
loadCities(code);
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
// Подставляем символ валюты по стране
const currencyMap = {
'fr':'€','de':'€','es':'€','it':'€','pt':'€','nl':'€','be':'€',
'at':'€','gr':'€','fi':'€','ie':'€','lu':'€','mt':'€','cy':'€',
'gb':'£','ch':'CHF','se':'kr','no':'kr','dk':'kr',
'us':'$','ca':'$','au':'$','nz':'$','sg':'$','hk':'$',
'ru':'₽','ua':'₴','by':'Br','kz':'₸',
'pl':'zł','cz':'Kč','hu':'Ft','ro':'lei',
'tr':'₺','il':'₪','ae':'AED','sa':'SAR','qa':'QAR',
'jp':'¥','kr':'₩','th':'฿','in':'₹',
'br':'R$','mx':'$','ar':'$','cl':'$','co':'$','za':'R',
};
const symbol = currencyMap[code] || '€';
// Обновляем placeholder и иконку валюты у всех полей цены
document.querySelectorAll('input[name*="[price]"]').forEach(input => {
    input.placeholder = symbol;
});
// Сохраняем символ для новых строк услуг
window.currentCurrency = symbol;
closeCountryModal();
hasUnsavedChanges = true;
}
async function loadCities(countryCode) {
const citySelect = document.getElementById('citySelect');
citySelect.disabled = true;
citySelect.innerHTML = '<option value="">Загрузка городов...</option>';
document.getElementById('customCityToggle').style.display = 'none';
document.getElementById('customCityBlock').style.display = 'none';
citySelect.style.display = '';
citySelect.disabled = true;
try {
const response = await fetch(`api/get-cities.php?country=${countryCode}`);
const cities = await response.json();
citySelect.innerHTML = '<option value="">Выберите город</option>';
if (cities.length === 0) {
citySelect.innerHTML = '<option value="">Нет городов в базе</option>';
citySelect.disabled = true;
document.getElementById('customCityToggle').style.display = 'block';
return;
}
cities.forEach(city => {
const option = document.createElement('option');
option.value = city.id;
const label = city.name_lat
    ? `${city.name_lat} (${city.name})${city.is_capital == 1 ? ' ★' : ''}`
    : `${city.name}${city.is_capital == 1 ? ' ★' : ''}`;
option.textContent = label;
citySelect.appendChild(option);
});
citySelect.disabled = false;
document.getElementById('customCityToggle').style.display = 'block';
} catch (error) {
console.error('Error loading cities:', error);
citySelect.innerHTML = '<option value="">Ошибка загрузки</option>';
citySelect.disabled = true;
document.getElementById('customCityToggle').style.display = 'block';
}
}

function toggleCustomCity() {
const block = document.getElementById('customCityBlock');
const select = document.getElementById('citySelect');
block.style.display = 'block';
select.style.display = 'none';
select.disabled = true; // отключаем select чтобы не отправлял значение
document.getElementById('btnCustomCity').style.display = 'none';
document.getElementById('customCityInput').focus();
hasUnsavedChanges = true;
}

function cancelCustomCity() {
const block = document.getElementById('customCityBlock');
const select = document.getElementById('citySelect');
block.style.display = 'none';
select.style.display = '';
select.disabled = false;
document.getElementById('btnCustomCity').style.display = 'flex';
document.getElementById('customCityInput').value = '';
hasUnsavedChanges = true;
}
const subcategories = <?php echo json_encode($categories); ?>;
function updateSubcategories() {
const category = document.getElementById('category').value;
const subcategorySelect = document.getElementById('subcategory');
subcategorySelect.innerHTML = '<option value="">Выберите подкатегорию</option>';
if (category && subcategories[category]) {
subcategorySelect.disabled = false;
subcategories[category].subcategories.forEach(sub => {
const option = document.createElement('option');
option.value = sub;
option.textContent = sub;
subcategorySelect.appendChild(option);
});
} else {
subcategorySelect.disabled = true;
subcategorySelect.innerHTML = '<option value="">Сначала выберите категорию</option>';
}
applyMessengerMode(category === 'messengers');
hasUnsavedChanges = true;
}

function applyMessengerMode(isMessenger) {
// Ссылка на группу
document.getElementById('groupLinkBlock').style.display = isMessenger ? 'block' : 'none';
// Телефон и Email
document.getElementById('phoneEmailBlock').style.display = isMessenger ? 'none' : 'block';
const phoneInput = document.getElementById('phone');
const emailInput = document.getElementById('email');
if (phoneInput) phoneInput.required = !isMessenger;
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
document.getElementById('description').addEventListener('input', function() {
const count = this.value.length;
document.getElementById('descCount').textContent = count;
hasUnsavedChanges = true;
});
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
function addService() {
if (serviceCount >= 20) {
alert('Максимум 20 услуг');
return;
}
const list = document.getElementById('servicesList');
const row = document.createElement('div');
row.className = 'service-row';
const symbol = window.currentCurrency || '€';
row.innerHTML = `
<input type="text" name="services[${serviceCount}][name]" placeholder="Название услуги" required>
<input type="number" name="services[${serviceCount}][price]" placeholder="${symbol}" min="0" step="0.01" required>
<button type="button" class="btn-remove-service" onclick="removeService(this)">
<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
</button>
`;
list.appendChild(row);
serviceCount++;
hasUnsavedChanges = true;
}
function removeService(btn) {
if (document.querySelectorAll('.service-row').length <= 1) {
alert('Должна быть хотя бы одна услуга');
return;
}
btn.closest('.service-row').remove();
hasUnsavedChanges = true;
}
function handlePhotoUpload(event) {
const files = event.target.files;
const preview = document.getElementById('photoPreview');
for (let file of files) {
if (photoCount >= maxPhotos) {
alert('Максимум 5 фотографий');
break;
}
if (!file.type.match('image.*')) {
alert('Только изображения (JPG, PNG, WebP)');
continue;
}
if (file.size > 5 * 1024 * 1024) {
alert('Максимальный размер 5MB');
continue;
}
const reader = new FileReader();
reader.onload = function(e) {
const item = document.createElement('div');
item.className = 'photo-item' + (photoCount === 0 ? ' photo-item-main' : '');
item.innerHTML = `
<img src="${e.target.result}" alt="Photo">
${photoCount === 0 ? '<div class="photo-item-badge">Основное</div>' : ''}
<button type="button" class="photo-item-remove" onclick="removePhoto(this)">
<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
</button>
`;
preview.appendChild(item);
photoCount++;
document.getElementById('photoCount').value = photoCount;
};
reader.readAsDataURL(file);
}
hasUnsavedChanges = true;
}
function removePhoto(btn) {
btn.closest('.photo-item').remove();
photoCount--;
document.getElementById('photoCount').value = photoCount;
const firstPhoto = document.querySelector('.photo-item');
if (firstPhoto) {
firstPhoto.classList.add('photo-item-main');
if (!firstPhoto.querySelector('.photo-item-badge')) {
const badge = document.createElement('div');
badge.className = 'photo-item-badge';
badge.textContent = 'Основное';
firstPhoto.appendChild(badge);
}
}
hasUnsavedChanges = true;
}
// 🔧 ИСПРАВЛЕНИЕ: УСТАНАВЛИВАЕМ action ПЕРЕД ОТПРАВКОЙ
function submitForm() {
if (!validateForm()) {
return;
}
if (confirm('Отправить сервис на модерацию?')) {
document.getElementById('formAction').value = 'publish';
document.getElementById('serviceForm').submit();
}
}
function saveDraft() {
if (!validateForm(true)) {
return;
}
document.getElementById('formAction').value = 'draft';
const formData = new FormData(document.getElementById('serviceForm'));
const data = Object.fromEntries(formData);
localStorage.setItem('serviceDraft', JSON.stringify(data));
alert('Черновик сохранён!');
hasUnsavedChanges = false;
}
function validateForm(skipRequired = false) {
let isValid = true;
document.querySelectorAll('.form-error').forEach(el => el.textContent = '');
document.querySelectorAll('.form-input, .form-select, .form-textarea, .country-selector').forEach(el => el.classList.remove('error'));
if (!skipRequired && !document.getElementById('country').value) {
document.getElementById('countryError').textContent = 'Выберите страну';
document.getElementById('countrySelector').classList.add('error');
isValid = false;
}
if (!skipRequired) {
  const citySelect = document.getElementById('citySelect');
  const customCityBlock = document.getElementById('customCityBlock');
  const customCityInput = document.getElementById('customCityInput');
  const cityVisible = customCityBlock && customCityBlock.style.display !== 'none';
  const cityOk = cityVisible ? (customCityInput && customCityInput.value.trim().length > 0)
                              : (citySelect && citySelect.value && citySelect.value !== '');
  if (!cityOk) {
    document.getElementById('cityError').textContent = 'Выберите или введите город';
    if (citySelect) citySelect.classList.add('error');
    isValid = false;
  }
}
if (!skipRequired && !document.getElementById('category').value) {
document.getElementById('categoryError').textContent = 'Выберите категорию';
document.getElementById('category').classList.add('error');
isValid = false;
}
if (!skipRequired && !document.getElementById('subcategory').value) {
document.getElementById('subcategoryError').textContent = 'Выберите подкатегорию';
document.getElementById('subcategory').classList.add('error');
isValid = false;
}
const name = document.getElementById('name').value;
if (!skipRequired && (name.length < 3 || name.length > 100)) {
document.getElementById('nameError').textContent = 'От 3 до 100 символов';
document.getElementById('name').classList.add('error');
isValid = false;
}
const description = document.getElementById('description').value;
if (!skipRequired && description.length < 100) {
document.getElementById('descriptionError').textContent = 'Минимум 100 символов';
document.getElementById('description').classList.add('error');
isValid = false;
}
const isMessengerCat = document.getElementById('category').value === 'messengers';
if (!skipRequired && !isMessengerCat && !document.getElementById('phone').value) {
document.getElementById('phoneError').textContent = 'Введите телефон';
document.getElementById('phone').classList.add('error');
isValid = false;
}
if (false) {
isValid = false;
}
if (!skipRequired && !document.getElementById('address').value && document.getElementById('addressBlock').style.display !== 'none') {
document.getElementById('addressError').textContent = 'Введите адрес';
document.getElementById('address').classList.add('error');
isValid = false;
}
if (!skipRequired && isMessengerCat) {
const gl = document.getElementById('groupLink');
if (!gl || !gl.value.trim()) {
document.getElementById('groupLinkError').textContent = 'Введите ссылку на группу';
gl.classList.add('error');
isValid = false;
}
}
const serviceRows = document.querySelectorAll('.service-row');
if (!skipRequired && !isMessengerCat && serviceRows.length === 0) {
document.getElementById('servicesError').textContent = 'Добавьте хотя бы одну услугу';
isValid = false;
}
if (!skipRequired && !document.getElementById('agreement').checked) {
alert('Необходимо согласие с условиями');
isValid = false;
}
if (isValid) {
hasUnsavedChanges = false;
}
return isValid;
}
window.addEventListener('beforeunload', function(e) {
if (hasUnsavedChanges) {
e.preventDefault();
e.returnValue = '';
}
});
setInterval(function() {
if (hasUnsavedChanges) {
const formData = new FormData(document.getElementById('serviceForm'));
const data = Object.fromEntries(formData);
localStorage.setItem('serviceDraft', JSON.stringify(data));
console.log('Черновик автосохранён');
}
}, 30000);
document.querySelectorAll('input, select, textarea').forEach(el => {
el.addEventListener('change', () => { hasUnsavedChanges = true; });
el.addEventListener('input', () => { hasUnsavedChanges = true; });
});
document.getElementById('countryModal').addEventListener('click', function(e) {
if (e.target === this) {
closeCountryModal();
}
});
document.getElementById('closeCountryModal').addEventListener('click', closeCountryModal);

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
  // Находим первую страну с таким кодом
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
// ─────────────────────────────────────────────────────────────────────────────

// 🔧 ИНИЦИАЛИЗАЦИЯ: восстанавливаем данные после ошибки или устанавливаем страну по IP
(async function initForm() {
const initCountry = savedFormData.country || detectedCountry;
const countryObj = countries.find(c => c.code === initCountry) || countries[0];
// Устанавливаем страну в UI
document.getElementById('country').value = countryObj.code;
document.getElementById('selectedCountryFlag').innerHTML = '<img src="https://flagcdn.com/w80/' + countryObj.code + '.png" alt="' + countryObj.name + '" style="width:100%;height:100%;object-fit:cover;display:block">';
document.getElementById('selectedCountryName').textContent = countryObj.name;
// Загружаем города для этой страны
await loadCities(countryObj.code);
// Если был выбран город — восстанавливаем
if (savedFormData.city_id && savedFormData.city_id > 0) {
  const citySelect = document.getElementById('citySelect');
  citySelect.value = savedFormData.city_id;
}
// Восстанавливаем категорию
if (savedFormData.category) {
  document.getElementById('category').value = savedFormData.category;
  updateSubcategories(); // это вызовет applyMessengerMode
  // Восстанавливаем подкатегорию
  if (savedFormData.subcategory) {
    setTimeout(() => {
      document.getElementById('subcategory').value = savedFormData.subcategory;
    }, 50);
  }
}
// Устанавливаем телефонный код для страны
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
if (phoneCodes[countryObj.code]) setPhoneDialCode(phoneCodes[countryObj.code]);
// Обновляем счётчик символов описания
const desc = document.getElementById('description');
if (desc && desc.value) {
  document.getElementById('descCount').textContent = desc.value.length;
}
})();
</script>
</body>
</html>