<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q           = trim($_GET['q'] ?? '');
$country     = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? 'fr'));
$userCityId  = intval($_GET['city_id'] ?? 0);

if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

require_once __DIR__ . '/../config/meilisearch.php';

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
        $stmt = $pdo->prepare("SELECT s.id AS service_id, s.name AS service_name, s.category, s.subcategory, s.rating, s.city_id, s.country_code, s.photo, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id = c.id WHERE " . implode(' AND ', $where) . " ORDER BY s.views DESC LIMIT 8");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
}

function formatHit(array $hit): array {
    return [
        'type'       => 'service',
        'text'       => $hit['name'] . ($hit['city_name'] ? ' • ' . $hit['city_name'] : ''),
        'q'          => $hit['name'],
        'city_id'    => (int)$hit['city_id'],
        'city_slug'  => isset($hit['city_slug']) ? strtolower($hit['city_slug']) : null,
        'country'    => $hit['country_code'],
        'photo'      => $hit['photo'] ?? null,
        'service_id' => (int)$hit['id'],
        'subtitle'   => trim($hit['subcategory'] ?: $hit['category'] ?: ''),
        'rating'     => $hit['rating'] > 0 ? number_format((float)$hit['rating'], 1) : null,
    ];
}

$results = [];
$hits = [];

// Парсим город из текста запроса
$detectedCityId = $userCityId;
$cleanQ = $q;
require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();
$qwords = array_filter(explode(' ', mb_strtolower($q, 'UTF-8')), fn($w) => mb_strlen($w) >= 3);
foreach ($qwords as $qw) {
    try {
        $cs = $pdo->prepare("SELECT id, name, name_lat, country_code FROM cities WHERE LOWER(name) LIKE ? OR LOWER(name_lat) LIKE ? LIMIT 1");
        $cs->execute(['%'.$qw.'%', '%'.$qw.'%']);
        $fc = $cs->fetch(PDO::FETCH_ASSOC);
        if ($fc) {
            $detectedCityId = (int)$fc['id'];
            $country = $fc['country_code'];
            $cleanQ = trim(preg_replace('/'.preg_quote($fc['name'], '/').'/iu', '', $cleanQ));
            $cleanQ = trim(preg_replace('/'.preg_quote($fc['name_lat'], '/').'/iu', '', $cleanQ));
            $cleanQ = trim(preg_replace('/\s+/', ' ', $cleanQ));
            if (empty($cleanQ)) $cleanQ = $q;
            break;
        }
    } catch (Exception $e) {}
}

if ($detectedCityId > 0) {
    $r = meiliSearch($cleanQ, ['filter' => "city_id = $detectedCityId", 'limit' => 8]);
    $hits = $r['hits'] ?? [];
} else {
    $r2 = meiliSearch($cleanQ, ['filter' => "country_code = '$country'", 'limit' => 8]);
    $hits = $r2['hits'] ?? [];
    if (count($hits) < 3) {
        $r3 = meiliSearch($cleanQ, ['filter' => "country_code != '$country'", 'limit' => 5, 'sort' => ['verified:desc', 'rating:desc', 'views:desc']]);
        $hits = array_merge($hits, $r3['hits'] ?? []);
    }
}

if (empty($hits) && empty($r2)) {
    $fallback = fallbackSearch($q, $country, $userCityId);
    foreach ($fallback as $row) {
        $photo = null;
        if (!empty($row['photo'])) {
            $photos = json_decode($row['photo'], true);
            if (is_array($photos) && !empty($photos[0])) $photo = $photos[0];
        }
        $results[] = ['type'=>'service','text'=>$row['service_name'].($row['city_name']?' • '.$row['city_name']:''),'q'=>$row['service_name'],'city_id'=>(int)$row['city_id'],'city_slug'=>$row['city_slug']?strtolower($row['city_slug']):null,'country'=>$row['country_code'],'photo'=>$photo,'service_id'=>(int)$row['service_id'],'subtitle'=>trim($row['subcategory']?:$row['category']?:''),'rating'=>$row['rating']>0?number_format((float)$row['rating'],1):null];
    }
    echo json_encode(array_slice($results, 0, 8), JSON_UNESCAPED_UNICODE);
    exit;
}

$seen = [];
foreach ($hits as $hit) {
    $key = mb_strtolower($hit['name']) . '_' . $hit['city_id'];
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $results[] = formatHit($hit);
}

echo json_encode(array_slice($results, 0, 8), JSON_UNESCAPED_UNICODE);
