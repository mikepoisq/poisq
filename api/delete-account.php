<?php
// api/delete-account.php — Удаление аккаунта пользователя
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
    exit;
}

$password = $_POST['password'] ?? '';
if ($password === '') {
    echo json_encode(['success' => false, 'error' => 'Введите пароль']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int)$_SESSION['user_id'];

try {
    $pdo = getDbConnection();

    // Проверяем пароль
    $stmt = $pdo->prepare("SELECT password_hash, avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Неверный пароль']);
        exit;
    }

    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

    // Удаляем фото сервисов
    $stmtSvc = $pdo->prepare("SELECT photo FROM services WHERE user_id = ?");
    $stmtSvc->execute([$userId]);
    $services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

    foreach ($services as $svc) {
        if (empty($svc['photo'])) continue;
        $photos = json_decode($svc['photo'], true);
        if (!is_array($photos)) continue;
        foreach ($photos as $photoPath) {
            $file = $docRoot . '/' . ltrim($photoPath, '/');
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    // Удаляем всё связанное с сервисами юзера
    try { $pdo->prepare("DELETE ror FROM review_owner_replies ror INNER JOIN reviews r ON ror.review_id = r.id INNER JOIN services s ON r.service_id = s.id WHERE s.user_id = ?")->execute([$userId]); } catch(Exception $e) {}
    try { $pdo->prepare("DELETE r FROM reviews r INNER JOIN services s ON r.service_id = s.id WHERE s.user_id = ?")->execute([$userId]); } catch(Exception $e) {}
    try { $pdo->prepare("DELETE vr FROM verification_requests vr INNER JOIN services s ON vr.service_id = s.id WHERE s.user_id = ?")->execute([$userId]); } catch(Exception $e) {}
    try { $pdo->prepare("DELETE f FROM favorites f INNER JOIN services s ON f.service_id = s.id WHERE s.user_id = ?")->execute([$userId]); } catch(Exception $e) {}
    // Удаляем сервисы
    $pdo->prepare("DELETE FROM services WHERE user_id = ?")->execute([$userId]);

    // Удаляем аватар
    if (!empty($user['avatar'])) {
        $avatarFile = $docRoot . '/' . ltrim($user['avatar'], '/');
        if (file_exists($avatarFile)) {
            @unlink($avatarFile);
        }
    }

    // Удаляем связанные записи
    $pdo->prepare("DELETE FROM article_submissions WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM review_owner_replies WHERE owner_user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM verification_requests WHERE user_id = ?")->execute([$userId]);
    // Удаляем пользователя
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    // Уничтожаем сессию
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'goodbye']);

} catch (PDOException $e) {
    error_log('delete-account.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
} catch (Exception $e) {
    error_log('delete-account.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
?>
