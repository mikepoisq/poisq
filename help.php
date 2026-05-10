<?php
// help.php — Помощь (FAQ)
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

// Загрузка FAQ из БД
$faqItems = [];
try {
    if (!isset($pdo)) { require_once __DIR__ . '/config/database.php'; $pdo = getDbConnection(); }
    $stFaq = $pdo->query("SELECT id, question, answer FROM faq WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
    $faqItems = $stFaq->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $faqItems = []; }
?>
<?php
$pageTitle       = 'Помощь — Poisq';
$pageDescription = 'Ответы на частые вопросы о Poisq — каталоге русскоязычных сервисов за рубежом.';
$canonicalUrl    = 'https://poisq.com/help.php';
$ogImage         = 'https://poisq.com/apple-touch-icon.png?v=2';
require_once __DIR__ . '/includes/header.php';
?>
<style>
html, body { min-height: 100%; overflow-x: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; background: var(--bg-secondary); color: var(--text); -webkit-font-smoothing: antialiased; }
.app-container { max-width: 430px; margin: 0 auto; background: var(--bg); min-height: 100vh; display: flex; flex-direction: column; }

/* ── HEADER ── */
.page-header {
  position: sticky; top: 0; z-index: 100;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
}
.header-top {
  display: flex; align-items: center;
  padding: 10px 14px; height: 56px; gap: 10px;
}
.btn-back {
  width: 38px; height: 38px; border-radius: var(--radius-xs); border: none;
  background: var(--bg-secondary); color: var(--text);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: all 0.15s; text-decoration: none;
}
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.btn-back:active { background: var(--primary); }
.btn-back:active svg { stroke: white; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.header-actions { width: 84px; display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.btn-grid {
  width: 38px; height: 38px; border-radius: var(--radius-xs); border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s;
}
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }
.btn-add {
  width: 38px; height: 38px; border-radius: var(--radius-xs); border: none;
  background: var(--primary-light); color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s, transform 0.1s;
  text-decoration: none; flex-shrink: 0;
}
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-burger {
  width: 38px; height: 38px; display: flex; flex-direction: column;
  justify-content: center; align-items: center; gap: 5px;
  padding: 8px; cursor: pointer; background: none; border: none; border-radius: var(--radius-xs); flex-shrink: 0;
}
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── CONTENT ── */
.page-content { flex: 1; padding: 24px 16px 40px; }

.page-hero {
  display: flex; flex-direction: column; align-items: center;
  text-align: center; padding: 8px 0 28px;
}
.hero-icon {
  width: 64px; height: 64px; border-radius: 20px;
  background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}
.hero-icon svg { width: 32px; height: 32px; stroke: var(--primary); fill: none; stroke-width: 1.8; }
.hero-title { font-size: 22px; font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.5px; }
.hero-sub { font-size: 14px; color: var(--text-secondary); font-weight: 500; line-height: 1.5; max-width: 280px; }

/* ── SECTIONS ── */
.section { margin-bottom: 28px; }
.section-title {
  font-size: 13px; font-weight: 700; color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px;
  margin-bottom: 12px; padding: 0 2px;
}

/* ── ACCORDION ── */
.accord-item {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  margin-bottom: 8px;
  overflow: hidden;
  background: var(--bg);
}
.accord-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px;
  cursor: pointer;
  font-size: 14.5px; font-weight: 700; color: var(--text);
  letter-spacing: -0.1px;
  user-select: none;
  transition: background 0.15s;
  gap: 10px;
}
.accord-head:active { background: var(--bg-secondary); }
.accord-arrow {
  width: 20px; height: 20px;
  stroke: var(--text-light); fill: none; stroke-width: 2.5;
  flex-shrink: 0;
  transition: transform 0.25s;
}
.accord-item.open .accord-arrow { transform: rotate(180deg); }
.accord-body {
  display: none;
  padding: 0 16px 16px;
  font-size: 13.5px; color: var(--text-secondary); font-weight: 500;
  line-height: 1.6;
  border-top: 1px solid var(--border-light);
}
.accord-item.open .accord-body { display: block; padding-top: 14px; }
.accord-body a { color: var(--primary); text-decoration: none; }

/* ── CONTACT BANNER ── */
.contact-banner {
  background: var(--primary-light);
  border-radius: 16px;
  padding: 20px;
  display: flex; align-items: center; gap: 16px;
  text-decoration: none;
  transition: all 0.15s;
  margin-top: 8px;
}
.contact-banner:active { transform: scale(0.98); }
.contact-banner-icon {
  width: 48px; height: 48px; border-radius: 14px;
  background: var(--primary);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.contact-banner-icon svg { width: 22px; height: 22px; stroke: white; fill: none; stroke-width: 2; }
.contact-banner-text { flex: 1; }
.contact-banner-title { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 3px; }
.contact-banner-sub { font-size: 12px; color: var(--text-secondary); font-weight: 500; }
.contact-banner-arrow svg { width: 18px; height: 18px; stroke: var(--text-light); fill: none; stroke-width: 2.5; }

/* ── FOOTER ── */
.page-footer {
  padding: 16px 16px 32px;
  border-top: 1px solid var(--border-light);
  display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px;
}
.footer-link {
  font-size: 12px; font-weight: 500;
  color: var(--text-secondary); text-decoration: none;
  transition: color 0.15s;
}
.footer-link:active { color: var(--primary); }
.footer-link.active { color: var(--primary); font-weight: 700; }


@media (min-width: 1024px) {
  .app-container { max-width: 720px; padding-top: 64px; }
  .page-header { display: none; }
  .page-content { padding: 32px 24px 48px; }
}
</style>

<!-- HEADER -->
  <div class="page-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24">
            <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
            <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
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

  <!-- CONTENT -->
  <div class="page-content">

    <div class="page-hero">
      <div class="hero-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17" stroke-linecap="round" stroke-width="3"/></svg>
      </div>
      <div class="hero-title">Помощь</div>
      <div class="hero-sub">Ответы на самые частые вопросы о Poisq</div>
    </div>

    <?php if (!empty($faqItems)): ?>
    <!-- FAQ из БД -->
    <div class="section">
      <div class="section-title">Частые вопросы</div>
      <?php foreach ($faqItems as $fi): ?>
      <div class="accord-item" id="faq<?php echo $fi['id']; ?>">
        <div class="accord-head" onclick="toggleAccord('faq<?php echo $fi['id']; ?>')">
          <span><?php echo htmlspecialchars($fi['question']); ?></span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body"><?php echo nl2br(htmlspecialchars($fi['answer'])); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- FAQ: Поиск (статичный fallback) -->
    <div class="section">
      <div class="section-title">Поиск сервисов</div>
      <div class="accord-item" id="faq0">
        <div class="accord-head" onclick="toggleAccord('faq0')"><span>Как найти специалиста в моём городе?</span><svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></div>
        <div class="accord-body">На главной странице выберите страну, затем введите в поиске нужную услугу — например, «врач» или «юрист». Poisq автоматически определяет ваш город по IP и предложит ближайших специалистов.</div>
      </div>
      <div class="accord-item" id="faq1">
        <div class="accord-head" onclick="toggleAccord('faq1')"><span>Что означает значок ✅ у сервиса?</span><svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></div>
        <div class="accord-body">Зелёный значок означает, что сервис прошёл проверку командой Poisq.</div>
      </div>
    </div>
    <div class="section">
      <div class="section-title">Размещение сервиса</div>
      <div class="accord-item" id="faq4">
        <div class="accord-head" onclick="toggleAccord('faq4')"><span>Как добавить свой сервис?</span><svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></div>
        <div class="accord-body">Нажмите кнопку «+» в шапке. Заполните форму и отправьте на модерацию — обычно до 24 часов.</div>
      </div>
      <div class="accord-item" id="faq5">
        <div class="accord-head" onclick="toggleAccord('faq5')"><span>Сколько стоит размещение?</span><svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></div>
        <div class="accord-body">Базовое размещение бесплатное — до 3 сервисов на аккаунт.</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Contact Banner -->
    <a href="/contact.php" class="contact-banner">
      <div class="contact-banner-icon">
        <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div class="contact-banner-text">
        <div class="contact-banner-title">Не нашли ответ?</div>
        <div class="contact-banner-sub">Напишите нам — ответим в течение 24 часов</div>
      </div>
      <div class="contact-banner-arrow">
        <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

  </div>

  <!-- FOOTER -->
  <div class="page-footer">
    <a href="/useful.php" class="footer-link">Полезное</a>
    <a href="/help.php" class="footer-link active">Помощь</a>
    <a href="/terms.php" class="footer-link">Условия</a>
    <a href="/about.php" class="footer-link">О нас</a>
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
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div>
  </div>
</div>

<script>
// ACCORDION
function toggleAccord(id) {
  const item = document.getElementById(id);
  if (!item) return;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.accord-item.open').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
