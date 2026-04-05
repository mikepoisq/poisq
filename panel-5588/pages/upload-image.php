<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

// Max 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large. Max 5MB.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    echo json_encode(['error' => 'Invalid format. Allowed: jpg, jpeg, png, webp']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/articles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'article_img_' . uniqid() . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

echo json_encode(['data' => ['filePath' => '/uploads/articles/' . $filename]]);
