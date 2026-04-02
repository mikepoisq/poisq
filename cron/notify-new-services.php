<?php
// cron/notify-new-services.php
// Рассылает email пользователям у которых включено notify_new_services = 1
// о новых сервисах в их городе за последние 24 часа
// Запускается через cron раз в сутки

// Защита от запуска через браузер
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

echo "[" . date('Y-m-d H:i:s') . "] Запуск notify-new-services\n";

// Шаг 1: Получаем всех пользователей у которых включены уведомления
$stmt = $pdo->query("
    SELECT id, email, name, city_id
    FROM users
    WHERE notify_new_services = 1
      AND is_blocked = 0
");
$users = $stmt->fetchAll();

echo "Найдено пользователей с подпиской: " . count($users) . "\n";

if (empty($users)) {
    echo "Нет подписчиков. Выход.\n";
    exit;
}

$sent = 0;
$skipped = 0;

foreach ($users as $user) {
    // Шаг 2: Определяем город пользователя
    // Сначала берём city_id из профиля пользователя
    // Если не указан — берём из его сервисов
    // Если сервисов нет — пропускаем
    $userCities = [];

    if (!empty($user['city_id'])) {
        // Город указан в профиле
        $stmtCity = $pdo->prepare("SELECT id as city_id, name as city_name FROM cities WHERE id = ?");
        $stmtCity->execute([$user['city_id']]);
        $cityRow = $stmtCity->fetch();
        if ($cityRow) $userCities[] = $cityRow;
    }

    if (empty($userCities)) {
        // Берём из сервисов пользователя
        $stmtCities = $pdo->prepare("
            SELECT DISTINCT s.city_id, c.name as city_name
            FROM services s
            JOIN cities c ON s.city_id = c.id
            WHERE s.user_id = ?
              AND s.city_id IS NOT NULL
        ");
        $stmtCities->execute([$user['id']]);
        $userCities = $stmtCities->fetchAll();
    }

    if (empty($userCities)) {
        $skipped++;
        continue;
    }

    // Шаг 3: Для каждого города ищем новые сервисы за последние 24 часа
    $allNewServices = [];

    foreach ($userCities as $city) {
        $stmtNew = $pdo->prepare("
            SELECT s.id, s.name, s.category, s.photo, c.name as city_name
            FROM services s
            JOIN cities c ON s.city_id = c.id
            WHERE s.city_id = ?
              AND s.status = 'approved'
              AND s.is_visible = 1
              AND s.created_at >= NOW() - INTERVAL 24 HOUR
            ORDER BY s.created_at DESC
            LIMIT 5
        ");
        $stmtNew->execute([$city['city_id']]);
        $newServices = $stmtNew->fetchAll();

        foreach ($newServices as $svc) {
            $allNewServices[] = $svc;
        }
    }

    // Если нет новых сервисов — пропускаем пользователя
    if (empty($allNewServices)) {
        $skipped++;
        echo "  [{$user['email']}] Нет новых сервисов — пропуск\n";
        continue;
    }

    // Шаг 4: Отправляем email
    $result = sendNewServicesEmail(
        $user['email'],
        $user['name'],
        $allNewServices
    );

    if ($result) {
        $sent++;
        echo "  [{$user['email']}] ✓ Отправлено ({$result} сервисов)\n";
    } else {
        echo "  [{$user['email']}] ✗ Ошибка отправки\n";
    }
}

echo "\nГотово: отправлено={$sent}, пропущено={$skipped}\n";
echo "[" . date('Y-m-d H:i:s') . "] Завершено\n";

// ══════════════════════════════════════════
// ФУНКЦИЯ ОТПРАВКИ EMAIL
// ══════════════════════════════════════════
function sendNewServicesEmail($to, $name, $services) {
    require_once __DIR__ . '/../phpmailer/Exception.php';
    require_once __DIR__ . '/../phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
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
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Новые сервисы в вашем городе — Poisq') . '?=';

        // Строим карточки сервисов
        $cards = '';
        foreach ($services as $svc) {
            $photo = 'https://poisq.com/logo.png';
            if (!empty($svc['photo'])) {
                $decoded = json_decode($svc['photo'], true);
                if (is_array($decoded) && !empty($decoded[0])) {
                    $photo = 'https://poisq.com' . $decoded[0];
                }
            }
            $link = 'https://poisq.com/service.php?id=' . (int)$svc['id'];
            $cards .= '
            <div style="display:inline-block;width:160px;vertical-align:top;margin:8px;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #E5E7EB;box-shadow:0 1px 4px rgba(0,0,0,0.07);">
                <a href="' . $link . '">
                    <img src="' . htmlspecialchars($photo) . '" width="160" height="120"
                         style="object-fit:cover;display:block;background:#F5F5F7;" alt="">
                </a>
                <div style="padding:10px;">
                    <div style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:4px;line-height:1.3;">
                        ' . htmlspecialchars($svc['name']) . '
                    </div>
                    <div style="font-size:11px;color:#64748B;">
                        📍 ' . htmlspecialchars($svc['city_name']) . '
                    </div>
                    <a href="' . $link . '"
                       style="display:block;margin-top:8px;background:#3B6CF4;color:#fff;text-decoration:none;
                              padding:7px;border-radius:6px;font-size:12px;font-weight:700;text-align:center;">
                        Посмотреть
                    </a>
                </div>
            </div>';
        }

        $count = count($services);
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#F5F5F7;font-family:Arial,sans-serif;">
        <div style="max-width:560px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

            <!-- Логотип -->
            <div style="background:#fff;padding:24px 30px;text-align:center;border-bottom:1px solid #E5E7EB;">
                <img src="https://poisq.com/logo.png" alt="Poisq" style="max-width:140px;height:auto;">
            </div>

            <!-- Шапка -->
            <div style="background:#3B6CF4;padding:24px 30px;text-align:center;">
                <div style="font-size:28px;margin-bottom:8px;">📢</div>
                <h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;">
                    ' . $count . ' ' . num_word($count, 'новый сервис', 'новых сервиса', 'новых сервисов') . ' в вашем городе
                </h1>
            </div>

            <!-- Контент -->
            <div style="padding:28px 20px;">
                <p style="margin:0 0 20px;font-size:15px;color:#374151;">
                    Здравствуйте, <strong>' . htmlspecialchars($name) . '</strong>!<br>
                    За последние 24 часа в вашем городе появились новые специалисты:
                </p>

                <!-- Карточки -->
                <div style="text-align:center;">
                    ' . $cards . '
                </div>

                <!-- Кнопка -->
                <div style="text-align:center;margin-top:24px;">
                    <a href="https://poisq.com"
                       style="display:inline-block;background:#3B6CF4;color:#fff;padding:13px 32px;
                              text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;">
                        Смотреть все сервисы
                    </a>
                </div>

                <!-- Отписка -->
                <p style="margin-top:24px;font-size:12px;color:#9CA3AF;text-align:center;line-height:1.5;">
                    Вы получаете это письмо потому что подписались на уведомления.<br>
                    <a href="https://poisq.com/profile.php" style="color:#3B6CF4;">Отписаться</a>
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
            "За последние 24 часа в вашем городе появилось {$count} новых сервисов.\n\n" .
            "Смотреть: https://poisq.com\n\n" .
            "Отписаться: https://poisq.com/profile.php\n\n" .
            "© 2025 Poisq";

        $mail->send();
        return $count;

    } catch (Exception $e) {
        error_log('notify-new-services email error: ' . $e->getMessage());
        return false;
    }
}

// Склонение чисел: 1 новый, 2 новых, 5 новых
function num_word($n, $one, $two, $five) {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $five;
    if ($n1 > 1 && $n1 < 5) return $two;
    if ($n1 == 1) return $one;
    return $five;
}
