<?php
// service.php — Страница сервиса Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($serviceId <= 0) {
    header('Location: index.php');
    exit;
}

// ── 301 редирект /service.php?id=X → /service/X-slug ────────
// Срабатывает только при прямом обращении к service.php?id=
// (не когда .htaccess внутренне маршрутизирует через RewriteRule)
if (strpos($_SERVER['REQUEST_URI'], 'service.php') !== false) {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/helpers.php';
    try {
        $pdo  = getDbConnection();
        $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
        $stmt->execute([$serviceId]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: https://poisq.com' . serviceUrl($serviceId, $row['name']));
            exit;
        }
    } catch (Exception $e) {
        // Ошибка БД — показываем страницу как есть
    }
}

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? $_SESSION['user_name']   : '';
$userAvatar = $isLoggedIn ? $_SESSION['user_avatar']  : '';

// ── ЗАПРОС К БАЗЕ ДАННЫХ ──────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

try {
    $pdo  = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT
            s.id, s.name, s.category, s.subcategory,
            s.description, s.photo, s.phone, s.whatsapp,
            s.email, s.website, s.address,
            s.hours, s.languages, s.services AS service_list,
            s.social, s.verified, s.verified_until, s.rating, s.reviews_count,
            s.views, s.status, s.is_visible, s.group_link,
            s.user_id, s.country_code, s.created_at, s.category,
            c.name AS city_name,
            c.name_lat AS city_name_lat
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Сервис не найден, не одобрен или скрыт — уходим на главную
    if (!$row || !in_array($row['status'], ['approved', 'pending']) || !$row['is_visible']) {
        header('Location: index.php');
        exit;
    }

    // Увеличиваем счётчик просмотров
    $pdo->prepare("UPDATE services SET views = views + 1 WHERE id = ?")->execute([$serviceId]);

    // ── Декодируем JSON-поля ─────────────────────────
    // ── Фото: поддерживаем и JSON-массив и обычную ссылку ──
    $photoRaw = $row['photo'] ?? '';
    $photos   = [];
    if (!empty($photoRaw)) {
        $decoded = json_decode($photoRaw, true);
        if (is_array($decoded) && !empty($decoded)) {
            // Формат: ["/uploads/photo_xxx.jpg", ...]
            $photos = $decoded;
        } else {
            // Формат: просто строка "https://..." или "/uploads/..."
            $photos = [$photoRaw];
        }
    }
    if (empty($photos)) {
        $photos = ['https://via.placeholder.com/800x600?text=Poisq'];
    }

    $hoursRaw = json_decode($row['hours']        ?? '{}', true) ?: [];
    $langs    = json_decode($row['languages']    ?? '[]', true) ?: [];
    $svcList  = json_decode($row['service_list'] ?? '[]', true) ?: [];
    $social   = json_decode($row['social']       ?? '{}', true) ?: [];

    // ── Форматируем часы работы ──────────────────────
    // В БД хранится: {"mon":{"open":"09:00","close":"18:00"},...}
    // Если open и close пустые — день выходной
    $hours = [];
    $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    foreach ($dayKeys as $dk) {
        if (!empty($hoursRaw[$dk])) {
            $o = trim($hoursRaw[$dk]['open']  ?? '');
            $c = trim($hoursRaw[$dk]['close'] ?? '');
            $hours[$dk] = ($o && $c) ? "с $o до $c" : 'Закрыто';
        } else {
            $hours[$dk] = '—';
        }
    }

    // ── Определяем валюту по стране ─────────────────
    $currencyMap = [
        'fr'=>'€','de'=>'€','es'=>'€','it'=>'€','pt'=>'€','nl'=>'€','be'=>'€',
        'at'=>'€','gr'=>'€','fi'=>'€','ie'=>'€','lu'=>'€','mt'=>'€','cy'=>'€',
        'gb'=>'£','ch'=>'CHF','se'=>'kr','no'=>'kr','dk'=>'kr',
        'us'=>'$','ca'=>'$','au'=>'$','nz'=>'$','sg'=>'$','hk'=>'$',
        'ru'=>'₽','ua'=>'₴','by'=>'Br','kz'=>'₸',
        'pl'=>'zł','cz'=>'Kč','hu'=>'Ft','ro'=>'lei',
        'tr'=>'₺','il'=>'₪','ae'=>'AED','sa'=>'SAR','qa'=>'QAR',
        'jp'=>'¥','kr'=>'₩','cn'=>'¥','th'=>'฿','in'=>'₹',
        'br'=>'R$','mx'=>'$','ar'=>'$','cl'=>'$','co'=>'$',
        'za'=>'R',
    ];
    $currency = $currencyMap[$row['country_code'] ?? ''] ?? '€';

    // ── Собираем итоговый массив $service ────────────
    // Формат совпадает с тем что ожидает HTML ниже
    $service = [
        'id'            => (int)$row['id'],
        'name'          => $row['name'],
        'category'      => $row['category'],
        'subcategory'   => $row['subcategory'] ?? '',
        'description'   => $row['description'] ?? '',
        'photos'        => $photos,
        'phone'         => $row['phone']    ?? '',
        'whatsapp'      => $row['whatsapp'] ?? '',
        'email'         => $row['email']    ?? '',
        'website'       => $row['website']  ?? '',
        'address'       => $row['address']  ?? '',
        'city_name'     => $row['city_name_lat'] ?: ($row['city_name'] ?? ''),
        'country_code'  => $row['country_code'] ?? '',
        'verified'      => (bool)$row['verified'] && ($row['verified_until'] === null || $row['verified_until'] >= date('Y-m-d')),
        'verified_until' => $row['verified_until'] ?? null,
        'rating'        => (float)($row['rating'] ?? 0),
        'reviews_count' => (int)($row['reviews_count'] ?? 0),
        'hours'         => $hours,
        'services'      => array_map(function($s) {
                               // services хранится как [{"name":"...","price":"..."}]
                               return is_array($s) ? $s : ['name' => $s, 'price' => ''];
                           }, $svcList),
        'prices'        => [], // устаревшее поле, не используем
        'social'        => $social,
        'reviews'       => (function() use ($pdo, $serviceId) {
            try {
                $stmt = $pdo->prepare("
                    SELECT r.id, r.rating, r.text, r.created_at, r.photo,
                           u.name AS author_name, u.avatar AS author_avatar,
                           rop.text AS reply_text,
                           rop.created_at AS reply_created_at
                    FROM reviews r
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN review_owner_replies rop ON rop.review_id = r.id AND rop.status = 'approved'
                    WHERE r.service_id = ? AND r.status = 'approved'
                    ORDER BY r.created_at DESC
                    LIMIT 20
                ");
                $stmt->execute([$serviceId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { return []; }
        })(),
        'currency'      => $currency,
        'group_link'    => $row['group_link'] ?? '',
        'user_id'       => (int)($row['user_id'] ?? 0),
    ];

    // Проверяем — в избранном ли у текущего пользователя
    $isFavorite = false;
    if ($isLoggedIn) {
        $stmtFav = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND service_id = ?");
        $stmtFav->execute([$_SESSION['user_id'], $serviceId]);
        $isFavorite = (bool)$stmtFav->fetch();
    }

    $isOwner = $isLoggedIn && (int)$_SESSION['user_id'] === $service['user_id'];
    $userExistingReview = null;
    if ($isLoggedIn && !$isOwner) {
        try {
            $stmtRev = $pdo->prepare("
                SELECT id, rating, text, photo, status, created_at, edited_until
                FROM reviews WHERE user_id = ? AND service_id = ? LIMIT 1
            ");
            $stmtRev->execute([$_SESSION['user_id'], $serviceId]);
            $userExistingReview = $stmtRev->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {}
    }

} catch (PDOException $e) {
    error_log('Service page DB error: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

$pageTitle       = htmlspecialchars($service['name']) . ' — ' . htmlspecialchars($service['category']);
$pageDescription = htmlspecialchars(substr($service['description'] ?? '', 0, 160));
$canonicalUrl    = serviceUrl($service['id'], $service['name']);
$ogImage         = htmlspecialchars($service['photos'][0] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="canonical" href="<?php echo $canonicalUrl; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:image" content="<?php echo $ogImage; ?>">
    <meta property="og:url" content="<?php echo $canonicalUrl; ?>">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/png" href="/favicon.png">
    <script type="application/ld+json">
    <?php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type'    => 'LocalBusiness',
        'name'        => $service['name'],
        'description' => substr($service['description'], 0, 200),
        'url'         => 'https://poisq.com' . serviceUrl($service['id'], $service['name']),
        'telephone'   => $service['phone'],
        'address' => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $service['address'],
            'addressLocality' => $service['city_name'],
            'addressCountry'  => strtoupper($service['country_code'])
        ],
        'image' => isset($service['photos'][0]) ? 'https://poisq.com' . $service['photos'][0] : '',
        'aggregateRating' => $service['rating'] > 0 ? [
            '@type'       => 'AggregateRating',
            'ratingValue' => $service['rating'],
            'reviewCount' => max(1, $service['reviews_count'])
        ] : null,
    ];
    $jsonLd = array_filter($jsonLd);
    echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    ?>
    </script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        :root {
            --primary: #2E73D8; --primary-light: #5EA1F0; --primary-dark: #1A5AB8;
            --text: #1F2937; --text-secondary: #9CA3AF; --text-light: #6B7280;
            --bg: #FFFFFF; --bg-secondary: #F5F5F7; --border: #D1D5DB; --border-light: #E5E7EB;
            --success: #10B981; --warning: #F59E0B; --danger: #EF4444;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        }
        html { -webkit-overflow-scrolling: touch; overflow-y: auto; height: auto; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary); color: var(--text);
            line-height: 1.5; padding-bottom: 80px;
        }
        .app-container {
            max-width: 430px; margin: 0 auto; background: var(--bg);
            min-height: 100vh;
        }
        
        /* 🔧 ШАПКА */
        .service-header {
            position: sticky; top: 0; z-index: 100;
            background: var(--bg); border-bottom: 1px solid var(--border-light);
        }
        .header-nav {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; height: 56px;
        }
        .btn-back {
            width: 40px; height: 40px; border-radius: 12px; border: none;
            background: var(--bg-secondary); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; padding: 0;
        }
        .btn-back:active { transform: scale(0.95); background: var(--border); }
        .btn-back svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }
        .header-actions { display: flex; align-items: center; gap: 8px; }
        .btn-share, .btn-favorite {
            width: 40px; height: 40px; border-radius: 12px; border: none;
            background: var(--bg-secondary); color: var(--text);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; padding: 0;
        }
        .btn-share:active, .btn-favorite:active { background: var(--border); }
        .btn-favorite.active svg { fill: var(--danger); stroke: var(--danger); }
        .btn-share svg, .btn-favorite svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; }
        
        /* 🔧 СЛАЙДЕР */
        .service-slider { position: relative; width: 100%; height: 280px; background: var(--bg-secondary); overflow: hidden; }
        .slider-container { display: flex; height: 100%; transition: transform 0.3s ease-out; }
        .slider-slide { min-width: 100%; height: 100%; }
        .slider-slide img { width: 100%; height: 100%; object-fit: cover; }
        .slider-dots {
            position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 6px; background: rgba(0,0,0,0.4);
            padding: 6px 10px; border-radius: 16px;
        }
        .slider-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: rgba(255,255,255,0.5); transition: all 0.2s ease;
        }
        .slider-dot.active { background: white; width: 18px; border-radius: 3px; }
        .slider-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.9); border: none;
            cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;
        }
        .slider-arrow:active { background: white; }
        .slider-arrow.prev { left: 12px; }
        .slider-arrow.next { right: 12px; }
        .slider-arrow svg { width: 20px; height: 20px; stroke: var(--text); fill: none; stroke-width: 2; }
        
        /* 🔧 КОНТЕНТ */
        .service-content { padding: 16px; }
        .service-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
        .service-title { font-size: 20px; font-weight: 700; color: var(--text); line-height: 1.3; }
        .service-category { font-size: 14px; color: var(--text-secondary); margin-bottom: 8px; }
        .service-rating-row { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .service-rating { display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 8px; background: var(--bg-secondary); }
        .service-rating svg { width: 16px; height: 16px; fill: var(--warning); stroke: var(--warning); }
        .service-rating-value { font-size: 14px; font-weight: 700; color: var(--warning); }
        .service-rating-count { font-size: 13px; color: var(--text-secondary); }
        .verified-badge { display: inline-flex; flex-direction: column; align-items: flex-start; background: #D1FAE5; padding: 4px 10px; border-radius: 999px; }
        .verified-badge-row { display: flex; align-items: center; gap: 4px; color: #065F46; font-size: 12px; font-weight: 600; }
        .verified-badge-row svg { width: 14px; height: 14px; fill: #065F46; flex-shrink: 0; }
        .verified-badge-date { font-size: 11px; font-weight: 400; color: var(--text-secondary); padding-left: 18px; line-height: 1.2; }
        
        /* 🔧 КНОПКИ ДЕЙСТВИЙ (ВЕРХНИЕ) */
        .action-buttons { 
            display: flex; 
            gap: 8px; 
            margin-bottom: 20px; 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            scrollbar-width: none; 
            padding-bottom: 4px; 
        }
        .action-buttons::-webkit-scrollbar { display: none; }
        .btn-action {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 999px;
            border: none;
            background: var(--bg-secondary);
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-action:active { transform: scale(0.98); background: var(--border); }
        .btn-action.primary { background: var(--primary); color: white; }
        .btn-action.primary:active { background: var(--primary-dark); }
        .btn-action svg { width: 16px; height: 16px; flex-shrink: 0; }
        
        /* 🔧 СЕКЦИИ TELEGRAM STYLE */
        .settings-section {
            background: var(--bg);
            border-radius: 16px;
            margin: 0 0 16px;
            overflow: hidden;
        }
        .settings-section-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 16px 8px;
        }
        .settings-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
        }
        .settings-item + .settings-item {
            border-top: 1px solid var(--border-light);
        }
        .settings-item-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .settings-item-icon svg {
            width: 24px;
            height: 24px;
            stroke: var(--text-secondary);
            fill: none;
            stroke-width: 2;
        }
        .settings-item-icon.blue { background: #E8F0FE; }
        .settings-item-icon.blue svg { stroke: var(--primary); }
        .settings-item-icon.green { background: #D1FAE5; }
        .settings-item-icon.green svg { stroke: var(--success); }
        .settings-item-icon.orange { background: #FEF3C7; }
        .settings-item-icon.orange svg { stroke: var(--warning); }
        .settings-item-icon.purple { background: #F3E8FF; }
        .settings-item-icon.purple svg { stroke: #9333EA; }
        .settings-item-content {
            flex: 1;
            min-width: 0;
        }
        .settings-item-label {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        .settings-item-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        /* 🔧 ЧАСЫ РАБОТЫ */
        .hours-table { width: 100%; margin-top: 8px; }
        .hours-table tr { border-bottom: 1px solid var(--border-light); }
        .hours-table tr:last-child { border-bottom: none; }
        .hours-table td { padding: 10px 0; font-size: 14px; }
        .hours-table td:first-child { font-weight: 500; color: var(--text); }
        .hours-table td:last-child { text-align: right; color: var(--text-secondary); }
        .hours-table tr.closed td:last-child { color: var(--danger); }
        
        /* 🔧 УСЛУГИ И ЦЕНЫ */
        .service-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }
        .service-item:last-child { border-bottom: none; }
        .service-item-name { font-size: 14px; color: var(--text); font-weight: 500; }
        .service-item-price { font-size: 14px; color: var(--primary); font-weight: 600; }
        
        /* 🔧 ОТЗЫВЫ */
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .reviews-count { font-size: 13px; color: var(--text-secondary); }
        .btn-add-review {
            font-size: 13px;
            color: var(--primary);
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
        }
        .btn-add-review:active { opacity: 0.6; }
        .review-item {
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
        }
        .review-item:last-child { border-bottom: none; }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .review-author { font-weight: 600; color: var(--text); font-size: 14px; }
        .review-date { font-size: 12px; color: var(--text-secondary); }
        .review-rating { display: flex; gap: 2px; margin-bottom: 6px; }
        .review-rating svg { width: 14px; height: 14px; fill: var(--warning); stroke: var(--warning); }
        .review-text { font-size: 14px; color: var(--text); line-height: 1.5; }
        .btn-show-more {
            width: 100%;
            padding: 14px;
            background: var(--bg-secondary);
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            cursor: pointer;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-show-more:active { background: var(--border); }
        .btn-show-more svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
        .reviews-expanded { display: none; }
        .reviews-expanded.active { display: block; }

        /* 🔧 ОТЗЫВЫ (РАСШИРЕННЫЙ СТИЛЬ) */
        .review-item-top {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 8px;
        }
        .review-author-row {
            display: flex; align-items: center; gap: 10px;
        }
        .review-avatar {
            width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        }
        .review-avatar-ph {
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700;
        }
        .review-stars { display: flex; gap: 2px; flex-shrink: 0; }
        .rv-star { font-size: 16px; color: #D1D5DB; line-height: 1; }
        .rv-star.rv-on { color: var(--warning); }
        .review-photo-thumb {
            width: 60px; height: 60px; object-fit: cover; border-radius: 8px;
            cursor: pointer; margin-top: 8px; display: block;
        }
        .owner-reply {
            margin-top: 10px; padding: 10px 12px;
            background: var(--bg-secondary); border-radius: 10px;
            border-left: 3px solid var(--primary);
        }
        .owner-reply-label {
            font-size: 12px; font-weight: 700; color: var(--primary); margin-bottom: 4px;
        }
        .owner-reply-text { font-size: 13px; color: var(--text); line-height: 1.5; }
        .review-own-block {
            margin: 0 16px 16px; padding: 14px; border-radius: 12px;
            background: #F0F7FF; border: 1px solid #BFDBFE;
        }
        .review-own-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px; font-size: 14px; font-weight: 700; color: var(--text);
        }
        .review-status {
            font-size: 11px; font-weight: 700; padding: 3px 8px;
            border-radius: 999px; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .review-status.pending { background: #FEF3C7; color: #92400E; }
        .review-status.rejected { background: #FEE2E2; color: #991B1B; }
        /* Загрузка фото в модалке отзыва */
        .review-photo-upload-row {
            display: flex; align-items: center; gap: 12px; margin-top: 12px; flex-wrap: wrap;
        }
        .btn-photo-label {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 999px;
            background: var(--bg-secondary); border: 1.5px solid var(--border);
            font-size: 13px; font-weight: 600; color: var(--text-light);
            cursor: pointer; transition: all 0.15s;
        }
        .btn-photo-label:active { background: var(--border); }
        .btn-remove-photo {
            background: none; border: none; cursor: pointer;
            font-size: 20px; color: var(--danger); line-height: 1; padding: 0 4px;
        }
        /* Лайтбокс для фото отзывов */
        .photo-lightbox {
            position: fixed; inset: 0; z-index: 999;
            background: rgba(0,0,0,0.92);
            display: flex; align-items: center; justify-content: center;
            visibility: hidden; opacity: 0;
            transition: opacity 0.25s, visibility 0.25s;
        }
        .photo-lightbox.active { visibility: visible; opacity: 1; }
        .photo-lightbox img { max-width: 96vw; max-height: 90vh; object-fit: contain; border-radius: 8px; }
        .photo-lightbox-close {
            position: absolute; top: 16px; right: 16px;
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(255,255,255,0.15); border: none; cursor: pointer;
            color: white; font-size: 22px; display: flex; align-items: center; justify-content: center;
        }

        /* 🔧 СОЦИАЛЬНЫЕ СЕТИ */
        .social-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 12px;
        }
        .social-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 12px;
            background: var(--bg-secondary);
            text-decoration: none;
            color: var(--text);
            transition: all 0.15s ease;
        }
        .social-link:active {
            background: var(--border);
            transform: scale(0.98);
        }
        .social-link svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }
        .social-link.instagram svg { stroke: #E4405F; }
        .social-link.facebook svg { stroke: #1877F2; }
        .social-link.vk svg { stroke: #4C75A3; }
        .social-link.telegram svg { stroke: #0088CC; }
        .social-link span {
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* 🔧 КАРТА */
        .map-container {
            width: 100%;
            height: 200px;
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 12px;
        }
        .map-container iframe { width: 100%; height: 100%; border: none; }
        .btn-map {
            width: 100%;
            margin-top: 12px;
        }
        
        /* 🔧 НИЖНЯЯ ПАНЕЛЬ (СКРЫТА ПО УМОЛЧАНИЮ) */
        .bottom-bar {
            position: fixed;
            bottom: -80px;
            left: 0;
            right: 0;
            background: var(--bg);
            border-top: 1px solid var(--border-light);
            padding: 10px 16px;
            z-index: 100;
            display: flex;
            gap: 8px;
            max-width: 430px;
            margin: 0 auto;
            transition: bottom 0.3s ease;
        }
        .bottom-bar.visible {
            bottom: 0;
        }
        .bottom-bar .btn-action { flex: 1; justify-content: center; }
        
        @media (max-width: 380px) {
            .service-title { font-size: 18px; }
            .service-slider { height: 240px; }
            .social-links { grid-template-columns: 1fr; }
        }
        ::-webkit-scrollbar { display: none; }

        /* ===== МОДАЛКА ОТЗЫВА ===== */
        .review-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.55);
            z-index: 300;
            display: flex;
            align-items: flex-end; justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.25s, visibility 0.25s;
        }
        .review-modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        .review-modal {
            width: 100%; max-width: 480px;
            background: var(--bg); border-radius: 24px 24px 0 0;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            display: flex; flex-direction: column;
            max-height: 90vh;
            /* убрали overflow:hidden — он блокирует touch на iOS */
        }
        .review-modal-overlay.active .review-modal { transform: translateY(0); }
        .review-modal-handle {
            width: 36px; height: 4px; border-radius: 99px;
            background: var(--border); margin: 12px auto 0; flex-shrink: 0;
        }
        .review-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px 12px; border-bottom: 1px solid var(--border-light); flex-shrink: 0;
        }
        .review-modal-title { font-size: 16px; font-weight: 800; color: var(--text); }
        .review-modal-close {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--bg-secondary); border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .review-modal-close svg { width: 16px; height: 16px; stroke: var(--text-secondary); fill: none; stroke-width: 2; }
        .review-modal-body { flex: 1; overflow-y: auto; padding: 20px; }

        .stars-row { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; }
        .star-btn {
            background: none; border: none; cursor: pointer; padding: 6px;
            -webkit-tap-highlight-color: transparent;
        }
        .star-btn svg { width: 38px; height: 38px; display: block; }
        .star-btn polygon { fill: #D1D5DB; stroke: #D1D5DB; transition: fill 0.1s, stroke 0.1s; }
        .star-btn.lit polygon { fill: #F59E0B; stroke: #F59E0B; }

        .review-textarea {
            width: 100%; min-height: 100px;
            border: 1.5px solid var(--border); border-radius: 14px;
            padding: 12px 14px; font-family: inherit; font-size: 14px;
            color: var(--text); background: var(--bg-secondary);
            resize: none; outline: none; box-sizing: border-box; transition: border-color 0.2s;
        }
        .review-textarea:focus { border-color: var(--primary); background: white; }
        .review-hint { font-size: 12px; color: var(--text-light); margin-top: 6px; }

        .review-modal-footer {
            padding: 12px 20px calc(12px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid var(--border-light); flex-shrink: 0;
        }
        .btn-submit-review {
            width: 100%; padding: 14px; border-radius: 14px; border: none;
            background: var(--primary); color: white;
            font-family: inherit; font-size: 15px; font-weight: 800;
            cursor: pointer; transition: all 0.15s;
        }
        .btn-submit-review:active { opacity: 0.85; transform: scale(0.99); }

        /* Незалогиненный */
        .review-auth-block { text-align: center; padding: 8px 0 12px; }
        .review-auth-icon { font-size: 44px; margin-bottom: 14px; }
        .review-auth-title { font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 6px; }
        .review-auth-sub { font-size: 13px; color: var(--text-secondary); margin-bottom: 22px; line-height: 1.5; }
        .review-auth-btns { display: flex; flex-direction: column; gap: 10px; }
        .btn-auth-login {
            display: block; padding: 14px; border-radius: 14px; border: none;
            background: var(--primary); color: white;
            font-family: inherit; font-size: 15px; font-weight: 800;
            text-decoration: none; text-align: center; cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-auth-register {
            display: block; padding: 13px; border-radius: 14px;
            border: 1.5px solid var(--border); background: white;
            color: var(--text); font-family: inherit; font-size: 15px; font-weight: 700;
            text-decoration: none; text-align: center;
        }
        .btn-auth-login:active { opacity: 0.85; }
        .btn-auth-register:active { background: var(--bg-secondary); }

        /* Форма входа внутри модалки */
        .btn-back-choice {
            display: flex; align-items: center; gap: 6px;
            background: none; border: none; cursor: pointer;
            font-family: inherit; font-size: 14px; font-weight: 600;
            color: var(--primary); padding: 0; margin-bottom: 18px;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-back-choice svg { width: 18px; height: 18px; }
        .login-form-title { font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 16px; }
        .login-error {
            background: #FEF2F2; color: var(--danger);
            border: 1px solid #FECACA; border-radius: 10px;
            padding: 10px 14px; font-size: 13px; margin-bottom: 14px;
        }
        .login-field { margin-bottom: 14px; }
        .login-field label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .login-field input {
            width: 100%; padding: 12px 14px; border-radius: 12px;
            border: 1.5px solid var(--border); background: var(--bg-secondary);
            font-family: inherit; font-size: 15px; color: var(--text);
            outline: none; box-sizing: border-box; transition: border-color 0.2s;
            -webkit-appearance: none;
        }
        .login-field input:focus { border-color: var(--primary); background: white; }
        .login-forgot { display: block; text-align: right; margin-top: 6px; font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 500; }

        /* ── МОДАЛКА ИЗБРАННОГО ── */
        .fav-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.5);
            z-index: 500;
            display: flex; align-items: flex-end; justify-content: center;
            visibility: hidden; opacity: 0;
            transition: opacity 0.25s, visibility 0.25s;
        }
        .fav-modal-overlay.active { visibility: visible; opacity: 1; }
        .fav-modal {
            width: 100%; max-width: 480px;
            background: var(--bg); border-radius: 24px 24px 0 0;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            display: flex; flex-direction: column;
            max-height: 85vh;
        }
        .fav-modal-overlay.active .fav-modal { transform: translateY(0); }
        .fav-modal-handle {
            width: 36px; height: 4px; border-radius: 99px;
            background: var(--border); margin: 12px auto 0; flex-shrink: 0;
        }
        .fav-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px 12px;
            border-bottom: 1px solid var(--border-light); flex-shrink: 0;
        }
        .fav-modal-title { font-size: 16px; font-weight: 800; color: var(--text); }
        .fav-modal-close {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--bg-secondary); border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .fav-modal-close svg { width: 16px; height: 16px; stroke: var(--text-secondary); fill: none; stroke-width: 2; }
        .fav-modal-body { flex: 1; overflow-y: auto; padding: 20px; }
        .fav-modal-footer {
            padding: 12px 20px calc(12px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid var(--border-light); flex-shrink: 0;
        }
        .fav-modal-footer button {
            width: 100%; padding: 14px; border-radius: 14px; border: none;
            background: var(--primary); color: white;
            font-family: inherit; font-size: 15px; font-weight: 800;
            cursor: pointer; transition: all 0.15s;
        }
        .fav-modal-footer button:active { opacity: 0.85; transform: scale(0.99); }
        .fav-modal-footer button:disabled { opacity: 0.6; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="service-header">
            <div class="header-nav">
                <button class="btn-back" onclick="goBack()" aria-label="Назад">
                    <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                </button>
                <div class="header-actions">
                    <button class="btn-share" onclick="shareService()" aria-label="Поделиться">
                        <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>
                    </button>
                    <button class="btn-favorite <?php echo $isFavorite ? 'active' : ''; ?>" id="favoriteBtn" onclick="toggleFavorite()" aria-label="В избранное" data-service-id="<?php echo $serviceId; ?>" data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>">
                        <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="service-slider" id="serviceSlider">
            <div class="slider-container" id="sliderContainer">
                <?php foreach ($service['photos'] as $photo): ?>
                <div class="slider-slide">
                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($service['photos']) > 1): ?>
            <button class="slider-arrow prev" onclick="slidePrev()">
                <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </button>
            <button class="slider-arrow next" onclick="slideNext()">
                <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
            <div class="slider-dots" id="sliderDots">
                <?php foreach ($service['photos'] as $i => $photo): ?>
                <div class="slider-dot <?php echo $i === 0 ? 'active' : ''; ?>"></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <main class="service-content">
            <div class="service-title-row">
                <h1 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h1>
                <?php if ($service['verified']): ?>
                <?php
                $verifUntil = $service['verified_until'];
                $verifiedDate = '';
                if ($verifUntil !== null && $verifUntil >= date('Y-m-d')) {
                    $verifiedDate = date('m.Y', strtotime($verifUntil . ' -3 months'));
                }
                ?>
                <div style="display: inline-flex; align-items: center; gap: 7px; background: #EAF3DE; border-radius: 10px; padding: 6px 12px;">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M8 1L2 3.5V8c0 3 2.5 5.5 6 6.5 3.5-1 6-3.5 6-6.5V3.5L8 1z" fill="#639922"/>
                    <path d="M5.5 8l2 2 3-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span style="font-size: 13px; font-weight: 500; color: #3B6D11;">Проверено</span>
                    <?php if ($verifiedDate): ?>
                    <span style="font-size: 10px; color: #639922;">с <?php echo $verifiedDate; ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
            </div>
            
            <p class="service-category"><?php echo htmlspecialchars($service['category']); ?><?php echo $service['city_name'] ? ' • ' . htmlspecialchars($service['city_name']) : ''; ?></p>
            
            <div class="service-rating-row">
                <div class="service-rating" onclick="addReview()" style="cursor:pointer">
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span class="service-rating-value"><?php echo number_format($service['rating'], 1); ?></span>
                    <span class="service-rating-count"><?php echo $service['reviews_count']; ?> отзывов</span>
                </div>
                <?php if ($service['reviews_count'] == 0): ?>
                <button class="btn-add-review" onclick="addReview()">✍️ Будьте первым!</button>
                <?php endif; ?>
            </div>

            <!-- 🔧 ВЕРХНИЕ КНОПКИ (исчезают при скролле) -->
            <div class="action-buttons" id="topButtons">
                <?php
                  $isMessengerSvc = ($service['category'] === 'messengers');
                  $groupLinkSvc = trim($service['group_link'] ?? '');
                  $isTgSvc = $groupLinkSvc && (strpos($groupLinkSvc, 't.me') !== false || strpos($groupLinkSvc, 'telegram') !== false);
                ?>
                <?php if ($isMessengerSvc && $groupLinkSvc): ?>
                <a href="<?php echo htmlspecialchars($groupLinkSvc); ?>" target="_blank"
                   class="btn-action primary"
                   style="<?php echo $isTgSvc ? 'background:#2AABEE;' : 'background:#25D366;'; ?>">
                    <?php if ($isTgSvc): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                        <path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/>
                    </svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                    </svg>
                    <?php endif; ?>
                    Посмотреть группу
                </a>
                <?php else: ?>
                <?php if ($service['phone']): ?>
                <a href="tel:<?php echo htmlspecialchars($service['phone']); ?>" class="btn-action primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Позвонить
                </a>
                <?php endif; ?>
                <?php if ($service['whatsapp']): ?>
                <a href="https://wa.me/<?php echo str_replace('+', '', $service['whatsapp']); ?>" target="_blank" class="btn-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                    </svg>
                    WhatsApp
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- 🔧 О СЕРВИСЕ -->
            <?php if ($service['description']): ?>
            <div class="settings-section">
                <div class="settings-section-title">Информация</div>
                <div class="settings-item">
                    <div class="settings-item-icon blue">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">О сервисе</div>
                        <div class="settings-item-description"><?php echo nl2br(htmlspecialchars($service['description'])); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 🔧 ЧАСЫ РАБОТЫ — скрыто для мессенджеров -->
            <?php if (!$isMessengerSvc && $service['hours']): ?>
            <div class="settings-section">
                <div class="settings-section-title">Режим работы</div>
                <div class="settings-item">
                    <div class="settings-item-icon orange">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">Часы работы</div>
                        <table class="hours-table">
                            <?php
                            $days = ['mon' => 'Понедельник', 'tue' => 'Вторник', 'wed' => 'Среда', 'thu' => 'Четверг', 'fri' => 'Пятница', 'sat' => 'Суббота', 'sun' => 'Воскресенье'];
                            foreach ($days as $key => $name):
                                $isClosed = isset($service['hours'][$key]) && $service['hours'][$key] === 'Закрыто';
                            ?>
                            <tr class="<?php echo $isClosed ? 'closed' : ''; ?>">
                                <td><?php echo $name; ?></td>
                                <td><?php echo $isClosed ? 'Закрыто' : htmlspecialchars($service['hours'][$key] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 🔧 УСЛУГИ И ЦЕНЫ -->
            <?php if ($service['services'] || $service['prices']): ?>
            <div class="settings-section">
                <div class="settings-section-title">Прайс-лист</div>
                <?php if ($service['services']): ?>
                <div class="settings-item">
                    <div class="settings-item-icon green">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">Услуги</div>
                        <div class="settings-item-description">
                            <?php foreach ($service['services'] as $svc): ?>
                            <div class="service-item">
                                <span class="service-item-name"><?php echo htmlspecialchars($svc['name'] ?? ''); ?></span>
                                <?php if(!empty($svc['price'])): ?>
                                <span class="service-item-price"><?php echo htmlspecialchars($svc['price']); ?> <?php echo htmlspecialchars($service['currency']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($service['prices']): ?>
                <div class="settings-item">
                    <div class="settings-item-icon purple">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">Цены</div>
                        <div class="settings-item-description">
                            <?php foreach ($service['prices'] as $price): ?>
                            <?php list($name, $cost) = explode(':', $price . ':'); ?>
                            <div class="service-item">
                                <span class="service-item-name"><?php echo htmlspecialchars(trim($name)); ?></span>
                                <span class="service-item-price"><?php echo htmlspecialchars(trim($cost)); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 🔧 СОЦИАЛЬНЫЕ СЕТИ -->
            <?php 
            $hasSocial = !empty($service['social']['instagram']) || !empty($service['social']['facebook']) || 
                        !empty($service['social']['vk']) || !empty($service['social']['telegram']);
            ?>
            <?php if ($hasSocial): ?>
            <div class="settings-section">
                <div class="settings-section-title">Социальные сети</div>
                <div class="settings-item">
                    <div class="settings-item-icon blue">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">Мы в соцсетях</div>
                        <div class="social-links">
                            <?php if (!empty($service['social']['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($service['social']['instagram']); ?>" target="_blank" class="social-link instagram">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                                </svg>
                                <span>Instagram</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($service['social']['facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($service['social']['facebook']); ?>" target="_blank" class="social-link facebook">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                </svg>
                                <span>Facebook</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($service['social']['vk'])): ?>
                            <a href="<?php echo htmlspecialchars($service['social']['vk']); ?>" target="_blank" class="social-link vk">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <span>VK</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($service['social']['telegram'])): ?>
                            <a href="<?php echo htmlspecialchars($service['social']['telegram']); ?>" target="_blank" class="social-link telegram">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                <span>Telegram</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 🔧 ОТЗЫВЫ -->
            <?php
            $_revList   = $service['reviews'];
            $_revCount  = max((int)$service['reviews_count'], count($_revList));
            $_revRating = (float)$service['rating'];
            $_revTitle  = 'Отзывы';
            if ($_revCount > 0) {
                $_revTitle = 'Отзывы (' . $_revCount . ')';
                if ($_revRating > 0) $_revTitle .= ' · ★ ' . number_format($_revRating, 1);
            }
            ?>
            <div class="settings-section" id="reviewsSection">
                <div class="settings-section-title"><?= htmlspecialchars($_revTitle) ?></div>

                <?php if ($_revList): ?>
                <div style="padding: 0 16px;">
                    <?php foreach (array_slice($_revList, 0, 3) as $_rev): ?>
                    <div class="review-item">
                        <?php
                        $_rName    = $_rev['author_name'] ?? 'Пользователь';
                        $_rInitial = mb_strtoupper(mb_substr($_rName, 0, 1, 'UTF-8'), 'UTF-8');
                        ?>
                        <div class="review-item-top">
                            <div class="review-author-row">
                                <?php if (!empty($_rev['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars($_rev['author_avatar']) ?>" class="review-avatar" alt="">
                                <?php else: ?>
                                <div class="review-avatar review-avatar-ph"><?= htmlspecialchars($_rInitial) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="review-author"><?= htmlspecialchars($_rName) ?></div>
                                    <div class="review-date"><?= date('d.m.Y', strtotime($_rev['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="review-stars">
                                <?php for ($_si = 1; $_si <= 5; $_si++): ?>
                                <span class="rv-star<?= $_si <= (int)$_rev['rating'] ? ' rv-on' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-text"><?= nl2br(htmlspecialchars($_rev['text'])) ?></div>
                        <?php if (!empty($_rev['photo'])): ?>
                        <img src="<?= htmlspecialchars($_rev['photo']) ?>" class="review-photo-thumb"
                             onclick="openPhotoModal(this.src)" alt="Фото к отзыву">
                        <?php endif; ?>
                        <?php if (!empty($_rev['reply_text'])): ?>
                        <div class="owner-reply">
                            <div class="owner-reply-label">Ответ владельца</div>
                            <div class="owner-reply-text"><?= nl2br(htmlspecialchars($_rev['reply_text'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($_revList) > 3): ?>
                <button class="btn-show-more" onclick="toggleReviews()">
                    Показать все отзывы
                    <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div class="reviews-expanded" id="reviewsExpanded" style="padding: 0 16px;">
                    <?php foreach (array_slice($_revList, 3) as $_rev): ?>
                    <div class="review-item">
                        <?php
                        $_rName    = $_rev['author_name'] ?? 'Пользователь';
                        $_rInitial = mb_strtoupper(mb_substr($_rName, 0, 1, 'UTF-8'), 'UTF-8');
                        ?>
                        <div class="review-item-top">
                            <div class="review-author-row">
                                <?php if (!empty($_rev['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars($_rev['author_avatar']) ?>" class="review-avatar" alt="">
                                <?php else: ?>
                                <div class="review-avatar review-avatar-ph"><?= htmlspecialchars($_rInitial) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div class="review-author"><?= htmlspecialchars($_rName) ?></div>
                                    <div class="review-date"><?= date('d.m.Y', strtotime($_rev['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="review-stars">
                                <?php for ($_si = 1; $_si <= 5; $_si++): ?>
                                <span class="rv-star<?= $_si <= (int)$_rev['rating'] ? ' rv-on' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="review-text"><?= nl2br(htmlspecialchars($_rev['text'])) ?></div>
                        <?php if (!empty($_rev['photo'])): ?>
                        <img src="<?= htmlspecialchars($_rev['photo']) ?>" class="review-photo-thumb"
                             onclick="openPhotoModal(this.src)" alt="Фото к отзыву">
                        <?php endif; ?>
                        <?php if (!empty($_rev['reply_text'])): ?>
                        <div class="owner-reply">
                            <div class="owner-reply-label">Ответ владельца</div>
                            <div class="owner-reply-text"><?= nl2br(htmlspecialchars($_rev['reply_text'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="padding: 14px 16px; color: var(--text-secondary); font-size: 14px;">
                    Пока нет отзывов. Будьте первым!
                </div>
                <?php endif; ?>

                <!-- Свой отзыв пользователя (pending / rejected) -->
                <?php if (!empty($userExistingReview) && $userExistingReview['status'] !== 'approved'): ?>
                <?php $_canEdit = !empty($userExistingReview['edited_until'])
                               && strtotime($userExistingReview['edited_until']) > time(); ?>
                <div class="review-own-block">
                    <div class="review-own-header">
                        <span>Ваш отзыв</span>
                        <?php if ($userExistingReview['status'] === 'pending'): ?>
                        <span class="review-status pending">На модерации</span>
                        <?php else: ?>
                        <span class="review-status rejected">Отклонён</span>
                        <?php endif; ?>
                    </div>
                    <div class="review-stars" style="margin-bottom:6px;">
                        <?php for ($_si = 1; $_si <= 5; $_si++): ?>
                        <span class="rv-star<?= $_si <= (int)$userExistingReview['rating'] ? ' rv-on' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="review-text"><?= nl2br(htmlspecialchars($userExistingReview['text'])) ?></div>
                    <?php if ($_canEdit): ?>
                    <button class="btn-action" style="margin-top:10px;"
                        onclick="openEditReview(
                            <?= (int)$userExistingReview['id'] ?>,
                            <?= (int)$userExistingReview['rating'] ?>,
                            <?= json_encode($userExistingReview['text'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                            <?= json_encode($userExistingReview['photo'] ?? '', JSON_UNESCAPED_UNICODE) ?>
                        )">Редактировать</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Добавить отзыв / войти -->
                <?php if (!$isOwner): ?>
                    <?php if (!$isLoggedIn): ?>
                    <div style="padding: 12px 16px 16px;">
                        <button class="btn-action primary" style="width:100%;justify-content:center;"
                                onclick="addReview()">
                            Войдите, чтобы оставить отзыв
                        </button>
                    </div>
                    <?php elseif (empty($userExistingReview)): ?>
                    <div style="padding: 12px 16px 16px;">
                        <button class="btn-action primary" style="width:100%;justify-content:center;"
                                onclick="addReview()">✍️ Написать отзыв</button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- 🔧 АДРЕС И КАРТА -->
            <?php if ($service['address']): ?>
            <div class="settings-section">
                <div class="settings-section-title">Контакты</div>
                <div class="settings-item">
                    <div class="settings-item-icon green">
                        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="settings-item-content">
                        <div class="settings-item-label">Адрес</div>
                        <div class="settings-item-description"><?php echo nl2br(htmlspecialchars($service['address'])); ?></div>
                        <div class="map-container">
                            <iframe src="https://www.google.com/maps?q=<?php echo urlencode($service['address']); ?>&output=embed" loading="lazy"></iframe>
                        </div>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($service['address']); ?>" target="_blank" class="btn-action primary btn-map">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            Открыть на карте
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 🔧 НИЖНЯЯ ПАНЕЛЬ (ПОЯВЛЯЕТСЯ ПРИ СКРОЛЛЕ) -->
    <div class="bottom-bar" id="bottomBar">
        <?php if ($isMessengerSvc && $groupLinkSvc): ?>
        <a href="<?php echo htmlspecialchars($groupLinkSvc); ?>" target="_blank"
           class="btn-action primary"
           style="<?php echo $isTgSvc ? 'background:#2AABEE;' : 'background:#25D366;'; ?>">
            <?php if ($isTgSvc): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                <path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/>
            </svg>
            <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
            <?php endif; ?>
            Посмотреть группу
        </a>
        <?php else: ?>
        <?php if ($service['phone']): ?>
        <a href="tel:<?php echo htmlspecialchars($service['phone']); ?>" class="btn-action primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
            Позвонить
        </a>
        <?php endif; ?>
        <?php if ($service['whatsapp']): ?>
        <a href="https://wa.me/<?php echo str_replace('+', '', $service['whatsapp']); ?>" target="_blank" class="btn-action">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
            WhatsApp
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // 🔧 ПОКАЗ/СКРЫТИЕ ФУТЕРА ПРИ СКРОЛЛЕ
        const topButtons = document.getElementById('topButtons');
        const bottomBar = document.getElementById('bottomBar');
        
        function toggleFooter() {
            if (topButtons) {
                const rect = topButtons.getBoundingClientRect();
                // Если верхние кнопки ушли за верх экрана (negative top)
                if (rect.top < -50) {
                    bottomBar.classList.add('visible');
                } else {
                    bottomBar.classList.remove('visible');
                }
            }
        }
        
        window.addEventListener('scroll', toggleFooter);
        toggleFooter(); // Проверить при загрузке

        function goBack() {
            const referrer = document.referrer;
            if (referrer.includes('results.php')) {
                sessionStorage.setItem('resultsScroll', window.scrollY);
                window.location.href = referrer;
            } else {
                window.history.back();
            }
        }

        window.addEventListener('load', () => {
            const scrollPos = sessionStorage.getItem('resultsScroll');
            if (scrollPos) {
                setTimeout(() => {
                    window.scrollTo(0, parseInt(scrollPos));
                    sessionStorage.removeItem('resultsScroll');
                }, 100);
            }
        });

        let currentSlide = 0;
        const totalSlides = <?php echo count($service['photos']); ?>;

        function slidePrev() {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            updateSlider();
        }

        function slideNext() {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        }

        function updateSlider() {
            const container = document.getElementById('sliderContainer');
            const dots = document.querySelectorAll('.slider-dot');
            container.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === currentSlide);
            });
        }

        let touchStartX = 0;
        let touchEndX = 0;
        const slider = document.getElementById('serviceSlider');
        slider.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].screenX; });
        slider.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchStartX - touchEndX > 50) slideNext();
            if (touchEndX - touchStartX > 50) slidePrev();
        });

        function shareService() {
            if (navigator.share) {
                navigator.share({
                    title: <?php echo json_encode($service['name']); ?>,
                    text: <?php echo json_encode($service['description'] ?? ''); ?>,
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Ссылка скопирована!');
            }
        }

        async function toggleFavorite() {
            const btn = document.getElementById('favoriteBtn');
            const isLoggedIn = btn.dataset.loggedIn === '1';
            const serviceId = btn.dataset.serviceId;

            // Не авторизован — показываем модалку входа
            if (!isLoggedIn) {
                openFavModal();
                return;
            }

            // Оптимистично меняем UI сразу
            btn.classList.toggle('active');
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('service_id', serviceId);

                const res = await fetch('/api/favorites.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (!data.success) {
                    btn.classList.toggle('active');
                }

                if (data.action === 'added') {
                    showToast('❤️ Добавлено в избранное');
                } else if (data.action === 'removed') {
                    showToast('🤍 Удалено из избранного');
                }

            } catch (e) {
                btn.classList.toggle('active');
            } finally {
                btn.disabled = false;
            }
        }

        // ── МОДАЛКА ИЗБРАННОГО ──────────────────────────
        function openFavModal() {
            document.getElementById('favModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            showFavChoice();
        }

        function closeFavModal() {
            document.getElementById('favModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function showFavChoice() {
            document.getElementById('favChoice').style.display = 'block';
            document.getElementById('favLoginForm').style.display = 'none';
            document.getElementById('favModalFooter').style.display = 'none';
        }

        function showFavLoginForm() {
            document.getElementById('favChoice').style.display = 'none';
            document.getElementById('favLoginForm').style.display = 'block';
            document.getElementById('favModalFooter').style.display = 'block';
            document.getElementById('favLoginError').style.display = 'none';
            setTimeout(() => document.getElementById('favEmail').focus(), 100);
        }

        async function submitFavLogin() {
            const email    = document.getElementById('favEmail').value.trim();
            const password = document.getElementById('favPassword').value;
            const errBox   = document.getElementById('favLoginError');
            const btn      = document.getElementById('favLoginBtn');

            if (!email || !password) {
                errBox.textContent = 'Заполните email и пароль';
                errBox.style.display = 'block';
                return;
            }

            btn.textContent = 'Входим…';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('email', email);
                fd.append('password', password);

                const res  = await fetch('/login.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    // Обновляем статус кнопки — теперь залогинен
                    const favBtn = document.getElementById('favoriteBtn');
                    favBtn.dataset.loggedIn = '1';

                    // Закрываем модалку
                    closeFavModal();

                    // Сразу добавляем в избранное
                    const serviceId = favBtn.dataset.serviceId;
                    const fd2 = new FormData();
                    fd2.append('service_id', serviceId);

                    const res2 = await fetch('/api/favorites.php', {
                        method: 'POST',
                        body: fd2
                    });
                    const data2 = await res2.json();

                    if (data2.success) {
                        favBtn.classList.add('active');
                        showToast('❤️ Добавлено в избранное');
                    }

                } else {
                    errBox.textContent = data.error || 'Неверный email или пароль';
                    errBox.style.display = 'block';
                    btn.textContent = 'Войти и добавить в избранное';
                    btn.disabled = false;
                }
            } catch (e) {
                errBox.textContent = 'Ошибка соединения. Попробуйте ещё раз.';
                errBox.style.display = 'block';
                btn.textContent = 'Войти и добавить в избранное';
                btn.disabled = false;
            }
        }

        // Enter в полях формы избранного
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const favForm = document.getElementById('favLoginForm');
                if (favForm && favForm.style.display !== 'none') submitFavLogin();
            }
        });

        function showToast(message) {
            let toast = document.getElementById('toastMsg');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toastMsg';
                toast.style.cssText = `
                    position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
                    background: #1F2937; color: white; padding: 10px 20px;
                    border-radius: 999px; font-size: 14px; font-weight: 600;
                    z-index: 999; opacity: 0; transition: opacity 0.3s;
                    white-space: nowrap; pointer-events: none;
                `;
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.opacity = '1';
            clearTimeout(toast._timer);
            toast._timer = setTimeout(() => { toast.style.opacity = '0'; }, 2500);
        }

        function addReview() {
            document.getElementById('reviewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        let reviewEditId = null;

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.remove('active');
            document.body.style.overflow = '';
            reviewEditId = null;
            const title = document.querySelector('.review-modal-title');
            if (title) title.textContent = 'Оставить отзыв';
            const btn = document.getElementById('btnSubmitReview');
            if (btn) btn.textContent = 'Отправить отзыв';
            // Сбросить форму если была открыта
            const loginForm = document.getElementById('authLoginForm');
            if (loginForm) showChoice();
        }

        function openEditReview(id, rating, text, photo) {
            reviewEditId = id;
            // Выставляем звёзды
            selectedRating = rating;
            document.querySelectorAll('.star-btn').forEach((s, i) => {
                s.classList.toggle('lit', i < rating);
            });
            // Текст
            const ta = document.getElementById('reviewText');
            if (ta) ta.value = text;
            // Меняем заголовок и кнопку
            const title = document.querySelector('.review-modal-title');
            if (title) title.textContent = 'Редактировать отзыв';
            const btn = document.getElementById('btnSubmitReview');
            if (btn) btn.textContent = 'Сохранить изменения';
            // Показываем существующее фото если есть
            if (photo) {
                const preview = document.getElementById('reviewPhotoPreview');
                const img = document.getElementById('reviewPhotoPreviewImg');
                if (preview && img) {
                    img.src = photo;
                    preview.style.display = 'flex';
                    preview.style.alignItems = 'center';
                    preview.style.gap = '8px';
                }
            }
            addReview();
        }

        function previewReviewPhoto(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('reviewPhotoPreview');
                const img = document.getElementById('reviewPhotoPreviewImg');
                if (preview && img) {
                    img.src = e.target.result;
                    preview.style.display = 'flex';
                    preview.style.alignItems = 'center';
                    preview.style.gap = '8px';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }

        function removeReviewPhoto() {
            const input = document.getElementById('reviewPhotoInput');
            const preview = document.getElementById('reviewPhotoPreview');
            if (input) input.value = '';
            if (preview) preview.style.display = 'none';
        }

        function openPhotoModal(src) {
            let lb = document.getElementById('photoLightbox');
            if (!lb) {
                lb = document.createElement('div');
                lb.id = 'photoLightbox';
                lb.className = 'photo-lightbox';
                lb.innerHTML = '<button class="photo-lightbox-close" onclick="closePhotoModal()">×</button><img id="photoLightboxImg">';
                lb.addEventListener('click', function(e) { if (e.target === lb) closePhotoModal(); });
                document.body.appendChild(lb);
            }
            document.getElementById('photoLightboxImg').src = src;
            lb.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePhotoModal() {
            const lb = document.getElementById('photoLightbox');
            if (lb) { lb.classList.remove('active'); document.body.style.overflow = ''; }
        }

        // Переключение экранов внутри модалки (только для незалогиненных)
        function showLoginForm() {
            document.getElementById('authChoice').style.display = 'none';
            document.getElementById('authLoginForm').style.display = 'block';
            document.getElementById('reviewAuthFooter').style.display = 'block';
            // Фокус на email после небольшой задержки (iOS)
            setTimeout(() => document.getElementById('loginEmail').focus(), 100);
        }
        function showChoice() {
            document.getElementById('authChoice').style.display = 'block';
            document.getElementById('authLoginForm').style.display = 'none';
            document.getElementById('reviewAuthFooter').style.display = 'none';
            document.getElementById('loginError').style.display = 'none';
            document.getElementById('loginEmail').value = '';
            document.getElementById('loginPassword').value = '';
        }

        async function submitLogin() {
            const email    = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value;
            const errBox   = document.getElementById('loginError');
            const btn      = document.getElementById('btnLoginSubmit');

            errBox.style.display = 'none';

            if (!email || !password) {
                errBox.textContent = 'Заполните email и пароль';
                errBox.style.display = 'block';
                return;
            }

            btn.textContent = 'Входим…';
            btn.disabled = true;

            try {
                const fd = new FormData();
                fd.append('email', email);
                fd.append('password', password);

                const res  = await fetch('/login.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    // Успех — показываем форму отзыва
                    document.getElementById('reviewAuthBody').outerHTML = `
                        <div class="review-modal-body" id="reviewFormBody">
                            <div class="stars-row" id="starsRow">
                                ${[1,2,3,4,5].map(i => `
                                <button class="star-btn" data-star="${i}" aria-label="${i} звезд">
                                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </button>`).join('')}
                            </div>
                            <textarea class="review-textarea" id="reviewText"
                                placeholder="Расскажите о своём опыте…" maxlength="2000"></textarea>
                            <div class="review-hint">Минимум 20 символов</div>
                            <div class="review-photo-upload-row">
                                <label for="reviewPhotoInput" class="btn-photo-label">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    Добавить фото
                                </label>
                                <input type="file" id="reviewPhotoInput" accept="image/jpeg,image/png"
                                       style="display:none" onchange="previewReviewPhoto(this)">
                                <div id="reviewPhotoPreview" style="display:none;"></div>
                            </div>
                        </div>`;
                    document.getElementById('reviewAuthFooter').outerHTML = `
                        <div class="review-modal-footer">
                            <button class="btn-submit-review" id="btnSubmitReview" onclick="submitReview()">Отправить отзыв</button>
                        </div>`;
                    // Переинициализируем звёздочки
                    initStars();
                } else {
                    errBox.textContent = data.error || 'Ошибка входа';
                    errBox.style.display = 'block';
                    btn.textContent = 'Войти и оставить отзыв';
                    btn.disabled = false;
                }
            } catch (e) {
                errBox.textContent = 'Ошибка соединения. Попробуйте ещё раз.';
                errBox.style.display = 'block';
                btn.textContent = 'Войти и оставить отзыв';
                btn.disabled = false;
            }
        }

        // Enter в полях формы
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const loginForm = document.getElementById('authLoginForm');
                if (loginForm && loginForm.style.display !== 'none') submitLogin();
            }
        });

        // Звёздочки
        let selectedRating = 0;

        function initStars() {
            const stars = document.querySelectorAll('.star-btn');
            if (!stars.length) return;
            stars.forEach(btn => {
                btn.onclick = function() {
                    selectedRating = parseInt(this.dataset.star);
                    stars.forEach((s, i) => {
                        s.classList.toggle('lit', i < selectedRating);
                    });
                };
            });
        }

        async function submitReview() {
            if (selectedRating === 0) {
                alert('Пожалуйста, выберите оценку');
                return;
            }
            const text = document.getElementById('reviewText').value.trim();
            if (text.length < 20) {
                alert('Напишите отзыв (минимум 20 символов)');
                return;
            }

            const btn = document.getElementById('btnSubmitReview') || document.querySelector('.btn-submit-review');
            const origText = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Отправляем…'; }

            const fd = new FormData();
            fd.append('action', reviewEditId ? 'edit_review' : 'submit_review');
            fd.append('service_id', '<?= $serviceId ?>');
            fd.append('rating', selectedRating);
            fd.append('text', text);
            if (reviewEditId) fd.append('review_id', reviewEditId);

            const photoInput = document.getElementById('reviewPhotoInput');
            if (photoInput && photoInput.files && photoInput.files[0]) {
                fd.append('photo', photoInput.files[0]);
            }

            try {
                const res  = await fetch('/api/reviews.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    closeReviewModal();
                    showToast('Отзыв отправлен на модерацию. Обычно это занимает до 24 часов.');
                    setTimeout(() => location.reload(), 2200);
                } else {
                    alert(data.error || 'Произошла ошибка. Попробуйте ещё раз.');
                    if (btn) { btn.disabled = false; btn.textContent = origText; }
                }
            } catch (e) {
                alert('Ошибка соединения. Попробуйте ещё раз.');
                if (btn) { btn.disabled = false; btn.textContent = origText; }
            }
        }

        function toggleReviews() {
            const expanded = document.getElementById('reviewsExpanded');
            const btn = document.querySelector('.btn-show-more');
            expanded.classList.toggle('active');
            if (expanded.classList.contains('active')) {
                btn.innerHTML = 'Свернуть отзывы <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2"><path d="M18 15l-6-6-6 6"/></svg>';
            } else {
                btn.innerHTML = 'Показать все отзывы <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2"><path d="M6 9l6 6 6-6"/></svg>';
            }
        }
    </script>
<!-- МОДАЛКА ОТЗЫВА -->
<div class="review-modal-overlay" id="reviewModal" onclick="if(event.target===this)closeReviewModal()">
    <div class="review-modal">
        <div class="review-modal-handle"></div>
        <div class="review-modal-header">
            <div class="review-modal-title">Оставить отзыв</div>
            <button class="review-modal-close" onclick="closeReviewModal()">
                <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <?php if ($isLoggedIn): ?>
        <!-- Залогинен — форма сразу -->
        <div class="review-modal-body" id="reviewFormBody">
            <div class="stars-row" id="starsRow">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <button class="star-btn" data-star="<?php echo $i; ?>" aria-label="<?php echo $i; ?> звезд">
                    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </button>
                <?php endfor; ?>
            </div>
            <textarea class="review-textarea" id="reviewText"
                placeholder="Расскажите о своём опыте — это поможет другим людям сделать выбор…"
                maxlength="2000"></textarea>
            <div class="review-hint">Минимум 20 символов</div>
            <div class="review-photo-upload-row">
                <label for="reviewPhotoInput" class="btn-photo-label">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    Добавить фото
                </label>
                <input type="file" id="reviewPhotoInput" accept="image/jpeg,image/png"
                       style="display:none" onchange="previewReviewPhoto(this)">
                <div id="reviewPhotoPreview" style="display:none; align-items:center; gap:8px;">
                    <img id="reviewPhotoPreviewImg"
                         style="width:60px;height:60px;object-fit:cover;border-radius:8px;">
                    <button type="button" onclick="removeReviewPhoto()" class="btn-remove-photo">×</button>
                </div>
            </div>
        </div>
        <div class="review-modal-footer">
            <button class="btn-submit-review" id="btnSubmitReview" onclick="submitReview()">Отправить отзыв</button>
        </div>

        <?php else: ?>
        <!-- Не залогинен — форма входа прямо здесь -->
        <div class="review-modal-body" id="reviewAuthBody">

          <!-- Экран выбора: войти или зарегистрироваться -->
          <div id="authChoice">
            <div class="review-auth-block">
              <div class="review-auth-icon">✍️</div>
              <div class="review-auth-title">Оставьте отзыв</div>
              <div class="review-auth-sub">Войдите в аккаунт — это займёт 10 секунд</div>
              <div class="review-auth-btns">
                <button class="btn-auth-login" onclick="showLoginForm()">Войти</button>
                <a href="register.php?redirect=service.php%3Fid%3D<?php echo $serviceId; ?>" class="btn-auth-register">Зарегистрироваться</a>
              </div>
            </div>
          </div>

          <!-- Форма входа (скрыта по умолчанию) -->
          <div id="authLoginForm" style="display:none">
            <button class="btn-back-choice" onclick="showChoice()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              Назад
            </button>
            <div class="login-form-title">Вход в аккаунт</div>
            <div id="loginError" class="login-error" style="display:none"></div>
            <div class="login-field">
              <label>Email</label>
              <input type="email" id="loginEmail" placeholder="example@mail.com" autocomplete="email">
            </div>
            <div class="login-field">
              <label>Пароль</label>
              <input type="password" id="loginPassword" placeholder="Введите пароль" autocomplete="current-password">
              <a href="forgot-password.php" class="login-forgot">Забыли пароль?</a>
            </div>
          </div>

        </div>

        <!-- Футер меняется в зависимости от экрана -->
        <div class="review-modal-footer" id="reviewAuthFooter" style="display:none">
          <button class="btn-submit-review" id="btnLoginSubmit" onclick="submitLogin()">Войти и оставить отзыв</button>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>initStars();</script>

<!-- МОДАЛКА ВХОДА ДЛЯ ИЗБРАННОГО -->
<div class="fav-modal-overlay" id="favModal" onclick="if(event.target===this)closeFavModal()">
    <div class="fav-modal">
        <div class="fav-modal-handle"></div>
        <div class="fav-modal-header">
            <div class="fav-modal-title">Добавить в избранное</div>
            <button class="fav-modal-close" onclick="closeFavModal()">
                <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="fav-modal-body" id="favModalBody">

            <!-- Экран выбора -->
            <div id="favChoice">
                <div class="review-auth-block">
                    <div class="review-auth-icon">❤️</div>
                    <div class="review-auth-title">Войдите, чтобы сохранить</div>
                    <div class="review-auth-sub">Добавляйте понравившиеся сервисы в избранное и возвращайтесь к ним в любой момент</div>
                    <div class="review-auth-btns">
                        <button class="btn-auth-login" onclick="showFavLoginForm()">Войти</button>
                        <a href="register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-auth-register">Зарегистрироваться</a>
                    </div>
                </div>
            </div>

            <!-- Форма входа (скрыта по умолчанию) -->
            <div id="favLoginForm" style="display:none">
                <button class="btn-back-choice" onclick="showFavChoice()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Назад
                </button>
                <div class="login-form-title">Вход в аккаунт</div>
                <div id="favLoginError" class="login-error" style="display:none"></div>
                <div class="login-field">
                    <label>Email</label>
                    <input type="email" id="favEmail" placeholder="example@mail.com" autocomplete="email">
                </div>
                <div class="login-field">
                    <label>Пароль</label>
                    <input type="password" id="favPassword" placeholder="Введите пароль" autocomplete="current-password">
                    <a href="forgot-password.php" class="login-forgot">Забыли пароль?</a>
                </div>
            </div>

        </div>

        <div class="fav-modal-footer" id="favModalFooter" style="display:none">
            <button id="favLoginBtn" onclick="submitFavLogin()">Войти и добавить в избранное</button>
        </div>
    </div>
</div>

</body>
</html>