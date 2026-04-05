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

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Parsedown.php';

// Читаем параметры — новый формат (slug) или старый (id) для обратной совместимости
$slug    = trim($_GET['slug']    ?? '');
$country = trim($_GET['country'] ?? '');
$id      = (int)($_GET['id']     ?? 0);

$article = null;

try {
    $pdo = getDbConnection();

    if ($slug) {
        // Новый формат: /article/fr/slug
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $stmt = $pdo->prepare("
            SELECT * FROM articles
            WHERE slug = ? AND status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $article = $stmt->fetch();
    } elseif ($id) {
        // Старый формат: article.php?id=1 — редиректим на ЧПУ
        $stmt = $pdo->prepare("
            SELECT * FROM articles
            WHERE id = ? AND status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if ($article) {
            $cc = ($article['country_code'] === 'all') ? 'all' : $article['country_code'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: /article/' . $cc . '/' . $article['slug']);
            exit;
        }
    }
} catch (Exception $e) {
    error_log('Article error: ' . $e->getMessage());
}

if ($article) {
    $categoryStyle = [
        'Финансы'  => 'background:#EEF2FF;color:#3B6CF4',
        'Документы'=> 'background:#FEF3C7;color:#D97706',
        'Семья'    => 'background:#FCE7F3;color:#DB2777',
        'Здоровье' => 'background:#ECFDF5;color:#059669',
    ];
    $style = $categoryStyle[$article['category']] ?? 'background:#F1F5F9;color:#64748B';
    $cc    = ($article['country_code'] === 'all') ? 'all' : $article['country_code'];
    $canonicalUrl = 'https://poisq.com/article/' . $cc . '/' . $article['slug'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo $article ? htmlspecialchars($article['title']) . ' — Poisq' : '404 — Poisq'; ?></title>
<?php if ($article): ?>
<meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($article['excerpt']), 0, 160)); ?>">
<link rel="canonical" href="<?php echo $canonicalUrl; ?>">
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary:       #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark:  #2952D9;
  --text:          #0F172A;
  --text-secondary:#64748B;
  --text-light:    #94A3B8;
  --bg:            #FFFFFF;
  --bg-secondary:  #F8FAFC;
  --border:        #E2E8F0;
  --border-light:  #F1F5F9;
  --success:       #10B981;
  --danger:        #EF4444;
  --radius:        16px;
  --radius-sm:     12px;
  --radius-xs:     10px;
  --shadow-sm:     0 1px 4px rgba(0,0,0,0.07);
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }

/* HEADER */
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; justify-content: space-between; padding: 0 14px; height: 56px; }
.header-left { display: flex; align-items: center; width: 84px; }
.header-center { display: flex; justify-content: center; }
.header-center img { height: 36px; width: auto; object-fit: contain; }
.header-right { width: 84px; display: flex; align-items: center; justify-content: flex-end; }
.btn-back { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.15s; text-decoration: none; }
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.btn-back:active { background: var(--primary); }
.btn-back:active svg { stroke: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--text); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--text); }

/* ОБЛОЖКА */
.article-cover { width: 100%; height: 220px; overflow: hidden; background: var(--bg-secondary); flex-shrink: 0; }
.article-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* МЕТА */
.article-meta-bar { padding: 16px 16px 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.article-date-top { font-size: 12px; color: var(--text-light); font-weight: 600; }
.article-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--text-light); }
.article-read-badge { font-size: 12px; color: var(--text-light); font-weight: 600; }
.article-cat-badge { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 99px; }

/* ЗАГОЛОВОК */
.article-title { font-size: 22px; font-weight: 800; color: var(--text); line-height: 1.25; letter-spacing: -0.5px; padding: 12px 16px 0; }

/* КОНТЕНТ */
.article-content { flex: 1; padding: 18px 16px 32px; font-size: 17px; line-height: 1.75; color: var(--text); }
.article-content p { margin-bottom: 20px; }
.article-content h1, .article-content h2 { font-size: 20px; font-weight: 700; margin: 28px 0 12px; }
.article-content h3 { font-size: 18px; font-weight: 600; margin: 24px 0 10px; }
.article-content strong, .article-content b { font-weight: 700; color: var(--text); }
.article-content ul, .article-content ol { padding-left: 22px; margin-bottom: 20px; }
.article-content li { margin-bottom: 8px; line-height: 1.7; }
.article-content img { width: 100%; border-radius: 12px; margin: 20px 0; }
.article-content a { color: var(--primary); text-decoration: underline; }
.article-content blockquote { border-left: 3px solid var(--primary); padding-left: 16px; color: var(--text-secondary); margin: 20px 0; font-style: italic; }
.article-content em { font-style: italic; color: var(--text-secondary); }

/* CTA */
.cta-banner { margin: 4px 16px 28px; background: var(--primary-light); border-radius: var(--radius); padding: 16px; display: flex; gap: 12px; align-items: center; text-decoration: none; transition: transform 0.12s; }
.cta-banner:active { transform: scale(0.98); }
.cta-icon { font-size: 28px; flex-shrink: 0; }
.cta-title { font-size: 14px; font-weight: 800; color: var(--primary); margin-bottom: 2px; }
.cta-sub { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.4; }

/* FOOTER */
.page-footer { background: var(--bg); border-top: 1px solid var(--border-light); padding: 14px 16px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 14px; }
.footer-link { font-size: 12px; color: var(--text-secondary); text-decoration: none; font-weight: 500; }
.footer-link.active { color: var(--primary); font-weight: 700; }

/* 404 */
.not-found { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; gap: 12px; }
.not-found-icon { font-size: 56px; }
.not-found-title { font-size: 20px; font-weight: 800; color: var(--text); }
.not-found-sub { font-size: 14px; color: var(--text-secondary); font-weight: 500; }
.not-found-btn { margin-top: 8px; display: inline-block; padding: 12px 24px; background: var(--primary); color: white; border-radius: var(--radius-sm); font-size: 15px; font-weight: 700; text-decoration: none; transition: transform 0.12s; }
.not-found-btn:active { transform: scale(0.97); }

/* SIDE MENU */
.side-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.4); backdrop-filter: blur(3px); z-index: 399; display: none; }
.side-overlay.active { display: block; }
.side-menu { position: fixed; top: 0; right: -290px; width: 270px; height: 100%; background: var(--bg); z-index: 400; transition: right 0.3s cubic-bezier(.4,0,.2,1); box-shadow: -8px 0 32px rgba(0,0,0,0.12); display: flex; flex-direction: column; border-radius: 20px 0 0 20px; }
.side-menu.active { right: 0; }
.side-menu-head { padding: 28px 20px 20px; background: var(--bg-secondary); border-bottom: 1px solid var(--border-light); }
.side-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 18px; margin-bottom: 10px; overflow: hidden; }
.side-avatar img { width: 100%; height: 100%; object-fit: cover; }
.side-user-name { font-size: 15px; font-weight: 700; color: var(--text); }
.side-user-sub { font-size: 12.5px; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
.side-items { flex: 1; overflow-y: auto; padding: 8px 0; }
.side-item { display: flex; align-items: center; gap: 13px; padding: 13px 20px; color: var(--text); text-decoration: none; font-size: 14.5px; font-weight: 600; transition: background 0.15s; }
.side-item:active { background: var(--bg-secondary); }
.side-item svg { width: 19px; height: 19px; stroke: var(--text-secondary); fill: none; stroke-width: 2; flex-shrink: 0; }
.side-item.highlight { color: var(--primary); }
.side-item.highlight svg { stroke: var(--primary); }
.side-item.danger { color: var(--danger); }
.side-item.danger svg { stroke: var(--danger); }
.side-divider { height: 1px; background: var(--border-light); margin: 6px 20px; }

::-webkit-scrollbar { display: none; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">

<header class="page-header">
  <div class="header-top">
    <div class="header-left">
      <a href="/useful.php" class="btn-back" aria-label="Назад">
        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
    </div>
    <div class="header-center">
      <a href="/index.php"><img src="/logo.png" alt="Poisq"></a>
    </div>
    <div class="header-right">
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<?php if ($article): ?>

  <div class="article-cover">
    <img src="<?php echo htmlspecialchars($article['photo']); ?>"
         alt="<?php echo htmlspecialchars($article['title']); ?>"
         onerror="this.style.display='none'">
  </div>

  <div class="article-meta-bar">
    <span class="article-cat-badge" style="<?php echo $style; ?>">
      <?php echo htmlspecialchars($article['category']); ?>
    </span>
    <span class="article-dot"></span>
    <span class="article-date-top"><?php echo htmlspecialchars(date('d.m.Y', strtotime($article['created_at']))); ?></span>
    <span class="article-dot"></span>
    <span class="article-read-badge"><?php echo htmlspecialchars($article['read_time']); ?> чтения</span>
  </div>

  <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>

  <div class="article-content">
    <?php
      $pd = new Parsedown();
      $pd->setSafeMode(false);
      echo $pd->text($article['content']);
    ?>
  </div>

  <a href="/results.php" class="cta-banner">
    <div class="cta-icon">🔍</div>
    <div>
      <div class="cta-title">Найти специалиста в Poisq</div>
      <div class="cta-sub">Русскоязычные юристы, врачи, бухгалтеры — в вашем городе</div>
    </div>
  </a>

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

</div>

<!-- SIDE MENU -->
<div class="side-overlay" id="menuOverlay" onclick="closeMenu()"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-head">
    <div class="side-avatar">
      <?php if ($isLoggedIn && $userAvatar): ?>
        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
      <?php elseif ($isLoggedIn): ?>
        <?php echo $userInitial; ?>
      <?php else: ?>
        👤
      <?php endif; ?>
    </div>
    <div class="side-user-name"><?php echo $isLoggedIn ? htmlspecialchars($userName) : 'Добро пожаловать!'; ?></div>
    <div class="side-user-sub"><?php echo $isLoggedIn ? htmlspecialchars($_SESSION['user_email'] ?? '') : 'Войдите в аккаунт'; ?></div>
  </div>
  <div class="side-items">
    <?php if ($isLoggedIn): ?>
    <a href="/profile.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Личный кабинет
    </a>
    <a href="/add-service.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Добавить сервис
    </a>
    <?php else: ?>
    <a href="/login.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Войти
    </a>
    <a href="/register.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      Регистрация
    </a>
    <a href="/add-service.php" class="side-item">
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Добавить сервис
    </a>
    <?php endif; ?>
    <a href="/useful.php" class="side-item highlight">
      <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Полезное
    </a>
    <div class="side-divider"></div>
    <a href="/contact.php" class="side-item">
      <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Контакт
    </a>
    <?php if ($isLoggedIn): ?>
    <div class="side-divider"></div>
    <a href="/logout.php" class="side-item danger">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Выйти
    </a>
    <?php endif; ?>
  </div>
</div>

<script>
const menuToggle  = document.getElementById('menuToggle');
const sideMenu    = document.getElementById('sideMenu');
const menuOverlay = document.getElementById('menuOverlay');
menuToggle.addEventListener('click', toggleMenu);
function toggleMenu() {
  const open = sideMenu.classList.toggle('active');
  menuOverlay.classList.toggle('active', open);
  menuToggle.classList.toggle('active', open);
  document.body.style.overflow = open ? 'hidden' : '';
}
function closeMenu() {
  sideMenu.classList.remove('active');
  menuOverlay.classList.remove('active');
  menuToggle.classList.remove('active');
  document.body.style.overflow = '';
}
</script>
</body>
</html>
