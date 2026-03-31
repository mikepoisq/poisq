<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDbConnection();

// Все одобренные сервисы
$stmtSvc = $pdo->query("
    SELECT id, name, updated_at
    FROM services
    WHERE status = 'approved' AND is_visible = 1
    ORDER BY updated_at DESC
");
$services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

// Все активные страны (у которых есть хотя бы один сервис)
$stmtCountries = $pdo->query("
    SELECT DISTINCT country_code
    FROM services
    WHERE status = 'approved' AND is_visible = 1
    ORDER BY country_code
");
$countries = $stmtCountries->fetchAll(PDO::FETCH_COLUMN);

// Категории
$categories = [
    'health', 'legal', 'family', 'education', 'business',
    'shops', 'home', 'transport', 'it', 'events', 'realestate',
];

// Популярные запросы для страниц поиска
$popularQueries = [
    'врач', 'юрист', 'репетитор', 'переводчик', 'психолог',
    'стоматолог', 'бухгалтер', 'нотариус', 'фотограф', 'массаж',
];

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Главная страница -->
  <url>
    <loc>https://poisq.com/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Страницы стран -->
  <?php foreach ($countries as $cc): ?>
  <url>
    <loc>https://poisq.com/results.php?country=<?php echo urlencode($cc); ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <?php endforeach; ?>

  <!-- Страницы категорий по странам -->
  <?php foreach ($countries as $cc): ?>
    <?php foreach ($categories as $cat): ?>
  <url>
    <loc>https://poisq.com/results.php?country=<?php echo urlencode($cc); ?>&amp;category=<?php echo urlencode($cat); ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <!-- Популярные поисковые запросы по странам -->
  <?php foreach ($countries as $cc): ?>
    <?php foreach ($popularQueries as $q): ?>
  <url>
    <loc>https://poisq.com/results.php?q=<?php echo urlencode($q); ?>&amp;country=<?php echo urlencode($cc); ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <!-- Карточки сервисов -->
  <?php foreach ($services as $s): ?>
  <url>
    <loc>https://poisq.com<?php echo htmlspecialchars(serviceUrl($s['id'], $s['name'])); ?></loc>
    <lastmod><?php echo date('Y-m-d', strtotime($s['updated_at'])); ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>

</urlset>