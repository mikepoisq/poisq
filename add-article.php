<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
$isLoggedIn  = isset($_SESSION['user_id']);
$userName    = $isLoggedIn ? ($_SESSION['user_name']   ?? '') : '';
$userAvatar  = $isLoggedIn ? ($_SESSION['user_avatar'] ?? '') : '';
$userInitial = $userName ? strtoupper(mb_substr($userName, 0, 1, 'UTF-8')) : '';

if (!$isLoggedIn) {
    header('Location: /login.php?redirect=/add-article.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

// Получаем категории из БД
$categories = [];
try {
    $pdo = getDbConnection();
    $cats = $pdo->query("SELECT DISTINCT category FROM articles WHERE status='published' AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $categories = $cats ?: ['Здоровье','Финансы','Жильё','Бюрократия','Культура','Образование','Работа','Дети'];
} catch (Exception $e) {
    $categories = ['Здоровье','Финансы','Жильё','Бюрократия','Культура','Образование','Работа','Дети'];
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title']    ?? '');
    $excerpt  = trim($_POST['excerpt']  ?? '');
    $body     = trim($_POST['body']     ?? '');
    $category = trim($_POST['category'] ?? '');
    $author   = trim($_POST['author']   ?? $userName);
    $country  = trim($_POST['country'] ?? 'all');

    if (mb_strlen($title) < 10)   $error = 'Заголовок слишком короткий (минимум 10 символов)';
    elseif (mb_strlen($title) > 90) $error = 'Заголовок слишком длинный (максимум 90 символов)';
    elseif (mb_strlen($excerpt) < 40) $error = 'Краткое описание слишком короткое (минимум 40 символов)';
    elseif (mb_strlen($body) < 500)   $error = 'Текст статьи слишком короткий (минимум 500 символов)';
    elseif (empty($category))         $error = 'Выберите рубрику';
    elseif (mb_strlen($author) < 2)   $error = 'Укажите имя автора';
    else {
        try {
            $photo = '';
            if (!empty($_FILES['photo']['tmp_name'])) {
                $allowed = ['image/jpeg','image/png','image/webp'];
                $mime = mime_content_type($_FILES['photo']['tmp_name']);
                if (!in_array($mime, $allowed)) {
                    $error = 'Допустимые форматы фото: JPG, PNG, WebP';
                } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'Фото не должно превышать 5 МБ';
                } else {
                    $ext  = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $name = 'article_sub_' . uniqid() . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $name;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        $photo = '/uploads/' . $name;
                    }
                }
            }
            if (!$error) {
                $stmt = $pdo->prepare("INSERT INTO article_submissions (user_id, author, title, excerpt, body_md, category, country_code, photo, status, created_at) VALUES (?,?,?,?,?,?,?,?,'pending',NOW())");
                $stmt->execute([$_SESSION['user_id'], $author, $title, $excerpt, $body, $category, $country, $photo]);
                $success = true;
            }
        } catch (Exception $e) {
            $error = 'Ошибка при отправке. Попробуйте ещё раз.';
            error_log('add-article error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Добавить статью — Poisq</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary: #3B6CF4; --primary-light: #EEF2FF;
  --text: #0F172A; --text-secondary: #64748B; --text-light: #94A3B8;
  --bg: #FFFFFF; --bg-secondary: #F8FAFC;
  --border: #E2E8F0; --border-light: #F1F5F9;
  --danger: #EF4444; --success: #10B981;
  --radius-sm: 12px; --radius-xs: 10px;
  --ink: #141414; --muted: #6B6B6B; --hair: rgba(0,0,0,0.08);
  --accent: #1E88E5; --field-bg: #FAFAF8;
  --field-border: rgba(0,0,0,0.15); --disabled: #D4D4D0;
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }

/* HEADER */
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; padding: 10px 14px; height: 56px; gap: 10px; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.btn-back { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.15s; text-decoration: none; }
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.btn-back:active { background: var(--primary); }
.btn-back:active svg { stroke: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; }

/* MASTHEAD */
.masthead { padding: 24px 20px 8px; }
.masthead h1 { font-size: 28px; font-weight: 800; line-height: 1.1; letter-spacing: -0.9px; color: var(--ink); margin-bottom: 8px; }
.masthead-sub { font-size: 14px; line-height: 1.5; color: var(--muted); }

/* PROGRESS */
.progress-wrap { padding: 18px 20px 10px; display: flex; align-items: center; gap: 6px; }
.prog-seg { flex: 1; height: 2px; background: var(--hair); border-radius: 1px; transition: background 0.2s; }
.prog-seg.filled { background: var(--ink); }
.prog-count { font-family: ui-monospace, monospace; font-size: 10px; color: var(--muted); letter-spacing: 1px; margin-left: 4px; white-space: nowrap; }

/* SECTIONS */
.form-section { padding: 22px 20px; border-bottom: 1px solid var(--hair); }
.field-label { font-family: ui-monospace, monospace; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; color: #4A4A4A; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.field-required { color: var(--accent); font-size: 8px; }
.field-hint { font-size: 12px; color: var(--muted); margin-top: 8px; line-height: 1.5; }
.field-error { font-size: 12px; color: var(--danger); margin-top: 6px; font-weight: 600; }

/* COVER UPLOAD */
.cover-upload { width: 100%; height: 200px; border: 1.5px dashed rgba(0,0,0,0.15); background: var(--field-bg); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; cursor: pointer; position: relative; overflow: hidden; border-radius: var(--radius-xs); }
.cover-upload input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.cover-upload-icon svg { width: 28px; height: 22px; stroke: var(--ink); fill: none; stroke-width: 1.3; }
.cover-upload-text { font-size: 14px; font-weight: 600; color: var(--ink); }
.cover-upload-sub { font-size: 12px; color: var(--muted); }
.cover-preview { position: relative; }
.cover-preview img { width: 100%; height: 200px; object-fit: cover; border-radius: var(--radius-xs); display: block; }
.cover-replace { position: absolute; bottom: 10px; right: 10px; background: var(--ink); color: white; font-size: 11px; font-weight: 500; padding: 5px 10px; border-radius: 999px; border: none; cursor: pointer; }

/* SELECT */
.field-select { width: 100%; padding: 14px 16px; border: 1px solid var(--field-border); background: var(--bg); font-size: 15px; color: var(--ink); outline: none; appearance: none; font-family: inherit; cursor: pointer; border-radius: 0; }
.field-select:focus { border-color: var(--ink); }

/* TEXTAREA */
.field-textarea { width: 100%; border: 1px solid var(--field-border); background: var(--bg); font-size: 15px; color: var(--ink); outline: none; font-family: inherit; line-height: 1.55; padding: 14px 16px; resize: vertical; border-radius: 0; }
.field-textarea:focus { border-color: var(--ink); }
.field-textarea.title-ta { font-size: 22px; font-weight: 800; letter-spacing: -0.6px; line-height: 1.15; min-height: 80px; }
.charcount { font-family: ui-monospace, monospace; font-size: 10px; color: var(--muted); text-align: right; margin-top: 4px; letter-spacing: 0.5px; }
.charcount.over { color: #C43636; }

/* TOOLBAR */
.md-toolbar { display: flex; align-items: center; gap: 2px; padding: 6px 8px; border: 1px solid var(--field-border); border-bottom: none; background: var(--field-bg); overflow-x: auto; }
.md-btn { width: 32px; height: 28px; border: none; background: none; cursor: pointer; font-size: 13px; font-weight: 700; color: var(--ink); border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.md-btn:hover { background: var(--border); }
.md-badge { margin-left: auto; font-family: ui-monospace, monospace; font-size: 9px; font-weight: 600; color: var(--muted); letter-spacing: 0.5px; padding: 2px 6px; border: 1px solid var(--border); border-radius: 4px; flex-shrink: 0; }

/* AUTHOR */
.author-row { display: flex; gap: 10px; align-items: center; }
.author-avatar { width: 44px; height: 44px; border-radius: 50%; background: #D6DCE4; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #4A5566; flex-shrink: 0; overflow: hidden; }
.author-avatar img { width: 100%; height: 100%; object-fit: cover; }
.author-input { flex: 1; padding: 12px 14px; border: 1px solid var(--field-border); background: var(--bg); font-size: 15px; color: var(--ink); outline: none; font-family: inherit; border-radius: 0; }
.author-input:focus { border-color: var(--ink); }

/* ERROR BANNER */
.error-banner { margin: 16px 20px 0; padding: 12px 16px; background: #FEF2F2; border-radius: var(--radius-xs); border: 1px solid #FECACA; font-size: 13px; font-weight: 600; color: var(--danger); }

/* FOOTER SUBMIT */
.submit-footer { padding: 22px 20px 40px; }
.btn-submit { width: 100%; padding: 14px 16px; background: var(--ink); color: white; font-size: 15px; font-weight: 600; border: none; cursor: pointer; font-family: inherit; transition: opacity 0.15s; }
.btn-submit:disabled { background: var(--disabled); cursor: not-allowed; }
.btn-submit:active:not(:disabled) { opacity: 0.85; }
.submit-hint { font-size: 12px; color: var(--muted); text-align: center; margin-top: 16px; line-height: 1.5; }

/* SUCCESS */
.success-screen { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 24px; text-align: center; gap: 16px; }
.success-icon { font-size: 56px; }
.success-title { font-size: 24px; font-weight: 800; color: var(--ink); letter-spacing: -0.5px; }
.success-sub { font-size: 15px; color: var(--muted); line-height: 1.6; max-width: 280px; }
.success-btn { margin-top: 8px; display: inline-block; padding: 14px 28px; background: var(--ink); color: white; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: var(--radius-xs); }

/* PAGE FOOTER */
.page-footer { border-top: 1px solid var(--border-light); padding: 16px 16px 32px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; margin-top: auto; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }

::-webkit-scrollbar { display: none; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/desktop.css">
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">

<!-- HEADER -->
<div class="page-header">
  <div class="header-top">
    <div style="width:84px;display:flex;align-items:center;">
      <a href="/useful.php" class="btn-back" aria-label="Назад">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
    </div>
    <div class="header-logo">
      <a href="/"><img src="/logo.png" alt="Poisq" onerror="this.style.display='none'"></a>
    </div>
    <div style="width:84px;display:flex;align-items:center;justify-content:flex-end;">
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</div>

<?php if ($success): ?>

<!-- SUCCESS SCREEN -->
<div class="success-screen">
  <div class="success-icon">✅</div>
  <div class="success-title">Статья отправлена!</div>
  <div class="success-sub">Редакция рассмотрит вашу статью в течение 48 часов. Уведомление придёт на <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'ваш email'); ?>.</div>
  <a href="/useful.php" class="success-btn">← Вернуться в Полезное</a>
</div>

<?php else: ?>

<!-- MASTHEAD -->
<div class="masthead">
  <h1>Поделиться опытом</h1>
  <p class="masthead-sub">Если вы разобрались с чем-то сложным — расскажите. Статья попадёт на модерацию.</p>
</div>

<!-- PROGRESS -->
<div class="progress-wrap">
  <?php for($i=0;$i<6;$i++): ?>
  <div class="prog-seg" id="prog<?php echo $i; ?>"></div>
  <?php endfor; ?>
  <span class="prog-count" id="progCount">0 / 6</span>
</div>

<?php if ($error): ?>
<div class="error-banner">⚠️ <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="articleForm">

<!-- ОБЛОЖКА -->
<div class="form-section">
  <div class="field-label">Обложка</div>
  <div id="coverEmpty" class="cover-upload">
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" onchange="previewCover(this)" id="coverInput">
    <div class="cover-upload-icon">
      <svg viewBox="0 0 28 22"><rect x="1" y="3" width="26" height="18" rx="2"/><circle cx="9" cy="9" r="2.5"/><polyline points="1,16 8,10 13,14 18,9 27,16"/></svg>
    </div>
    <div class="cover-upload-text">Загрузить фото</div>
    <div class="cover-upload-sub">JPG или PNG · до 5 МБ</div>
  </div>
  <div id="coverPreview" class="cover-preview" style="display:none">
    <img id="coverImg" src="" alt="Обложка">
    <button type="button" class="cover-replace" onclick="document.getElementById('coverInput').click()">Заменить</button>
  </div>
  <div class="field-hint">Горизонтальное фото 16:9 смотрится лучше всего.</div>
</div>

<!-- РУБРИКА -->
<div class="form-section">
  <div class="field-label">Рубрика <span class="field-required">●</span></div>
  <select name="category" class="field-select" id="fieldCategory" onchange="updateProgress()" required>
    <option value="">— Выберите рубрику —</option>
    <?php foreach ($categories as $cat): ?>
    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (($_POST['category'] ?? '') === $cat) ? 'selected' : ''; ?>>
      <?php echo htmlspecialchars($cat); ?>
    </option>
    <?php endforeach; ?>
  </select>
</div>

<!-- ЗАГОЛОВОК -->
<div class="form-section">
  <div class="field-label">Заголовок <span class="field-required">●</span></div>
  <textarea name="title" class="field-textarea title-ta" id="fieldTitle" rows="2" maxlength="90"
    placeholder="Как я оформил ВНЖ за 3 месяца"
    oninput="updateCharCount(this, 90, 'titleCount'); updateProgress()" required><?php echo htmlspecialchars($_POST['title'] ?? ''); ?></textarea>
  <div class="charcount" id="titleCount">0 / 90</div>
</div>

<!-- КРАТКОЕ ОПИСАНИЕ -->
<div class="form-section">
  <div class="field-label">Краткое описание <span class="field-required">●</span></div>
  <textarea name="excerpt" class="field-textarea" id="fieldExcerpt" rows="3" maxlength="200"
    placeholder="2 предложения о чём статья"
    oninput="updateCharCount(this, 200, 'excerptCount'); updateProgress()" required><?php echo htmlspecialchars($_POST['excerpt'] ?? ''); ?></textarea>
  <div class="charcount" id="excerptCount">0 / 200</div>
  <div class="field-hint">2 предложения. Появляется под заголовком — приглашает читать.</div>
</div>

<!-- ТЕКСТ СТАТЬИ -->
<div class="form-section">
  <div class="field-label">Текст статьи <span class="field-required">●</span></div>
  <div class="md-toolbar">
    <button type="button" class="md-btn" onclick="mdWrap('**','**')" title="Жирный"><b>B</b></button>
    <button type="button" class="md-btn" onclick="mdWrap('*','*')" title="Курсив"><i>I</i></button>
    <button type="button" class="md-btn" onclick="mdLine('## ')" title="Заголовок">H₂</button>
    <button type="button" class="md-btn" onclick="mdLine('- ')" title="Список">•</button>
    <button type="button" class="md-btn" onclick="mdLine('> ')" title="Цитата">"</button>
    <span class="md-badge">MD</span>
  </div>
  <textarea name="body" class="field-textarea" id="fieldBody" rows="12"
    style="min-height:200px;border-top:none"
    placeholder="Расскажите вашу историю..."
    oninput="updateCharCount(this, 20000, 'bodyCount'); updateProgress()" required><?php echo htmlspecialchars($_POST['body'] ?? ''); ?></textarea>
  <div class="charcount" id="bodyCount">0 / 20000</div>
  <div class="field-hint">Поддерживается Markdown: ## заголовок, **жирный**, *курсив*, > цитата. Минимум 500 символов.</div>
</div>

<!-- АВТОР -->
<div class="form-section">
  <div class="field-label">Автор <span class="field-required">●</span></div>
  <div class="author-row">
    <div class="author-avatar">
      <?php if ($userAvatar): ?>
      <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
      <?php else: ?>
      <?php echo htmlspecialchars($userInitial ?: '?'); ?>
      <?php endif; ?>
    </div>
    <input type="text" name="author" class="author-input" id="fieldAuthor"
      value="<?php echo htmlspecialchars($_POST['author'] ?? $userName); ?>"
      placeholder="Ваше имя" maxlength="60"
      oninput="updateProgress()" required>
  </div>
  <div class="field-hint">Появится в байлайне статьи.</div>
</div>

<!-- СТРАНА -->
<input type="hidden" name="country" id="fieldCountry" value="all">

<!-- SUBMIT -->
<div class="submit-footer">
  <button type="submit" class="btn-submit" id="submitBtn" disabled>
    Отправить на модерацию
  </button>
  <div class="submit-hint">После отправки редакция просмотрит статью в течение 48 часов.</div>
</div>

</form>

<?php endif; ?>

<div class="page-footer">
  <a href="/useful.php" class="footer-link active">Полезное</a>
  <a href="/help.php" class="footer-link">Помощь</a>
  <a href="/terms.php" class="footer-link">Условия</a>
  <a href="/about.php" class="footer-link">О нас</a>
  <a href="/contact.php" class="footer-link">Контакт</a>
</div>

</div><!-- /app-container -->

<?php include __DIR__ . '/includes/menu.php'; ?>

<script>
// ПРОГРЕСС
function updateProgress() {
  const fields = [
    document.getElementById('coverImg') && document.getElementById('coverImg').src ? 1 : 0,
    (document.getElementById('fieldCategory')?.value || '').length > 0 ? 1 : 0,
    (document.getElementById('fieldTitle')?.value || '').length >= 10 ? 1 : 0,
    (document.getElementById('fieldExcerpt')?.value || '').length >= 40 ? 1 : 0,
    (document.getElementById('fieldBody')?.value || '').length >= 500 ? 1 : 0,
    (document.getElementById('fieldAuthor')?.value || '').length >= 2 ? 1 : 0,
  ];
  const count = fields.reduce((a,b) => a+b, 0);
  fields.forEach((v, i) => {
    const seg = document.getElementById('prog' + i);
    if (seg) seg.classList.toggle('filled', v === 1);
  });
  document.getElementById('progCount').textContent = count + ' / 6';
  const btn = document.getElementById('submitBtn');
  if (btn) btn.disabled = count < 5; // обложка опциональна
}

// СЧЁТЧИК СИМВОЛОВ
function updateCharCount(el, max, countId) {
  const len = el.value.length;
  const el2 = document.getElementById(countId);
  if (el2) {
    el2.textContent = len + ' / ' + max;
    el2.classList.toggle('over', len > max);
  }
}

// ПРЕВЬЮ ОБЛОЖКИ
function previewCover(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('coverImg').src = e.target.result;
    document.getElementById('coverEmpty').style.display = 'none';
    document.getElementById('coverPreview').style.display = 'block';
    updateProgress();
  };
  reader.readAsDataURL(input.files[0]);
}

// MARKDOWN ПОМОЩНИКИ
function mdWrap(before, after) {
  const ta = document.getElementById('fieldBody');
  if (!ta) return;
  const start = ta.selectionStart, end = ta.selectionEnd;
  const sel = ta.value.substring(start, end);
  ta.value = ta.value.substring(0, start) + before + sel + after + ta.value.substring(end);
  ta.selectionStart = start + before.length;
  ta.selectionEnd   = end + before.length;
  ta.focus();
  updateProgress();
}
function mdLine(prefix) {
  const ta = document.getElementById('fieldBody');
  if (!ta) return;
  const start = ta.selectionStart;
  const lineStart = ta.value.lastIndexOf('\n', start - 1) + 1;
  ta.value = ta.value.substring(0, lineStart) + prefix + ta.value.substring(lineStart);
  ta.selectionStart = ta.selectionEnd = start + prefix.length;
  ta.focus();
  updateProgress();
}

// ИНИТ СЧЁТЧИКОВ при загрузке
window.addEventListener('DOMContentLoaded', function() {
  const titleEl = document.getElementById('fieldTitle');
  const excerptEl = document.getElementById('fieldExcerpt');
  const bodyEl = document.getElementById('fieldBody');
  if (titleEl && titleEl.value) updateCharCount(titleEl, 90, 'titleCount');
  if (excerptEl && excerptEl.value) updateCharCount(excerptEl, 200, 'excerptCount');
  if (bodyEl && bodyEl.value) updateCharCount(bodyEl, 20000, 'bodyCount');
});

// СТРАНА из localStorage
window.addEventListener('DOMContentLoaded', function() {
  const cc = localStorage.getItem('poisq_country') || 'all';
  const fc = document.getElementById('fieldCountry');
  if (fc) fc.value = cc;
  updateProgress();
});
</script>
</body>
</html>