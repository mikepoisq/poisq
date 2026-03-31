<?php
// panel-5588/reindex.php — Индексация всех сервисов в Meilisearch
// Запускать вручную или после массовых изменений в БД

define('MEILI_HOST', 'http://127.0.0.1:7700');
define('MEILI_KEY',  'acad64db686c48a6cca1578b0ecdcea3938fa6653377fc00b3868e58beeed554');
define('MEILI_INDEX', 'services');
define('BATCH_SIZE', 100);

require_once __DIR__ . '/../config/database.php';

// Простая защита — только с сервера или с паролем
$token = $_GET['token'] ?? '';
if (php_sapi_name() !== 'cli' && $token !== 'reindex_' . date('Ymd')) {
    http_response_code(403);
    die('Forbidden. Token: reindex_' . date('Ymd'));
}

$pdo = getDbConnection();

// Считаем сколько сервисов
$total = $pdo->query("SELECT COUNT(*) FROM services WHERE status='approved' AND is_visible=1")->fetchColumn();
echo "Total services to index: $total\n";

// Удаляем старые документы
meiliRequest('DELETE', '/indexes/' . MEILI_INDEX . '/documents');
echo "Old documents deleted\n";

// Загружаем батчами по 100
$offset = 0;
$indexed = 0;

while ($offset < $total) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.description, s.category, s.subcategory,
               s.city_id, s.country_code, s.phone, s.email, s.website,
               s.rating, s.reviews_count, s.views, s.verified,
               s.status, s.is_visible, s.created_at, s.photo,
               c.name AS city_name, c.name_lat AS city_slug
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.status = 'approved' AND s.is_visible = 1
        ORDER BY s.id
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([BATCH_SIZE, $offset]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) break;

    // Подготавливаем документы
    $docs = [];
    foreach ($rows as $row) {
        // Берём первое фото из JSON массива
        $photo = null;
        if (!empty($row['photo'])) {
            $photos = json_decode($row['photo'], true);
            if (is_array($photos) && !empty($photos[0])) $photo = $photos[0];
        }
        $docs[] = [
            'id'            => (int)$row['id'],
            'name'          => $row['name'] ?? '',
            'description'   => mb_substr($row['description'] ?? '', 0, 500),
            'category'      => $row['category'] ?? '',
            'subcategory'   => $row['subcategory'] ?? '',
            'city_id'       => (int)$row['city_id'],
            'city_name'     => $row['city_name'] ?? '',
            'city_slug'     => $row['city_slug'] ?? '',
            'country_code'  => $row['country_code'] ?? '',
            'rating'        => (float)$row['rating'],
            'views'         => (int)$row['views'],
            'verified'      => (int)$row['verified'],
            'created_at'    => strtotime($row['created_at']),
            'photo'         => $photo,
        ];
    }

    $result = meiliRequest('POST', '/indexes/' . MEILI_INDEX . '/documents', $docs);
    $indexed += count($docs);
    echo "Indexed: $indexed / $total\n";
    $offset += BATCH_SIZE;
}

echo "Done! Total indexed: $indexed\n";

function meiliRequest(string $method, string $path, $body = null): array {
    $ch = curl_init(MEILI_HOST . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MEILI_KEY,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}
