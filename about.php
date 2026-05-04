<?php
// about.php — О нас
require_once __DIR__ . '/config/session.php';
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

// Загрузка контента из БД
$aboutDbContent = null;
try {
    if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
    $stPage = $pdo->prepare("SELECT content_html FROM pages WHERE slug='about'");
    $stPage->execute();
    $row = $stPage->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty(trim($row['content_html']))) $aboutDbContent = $row['content_html'];
} catch (Exception $e) { $aboutDbContent = null; }
?>
<?php
$pageTitle       = 'О нас — Poisq';
$pageDescription = 'Poisq — каталог русскоязычных специалистов за рубежом. Узнайте о нашей миссии, команде и ценностях.';
$canonicalUrl    = 'https://poisq.com/about.php';
$ogImage         = 'https://poisq.com/apple-touch-icon.png?v=2';
require_once __DIR__ . '/includes/header.php';
?>
<style>
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

/* CONTENT */
.page-content { flex: 1; }

/* HERO */
.about-hero {
  background: linear-gradient(135deg, #3B6CF4 0%, #2952D9 100%);
  padding: 40px 24px 36px;
  text-align: center;
  position: relative; overflow: hidden;
}
.about-hero::before {
  content: ''; position: absolute; top: -40px; right: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  background: rgba(255,255,255,0.06);
}
.about-hero::after {
  content: ''; position: absolute; bottom: -60px; left: -20px;
  width: 160px; height: 160px; border-radius: 50%;
  background: rgba(255,255,255,0.04);
}
.hero-logo-wrap { margin-bottom: 16px; }
.hero-logo-wrap img { height: 44px; filter: brightness(0) invert(1); }
.about-hero-title { font-size: 24px; font-weight: 800; color: white; margin-bottom: 10px; letter-spacing: -0.5px; line-height: 1.2; }
.about-hero-sub { font-size: 14px; color: rgba(255,255,255,0.8); font-weight: 500; line-height: 1.6; max-width: 300px; margin: 0 auto; }

/* STATS */
.stats-row {
  display: grid; grid-template-columns: repeat(3,1fr);
  border-bottom: 1px solid var(--border-light);
}
.stat-item { padding: 20px 12px; text-align: center; border-right: 1px solid var(--border-light); }
.stat-item:last-child { border-right: none; }
.stat-num { font-size: 22px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; font-weight: 600; color: var(--text-light); line-height: 1.3; }

/* SECTIONS */
.about-section { padding: 28px 16px; border-bottom: 1px solid var(--border-light); }
.about-section:last-child { border-bottom: none; }
.about-section-title { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 14px; letter-spacing: -0.3px; }
.about-text { font-size: 14px; color: var(--text-secondary); font-weight: 500; line-height: 1.7; }
.about-text p { margin-bottom: 12px; }
.about-text p:last-child { margin-bottom: 0; }

/* VALUE CARDS */
.values-grid { display: flex; flex-direction: column; gap: 10px; }
.value-card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; background: var(--bg-secondary); border-radius: 14px; }
.value-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; }
.vi-blue { background: var(--primary-light); }
.vi-green { background: #F0FDF4; }
.vi-orange { background: #FFF7ED; }
.vi-purple { background: #F5F3FF; }
.value-card-text { flex: 1; }
.value-card-title { font-size: 14px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
.value-card-desc { font-size: 13px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; }

/* COUNTRIES */
.countries-list { display: flex; flex-wrap: wrap; gap: 8px; }
.country-chip { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: var(--bg-secondary); border-radius: 99px; font-size: 12px; font-weight: 600; color: var(--text-secondary); }

/* CTA */
.about-cta { margin: 0 16px 28px; padding: 24px 20px; background: var(--primary-light); border-radius: 20px; text-align: center; }
.cta-title { font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
.cta-sub { font-size: 13px; color: var(--text-secondary); font-weight: 500; margin-bottom: 16px; line-height: 1.5; }
.cta-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: white; border: none; border-radius: 12px; font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; transition: all 0.15s; }
.cta-btn:active { transform: scale(0.97); background: var(--primary-dark); }
.cta-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2.5; }

/* FOOTER */
.page-footer { padding: 16px 16px 32px; border-top: 1px solid var(--border-light); display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; }
.footer-link { font-size: 12px; font-weight: 500; color: var(--text-secondary); text-decoration: none; transition: color 0.15s; }
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }


@media (min-width: 1024px) {
  .app-container { max-width: 720px; padding-top: 64px; }
  .page-header { display: none; }
  .page-content { padding: 32px 0 48px; }
}
</style>

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

<?php if ($aboutDbContent): ?>
    <?php echo $aboutDbContent; ?>
<?php else: ?>

    <!-- HERO -->
    <div class="about-hero">
      <div class="hero-logo-wrap">
        <img src="/logo.png" alt="Poisq" onerror="this.style.display='none'">
      </div>
      <div class="about-hero-title">Русскоязычные сервисы там, где вы живёте</div>
      <div class="about-hero-sub">Мы помогаем русскоязычным людям за рубежом находить проверенных специалистов на родном языке</div>
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-item">
        <div class="stat-num">40+</div>
        <div class="stat-label">Стран в каталоге</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">800+</div>
        <div class="stat-label">Городов мира</div>
      </div>
      <div class="stat-item">
        <div class="stat-num">100%</div>
        <div class="stat-label">Бесплатно</div>
      </div>
    </div>

    <!-- MISSION -->
    <div class="about-section">
      <div class="about-section-title">Наша миссия</div>
      <div class="about-text">
        <p>Миллионы русскоязычных людей живут за пределами России и других стран СНГ. Переезд — это всегда стресс: новый язык, новые правила, незнакомая система здравоохранения, юридические нюансы.</p>
        <p>Poisq создан для того, чтобы эмигрант в Париже, Берлине или Тель-Авиве мог за минуту найти русскоязычного врача, юриста или репетитора — специалиста, с которым можно говорить на одном языке.</p>
        <p>Мы верим, что языковой барьер не должен стоять между человеком и нужной помощью.</p>
      </div>
    </div>

    <!-- VALUES -->
    <div class="about-section">
      <div class="about-section-title">Наши ценности</div>
      <div class="values-grid">
        <div class="value-card">
          <div class="value-icon vi-blue">🛡️</div>
          <div class="value-card-text">
            <div class="value-card-title">Доверие и проверка</div>
            <div class="value-card-desc">Каждый сервис проходит ручную модерацию перед публикацией. Мы проверяем достоверность контактов и описания.</div>
          </div>
        </div>
        <div class="value-card">
          <div class="value-icon vi-green">🤝</div>
          <div class="value-card-text">
            <div class="value-card-title">Доступность</div>
            <div class="value-card-desc">Базовое размещение всегда бесплатное. Мы убеждены, что помочь сообществу не должно ничего стоить.</div>
          </div>
        </div>
        <div class="value-card">
          <div class="value-icon vi-orange">🌍</div>
          <div class="value-card-text">
            <div class="value-card-title">Глобальность</div>
            <div class="value-card-desc">Более 40 стран и 800 городов. Где бы вы ни жили — Poisq найдёт специалиста рядом с вами.</div>
          </div>
        </div>
        <div class="value-card">
          <div class="value-icon vi-purple">🔒</div>
          <div class="value-card-text">
            <div class="value-card-title">Приватность</div>
            <div class="value-card-desc">Мы не продаём ваши данные. Никакой рекламы, никаких брокеров данных. Только каталог и ничего лишнего.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- CATEGORIES -->
    <div class="about-section">
      <div class="about-section-title">Что можно найти</div>
      <div class="about-text">
        <p>Poisq охватывает все сферы жизни за рубежом:</p>
      </div>
      <div class="countries-list" style="margin-top:12px">
        <span class="country-chip">🏥 Здоровье</span>
        <span class="country-chip">⚖️ Юристы</span>
        <span class="country-chip">📚 Образование</span>
        <span class="country-chip">👨‍👩‍👧 Семья</span>
        <span class="country-chip">💼 Бизнес</span>
        <span class="country-chip">🏠 Дом и быт</span>
        <span class="country-chip">🚗 Транспорт</span>
        <span class="country-chip">🛒 Магазины</span>
        <span class="country-chip">💻 IT</span>
        <span class="country-chip">🏢 Недвижимость</span>
        <span class="country-chip">📷 События</span>
      </div>
    </div>

    <!-- COMPANY -->
    <div class="about-section">
      <div class="about-section-title">О компании</div>
      <div class="about-text">
        <p>Poisq Solutions Ltd — швейцарская компания, зарегистрированная в Женеве. Мы небольшая команда людей, сами прошедших через эмиграцию и понявших, насколько сложно найти нужного специалиста за рубежом.</p>
        <p>Если у вас есть вопросы, предложения или вы хотите сообщить о проблеме — мы всегда рады обратной связи.</p>
      </div>
    </div>

    <!-- CTA -->
    <div class="about-cta">
      <div class="cta-title">Вы специалист?</div>
      <div class="cta-sub">Разместите свои услуги бесплатно и найдите новых клиентов среди русскоязычной аудитории в вашем городе</div>
      <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
      <button onclick="openSlotsModal()" class="cta-btn">
      <?php else: ?>
      <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="cta-btn">
      <?php endif; ?>
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить сервис
      <?php if ($isLoggedIn && $slotsLeft <= 0): ?></button><?php else: ?></a><?php endif; ?>
    </div>

  </div>

<?php endif; // end static about content ?>

  <div class="page-footer">
    <a href="/useful.php" class="footer-link">Полезное</a>
            <a href="/help.php" class="footer-link">Помощь</a>
    <a href="/terms.php" class="footer-link">Условия</a>
    <a href="/about.php" class="footer-link active">О нас</a>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
