<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin();

$pdo = getDbConnection();

$period = $_GET['period'] ?? '7';
$countryFilter = $_GET['country'] ?? '';
$periodMap = ['1'=>'Сегодня','7'=>'7 дней','30'=>'30 дней','90'=>'90 дней'];
$periodDays = in_array($period, ['1','7','30','90']) ? $period : '7';
$periodLabel = $periodMap[$periodDays];

$whereTime = $periodDays == 1
    ? "DATE(created_at) = CURDATE()"
    : "created_at >= DATE_SUB(NOW(), INTERVAL {$periodDays} DAY)";

$whereCountry = '';
$params = [];
if ($countryFilter) { $whereCountry = " AND country_code = ?"; $params[] = $countryFilter; }

$total     = $pdo->prepare("SELECT COUNT(*) FROM search_logs WHERE $whereTime $whereCountry"); $total->execute($params); $total = (int)$total->fetchColumn();
$found     = $pdo->prepare("SELECT COUNT(*) FROM search_logs WHERE $whereTime AND status='found' $whereCountry"); $found->execute($params); $found = (int)$found->fetchColumn();
$notFound  = $pdo->prepare("SELECT COUNT(*) FROM search_logs WHERE $whereTime AND status='not_found' $whereCountry"); $notFound->execute($params); $notFound = (int)$notFound->fetchColumn();
$viewsTotal= $pdo->prepare("SELECT COUNT(*) FROM page_views WHERE $whereTime $whereCountry"); $viewsTotal->execute($params); $viewsTotal = (int)$viewsTotal->fetchColumn();

$byCountry = $pdo->prepare("
    SELECT country_code, COUNT(*) as total,
           SUM(CASE WHEN status='found' THEN 1 ELSE 0 END) as found,
           SUM(CASE WHEN status='not_found' THEN 1 ELSE 0 END) as not_found
    FROM search_logs WHERE $whereTime
    GROUP BY country_code ORDER BY total DESC LIMIT 20
");
$byCountry->execute();
$byCountry = $byCountry->fetchAll(PDO::FETCH_ASSOC);

$topQueries = $pdo->prepare("
    SELECT query, country_code, COUNT(*) as cnt,
           SUM(CASE WHEN status='not_found' THEN 1 ELSE 0 END) as not_found_cnt,
           MAX(created_at) as last_at
    FROM search_logs
    WHERE $whereTime AND query != '' $whereCountry
    GROUP BY query, country_code ORDER BY cnt DESC LIMIT 50
");
$topQueries->execute($params);
$topQueries = $topQueries->fetchAll(PDO::FETCH_ASSOC);

$noResults = $pdo->prepare("
    SELECT query, country_code, COUNT(*) as cnt, MAX(created_at) as last_at
    FROM search_logs
    WHERE $whereTime AND status='not_found' AND query != '' $whereCountry
    GROUP BY query, country_code ORDER BY cnt DESC LIMIT 30
");
$noResults->execute($params);
$noResults = $noResults->fetchAll(PDO::FETCH_ASSOC);

$byDay = $pdo->prepare("
    SELECT DATE(created_at) as day,
           COUNT(*) as total,
           SUM(CASE WHEN status='found' THEN 1 ELSE 0 END) as found,
           SUM(CASE WHEN status='not_found' THEN 1 ELSE 0 END) as not_found
    FROM search_logs WHERE $whereTime $whereCountry
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$byDay->execute($params);
$byDay = $byDay->fetchAll(PDO::FETCH_ASSOC);

$countries = $pdo->query("SELECT DISTINCT country_code FROM search_logs ORDER BY country_code")->fetchAll(PDO::FETCH_COLUMN);
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();

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

ob_start();
?>

<!-- Фильтры -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <div class="filter-chips">
        <?php foreach ($periodMap as $days => $label): ?>
        <a href="?period=<?php echo $days; ?><?php echo $countryFilter?'&country='.$countryFilter:''; ?>"
           class="chip <?php echo $periodDays==$days?'active':''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
    <select class="form-control form-select" style="width:180px;" onchange="location.href='?period=<?php echo $periodDays; ?>&country='+this.value">
        <option value="">🌍 Все страны</option>
        <?php foreach ($countries as $code): ?>
        <option value="<?php echo $code; ?>" <?php echo $countryFilter===$code?'selected':''; ?>>
            <?php echo $countryNames[$code] ?? strtoupper($code); ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php if ($countryFilter): ?>
    <a href="?period=<?php echo $periodDays; ?>" class="btn btn-secondary btn-sm">✕ Сбросить</a>
    <?php endif; ?>
</div>

<!-- Статистика -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-card-label">Визиты</div>
        <div class="stat-card-value"><?php echo $viewsTotal; ?></div>
        <div class="stat-card-sub"><?php echo $periodLabel; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Запросов</div>
        <div class="stat-card-value"><?php echo $total; ?></div>
        <div class="stat-card-sub"><?php echo $periodLabel; ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-card-label">Найдено</div>
        <div class="stat-card-value"><?php echo $found; ?></div>
        <div class="stat-card-sub"><?php echo $total > 0 ? round($found/$total*100) : 0; ?>%</div>
    </div>
    <div class="stat-card red">
        <div class="stat-card-label">Не найдено</div>
        <div class="stat-card-value"><?php echo $notFound; ?></div>
        <div class="stat-card-sub"><?php echo $total > 0 ? round($notFound/$total*100) : 0; ?>%</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

<!-- График -->
<?php if (!empty($byDay)): ?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">📈 График по дням</div>
        <span style="font-size:12px;color:var(--text-light);"><?php echo $periodLabel; ?></span>
    </div>
    <?php $maxVal = max(array_column($byDay, 'total') ?: [1]); ?>
    <div style="padding:16px;">
        <div style="display:flex;align-items:flex-end;gap:4px;height:100px;margin-bottom:8px;">
            <?php foreach ($byDay as $d):
                $hFound = $maxVal > 0 ? round(($d['found']/$maxVal)*85) : 0;
                $hNf    = $maxVal > 0 ? round(($d['not_found']/$maxVal)*85) : 0;
                $dayLabel = date('d.m', strtotime($d['day']));
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:0;" title="<?php echo $dayLabel; ?>: найдено <?php echo $d['found']; ?>, не найдено <?php echo $d['not_found']; ?>">
                <div style="width:100%;display:flex;flex-direction:column;align-items:stretch;justify-content:flex-end;height:85px;gap:1px;">
                    <div style="background:#FCA5A5;border-radius:2px 2px 0 0;height:<?php echo $hNf; ?>px;min-height:<?php echo $d['not_found']>0?'2':'0'; ?>px;"></div>
                    <div style="background:var(--success);border-radius:2px 2px 0 0;height:<?php echo max(2,$hFound); ?>px;"></div>
                </div>
                <div style="font-size:9px;color:var(--text-light);white-space:nowrap;overflow:hidden;"><?php echo $dayLabel; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;font-size:12px;color:var(--text-secondary);">
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--success);display:inline-block;"></span>Найдено</span>
            <span style="display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;border-radius:2px;background:#FCA5A5;display:inline-block;"></span>Не найдено</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- По странам -->
<?php if (!empty($byCountry) && !$countryFilter): ?>
<div class="panel">
    <div class="panel-header"><div class="panel-title">🌍 По странам</div></div>
    <div style="padding:8px 0;">
        <?php $maxC = max(array_column($byCountry, 'total') ?: [1]); ?>
        <?php foreach (array_slice($byCountry, 0, 8) as $c): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 16px;">
            <div style="width:120px;font-size:13px;font-weight:500;flex-shrink:0;">
                <?php echo $countryNames[$c['country_code']] ?? strtoupper($c['country_code']); ?>
            </div>
            <div style="flex:1;height:6px;background:var(--border-light);border-radius:99px;overflow:hidden;">
                <div style="height:100%;background:var(--primary);border-radius:99px;width:<?php echo round($c['total']/$maxC*100); ?>%;"></div>
            </div>
            <div style="font-size:13px;font-weight:700;color:var(--text);width:30px;text-align:right;"><?php echo $c['total']; ?></div>
            <?php if ($c['not_found'] > 0): ?>
            <div style="font-size:11px;color:var(--danger);font-weight:600;width:30px;">✗<?php echo $c['not_found']; ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</div>

<!-- Таблицы запросов -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<!-- Топ запросов -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">🔝 Топ запросов</div>
        <span style="font-size:12px;color:var(--text-light);"><?php echo $periodLabel; ?></span>
    </div>
    <?php if (empty($topQueries)): ?>
    <div class="empty-state"><div class="empty-state-icon">📊</div><div class="empty-state-title">Данных пока нет</div></div>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th style="width:30px">#</th>
            <th>Запрос</th>
            <th>Страна</th>
            <th>Раз</th>
            <th>Статус</th>
        </tr></thead>
        <tbody>
        <?php foreach ($topQueries as $i => $q): ?>
        <tr>
            <td style="font-size:12px;color:var(--text-light);"><?php echo $i+1; ?></td>
            <td>
                <div style="font-size:13px;font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($q['query']); ?></div>
                <div style="font-size:11px;color:var(--text-light);"><?php echo date('d.m H:i', strtotime($q['last_at'])); ?></div>
            </td>
            <td style="font-size:12px;font-weight:700;color:var(--text-secondary);">
                <?php echo explode(' ', $countryNames[$q['country_code']] ?? '')[0]; ?>
            </td>
            <td><span class="badge badge-blue"><?php echo $q['cnt']; ?>×</span></td>
            <td>
                <?php if ($q['not_found_cnt'] > 0): ?>
                <span class="badge badge-red">✗<?php echo $q['not_found_cnt']; ?></span>
                <?php else: ?>
                <span class="badge badge-green">✓</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Не найдено -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">❌ Не найдено</div>
        <span style="font-size:12px;color:var(--text-light);">Пробелы в каталоге</span>
    </div>
    <?php if (empty($noResults)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">🎉</div>
        <div class="empty-state-title">Всё находится!</div>
        <div class="empty-state-text">Запросов без результатов нет</div>
    </div>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th style="width:30px">#</th>
            <th>Запрос</th>
            <th>Страна</th>
            <th>Раз</th>
        </tr></thead>
        <tbody>
        <?php foreach ($noResults as $i => $q): ?>
        <tr>
            <td style="font-size:12px;color:var(--text-light);"><?php echo $i+1; ?></td>
            <td>
                <div style="font-size:13px;font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($q['query']); ?></div>
                <div style="font-size:11px;color:var(--text-light);"><?php echo date('d.m H:i', strtotime($q['last_at'])); ?></div>
            </td>
            <td style="font-size:12px;font-weight:700;color:var(--text-secondary);">
                <?php echo explode(' ', $countryNames[$q['country_code']] ?? '')[0]; ?>
            </td>
            <td><span class="badge badge-red"><?php echo $q['cnt']; ?>×</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

</div>

<?php
$content = ob_get_clean();
renderLayout('Аналитика поиска', $content, $pendingCount, 0, $pendingReviewCount);
?>
