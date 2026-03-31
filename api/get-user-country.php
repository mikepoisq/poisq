<?php
// api/get-user-country.php — Определение страны и города по IP
// Использует freeipapi.com (бесплатно, без ключа, 60 req/min)
// Результат кешируется в /tmp/ на 24 часа

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Если уже всё есть в сессии — отдаём сразу ───────────────
if (
    isset($_SESSION['user_country']) &&
    isset($_SESSION['user_city_id'])
) {
    echo json_encode([
        'country_code' => $_SESSION['user_country'],
        'city_id'      => $_SESSION['user_city_id'],
        'city_slug'    => $_SESSION['user_city_slug'] ?? null,
        'city_name'    => $_SESSION['user_city_name'] ?? null,
    ]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ── Получаем IP ──────────────────────────────────────────────
function getUserIP(): string {
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';

    // Если несколько IP через запятую (прокси) — берём первый
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return $ip;
}

// ── Запрос к freeipapi.com ───────────────────────────────────
function fetchGeoData(string $ip): ?array {
    $url     = 'https://freeipapi.com/api/json/' . urlencode($ip);
    $context = stream_context_create([
        'http' => [
            'timeout'    => 4,
            'user_agent' => 'Poisq/1.0',
            'method'     => 'GET',
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['countryCode'])) return null;

    return [
        'country_code' => strtolower($data['countryCode']),
        'city_raw'     => $data['cityName'] ?? '',
        'region'       => $data['regionName'] ?? '',
    ];
}

// ── Ищем город в нашей БД по названию из API ────────────────
// freeipapi возвращает название на английском → ищем по name_lat
function matchCityInDB(PDO $pdo, string $cityRaw, string $countryCode): ?array {
    if (empty($cityRaw)) return null;

    // Нормализуем: убираем лишнее, lower
    $cityNorm = mb_strtolower(trim($cityRaw));

    // Сначала точное совпадение name_lat или name
    $stmt = $pdo->prepare("
        SELECT id, name, name_lat, country_code
        FROM cities
        WHERE country_code = ?
          AND status = 'active'
          AND (
            LOWER(name_lat) = ?
            OR LOWER(name)  = ?
          )
        ORDER BY is_capital DESC
        LIMIT 1
    ");
    $stmt->execute([$countryCode, $cityNorm, $cityNorm]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    // Частичное совпадение (Paris → Paris, Marseille → Marseille)
    $stmt2 = $pdo->prepare("
        SELECT id, name, name_lat, country_code
        FROM cities
        WHERE country_code = ?
          AND status = 'active'
          AND (
            LOWER(name_lat) LIKE ?
            OR LOWER(name)  LIKE ?
          )
        ORDER BY is_capital DESC, sort_order ASC
        LIMIT 1
    ");
    $stmt2->execute([$countryCode, $cityNorm . '%', $cityNorm . '%']);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($row2) return $row2;

    return null;
}

// ── Основная логика ──────────────────────────────────────────
$ip        = getUserIP();
$isLocal   = in_array($ip, ['127.0.0.1', '::1', 'localhost', '']);
$cacheFile = sys_get_temp_dir() . '/poisq_geo2_' . md5($ip);

$geo = null;

// Читаем кеш
if (!$isLocal && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (!empty($cached['country_code'])) {
        $geo = $cached;
    }
}

// Запрашиваем API если нет кеша
if (!$geo && !$isLocal) {
    $apiData = fetchGeoData($ip);
    if ($apiData) {
        $geo = $apiData;
    }
}

// Fallback для локального окружения или ошибки API
if (!$geo) {
    $geo = [
        'country_code' => 'fr',
        'city_raw'     => 'Paris',
        'region'       => '',
    ];
}

// Ищем город в нашей БД
$cityRow     = null;
$cityId      = null;
$citySlug    = null;
$cityName    = null;

try {
    $pdo     = getDbConnection();
    $cityRow = matchCityInDB($pdo, $geo['city_raw'], $geo['country_code']);

    if ($cityRow) {
        $cityId   = (int)$cityRow['id'];
        $citySlug = $cityRow['name_lat'] ? strtolower($cityRow['name_lat']) : null;
        $cityName = $cityRow['name'];
    }
} catch (Exception $e) {
    error_log('GeoCity DB Error: ' . $e->getMessage());
}

// Сохраняем в кеш (с городом)
if (!$isLocal) {
    $cacheData = array_merge($geo, [
        'city_id'   => $cityId,
        'city_slug' => $citySlug,
        'city_name' => $cityName,
    ]);
    @file_put_contents($cacheFile, json_encode($cacheData));
}

// Сохраняем в сессию
$_SESSION['user_country']   = $geo['country_code'];
$_SESSION['user_city_id']   = $cityId;
$_SESSION['user_city_slug'] = $citySlug;
$_SESSION['user_city_name'] = $cityName;

echo json_encode([
    'country_code' => $geo['country_code'],
    'city_id'      => $cityId,
    'city_slug'    => $citySlug,
    'city_name'    => $cityName,
]);
?>