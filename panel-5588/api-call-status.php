<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";

header('Content-Type: application/json');

if (!isAdminLoggedIn() && !isModeratorLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$serviceId  = (int)($_POST['service_id']  ?? 0);
$callStatus = trim($_POST['call_status']  ?? '');
$callNote   = trim($_POST['call_note']    ?? '');

$allowed = ['not_called', 'no_answer', 'reached', 'no_number', 'other'];
if ($serviceId <= 0 || !in_array($callStatus, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE services SET call_status = ?, call_note = ? WHERE id = ?");
    $stmt->execute([$callStatus, $callNote, $serviceId]);

    // Запись статистики модератора
    if (isModeratorLoggedIn()) {
        recordModeratorStat(getModeratorId(), $serviceId, $callStatus);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('api-call-status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
