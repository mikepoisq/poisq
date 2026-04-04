<?php
// verify-service.php — Страница подтверждения актуальности сервиса (по токену)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email.php';

$token  = trim($_GET['token'] ?? '');
$action = $_POST['action'] ?? '';

$service      = null;
$tokenInvalid = false;
$message      = '';
$msgType      = '';

if (empty($token)) {
    $tokenInvalid = true;
} else {
    try {
        $pdo  = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.description, s.category, s.verified_until,
                   c.name AS city_name, s.country_code,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email
            FROM services s
            LEFT JOIN cities c ON s.city_id = c.id
            JOIN users u ON s.user_id = u.id
            WHERE s.verification_token = ?
              AND s.verification_token_expires > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$service) {
            $tokenInvalid = true;
        }
    } catch (PDOException $e) {
        error_log('verify-service DB error: ' . $e->getMessage());
        $tokenInvalid = true;
    }
}

// Обработка действия кнопки
if (!$tokenInvalid && $service && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'confirm') {
        $newUntil = date('Y-m-d', strtotime('+3 months'));
        $pdo->prepare("
            UPDATE services
            SET verified = 1, verified_until = ?, verification_token = NULL, verification_token_expires = NULL
            WHERE id = ?
        ")->execute([$newUntil, $service['id']]);

        $dateRu = (new DateTime($newUntil))->format('d.m.Y');
        $message = 'Отлично! Значок Проверено продлён до ' . $dateRu;
        $msgType = 'success';
        $service  = null; // скрываем кнопки
    } elseif ($action === 'remove') {
        $pdo->prepare("
            UPDATE services
            SET verified = 0, verified_until = NULL, verification_token = NULL, verification_token_expires = NULL
            WHERE id = ?
        ")->execute([$service['id']]);

        $message = 'Значок снят. Ваш сервис остаётся в каталоге.';
        $msgType = 'neutral';
        $service  = null;
    }
}

$categories = [
    "health"=>"Здоровье","legal"=>"Юридические","family"=>"Семья",
    "shops"=>"Магазины","home"=>"Дом","education"=>"Образование",
    "business"=>"Бизнес","transport"=>"Транспорт","events"=>"События",
    "it"=>"IT","realestate"=>"Недвижимость"
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Подтверждение актуальности — Poisq</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
:root {
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --border: #E2E8F0;
  --success: #10B981;
  --success-bg: #ECFDF5;
  --danger: #EF4444;
  --shadow-card: 0 2px 12px rgba(0,0,0,0.06);
  --radius: 16px;
  --radius-sm: 10px;
}
body {
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-secondary);
  color: var(--text);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  -webkit-font-smoothing: antialiased;
}
.card {
  background: var(--bg);
  border-radius: var(--radius);
  box-shadow: var(--shadow-card);
  border: 1px solid var(--border);
  width: 100%; max-width: 420px;
  overflow: hidden;
}
.card-header {
  background: var(--primary);
  padding: 28px 24px 20px;
  text-align: center;
}
.logo {
  font-size: 26px; font-weight: 800; color: white; letter-spacing: -1px;
  text-decoration: none; display: inline-block; margin-bottom: 4px;
}
.logo-sub { font-size: 13px; color: rgba(255,255,255,0.75); }
.card-body { padding: 24px; }
.service-name {
  font-size: 19px; font-weight: 800; color: var(--text);
  letter-spacing: -0.4px; margin-bottom: 4px;
}
.service-meta {
  font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;
}
.service-desc {
  font-size: 13px; color: var(--text-secondary); line-height: 1.6;
  margin-bottom: 20px; padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.question {
  font-size: 21px; font-weight: 800; color: var(--text);
  letter-spacing: -0.5px; margin-bottom: 8px; text-align: center;
}
.question-sub {
  font-size: 14px; color: var(--text-secondary); text-align: center;
  margin-bottom: 24px; line-height: 1.5;
}
.btn-group { display: flex; flex-direction: column; gap: 10px; }
.btn {
  display: block; width: 100%;
  padding: 15px 20px;
  border-radius: var(--radius-sm);
  font-size: 16px; font-weight: 700;
  border: none; cursor: pointer;
  font-family: inherit; text-align: center;
  transition: opacity 0.15s, transform 0.1s;
  text-decoration: none;
}
.btn:active { transform: scale(0.98); }
.btn-yes { background: var(--success); color: white; }
.btn-yes:hover { opacity: 0.9; }
.btn-no { background: var(--bg-secondary); color: var(--text-secondary); border: 1.5px solid var(--border); }
.btn-no:hover { background: var(--border); }
.msg-box {
  text-align: center; padding: 32px 24px;
}
.msg-icon { font-size: 48px; margin-bottom: 16px; }
.msg-title { font-size: 20px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.msg-text { font-size: 15px; color: var(--text-secondary); line-height: 1.6; }
.msg-link {
  display: inline-block; margin-top: 20px;
  color: var(--primary); font-weight: 600; text-decoration: none; font-size: 14px;
}
.error-box { text-align: center; padding: 32px 24px; }
.error-icon { font-size: 48px; margin-bottom: 16px; }
.error-title { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
.error-text { font-size: 14px; color: var(--text-secondary); line-height: 1.6; }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <a href="https://poisq.com" class="logo">Poisq</a>
    <div class="logo-sub">Подтверждение актуальности</div>
  </div>

  <?php if ($tokenInvalid): ?>
  <div class="error-box">
    <div class="error-icon">⚠️</div>
    <div class="error-title">Ссылка недействительна</div>
    <div class="error-text">Ссылка недействительна или устарела.<br>Войдите в профиль для управления сервисом.</div>
    <a href="/profile.php" style="display:inline-block;margin-top:20px;color:#3B6CF4;font-weight:600;text-decoration:none;font-size:14px;">Перейти в профиль →</a>
  </div>

  <?php elseif ($message): ?>
  <div class="msg-box">
    <?php if ($msgType === 'success'): ?>
    <div class="msg-icon">✅</div>
    <?php else: ?>
    <div class="msg-icon">👋</div>
    <?php endif; ?>
    <div class="msg-title"><?php if ($msgType === 'success'): ?>Готово!<?php else: ?>Понятно<?php endif; ?></div>
    <div class="msg-text"><?php echo htmlspecialchars($message); ?></div>
    <a href="/profile.php" class="msg-link">Перейти в профиль →</a>
  </div>

  <?php else: ?>
  <div class="card-body">
    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
    <div class="service-meta">
      <?php echo $categories[$service['category']] ?? $service['category']; ?>
      <?php if ($service['city_name']): ?> · <?php echo htmlspecialchars($service['city_name']); ?><?php endif; ?>
    </div>
    <?php if ($service['description']): ?>
    <div class="service-desc"><?php echo htmlspecialchars(mb_substr($service['description'], 0, 150)); ?><?php if (mb_strlen($service['description']) > 150): ?>…<?php endif; ?></div>
    <?php endif; ?>

    <div class="question">Ваш сервис актуален?</div>
    <div class="question-sub">Хотите сохранить значок Проверено на следующие 3 месяца?</div>

    <form method="POST" class="btn-group">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
      <button type="submit" name="action" value="confirm" class="btn btn-yes">✅ Да, всё актуально</button>
      <button type="submit" name="action" value="remove" class="btn btn-no">❌ Нет, снять значок</button>
    </form>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
