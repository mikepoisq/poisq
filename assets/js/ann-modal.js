// ════════════════════════════════════════
// СВЕЖИЕ СЕРВИСЫ (РУПОР)
// ════════════════════════════════════════
let annCityId = null;

const catNames = {
  health:     '🏥 Здоровье и красота',
  legal:      '⚖️ Юридические услуги',
  family:     '👨‍👩‍👧‍👦 Семья и дети',
  shops:      '🛒 Магазины и продукты',
  home:       '🏠 Дом и быт',
  education:  '📚 Образование',
  business:   '💼 Бизнес и финансы',
  transport:  '🚗 Транспорт и авто',
  events:     '📷 События и развлечения',
  it:         '💻 IT и онлайн услуги',
  realestate: '🏢 Недвижимость',
  messengers: '💬 Группы и каналы',
};

async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';

  try {
    let cc = localStorage.getItem('poisq_country') || '';
    if (!cc) {
      const cr = await fetch('/api/get-user-country.php');
      const cd = await cr.json();
      cc = cd.country_code || 'fr';
    }
    const cir    = await fetch(`/api/get-cities.php?country=${cc}`);
    const cities = await cir.json();

    const sel = document.getElementById('annCitySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name + (c.is_capital == 1 ? ' (столица)' : '');
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch {
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
    const r  = await fetch(`/api/get-services.php?city_id=${cityId}&days=5`);
    const d  = await r.json();
    const sv = d.services || [];

    if (!sv.length) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
          <h3>Пока нет сервисов</h3>
          <p>В этом городе нет новых сервисов<br>за последние 5 дней</p>
          <button class="ann-add-btn" onclick="goAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Добавить сервис
          </button>
          <span class="ann-add-free">бесплатно</span>
        </div>`;
      return;
    }

    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });

    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      const limited = list.slice(0, 5);
      html += `<div class="ann-category">
        <div class="ann-cat-title">${catNames[cat] || cat}</div>
        <div class="ann-grid">
          ${limited.map(s => {
            let photo = 'https://via.placeholder.com/200?text=Poisq';
            if (s.photo) {
              try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; }
              catch { photo = s.photo; }
            }
            return `
            <div class="ann-item" onclick="location.href='/service.php?id=${s.id}'">
              <img src="${photo}" alt="${s.name}" loading="lazy" onerror="this.src='https://via.placeholder.com/200?text=Poisq'">
              <div class="ann-date">${fmtDate(s.created_at)}</div>
              <div class="ann-item-name">${s.name}</div>
            </div>`;
          }).join('')}
          <div class="ann-add-card" onclick="goAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Добавить свой сервис</span>
          </div>
        </div>
      </div>`;
    }
    content.innerHTML = html;
  } catch {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}

function goAdd() {
  if (typeof openSlotsModal === 'function') {
    closeAnnModal();
    setTimeout(() => openSlotsModal(), 300);
  } else {
    location.href = window.annAddUrl || '/add-service.php';
  }
}

function annErr(t, p) {
  return `<div class="ann-empty">
    <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <h3>${t}</h3><p>${p}</p>
  </div>`;
}

function fmtDate(ds) {
  const d = new Date(ds), now = new Date();
  const diff = Math.floor((now - d) / 86400000);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Вчера';
  if (diff < 5)  return diff + ' дн.';
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}
