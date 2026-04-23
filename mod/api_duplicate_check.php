<?php
session_start();
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

// Доступ только для авторизованных модераторов
if (!isModeratorLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$pdo    = getDbConnection();
$name   = trim($_GET['name']   ?? '');
$phone  = trim($_GET['phone']  ?? '');
$email  = trim($_GET['email']  ?? '');
$address= trim($_GET['address']?? '');
$excludeId = (int)($_GET['exclude'] ?? 0);

if (empty($name) && empty($phone) && empty($email) && empty($address)) {
    echo json_encode([]);
    exit;
}

$conditions = [];
$params     = [];

if (!empty($phone)) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) >= 7) {
        $conditions[] = "REGEXP_REPLACE(s.phone, '[^0-9]', '') LIKE ?";
        $params[]     = '%' . $cleanPhone . '%';
    }
}
if (!empty($email)) {
    $conditions[] = "LOWER(s.email) = ?";
    $params[]     = strtolower($email);
}
if (!empty($name) && mb_strlen($name) >= 4) {
    $conditions[] = "s.name LIKE ?";
    $params[]     = '%' . $name . '%';
}
if (!empty($address) && mb_strlen($address) >= 6) {
    // Берём только уникальные слова длиннее 4 символов, исключая цифры и общие слова
    $stopWords = ['paris','paris','paris','street','avenue','boulevard','france'];
    $words = array_filter(preg_split('/[\s,]+/', $address), function($w) use ($stopWords) {
        return mb_strlen($w) > 4 && !is_numeric($w) && !in_array(mb_strtolower($w), $stopWords);
    });
    $words = array_slice(array_values($words), 0, 2);
    if (!empty($words)) {
        // AND логика — адрес должен содержать ВСЕ слова
        $addrParts = [];
        foreach ($words as $word) {
            $addrParts[] = "s.address LIKE ?";
            $params[]    = '%' . $word . '%';
        }
        $conditions[] = '(' . implode(' AND ', $addrParts) . ')';
    }
}

if (empty($conditions)) {
    echo json_encode([]);
    exit;
}

$whereOr = implode(' OR ', $conditions);
$whereEx = $excludeId > 0 ? " AND s.id != $excludeId" : '';

$sql = "
    SELECT s.id, s.name, s.phone, s.whatsapp, s.email, s.address,
           s.photo, s.status, s.country_code,
           c.name AS city_name
    FROM services s
    LEFT JOIN cities c ON s.city_id = c.id
    WHERE ($whereOr)
      AND s.status IN ('approved','pending','duplicate')
      $whereEx
    ORDER BY s.status = 'approved' DESC, s.created_at DESC
    LIMIT 5
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $photos = json_decode($row['photo'] ?? '[]', true) ?: [];
        $result[] = [
            'id'      => (int)$row['id'],
            'name'    => $row['name'],
            'phone'   => $row['phone']    ?? '',
            'email'   => $row['email']    ?? '',
            'address' => $row['address']  ?? '',
            'photo'   => $photos[0]       ?? '',
            'status'  => $row['status'],
            'city'    => $row['city_name']    ?? '',
            'country' => $row['country_code'] ?? '',
        ];
    }
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
