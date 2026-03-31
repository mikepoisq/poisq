<?php
// router.php — 301 редиректы со старых ссылок на ЧПУ
// Вызывается из .htaccess когда открывают /results.php?... или /service.php?...

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// -- Редирект /service.php?id=123 > /service/123-slug --------
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $pdo  = getDbConnection();
        $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $url = 'https://poisq.com' . serviceUrl($id, $row['name']);
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $url);
            exit;
        }
    } catch (Exception $e) {
        // Ошибка БД — просто показываем страницу напрямую
        include __DIR__ . '/service.php';
        exit;
    }
    // Сервис не найден — на главную
    header('Location: /');
    exit;
}

// -- Редирект /results.php?... > ЧПУ ------------------------
$country  = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? 'fr'));
$q        = trim($_GET['q'] ?? '');
$cityId   = intval($_GET['city_id'] ?? 0);
$category = preg_replace('/[^a-z]/', '', strtolower($_GET['category'] ?? ''));
$page     = intval($_GET['page'] ?? 1);

// Определяем slug города из city_id
$citySlug = '';
if ($cityId > 0) {
    try {
        $pdo      = getDbConnection();
        $stmt     = $pdo->prepare("SELECT name_lat FROM cities WHERE id = ? LIMIT 1");
        $stmt->execute([$cityId]);
        $row      = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['name_lat'])) {
            $citySlug = strtolower(trim($row['name_lat']));
        }
    } catch (Exception $e) {
        // Ошибка БД — собираем URL без города
    }
}

// Собираем новый ЧПУ URL
// Формат: /fr/, /fr/paris/, /fr/paris/врач, /fr/врач
$path = '/' . $country . '/';

if ($citySlug) {
    $path .= $citySlug . '/';
}

if ($q) {
    $path .= urlencode($q);
}

// Доп. параметры которые не входят в ЧПУ — передаём как ?param=value
$extra = [];
if ($category)  $extra[] = 'category=' . urlencode($category);
if ($page > 1)  $extra[] = 'page=' . $page;
if (isset($_GET['rating']) && floatval($_GET['rating']) > 0) {
    $extra[] = 'rating=' . floatval($_GET['rating']);
}
if (isset($_GET['verified'])) {
    $extra[] = 'verified=1';
}

$url = 'https://poisq.com' . $path;
if (!empty($extra)) {
    $url .= '?' . implode('&', $extra);
}

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $url);
exit;
?>