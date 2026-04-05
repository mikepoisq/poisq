<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../layout.php';
requireAdmin();

$pdo      = getDbConnection();
$id       = (int)($_GET['id'] ?? 0);
$article  = null;
$msg      = '';
$errors   = [];

// ── Transliteration helper ──────────────────────────────────
function makeSlug(string $str): string {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh',
            'щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $str = mb_strtolower($str);
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9\-]+/', '-', $str);
    return trim(preg_replace('/-+/', '-', $str), '-');
}

// ── Load existing if editing ─────────────────────────────────
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM articles WHERE id=?");
    $st->execute([$id]);
    $article = $st->fetch(PDO::FETCH_ASSOC);
    if (!$article) { header('Location: articles.php'); exit; }
}

// ── Load categories & countries ──────────────────────────────
$categoriesList = $pdo->query("SELECT * FROM article_categories WHERE is_active=1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);

$_countryNames = [
    'ae'=>'ОАЭ','al'=>'Албания','am'=>'Армения','ar'=>'Аргентина','at'=>'Австрия',
    'au'=>'Австралия','az'=>'Азербайджан','ba'=>'Босния и Герцеговина','be'=>'Бельгия',
    'bg'=>'Болгария','br'=>'Бразилия','by'=>'Беларусь','ca'=>'Канада','ch'=>'Швейцария',
    'cl'=>'Чили','co'=>'Колумбия','cy'=>'Кипр','cz'=>'Чехия','de'=>'Германия',
    'dk'=>'Дания','ee'=>'Эстония','es'=>'Испания','fi'=>'Финляндия','fr'=>'Франция',
    'gb'=>'Великобритания','ge'=>'Грузия','gr'=>'Греция','hk'=>'Гонконг','hr'=>'Хорватия',
    'hu'=>'Венгрия','ie'=>'Ирландия','il'=>'Израиль','it'=>'Италия','jp'=>'Япония',
    'kr'=>'Южная Корея','kz'=>'Казахстан','lt'=>'Литва','lv'=>'Латвия','md'=>'Молдова',
    'me'=>'Черногория','mk'=>'Северная Македония','mt'=>'Мальта','mx'=>'Мексика',
    'nl'=>'Нидерланды','no'=>'Норвегия','nz'=>'Новая Зеландия','pl'=>'Польша',
    'pt'=>'Португалия','ro'=>'Румыния','rs'=>'Сербия','ru'=>'Россия','se'=>'Швеция',
    'sg'=>'Сингапур','si'=>'Словения','sk'=>'Словакия','th'=>'Таиланд','tr'=>'Турция',
    'ua'=>'Украина','us'=>'США','xk'=>'Косово','za'=>'ЮАР',
];
$_dbCodes = $pdo->query("SELECT DISTINCT country_code FROM cities ORDER BY country_code")->fetchAll(PDO::FETCH_COLUMN);
$countriesList = [];
foreach ($_dbCodes as $_code) {
    $_codeL = strtolower($_code);
    $countriesList[$_codeL] = $_countryNames[$_codeL] ?? strtoupper($_code);
}
asort($countriesList);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && $id > 0) {
        $pdo->prepare("DELETE FROM articles WHERE id=?")->execute([$id]);
        header('Location: articles.php?msg=deleted');
        exit;
    }

    $title       = trim($_POST['title']       ?? '');
    $excerpt     = trim($_POST['excerpt']     ?? '');
    $content     = trim($_POST['content']     ?? '');
    $category    = trim($_POST['category']    ?? '');
    $country_code= trim($_POST['country_code']?? 'all');
    $read_time   = trim($_POST['read_time']   ?? '5 мин');
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $status      = in_array($_POST['status'] ?? '', ['published','draft']) ? $_POST['status'] : 'draft';
    $slug        = trim($_POST['slug']        ?? '');

    if (!$title) $errors[] = 'Заголовок обязателен';

    if (!$slug) $slug = makeSlug($title);

    // Ensure unique slug (append id if needed)
    $existingSlug = $pdo->prepare("SELECT id FROM articles WHERE slug=? AND id!=?");
    $existingSlug->execute([$slug, $id ?: 0]);
    if ($existingSlug->fetchColumn()) {
        $slug .= '-' . ($id ?: time());
    }

    // Photo upload
    $photoPath = $article['photo'] ?? '';
    if (!empty($_FILES['photo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $uploadDir = __DIR__ . '/../../uploads/articles/';
            $filename  = 'article_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photoPath = '/uploads/articles/' . $filename;
            }
        } else {
            $errors[] = 'Допустимые форматы фото: jpg, jpeg, png, webp';
        }
    }

    if (empty($errors)) {
        if ($id > 0) {
            $pdo->prepare("UPDATE articles SET title=?, excerpt=?, content=?, category=?, country_code=?,
                           read_time=?, sort_order=?, status=?, slug=?, photo=?, updated_at=NOW()
                           WHERE id=?")
                ->execute([$title, $excerpt, $content, $category, $country_code,
                           $read_time, $sort_order, $status, $slug, $photoPath, $id]);
            // Rename photo if it had 'new' in name
            if (!empty($photoPath) && strpos($photoPath, '_new_') !== false) {
                $newPath = str_replace('_new_', '_' . $id . '_', $photoPath);
                rename(__DIR__ . '/../../' . ltrim($photoPath, '/'),
                       __DIR__ . '/../../' . ltrim($newPath, '/'));
                $pdo->prepare("UPDATE articles SET photo=? WHERE id=?")->execute([$newPath, $id]);
            }
            $msg = 'updated';
        } else {
            $pdo->prepare("INSERT INTO articles (title, excerpt, content, category, country_code, read_time, sort_order, status, slug, photo, created_at, updated_at)
                           VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                ->execute([$title, $excerpt, $content, $category, $country_code,
                           $read_time, $sort_order, $status, $slug, $photoPath]);
            $newId = (int)$pdo->lastInsertId();
            // Rename photo from _new_ to _id_
            if (!empty($photoPath) && strpos($photoPath, '_new_') !== false) {
                $newPath = str_replace('_new_', '_' . $newId . '_', $photoPath);
                rename(__DIR__ . '/../../' . ltrim($photoPath, '/'),
                       __DIR__ . '/../../' . ltrim($newPath, '/'));
                $pdo->prepare("UPDATE articles SET photo=? WHERE id=?")->execute([$newPath, $newId]);
            }
            header("Location: article-edit.php?id=$newId&msg=created");
            exit;
        }
        // Reload
        $st = $pdo->prepare("SELECT * FROM articles WHERE id=?");
        $st->execute([$id]);
        $article = $st->fetch(PDO::FETCH_ASSOC);
    }
}

$msg = $_GET['msg'] ?? $msg;

// Defaults
$a = $article ?? [];
$a['status']       ??= 'draft';
$a['country_code'] ??= 'all';
$a['sort_order']   ??= 0;
$a['read_time']    ??= '5 мин';
$a['category']     ??= '';

ob_start();
?>
<style>
.CodeMirror, .EasyMDEContainer .CodeMirror { font-size: 14px !important; }
.editor-toolbar { border-color: var(--border); }
.CodeMirror { border-color: var(--border); border-radius: 0 0 var(--radius-sm) var(--radius-sm); }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">

<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <a href="articles.php" class="btn btn-secondary btn-sm">← Назад</a>
    <h2 style="font-size:16px;font-weight:700;color:var(--text)"><?php echo $id ? 'Редактировать статью' : 'Новая статья'; ?></h2>
    <?php if ($id): ?>
    <a href="https://poisq.com/article/<?php echo htmlspecialchars($a['country_code']); ?>/<?php echo htmlspecialchars($a['slug'] ?? ''); ?>"
       target="_blank" class="btn btn-secondary btn-sm" style="margin-left:auto">
        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Открыть статью
    </a>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo implode('; ', $errors); ?>
</div>
<?php elseif ($msg): ?>
<div class="alert alert-success" style="margin-bottom:16px">
    <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php echo ['created'=>'Статья создана','updated'=>'Статья сохранена'][$msg] ?? 'Готово'; ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="action" value="save">

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

<!-- Основная колонка -->
<div style="display:flex;flex-direction:column;gap:16px">

    <div class="panel">
        <div class="panel-header"><div class="panel-title">Содержание</div></div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px">

            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Заголовок *</label>
                <input type="text" name="title" class="form-control" required
                    value="<?php echo htmlspecialchars($a['title'] ?? ''); ?>"
                    placeholder="Как открыть банковский счёт во Франции"
                    oninput="autoSlug(this.value)">
            </div>

            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Краткое описание (excerpt)</label>
                <textarea name="excerpt" class="form-control" maxlength="200" style="min-height:70px;resize:vertical"
                    placeholder="До 200 символов — показывается в карточке статьи"><?php echo htmlspecialchars($a['excerpt'] ?? ''); ?></textarea>
            </div>

            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Текст статьи (Markdown)</label>
                <textarea name="content" id="mdEditor"><?php echo htmlspecialchars($a['content'] ?? ''); ?></textarea>
            </div>

        </div>
    </div>

</div>

<!-- Боковая колонка -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Публикация -->
    <div class="panel">
        <div class="panel-header"><div class="panel-title">Публикация</div></div>
        <div style="padding:14px;display:flex;flex-direction:column;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Статус</label>
                <select name="status" class="form-control form-select">
                    <option value="draft"     <?php echo $a['status']==='draft'     ?'selected':''; ?>>Черновик</option>
                    <option value="published" <?php echo $a['status']==='published' ?'selected':''; ?>>Опубликована</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Slug (URL)</label>
                <input type="text" name="slug" id="slugField" class="form-control"
                    value="<?php echo htmlspecialchars($a['slug'] ?? ''); ?>"
                    placeholder="auto-generated-from-title">
                <div style="font-size:11px;color:var(--text-light);margin-top:3px">Автозаполняется из заголовка</div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn btn-primary" style="flex:1">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg>
                    Сохранить
                </button>
                <?php if ($id): ?>
                <button type="submit" name="action" value="delete"
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Удалить статью навсегда?')" title="Удалить">🗑</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Параметры -->
    <div class="panel">
        <div class="panel-header"><div class="panel-title">Параметры</div></div>
        <div style="padding:14px;display:flex;flex-direction:column;gap:10px">
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                    <label style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;margin:0">Рубрика</label>
                    <button type="button" onclick="openCatModal()" style="font-size:11px;color:var(--primary);background:none;border:none;cursor:pointer;padding:0;text-decoration:underline">Управление рубриками</button>
                </div>
                <select name="category" id="categorySelect" class="form-control form-select">
                    <option value="">— Без рубрики —</option>
                    <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"
                        <?php echo $a['category']===$cat['name']?'selected':''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Страна</label>
                <select name="country_code" class="form-control form-select">
                    <option value="all" <?php echo $a['country_code']==='all'?'selected':''; ?>>🌍 Все страны</option>
                    <?php foreach ($countriesList as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"
                        <?php echo $a['country_code']===$code?'selected':''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Время чтения</label>
                <input type="text" name="read_time" class="form-control"
                    value="<?php echo htmlspecialchars($a['read_time'] ?? '5 мин'); ?>"
                    placeholder="5 мин">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-control"
                    value="<?php echo $a['sort_order'] ?? 0; ?>" min="0" step="10">
            </div>
        </div>
    </div>

    <!-- Фото -->
    <div class="panel">
        <div class="panel-header"><div class="panel-title">Фото</div></div>
        <div style="padding:14px">
            <?php if (!empty($a['photo'])): ?>
            <img src="<?php echo htmlspecialchars($a['photo']); ?>" alt=""
                style="width:100%;height:160px;object-fit:cover;border-radius:6px;margin-bottom:10px;display:block">
            <?php endif; ?>
            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp"
                style="padding:6px">
            <div style="font-size:11px;color:var(--text-light);margin-top:4px">JPG, PNG или WebP. Сохраняется в /uploads/articles/</div>
        </div>
    </div>

</div>
</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
// EasyMDE
const easyMDE = new EasyMDE({
    element: document.getElementById('mdEditor'),
    spellChecker: false,
    autosave: { enabled: true, uniqueId: 'article-<?php echo $id ?: "new"; ?>' },
    toolbar: ['bold','italic','heading','|','quote','unordered-list','ordered-list','|','link','image','upload-image','|','preview','side-by-side','fullscreen','|','guide'],
    placeholder: 'Текст статьи в формате Markdown...',
    minHeight: '300px',
    uploadImage: true,
    imageUploadFunction: function(file, onSuccess, onError) {
        if (file.size > 5 * 1024 * 1024) { onError('Файл слишком большой. Макс. 5MB.'); return; }
        var allowed = ['image/jpeg','image/png','image/webp'];
        if (!allowed.includes(file.type)) { onError('Допустимые форматы: jpg, png, webp'); return; }
        var fd = new FormData();
        fd.append('image', file);
        fetch('upload-image.php', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (data.data && data.data.filePath) { onSuccess(data.data.filePath); }
                else { onError(data.error || 'Ошибка загрузки'); }
            })
            .catch(function(){ onError('Ошибка сети'); });
    },
});

// Auto-slug
const cyrMap = {а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'yo',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'kh',ц:'ts',ч:'ch',ш:'sh',щ:'shch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
function toSlug(str) {
    return str.toLowerCase()
        .split('').map(c => cyrMap[c] !== undefined ? cyrMap[c] : c).join('')
        .replace(/[^a-z0-9\-]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}
let slugLocked = <?php echo !empty($a['slug']) ? 'true' : 'false'; ?>;
document.getElementById('slugField').addEventListener('input', () => { slugLocked = true; });
function autoSlug(val) {
    if (!slugLocked) document.getElementById('slugField').value = toSlug(val);
}
</script>

<!-- ── Category management modal ──────────────────────────────── -->
<div id="catModal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,0.5);overflow-y:auto">
  <div style="background:var(--bg);border-radius:10px;max-width:640px;width:90%;margin:40px auto;padding:0;box-shadow:var(--shadow-md)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)">
      <h3 style="font-size:15px;font-weight:700;color:var(--text);margin:0">Управление рубриками</h3>
      <button onclick="closeCatModal()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:20px;line-height:1;padding:0">&times;</button>
    </div>

    <!-- List -->
    <div id="catList" style="padding:16px 20px;display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto">
      <div style="color:var(--text-secondary);font-size:13px">Загрузка...</div>
    </div>

    <!-- Add / Edit form -->
    <div style="padding:16px 20px;border-top:1px solid var(--border);background:var(--bg-secondary);border-radius:0 0 10px 10px">
      <div id="catFormTitle" style="font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:10px">Добавить рубрику</div>
      <input type="hidden" id="catEditId" value="">
      <div style="display:grid;grid-template-columns:1fr 80px 80px 60px;gap:8px;align-items:end">
        <div>
          <label style="font-size:11px;color:var(--text-secondary);display:block;margin-bottom:3px">Название *</label>
          <input type="text" id="catName" class="form-control" placeholder="Банки и финансы" style="font-size:13px;padding:6px 10px">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-secondary);display:block;margin-bottom:3px">Цвет текста</label>
          <input type="color" id="catColor" value="#1a73e8" style="width:100%;height:34px;padding:2px;border:1px solid var(--border);border-radius:6px;cursor:pointer">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-secondary);display:block;margin-bottom:3px">Фон бейджа</label>
          <input type="color" id="catBgColor" value="#e8f0fe" style="width:100%;height:34px;padding:2px;border:1px solid var(--border);border-radius:6px;cursor:pointer">
        </div>
        <div>
          <label style="font-size:11px;color:var(--text-secondary);display:block;margin-bottom:3px">Порядок</label>
          <input type="number" id="catOrder" value="0" min="0" step="10" class="form-control" style="font-size:13px;padding:6px 6px">
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button onclick="saveCat()" class="btn btn-primary btn-sm" style="font-size:13px">Сохранить</button>
        <button onclick="cancelCatEdit()" id="catCancelBtn" class="btn btn-secondary btn-sm" style="font-size:13px;display:none">Отмена</button>
      </div>
    </div>
  </div>
</div>

<script>
const CAT_API = 'api-categories.php';

function openCatModal() {
    document.getElementById('catModal').style.display = 'block';
    loadCats();
}
function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
}
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeCatModal();
});

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── List ─────────────────────────────────────────────────────────
function loadCats() {
    const list = document.getElementById('catList');
    list.innerHTML = '<div style="color:var(--text-secondary);font-size:13px">Загрузка...</div>';
    fetch(CAT_API + '?action=list', { credentials: 'same-origin' })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(function(text) {
            let data;
            try { data = JSON.parse(text); } catch(e) {
                console.error('api-categories JSON parse error. Response:', text.substring(0, 500));
                list.innerHTML = '<div style="color:var(--danger);font-size:13px">Ошибка: неверный ответ сервера. Смотри консоль.</div>';
                return;
            }
            if (!data.success) {
                if (data.error === 'auth') {
                    list.innerHTML = '<div style="color:var(--danger);font-size:13px">Сессия истекла. Обновите страницу.</div>';
                } else {
                    console.error('api-categories error:', data.error);
                    list.innerHTML = '<div style="color:var(--danger);font-size:13px">Ошибка: ' + escH(data.error || 'неизвестная') + '</div>';
                }
                return;
            }
            if (!data.categories || !data.categories.length) {
                list.innerHTML = '<div style="color:var(--text-secondary);font-size:13px">Рубрик пока нет</div>';
                return;
            }
            list.innerHTML = '';
            data.categories.forEach(function(c) {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--bg);border:1px solid var(--border);border-radius:6px';
                row._cat = { id: c.id, name: c.name, color: c.color, bg_color: c.bg_color, sort_order: c.sort_order };
                renderCatRowStatic(row);
                list.appendChild(row);
            });
        })
        .catch(function(err) {
            console.error('api-categories fetch error:', err);
            list.innerHTML = '<div style="color:var(--danger);font-size:13px">Ошибка загрузки: ' + escH(err.message) + '</div>';
        });
}

function renderCatRowStatic(row) {
    const c = row._cat;
    row.innerHTML =
        '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600;' +
            'color:' + escH(c.color) + ';background:' + escH(c.bg_color) + ';flex:1">' + escH(c.name) + '</span>' +
        '<button class="cat-edit-btn" style="font-size:11px;color:var(--primary);background:none;border:none;cursor:pointer;padding:2px 8px">Изм.</button>' +
        '<button class="cat-del-btn" style="font-size:11px;color:var(--danger);background:none;border:none;cursor:pointer;padding:2px 8px">Удалить</button>';
    row.querySelector('.cat-edit-btn').addEventListener('click', function() { renderCatRowEdit(row); });
    row.querySelector('.cat-del-btn').addEventListener('click', function() { deleteCatRow(row); });
}

function renderCatRowEdit(row) {
    const c = row._cat;
    row.innerHTML =
        '<input type="text" class="form-control cat-inp-name"' +
            ' style="flex:1;font-size:13px;padding:4px 8px;min-width:0">' +
        '<input type="color" class="cat-inp-color"' +
            ' style="width:34px;height:28px;padding:2px;border:1px solid var(--border);border-radius:4px;cursor:pointer" title="Цвет текста">' +
        '<input type="color" class="cat-inp-bg"' +
            ' style="width:34px;height:28px;padding:2px;border:1px solid var(--border);border-radius:4px;cursor:pointer" title="Фон бейджа">' +
        '<button class="cat-save-btn btn btn-primary btn-sm" style="font-size:12px;padding:3px 10px;white-space:nowrap">Сохр.</button>' +
        '<button class="cat-cancel-btn btn btn-secondary btn-sm" style="font-size:12px;padding:3px 8px">✕</button>';
    // Set values via JS to avoid HTML-escaping issues
    row.querySelector('.cat-inp-name').value  = c.name;
    row.querySelector('.cat-inp-color').value = c.color;
    row.querySelector('.cat-inp-bg').value    = c.bg_color;
    row.querySelector('.cat-inp-name').focus();
    row.querySelector('.cat-cancel-btn').addEventListener('click', function() { renderCatRowStatic(row); });
    row.querySelector('.cat-save-btn').addEventListener('click', function() {
        const name = row.querySelector('.cat-inp-name').value.trim();
        if (!name) { alert('Введите название рубрики'); return; }
        const fd = new FormData();
        fd.append('action',     'update');
        fd.append('id',         c.id);
        fd.append('name',       name);
        fd.append('color',      row.querySelector('.cat-inp-color').value);
        fd.append('bg_color',   row.querySelector('.cat-inp-bg').value);
        fd.append('sort_order', c.sort_order);
        fd.append('is_active',  '1');
        fetch(CAT_API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.error || 'Ошибка при сохранении'); return; }
                row._cat = Object.assign({}, c, {
                    name:     name,
                    color:    row.querySelector('.cat-inp-color').value,
                    bg_color: row.querySelector('.cat-inp-bg').value,
                });
                renderCatRowStatic(row);
                refreshCategorySelect();
            });
    });
}

function deleteCatRow(row) {
    if (!confirm('Вы уверены? Удалить рубрику «' + row._cat.name + '»?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id',     row._cat.id);
    fetch(CAT_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.error || 'Ошибка при удалении'); return; }
            row.remove();
            const list = document.getElementById('catList');
            if (!list.children.length) {
                list.innerHTML = '<div style="color:var(--text-secondary);font-size:13px">Рубрик пока нет</div>';
            }
            refreshCategorySelect();
        });
}

// ── Add new (bottom form) ────────────────────────────────────────
function saveCat() {
    const name = document.getElementById('catName').value.trim();
    if (!name) { alert('Введите название рубрики'); return; }
    const fd = new FormData();
    fd.append('action',     'create');
    fd.append('name',       name);
    fd.append('color',      document.getElementById('catColor').value);
    fd.append('bg_color',   document.getElementById('catBgColor').value);
    fd.append('sort_order', document.getElementById('catOrder').value);
    fetch(CAT_API, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.error || 'Ошибка'); return; }
            document.getElementById('catName').value    = '';
            document.getElementById('catColor').value   = '#1a73e8';
            document.getElementById('catBgColor').value = '#e8f0fe';
            document.getElementById('catOrder').value   = '0';
            loadCats();
            refreshCategorySelect();
        })
        .catch(err => { console.error('saveCat error:', err); alert('Ошибка сети'); });
}

// ── Refresh page dropdown ─────────────────────────────────────────
function refreshCategorySelect() {
    const sel = document.getElementById('categorySelect');
    const current = sel.value;
    fetch(CAT_API + '?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const opts = [new Option('— Без рубрики —', '')];
            data.categories.forEach(c => opts.push(new Option(c.name, c.name)));
            sel.innerHTML = '';
            opts.forEach(o => sel.appendChild(o));
            sel.value = current;
        });
}
</script>
<?php
$content = ob_get_clean();
renderLayout(($id ? 'Редактировать' : 'Новая') . ' статью', $content);
