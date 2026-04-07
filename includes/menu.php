<?php
// Подсчёт избранного для залогиненного юзера
$favCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $pdo_menu = getDbConnection();
        $st = $pdo_menu->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
        $st->execute([$_SESSION['user_id']]);
        $favCount = (int)$st->fetchColumn();
    } catch (Exception $e) { $favCount = 0; }
}
// Подсчёт новых статей для залогиненного юзера
$newArticlesCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $pdo_menu2 = getDbConnection();
        // Берём last_visit юзера из БД
        $stLv = $pdo_menu2->prepare("SELECT last_visit FROM users WHERE id = ? LIMIT 1");
        $stLv->execute([$_SESSION['user_id']]);
        $lastVisit = $stLv->fetchColumn();
        if ($lastVisit) {
            // Страна юзера из сессии (определяется по IP)
            $userCountryForMenu = $_SESSION['user_country'] ?? 'fr';
            $stArt = $pdo_menu2->prepare("SELECT COUNT(*) FROM articles WHERE status='published' AND created_at > ? AND (country_code = ? OR country_code = 'all')");
            $stArt->execute([$lastVisit, $userCountryForMenu]);
            $newArticlesCount = (int)$stArt->fetchColumn();
        }
    } catch (Exception $e) { $newArticlesCount = 0; }
}
$menuIsLoggedIn  = isset($_SESSION['user_id']);
$menuUserName    = $_SESSION['user_name']   ?? 'Гость';
$menuUserEmail   = $_SESSION['user_email']  ?? '';
$menuUserAvatar  = $_SESSION['user_avatar'] ?? '';
$menuUserInitial = $menuIsLoggedIn ? strtoupper(mb_substr($menuUserName, 0, 1, 'UTF-8')) : '';
?>
<style>
.side-menu-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.25s}
.side-menu-overlay.active{opacity:1;pointer-events:all}
.side-menu{position:fixed;top:0;right:-320px;width:300px;height:100%;background:var(--bg);z-index:1001;display:flex;flex-direction:column;transition:right 0.28s cubic-bezier(.4,0,.2,1);box-shadow:-4px 0 24px rgba(0,0,0,0.13);overflow:hidden}
.side-menu.active{right:0}
.side-menu-header{padding:28px 20px 20px;background:var(--primary);display:flex;flex-direction:column;gap:4px;flex-shrink:0}
.side-user-avatar{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:white;margin-bottom:8px;overflow:hidden;flex-shrink:0}
.side-user-avatar img{width:100%;height:100%;object-fit:cover}
.side-user-name{font-size:16px;font-weight:700;color:white}
.side-user-sub{font-size:12px;color:rgba(255,255,255,0.75);margin-top:2px}
.side-menu-items{flex:1;overflow-y:auto;padding:8px 0}
.menu-item{display:flex;align-items:center;gap:12px;padding:13px 20px;font-size:15px;color:var(--text);text-decoration:none;transition:background 0.15s;position:relative}
.menu-item:active{background:var(--bg-secondary)}
.menu-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mi-blue{background:#EFF6FF;color:#2563EB}
.mi-green{background:#F0FDF4;color:#16A34A}
.mi-orange{background:#FFF7ED;color:#EA580C}
.mi-red{background:#FFF1F2;color:#E11D48}
.mi-purple{background:#FAF5FF;color:#7C3AED}
.mi-pink{background:#FDF2F8;color:#DB2777}
.mi-gray{background:#F9FAFB;color:#6B7280}
.menu-badge{margin-left:auto;background:var(--primary);color:white;font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center}
.menu-divider{height:1px;background:var(--border-light);margin:6px 16px}
.menu-footer{padding:16px 20px;border-top:1px solid var(--border-light);display:flex;flex-direction:column;gap:8px;flex-shrink:0}
.menu-footer-btn{display:block;text-align:center;padding:12px;border-radius:12px;font-size:15px;font-weight:600;text-decoration:none;transition:opacity 0.15s}
.menu-footer-btn-login{background:var(--primary);color:white}
.menu-footer-btn-logout{background:#FEF2F2;color:#DC2626}
.menu-footer-btn-secondary{display:block;text-align:center;padding:10px;border-radius:12px;font-size:14px;font-weight:500;color:var(--text-secondary);text-decoration:none;border:1px solid var(--border)}
</style>
<div class="side-menu-overlay" id="menuOverlay"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-header">
    <div class="side-user-avatar">
      <?php if ($menuUserAvatar): ?>
        <img src="<?php echo htmlspecialchars($menuUserAvatar); ?>" alt="">
      <?php elseif ($menuIsLoggedIn): ?>
        <?php echo htmlspecialchars($menuUserInitial); ?>
      <?php else: ?>
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      <?php endif; ?>
    </div>
    <div class="side-user-name"><?php echo $menuIsLoggedIn ? htmlspecialchars($menuUserName) : 'Добро пожаловать!'; ?></div>
    <div class="side-user-sub"><?php echo $menuIsLoggedIn ? htmlspecialchars($menuUserEmail) : 'Войдите в аккаунт'; ?></div>
  </div>
  <div class="side-menu-items">
    <?php if ($menuIsLoggedIn): ?>
    <a href="/" class="menu-item">
      <div class="menu-icon mi-blue"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
      Главная
    </a>
    <a href="/profile.php" class="menu-item">
      <span class="menu-icon mi-blue"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      Личный кабинет
    </a>
    <a href="/my-services.php" class="menu-item">
      <span class="menu-icon mi-green"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8M8 12h8M8 17h5"/></svg></span>
      Мои сервисы
    </a>
    <a href="/favorites.php" class="menu-item">
      <span class="menu-icon mi-pink"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span>
      Избранное
      <?php if ($favCount > 0): ?><span class="menu-badge"><?php echo $favCount; ?></span><?php endif; ?>
    </a>
    <div class="menu-divider"></div>
    <?php endif; ?>

    <a href="/add-service.php" class="menu-item">
      <span class="menu-icon mi-orange"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      Добавить сервис
    </a>
    <a href="/useful.php" class="menu-item">
      <span class="menu-icon mi-blue"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></span>
      Полезное
      <?php if ($newArticlesCount > 0): ?><span class="menu-badge"><?php echo $newArticlesCount; ?></span><?php endif; ?>
    </a>
    <a href="/help.php" class="menu-item">
      <span class="menu-icon mi-gray"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
      Помощь
    </a>
    <a href="/contact.php" class="menu-item">
      <span class="menu-icon mi-gray"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
      Контакт
    </a>
  </div>
  <div class="menu-footer">
    <?php if ($menuIsLoggedIn): ?>
    <a href="/logout.php" class="menu-footer-btn menu-footer-btn-logout">Выйти</a>
    <?php else: ?>
    <a href="/login.php" class="menu-footer-btn menu-footer-btn-login">Войти</a>
    <a href="/register.php" class="menu-footer-btn-secondary">Регистрация</a>
    <?php endif; ?>
  </div>
</div>
<script>
function openMenu()  { document.getElementById('sideMenu').classList.add('active'); document.getElementById('menuOverlay').classList.add('active'); document.body.style.overflow='hidden'; }
function closeMenu() { document.getElementById('sideMenu').classList.remove('active'); document.getElementById('menuOverlay').classList.remove('active'); document.body.style.overflow=''; }
document.addEventListener('DOMContentLoaded', function() {
  var t = document.getElementById('menuToggle');
  if (t) t.addEventListener('click', openMenu);
  var o = document.getElementById('menuOverlay');
  if (o) o.addEventListener('click', closeMenu);
});
</script>
