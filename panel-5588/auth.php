<?php
session_start();

define('ADMIN_LOGIN', 'admin_mike');
define('ADMIN_HASH', '$2y$10$0nIAjp1OFvbapE7VZZzVnu/mwm/nyEdH6Y.IWWSP2KRtpfANluNdu');
define('SUPER_ADMIN_ID', 1); // ID главного администратора для поля created_by_admin

// Подключаем авторизацию модераторов
require_once __DIR__ . '/../mod/auth.php';

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: /panel-5588/');
        exit;
    }
    // Таймаут 2 часа
    if (time() - ($_SESSION['admin_last_active'] ?? 0) > 7200) {
        session_destroy();
        header('Location: /panel-5588/');
        exit;
    }
    $_SESSION['admin_last_active'] = time();
}

function adminLogin($login, $password) {
    if ($login === ADMIN_LOGIN && password_verify($password, ADMIN_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login']     = $login;
        $_SESSION['admin_last_active'] = time();
        return true;
    }
    return false;
}

/**
 * Проверяет авторизацию: супер-админ ИЛИ модератор с указанным разрешением.
 * Если никто не залогинен — редирект на /panel-5588/
 */
function requireAuthAny(string $modPermission = ''): void {
    if (isAdminLoggedIn()) {
        if (time() - ($_SESSION['admin_last_active'] ?? 0) > 7200) {
            session_destroy();
            header('Location: /panel-5588/');
            exit;
        }
        $_SESSION['admin_last_active'] = time();
        return;
    }
    if (isModeratorLoggedIn()) {
        requireModeratorAuth($modPermission);
        return;
    }
    header('Location: /panel-5588/');
    exit;
}
?>