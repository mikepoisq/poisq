<?php
// test-email.php — Диагностика SMTP
// ВАЖНО: удали этот файл после проверки!

ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testTo = trim($_POST['email'] ?? '');

    require_once __DIR__ . '/phpmailer/Exception.php';
    require_once __DIR__ . '/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->SMTPDebug = 3; // Максимальный вывод отладки
    $mail->Debugoutput = function($str, $level) {
        echo "<div style='font-family:monospace;font-size:12px;padding:3px 6px;border-bottom:1px solid #eee;color:" . ($level >= 3 ? 'red' : '#333') . ";'>" . htmlspecialchars($str) . "</div>";
    };

    try {
        $mail->isSMTP();
        $mail->Host       = 'localhost';
        $mail->SMTPAuth   = false;
        $mail->SMTPSecure = '';
        $mail->Port       = 25;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPAutoTLS = false;
        $mail->Timeout    = 30;

        $mail->setFrom('noreply@poisq.com', 'Poisq');
        $mail->addAddress($testTo);
        $mail->isHTML(true);
        $mail->Subject = 'Тест SMTP — Poisq';
        $mail->Body    = '<h2>✅ Письмо дошло!</h2><p>SMTP работает корректно.</p>';
        $mail->AltBody = 'SMTP работает корректно.';

        echo "<div style='background:#f8fafc;padding:16px;margin-bottom:16px;border-radius:8px;font-family:monospace;font-size:12px;border:1px solid #e2e8f0;'>";
        echo "<strong>📡 Лог подключения:</strong><br><br>";
        $mail->send();
        echo "</div>";
        $result = 'success';
    } catch (Exception $e) {
        echo "</div>";
        $result = 'error';
        $errorMsg = $mail->ErrorInfo;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Тест SMTP — Poisq</title>
<style>
  body { font-family: -apple-system, sans-serif; background: #f5f5f7; margin: 0; padding: 20px; }
  .box { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
  h1 { font-size: 20px; margin: 0 0 6px; }
  .sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
  input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 15px; box-sizing: border-box; margin-bottom: 12px; outline: none; font-family: inherit; }
  input:focus { border-color: #3b6cf4; }
  button { width: 100%; padding: 13px; background: #3b6cf4; color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; }
  .success { background: #ecfdf5; color: #065f46; padding: 14px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; border: 1px solid #a7f3d0; }
  .error { background: #fef2f2; color: #991b1b; padding: 14px 16px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; border: 1px solid #fecaca; font-family: monospace; font-size: 13px; }
  .warn { background: #fffbeb; color: #92400e; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-top: 20px; border: 1px solid #fde68a; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="box">
  <h1>🔧 Диагностика SMTP</h1>
  <p class="sub">Проверка отправки email для Poisq</p>

  <?php if ($result === 'success'): ?>
    <div class="success">✅ Письмо успешно отправлено! Проверьте почту (и папку Спам).</div>
  <?php elseif ($result === 'error'): ?>
    <div class="error">❌ Ошибка: <?php echo htmlspecialchars($errorMsg); ?></div>
  <?php endif; ?>

  <form method="POST">
    <label style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:7px;">Отправить тест на email:</label>
    <input type="email" name="email" placeholder="ваш@email.com"
           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    <button type="submit">Отправить тестовое письмо</button>
  </form>

  <div class="warn">
    ⚠️ <strong>Удали этот файл после проверки!</strong> Он содержит данные SMTP.
  </div>
</div>
</body>
</html>