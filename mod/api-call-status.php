<?php
require_once __DIR__ . "/../panel-5588/auth.php";
require_once __DIR__ . "/../config/database.php";
header('Content-Type: application/json');
if (!isModeratorLoggedIn() && !isAdminLoggedIn()) {
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
    $pdo->prepare("UPDATE services SET call_status = ?, call_note = ? WHERE id = ?")->execute([$callStatus, $callNote, $serviceId]);
    if (isModeratorLoggedIn()) {
        recordModeratorStat(getModeratorId(), $serviceId, $callStatus);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
