<?php
// api/submit-verification.php — Отправка заявки на значок Проверено
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

$userId    = (int)$_SESSION['user_id'];
$serviceId = (int)($_POST['service_id'] ?? 0);
$agree     = !empty($_POST['agree']);

if (!$agree) {
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=agree');
    exit;
}
if ($serviceId <= 0) {
    header('Location: /profile.php');
    exit;
}

try {
    $pdo = getDbConnection();

    // Проверяем что сервис принадлежит пользователю и одобрен
    $stmt = $pdo->prepare("SELECT id, name FROM services WHERE id=? AND user_id=? AND status='approved' LIMIT 1");
    $stmt->execute([$serviceId, $userId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        header('Location: /profile.php');
        exit;
    }

    // Проверяем нет ли уже pending заявки
    $stmtChk = $pdo->prepare("SELECT id FROM verification_requests WHERE service_id=? AND status='pending' LIMIT 1");
    $stmtChk->execute([$serviceId]);
    if ($stmtChk->fetch()) {
        header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=already_pending');
        exit;
    }
} catch (PDOException $e) {
    error_log('submit-verification DB error: ' . $e->getMessage());
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=db');
    exit;
}

// Валидация файла
if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=no_file');
    exit;
}

$file    = $_FILES['document'];
$maxSize = 10 * 1024 * 1024; // 10 MB

if ($file['size'] > $maxSize) {
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=file_size');
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$mimeMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
if (!isset($mimeMap[$mime])) {
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=file_type');
    exit;
}

$ext       = $mimeMap[$mime];
$uploadDir = realpath(__DIR__ . '/../uploads/verification');
if ($uploadDir === false) {
    $uploadDir = __DIR__ . '/../uploads/verification';
    if (!mkdir($uploadDir, 0755, true)) {
        error_log('submit-verification: cannot create dir ' . $uploadDir);
        header('Location: /profile.php?verif_error=upload_fail');
        exit;
    }
    $uploadDir = realpath($uploadDir);
}
$uploadDir = rtrim($uploadDir, '/') . '/';

$fileName = $userId . '_' . $serviceId . '_' . time() . '.' . $ext;
$filePath = $uploadDir . $fileName;
$docPath  = '/uploads/verification/' . $fileName;
$origName = basename($file['name']);

if (!is_writable($uploadDir)) {
    error_log('submit-verification: directory not writable: ' . $uploadDir . ' (owner: ' . posix_getpwuid(fileowner($uploadDir))['name'] . ', process uid: ' . posix_geteuid() . ')');
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=upload_fail');
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    error_log('submit-verification: move_uploaded_file failed. tmp=' . $file['tmp_name'] . ' dst=' . $filePath . ' is_uploaded=' . (is_uploaded_file($file['tmp_name']) ? 'yes' : 'no'));
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=upload_fail');
    exit;
}

try {
    $stmtIns = $pdo->prepare("
        INSERT INTO verification_requests (user_id, service_id, document_path, document_original_name, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmtIns->execute([$userId, $serviceId, $docPath, $origName]);

    $stmtUser = $pdo->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    sendAdminVerificationEmail(
        $serviceId,
        $service['name'],
        $user['name'] ?? '',
        $user['email'] ?? '',
        $docPath
    );
} catch (PDOException $e) {
    error_log('submit-verification insert error: ' . $e->getMessage());
    header('Location: /verification.php?service_id=' . $serviceId . '&verif_error=db');
    exit;
}

header('Location: /verification.php?service_id=' . $serviceId . '&verif_success=1');
exit;
