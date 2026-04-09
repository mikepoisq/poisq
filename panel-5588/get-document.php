<?php
session_start();
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { http_response_code(403); exit('Forbidden'); }
$file = $_GET['file'] ?? '';
$file = basename($file);
$path = __DIR__ . '/../uploads/verification/' . $file;
if (!$file || !file_exists($path)) { http_response_code(404); exit('Not found'); }
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','pdf'=>'application/pdf'];
$mime = $types[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
