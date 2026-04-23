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

$where = ["s.status = 'approved'", "s.is_visible = 1", "s.lat IS NOT NULL", "s.lng IS NOT NULL"];
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

$sql = "SELECT s.id, s.name, s.category, s.subcategory, s.lat, s.lng, 
               s.phone, s.whatsapp, s.photo, s.address, s.description,
               s.rating, s.reviews_count, c.name as city_name, s.country_code
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE " . implode(' AND ', $where);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($services as &$s) {
    $photos = json_decode($s['photo'], true);
    $s['photo'] = (!empty($photos) && is_array($photos)) ? $photos[0] : null;
}

echo json_encode(['success' => true, 'services' => $services], JSON_UNESCAPED_UNICODE);
