<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
requireAdmin();

$pdo = getDbConnection();
$pendingCount       = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingVerifCount  = (int)$pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

// ── Handle POST (AJAX) ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    function jok($d = []) { echo json_encode(['ok' => true] + $d); exit; }
    function jerr($m)      { echo json_encode(['ok' => false, 'error' => $m]); exit; }

    switch ($action) {
        case 'add': {
            $name    = trim($_POST['name'] ?? '');
            $nameLat = trim($_POST['name_lat'] ?? '');
            $cc      = strtolower(trim($_POST['country_code'] ?? ''));
            $capital = (int)($_POST['is_capital'] ?? 0);
            $order   = (int)($_POST['sort_order'] ?? 0);
            if (!$name || !$cc) jerr('Название и страна обязательны');
            $pdo->prepare("INSERT INTO cities (name, name_lat, country_code, is_capital, sort_order, status) VALUES (?,?,?,?,?,'active')")
                ->execute([$name, $nameLat, $cc, $capital, $order]);
            jok(['id' => $pdo->lastInsertId()]);
        }
        case 'edit': {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $nameLat = trim($_POST['name_lat'] ?? '');
            $capital = (int)($_POST['is_capital'] ?? 0);
            $order   = (int)($_POST['sort_order'] ?? 0);
            $status  = in_array($_POST['status'] ?? '', ['active','pending']) ? $_POST['status'] : 'active';
            if (!$id || !$name) jerr('id и name обязательны');
            $pdo->prepare("UPDATE cities SET name=?,name_lat=?,is_capital=?,sort_order=?,status=? WHERE id=?")
                ->execute([$name, $nameLat, $capital, $order, $status, $id]);
            jok();
        }
        case 'approve': {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) jerr('id обязателен');
            $pdo->prepare("UPDATE cities SET status='active' WHERE id=?")->execute([$id]);
            jok();
        }
        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) jerr('id обязателен');
            $st = $pdo->prepare("SELECT COUNT(*) FROM services WHERE city_id=?");
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) jerr('Нельзя удалить: есть сервисы в этом городе');
            $pdo->prepare("DELETE FROM cities WHERE id=?")->execute([$id]);
            jok();
        }
        default: jerr('Неизвестное действие');
    }
}

// ── Active tab ─────────────────────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['cities','countries']) ? $_GET['tab'] : 'cities';

// ── Countries from DB (used everywhere) ────────────────────────────────────
$dbCountries = $pdo->query("SELECT code, name_ru FROM countries ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
$ccNames = [];
foreach ($dbCountries as $r) $ccNames[$r['code']] = $r['name_ru'];

// ── Cities tab data ─────────────────────────────────────────────────────────
$filterCC     = trim($_GET['cc'] ?? '');
$filterSearch = trim($_GET['q']  ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;

$pendingCities = $pdo->query("SELECT * FROM cities WHERE status='pending' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$where   = ["status='active'"];
$params  = [];
if ($filterCC)     { $where[] = "country_code=?"; $params[] = $filterCC; }
if ($filterSearch) { $where[] = "(name LIKE ? OR name_lat LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }
$whereStr = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM cities WHERE $whereStr");
$totalStmt->execute($params);
$total      = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$cityStmt = $pdo->prepare("SELECT * FROM cities WHERE $whereStr ORDER BY country_code, sort_order, name LIMIT $perPage OFFSET $offset");
$cityStmt->execute($params);
$cities = $cityStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- ── TABS ────────────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div style="display:flex;gap:4px;background:var(--border-light);border-radius:var(--radius-sm);padding:3px">
        <a href="?tab=cities<?php echo $filterCC ? '&cc='.urlencode($filterCC) : ''; ?>"
           style="padding:7px 20px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;transition:all 0.15s;
                  <?php echo $tab==='cities' ? 'background:var(--bg-white);color:var(--primary);box-shadow:var(--shadow)' : 'color:var(--text-secondary)'; ?>">
            🏙️ Города
        </a>
        <a href="?tab=countries"
           style="padding:7px 20px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;transition:all 0.15s;
                  <?php echo $tab==='countries' ? 'background:var(--bg-white);color:var(--primary);box-shadow:var(--shadow)' : 'color:var(--text-secondary)'; ?>">
            🌍 Страны
        </a>
    </div>
    <?php if ($tab === 'cities'): ?>
    <button class="btn btn-primary" onclick="openAddModal()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить город
    </button>
    <?php else: ?>
    <button class="btn btn-primary" onclick="openCountryModal()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить страну
    </button>
    <?php endif; ?>
</div>

<div id="alertBox"></div>

<?php if ($tab === 'cities'): ?>
<!-- ════════════════════════════════════════
     ВКЛАДКА: ГОРОДА
     ════════════════════════════════════════ -->

<?php if (!empty($pendingCities)): ?>
<div class="panel" style="margin-bottom:20px;border-left:3px solid var(--warning)">
    <div class="panel-header" style="background:var(--warning-bg)">
        <div class="panel-title" style="color:#92400E">⚠️ Ожидают добавления: <?php echo count($pendingCities); ?> городов</div>
        <div style="font-size:12px;color:#92400E">Добавлены пользователями при регистрации сервиса</div>
    </div>
    <table class="table">
        <thead><tr><th>Название</th><th>Страна</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($pendingCities as $pc): ?>
        <tr id="pending-<?php echo $pc['id']; ?>">
            <td style="font-weight:600"><?php echo htmlspecialchars($pc['name']); ?></td>
            <td>
                <span class="badge badge-gray">
                    <?php echo htmlspecialchars($ccNames[$pc['country_code']] ?? strtoupper($pc['country_code'])); ?>
                </span>
            </td>
            <td>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-success btn-sm" onclick="approveCity(<?php echo $pc['id']; ?>)">✅ Добавить в базу</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCity(<?php echo $pc['id']; ?>,'<?php echo addslashes($pc['name']); ?>',true)">✕ Отклонить</button>
                    <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?php echo $pc['id']; ?>,'<?php echo addslashes($pc['name']); ?>','<?php echo addslashes($pc['name_lat']??''); ?>','<?php echo $pc['country_code']; ?>',<?php echo $pc['is_capital']; ?>,<?php echo $pc['sort_order']; ?>,'<?php echo $pc['status']; ?>')">✏️</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="panel" style="margin-bottom:16px">
    <div style="padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;flex:1;align-items:center">
            <input type="hidden" name="tab" value="cities">
            <input type="text" name="q" class="form-control" placeholder="Поиск по названию..." value="<?php echo htmlspecialchars($filterSearch); ?>" style="max-width:220px">
            <select name="cc" class="form-control form-select" style="max-width:220px">
                <option value="">Все страны</option>
                <?php foreach ($dbCountries as $cr): ?>
                <option value="<?php echo $cr['code']; ?>" <?php echo $cr['code'] === $filterCC ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cr['name_ru']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Найти</button>
            <?php if ($filterCC || $filterSearch): ?>
            <a href="?tab=cities" class="btn btn-secondary">Сбросить</a>
            <?php endif; ?>
        </form>
        <div style="font-size:13px;color:var(--text-secondary)">Найдено: <strong><?php echo $total; ?></strong></div>
    </div>
</div>

<!-- Cities table -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">Активные города</div>
        <div style="font-size:12px;color:var(--text-secondary)">Стр. <?php echo $page; ?> из <?php echo $totalPages; ?></div>
    </div>
    <?php if (empty($cities)): ?>
    <div class="empty-state"><div class="empty-state-icon">🏙️</div><div class="empty-state-title">Нет городов</div></div>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th>Название (RU)</th><th>Название (LAT)</th><th>Страна</th><th>Столица</th><th>Порядок</th><th style="width:90px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($cities as $city): ?>
        <tr id="city-<?php echo $city['id']; ?>">
            <td style="font-weight:600"><?php echo htmlspecialchars($city['name']); ?></td>
            <td style="color:var(--text-secondary)"><?php echo htmlspecialchars($city['name_lat'] ?? ''); ?></td>
            <td><span class="badge badge-blue"><?php echo htmlspecialchars($ccNames[$city['country_code']] ?? strtoupper($city['country_code'])); ?></span></td>
            <td><?php echo $city['is_capital'] ? '<span class="badge badge-yellow">★ Столица</span>' : '<span style="color:var(--text-light)">—</span>'; ?></td>
            <td style="color:var(--text-secondary)"><?php echo $city['sort_order']; ?></td>
            <td>
                <div style="display:flex;gap:4px">
                    <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?php echo $city['id']; ?>,'<?php echo addslashes($city['name']); ?>','<?php echo addslashes($city['name_lat']??''); ?>','<?php echo $city['country_code']; ?>',<?php echo $city['is_capital']; ?>,<?php echo $city['sort_order']; ?>,'<?php echo $city['status']; ?>')">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCity(<?php echo $city['id']; ?>,'<?php echo addslashes($city['name']); ?>',false)">🗑</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?tab=cities&page=<?php echo $page-1; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>" class="page-link">← Пред</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
        <a href="?tab=cities&page=<?php echo $p; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>"
           class="page-link <?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?tab=cities&page=<?php echo $page+1; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>" class="page-link">След →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ════════════════════════════════════════
     ВКЛАДКА: СТРАНЫ
     ════════════════════════════════════════ -->

<div id="countriesTable">
    <div class="panel"><div style="padding:32px;text-align:center;color:var(--text-secondary)">Загрузка...</div></div>
</div>

<?php endif; ?>

<!-- ── Modal: Add / Edit City ──────────────────────────────────────────── -->
<div id="cityModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.5);overflow-y:auto">
  <div style="background:var(--bg-white);border-radius:var(--radius);max-width:480px;width:90%;margin:60px auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
        <h3 id="cityModalTitle" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Добавить город</h3>
        <button onclick="closeModal()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:22px;line-height:1;padding:0">&times;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="cityId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Название (RU) *</label>
                <input type="text" id="cityName" class="form-control" placeholder="Париж">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Название (LAT)</label>
                <input type="text" id="cityNameLat" class="form-control" placeholder="paris">
            </div>
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Страна *</label>
            <select id="cityCC" class="form-control form-select">
                <?php foreach ($dbCountries as $cr): ?>
                <option value="<?php echo $cr['code']; ?>"><?php echo htmlspecialchars($cr['name_ru']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Столица</label>
                <select id="cityCapital" class="form-control form-select"><option value="0">Нет</option><option value="1">Да</option></select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок</label>
                <input type="number" id="cityOrder" class="form-control" value="0" min="0" step="10">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Статус</label>
                <select id="cityStatus" class="form-control form-select">
                    <option value="active">Активен</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>
    </div>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeModal()" class="btn btn-secondary">Отмена</button>
        <button onclick="saveCity()" class="btn btn-primary">Сохранить</button>
    </div>
  </div>
</div>

<!-- ── Modal: Add / Edit Country ───────────────────────────────────────── -->
<div id="countryModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.5);overflow-y:auto">
  <div style="background:var(--bg-white);border-radius:var(--radius);max-width:440px;width:90%;margin:60px auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
        <h3 id="countryModalTitle" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Добавить страну</h3>
        <button onclick="closeCountryModal()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:22px;line-height:1;padding:0">&times;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="countryEditCode">
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Код страны (2 буквы, ISO 3166-1) *</label>
            <input type="text" id="countryCode" class="form-control" placeholder="fr" maxlength="2" style="text-transform:lowercase">
            <div style="font-size:11px;color:var(--text-light);margin-top:3px">Нельзя изменить после создания. Используется для флагов и URL.</div>
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Название на русском *</label>
            <input type="text" id="countryName" class="form-control" placeholder="Франция">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок сортировки</label>
            <input type="number" id="countryOrder" class="form-control" value="0" min="0" step="10">
        </div>
    </div>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeCountryModal()" class="btn btn-secondary">Отмена</button>
        <button onclick="saveCountry()" class="btn btn-primary">Сохранить</button>
    </div>
  </div>
</div>

<script>
const CITY_API    = '/panel-5588/settings/cities.php';
const COUNTRY_API = '/panel-5588/settings/api-countries.php';

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}" style="margin-bottom:16px">${msg}</div>`;
    setTimeout(() => { box.innerHTML = ''; }, 3500);
}

// ══════════════════════════════════════════
// ГОРОДА
// ══════════════════════════════════════════
function openAddModal() {
    document.getElementById('cityModalTitle').textContent = 'Добавить город';
    document.getElementById('cityId').value = '';
    document.getElementById('cityName').value = '';
    document.getElementById('cityNameLat').value = '';
    document.getElementById('cityCapital').value = '0';
    document.getElementById('cityOrder').value = '0';
    document.getElementById('cityStatus').value = 'active';
    document.getElementById('cityModal').style.display = 'block';
}

function openEditModal(id, name, nameLat, cc, capital, order, status) {
    document.getElementById('cityModalTitle').textContent = 'Изменить город';
    document.getElementById('cityId').value = id;
    document.getElementById('cityName').value = name;
    document.getElementById('cityNameLat').value = nameLat;
    document.getElementById('cityCC').value = cc;
    document.getElementById('cityCapital').value = capital;
    document.getElementById('cityOrder').value = order;
    document.getElementById('cityStatus').value = status;
    document.getElementById('cityModal').style.display = 'block';
}

function closeModal() { document.getElementById('cityModal').style.display = 'none'; }

async function saveCity() {
    const id = document.getElementById('cityId').value;
    const name = document.getElementById('cityName').value.trim();
    if (!name) { alert('Введите название города'); return; }
    const fd = new FormData();
    fd.append('action', id ? 'edit' : 'add');
    if (id) fd.append('id', id);
    fd.append('name', name);
    fd.append('name_lat', document.getElementById('cityNameLat').value.trim());
    fd.append('country_code', document.getElementById('cityCC').value);
    fd.append('is_capital', document.getElementById('cityCapital').value);
    fd.append('sort_order', document.getElementById('cityOrder').value);
    fd.append('status', document.getElementById('cityStatus').value);
    const res = await fetch(CITY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) { closeModal(); showAlert('Город сохранён', 'success'); setTimeout(() => location.reload(), 900); }
    else showAlert(data.error, 'danger');
}

async function approveCity(id) {
    const fd = new FormData(); fd.append('action', 'approve'); fd.append('id', id);
    const res = await fetch(CITY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) { document.getElementById('pending-' + id)?.remove(); showAlert('Город добавлен в базу', 'success'); }
    else showAlert(data.error, 'danger');
}

async function deleteCity(id, name, isPending) {
    if (!confirm(isPending ? `Отклонить и удалить город "${name}"?` : `Удалить город "${name}"?`)) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    const res = await fetch(CITY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
        document.getElementById('pending-' + id)?.remove();
        document.getElementById('city-' + id)?.remove();
        showAlert('Город удалён', 'success');
    } else showAlert(data.error, 'danger');
}

document.getElementById('cityModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

// ══════════════════════════════════════════
// СТРАНЫ
// ══════════════════════════════════════════
<?php if ($tab === 'countries'): ?>
async function loadCountries() {
    const res = await fetch(COUNTRY_API + '?action=list');
    const data = await res.json();
    if (!data.ok) { document.getElementById('countriesTable').innerHTML = '<div class="panel"><div style="padding:24px;color:var(--danger)">Ошибка загрузки</div></div>'; return; }
    renderCountries(data.countries);
}

function renderCountries(list) {
    const wrap = document.getElementById('countriesTable');
    if (!list.length) {
        wrap.innerHTML = '<div class="panel"><div class="empty-state"><div class="empty-state-icon">🌍</div><div class="empty-state-title">Нет стран</div></div></div>';
        return;
    }
    wrap.innerHTML = `
    <div class="panel">
        <table class="table">
            <thead><tr>
                <th style="width:56px">Флаг</th>
                <th>Название</th>
                <th>Код</th>
                <th>Городов</th>
                <th>Статус</th>
                <th>Порядок</th>
                <th style="width:130px"></th>
            </tr></thead>
            <tbody>${list.map(c => `
            <tr id="country-${c.code}">
                <td><img src="https://flagcdn.com/w40/${c.code}.png" alt="${esc(c.code)}" style="width:36px;height:auto;border-radius:4px;border:1px solid var(--border);display:block" onerror="this.style.opacity=0.2"></td>
                <td style="font-weight:600">${esc(c.name_ru)}</td>
                <td><code style="background:var(--border-light);padding:2px 7px;border-radius:4px;font-size:12px">${esc(c.code)}</code></td>
                <td>
                    <a href="?tab=cities&cc=${esc(c.code)}" style="color:var(--primary);font-weight:600;text-decoration:none">
                        ${c.city_count} <span style="font-size:11px;color:var(--text-light)">→</span>
                    </a>
                </td>
                <td>
                    <button onclick="toggleCountry('${esc(c.code)}')" class="btn btn-sm" id="toggle-${esc(c.code)}"
                        style="${c.is_active == 1 ? 'background:var(--success-bg);color:#065F46;border:1px solid #A7F3D0' : 'background:var(--border-light);color:var(--text-secondary);border:1px solid var(--border)'}">
                        ${c.is_active == 1 ? '✅ Активна' : '⏸ Скрыта'}
                    </button>
                </td>
                <td style="color:var(--text-secondary)">${c.sort_order}</td>
                <td>
                    <div style="display:flex;gap:4px">
                        <button class="btn btn-secondary btn-sm" onclick="openCountryEditModal('${esc(c.code)}','${escQ(c.name_ru)}',${c.sort_order})">✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteCountry('${esc(c.code)}','${escQ(c.name_ru)}',${c.city_count})">🗑</button>
                    </div>
                </td>
            </tr>`).join('')}
            </tbody>
        </table>
    </div>`;
}

function openCountryModal() {
    document.getElementById('countryModalTitle').textContent = 'Добавить страну';
    document.getElementById('countryEditCode').value = '';
    document.getElementById('countryCode').value = '';
    document.getElementById('countryCode').disabled = false;
    document.getElementById('countryName').value = '';
    document.getElementById('countryOrder').value = '0';
    document.getElementById('countryModal').style.display = 'block';
}

function openCountryEditModal(code, name, order) {
    document.getElementById('countryModalTitle').textContent = 'Изменить страну';
    document.getElementById('countryEditCode').value = code;
    document.getElementById('countryCode').value = code;
    document.getElementById('countryCode').disabled = true;
    document.getElementById('countryName').value = name;
    document.getElementById('countryOrder').value = order;
    document.getElementById('countryModal').style.display = 'block';
}

function closeCountryModal() { document.getElementById('countryModal').style.display = 'none'; }

async function saveCountry() {
    const editCode = document.getElementById('countryEditCode').value;
    const code  = document.getElementById('countryCode').value.trim().toLowerCase();
    const name  = document.getElementById('countryName').value.trim();
    const order = document.getElementById('countryOrder').value;
    if (!name) { alert('Введите название'); return; }
    if (!editCode && (code.length !== 2 || !/^[a-z]+$/.test(code))) { alert('Код должен быть 2 латинские буквы'); return; }
    const fd = new FormData();
    fd.append('action', editCode ? 'update' : 'create');
    fd.append('code', editCode || code);
    fd.append('name_ru', name);
    fd.append('sort_order', order);
    const res = await fetch(COUNTRY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) { closeCountryModal(); loadCountries(); showAlert('Страна сохранена', 'success'); }
    else showAlert(data.error, 'danger');
}

async function toggleCountry(code) {
    const fd = new FormData(); fd.append('action', 'toggle'); fd.append('code', code);
    const res = await fetch(COUNTRY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
        const btn = document.getElementById('toggle-' + code);
        if (data.is_active) {
            btn.textContent = '✅ Активна';
            btn.style.cssText = 'background:var(--success-bg);color:#065F46;border:1px solid #A7F3D0';
        } else {
            btn.textContent = '⏸ Скрыта';
            btn.style.cssText = 'background:var(--border-light);color:var(--text-secondary);border:1px solid var(--border)';
        }
    } else showAlert(data.error, 'danger');
}

async function deleteCountry(code, name, cityCount) {
    if (cityCount > 0) { showAlert(`Нельзя удалить «${name}»: ${cityCount} городов`, 'danger'); return; }
    if (!confirm(`Удалить страну «${name}» (${code})?`)) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('code', code);
    const res = await fetch(COUNTRY_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) { document.getElementById('country-' + code)?.remove(); showAlert('Страна удалена', 'success'); }
    else showAlert(data.error, 'danger');
}

document.getElementById('countryModal').addEventListener('click', function(e) { if (e.target === this) closeCountryModal(); });
loadCountries();

<?php else: ?>
function openCountryModal() {}
function closeCountryModal() {}
<?php endif; ?>

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escQ(s) { return String(s).replace(/'/g,"\\'").replace(/"/g,'\\"'); }
</script>

<?php
$content = ob_get_clean();
renderLayout('Настройки — Города и Страны', $content, $pendingCount, $pendingVerifCount, $pendingReviewCount);
?>
