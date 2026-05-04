<?php
// config/session.php — единые настройки сессии для всего сайта
//
// Подключать ДО любого вывода в HTML:
//   require_once __DIR__ . '/../config/session.php';
//   (или require_once 'config/session.php'; из корня)
//
// session_start() вызывается здесь — не вызывай его повторно.

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure',   '1');      // HTTPS — обязательно
    ini_set('session.cookie_path',     '/');
    ini_set('session.cookie_httponly', '1');       // защита от JS-доступа к куке
    ini_set('session.use_strict_mode', '1');       // отклонять чужие session ID
    session_start();
}
