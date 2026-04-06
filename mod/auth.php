<?php
// /mod/auth.php — Авторизация модераторов
// session_start() вызывается на страницах, включающих этот файл

if (!defined('MOD_AUTH_INCLUDED')) define('MOD_AUTH_INCLUDED', true);

function isModeratorLoggedIn(): bool {
    return isset($_SESSION['moderator_id']) && (int)$_SESSION['moderator_id'] > 0;
}

function getModeratorId(): int {
    return (int)($_SESSION['moderator_id'] ?? 0);
}

function getModeratorName(): string {
    return $_SESSION['moderator_name'] ?? '';
}

function getModeratorPermissions(): array {
    return $_SESSION['moderator_permissions'] ?? [];
}

function moderatorHasPermission(string $perm): bool {
    return in_array($perm, getModeratorPermissions(), true);
}

function requireModeratorAuth(string $permission = ''): void {
    if (!isModeratorLoggedIn()) {
        header('Location: /mod/');
        exit;
    }
    if ($permission && !moderatorHasPermission($permission)) {
        header('Location: /mod/?error=noaccess');
        exit;
    }
    // Таймаут 4 часа
    if (time() - ($_SESSION['moderator_last_active'] ?? 0) > 14400) {
        session_destroy();
        header('Location: /mod/');
        exit;
    }
    $_SESSION['moderator_last_active'] = time();
}

function moderatorLogin(string $email, string $password): array {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT * FROM moderators WHERE email = ?");
    $stmt->execute([trim($email)]);
    $mod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mod) {
        return ['success' => false, 'error' => 'Неверный email или пароль'];
    }
    if (!$mod['is_active']) {
        return ['success' => false, 'error' => 'Аккаунт деактивирован. Обратитесь к администратору'];
    }
    // Rate limit
    if ($mod['locked_until'] && strtotime($mod['locked_until']) > time()) {
        $until = date('H:i', strtotime($mod['locked_until']));
        return ['success' => false, 'error' => "Слишком много попыток. Аккаунт заблокирован до $until"];
    }

    if (!password_verify($password, $mod['password_hash'])) {
        $attempts = (int)$mod['login_attempts'] + 1;
        if ($attempts >= 5) {
            $locked = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $pdo->prepare("UPDATE moderators SET login_attempts=?, locked_until=? WHERE id=?")
                ->execute([$attempts, $locked, $mod['id']]);
            return ['success' => false, 'error' => 'Слишком много попыток. Аккаунт заблокирован на 30 минут'];
        }
        $pdo->prepare("UPDATE moderators SET login_attempts=? WHERE id=?")->execute([$attempts, $mod['id']]);
        return ['success' => false, 'error' => 'Неверный email или пароль'];
    }

    // Успешный вход
    $pdo->prepare("UPDATE moderators SET login_attempts=0, locked_until=NULL WHERE id=?")->execute([$mod['id']]);

    $moderatorId = (int)$mod['id'];
    $_SESSION['moderator_id']          = $moderatorId;
    $_SESSION['moderator_name']        = $mod['name'];
    $_SESSION['moderator_permissions'] = json_decode($mod['permissions'] ?? '[]', true) ?: [];
    $_SESSION['moderator_last_active'] = time();

    error_log("Moderator login success: " . $moderatorId . " session_id: " . session_id());

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $pdo->prepare("INSERT INTO moderator_sessions (moderator_id, ip_address, user_agent) VALUES (?,?,?)")
        ->execute([$mod['id'], $ip, $ua]);

    return ['success' => true];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function recordModeratorStat(int $modId, int $serviceId, string $action): void {
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = getDbConnection();
        if ($action === 'created') {
            $pdo->prepare("INSERT INTO moderator_stats (moderator_id, service_id, action, stat_date) VALUES (?,?,?,CURDATE())")
                ->execute([$modId, $serviceId, 'created']);
        } else {
            // Обновляем статус созвона: сначала удаляем старый, потом вставляем новый
            $pdo->prepare("DELETE FROM moderator_stats WHERE moderator_id=? AND service_id=? AND action IN ('reached','no_answer','no_number','other')")
                ->execute([$modId, $serviceId]);
            if ($action !== 'not_called') {
                $pdo->prepare("INSERT INTO moderator_stats (moderator_id, service_id, action, stat_date) VALUES (?,?,?,CURDATE())")
                    ->execute([$modId, $serviceId, $action]);
            }
        }
    } catch (Exception $e) {
        error_log('recordModeratorStat error: ' . $e->getMessage());
    }
}
