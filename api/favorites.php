<?php
error_log('Favorites API called: ' . json_encode($_POST) . ' SESSION: ' . json_encode($_SESSION));
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.category, s.photo,
                s.rating, s.reviews_count, c.name AS city_name
            FROM favorites f
            JOIN services s ON f.service_id = s.id
            LEFT JOIN cities c ON s.city_id = c.id
            WHERE f.user_id = ? AND s.status = 'approved' AND s.is_visible = 1
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(function($row) {
            $photoRaw = $row['photo'] ?? '';
            $photo = '';
            if (!empty($photoRaw)) {
                $decoded = json_decode($photoRaw, true);
                $photo = is_array($decoded) ? ($decoded[0] ?? '') : $photoRaw;
            }
            return [
                'id' => (int)$row['id'], 'name' => $row['name'],
                'category' => $row['category'], 'city_name' => $row['city_name'] ?? '',
                'photo' => $photo, 'rating' => $row['rating'] ?? 0,
                'reviews_count' => (int)$row['reviews_count'],
            ];
        }, $rows);
        echo json_encode(['success' => true, 'items' => $items]);
        exit;
    }

    $serviceId = (int)($_POST['service_id'] ?? 0);

    if ($serviceId <= 0) {
        echo json_encode(['success' => false, 'error' => 'invalid_service']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND service_id = ?");
    $stmt->execute([$userId, $serviceId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND service_id = ?")->execute([$userId, $serviceId]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, service_id) VALUES (?, ?)")->execute([$userId, $serviceId]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }

} catch (PDOException $e) {
    error_log('Favorites DB Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'db_error', 'message' => $e->getMessage()]);
}
