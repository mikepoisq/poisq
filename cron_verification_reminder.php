<?php
// cron_verification_reminder.php
// Запускать ежедневно: php /home/mike/web/poisq.com/public_html/cron_verification_reminder.php
// Или через HTTP: /cron_verification_reminder.php?secret=poisq_cron_2024_secret

$secret = 'poisq_cron_2024_secret';
if (php_sapi_name() !== 'cli' && ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    error_log('cron_verification_reminder: DB connection failed: ' . $e->getMessage());
    exit(1);
}

$today   = date('Y-m-d');
$in7days = date('Y-m-d', strtotime('+7 days'));

// ── 1. Отправляем напоминания за 7 дней до истечения ──────────────
$stmtRemind = $pdo->prepare("
    SELECT s.id as service_id, s.name as service_name, s.verified_until,
           s.verification_token, s.verification_token_expires,
           u.id as user_id, u.name as user_name, u.email as user_email
    FROM services s
    JOIN users u ON s.user_id = u.id
    WHERE s.verified = 1
      AND s.verified_until = ?
      AND (s.verification_token IS NULL OR s.verification_token_expires < NOW())
");
$stmtRemind->execute([$in7days]);
$reminders = $stmtRemind->fetchAll(PDO::FETCH_ASSOC);

foreach ($reminders as $row) {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+14 days'));

    $pdo->prepare("
        UPDATE services
        SET verification_token = ?, verification_token_expires = ?
        WHERE id = ?
    ")->execute([$token, $expires, $row['service_id']]);

    sendVerificationReminderEmail(
        $row['user_email'],
        $row['user_name'],
        $row['service_name'],
        $row['verified_until'],
        $token
    );

    echo "[remind] Service #{$row['service_id']} — token sent to {$row['user_email']}\n";
}

// ── 2. Снимаем значок с просроченных сервисов ─────────────────────
$stmtExpired = $pdo->prepare("
    SELECT s.id as service_id, s.name as service_name,
           u.name as user_name, u.email as user_email
    FROM services s
    JOIN users u ON s.user_id = u.id
    WHERE s.verified = 1
      AND s.verified_until < ?
");
$stmtExpired->execute([$today]);
$expired = $stmtExpired->fetchAll(PDO::FETCH_ASSOC);

foreach ($expired as $row) {
    $pdo->prepare("
        UPDATE services
        SET verified = 0, verified_until = NULL, verification_token = NULL, verification_token_expires = NULL
        WHERE id = ?
    ")->execute([$row['service_id']]);

    sendVerificationExpiredEmail(
        $row['user_email'],
        $row['user_name'],
        $row['service_name']
    );

    echo "[expired] Service #{$row['service_id']} — badge removed, notified {$row['user_email']}\n";
}

echo "Done. Reminders: " . count($reminders) . ", Expired: " . count($expired) . "\n";
