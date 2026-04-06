<?php
// api/register-step2.php — Шаг 2: проверка кода, создание аккаунта, вход
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$email = trim($_POST['email'] ?? $_SESSION['verify_email'] ?? '');
$code  = str_replace(' ', '', $_POST['code'] ?? '');

if (!$email || !$code) {
    echo json_encode(['error' => 'Неверные данные']); exit;
}

$hashedPassword = $_SESSION['verify_password'] ?? '';
$name           = $_SESSION['verify_name']     ?? '';

if (!$hashedPassword) {
    echo json_encode(['error' => 'Сессия истекла. Начните регистрацию заново']); exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT id, expires_at FROM verification_codes WHERE email = ? AND code = ? AND used = 0");
$stmt->execute([$email, $code]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$verification) {
    echo json_encode(['error' => 'Неверный код. Проверьте 6 цифр из письма']); exit;
}
if (strtotime($verification['expires_at']) <= time()) {
    echo json_encode(['error' => 'Срок действия кода истёк. Начните регистрацию заново']); exit;
}

// Создаём пользователя
$pdo->prepare("INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, NOW())")
    ->execute([$email, $hashedPassword, $name]);
$userId = (int)$pdo->lastInsertId();

// Помечаем код использованным
$pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?")
    ->execute([$verification['id']]);

// Уведомляем администратора
try {
    require_once __DIR__ . '/../config/email.php';
    sendAdminNewUserEmail($name, $email);
} catch (Exception $e) {
    error_log('register-step2 admin notify error: ' . $e->getMessage());
}

// Создаём сессию
$_SESSION['user_id']     = $userId;
$_SESSION['user_email']  = $email;
$_SESSION['user_name']   = $name;
$_SESSION['user_avatar'] = '';

unset($_SESSION['verify_email'], $_SESSION['verify_name'], $_SESSION['verify_password'], $_SESSION['verify_code']);

echo json_encode([
    'success' => true,
    'user'    => ['id' => $userId, 'name' => $name, 'email' => $email],
]);
