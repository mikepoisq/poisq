<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDbConnection();

$stmtSvc = $pdo->query("
    SELECT id, name, updated_at
    FROM services
    WHERE status = 'approved' AND is_visible = 1
    ORDER BY updated_at DESC
");
$services = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Главная страница -->
  <url>
    <loc>https://poisq.com/</loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Статичные страницы -->
  <url>
    <loc>https://poisq.com/about.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc>https://poisq.com/help.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc>https://poisq.com/contact.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc>https://poisq.com/terms.php</loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

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
