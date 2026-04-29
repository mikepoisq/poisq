<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

$pdo = getDbConnection();

$country  = $_GET['country'] ?? '';
$city_id  = $_GET['city_id'] ?? '';
$category = $_GET['category'] ?? '';
$rating   = floatval($_GET['rating'] ?? 0);
$verified = isset($_GET['verified']) ? 1 : 0;
$q        = trim($_GET['q'] ?? '');
$focus_id = intval($_GET['focus'] ?? 0);

// Если передан focus — возвращаем только этот сервис
if ($focus_id > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.name, s.category, s.subcategory, s.lat, s.lng,
               s.phone, s.whatsapp, s.photo, s.address, s.description,
               s.rating, s.reviews_count, c.name as city_name, s.country_code
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.id = ? AND s.status = 'approved' AND s.is_visible = 1 AND s.lat IS NOT NULL AND s.lng IS NOT NULL");
    $stmt->execute([$focus_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as &$s) {
        $photos = json_decode($s['photo'], true);
        $s['photo'] = (!empty($photos) && is_array($photos)) ? $photos[0] : null;
    }
    echo json_encode(['success' => true, 'services' => $services], JSON_UNESCAPED_UNICODE);
    exit;
}

$where = ["s.status = 'approved'", "s.is_visible = 1", "(s.lat IS NOT NULL OR (s.category = 'messengers' AND c2.lat IS NOT NULL))"];
$params = [];

if ($country) {
    $where[] = "s.country_code = ?";
    $params[] = $country;
}
if ($city_id) {
    $where[] = "s.city_id = ?";
    $params[] = $city_id;
}
if ($category) {
    $where[] = "s.category = ?";
    $params[] = $category;
}
if ($rating > 0) {
    $where[] = "s.rating >= ?";
    $params[] = $rating;
}
if ($verified) {
    $where[] = "s.verified = 1";
}
// Убираем название города из запроса если city_id уже передан
if ($q && $city_id) {
    try {
        $cs = $pdo->prepare("SELECT name, name_lat FROM cities WHERE id = ? LIMIT 1");
        $cs->execute([$city_id]);
        $fc = $cs->fetch(PDO::FETCH_ASSOC);
        if ($fc) {
            $q = trim(preg_replace('/'.preg_quote($fc['name'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/'.preg_quote($fc['name_lat'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/\s+/', ' ', $q));
        }
    } catch (Exception $e) {}
}
// Парсим город из текста запроса (если city_id не передан)
if ($q && !$city_id) {
    $qwords = array_filter(explode(' ', mb_strtolower($q, 'UTF-8')), fn($w) => mb_strlen($w) >= 3);
    foreach ($qwords as $qw) {
        $cs = $pdo->prepare("SELECT id, name, name_lat, country_code FROM cities WHERE LOWER(name) LIKE ? OR LOWER(name_lat) LIKE ? LIMIT 1");
        $cs->execute(['%'.$qw.'%', '%'.$qw.'%']);
        $fc = $cs->fetch(PDO::FETCH_ASSOC);
        if ($fc) {
            $city_id = $fc['id'];
            if (!$country) $country = $fc['country_code'];
            // Убираем название города из запроса
            $q = trim(preg_replace('/'.preg_quote($fc['name'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/'.preg_quote($fc['name_lat'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/\s+/', ' ', $q));
            // Обновляем WHERE для города
            $where[] = "s.city_id = ?";
            $params[] = $city_id;
            // Убираем фильтр страны если был — город точнее
            if ($country) {
                $where = array_filter($where, fn($w) => strpos($w, 'country_code') === false);
                $where = array_values($where);
                $params = array_filter($params, fn($v) => $v !== $country);
                $params = array_values($params);
            }
            break;
        }
    }
}

// Распознаём мессенджер из запроса
$messengerKeywords = [
    'WhatsApp группа' => ['ватсап','вотсап','whatsapp','ватсапп'],
    'Telegram группа' => ['телеграм','telegram','телеграмм','тг'],
];
foreach ($messengerKeywords as $subcatValue => $keywords) {
    foreach ($keywords as $kw) {
        if (mb_strpos(mb_strtolower($q, 'UTF-8'), $kw) !== false) {
            $where[] = "s.subcategory = ?";
            $params[] = $subcatValue;
            $q = '';
            break 2;
        }
    }
}

if ($q) {
    // Ищем совпадение с подкатегорией
    $subStmt = $pdo->prepare("SELECT category_slug, name FROM service_subcategories WHERE is_active=1");
    $subStmt->execute();
    $allSubs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    // Ищем совпадение с категорией
    $catStmt = $pdo->prepare("SELECT slug, name FROM service_categories WHERE is_active=1");
    $catStmt->execute();
    $allCats = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $matchedSubcategory = null;
    $matchedCategory = null;
    $qLower = mb_strtolower($q, 'UTF-8');

    // Проверяем подкатегории (точное и частичное совпадение)
    foreach ($allSubs as $sub) {
        $subLower = mb_strtolower($sub['name'], 'UTF-8');
        if ($subLower === $qLower || strpos($subLower, $qLower) !== false || strpos($qLower, $subLower) !== false) {
            $matchedSubcategory = $sub['name'];
            $matchedCategory = $sub['category_slug'];
            break;
        }
    }

    // Если подкатегория не найдена — проверяем категории
    if (!$matchedSubcategory) {
        foreach ($allCats as $cat) {
            $catLower = mb_strtolower($cat['name'], 'UTF-8');
            if ($catLower === $qLower || strpos($catLower, $qLower) !== false || strpos($qLower, $catLower) !== false) {
                $matchedCategory = $cat['slug'];
                break;
            }
        }
    }

    if ($matchedSubcategory) {
        // Точный поиск по подкатегории
        $where[] = "s.subcategory = ?";
        $params[] = $matchedSubcategory;
    } elseif ($matchedCategory) {
        // Поиск по категории
        $where[] = "s.category = ?";
        $params[] = $matchedCategory;
    } else {
        // Обычный текстовый поиск
        $where[] = "(s.name LIKE ? OR s.description LIKE ? OR s.subcategory LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
}

$sql = "SELECT s.id, s.name, s.category, s.subcategory,
               COALESCE(s.lat, c2.lat) as lat,
               COALESCE(s.lng, c2.lng) as lng,
               s.phone, s.whatsapp, s.photo, s.address, s.description,
               s.rating, s.reviews_count, c2.name as city_name, s.country_code, s.group_link
        FROM services s
        LEFT JOIN cities c2 ON s.city_id = c2.id
        WHERE " . implode(' AND ', $where);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($services as &$s) {
    $photos = json_decode($s['photo'], true);
    $s['photo'] = (!empty($photos) && is_array($photos)) ? $photos[0] : null;
}

echo json_encode(['success' => true, 'services' => $services], JSON_UNESCAPED_UNICODE);
