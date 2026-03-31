<?php
// api/get-services.php — Получение сервисов за N дней
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$cityId = $_GET['city_id'] ?? null;
$days = $_GET['days'] ?? 5;

if (!$cityId) {
    echo json_encode(['services' => []]);
    exit;
}

try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.name,
            s.category,
            s.photo,
            s.rating,
            s.created_at,
            c.name as city_name
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.city_id = ?
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$cityId, $days]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['services' => $services]);
    
} catch (PDOException $e) {
    error_log('Get Services Error: ' . $e->getMessage());
    echo json_encode(['services' => []]);
}
?>