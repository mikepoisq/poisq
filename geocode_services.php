<?php
require_once 'config/database.php';
$pdo = getDbConnection();

$services = $pdo->query("SELECT id, name, address, city_id, country_code FROM services WHERE status='approved' AND address != '' AND address IS NOT NULL AND (lat IS NULL OR lng IS NULL)")->fetchAll(PDO::FETCH_ASSOC);

echo "Найдено сервисов для геокодирования: " . count($services) . "\n";

$ok = 0; $fail = 0;

foreach ($services as $s) {
    $query = urlencode($s['address'] . ', ' . $s['country_code']);
    $url = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1";
    
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: Poisq/1.0 (poisq.com)\r\n", 'timeout' => 10]]);
    $result = @file_get_contents($url, false, $ctx);
    
    if ($result) {
        $data = json_decode($result, true);
        if (!empty($data[0]['lat'])) {
            $lat = $data[0]['lat'];
            $lng = $data[0]['lon'];
            $pdo->prepare("UPDATE services SET lat=?, lng=? WHERE id=?")->execute([$lat, $lng, $s['id']]);
            echo "✓ [{$s['id']}] {$s['name']}: {$lat}, {$lng}\n";
            $ok++;
        } else {
            echo "✗ [{$s['id']}] {$s['name']}: адрес не найден\n";
            $fail++;
        }
    } else {
        echo "✗ [{$s['id']}] {$s['name']}: ошибка запроса\n";
        $fail++;
    }
    sleep(1); // лимит Nominatim: 1 запрос/сек
}

echo "\nГотово: успешно={$ok}, не найдено={$fail}\n";
