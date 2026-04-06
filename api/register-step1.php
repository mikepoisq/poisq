<?php
// api/register-step1.php — Шаг 1: валидация и отправка кода верификации
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['error' => 'Заполните все поля']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Некорректный email']); exit;
}
if (strlen($password) < 6) {
    echo json_encode(['error' => 'Пароль должен быть не менее 6 символов']); exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Этот email уже зарегистрирован']); exit;
}

// Сохраняем данные в сессию
$_SESSION['verify_email']    = $email;
$_SESSION['verify_name']     = $name;
$_SESSION['verify_password'] = password_hash($password, PASSWORD_DEFAULT);

// Генерируем и сохраняем код
$code      = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$_SESSION['verify_code'] = $code;

$pdo->prepare("INSERT INTO verification_codes (email, code, expires_at, used) VALUES (?, ?, ?, 0)")
    ->execute([$email, $code, $expiresAt]);

// Отправляем email
try {
    require_once __DIR__ . '/../config/email.php';
    sendVerificationEmail($email, $code, $name);
} catch (Exception $e) {
    error_log('register-step1 email error: ' . $e->getMessage());
    // Продолжаем — код в БД, письмо необязательно
}

echo json_encode(['success' => true]);
