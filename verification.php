<?php
// verification.php — Страница значка Проверено для конкретного сервиса
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$serviceId = (int)($_GET['service_id'] ?? 0);
if ($serviceId <= 0) {
    header('Location: /profile.php');
    exit;
}

require_once 'config/database.php';

$service     = null;
$vr          = null;
$verifError  = $_GET['verif_error'] ?? '';
$verifSuccess = !empty($_GET['verif_success']);

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT id, name, verified, verified_until FROM services WHERE id=? AND user_id=? AND status='approved' LIMIT 1");
    $stmt->execute([$serviceId, $_SESSION['user_id']]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        header('Location: /profile.php');
        exit;
    }

    $stmtVR = $pdo->prepare("
        SELECT * FROM verification_requests
        WHERE service_id=?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmtVR->execute([$serviceId]);
    $vr = $stmtVR->fetch(PDO::FETCH_ASSOC) ?: null;

} catch (PDOException $e) {
    error_log('verification.php DB error: ' . $e->getMessage());
    header('Location: /profile.php');
    exit;
}

$today      = date('Y-m-d');
$isVerified = $service['verified'] && ($service['verified_until'] === null || $service['verified_until'] >= $today);

if ($isVerified) {
    $statusKey = 'approved';
} elseif ($vr && $vr['status'] === 'pending') {
    $statusKey = 'pending';
} elseif ($vr && $vr['status'] === 'rejected') {
    $statusKey = 'rejected';
} else {
    $statusKey = 'none';
}

$errMessages = [
    'agree'           => 'Необходимо согласиться с условиями',
    'already_pending' => 'Заявка уже отправлена',
    'no_file'         => 'Загрузите файл документа',
    'file_size'       => 'Файл слишком большой (максимум 10 МБ)',
    'file_type'       => 'Недопустимый формат файла (jpg, png, pdf)',
    'upload_fail'     => 'Не удалось сохранить файл',
    'db'              => 'Ошибка базы данных',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Значок Проверено — <?php echo htmlspecialchars($service['name']); ?></title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after {
  margin: 0; padding: 0; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

:root {
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark: #2952D9;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --success: #10B981;
  --success-bg: #ECFDF5;
  --warning: #F59E0B;
  --warning-bg: #FFFBEB;
  --danger: #EF4444;
  --danger-bg: #FEF2F2;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-card: 0 2px 12px rgba(0,0,0,0.06);
  --radius: 16px;
  --radius-sm: 10px;
  --radius-xs: 8px;
}

html { -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto; }

body {
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-secondary);
  color: var(--text);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  touch-action: manipulation;
  overflow-y: auto;
}

.app-container {
  max-width: 430px;
  margin: 0 auto;
  background: var(--bg);
  min-height: 100vh; min-height: 100dvh;
  position: relative;
  display: flex; flex-direction: column;
}

/* ── ШАПКА ── */
.header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 16px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0; height: 58px;
  position: sticky; top: 0; z-index: 100;
}

.header-side { display: flex; align-items: center; width: 44px; }
.header-side.right { justify-content: flex-end; }
.header-title { font-size: 17px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }

.btn-icon-header {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none;
  background: var(--bg-secondary);
  color: var(--text);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
  text-decoration: none;
}
.btn-icon-header:active { transform: scale(0.92); background: var(--border); }
.btn-icon-header svg { width: 19px; height: 19px; stroke: currentColor; fill: none; stroke-width: 2.2; }

/* ── КОНТЕНТ ── */
.profile-container {
  flex: 1;
  background: var(--bg-secondary);
  padding-bottom: 32px;
}

/* ── СТРАНИЦА ЗАГОЛОВОК ── */
.page-hero {
  background: var(--bg);
  padding: 20px 20px 18px;
  border-bottom: 1px solid var(--border-light);
  margin-bottom: 12px;
}
.page-hero-name {
  font-size: 20px; font-weight: 800; color: var(--text);
  letter-spacing: -0.5px; margin-bottom: 3px;
}
.page-hero-sub {
  font-size: 13.5px; color: var(--text-secondary); font-weight: 500;
}

/* ── СЕКЦИЯ ── */
.section {
  background: var(--bg);
  border-radius: var(--radius);
  margin: 0 14px 12px;
  overflow: hidden;
  box-shadow: var(--shadow-card);
  border: 1px solid var(--border-light);
}

.section-body {
  padding: 16px;
}

.section-head {
  font-size: 15px; font-weight: 700; color: var(--text);
  letter-spacing: -0.2px; margin-bottom: 8px;
}

.section-text {
  font-size: 13.5px; color: var(--text-secondary); line-height: 1.65;
}

/* ── СТАТУС БЛОКИ ── */
.status-block {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; padding: 28px 20px 24px;
  background: var(--bg);
  border-radius: var(--radius);
  margin: 0 14px 12px;
  box-shadow: var(--shadow-card);
  border: 1px solid var(--border-light);
}

.status-icon {
  width: 64px; height: 64px;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px;
}
.status-icon svg { width: 32px; height: 32px; stroke-width: 2.2; }
.status-icon.green  { background: var(--success-bg); color: var(--success); }
.status-icon.orange { background: var(--warning-bg); color: var(--warning); }
.status-icon.red    { background: var(--danger-bg); color: var(--danger); }

.status-title {
  font-size: 18px; font-weight: 800; color: var(--text);
  letter-spacing: -0.4px; margin-bottom: 8px;
}
.status-desc {
  font-size: 13.5px; color: var(--text-secondary); line-height: 1.6;
  max-width: 300px;
}
.status-meta {
  margin-top: 10px;
  font-size: 12.5px; color: var(--text-light); font-weight: 500;
}

/* ── ПРИЧИНА ОТКАЗА ── */
.reject-reason {
  background: var(--danger-bg);
  border: 1px solid #FECACA;
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 13.5px; color: #991B1B; line-height: 1.6;
  margin: 0 14px 12px;
}
.reject-reason-label {
  font-size: 11.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; margin-bottom: 5px; color: #B91C1C;
}

/* ── ФОРМА ── */
.form-section {
  background: var(--bg);
  border-radius: var(--radius);
  margin: 0 14px 12px;
  overflow: hidden;
  box-shadow: var(--shadow-card);
  border: 1px solid var(--border-light);
  padding: 16px;
}

.form-group {
  margin-bottom: 14px;
}

.form-label {
  display: block;
  font-size: 12px; font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.4px;
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  padding: 10px 13px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 14px; font-family: inherit;
  background: var(--bg-secondary);
  color: var(--text);
  outline: none;
  transition: border-color 0.15s;
}
.form-input:focus { border-color: var(--primary); background: var(--bg); }

.form-checkbox-row {
  display: flex; align-items: flex-start; gap: 11px;
  margin-bottom: 18px; cursor: pointer;
}
.form-checkbox-row input[type="checkbox"] {
  margin-top: 2px; width: 17px; height: 17px;
  flex-shrink: 0; accent-color: var(--primary);
}
.form-checkbox-text {
  font-size: 13px; color: var(--text-secondary); line-height: 1.55;
}

.btn-primary {
  display: block; width: 100%;
  padding: 14px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 15px; font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s;
  text-align: center;
}
.btn-primary:active { transform: scale(0.98); opacity: 0.88; }

/* ── ALERT ── */
.alert {
  margin: 0 14px 12px;
  padding: 12px 14px;
  border-radius: var(--radius-sm);
  font-size: 13.5px; font-weight: 600;
  display: flex; align-items: flex-start; gap: 10px;
}
.alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
.alert-error   { background: var(--danger-bg); color: #991B1B; border: 1px solid #FECACA; }
/* ── КАК ЭТО РАБОТАЕТ ── */
.how-list { display: flex; flex-direction: column; gap: 0; }
.how-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 11px 0;
  border-bottom: 1px solid var(--border-light);
}
.how-item:last-child { border-bottom: none; padding-bottom: 0; }
.how-item:first-child { padding-top: 6px; }
.how-icon { font-size: 17px; flex-shrink: 0; width: 24px; text-align: center; margin-top: 1px; }
.how-title { font-size: 13.5px; font-weight: 700; color: var(--text); margin-bottom: 3px; letter-spacing: -0.1px; }
.how-text  { font-size: 12.5px; color: var(--text-secondary); line-height: 1.6; font-weight: 500; }
</style>
</head>
<body>
<div class="app-container">

  <!-- ШАПКА -->
  <div class="header">
    <div class="header-side">
      <a href="/profile.php" class="btn-icon-header">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      </a>
    </div>
    <span class="header-title">Значок Проверено</span>
    <div class="header-side right"></div>
  </div>

  <div class="profile-container">

    <!-- ЗАГОЛОВОК СТРАНИЦЫ -->
    <div class="page-hero">
      <div class="page-hero-name"><?php echo htmlspecialchars($service['name']); ?></div>
      <div class="page-hero-sub">Значок Проверено</div>
    </div>

    <?php if ($verifSuccess): ?>
    <div class="alert alert-success">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
      Заявка отправлена — мы рассмотрим её в течение 1-2 рабочих дней
    </div>
    <?php endif; ?>

    <?php if ($verifError): ?>
    <div class="alert alert-error">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php echo htmlspecialchars($errMessages[$verifError] ?? 'Произошла ошибка'); ?>
    </div>
    <?php endif; ?>

    <?php if ($statusKey === 'approved'): ?>
    <!-- ── СТАТУС: ОДОБРЕНО ── -->
    <?php $hasValidDate = !empty($service['verified_until']) && $service['verified_until'] >= '2000-01-01'; ?>
    <div class="status-block">
      <div class="status-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <div class="status-title">Значок Проверено активен</div>
      <?php if ($hasValidDate): ?>
      <div class="status-desc">Действует до <?php echo date('d.m.Y', strtotime($service['verified_until'])); ?></div>
      <?php else: ?>
      <div class="status-desc">Активен</div>
      <?php endif; ?>
      <?php if ($hasValidDate): ?>
      <div class="status-meta">Вы получите письмо за 7 дней до истечения срока для подтверждения актуальности</div>
      <?php endif; ?>
    </div>

    <?php elseif ($statusKey === 'pending'): ?>
    <!-- ── СТАТУС: ОЖИДАЕТ ── -->
    <div class="status-block">
      <div class="status-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="status-title">Заявка на рассмотрении</div>
      <div class="status-desc">Мы проверяем ваш документ. Обычно это занимает 1-2 рабочих дня. Вы получите письмо с результатом.</div>
      <?php if (!empty($vr['created_at'])): ?>
      <div class="status-meta">Дата подачи: <?php echo date('d.m.Y', strtotime($vr['created_at'])); ?></div>
      <?php endif; ?>
    </div>

    <?php elseif ($statusKey === 'rejected'): ?>
    <!-- ── СТАТУС: ОТКЛОНЕНО ── -->
    <div class="status-block">
      <div class="status-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      </div>
      <div class="status-title">Заявка отклонена</div>
      <?php if (!empty($vr['created_at'])): ?>
      <div class="status-meta">Дата подачи: <?php echo date('d.m.Y', strtotime($vr['created_at'])); ?></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($vr['admin_comment'])): ?>
    <div class="reject-reason">
      <div class="reject-reason-label">Причина отказа</div>
      <?php echo htmlspecialchars($vr['admin_comment']); ?>
    </div>
    <?php endif; ?>

    <!-- Форма повторной подачи -->
    <form action="/api/submit-verification.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
      <div class="form-section">
        <div class="form-group">
          <label class="form-label">Диплом, лицензия или другой документ</label>
          <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required class="form-input">
        </div>
        <label class="form-checkbox-row">
          <input type="checkbox" name="agree" required>
          <span class="form-checkbox-text">Соглашаюсь получать письмо раз в 3 месяца для подтверждения актуальности сервиса</span>
        </label>
        <button type="submit" class="btn-primary">Подать повторно</button>
      </div>
    </form>

    <?php else: ?>
    <!-- ── СТАТУС: НЕТ ЗАЯВКИ ── -->
    <div class="section">
      <div class="section-body">
        <div class="section-head">Что даёт значок Проверено?</div>
        <div class="section-text">Клиенты видят что вы реальный специалист с подтверждёнными документами. Сервисы со значком получают больше доверия и обращений.</div>
      </div>
    </div>

    <div class="section">
      <div class="section-body">
        <div class="section-head">Как получить?</div>
        <div class="section-text">Загрузите скан диплома, лицензии или другого документа подтверждающего вашу квалификацию. Мы проверим его в течение 1-2 рабочих дней.</div>
      </div>
    </div>

    <form action="/api/submit-verification.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
      <div class="form-section">
        <div class="form-group">
          <label class="form-label">Диплом, лицензия или другой документ</label>
          <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required class="form-input">
        </div>
        <label class="form-checkbox-row">
          <input type="checkbox" name="agree" required>
          <span class="form-checkbox-text">Соглашаюсь получать письмо раз в 3 месяца для подтверждения актуальности сервиса</span>
        </label>
        <button type="submit" class="btn-primary">Отправить на проверку</button>
      </div>
    </form>
    <?php endif; ?>

    <!-- КАК ЭТО РАБОТАЕТ — показывается всегда -->
    <div class="section">
      <div class="section-body">
        <div class="section-head">Как это работает</div>
        <div class="how-list">
          <div class="how-item">
            <div class="how-icon">⭐</div>
            <div class="how-content">
              <div class="how-title">Значок Проверено</div>
              <div class="how-text">Показывает клиентам что вы реальный специалист. Сервисы со значком получают больше доверия.</div>
            </div>
          </div>
          <div class="how-item">
            <div class="how-icon">📄</div>
            <div class="how-content">
              <div class="how-title">Что нужно предоставить</div>
              <div class="how-text">Скан диплома, лицензии, сертификата или другого документа подтверждающего вашу квалификацию.</div>
            </div>
          </div>
          <div class="how-item">
            <div class="how-icon">⏱</div>
            <div class="how-content">
              <div class="how-title">Срок проверки</div>
              <div class="how-text">Мы рассматриваем заявки в течение 1-2 рабочих дней и сообщаем результат на email.</div>
            </div>
          </div>
          <div class="how-item">
            <div class="how-icon">🔄</div>
            <div class="how-content">
              <div class="how-title">Продление каждые 3 месяца</div>
              <div class="how-text">Раз в 3 месяца вам придёт письмо со ссылкой для подтверждения что сервис актуален. Если не подтвердить в течение 7 дней — значок снимается автоматически, но сервис остаётся в каталоге.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /profile-container -->
</div><!-- /app-container -->
</body>
</html>
