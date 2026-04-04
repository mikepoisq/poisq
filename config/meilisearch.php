<?php
// config/meilisearch.php — Клиент для работы с Meilisearch

define('MEILI_HOST',  'http://127.0.0.1:7700');
define('MEILI_KEY',   'acad64db686c48a6cca1578b0ecdcea3938fa6653377fc00b3868e58beeed554');
define('MEILI_INDEX', 'services');

function meiliSearch(string $q, array $options = []): array {
    $body = array_merge(['q' => $q, 'limit' => 20], $options);
    return meiliRequest('POST', '/indexes/' . MEILI_INDEX . '/search', $body);
}

function meiliAddDocument(array $doc): void {
    meiliRequest('POST', '/indexes/' . MEILI_INDEX . '/documents', [$doc]);
}

function meiliUpdateDocument(array $doc): void {
    meiliRequest('PUT', '/indexes/' . MEILI_INDEX . '/documents', [$doc]);
}

function meiliDeleteDocument(int $id): void {
    meiliRequest('DELETE', '/indexes/' . MEILI_INDEX . '/documents/' . $id);
}

function meiliRequest(string $method, string $path, $body = null): array {
    $ch = curl_init(MEILI_HOST . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MEILI_KEY,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) return [];
    return json_decode($response, true) ?? [];
}

// Подготовить документ из строки БД для индексации
function meiliPrepareDoc(array $row): array {
    $photo = null;
    if (!empty($row['photo'])) {
        $photos = json_decode($row['photo'], true);
        if (is_array($photos) && !empty($photos[0])) $photo = $photos[0];
    }
    return [
        'id'           => (int)$row['id'],
        'name'         => $row['name'] ?? '',
        'description'  => mb_substr($row['description'] ?? '', 0, 500),
        'category'     => $row['category'] ?? '',
        'subcategory'  => $row['subcategory'] ?? '',
        'city_id'      => (int)($row['city_id'] ?? 0),
        'city_name'    => $row['city_name'] ?? '',
        'city_slug'    => $row['city_slug'] ?? '',
        'country_code' => $row['country_code'] ?? '',
        'rating'       => (float)($row['rating'] ?? 0),
        'views'        => (int)($row['views'] ?? 0),
        'verified'     => (int)($row['verified'] ?? 0),
        'created_at'   => isset($row['created_at']) ? strtotime($row['created_at']) : time(),
        'photo'        => $photo,
        'languages'    => json_decode($row['languages'] ?? '[]', true) ?: [],
    ];
}
