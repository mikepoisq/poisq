<?php
session_start();

define('ADMIN_LOGIN', 'admin_mike');
define('ADMIN_HASH', '$2y$10$0nIAjp1OFvbapE7VZZzVnu/mwm/nyEdH6Y.IWWSP2KRtpfANluNdu');

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
?>