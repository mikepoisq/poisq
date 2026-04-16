<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
header('Content-Type: application/xml; charset=utf-8');
$pdo = getDbConnection();

// Сервисы
$services = $pdo->query("
    SELECT id, name, updated_at
    FROM services
    WHERE status = 'approved' AND is_visible = 1
    ORDER BY updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Страны где есть сервисы
$countries = $pdo->query("
    SELECT DISTINCT country_code
    FROM services
    WHERE status = 'approved' AND is_visible = 1
")->fetchAll(PDO::FETCH_COLUMN);

// Города где есть сервисы
$cities = $pdo->query("
    SELECT DISTINCT c.name_lat, c.country_code
    FROM services s
    JOIN cities c ON s.city_id = c.id
    WHERE s.status = 'approved' AND s.is_visible = 1
    AND c.name_lat IS NOT NULL AND c.name_lat != ''
")->fetchAll(PDO::FETCH_ASSOC);

// Категории
$categories = [
    'health', 'legal', 'family', 'education', 'business',
    'shops', 'home', 'transport', 'it', 'events', 'realestate'
];

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Главная -->
  <url>
    <loc>https://poisq.com/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Статичные страницы -->
  <url><loc>https://poisq.com/about.php</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
  <url><loc>https://poisq.com/help.php</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
  <url><loc>https://poisq.com/contact.php</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>
  <url><loc>https://poisq.com/terms.php</loc><changefreq>monthly</changefreq><priority>0.5</priority></url>

  <!-- Страницы стран -->
  <?php foreach ($countries as $cc): ?>
  <url>
    <loc>https://poisq.com/<?php echo $cc; ?>/</loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>

  <?php endforeach; ?>

  <!-- Страницы городов -->
  <?php foreach ($cities as $city): ?>
  <url>
    <loc>https://poisq.com/<?php echo $city['country_code']; ?>/<?php echo strtolower($city['name_lat']); ?>/</loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>

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
