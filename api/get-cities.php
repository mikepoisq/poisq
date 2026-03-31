<?php
// api/get-cities.php — Получение городов страны
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$countryCode = $_GET['country'] ?? 'fr';

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT id, name, name_lat, is_capital, sort_order
        FROM cities
        WHERE country_code = ?
        AND (status = 'active' OR status IS NULL)
        ORDER BY is_capital DESC, sort_order ASC, name ASC
    ");
    $stmt->execute([$countryCode]);
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cities);

} catch (PDOException $e) {
    error_log('Get Cities Error: ' . $e->getMessage());
    echo json_encode([]);
}
?>