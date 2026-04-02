<?php
// cron/notify-views-digest.php
// Рассылает статистику просмотров сервисов пользователям
// weekly — каждый понедельник, monthly — первого числа месяца
// Запускается через cron каждый день, сам определяет кому слать

if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    http_response_code(403);
    die('Access denied');
}
if (isset($_GET['secret']) && $_GET['secret'] !== 'poisq_cron_2025') {
    http_response_code(403);
    die('Wrong secret');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

$pdo = getDbConnection();

$today     = date('N'); // 1=пн, 7=вс
$dayOfMonth = date('j'); // 1-31

echo "[" . date('Y-m-d H:i:s') . "] Запуск notify-views-digest\n";
echo "День недели: {$today}, день месяца: {$dayOfMonth}\n";

// Определяем за какой период считать просмотры
// weekly шлём в понедельник (день 1), monthly — первого числа
$sendWeekly  = ($today == 1);       // понедельник
$sendMonthly = ($dayOfMonth == 1);  // первое число

if (!$sendWeekly && !$sendMonthly) {
    echo "Сегодня не день рассылки. Выход.\n";
    exit;
}

echo "Режим: " . ($sendMonthly ? "monthly" : "weekly") . "\n";

// Период для подсчёта просмотров
if ($sendMonthly) {
    $periodLabel = 'за прошлый месяц';
    $interval    = 'INTERVAL 30 DAY';
    $freq        = 'monthly';
} else {
    $periodLabel = 'за прошлую неделю';
    $interval    = 'INTERVAL 7 DAY';
    $freq        = 'weekly';
}

// Получаем пользователей с нужной частотой уведомлений
$stmt = $pdo->prepare("
    SELECT id, email, name
    FROM users
    WHERE notify_views_freq = ?
      AND is_blocked = 0
");
$stmt->execute([$freq]);
$users = $stmt->fetchAll();

echo "Найдено пользователей для {$freq} дайджеста: " . count($users) . "\n";

if (empty($users)) {
    echo "Нет подписчиков. Выход.\n";
    exit;
}

$sent    = 0;
$skipped = 0;

foreach ($users as $user) {
    // Получаем все approved сервисы пользователя
    $stmtSvc = $pdo->prepare("
        SELECT s.id, s.name, s.category, s.views, s.rating, s.reviews_count,
               c.name as city_name
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.user_id = ?
          AND s.status = 'approved'
        ORDER BY s.views DESC
    ");
    $stmtSvc->execute([$user['id']]);
    $services = $stmtSvc->fetchAll();

    if (empty($services)) {
        $skipped++;
        echo "  [{$user['email']}] Нет сервисов — пропуск\n";
        continue;
    }

    // Считаем суммарные просмотры
    $totalViews = array_sum(array_column($services, 'views'));

    $result = sendViewsDigestEmail(
        $user['email'],
        $user['name'],
        $services,
        $totalViews,
        $periodLabel,
        $freq
    );

    if ($result) {
        $sent++;
        echo "  [{$user['email']}] ✓ Отправлено\n";
    } else {
        echo "  [{$user['email']}] ✗ Ошибка\n";
    }
}

echo "\nГотово: отправлено={$sent}, пропущено={$skipped}\n";
echo "[" . date('Y-m-d H:i:s') . "] Завершено\n";

// ══════════════════════════════════════════
// ФУНКЦИЯ ОТПРАВКИ EMAIL
// ══════════════════════════════════════════
function sendViewsDigestEmail($to, $name, $services, $totalViews, $periodLabel, $freq) {
    require_once __DIR__ . '/../phpmailer/Exception.php';
    require_once __DIR__ . '/../phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host        = SMTP_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = SMTP_USER;
        $mail->Password    = SMTP_PASS;
        $mail->SMTPSecure  = SMTP_SECURE;
        $mail->Port        = SMTP_PORT;
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->SMTPAutoTLS = false;
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]];
        $mail->Timeout = 30;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->isHTML(true);

        $freqLabel = $freq === 'monthly' ? 'Месячный' : 'Еженедельный';
        $mail->Subject = '=?UTF-8?B?' . base64_encode($freqLabel . ' отчёт — Poisq') . '?=';

        // Строки таблицы сервисов
        $rows = '';
        foreach ($services as $i => $svc) {
            $bg      = $i % 2 === 0 ? '#F9FAFB' : '#FFFFFF';
            $stars   = str_repeat('★', (int)round($svc['rating'])) . str_repeat('☆', 5 - (int)round($svc['rating']));
            $link    = 'https://poisq.com/service.php?id=' . (int)$svc['id'];
            $city    = htmlspecialchars($svc['city_name'] ?? '—');
            $rows .= '
            <tr style="background:' . $bg . ';">
                <td style="padding:12px 14px;font-size:13px;color:#0F172A;font-weight:600;">
                    <a href="' . $link . '" style="color:#3B6CF4;text-decoration:none;">' . htmlspecialchars($svc['name']) . '</a>
                </td>
                <td style="padding:12px 14px;font-size:12px;color:#64748B;text-align:center;">' . $city . '</td>
                <td style="padding:12px 14px;font-size:14px;font-weight:800;color:#0F172A;text-align:center;">' . number_format($svc['views'], 0, '.', ' ') . '</td>
                <td style="padding:12px 14px;font-size:12px;color:#F59E0B;text-align:center;">' . $stars . '</td>
            </tr>';
        }

        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#F5F5F7;font-family:Arial,sans-serif;">
        <div style="max-width:580px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

            <!-- Логотип -->
            <div style="background:#fff;padding:24px 30px;text-align:center;border-bottom:1px solid #E5E7EB;">
                <img src="https://poisq.com/logo.png" alt="Poisq" style="max-width:140px;height:auto;">
            </div>

            <!-- Шапка -->
            <div style="background:#3B6CF4;padding:24px 30px;text-align:center;">
                <div style="font-size:28px;margin-bottom:8px;">📊</div>
                <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;">' . $freqLabel . ' отчёт по просмотрам</h1>
                <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;">' . $periodLabel . '</p>
            </div>

            <!-- Итого -->
            <div style="padding:24px 30px;text-align:center;border-bottom:1px solid #E5E7EB;">
                <p style="margin:0 0 4px;font-size:14px;color:#64748B;">Здравствуйте, <strong>' . htmlspecialchars($name) . '</strong>! Ваши сервисы просмотрели:</p>
                <div style="font-size:48px;font-weight:800;color:#3B6CF4;margin:10px 0;">' . number_format($totalViews, 0, '.', ' ') . '</div>
                <p style="margin:0;font-size:14px;color:#64748B;">раз ' . $periodLabel . '</p>
            </div>

            <!-- Таблица -->
            <div style="padding:20px 20px;">
                <table style="width:100%;border-collapse:collapse;border-radius:8px;overflow:hidden;border:1px solid #E5E7EB;">
                    <thead>
                        <tr style="background:#F1F5F9;">
                            <th style="padding:10px 14px;font-size:12px;color:#64748B;text-align:left;font-weight:700;">Сервис</th>
                            <th style="padding:10px 14px;font-size:12px;color:#64748B;text-align:center;font-weight:700;">Город</th>
                            <th style="padding:10px 14px;font-size:12px;color:#64748B;text-align:center;font-weight:700;">👁 Просмотры</th>
                            <th style="padding:10px 14px;font-size:12px;color:#64748B;text-align:center;font-weight:700;">Рейтинг</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>

            <!-- Кнопка -->
            <div style="padding:0 20px 28px;text-align:center;">
                <a href="https://poisq.com/my-services.php"
                   style="display:inline-block;background:#3B6CF4;color:#fff;padding:13px 32px;
                          text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;">
                    Управление сервисами
                </a>
            </div>

            <!-- Отписка -->
            <div style="padding:0 20px 20px;text-align:center;">
                <p style="font-size:12px;color:#9CA3AF;line-height:1.5;">
                    Вы получаете этот отчёт потому что включили статистику просмотров.<br>
                    <a href="https://poisq.com/profile.php" style="color:#3B6CF4;">Изменить настройки</a>
                    в разделе Профиль → Уведомления
                </p>
            </div>

            <!-- Футер -->
            <div style="background:#F5F5F7;padding:16px;text-align:center;border-top:1px solid #E5E7EB;">
                <p style="margin:0;font-size:12px;color:#9CA3AF;">© 2025 Poisq — каталог русскоязычных сервисов</p>
            </div>

        </div>
        </body>
        </html>';

        $mail->AltBody = "Здравствуйте, {$name}!\n\n" .
            "Отчёт по просмотрам {$periodLabel}.\n\n" .
            "Всего просмотров: {$totalViews}\n\n" .
            "Управление сервисами: https://poisq.com/my-services.php\n\n" .
            "© 2025 Poisq";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('notify-views-digest email error: ' . $e->getMessage());
        return false;
    }
}
