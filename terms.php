<?php
// terms.php — Условия использования
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

// Проверка слотов
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

// Загрузка контента из БД (если есть — перезаписывает статичный)
$termsDbContent = null;
try {
    if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
    $stPage = $pdo->prepare("SELECT content_html FROM pages WHERE slug='terms'");
    $stPage->execute();
    $row = $stPage->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty(trim($row['content_html']))) $termsDbContent = $row['content_html'];
} catch (Exception $e) { $termsDbContent = null; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Условия использования — Poisq</title>
<meta name="description" content="Условия использования сервиса Poisq — каталога русскоязычных специалистов за рубежом.">
<link rel="canonical" href="https://poisq.com/terms.php">
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
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary: #3B6CF4; --primary-light: #EEF2FF; --primary-dark: #2952D9;
  --text: #0F172A; --text-secondary: #64748B; --text-light: #94A3B8;
  --bg: #FFFFFF; --bg-secondary: #F8FAFC;
  --border: #E2E8F0; --border-light: #F1F5F9;
  --danger: #EF4444; --radius-sm: 12px; --radius-xs: 10px;
}
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; background: var(--bg-secondary); color: var(--text); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }
.page-header { position: sticky; top: 0; z-index: 100; background: var(--bg); border-bottom: 1px solid var(--border-light); }
.header-top { display: flex; align-items: center; padding: 10px 14px; height: 56px; gap: 10px; }
.btn-grid { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s; }
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.btn-add { width: 38px; height: 38px; border-radius: var(--radius-xs); border: none; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s, transform 0.1s; text-decoration: none; flex-shrink: 0; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-burger { width: 38px; height: 38px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0; }
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
.page-content { flex: 1; padding: 24px 16px 40px; }
.page-hero { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 8px 0 28px; }
.hero-icon { width: 64px; height: 64px; border-radius: 20px; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
.hero-icon svg { width: 32px; height: 32px; stroke: var(--primary); fill: none; stroke-width: 1.8; }
.hero-title { font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.5px; }
.hero-sub { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
.terms-section { margin-bottom: 28px; }
.terms-section-title { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 10px; letter-spacing: -0.2px; display: flex; align-items: center; gap: 8px; }
.terms-section-num { width: 26px; height: 26px; border-radius: 8px; background: var(--primary-light); color: var(--primary); font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.terms-text { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.7; }
.terms-text p { margin-bottom: 10px; }
.terms-text p:last-child { margin-bottom: 0; }
.terms-text ul { padding-left: 18px; margin-bottom: 10px; }
.terms-text ul li { margin-bottom: 6px; }
.terms-text a { color: var(--primary); text-decoration: none; }
.terms-divider { height: 1px; background: var(--border-light); margin: 0 0 28px; }
.update-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 24px; }
.update-badge svg { width: 14px; height: 14px; stroke: var(--text-light); fill: none; stroke-width: 2; }
.page-footer { padding: 16px 16px 32px; border-top: 1px solid var(--border-light); display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; transition: color 0.15s; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }
/* ANN MODAL */
.ann-modal { position: fixed; inset: 0; z-index: 500; background: var(--bg); transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; max-width: 430px; margin: 0 auto; }
.ann-modal.active { transform: translateY(0); }
.ann-header { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.ann-header-icon { font-size: 20px; }
.ann-title { flex: 1; font-size: 17px; font-weight: 800; color: var(--text); }
.ann-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.ann-city { padding: 12px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid var(--border); font-family: inherit; font-size: 14px; font-weight: 600; background: var(--bg-secondary); color: var(--text); outline: none; appearance: none; }
.ann-content { flex: 1; overflow-y: auto; padding: 16px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; padding: 48px 0; gap: 12px; }
.spinner { width: 28px; height: 28px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-light); font-weight: 500; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 48px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; }
.ann-empty-icon svg { width: 26px; height: 26px; stroke: var(--text-light); fill: none; stroke-width: 1.8; }
.ann-empty h3 { font-size: 17px; font-weight: 800; color: var(--text); }
.ann-empty p { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 10px; }
.ann-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
.ann-item { border-radius: 12px; overflow: hidden; background: var(--bg-secondary); cursor: pointer; }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.ann-date { font-size: 10px; color: var(--text-light); font-weight: 600; padding: 6px 8px 2px; }
.ann-item-name { font-size: 12px; font-weight: 700; color: var(--text); padding: 0 8px 8px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app-container">

  <div class="page-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24">
            <circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/>
            <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
          </svg>
        </button>
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

  <div class="page-content">

<?php if ($termsDbContent): ?>
    <?php echo $termsDbContent; ?>
<?php else: ?>

    <div class="page-hero">
      <div class="hero-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="hero-title">Условия использования</div>
      <div class="hero-sub">Poisq Solutions Ltd</div>
    </div>

    <div class="update-badge">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Последнее обновление: 1 января 2025 года
    </div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">1</span>
        Общие положения
      </div>
      <div class="terms-text">
        <p>Настоящие Условия использования регулируют отношения между Poisq Solutions Ltd (далее — «Poisq», «мы», «нас») и пользователями платформы Poisq (далее — «вы», «пользователь»).</p>
        <p>Используя сайт poisq.com, вы соглашаетесь с настоящими условиями. Если вы не согласны с какими-либо положениями, пожалуйста, прекратите использование платформы.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">2</span>
        Описание сервиса
      </div>
      <div class="terms-text">
        <p>Poisq — это онлайн-каталог русскоязычных специалистов и компаний, работающих за рубежом. Платформа позволяет:</p>
        <ul>
          <li>находить русскоязычных специалистов по категориям и городам;</li>
          <li>размещать информацию о своих услугах в каталоге;</li>
          <li>оставлять и читать отзывы о специалистах.</li>
        </ul>
        <p>Poisq является информационной платформой и не несёт ответственности за качество услуг, оказываемых специалистами, размещёнными в каталоге.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">3</span>
        Регистрация и аккаунт
      </div>
      <div class="terms-text">
        <p>Для размещения сервиса необходима регистрация. При регистрации вы обязуетесь:</p>
        <ul>
          <li>предоставить достоверную информацию о себе;</li>
          <li>не передавать данные аккаунта третьим лицам;</li>
          <li>незамедлительно сообщить нам о несанкционированном доступе к вашему аккаунту.</li>
        </ul>
        <p>Мы оставляем за собой право заблокировать или удалить аккаунт при нарушении настоящих условий без предварительного уведомления.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">4</span>
        Размещение объявлений
      </div>
      <div class="terms-text">
        <p>Размещая сервис на платформе, вы гарантируете, что:</p>
        <ul>
          <li>информация является достоверной и актуальной;</li>
          <li>вы имеете право предлагать указанные услуги;</li>
          <li>контент не нарушает права третьих лиц;</li>
          <li>размещаемый контент не является рекламой незаконных товаров и услуг.</li>
        </ul>
        <p>Каждое объявление проходит ручную модерацию. Мы вправе отказать в публикации или удалить объявление без объяснения причин.</p>
        <p>Базовое размещение бесплатно — до 3 объявлений на аккаунт.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">5</span>
        Запрещённый контент
      </div>
      <div class="terms-text">
        <p>На платформе категорически запрещено размещать:</p>
        <ul>
          <li>ложную, вводящую в заблуждение или мошенническую информацию;</li>
          <li>контент, нарушающий законодательство любой страны;</li>
          <li>материалы, дискриминирующие по признаку расы, пола, религии, национальности;</li>
          <li>спам, рекламу казино, финансовых пирамид и иных сомнительных схем;</li>
          <li>контент сексуального или насильственного характера.</li>
        </ul>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">6</span>
        Конфиденциальность данных
      </div>
      <div class="terms-text">
        <p>Мы собираем и обрабатываем персональные данные в соответствии с Регламентом ЕС о защите данных (GDPR) и законодательством Швейцарии.</p>
        <p>Мы не продаём и не передаём ваши персональные данные третьим лицам в коммерческих целях. Данные используются исключительно для обеспечения работы платформы.</p>
        <p>Вы вправе запросить доступ, исправление или удаление своих данных, написав на <a href="mailto:support@poisq.com">support@poisq.com</a>.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">7</span>
        Ограничение ответственности
      </div>
      <div class="terms-text">
        <p>Poisq предоставляет платформу «как есть» и не гарантирует:</p>
        <ul>
          <li>бесперебойную работу сервиса 24/7;</li>
          <li>точность и актуальность информации в объявлениях;</li>
          <li>качество услуг специалистов, размещённых в каталоге.</li>
        </ul>
        <p>Poisq не является стороной сделок между пользователями и специалистами и не несёт ответственности за их результат.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">8</span>
        Изменение условий
      </div>
      <div class="terms-text">
        <p>Мы вправе изменять настоящие условия в любое время. Об изменениях мы уведомляем пользователей по email не менее чем за 14 дней до вступления изменений в силу.</p>
        <p>Продолжение использования платформы после вступления изменений в силу означает согласие с новыми условиями.</p>
      </div>
    </div>
    <div class="terms-divider"></div>

    <div class="terms-section">
      <div class="terms-section-title">
        <span class="terms-section-num">9</span>
        Применимое право
      </div>
      <div class="terms-text">
        <p>Настоящие условия регулируются законодательством Швейцарии. Все споры подлежат рассмотрению в судах по месту нахождения компании — г. Женева, Швейцария.</p>
        <p>По всем вопросам обращайтесь: <a href="mailto:support@poisq.com">support@poisq.com</a> или через <a href="/contact.php">форму обратной связи</a>.</p>
      </div>
    </div>

  </div>

<?php endif; // end static terms content ?>

  <div class="page-footer">
    <a href="/useful.php" class="footer-link">Полезное</a>
            <a href="/help.php" class="footer-link">Помощь</a>
    <a href="/terms.php" class="footer-link active">Условия</a>
    <a href="/about.php" class="footer-link">О нас</a>
    <a href="/contact.php" class="footer-link">Контакт</a>
  </div>

</div>

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- ANN MODAL -->
<div class="ann-modal" id="annModal">
  <div class="ann-header"><span class="ann-header-icon">📢</span><span class="ann-title">Свежие сервисы</span><button class="ann-close" onclick="closeAnnModal()"><svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div>
  <div class="ann-city"><select id="annCitySelect" class="city-select" onchange="filterByCity()"><option>Загрузка...</option></select></div>
  <div class="ann-content" id="annContent"><div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div></div>
</div>

<script>
let annCityId = null;
async function openAnnModal() {
  const modal = document.getElementById('annModal'), content = document.getElementById('annContent');
  modal.classList.add('active'); document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr = await fetch('/api/get-user-country.php'), cd = await cr.json(), cc = cd.country_code || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc), cities = await cir.json();
    const sel = document.getElementById('annCitySelect'); sel.innerHTML = '';
    cities.forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name_lat ? c.name_lat + ' (' + c.name + ')' : c.name; sel.appendChild(o); if (c.is_capital == 1 && !annCityId) annCityId = c.id; });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch(e) { document.getElementById('annContent').innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg></div><h3>Ошибка загрузки</h3><p>Проверьте соединение</p></div>'; }
}
function closeAnnModal() { document.getElementById('annModal').classList.remove('active'); document.body.style.overflow = ''; }
async function filterByCity() { annCityId = document.getElementById('annCitySelect').value; await loadAnnServices(annCityId); }
async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5'), d = await r.json(), sv = d.services || [];
    if (!sv.length) { content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>Нет новых за последние 5 дней</p></div>'; return; }
    const byCat = {}; sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += '<div class="ann-category"><div class="ann-cat-title">' + cat + '</div><div class="ann-grid">';
      list.forEach(s => { let photo = 'https://via.placeholder.com/200?text=Poisq'; if (s.photo) { try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; } catch(e) {} } const now = new Date(), d2 = new Date(s.created_at), diff = Math.floor((now-d2)/86400000); const ds = diff===0?'Сегодня':diff===1?'Вчера':diff<5?diff+' дн.':d2.toLocaleDateString('ru-RU',{day:'numeric',month:'short'}); html += '<div class="ann-item" onclick="location.href=\'/service/'+s.id+'\'"><img src="'+photo+'" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'"><div class="ann-date">'+ds+'</div><div class="ann-item-name">'+s.name+'</div></div>'; });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) { content.innerHTML = '<div class="ann-empty"><h3>Ошибка</h3></div>'; }
}
</script>
<?php if ($isLoggedIn && $slotsLeft <= 0): ?>
<div id="slotsModal" style="display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,0.5);align-items:flex-end;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:430px;border-radius:24px 24px 0 0;padding:32px 24px 40px;animation:slideUpSlots 0.3s ease-out;">
    <div style="text-align:center;margin-bottom:20px;">
      <div style="width:64px;height:64px;border-radius:50%;background:#FEF2F2;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#EF4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="font-size:19px;font-weight:700;color:#1F2937;margin-bottom:8px;">Все слоты заняты</div>
      <div style="font-size:14px;color:#6B7280;line-height:1.6;">Вы разместили максимальное количество сервисов (3 из 3).<br>Удалите один из существующих, чтобы добавить новый.</div>
    </div>
    <div style="background:#F0FDF4;border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#065F46;line-height:1.5;">💡 Перейдите в <strong>«Мои сервисы»</strong>, чтобы удалить или управлять сервисами</div>
    <a href="/my-services.php" style="display:block;width:100%;padding:14px;background:#3B6CF4;color:white;border-radius:12px;text-align:center;font-size:15px;font-weight:600;text-decoration:none;margin-bottom:10px;">Перейти в Мои сервисы</a>
    <button onclick="closeSlotsModal()" style="display:block;width:100%;padding:14px;background:#F3F4F6;color:#374151;border-radius:12px;border:none;font-size:15px;cursor:pointer;">Закрыть</button>
  </div>
</div>
<style>@keyframes slideUpSlots{from{transform:translateY(100%)}to{transform:translateY(0)}}</style>
<script>
function openSlotsModal(){document.getElementById("slotsModal").style.display="flex";document.body.style.overflow="hidden";}
function closeSlotsModal(){document.getElementById("slotsModal").style.display="none";document.body.style.overflow="";}
document.getElementById("slotsModal").addEventListener("click",function(e){if(e.target===this)closeSlotsModal();});
</script>
<?php endif; ?>
</body>
</html>
