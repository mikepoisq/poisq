<?php
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/layout.php';
requireModeratorAuth('cities');

$pdo = getDbConnection();

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

// ── Countries from DB ───────────────────────────────────────────────────────
$dbCountries = $pdo->query("SELECT code, name_ru FROM countries ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC);
$ccNames = [];
foreach ($dbCountries as $r) $ccNames[$r['code']] = $r['name_ru'];

// ── Cities data ─────────────────────────────────────────────────────────────
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

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div style="font-size:14px;color:var(--text-secondary);">Управление городами</div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить город
    </button>
</div>

<div id="alertBox"></div>

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
            <a href="/mod/cities.php" class="btn btn-secondary">Сбросить</a>
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
        <a href="?page=<?php echo $page-1; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>" class="page-link">← Пред</a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-3); $p <= min($totalPages,$page+3); $p++): ?>
        <a href="?page=<?php echo $p; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>"
           class="page-link <?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page+1; ?>&cc=<?php echo urlencode($filterCC); ?>&q=<?php echo urlencode($filterSearch); ?>" class="page-link">След →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: Add / Edit City -->
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

<script>
const CITY_API = '/mod/cities.php';

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}" style="margin-bottom:16px">${msg}</div>`;
    setTimeout(() => { box.innerHTML = ''; }, 3500);
}

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
</script>

<?php
$content = ob_get_clean();
renderModLayout('Города и страны', $content);
?>
