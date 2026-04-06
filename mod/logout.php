<?php
session_start();
// Удаляем только переменные модератора
unset(
    $_SESSION['moderator_id'],
    $_SESSION['moderator_name'],
    $_SESSION['moderator_permissions'],
    $_SESSION['moderator_last_active']
);
header('Location: /mod/');
exit;
