<?php
// help.php — Помощь (FAQ)
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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Помощь — Poisq</title>
<meta name="description" content="Ответы на частые вопросы о Poisq — каталоге русскоязычных сервисов за рубежом.">
<link rel="canonical" href="https://poisq.com/help.php">
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
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
  --warning: #F59E0B;
  --danger: #EF4444;
  --radius-sm: 12px;
  --radius-xs: 10px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
  --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
}
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

/* ── SIDE MENU ── */
.side-menu-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.4); backdrop-filter: blur(4px);
  z-index: 300; display: none; opacity: 0; transition: opacity 0.25s;
}
.side-menu-overlay.active { display: block; opacity: 1; }
.side-menu {
  position: fixed; top: 0; right: -100%; width: 280px; height: 100vh;
  background: var(--bg); z-index: 400;
  transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
  box-shadow: -8px 0 32px rgba(0,0,0,0.12);
  border-radius: 20px 0 0 20px;
  display: flex; flex-direction: column; overflow: hidden;
}
.side-menu.active { right: 0; }
.side-menu-header { padding: 20px; background: var(--primary); }
.side-user-avatar {
  width: 52px; height: 52px; border-radius: 50%;
  background: rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: white; margin-bottom: 12px;
  overflow: hidden; border: 2px solid rgba(255,255,255,0.3);
}
.side-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.side-user-name { font-size: 16px; font-weight: 800; color: white; margin-bottom: 2px; }
.side-user-sub { font-size: 12px; color: rgba(255,255,255,0.7); }
.side-menu-items { flex: 1; overflow-y: auto; padding: 8px 0; }
.menu-item {
  display: flex; align-items: center; gap: 14px; padding: 14px 20px;
  color: var(--text); text-decoration: none; font-size: 15px; font-weight: 600;
  transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%;
}
.menu-item:active { background: var(--bg-secondary); }
.menu-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.menu-icon svg { width: 18px; height: 18px; fill: none; stroke-width: 2; }
.mi-blue { background: #EEF2FF; } .mi-blue svg { stroke: var(--primary); }
.mi-green { background: #F0FDF4; } .mi-green svg { stroke: #16A34A; }
.mi-orange { background: #FFF7ED; } .mi-orange svg { stroke: #EA580C; }
.mi-red { background: #FFF1F2; } .mi-red svg { stroke: var(--danger); }
.menu-divider { height: 1px; background: var(--border-light); margin: 6px 16px; }

/* ── ANN MODAL ── */
.ann-modal {
  position: fixed; inset: 0; z-index: 500;
  background: var(--bg);
  transform: translateY(100%);
  transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
  display: flex; flex-direction: column;
  max-width: 430px; margin: 0 auto;
}
.ann-modal.active { transform: translateY(0); }
.ann-header {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 16px; border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.ann-header-icon { font-size: 20px; }
.ann-title { flex: 1; font-size: 17px; font-weight: 800; color: var(--text); }
.ann-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.ann-city { padding: 12px 16px; border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1.5px solid var(--border); font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; font-size: 14px; font-weight: 600; background: var(--bg-secondary); color: var(--text); outline: none; appearance: none; }
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
</head>
<body>
<div class="app-container">

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
        <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </a>
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

    <!-- FAQ: Поиск -->
    <div class="section">
      <div class="section-title">Поиск сервисов</div>

      <div class="accord-item" id="faq0">
        <div class="accord-head" onclick="toggleAccord('faq0')">
          <span>Как найти специалиста в моём городе?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">На главной странице выберите страну, затем введите в поиске нужную услугу — например, «врач» или «юрист». Poisq автоматически определяет ваш город по IP и предложит ближайших специалистов. Вы также можете ввести город прямо в строке поиска, например «стоматолог Берлин».</div>
      </div>

      <div class="accord-item" id="faq1">
        <div class="accord-head" onclick="toggleAccord('faq1')">
          <span>Как отфильтровать результаты по категории?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">На странице результатов поиска нажмите кнопку «Фильтры» или выберите нужную категорию в строке чипов под строкой поиска. Можно также фильтровать по городу, минимальному рейтингу и статусу проверки.</div>
      </div>

      <div class="accord-item" id="faq2">
        <div class="accord-head" onclick="toggleAccord('faq2')">
          <span>Что означает значок ✅ у сервиса?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Зелёный значок означает, что сервис прошёл проверку командой Poisq. Мы убедились в подлинности контактных данных и реальности специалиста. Проверенным сервисам можно доверять с большей уверенностью.</div>
      </div>

      <div class="accord-item" id="faq3">
        <div class="accord-head" onclick="toggleAccord('faq3')">
          <span>Что такое «Свежие сервисы» (кнопка 9 точек)?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Кнопка с 9 точками в шапке открывает ленту новых сервисов, добавленных за последние 5 дней в вашем городе. Удобно следить за появлением новых специалистов рядом с вами.</div>
      </div>
    </div>

    <!-- FAQ: Размещение -->
    <div class="section">
      <div class="section-title">Размещение сервиса</div>

      <div class="accord-item" id="faq4">
        <div class="accord-head" onclick="toggleAccord('faq4')">
          <span>Как добавить свой сервис?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Нажмите кнопку «+» в шапке или перейдите в раздел «Добавить сервис». Заполните форму: название, категория, описание, контакты, фотографии и часы работы. После отправки сервис проходит ручную модерацию — обычно до 24 часов в будние дни.</div>
      </div>

      <div class="accord-item" id="faq5">
        <div class="accord-head" onclick="toggleAccord('faq5')">
          <span>Сколько стоит размещение?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Базовое размещение абсолютно бесплатное. Каждый пользователь может добавить до 3 сервисов без ограничений по времени. В будущем появятся платные опции для повышения видимости в результатах поиска.</div>
      </div>

      <div class="accord-item" id="faq6">
        <div class="accord-head" onclick="toggleAccord('faq6')">
          <span>Почему мой сервис не опубликован?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">После отправки сервис проходит ручную проверку командой Poisq. Обычно это занимает до 24 часов в будние дни. Если сервис отклонён — вы получите email с причиной и сможете внести исправления и отправить снова. Проверьте статус в разделе «Мои сервисы».</div>
      </div>

      <div class="accord-item" id="faq7">
        <div class="accord-head" onclick="toggleAccord('faq7')">
          <span>Как изменить информацию в сервисе?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Перейдите в раздел «Мои сервисы», выберите нужный и нажмите «Редактировать». После сохранения изменений сервис автоматически отправляется на повторную модерацию.</div>
      </div>

      <div class="accord-item" id="faq8">
        <div class="accord-head" onclick="toggleAccord('faq8')">
          <span>Могу ли я скрыть сервис на время?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Да. В разделе «Мои сервисы» у каждого одобренного сервиса есть переключатель видимости. Скрытый сервис перестаёт отображаться в каталоге, но остаётся в вашем аккаунте — вы можете снова сделать его видимым в любой момент.</div>
      </div>
    </div>

    <!-- FAQ: Аккаунт -->
    <div class="section">
      <div class="section-title">Аккаунт и безопасность</div>

      <div class="accord-item" id="faq9">
        <div class="accord-head" onclick="toggleAccord('faq9')">
          <span>Мои данные в безопасности?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Мы не передаём ваши персональные данные третьим лицам. Email используется только для входа, верификации и уведомлений, которые вы сами включаете в настройках. Все пароли хранятся в зашифрованном виде.</div>
      </div>

      <div class="accord-item" id="faq10">
        <div class="accord-head" onclick="toggleAccord('faq10')">
          <span>Как удалить свой аккаунт?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">Напишите нам через <a href="/contact.php">страницу контакта</a> с темой «Удаление аккаунта». Мы удалим все ваши данные в течение 5 рабочих дней. Удаление необратимо — все ваши сервисы также будут удалены.</div>
      </div>

      <div class="accord-item" id="faq11">
        <div class="accord-head" onclick="toggleAccord('faq11')">
          <span>Я забыл пароль — что делать?</span>
          <svg class="accord-arrow" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </div>
        <div class="accord-body">На странице входа нажмите «Забыл пароль». Введите ваш email — мы отправим ссылку для сброса пароля. Ссылка действительна 1 час. Если письмо не пришло, проверьте папку «Спам».</div>
      </div>
    </div>

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

<!-- SIDE MENU -->
<div class="side-menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-header">
    <div class="side-user-avatar">
      <?php if ($isLoggedIn && $userAvatar): ?>
        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="">
      <?php elseif ($isLoggedIn): ?>
        <?php echo $userInitial; ?>
      <?php else: ?>
        👤
      <?php endif; ?>
    </div>
    <div class="side-user-name"><?php echo $isLoggedIn ? htmlspecialchars($userName) : 'Гость'; ?></div>
    <div class="side-user-sub"><?php echo $isLoggedIn ? htmlspecialchars($_SESSION['user_email'] ?? '') : 'Войдите в аккаунт'; ?></div>
  </div>
  <div class="side-menu-items">
    <?php if ($isLoggedIn): ?>
    <a href="/profile.php" class="menu-item">
      <div class="menu-icon mi-blue"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
      Личный кабинет
    </a>
    <a href="/my-services.php" class="menu-item">
      <div class="menu-icon mi-green"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg></div>
      Мои сервисы
    </a>
    <?php endif; ?>
    <a href="/add-service.php" class="menu-item">
      <div class="menu-icon mi-orange"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>
      Добавить сервис
    </a>
    <div class="menu-divider"></div>
    <?php if ($isLoggedIn): ?>
    <a href="/logout.php" class="menu-item">
      <div class="menu-icon mi-red"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
      Выйти
    </a>
    <?php else: ?>
    <a href="/login.php" class="menu-item">
      <div class="menu-icon mi-blue"><svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div>
      Войти
    </a>
    <a href="/register.php" class="menu-item">
      <div class="menu-icon mi-green"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
      Регистрация
    </a>
    <?php endif; ?>
  </div>
</div>

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
// MENU
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

// ACCORDION
function toggleAccord(id) {
  const item = document.getElementById(id);
  if (!item) return;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.accord-item.open').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

// ANN MODAL
let annCityId = null;
async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr  = await fetch('/api/get-user-country.php');
    const cd  = await cr.json();
    const cc  = cd.country_code || 'fr';
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
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение и попробуйте снова.');
  }
}
function closeAnnModal() {
  document.getElementById('annModal').classList.remove('active');
  document.body.style.overflow = '';
}
async function filterByCity() {
  annCityId = document.getElementById('annCitySelect').value;
  await loadAnnServices(annCityId);
}
async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5');
    const d = await r.json();
    const sv = d.services || [];
    if (!sv.length) {
      content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>В этом городе нет новых сервисов за последние 5 дней</p></div>';
      return;
    }
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
        const dateStr = diff === 0 ? 'Сегодня' : diff === 1 ? 'Вчера' : diff < 5 ? diff + ' дн.' : d2.toLocaleDateString('ru-RU', {day:'numeric',month:'short'});
        html += '<div class="ann-item" onclick="location.href=\'/service/' + s.id + '\'"><img src="' + photo + '" alt="' + s.name + '" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'"><div class="ann-date">' + dateStr + '</div><div class="ann-item-name">' + s.name + '</div></div>';
      });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}
function annErr(t, p) {
  return '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><h3>' + t + '</h3><p>' + p + '</p></div>';
}
</script>
</body>
</html>
