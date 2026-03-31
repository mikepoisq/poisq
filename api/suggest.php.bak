<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q           = trim($_GET['q'] ?? '');
$country     = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? 'fr'));
$userCityId  = intval($_GET['city_id'] ?? 0);

if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

require_once __DIR__ . '/../config/meilisearch.php';

// Fallback на MySQL если Meilisearch недоступен
function fallbackSearch(string $q, string $country, int $cityId): array {
    require_once __DIR__ . '/../config/database.php';
    try {
        $pdo = getDbConnection();
        $params = [];
        $where  = ["s.status='approved'", "s.is_visible=1"];
        if ($cityId > 0) { $where[] = "s.city_id=?"; $params[] = $cityId; }
        else             { $where[] = "s.country_code=?"; $params[] = $country; }
        $where[]  = "(s.name LIKE ? OR s.description LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT s.id AS service_id, s.name AS service_name, s.category, s.subcategory,
                   s.rating, s.city_id, s.country_code, s.photo,
                   c.name AS city_name, c.name_lat AS city_slug
            FROM services s LEFT JOIN cities c ON s.city_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.views DESC LIMIT 8
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

$results = [];

// ШАГ 1: ищем сначала в городе юзера
if ($userCityId > 0) {
    $cityResult = meiliSearch($q, [
        'filter' => "city_id = $userCityId",
        'limit'  => 5,
    ]);
    $cityHits = $cityResult['hits'] ?? [];

    // Потом в стране (исключая город)
    $countryResult = meiliSearch($q, [
        'filter' => "country_code = '$country' AND city_id != $userCityId",
        'limit'  => 5,
    ]);
    $countryHits = $countryResult['hits'] ?? [];

    $hits = array_merge($cityHits, $countryHits);

} else {
    // Нет города — ищем по стране
    $result = meiliSearch($q, [
        'filter' => "country_code = '$country'",
        'limit'  => 8,
    ]);
    $hits = $result['hits'] ?? [];

    // Если Meilisearch вернул пустой массив (не работает) — fallback
    if (empty($hits) && empty($result)) {
        $fallback = fallbackSearch($q, $country, $userCityId);
        foreach ($fallback as $row) {
            $photo = null;
            if (!empty($row['photo'])) {
                $photos = json_decode($row['photo'], true);
                if (is_array($photos) && !empty($photos[0])) $photo = $photos[0];
            }
            $results[] = [
                'type'       => 'service',
                'text'       => $row['service_name'] . ($row['city_name'] ? ' • ' . $row['city_name'] : ''),
                'q'          => $row['service_name'],
                'city_id'    => (int)$row['city_id'],
                'city_slug'  => $row['city_slug'] ? strtolower($row['city_slug']) : null,
                'country'    => $row['country_code'],
                'photo'      => $photo,
                'service_id' => (int)$row['service_id'],
                'subtitle'   => trim($row['subcategory'] ?: $row['category'] ?: ''),
                'rating'     => $row['rating'] > 0 ? number_format((float)$row['rating'], 1) : null,
            ];
        }
        echo json_encode(array_slice($results, 0, 8), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Формируем ответ из Meilisearch hits
$seen = [];
foreach ($hits as $hit) {
    $key = mb_strtolower($hit['name']) . '_' . $hit['city_id'];
    if (isset($seen[$key])) continue;
    $seen[$key] = true;

    $results[] = [
        'type'       => 'service',
        'text'       => $hit['name'] . ($hit['city_name'] ? ' • ' . $hit['city_name'] : ''),
        'q'          => $hit['name'],
        'city_id'    => (int)$hit['city_id'],
        'city_slug'  => $hit['city_slug'] ? strtolower($hit['city_slug']) : null,
        'country'    => $hit['country_code'],
        'photo'      => $hit['photo'] ?? null,
        'service_id' => (int)$hit['id'],
        'subtitle'   => trim($hit['subcategory'] ?: $hit['category'] ?: ''),
        'rating'     => $hit['rating'] > 0 ? number_format((float)$hit['rating'], 1) : null,
    ];
}

echo json_encode(array_slice($results, 0, 8), JSON_UNESCAPED_UNICODE);
