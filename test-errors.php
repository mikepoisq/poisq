<?php
// test-errors.php — Показать все ошибки (УДАЛИТЬ ПОСЛЕ ПРОВЕРКИ!)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo '<h2>✅ Тест ошибок включён</h2>';
echo '<p>Теперь открой register.php и попробуй зарегистрироваться ещё раз.</p>';
echo '<p>Все ошибки будут показаны на экране.</p>';
echo '<hr>';
echo '<h3>Проверка PHPMailer:</h3>';

// Проверяем файлы PHPMailer
$files = [
    'phpmailer/PHPMailer.php',
    'phpmailer/SMTP.php',
    'phpmailer/Exception.php',
    'config/email.php',
    'config/database.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ <b>{$file}</b> — найден<br>";
    } else {
        echo "❌ <b>{$file}</b> — НЕ найден<br>";
    }
}

echo '<hr>';
echo '<h3>Проверка подключения к БД:</h3>';
try {
    require_once 'config/database.php';
    $pdo = getDbConnection();
    echo "✅ Подключение к БД успешно!<br>";
} catch (Exception $e) {
    echo "❌ Ошибка БД: " . $e->getMessage() . "<br>";
}
?>