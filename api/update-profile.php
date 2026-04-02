<?php
// api/update-profile.php — Обновление профиля пользователя
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходимо авторизоваться']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();

    // ============================================
    // ОБНОВЛЕНИЕ ИМЕНИ
    // ============================================
    if ($action === 'update_name') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) {
            echo json_encode(['success' => false, 'error' => 'Имя слишком короткое']); exit;
        }
        if (mb_strlen($name) > 100) {
            echo json_encode(['success' => false, 'error' => 'Имя слишком длинное']); exit;
        }
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$name, $userId]);
        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true]); exit;
    }

    // ============================================
    // СМЕНА ПАРОЛЯ
    // ============================================
    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 8) {
            echo json_encode(['success' => false, 'error' => 'Новый пароль минимум 8 символов']); exit;
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($oldPass, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Текущий пароль неверный']); exit;
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        echo json_encode(['success' => true]); exit;
    }

    // ============================================
    // ЗАПРОС СМЕНЫ EMAIL — отправить код
    // ============================================
    if ($action === 'request_email_change') {
        $newEmail = trim($_POST['new_email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Некорректный email']); exit;
        }

        // Проверяем, не занят ли email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Этот email уже используется']); exit;
        }

        // Генерируем 6-значный код
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 15 * 60); // 15 минут

        // Удаляем старые коды смены email для этого пользователя
        $pdo->prepare("DELETE FROM verification_codes WHERE email = ? AND email LIKE 'change_email_%'")->execute([$newEmail]);

        // Сохраняем код (используем поле email с префиксом для отличия от регистрации)
        $stmt = $pdo->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$newEmail, $code, $expires]);

        // Сохраняем новый email в сессии (временно)
        $_SESSION['pending_email'] = $newEmail;

        // Отправляем код через стандартную функцию sendVerificationEmail($to, $code, $name)
        $userNameForEmail = $_SESSION["user_name"] ?? "Пользователь";
        $sent = sendVerificationEmail($newEmail, $code, $userNameForEmail);
        if (!$sent) {
            echo json_encode(['success' => false, 'error' => 'Не удалось отправить письмо']); exit;
        }

        echo json_encode(['success' => true]); exit;
    }

    // ============================================
    // ПОДТВЕРЖДЕНИЕ СМЕНЫ EMAIL — проверить код
    // ============================================
    if ($action === 'verify_email_change') {
        $code     = trim($_POST['code'] ?? '');
        $newEmail = $_SESSION['pending_email'] ?? '';

        if (!$newEmail) {
            echo json_encode(['success' => false, 'error' => 'Сессия истекла, начните заново']); exit;
        }

        // Проверяем код
        $stmt = $pdo->prepare("
            SELECT id FROM verification_codes
            WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$newEmail, $code]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Неверный или истёкший код']); exit;
        }

        // Помечаем код как использованный
        $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?")->execute([$row['id']]);

        // Обновляем email
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);

        // Обновляем сессию
        $_SESSION['user_email'] = $newEmail;
        unset($_SESSION['pending_email']);

        echo json_encode(['success' => true, 'new_email' => $newEmail]); exit;
    }

    // ============================================
    // СОХРАНЕНИЕ НАСТРОЕК УВЕДОМЛЕНИЙ
    // ============================================
    if ($action === 'save_notification') {
        $type  = $_POST['type']  ?? '';
        $value = $_POST['value'] ?? '0';

        if ($type === 'new_services') {
            $val = $value === '1' ? 1 : 0;
            // Добавляем колонку если нет (graceful)
            try {
                $pdo->prepare("UPDATE users SET notify_new_services = ? WHERE id = ?")->execute([$val, $userId]);
            } catch (PDOException $e) {
                // Колонка не существует — сообщаем в ответе
                echo json_encode(['success' => false, 'error' => 'Требуется обновление БД (см. инструкцию)']); exit;
            }
            echo json_encode(['success' => true]); exit;
        }

        if ($type === 'views_freq_full') {
            $freq = in_array($value, ['weekly', 'monthly', 'off']) ? $value : 'off';
            try {
                $pdo->prepare("UPDATE users SET notify_views_freq = ? WHERE id = ?")->execute([$freq, $userId]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Требуется обновление БД (см. инструкцию)']); exit;
            }
            echo json_encode(['success' => true]); exit;
        }

        echo json_encode(['success' => false, 'error' => 'Неизвестный тип уведомления']); exit;
    }

    // ============================================
    // ОТПРАВИТЬ СООБЩЕНИЕ АДМИНИСТРАЦИИ
    // ============================================
    if ($action === 'send_contact') {
        $subject = trim($_POST['subject'] ?? 'Без темы');
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Сообщение не может быть пустым']); exit;
        }
        if (mb_strlen($message) > 2000) {
            echo json_encode(['success' => false, 'error' => 'Сообщение слишком длинное']); exit;
        }

        $userEmail = $_SESSION['user_email'] ?? '';
        $userName  = $_SESSION['user_name']  ?? 'Пользователь';

        $emailSubject = "РЎРѕРѕР±С‰РµРЅРёРµ РѕС‚ РїРѕР»СЊР·РѕРІР°С‚РµР»СЏ: " . $subject;
        $emailBody = "<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:30px;\">"
            . "<h2 style=\"color:#3B6CF4;\">" . "\xd0\x9d\xd0\xbe\xd0\xb2\xd0\xbe\xd0\xb5 \xd1\x81\xd0\xbe\xd0\xbe\xd0\xb1\xd1\x89\xd0\xb5\xd0\xbd\xd0\xb8\xd0\xb5 \xd0\xbe\xd1\x82 \xd0\xbf\xd0\xbe\xd0\xbb\xd1\x8c\xd0\xb7\xd0\xbe\xd0\xb2\xd0\xb0\xd1\x82\xd0\xb5\xd0\xbb\xd1\x8f" . "</h2>"
            . "<table style=\"width:100%;border-collapse:collapse;margin:16px 0;\">"
            . "<tr><td style=\"padding:8px 0;color:#64748B;font-size:14px;width:80px;\">" . "\xd0\x9e\xd1\x82:" . "</td>"
            . "<td style=\"font-weight:600;\">" . htmlspecialchars($userName) . " &lt;" . htmlspecialchars($userEmail) . "&gt;</td></tr>"
            . "<tr><td style=\"padding:8px 0;color:#64748B;font-size:14px;\">ID:</td><td>" . $userId . "</td></tr>"
            . "<tr><td style=\"padding:8px 0;color:#64748B;font-size:14px;\">" . "\xd0\xa2\xd0\xb5\xd0\xbc\xd0\xb0:" . "</td>"
            . "<td style=\"font-weight:600;\">" . htmlspecialchars($subject) . "</td></tr>"
            . "</table>"
            . "<div style=\"background:#F8FAFC;border-radius:12px;padding:20px;border-left:4px solid #3B6CF4;\">"
            . "<p style=\"margin:0;line-height:1.6;\">" . nl2br(htmlspecialchars($message)) . "</p>"
            . "</div></div>";
        $adminEmail = 'support@poisq.com';
        try {
            require_once __DIR__ . '/../phpmailer/Exception.php';
            require_once __DIR__ . '/../phpmailer/PHPMailer.php';
            require_once __DIR__ . '/../phpmailer/SMTP.php';
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = SMTP_HOST;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = SMTP_USER;
            $mailer->Password   = SMTP_PASS;
            $mailer->SMTPSecure = SMTP_SECURE;
            $mailer->Port       = SMTP_PORT;
            $mailer->CharSet    = 'UTF-8';
            $mailer->SMTPAutoTLS = false;
            $mailer->SMTPOptions = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false, "allow_self_signed" => true]];
            $mailer->Encoding = "base64";
            $mailer->Timeout    = 30;
            $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mailer->addAddress($adminEmail, 'Admin Poisq');
            $mailer->addReplyTo($userEmail, $userName);
            $mailer->isHTML(true);
            $mailer->Subject = $emailSubject;
            $mailer->Body    = $emailBody;
            $mailer->send();
            $sent = true;
        } catch (Exception $e) {
            error_log('Contact email error: ' . $e->getMessage());
            $sent = false;
        }

        if ($sent) {
            echo json_encode(['success' => true]); exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка отправки. Попробуйте позже.']); exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);


    // ============================================
    // РЎРћРҐР РђРќР•РќРР• Р“РћР РћР”Рђ РџРћР›Р¬Р—РћР’РђРўР•Р›РЇ
    // ============================================
    if ($action === 'save_city') {
        $cityId = intval($_POST['city_id'] ?? 0);
        if ($cityId <= 0) {
            echo json_encode(['success' => false, 'error' => 'РќРµРєРѕСЂСЂРµРєС‚РЅС‹Р№ РіРѕСЂРѕРґ']); exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM cities WHERE id = ?");
        $stmt->execute([$cityId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Р“РѕСЂРѕРґ РЅРµ РЅР°Р№РґРµРЅ']); exit;
        }
        $pdo->prepare("UPDATE users SET city_id = ? WHERE id = ?")->execute([$cityId, $userId]);
        echo json_encode(['success' => true]); exit;
    }

} catch (PDOException $e) {
    error_log('update-profile.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
} catch (Exception $e) {
    error_log('update-profile.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера']);
}
?>