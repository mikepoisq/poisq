<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cityId = (int)($_POST['city_id'] ?? 0);
    if ($action === 'toggle' && $cityId > 0) {
        $pdo->prepare("UPDATE cities SET status = IF(status='active','pending','active') WHERE id=?")->execute([$cityId]);
    } elseif ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $nameLat  = trim($_POST['name_lat'] ?? '');
        $country  = trim($_POST['country_code'] ?? '');
        $isCapital= (int)($_POST['is_capital'] ?? 0);
        if ($name && $country) {
            $pdo->prepare("INSERT INTO cities (name, name_lat, country_code, is_capital, status, sort_order) VALUES (?,?,?,?,'active',0)")
                ->execute([$name, $nameLat, strtolower($country), $isCapital]);
        }
    } elseif ($action === 'delete' && $cityId > 0) {
        $pdo->prepare("DELETE FROM cities WHERE id=?")->execute([$cityId]);
    }
    header("Location: /panel-5588/cities.php?country=" . urlencode($_POST['country_code'] ?? $_GET['country'] ?? ''));
    exit;
}

$countryFilter = trim($_GET['country'] ?? '');
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 30;

$countryNames = [
    'fr'=>'🇫🇷 Франция','de'=>'🇩🇪 Германия','es'=>'🇪🇸 Испания','it'=>'🇮🇹 Италия',
    'gb'=>'🇬🇧 Великобритания','us'=>'🇺🇸 США','ca'=>'🇨🇦 Канада','au'=>'🇦🇺 Австралия',
    'nl'=>'🇳🇱 Нидерланды','be'=>'🇧🇪 Бельгия','ch'=>'🇨🇭 Швейцария','at'=>'🇦🇹 Австрия',
    'pt'=>'🇵🇹 Португалия','gr'=>'🇬🇷 Греция','pl'=>'🇵🇱 Польша','cz'=>'🇨🇿 Чехия',
    'se'=>'🇸🇪 Швеция','no'=>'🇳🇴 Норвегия','dk'=>'🇩🇰 Дания','fi'=>'🇫🇮 Финляндия',
    'ie'=>'🇮🇪 Ирландия','nz'=>'🇳🇿 Новая Зеландия','ae'=>'🇦🇪 ОАЭ','il'=>'🇮🇱 Израиль',
    'tr'=>'🇹🇷 Турция','th'=>'🇹🇭 Таиланд','jp'=>'🇯🇵 Япония','kr'=>'🇰🇷 Корея',
    'sg'=>'🇸🇬 Сингапур','hk'=>'🇭🇰 Гонконг','mx'=>'🇲🇽 Мексика','br'=>'🇧🇷 Бразилия',
    'ar'=>'🇦🇷 Аргентина','cl'=>'🇨🇱 Чили','co'=>'🇨🇴 Колумбия','za'=>'🇿🇦 ЮАР',
    'ru'=>'🇷🇺 Россия','ua'=>'🇺🇦 Украина','by'=>'🇧🇾 Беларусь','kz'=>'🇰🇿 Казахстан',
];

$countries = $pdo->query("SELECT country_code, COUNT(*) as cnt FROM cities GROUP BY country_code ORDER BY country_code ASC")->fetchAll(PDO::FETCH_ASSOC);

$where  = ["1=1"];
$params = [];
if ($countryFilter) { $where[] = "country_code = ?"; $params[] = $countryFilter; }
if ($search) {
    $where[] = "(name LIKE ? OR name_lat LIKE ?)";
    $like = "%$search%"; $params[] = $like; $params[] = $like;
}
$whereSQL = "WHERE " . implode(" AND ", $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM cities $whereSQL");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM services s WHERE s.city_id=c.id AND s.status='approved') as services_count
    FROM cities c $whereSQL
    ORDER BY c.country_code ASC, c.is_capital DESC, c.sort_order ASC, c.name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$totalCities  = $pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn();
$activeCities = $pdo->query("SELECT COUNT(*) FROM cities WHERE status='active'")->fetchColumn();

ob_start();
?>

<div class="stat-grid stat-grid-3" style="margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-card-label">Всего городов</div>
        <div class="stat-card-value"><?php echo $totalCities; ?></div>
        <div class="stat-card-sub"><?php echo count($countries); ?> стран</div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Активных</div>
        <div class="stat-card-value"><?php echo $activeCities; ?></div>
    </div>
    <div class="stat-card gray">
        <div class="stat-card-label">Скрытых</div>
        <div class="stat-card-value"><?php echo $totalCities - $activeCities; ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <div class="panel-title">Города</div>
        <div class="panel-actions">
            <form method="GET" style="display:flex;gap:6px;">
                <?php if ($countryFilter): ?>
                <input type="hidden" name="country" value="<?php echo htmlspecialchars($countryFilter); ?>">
                <?php endif; ?>
                <input class="form-control" type="text" name="q"
                    placeholder="Поиск города..."
                    value="<?php echo htmlspecialchars($search); ?>"
                    style="width:200px;">
                <button type="submit" class="btn btn-primary btn-sm">🔍</button>
                <?php if ($search): ?>
                <a href="?country=<?php echo $countryFilter; ?>" class="btn btn-secondary btn-sm">✕</a>
                <?php endif; ?>
            </form>
            <button class="btn btn-primary btn-sm" onclick="toggleAddForm()">+ Добавить город</button>
        </div>
    </div>

    <!-- Форма добавления -->
    <div id="addForm" style="display:none;padding:16px 18px;border-bottom:1px solid var(--border-light);background:var(--bg);">
        <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;align-items:end;">
            <input type="hidden" name="action" value="add">
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Название (рус) *</label>
                <input type="text" name="name" class="form-control" placeholder="Париж" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Slug (лат)</label>
                <input type="text" name="name_lat" class="form-control" placeholder="paris">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Страна *</label>
                <select name="country_code" class="form-control form-select" required>
                    <?php foreach ($countryNames as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $countryFilter===$code?'selected':''; ?>>
                        <?php echo strtoupper($code); ?> — <?php echo strip_tags($name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px;">Столица?</label>
                <select name="is_capital" class="form-control form-select">
                    <option value="0">Нет</option>
                    <option value="1">Да</option>
                </select>
            </div>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Отмена</button>
            </div>
        </form>
    </div>

    <!-- Фильтр по странам -->
    <div style="padding:12px 18px;border-bottom:1px solid var(--border-light);display:flex;gap:6px;overflow-x:auto;">
        <a href="/panel-5588/cities.php<?php echo $search?'?q='.urlencode($search):''; ?>"
           class="chip <?php echo !$countryFilter?'active':''; ?>">Все</a>
        <?php foreach ($countries as $c):
            $code = $c['country_code'];
            $fullName = $countryNames[$code] ?? strtoupper($code);
        ?>
        <a href="/panel-5588/cities.php?country=<?php echo $code; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="chip <?php echo $countryFilter===$code?'active':''; ?>" style="white-space:nowrap;">
            <?php echo $fullName; ?>
            <span style="color:rgba(255,255,255,0.7);font-size:11px"> <?php echo $c['cnt']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="overflow-x:auto;">
        <?php if (empty($cities)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🌍</div>
            <div class="empty-state-title">Городов не найдено</div>
        </div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Город</th>
                    <th>Slug</th>
                    <th>Страна</th>
                    <th>Столица</th>
                    <th>Сервисов</th>
                    <th>Статус</th>
                    <th style="width:120px">Действия</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cities as $city):
                $isActive = ($city['status'] ?? 'active') === 'active';
            ?>
            <tr style="<?php echo !$isActive ? 'opacity:0.6' : ''; ?>">
                <td>
                    <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($city['name']); ?></div>
                </td>
                <td style="font-size:12px;color:var(--text-secondary);font-family:monospace;"><?php echo htmlspecialchars($city['name_lat'] ?? '—'); ?></td>
                <td>
                    <?php $flag = explode(' ', $countryNames[$city['country_code']] ?? '')[0]; ?>
                    <span style="font-size:13px;"><?php echo $flag; ?> <?php echo strtoupper($city['country_code']); ?></span>
                </td>
                <td><?php echo $city['is_capital'] ? '<span class="badge badge-blue">✓ столица</span>' : '—'; ?></td>
                <td>
                    <?php if ((int)$city['services_count'] > 0): ?>
                    <span style="font-size:13px;font-weight:600;color:var(--success);"><?php echo $city['services_count']; ?></span>
                    <?php else: ?>
                    <span style="color:var(--text-light);">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isActive): ?>
                    <span class="badge badge-green">Активен</span>
                    <?php else: ?>
                    <span class="badge badge-gray">Скрыт</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="city_id" value="<?php echo $city['id']; ?>">
                            <input type="hidden" name="country_code" value="<?php echo htmlspecialchars($countryFilter); ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="<?php echo $isActive?'Скрыть':'Показать'; ?>">
                                <?php echo $isActive ? '🙈' : '👁'; ?>
                            </button>
                        </form>
                        <?php if ((int)$city['services_count'] === 0): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Удалить город?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="city_id" value="<?php echo $city['id']; ?>">
                            <input type="hidden" name="country_code" value="<?php echo htmlspecialchars($countryFilter); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?country=<?php echo $countryFilter; ?>&page=<?php echo max(1,$page-1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page<=1?'disabled':''; ?>">← Назад</a>
        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
        <a href="?country=<?php echo $countryFilter; ?>&page=<?php echo $i; ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page===$i?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <a href="?country=<?php echo $countryFilter; ?>&page=<?php echo min($totalPages,$page+1); ?><?php echo $search?'&q='.urlencode($search):''; ?>"
           class="page-link <?php echo $page>=$totalPages?'disabled':''; ?>">Вперёд →</a>
        <span style="margin-left:auto;font-size:13px;color:var(--text-light);">Стр. <?php echo $page; ?> из <?php echo $totalPages; ?></span>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAddForm() {
    const f = document.getElementById('addForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php
$content = ob_get_clean();
renderLayout('Города', $content, $pendingCount);
?>
