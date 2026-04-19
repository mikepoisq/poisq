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
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';

// Проверка слотов для шапки
$slotsLeft = 3;
if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDbConnection();
        $st = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ? AND status = 'approved'");
        $st->execute([$_SESSION['user_id']]);
        $slotsLeft = max(0, 3 - (int)$st->fetchColumn());
    } catch (Exception $e) { $slotsLeft = 3; }
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Parsedown.php';

$slug    = trim($_GET['slug']    ?? '');
$country = trim($_GET['country'] ?? '');
$id      = (int)($_GET['id']     ?? 0);
$article = null;
$related = [];

try {
    $pdo = getDbConnection();
    if ($slug) {
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        $article = $stmt->fetch();
    } elseif ($id) {
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if ($article) {
            $cc = ($article['country_code'] === 'all') ? 'all' : $article['country_code'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: /article/' . $cc . '/' . $article['slug']);
            exit;
        }
    }
    // Похожие статьи
    if ($article) {
        $stmt2 = $pdo->prepare("SELECT id, title, excerpt, category, slug, photo, read_time, DATE_FORMAT(created_at, '%d.%m.%Y') as date FROM articles WHERE status='published' AND id != ? AND category = ? LIMIT 3");
        $stmt2->execute([$article['id'], $article['category']]);
        $related = $stmt2->fetchAll();
        if (count($related) < 2) {
            $stmt3 = $pdo->prepare("SELECT id, title, excerpt, category, slug, photo, read_time, DATE_FORMAT(created_at, '%d.%m.%Y') as date FROM articles WHERE status='published' AND id != ? ORDER BY created_at DESC LIMIT 3");
            $stmt3->execute([$article['id']]);
            $related = $stmt3->fetchAll();
        }
    }
} catch (Exception $e) {
    error_log('Article error: ' . $e->getMessage());
}

if ($article) {
    $cc = ($article['country_code'] === 'all') ? 'all' : $article['country_code'];
    $canonicalUrl = 'https://poisq.com/article/' . $cc . '/' . $article['slug'];
    $dateFormatted = date('j', strtotime($article['created_at'])) . ' ' .
        ['','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'][
            (int)date('n', strtotime($article['created_at']))
        ] . ' ' . date('Y', strtotime($article['created_at']));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo $article ? htmlspecialchars($article['title']) . ' — Poisq' : '404 — Poisq'; ?></title>
<?php if ($article): ?>
<meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?? ''), 0, 160)); ?>">
<link rel="canonical" href="<?php echo $canonicalUrl; ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($article['title']); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($article['excerpt'] ?? ''), 0, 160)); ?>">
<?php if ($article['photo']): ?><meta property="og:image" content="<?php echo htmlspecialchars($article['photo']); ?>"><?php endif; ?>
<?php endif; ?>
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Poisq">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --radius-sm: 12px;
  --radius-xs: 10px;
  --ink: #141414;
  --body: #2A2A2A;
  --muted: #6B6B6B;
  --hair: rgba(0,0,0,0.08);
  --accent: #1E88E5;
  --quote-bg: #F7F5F1;
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }

/* ── HEADER (как help.php) ── */
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; padding: 10px 14px; height: 56px; gap: 10px; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.btn-back { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.15s; text-decoration: none; }
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.btn-back:active { background: var(--primary); }
.btn-back:active svg { stroke: white; }
.btn-grid { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.15s; }
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { background: var(--primary); }
.btn-grid:active svg { fill: white; }
.btn-add { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; text-decoration: none; flex-shrink: 0; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-add:active { background: var(--primary); color: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }

/* ── ОБЛОЖКА ── */
.article-cover { width: calc(100% - 32px); margin: 20px 16px 0; height: 220px; border-radius: var(--radius-sm); overflow: hidden; background: var(--bg-secondary); }
.article-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* ── OVERLINE + ЗАГОЛОВОК ── */
.article-top { padding: 20px 20px 0; }
.article-overline { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.overline-left { display: flex; align-items: center; gap: 8px; }
.overline-cat { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 10px; letter-spacing: 1.8px; text-transform: uppercase; font-weight: 600; color: var(--accent); text-decoration: none; }
.overline-sep { font-size: 10px; color: var(--muted); }
.overline-country { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 10px; letter-spacing: 1.8px; text-transform: uppercase; font-weight: 600; color: var(--muted); }
.btn-add-article { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px 6px 8px; border: 1px solid var(--ink); border-radius: 999px; background: var(--bg); font-size: 11px; font-weight: 600; color: var(--ink); text-decoration: none; transition: all 0.15s; flex-shrink: 0; }
.btn-add-article:active { background: var(--ink); color: white; }
.btn-add-article svg { width: 10px; height: 10px; stroke: currentColor; fill: none; stroke-width: 1.6; }
.article-h1 { font-size: 28px; font-weight: 800; line-height: 1.1; letter-spacing: -0.9px; color: var(--ink); margin-bottom: 14px; }
.article-lede { font-size: 17px; line-height: 1.5; font-weight: 400; color: #4A4A4A; margin-bottom: 22px; }

/* ── BYLINE ── */
.article-byline { padding: 0 20px 22px; border-bottom: 1px solid var(--hair); display: flex; align-items: center; gap: 12px; }
.byline-avatar { width: 36px; height: 36px; border-radius: 50%; background: #D6DCE4; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #4A5566; flex-shrink: 0; overflow: hidden; }
.byline-avatar img { width: 100%; height: 100%; object-fit: cover; }
.byline-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.byline-meta { font-size: 12px; color: var(--muted); margin-top: 2px; }

/* ── КОНТЕНТ ── */
.article-body { padding: 28px 20px 48px; font-size: 17px; line-height: 1.6; color: var(--body); }
.article-body p { margin-bottom: 16px; }
.article-body h2 { font-size: 24px; font-weight: 700; line-height: 1.2; letter-spacing: -0.4px; color: var(--ink); margin: 36px 0 14px; }
.article-body h3 { font-size: 19px; font-weight: 600; line-height: 1.3; letter-spacing: -0.2px; color: var(--ink); margin: 28px 0 10px; }
.article-body ul, .article-body ol { margin: 14px 0; padding-left: 22px; }
.article-body li { margin-bottom: 8px; }
.article-body blockquote { margin: 24px 0; padding: 16px 20px; border-left: 3px solid var(--ink); background: var(--quote-bg); font-family: ui-serif, Georgia, serif; font-size: 17px; font-style: italic; color: #4A4A4A; line-height: 1.55; }
.article-body strong { font-weight: 600; color: var(--ink); }
.article-body em { font-style: italic; }
.article-body code { font-family: ui-monospace, Menlo, monospace; font-size: 0.92em; background: rgba(0,0,0,0.05); padding: 1px 6px; border-radius: 4px; }
.article-body a { color: var(--accent); text-decoration: underline; }
.article-body img { width: 100%; height: auto; margin: 20px 0; border-radius: var(--radius-sm); }

/* ── END RULE ── */
.article-end { display: flex; align-items: center; gap: 14px; padding: 0 20px 32px; }
.end-line { flex: 1; height: 1px; background: rgba(0,0,0,0.15); }
.end-dots { font-family: ui-monospace, monospace; font-size: 10px; color: var(--muted); letter-spacing: 1.5px; }

/* ── РЕАКЦИИ ── */
.article-reactions { display: flex; gap: 10px; padding: 0 20px 24px; }
.reaction-btn { flex: 1; padding: 12px 8px; border: 1px solid var(--hair); border-radius: 10px; background: var(--bg); font-size: 12px; font-weight: 500; color: var(--ink); cursor: pointer; text-align: center; transition: all 0.15s; }
.reaction-btn:active, .reaction-btn.active { background: var(--ink); color: white; border-color: var(--ink); }

/* ── CTA БАННЕР ── */
.cta-banner { margin: 4px 20px 28px; background: var(--primary-light); border-radius: var(--radius-sm); padding: 16px; display: flex; gap: 12px; align-items: center; text-decoration: none; transition: opacity 0.12s; }
.cta-banner:active { opacity: 0.8; }
.cta-icon { font-size: 28px; flex-shrink: 0; }
.cta-title { font-size: 14px; font-weight: 800; color: var(--primary); margin-bottom: 2px; }
.cta-sub { font-size: 12px; color: var(--text-secondary); line-height: 1.4; }

/* ── ЧИТАЙТЕ ТАКЖЕ ── */
.related-section { padding: 20px 20px 40px; }
.related-header { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
.related-label { font-family: ui-serif, Georgia, serif; font-size: 14px; font-weight: 500; font-style: italic; color: var(--ink); white-space: nowrap; }
.related-line { flex: 1; height: 1px; background: var(--ink); }
.related-item { display: flex; gap: 14px; align-items: flex-start; padding: 16px 0; border-top: 1px solid var(--hair); text-decoration: none; color: inherit; transition: opacity 0.12s; }
.related-item:first-child { border-top: none; }
.related-item:active { opacity: 0.7; }
.related-text { flex: 1; min-width: 0; }
.related-cat { font-family: ui-monospace, monospace; font-size: 10px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: var(--accent); margin-bottom: 5px; display: block; }
.related-title { font-size: 16px; font-weight: 700; line-height: 1.25; letter-spacing: -0.2px; color: var(--ink); margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.related-meta { font-size: 11px; color: var(--muted); }
.related-thumb { width: 72px; height: 72px; flex-shrink: 0; object-fit: cover; display: block; background: var(--bg-secondary); border-radius: var(--radius-xs); }
.related-thumb-ph { width: 72px; height: 72px; flex-shrink: 0; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 24px; }

/* ── FOOTER ── */
.page-footer { border-top: 1px solid var(--border-light); padding: 16px 16px 32px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; margin-top: auto; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }

/* ── 404 ── */
.not-found { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; gap: 12px; }
.not-found-icon { font-size: 56px; }
.not-found-title { font-size: 20px; font-weight: 800; color: var(--ink); }
.not-found-sub { font-size: 14px; color: var(--muted); line-height: 1.5; }
.not-found-btn { margin-top: 8px; display: inline-block; padding: 12px 24px; background: var(--primary); color: white; border-radius: var(--radius-sm); font-size: 15px; font-weight: 700; text-decoration: none; }

/* ── ANN MODAL ── */
.ann-modal { position: fixed; inset: 0; z-index: 500; background: var(--bg); transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; max-width: 430px; margin: 0 auto; }
.ann-modal.active { transform: translateY(0); }
.ann-header { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.ann-header-icon { font-size: 20px; }
.ann-title { flex: 1; font-size: 17px; font-weight: 800; color: var(--text); }
.ann-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.ann-city { padding: 12px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid var(--border); font-size: 14px; font-weight: 600; background: var(--bg-secondary); color: var(--text); outline: none; appearance: none; }
.ann-content { flex: 1; overflow-y: auto; padding: 16px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; padding: 48px 0; gap: 12px; }
.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-light); font-weight: 500; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 48px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; }
.ann-empty-icon svg { width: 26px; height: 26px; stroke: var(--text-light); fill: none; stroke-width: 1.8; }
.ann-empty h3 { font-size: 17px; font-weight: 800; }
.ann-empty p { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 10px; }
.ann-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
.ann-item { border-radius: 12px; overflow: hidden; background: var(--bg-secondary); cursor: pointer; }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.ann-date { font-size: 10px; color: var(--text-light); font-weight: 600; padding: 6px 8px 2px; }
.ann-item-name { font-size: 12px; font-weight: 700; color: var(--text); padding: 0 8px 8px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

::-webkit-scrollbar { display: none; }
@media (min-width: 430px) { .app-container { border-left: 1px solid var(--border-light); border-right: 1px solid var(--border-light); } }
@media (min-width: 1024px) { .app-container { max-width: 720px; padding-top: 64px; } .page-header { display: none; } }
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
    <div style="width:84px;display:flex;align-items:center;gap:6px;">
      <a href="/useful.php" class="btn-back" aria-label="Назад">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
    </div>
    <div class="header-logo">
      <a href="/"><img src="/logo.png" alt="Poisq" onerror="this.style.display='none'"></a>
    </div>
    <div style="width:84px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">

      <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
      <button class="btn-add" onclick="openSlotsModal()" aria-label="Добавить сервис">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <?php else: ?>
      <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </a>
      <?php endif; ?>
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</div>

<?php if ($article): ?>

  <?php if ($article['photo']): ?>
  <div class="article-cover">
    <img src="<?php echo htmlspecialchars($article['photo']); ?>"
         alt="<?php echo htmlspecialchars($article['title']); ?>"
         onerror="this.parentElement.style.display='none'">
  </div>
  <?php endif; ?>

  <div class="article-top">
    <div class="article-overline">
      <div class="overline-left">
        <a href="/useful.php?cat=<?php echo urlencode($article['category']); ?>" class="overline-cat">
          <?php echo htmlspecialchars($article['category']); ?>
        </a>
        <?php if ($article['country_code'] && $article['country_code'] !== 'all'): ?>
        <span class="overline-sep">·</span>
        <span class="overline-country"><?php echo strtoupper(htmlspecialchars($article['country_code'])); ?></span>
        <?php endif; ?>
      </div>
      <a href="<?php echo $isLoggedIn ? '/add-article.php' : '/login.php?redirect=/add-article.php'; ?>" class="btn-add-article">
        <svg viewBox="0 0 10 10"><line x1="5" y1="1" x2="5" y2="9"/><line x1="1" y1="5" x2="9" y2="5"/></svg>
        Добавить статью
      </a>
    </div>

    <h1 class="article-h1"><?php echo htmlspecialchars($article['title']); ?></h1>
    <?php if (!empty($article['excerpt'])): ?>
    <p class="article-lede"><?php echo htmlspecialchars($article['excerpt']); ?></p>
    <?php endif; ?>
  </div>

  <div class="article-byline">
    <div class="byline-avatar">
      <?php echo htmlspecialchars(mb_strtoupper(mb_substr($article['author'] ?? $article['title'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
    </div>
    <div>
      <div class="byline-name"><?php echo htmlspecialchars($article['author'] ?? 'Редакция Poisq'); ?></div>
      <div class="byline-meta"><?php echo $dateFormatted; ?> · <?php echo htmlspecialchars($article['read_time'] ?? '5 мин'); ?> чтения</div>
    </div>
  </div>

  <div class="article-body">
    <?php
      $pd = new Parsedown();
      $pd->setSafeMode(false);
      echo $pd->text($article['content'] ?? '');
    ?>
  </div>

  <div class="article-end">
    <div class="end-line"></div>
    <div class="end-dots">◆ ◆ ◆</div>
    <div class="end-line"></div>
  </div>

  <div class="article-reactions">
    <button class="reaction-btn" id="likeBtn" onclick="toggleLike()">
      👍 Полезно <span id="likeCount" style="margin-left:4px;font-weight:700"></span>
    </button>
    <button class="reaction-btn" onclick="shareArticle()">↗ Поделиться</button>
  </div>

  <a href="/useful.php" class="cta-banner">
    <div class="cta-icon">🔍</div>
    <div>
      <div class="cta-title">Найти специалиста в Poisq</div>
      <div class="cta-sub">Русскоязычные юристы, врачи, бухгалтеры — в вашем городе</div>
    </div>
  </a>

  <?php if (!empty($related)): ?>
  <div class="related-section">
    <div class="related-header">
      <span class="related-label">Читайте также</span>
      <div class="related-line"></div>
    </div>
    <?php foreach ($related as $r): ?>
    <?php $rcc = ($r['country_code'] ?? 'all') === 'all' ? 'all' : ($r['country_code'] ?? 'all'); ?>
    <a href="/article/<?php echo $rcc; ?>/<?php echo htmlspecialchars($r['slug']); ?>" class="related-item">
      <div class="related-text">
        <span class="related-cat"><?php echo htmlspecialchars($r['category'] ?? ''); ?></span>
        <div class="related-title"><?php echo htmlspecialchars($r['title']); ?></div>
        <div class="related-meta"><?php echo htmlspecialchars($r['date'] ?? ''); ?> · <?php echo htmlspecialchars($r['read_time'] ?? '5 мин'); ?> чтения</div>
      </div>
      <?php if (!empty($r['photo'])): ?>
      <img class="related-thumb" src="<?php echo htmlspecialchars($r['photo']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>" loading="lazy">
      <?php else: ?>
      <div class="related-thumb-ph">📰</div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="not-found">
    <div class="not-found-icon">📭</div>
    <div class="not-found-title">Статья не найдена</div>
    <div class="not-found-sub">Возможно, она была удалена или вы перешли по неверной ссылке.</div>
    <a href="/useful.php" class="not-found-btn">← Все статьи</a>
  </div>
<?php endif; ?>

<div class="page-footer">
  <a href="/useful.php"  class="footer-link active">Полезное</a>
  <a href="/help.php"    class="footer-link">Помощь</a>
  <a href="/terms.php"   class="footer-link">Условия</a>
  <a href="/about.php"   class="footer-link">О нас</a>
  <a href="/contact.php" class="footer-link">Контакт</a>
</div>

</div><!-- /app-container -->

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- ANN MODAL -->
<div class="ann-modal" id="annModal">
  <div class="ann-header">
    <span class="ann-header-icon">📢</span>
    <span class="ann-title">Свежие сервисы</span>
    <button class="ann-close" onclick="closeAnnModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="ann-city">
    <select id="annCitySelect" class="city-select" onchange="filterByCity()">
      <option>Загрузка...</option>
    </select>
  </div>
  <div class="ann-content" id="annContent">
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>
  </div>
</div>

<script>
// ── BOOKMARK ──
function saveArticle(btn) {
  const slug = '<?php echo addslashes($article["slug"] ?? ""); ?>';
  const saved = JSON.parse(localStorage.getItem('savedArticles') || '[]');
  const idx = saved.indexOf(slug);
  if (idx === -1) { saved.push(slug); btn.classList.add('active'); btn.textContent = '🔖 Сохранено'; }
  else { saved.splice(idx, 1); btn.classList.remove('active'); btn.textContent = '🔖 Сохранить'; }
  localStorage.setItem('savedArticles', JSON.stringify(saved));
}

// ── SHARE ──
function shareArticle() {
  const url = window.location.href;
  const title = '<?php echo addslashes($article["title"] ?? ""); ?>';
  if (navigator.share) {
    navigator.share({ title, url }).catch(() => {});
  } else {
    navigator.clipboard.writeText(url).then(() => {
      const btn = document.querySelectorAll('.reaction-btn')[2];
      btn.textContent = '✓ Скопировано';
      setTimeout(() => btn.textContent = '↗ Поделиться', 2000);
    });
  }
}

// ── ANN MODAL ──
let annCityId = null;
async function openAnnModal() {
  const modal = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr = await fetch('/api/get-user-country.php');
    const cd = await cr.json();
    const cc = cd.country_code || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc);
    const cities = await cir.json();
    const sel = document.getElementById('annCitySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name_lat ? c.name_lat + ' (' + c.name + ')' : c.name;
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch(e) {
    document.getElementById('annContent').innerHTML = '<div class="ann-empty"><h3>Ошибка</h3><p>Проверьте соединение</p></div>';
  }
}
function closeAnnModal() { document.getElementById('annModal').classList.remove('active'); document.body.style.overflow = ''; }
async function filterByCity() { annCityId = document.getElementById('annCitySelect').value; await loadAnnServices(annCityId); }
async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5');
    const d = await r.json();
    const sv = d.services || [];
    if (!sv.length) { content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>Нет новых за 5 дней</p></div>'; return; }
    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += '<div class="ann-category"><div class="ann-cat-title">' + cat + '</div><div class="ann-grid">';
      list.forEach(s => {
        let photo = 'https://via.placeholder.com/200?text=Poisq';
        if (s.photo) { try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; } catch(e) { photo = s.photo; } }
        const now = new Date(), d2 = new Date(s.created_at);
        const diff = Math.floor((now - d2) / 86400000);
        const ds = diff === 0 ? 'Сегодня' : diff === 1 ? 'Вчера' : diff + ' дн.';
        html += '<div class="ann-item" onclick="location.href=\"/service/' + s.id + '\"">' + '<img src="' + photo + '" alt="' + s.name + '" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'">' + '<div class="ann-date">' + ds + '</div><div class="ann-item-name">' + s.name + '</div></div>';
      });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) { content.innerHTML = '<div class="ann-empty"><h3>Ошибка</h3><p>Не удалось загрузить</p></div>'; }
}

// ── ЛАЙКИ ──
const ARTICLE_SLUG = '<?php echo addslashes($article["slug"] ?? ""); ?>';

async function initLikes() {
  try {
    const r = await fetch('/api/article-like.php?slug=' + encodeURIComponent(ARTICLE_SLUG));
    const d = await r.json();
    updateLikeUI(d.likes, d.liked);
  } catch(e) {}
}

async function toggleLike() {
  const btn = document.getElementById('likeBtn');
  btn.disabled = true;
  try {
    const r = await fetch('/api/article-like.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'slug=' + encodeURIComponent(ARTICLE_SLUG)
    });
    const d = await r.json();
    updateLikeUI(d.likes, d.liked);
  } catch(e) {}
  btn.disabled = false;
}

function updateLikeUI(likes, liked) {
  const btn = document.getElementById('likeBtn');
  const cnt = document.getElementById('likeCount');
  if (btn) btn.classList.toggle('active', liked);
  if (cnt) cnt.textContent = likes > 0 ? likes : '';
}

initLikes();

// ── ИНИТ BOOKMARK ──
(function() {
  const slug = '<?php echo addslashes($article["slug"] ?? ""); ?>';
  const saved = JSON.parse(localStorage.getItem('savedArticles') || '[]');
  if (saved.includes(slug)) {
    const btn = document.querySelectorAll('.reaction-btn')[1];
    if (btn) { btn.classList.add('active'); btn.textContent = '🔖 Сохранено'; }
  }
})();
</script>
</body>
</html>