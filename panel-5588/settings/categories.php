<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../layout.php';
requireAdmin();

$pdo = getDbConnection();
$pendingCount      = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingVerifCount = (int)$pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status='pending'")->fetchColumn();
$pendingReviewCount= (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

ob_start();
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:4px">Категории сервисов</h2>
        <div style="font-size:13px;color:var(--text-secondary)">Управление категориями и подкатегориями</div>
    </div>
    <button class="btn btn-primary" onclick="openCatModal()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Добавить категорию
    </button>
</div>

<div id="alertBox"></div>

<!-- Categories list -->
<div id="catList">
    <div class="panel"><div style="padding:32px;text-align:center;color:var(--text-secondary)">Загрузка...</div></div>
</div>

<!-- ── Modal: Add/Edit Category ───────────────────────────────────────── -->
<div id="catModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.5);overflow-y:auto">
  <div style="background:var(--bg-white);border-radius:var(--radius);max-width:500px;width:90%;margin:60px auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
        <h3 id="catModalTitle" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Добавить категорию</h3>
        <button onclick="closeCatModal()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:22px;line-height:1;padding:0">&times;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="catId">
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Slug (латиница, уникальный) *</label>
            <input type="text" id="catSlug" class="form-control" placeholder="health" pattern="[a-z0-9_-]+">
            <div style="font-size:11px;color:var(--text-light);margin-top:3px">Только латиница, цифры, дефис. Нельзя изменить после создания.</div>
        </div>
        <div style="display:grid;grid-template-columns:60px 1fr;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Иконка</label>
                <input type="text" id="catIcon" class="form-control" placeholder="🏥" style="text-align:center;font-size:20px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Название *</label>
                <input type="text" id="catName" class="form-control" placeholder="Здоровье и красота">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок сортировки</label>
                <input type="number" id="catOrder" class="form-control" value="0" min="0" step="10">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Активна</label>
                <select id="catActive" class="form-control form-select">
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>
        </div>
    </div>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeCatModal()" class="btn btn-secondary">Отмена</button>
        <button onclick="saveCat()" class="btn btn-primary">Сохранить</button>
    </div>
  </div>
</div>

<!-- ── Modal: Add/Edit Subcategory ────────────────────────────────────── -->
<div id="subModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.5);overflow-y:auto">
  <div style="background:var(--bg-white);border-radius:var(--radius);max-width:440px;width:90%;margin:60px auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
        <h3 id="subModalTitle" style="font-size:15px;font-weight:700;color:var(--text);margin:0">Добавить подкатегорию</h3>
        <button onclick="closeSubModal()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:22px;line-height:1;padding:0">&times;</button>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <input type="hidden" id="subId">
        <input type="hidden" id="subCatSlug">
        <div>
            <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Название *</label>
            <input type="text" id="subName" class="form-control" placeholder="Название подкатегории">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок</label>
                <input type="number" id="subOrder" class="form-control" value="0" min="0" step="10">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Активна</label>
                <select id="subActive" class="form-control form-select">
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>
        </div>
    </div>
    <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
        <button onclick="closeSubModal()" class="btn btn-secondary">Отмена</button>
        <button onclick="saveSub()" class="btn btn-primary">Сохранить</button>
    </div>
  </div>
</div>

<script>
const API = '/panel-5588/settings/api-categories.php';
let allCategories = [];

// ── Load & render ──────────────────────────────────────────────────────────
async function loadCats() {
    const res = await fetch(API + '?action=list');
    const data = await res.json();
    allCategories = data.categories || [];
    renderCats();
}

function renderCats() {
    const wrap = document.getElementById('catList');
    if (!allCategories.length) {
        wrap.innerHTML = '<div class="panel"><div class="empty-state"><div class="empty-state-icon">📂</div><div class="empty-state-title">Нет категорий</div></div></div>';
        return;
    }
    wrap.innerHTML = allCategories.map(cat => `
    <div class="panel" style="margin-bottom:12px">
        <div class="panel-header">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:22px;width:32px;text-align:center">${cat.icon||'📁'}</span>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--text)">${esc(cat.name)}</div>
                    <div style="font-size:12px;color:var(--text-light);margin-top:1px">
                        slug: <code style="background:var(--border-light);padding:1px 5px;border-radius:4px">${esc(cat.slug)}</code>
                        · ${cat.subcategories.length} подкатегорий
                        · порядок: ${cat.sort_order}
                        ${cat.is_active=='0'?'<span class="badge badge-gray" style="margin-left:4px">Скрыта</span>':''}
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
                <button class="btn btn-secondary btn-sm" onclick="openSubModal('${esc(cat.slug)}')">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Подкатегория
                </button>
                <button class="btn btn-secondary btn-sm" onclick="openCatEditModal(${cat.id},'${esc(cat.slug)}','${esc(cat.icon||'')}','${escQ(cat.name)}',${cat.sort_order},${cat.is_active})">✏️ Изменить</button>
                <button class="btn btn-danger btn-sm" onclick="deleteCat(${cat.id},'${escQ(cat.name)}')">🗑</button>
                <button class="btn btn-secondary btn-sm" onclick="toggleSubs('subs-${cat.slug}',this)" style="min-width:90px">▼ Показать</button>
            </div>
        </div>
        <div id="subs-${cat.slug}" style="display:none">
            ${renderSubs(cat)}
        </div>
    </div>`).join('');
}

function renderSubs(cat) {
    if (!cat.subcategories.length) return '<div style="padding:16px 18px;color:var(--text-secondary);font-size:13px">Нет подкатегорий</div>';
    return `<table class="table">
        <thead><tr>
            <th>Название</th><th>Порядок</th><th>Статус</th><th style="width:100px"></th>
        </tr></thead>
        <tbody>${cat.subcategories.map(s=>`
        <tr>
            <td style="font-size:14px">${esc(s.name)}</td>
            <td style="font-size:13px;color:var(--text-secondary)">${s.sort_order}</td>
            <td>${s.is_active=='1'?'<span class="badge badge-green">Активна</span>':'<span class="badge badge-gray">Скрыта</span>'}</td>
            <td>
                <div style="display:flex;gap:4px">
                    <button class="btn btn-secondary btn-sm" onclick="openSubEditModal(${s.id},'${esc(cat.slug)}','${escQ(s.name)}',${s.sort_order},${s.is_active})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteSub(${s.id},'${escQ(s.name)}')">🗑</button>
                </div>
            </td>
        </tr>`).join('')}
        </tbody>
    </table>`;
}

function toggleSubs(id, btn) {
    const el = document.getElementById(id);
    const open = el.style.display === 'none';
    el.style.display = open ? 'block' : 'none';
    btn.textContent = open ? '▲ Скрыть' : '▼ Показать';
}

// ── Category modal ─────────────────────────────────────────────────────────
function openCatModal() {
    document.getElementById('catModalTitle').textContent = 'Добавить категорию';
    document.getElementById('catId').value = '';
    document.getElementById('catSlug').value = '';
    document.getElementById('catSlug').disabled = false;
    document.getElementById('catIcon').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catOrder').value = (allCategories.length * 10) || 0;
    document.getElementById('catActive').value = '1';
    document.getElementById('catModal').style.display = 'block';
}
function openCatEditModal(id, slug, icon, name, order, active) {
    document.getElementById('catModalTitle').textContent = 'Изменить категорию';
    document.getElementById('catId').value = id;
    document.getElementById('catSlug').value = slug;
    document.getElementById('catSlug').disabled = true;
    document.getElementById('catIcon').value = icon;
    document.getElementById('catName').value = name;
    document.getElementById('catOrder').value = order;
    document.getElementById('catActive').value = active;
    document.getElementById('catModal').style.display = 'block';
}
function closeCatModal() { document.getElementById('catModal').style.display = 'none'; }

async function saveCat() {
    const id    = document.getElementById('catId').value;
    const slug  = document.getElementById('catSlug').value.trim();
    const icon  = document.getElementById('catIcon').value.trim();
    const name  = document.getElementById('catName').value.trim();
    const order = document.getElementById('catOrder').value;
    const active= document.getElementById('catActive').value;
    if (!name) { alert('Введите название'); return; }
    if (!id && !slug) { alert('Введите slug'); return; }
    const action = id ? 'cat_edit' : 'cat_add';
    const fd = new FormData();
    fd.append('action', action);
    if (id) fd.append('id', id); else fd.append('slug', slug);
    fd.append('icon', icon); fd.append('name', name);
    fd.append('sort_order', order); fd.append('is_active', active);
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { closeCatModal(); loadCats(); showAlert('Сохранено', 'success'); }
    else showAlert(data.error, 'danger');
}

async function deleteCat(id, name) {
    if (!confirm(`Удалить категорию "${name}"?\nВсе подкатегории будут удалены.`)) return;
    const fd = new FormData(); fd.append('action','cat_delete'); fd.append('id', id);
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { loadCats(); showAlert('Категория удалена', 'success'); }
    else showAlert(data.error, 'danger');
}

// ── Subcategory modal ──────────────────────────────────────────────────────
function openSubModal(catSlug) {
    document.getElementById('subModalTitle').textContent = 'Добавить подкатегорию';
    document.getElementById('subId').value = '';
    document.getElementById('subCatSlug').value = catSlug;
    document.getElementById('subName').value = '';
    const cat = allCategories.find(c=>c.slug===catSlug);
    document.getElementById('subOrder').value = cat ? (cat.subcategories.length * 10) : 0;
    document.getElementById('subActive').value = '1';
    document.getElementById('subModal').style.display = 'block';
    // auto-expand
    const el = document.getElementById('subs-'+catSlug);
    if (el) el.style.display = 'block';
}
function openSubEditModal(id, catSlug, name, order, active) {
    document.getElementById('subModalTitle').textContent = 'Изменить подкатегорию';
    document.getElementById('subId').value = id;
    document.getElementById('subCatSlug').value = catSlug;
    document.getElementById('subName').value = name;
    document.getElementById('subOrder').value = order;
    document.getElementById('subActive').value = active;
    document.getElementById('subModal').style.display = 'block';
}
function closeSubModal() { document.getElementById('subModal').style.display = 'none'; }

async function saveSub() {
    const id      = document.getElementById('subId').value;
    const catSlug = document.getElementById('subCatSlug').value;
    const name    = document.getElementById('subName').value.trim();
    const order   = document.getElementById('subOrder').value;
    const active  = document.getElementById('subActive').value;
    if (!name) { alert('Введите название'); return; }
    const action = id ? 'sub_edit' : 'sub_add';
    const fd = new FormData();
    fd.append('action', action);
    if (id) fd.append('id', id); else fd.append('category_slug', catSlug);
    fd.append('name', name); fd.append('sort_order', order); fd.append('is_active', active);
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { closeSubModal(); loadCats(); showAlert('Сохранено', 'success'); }
    else showAlert(data.error, 'danger');
}

async function deleteSub(id, name) {
    if (!confirm(`Удалить подкатегорию "${name}"?`)) return;
    const fd = new FormData(); fd.append('action','sub_delete'); fd.append('id', id);
    const res = await fetch(API, {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) { loadCats(); showAlert('Подкатегория удалена', 'success'); }
    else showAlert(data.error, 'danger');
}

// ── Helpers ────────────────────────────────────────────────────────────────
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escQ(s) { return String(s).replace(/'/g,"\\'").replace(/"/g,'\\"'); }

function showAlert(msg, type) {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type}" style="margin-bottom:16px">${esc(msg)}</div>`;
    setTimeout(()=>{ box.innerHTML=''; }, 3000);
}

// Close modals on overlay click
['catModal','subModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });
});

loadCats();
</script>

<?php
$content = ob_get_clean();
renderLayout('Настройки — Категории', $content, $pendingCount, $pendingVerifCount, $pendingReviewCount);
?>
