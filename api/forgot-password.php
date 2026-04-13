<?php
// api/forgot-password.php — AJAX восстановление пароля
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Введите email']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Некорректный email']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
            ->execute([$email]);

        $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used) VALUES (?, ?, ?, 0)")
            ->execute([$email, $token, $expiresAt]);

        try {
            require_once __DIR__ . '/../config/email.php';
            $resetLink = 'https://poisq.com/reset-password.php?token=' . $token;
            $sent = sendPasswordResetEmail($email, $resetLink, $user['name']);
            if (!$sent) {
                echo json_encode(['success' => false, 'error' => 'Ошибка отправки письма. Попробуйте позже.']);
                exit;
            }
        } catch (Exception $e) {
            error_log('Forgot password email error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Ошибка отправки письма. Попробуйте позже.']);
            exit;
        }
    }
    // Не раскрываем, есть ли такой email
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('Forgot password DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера. Попробуйте позже.']);
}
