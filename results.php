<?php
// results.php — Результаты поиска Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

// ── 301 редирект со старых ссылок на ЧПУ ────────────────────
// Срабатывает только когда открывают /results.php?... напрямую
// (не когда .htaccess внутренне перенаправляет на этот файл)
if (isset($_SERVER['REDIRECT_URL']) === false && 
    isset($_GET['country']) && 
    !isset($_GET['city_slug']) &&
    strpos($_SERVER['REQUEST_URI'], 'results.php') !== false) {

    require_once __DIR__ . '/config/database.php';

    $rc  = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? 'fr'));
    $q   = trim($_GET['q'] ?? '');
    $cid = intval($_GET['city_id'] ?? 0);
    $cat = preg_replace('/[^a-z]/', '', strtolower($_GET['category'] ?? ''));
    $pg  = intval($_GET['page'] ?? 1);

    $citySlugRedir = '';
    if ($cid > 0) {
        try {
            $pdo  = getDbConnection();
            $stmt = $pdo->prepare("SELECT name_lat FROM cities WHERE id = ? LIMIT 1");
            $stmt->execute([$cid]);
            $row  = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['name_lat'])) {
                $citySlugRedir = strtolower(trim($row['name_lat']));
            }
        } catch (Exception $e) {}
    }

    $path = '/' . $rc . '/';
    if ($citySlugRedir) $path .= $citySlugRedir . '/';
    if ($q) $path .= urlencode($q);

    $extra = [];
    if ($cat)     $extra[] = 'category=' . urlencode($cat);
    if ($pg > 1)  $extra[] = 'page=' . $pg;
    if (isset($_GET['rating']) && floatval($_GET['rating']) > 0) $extra[] = 'rating=' . floatval($_GET['rating']);
    if (isset($_GET['verified'])) $extra[] = 'verified=1';
    if (!empty($_GET['languages'])) $extra[] = 'languages=' . urlencode($_GET['languages']);

    $url = 'https://poisq.com' . $path;
    if (!empty($extra)) $url .= '?' . implode('&', $extra);

    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $url);
    exit;
}

// ── Декодируем параметры из ЧПУ ─────────────────────────────
// .htaccess передаёт: ?country=fr&city_slug=paris&q=врач
// Старый формат тоже работает: ?country=fr&city_id=1&q=врач
$searchQuery    = trim(urldecode($_GET['q'] ?? ''));
$countryCode    = preg_replace('/[^a-z]/', '', strtolower($_GET['country'] ?? 'fr'));
$categoryFilter = preg_replace('/[^a-z]/', '', strtolower($_GET['category'] ?? ''));
$citySlug       = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['city_slug'] ?? ''));
$cityFilter     = intval($_GET['city_id'] ?? 0);
$ratingFilter   = floatval($_GET['rating'] ?? 0);
$verifiedFilter = isset($_GET['verified']) ? 1 : 0;
$languagesFilter = array_values(array_filter(
    array_map('trim', explode(',', $_GET['languages'] ?? '')),
    fn($l) => preg_match('/^[a-z]{2}$/', $l)
));
$page           = max(1, intval($_GET['page'] ?? 1));
$perPage        = 10;

$isLoggedIn  = isset($_SESSION['user_id']);
$userName    = $isLoggedIn ? $_SESSION['user_name']   : '';
$userAvatar  = $isLoggedIn ? $_SESSION['user_avatar'] : '';
$userInitial = $isLoggedIn ? strtoupper(substr($userName, 0, 1)) : '';

// Проверка слотов
$slotsLeft = 3;
if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDbConnection();
        $st = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ? AND status = 'approved'");
        $st->execute([$_SESSION['user_id']]);
        $slotsLeft = max(0, 3 - (int)$st->fetchColumn());
    } catch (Exception $e) { $slotsLeft = 3; }
}

// Страна юзера (из сессии или из URL)
$userCountry = $_SESSION['user_country'] ?? $countryCode;

require_once 'config/database.php';
require_once 'config/helpers.php';

$services       = [];
$servicesExtra  = [];
$servicesGlobal = [];
$totalCount     = 0;
$totalPages     = 1;
$detectedCity   = null;
$detectedCountry = null;
require_once 'config/meilisearch.php';

$pdo = getDbConnection();

// Резолвим city_slug в city_id
if (!empty($citySlug) && $cityFilter === 0) {
    try {
        $s = $pdo->prepare("SELECT id FROM cities WHERE LOWER(name_lat)=? AND country_code=? LIMIT 1");
        $s->execute([$citySlug, $countryCode]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r) $cityFilter = (int)$r['id'];
    } catch (Exception $e) {}
}

// Убираем "русский/русскоязычный" и похожие — они мешают поиску (все сервисы и так русскоязычные)
$russianStopWords = [
    'русскоязычный','русскоязычная','русскоязычное','русскоязычные','русскоязычных',
    'русскоговорящий','русскоговорящая','русскоговорящие','русскоговорящих',
    'русскоязычному','русскоязычной',
    'русский','русская','русское','русские','русских','русского','русскому',
    'на русском','на русском языке',
];
$cleanQuery = mb_strtolower(trim($searchQuery), 'UTF-8');
foreach ($russianStopWords as $sw) {
    $cleanQuery = preg_replace('/'.preg_quote($sw,'/').'/' , '', $cleanQuery);
}
$cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));
// Если после очистки запрос пустой — используем оригинал
if (empty($cleanQuery)) $cleanQuery = $searchQuery;
// Парсим город из текста запроса
if (!empty($searchQuery) && $cityFilter === 0) {
    $qwords = array_filter(explode(' ', mb_strtolower($searchQuery)), fn($w) => mb_strlen($w) >= 3);
    foreach ($qwords as $qw) {
        try {
            $cs = $pdo->prepare("SELECT id,name,name_lat,country_code FROM cities WHERE LOWER(name) LIKE ? OR LOWER(name_lat) LIKE ? LIMIT 1");
            $cs->execute(['%'.$qw.'%','%'.$qw.'%']);
            $fc = $cs->fetch(PDO::FETCH_ASSOC);
            if ($fc) {
                $detectedCity = $fc;
                $cityFilter   = (int)$fc['id'];
                $countryCode  = $fc['country_code'];
                $cleanQuery   = trim(preg_replace('/'.preg_quote($fc['name'],'/').'/iu','', $cleanQuery));
                $cleanQuery   = trim(preg_replace('/'.preg_quote($fc['name_lat'],'/').'/iu','', $cleanQuery));
                $cleanQuery   = trim(preg_replace('/\s+/',' ', $cleanQuery));
                break;
            }
        } catch (Exception $e) {}
    }
}

// Парсим страну из текста запроса (если город не найден или страна не определена из URL)
if (!empty($searchQuery)) {
    try {
        $countries_list = $pdo->query("SELECT code, name_ru FROM countries WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        $qLower = mb_strtolower($searchQuery, 'UTF-8');
        foreach ($countries_list as $cnt) {
            $cname = mb_strtolower($cnt['name_ru'], 'UTF-8');
            if (mb_strpos($qLower, $cname) !== false) {
                // Нашли страну в запросе
                $countryCode = $cnt['code'];
                // Убираем название страны из cleanQuery
                $cleanQuery = trim(preg_replace('/'.preg_quote($cname,'/').'/'.'i', '', $cleanQuery));
                $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));
                break;
            }
        }
    } catch (Exception $e) {}
}
// Распознаём мессенджер из запроса → фильтр по subcategory
$messengerFilter = '';
$messengerKeywords = [
    'WhatsApp группа' => ['ватсап','вотсап','whatsapp','ватсапп'],
    'Telegram группа' => ['телеграм','telegram','телеграмм','тг'],
];
foreach ($messengerKeywords as $subcatValue => $keywords) {
    foreach ($keywords as $kw) {
        if (mb_strpos(mb_strtolower($cleanQuery, 'UTF-8'), $kw) !== false ||
            mb_strpos(mb_strtolower($searchQuery, 'UTF-8'), $kw) !== false) {
            $messengerFilter = $subcatValue;
            $cleanQuery = trim(preg_replace('/'.preg_quote($kw,'/').'/'.'i', '', $cleanQuery));
            $cleanQuery = trim(preg_replace('/\s+/', ' ', $cleanQuery));
            break 2;
        }
    }
}
// Базовый фильтр Meilisearch
$mf_parts = [];
if ($verifiedFilter)             $mf_parts[] = "verified = 1";
if (!empty($categoryFilter))     $mf_parts[] = "category = '$categoryFilter'";
if ($ratingFilter > 0)           $mf_parts[] = "rating >= $ratingFilter";
if (!empty($languagesFilter)) {
    $langConds = array_map(fn($l) => "languages = '$l'", $languagesFilter);
    $mf_parts[] = '(' . implode(' OR ', $langConds) . ')';
}
if (!empty($messengerFilter)) $mf_parts[] = "subcategory = '$messengerFilter'";
$mf = !empty($mf_parts) ? implode(' AND ', $mf_parts) : '';

$userCityId = (int)($_SESSION['user_city_id'] ?? 0);
$offset     = ($page - 1) * $perPage;
$meiliOk    = false;
$pinId      = isset($_GET['pin']) ? (int)$_GET['pin'] : 0;
$meiliIds   = [];
$meiliIds2  = [];
$meiliIds3  = [];

try {
    if ($cityFilter > 0) {
        $r = meiliSearch($cleanQuery, [
            'filter' => ($mf ? "$mf AND " : '') . "city_id = $cityFilter",
            'limit'  => $perPage, 'offset' => $offset,
            'sort'   => ['verified:desc','rating:desc','views:desc'],
        ]);
        if (isset($r['hits'])) {
            $meiliIds   = array_column($r['hits'], 'id');
            $totalCount = $r['estimatedTotalHits'] ?? count($meiliIds);
            $meiliOk    = true;
        }
    } else {
        $cityHits = [];
        if ($userCityId > 0 && empty($cleanQuery)) {
            $r1 = meiliSearch($cleanQuery, [
                'filter' => ($mf ? "$mf AND " : '') . "city_id = $userCityId AND country_code = '$countryCode'",
                'limit'  => 5, 'sort' => ['verified:desc','rating:desc','views:desc'],
            ]);
            $cityHits = array_column($r1['hits'] ?? [], 'id');
        }
        $cityEx = ($userCityId > 0 && empty($cleanQuery)) ? " AND city_id != $userCityId" : "";
        $r2opts = [
            'filter' => ($mf ? "$mf AND " : '') . "country_code = '$countryCode'$cityEx",
            'limit'  => $perPage, 'offset' => $offset,
        ];
        if (empty($cleanQuery)) {
            $r2opts['sort'] = ['verified:desc','rating:desc','views:desc'];
        }
        $r2 = meiliSearch($cleanQuery, $r2opts);
        if (isset($r2['hits'])) {
            $countryHits = array_column($r2['hits'], 'id');
            $meiliIds    = array_unique(array_merge($cityHits, $countryHits));
            $totalCount  = ($r2['estimatedTotalHits'] ?? 0) + count($cityHits);
            $meiliOk     = true;
        }
    }
    // Блоки "Похожее" — работают всегда когда результатов < 5
    if ($meiliOk && count($meiliIds) < 5) {
        // Сначала ищем в стране пользователя
        $r3user = meiliSearch($cleanQuery, [
            'filter' => ($mf ? "$mf AND " : '') . "country_code = '$userCountry' AND country_code != '$countryCode'",
            'limit'  => 5, 'sort' => ['verified:desc','rating:desc','views:desc'],
        ]);
        $meiliIds2 = array_column($r3user['hits'] ?? [], 'id');
    }
    // Ищем в других странах для блока "Похожее в других странах"
    if ($meiliOk && (!empty($cleanQuery) || !empty($messengerFilter) || $cityFilter > 0)) {
        $r4 = meiliSearch($cleanQuery, [
            'filter' => ($mf ? "$mf AND " : '') . "country_code != '$countryCode' AND country_code != '$userCountry'",
            'limit'  => 5, 'sort' => ['verified:desc','rating:desc','views:desc'],
        ]);
        $meiliIds3 = array_column($r4['hits'] ?? [], 'id');
    }
    // Если в своей стране 0 результатов — основной список пустой, похожее в своём блоке
    if ($meiliOk && count($meiliIds) === 0) {
        $totalCount = 0;
        // Глобальный поиск без фильтра по стране — если есть текстовый запрос
        // Показываем как основные результаты (юзер ввёл точное название)
        if (!empty($cleanQuery)) {
            $rGlobal = meiliSearch($cleanQuery, [
                'limit' => $perPage,
                'sort'  => ['verified:desc','rating:desc','views:desc'],
            ]);
            $globalHits = array_column($rGlobal['hits'] ?? [], 'id');
            if (!empty($globalHits)) {
                $meiliIds   = $globalHits;
                $totalCount = $rGlobal['estimatedTotalHits'] ?? count($globalHits);
                $meiliIds2  = [];
                $meiliIds3  = [];
                $isGlobalSearch = true;
            }
        }
    }
} catch (Exception $e) {
    error_log('Meilisearch error: ' . $e->getMessage());
}

// Получаем полные данные из MySQL по ID
function fetchFullByIds(PDO $pdo, array $ids): array {
    if (empty($ids)) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT s.id, s.name, s.category, s.subcategory,
               s.photo, s.phone, s.whatsapp, s.email, s.website,
               s.rating, s.reviews_count, s.views,
               s.description, s.address, s.languages,
               s.services AS service_list, s.social,
               s.verified, s.verified_until, s.hours, s.created_at, s.group_link,
               c.name AS city_name, c.name_lat AS city_name_lat
        FROM services s LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.id IN ($ph) ORDER BY FIELD(s.id, $ph)
    ");
    $st->execute(array_merge($ids, $ids));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

if ($meiliOk) {
    $services       = fetchFullByIds($pdo, $meiliIds);
    $servicesExtra  = fetchFullByIds($pdo, $meiliIds2);
    $servicesGlobal = fetchFullByIds($pdo, $meiliIds3);
} else {
    // Fallback: MySQL FULLTEXT + LIKE
    function buildSearchCondition(string $text, array &$params, string $alias = 's'): string {
        $words = array_filter(preg_split('/\s+/', trim($text)), fn($w) => mb_strlen($w) >= 2);
        if (empty($words)) {
            $params[] = "%$text%"; $params[] = "%$text%";
            $params[] = "%$text%"; $params[] = "%$text%";
            return "($alias.name LIKE ? OR $alias.description LIKE ? OR $alias.category LIKE ? OR $alias.subcategory LIKE ?)";
        }
        $long  = array_filter($words, fn($w) => mb_strlen($w) >= 4);
        $short = array_filter($words, fn($w) => mb_strlen($w) < 4);
        $parts = [];
        if (!empty($long)) {
            $ftq = implode(' ', array_map(fn($w) => "+$w*", $long));
            $params[] = $ftq; $params[] = $ftq;
            $parts[] = "(MATCH($alias.name,$alias.description,$alias.category,$alias.subcategory) AGAINST(? IN BOOLEAN MODE) OR MATCH($alias.name,$alias.description,$alias.category,$alias.subcategory) AGAINST(? IN NATURAL LANGUAGE MODE))";
        }
        foreach ($short as $sw) {
            $params[] = "%$sw%"; $params[] = "%$sw%";
            $params[] = "%$sw%"; $params[] = "%$sw%";
            $parts[] = "($alias.name LIKE ? OR $alias.description LIKE ? OR $alias.category LIKE ? OR $alias.subcategory LIKE ?)";
        }
        return implode(' AND ', $parts);
    }
    $where  = ["s.status='approved'","s.is_visible=1","s.country_code=?"];
    $params = [$countryCode];
    if ($cityFilter > 0)         { $where[] = "s.city_id=?"; $params[] = $cityFilter; }
    if (!empty($cleanQuery))     { $where[] = buildSearchCondition($cleanQuery, $params); }
    if (!empty($categoryFilter)) { $where[] = "s.category=?"; $params[] = $categoryFilter; }
    if ($ratingFilter > 0)       { $where[] = "s.rating>=?"; $params[] = $ratingFilter; }
    if ($verifiedFilter)           $where[] = "s.verified=1";
    if (!empty($languagesFilter)) {
        $langConds = array_map(fn($l) => "JSON_CONTAINS(s.languages, ?)", $languagesFilter);
        $where[] = '(' . implode(' OR ', $langConds) . ')';
        foreach ($languagesFilter as $l) $params[] = json_encode($l);
    }
    $wsql = implode(' AND ', $where);
    try {
        $cs = $pdo->prepare("SELECT COUNT(*) FROM services s WHERE $wsql");
        $cs->execute($params);
        $totalCount = (int)$cs->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $st = $pdo->prepare("
            SELECT s.id, s.name, s.category, s.subcategory,
                   s.photo, s.phone, s.whatsapp, s.email, s.website,
                   s.rating, s.reviews_count, s.views,
                   s.description, s.address, s.languages,
                   s.services AS service_list, s.social,
                   s.verified, s.verified_until, s.hours, s.created_at, s.group_link,
                   c.name AS city_name, c.name_lat AS city_name_lat
            FROM services s LEFT JOIN cities c ON s.city_id = c.id
            WHERE $wsql
            ORDER BY s.verified DESC, s.rating DESC, s.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $st->execute($params);
        $services = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Results fallback error: ' . $e->getMessage());
    }
}

// Если передан pin — загружаем этот сервис и ставим первым
$pinnedService = null;
if ($pinId > 0) {
    try {
        $ps = $pdo->prepare("SELECT s.id, s.name, s.category, s.subcategory,
               s.photo, s.phone, s.whatsapp, s.email, s.website,
               s.rating, s.reviews_count, s.views,
               s.description, s.address, s.languages,
               s.services AS service_list, s.social,
               s.verified, s.verified_until, s.hours, s.created_at, s.group_link,
               c.name AS city_name, c.name_lat AS city_name_lat
               FROM services s LEFT JOIN cities c ON s.city_id = c.id
               WHERE s.id = ? AND s.status = 'approved' AND s.is_visible = 1 LIMIT 1");
        $ps->execute([$pinId]);
        $pinnedService = $ps->fetch(PDO::FETCH_ASSOC);
        if ($pinnedService) {
            // Убираем этот сервис из основного списка если он там есть
            $services = array_filter($services ?? [], fn($sv) => $sv['id'] != $pinId);
            $services = array_values($services);
            // Ставим первым
            array_unshift($services, $pinnedService);
        }
    } catch (PDOException $e) {
        error_log('Pin service error: ' . $e->getMessage());
    }
}
$totalPages = max(1, ceil($totalCount / $perPage));

// Логируем поисковый запрос
if (!empty($cleanQuery) || !empty($categoryFilter)) {
    try {
        $logIp = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "";
        $logIp = trim(explode(",", $logIp)[0]);
        $logStatus = $totalCount > 0 ? "found" : "not_found";
        $logQuery = !empty($cleanQuery) ? $cleanQuery : $categoryFilter;
        $pdo->prepare("INSERT INTO search_logs (query, country_code, city_id, results_count, status, ip) VALUES (?,?,?,?,?,?)")
            ->execute([$logQuery, $countryCode, $cityFilter ?: null, $totalCount, $logStatus, $logIp]);
    } catch (Exception $e) { error_log("search_log error: " . $e->getMessage()); }
}

// Логируем просмотр страницы
try {
    $pvIp = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "";
    $pvIp = trim(explode(",", $pvIp)[0]);
    $pdo->prepare("INSERT INTO page_views (page, ip, country_code) VALUES (?,?,?)")
        ->execute(["results", $pvIp, $countryCode]);
} catch (Exception $e) { error_log("page_view error: " . $e->getMessage()); }
$page       = min($page, $totalPages);

// Декодируем JSON поля для шаблона
foreach ($services as &$svc) {
    $svc['photo_arr']        = json_decode($svc['photo']        ?? '[]', true) ?: [];
    $svc['languages_arr']    = json_decode($svc['languages']    ?? '[]', true) ?: [];
    $svc['service_list_arr'] = json_decode($svc['service_list'] ?? '[]', true) ?: [];
    $svc['social_arr']       = json_decode($svc['social']       ?? '{}', true) ?: [];
}
unset($svc);
foreach ($servicesExtra as &$svc) {
    $svc['photo_arr']        = json_decode($svc['photo']        ?? '[]', true) ?: [];
    $svc['languages_arr']    = json_decode($svc['languages']    ?? '[]', true) ?: [];
    $svc['service_list_arr'] = json_decode($svc['service_list'] ?? '[]', true) ?: [];
    $svc['social_arr']       = json_decode($svc['social']       ?? '{}', true) ?: [];
}
unset($svc);
foreach ($servicesGlobal as &$svc) {
    $svc['photo_arr']        = json_decode($svc['photo']        ?? '[]', true) ?: [];
    $svc['languages_arr']    = json_decode($svc['languages']    ?? '[]', true) ?: [];
    $svc['service_list_arr'] = json_decode($svc['service_list'] ?? '[]', true) ?: [];
    $svc['social_arr']       = json_decode($svc['social']       ?? '{}', true) ?: [];
}
unset($svc);

// Города для фильтра
try {
    $cst = $pdo->prepare("SELECT id,name,name_lat,is_capital FROM cities WHERE country_code=? ORDER BY is_capital DESC,sort_order ASC");
    $cst->execute([$countryCode]);
    $cities = $cst->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cities = [];
}
// Категории для фильтра
$categories = [
    'health'      => '🏥 Здоровье',
    'legal'       => '⚖️ Юристы',
    'family'      => '👨‍👩‍👧 Семья',
    'education'   => '📚 Образование',
    'business'    => '💼 Бизнес',
    'shops'       => '🛒 Магазины',
    'home'        => '🏠 Дом',
    'transport'   => '🚗 Транспорт',
    'it'          => '💻 IT',
    'events'      => '📷 События',
    'realestate'  => '🏢 Недвижимость',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="robots" content="noindex, follow">
<?php
// ── SEO мета-теги для results.php ───────────────────────
$countryNames = [
    'fr'=>'Франции','de'=>'Германии','es'=>'Испании','it'=>'Италии',
    'gb'=>'Великобритании','us'=>'США','ca'=>'Канаде','au'=>'Австралии',
    'nl'=>'Нидерландах','be'=>'Бельгии','ch'=>'Швейцарии','at'=>'Австрии',
    'pt'=>'Португалии','gr'=>'Греции','pl'=>'Польше','cz'=>'Чехии',
    'se'=>'Швеции','no'=>'Норвегии','dk'=>'Дании','fi'=>'Финляндии',
    'ie'=>'Ирландии','nz'=>'Новой Зеландии','ae'=>'ОАЭ','il'=>'Израиле',
    'tr'=>'Турции','th'=>'Таиланде','jp'=>'Японии','kr'=>'Южной Корее',
    'sg'=>'Сингапуре','hk'=>'Гонконге','mx'=>'Мексике','br'=>'Бразилии',
    'ar'=>'Аргентине','cl'=>'Чили','co'=>'Колумбии','za'=>'ЮАР',
    'ru'=>'России','ua'=>'Украине','by'=>'Беларуси','kz'=>'Казахстане',
];
$countryNameIn = $countryNames[$countryCode] ?? $countryCode;

// Формируем title и description в зависимости от контекста
if (!empty($searchQuery) && $detectedCity) {
    // "Врач в Париже — Poisq"
    $seoTitle = htmlspecialchars($searchQuery) . ' в ' . htmlspecialchars($detectedCity['name']) . ' — Poisq';
    $seoDesc  = 'Найдите русскоязычного специалиста «' . htmlspecialchars($searchQuery) . '» в ' . htmlspecialchars($detectedCity['name']) . '. Каталог русскоговорящих сервисов Poisq — ' . $totalCount . ' ' . (($totalCount === 1) ? 'результат' : ($totalCount < 5 ? 'результата' : 'результатов')) . '.';
} elseif (!empty($searchQuery)) {
    // "Врач во Франции — Poisq"
    $seoTitle = htmlspecialchars($searchQuery) . ' в ' . $countryNameIn . ' — Poisq';
    $seoDesc  = 'Найдите русскоязычного специалиста «' . htmlspecialchars($searchQuery) . '» в ' . $countryNameIn . '. Каталог русскоговорящих сервисов Poisq — ' . $totalCount . ' ' . (($totalCount === 1) ? 'результат' : ($totalCount < 5 ? 'результата' : 'результатов')) . '.';
} else {
    // Без запроса — общая страница страны
    $seoTitle = 'Русскоязычные сервисы в ' . $countryNameIn . ' — Poisq';
    $seoDesc  = 'Каталог русскоговорящих специалистов и сервисов в ' . $countryNameIn . ': врачи, юристы, репетиторы, переводчики и другие.';
}

$canonicalUrl = 'https://poisq.com/' . $countryCode . '/';
if ($detectedCity && !empty($detectedCity['name_lat'])) {
    $canonicalUrl .= strtolower($detectedCity['name_lat']) . '/';
} elseif ($cityFilter > 0 && !empty($citySlug)) {
    $canonicalUrl .= $citySlug . '/';
}
if (!empty($searchQuery)) {
    $canonicalUrl .= urlencode($searchQuery);
}
$extraParams = [];
if ($categoryFilter) $extraParams[] = 'category=' . urlencode($categoryFilter);
if ($page > 1)       $extraParams[] = 'page=' . $page;
if (!empty($extraParams)) $canonicalUrl .= '?' . implode('&', $extraParams);
?>
<title><?php echo $seoTitle; ?></title>
<meta name="description" content="<?php echo htmlspecialchars($seoDesc); ?>">
<link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
<meta property="og:title" content="<?php echo htmlspecialchars($seoTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seoDesc); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="https://poisq.com/og-image.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
<link rel="manifest" href="/manifest.json?v=2">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Poisq">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
:root {
  --primary: #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark: #2952D9;
  --text: #0F172A;
  --text-secondary: #64748B;
  --text-light: #94A3B8;
  --bg: #FFFFFF;
  --bg-secondary: #F8FAFC;
  --border: #E2E8F0;
  --border-light: #F1F5F9;
  --success: #10B981;
  --warning: #F59E0B;
  --danger: #EF4444;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
  --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
}
html, body { min-height: 100%; overflow-x: hidden; }
body {
  font-family: 'Manrope', sans-serif;
  background: var(--bg-secondary);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}
.app-container {
  max-width: 430px; margin: 0 auto;
  background: var(--bg); min-height: 100vh;
  display: flex; flex-direction: column;
  position: relative;
}

/* ===== HEADER ===== */
.results-header {
  position: sticky; top: 0; z-index: 100;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
}
.header-top {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; height: 56px;
}
.btn-back {
  width: 38px; height: 38px; border-radius: 12px; border: none;
  background: var(--bg-secondary); color: var(--text);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: all 0.15s;
}
.btn-back svg { width: 20px; height: 20px; stroke: var(--text); stroke-width: 2.5; fill: none; }
.btn-back:active { background: var(--primary); }
.btn-back:active svg { stroke: white; }
.header-logo { flex: 1; display: flex; justify-content: center; }
.header-logo img { height: 36px; width: auto; object-fit: contain; }
.btn-burger {
  width: 38px; height: 38px; display: flex; flex-direction: column;
  justify-content: center; align-items: center; gap: 5px;
  padding: 8px; cursor: pointer; background: none; border: none; border-radius: 12px;
  flex-shrink: 0;
}
.btn-burger span { display: block; width: 20px; height: 2px; background: var(--text-light); border-radius: 2px; transition: all 0.2s; }
.btn-burger:active { background: var(--primary-light); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.btn-burger.active span:nth-child(2) { opacity: 0; }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
.btn-add {
  width: 38px; height: 38px; border-radius: 12px; border: none;
  background: var(--primary-light); color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s, transform 0.1s;
  text-decoration: none; flex-shrink: 0;
}
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* Поисковая строка в хедере */
.header-search {
  padding: 0 14px 10px;
  position: relative;
}
.search-bar {
  display: flex; align-items: center; gap: 10px;
  background: var(--bg-secondary);
  border: 1.5px solid var(--border);
  border-radius: 99px; padding: 10px 16px;
  transition: all 0.2s;
}
.search-bar:focus-within { border-color: var(--primary); background: var(--bg); box-shadow: 0 0 0 3px rgba(59,108,244,0.08); }
.search-bar svg { width: 18px; height: 18px; stroke: var(--text-light); stroke-width: 2.5; fill: none; flex-shrink: 0; }
.search-bar:focus-within svg { stroke: var(--primary); }
.search-bar input {
  flex: 1; border: none; outline: none; font-size: 15px;
  font-family: 'Manrope', sans-serif; font-weight: 500;
  background: transparent; color: var(--text);
}
.search-bar input::placeholder { color: var(--text-light); }
.search-clear {
  width: 22px; height: 22px; border-radius: 50%; border: none;
  background: var(--border); cursor: pointer;
  display: none; align-items: center; justify-content: center;
  flex-shrink: 0; padding: 0; transition: all 0.15s;
}
.search-clear.visible { display: flex; }
.search-clear svg { width: 10px; height: 10px; stroke: var(--text-secondary); stroke-width: 2.5; }
.search-clear:active { background: var(--primary); }
.search-clear:active svg { stroke: white; }

/* ===== SEARCH OVERLAY ===== */
.search-overlay {
  position: fixed; inset: 0;
  background: var(--bg);
  z-index: 300;
  display: flex; flex-direction: column;
  transform: translateY(-100%);
  transition: transform 0.28s cubic-bezier(.4,0,.2,1);
  max-width: 430px; margin: 0 auto;
}
.search-overlay.active { transform: translateY(0); }
.so-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 14px; height: 58px; flex-shrink: 0;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg);
}
.so-back {
  width: 38px; height: 38px; border-radius: 12px;
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0; transition: background 0.15s, transform 0.1s;
}
.so-back:active { transform: scale(0.92); background: var(--border); }
.so-back svg { width: 18px; height: 18px; stroke: var(--text); fill: none; stroke-width: 2.2; }
.so-input-wrap {
  flex: 1; display: flex; align-items: center; gap: 10px;
  background: var(--bg-secondary); border: 1.5px solid var(--border);
  border-radius: 99px; padding: 9px 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.so-input-wrap:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,108,244,0.1); }
.so-input-wrap svg { width: 17px; height: 17px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.so-input {
  flex: 1; border: none; outline: none;
  font-size: 16px; font-weight: 500; color: var(--text);
  background: transparent; font-family: 'Manrope', sans-serif;
  -webkit-appearance: none; appearance: none; caret-color: var(--primary);
}
.so-input::placeholder { color: var(--text-light); }
.so-input::-webkit-search-decoration, .so-input::-webkit-search-cancel-button { display: none; }
.so-clear-btn {
  width: 24px; height: 24px; border-radius: 50%; border: none;
  background: var(--border); display: none; align-items: center;
  justify-content: center; cursor: pointer; flex-shrink: 0;
  transition: background 0.15s; padding: 0;
}
.so-clear-btn.visible { display: flex; }
.so-clear-btn svg { width: 11px; height: 11px; stroke: var(--text-secondary); fill: none; stroke-width: 3; }
.so-clear-btn:active { background: var(--primary); }
.so-clear-btn:active svg { stroke: white; }
.so-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }
.so-section-label {
  font-size: 11px; font-weight: 700; color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px; padding: 16px 18px 8px;
}
.so-item {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 18px; cursor: pointer;
  transition: background 0.12s; border-bottom: 1px solid var(--border-light);
}
.so-item:last-of-type { border-bottom: none; }
.so-item:active { background: var(--bg-secondary); }

.so-item-body { display:flex; flex-direction:column; gap:2px; flex:1; min-width:0; }
.so-item-text { font-size:14px; font-weight:500; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.so-item-sub  { font-size:12px; color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.so-item-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.so-item-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.so-item-icon.history { background: var(--bg-secondary); color: var(--text-light); }
.so-item-icon.suggest { background: var(--primary-light); color: var(--primary); }
.so-item-text { flex: 1; font-size: 15px; font-weight: 500; color: var(--text); }
.so-item-text .hl { color: var(--primary); font-weight: 700; }
.so-item-arrow { width: 16px; height: 16px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; }
.so-clear-history {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  padding: 14px 18px; font-size: 13px; font-weight: 600;
  color: var(--danger); cursor: pointer; border-top: 1px solid var(--border-light);
}
.so-clear-history svg { width: 14px; height: 14px; stroke: var(--danger); fill: none; stroke-width: 2; flex-shrink: 0; }
.so-popular { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 18px 12px; }
.so-tag {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px; background: var(--bg-secondary);
  border: 1.5px solid var(--border); border-radius: 99px;
  font-size: 14px; font-weight: 600; color: var(--text);
  cursor: pointer; transition: all 0.15s;
}
.so-tag:active { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }

/* Фильтры */
.filters-row {
  display: flex; gap: 6px; padding: 8px 16px 10px;
  overflow-x: auto; scrollbar-width: none;
}
.filters-row::-webkit-scrollbar { display: none; }
.filter-chip {
  flex-shrink: 0; display: flex; align-items: center; gap: 4px;
  padding: 5px 12px;
  font-size: 12px; font-weight: 500; font-family: 'Manrope', sans-serif;
  border: 1px solid #DFE1E5; border-radius: 99px;
  background: #fff; color: #4D5156;
  cursor: pointer; white-space: nowrap;
  transition: all 0.15s; user-select: none;
}
.filter-chip:active { background: #F8F9FA; }
.filter-chip.active { background: #E8F0FE; border-color: #1A73E8; color: #1A73E8; }
.filter-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: #34A853; flex-shrink: 0; }
.filter-chip.active .dot { background: #1A73E8; }

/* Инфо строка */
.results-meta {
  display: flex; align-items: center; justify-content: space-between;
  padding: 6px 16px 8px;
  border-top: 1px solid var(--border-light);
  background: var(--bg);
}
.results-count {
  font-size: 12px; font-weight: 500; color: var(--text-light);
}
.results-count span { color: var(--text-secondary); font-weight: 600; }
.sort-btn {
  display: flex; align-items: center; gap: 5px;
  font-family: 'Manrope', sans-serif;
  font-size: 13px; font-weight: 600; color: var(--text-secondary);
  border: none; background: none; cursor: pointer; padding: 4px 8px;
  border-radius: 8px; transition: all 0.15s;
}
.sort-btn svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }
.sort-btn:active { background: var(--border-light); }

/* ===== GOOGLE-STYLE RESULTS ===== */
.results-list { flex: 1; padding-bottom: 80px; background: var(--bg); }

.service-card {
  padding: 16px 16px 14px;
  border-bottom: 1px solid var(--border-light);
  cursor: pointer;
  transition: background 0.1s;
  background: var(--bg);
}
.service-card:active { background: #F8F9FA; }

/* Строка 1: favicon + breadcrumb */
.card-url-row {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 7px;
}
.card-favicon {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary-light);
  border: 1px solid var(--border-light);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: var(--primary);
  flex-shrink: 0; overflow: hidden;
}
.card-favicon img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.card-site-info { min-width: 0; flex: 1; }
.card-site-name {
  font-size: 13px; color: var(--text); font-weight: 600;
  display: flex; align-items: center; gap: 5px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.card-site-name .verified-dot {
  width: 15px; height: 15px; border-radius: 50%;
  background: #34A853; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
}
.card-site-name .verified-dot svg { width: 9px; height: 9px; }
.card-breadcrumb {
  font-size: 12px; color: #188038;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.badge-new {
  flex-shrink: 0;
  background: var(--primary); color: white;
  font-size: 9px; font-weight: 800;
  padding: 2px 6px; border-radius: 5px;
  letter-spacing: 0.4px; white-space: nowrap;
}
/* Строка 2: крупный заголовок */
.card-title {
  font-size: 18px; font-weight: 600; color: #1A0DAB;
  line-height: 1.3; margin-bottom: 5px;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
/* Строка 3: рейтинг */
.card-rating-row {
  display: flex; align-items: center; gap: 5px;
  font-size: 13px; color: var(--text-light);
  margin-bottom: 6px;
}
.card-rating-num { font-weight: 700; color: var(--text); }
.card-stars { display: flex; gap: 1px; }
.card-star-filled { color: #EA8600; }
.card-star-empty { color: var(--border); }
.card-reviews-cnt { color: var(--text-light); }
/* Строка 4: сниппет */
.card-snippet {
  font-size: 14px; line-height: 1.55; color: var(--text);
  margin-bottom: 10px;
  display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden;
}
/* Строка 5: теги */
.card-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.card-tag {
  font-size: 11.5px; font-weight: 600;
  padding: 3px 10px; border-radius: 99px; white-space: nowrap;
}
.tag-lang { background: #E8F0FE; color: #1A73E8; }
.tag-service { background: #E6F4EA; color: #188038; }
.tag-city { background: #FEF7E0; color: #B45309; }
/* Строка 6: кнопки */
.card-actions { display: flex; gap: 8px; }
.btn-call {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 18px; font-size: 13px; font-weight: 600;
  font-family: 'Manrope', sans-serif;
  background: var(--primary); color: white;
  border: none; border-radius: 20px; cursor: pointer;
  text-decoration: none; transition: opacity 0.15s;
}
.btn-call svg { width: 14px; height: 14px; stroke: white; stroke-width: 2.5; fill: none; flex-shrink: 0; }
.btn-call:active { opacity: 0.8; }
.btn-call-tg { background: #2AABEE; }
.btn-call-wa { background: #25D366; }
.btn-icon {
  width: 34px; height: 34px; border-radius: 50%;
  border: 1px solid var(--border); background: var(--bg);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--text);
  transition: background 0.15s; text-decoration: none; flex-shrink: 0;
}
.btn-icon svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
.btn-icon:active { background: #F1F3F4; }
.btn-icon.whatsapp { background: #25D366; border-color: #25D366; color: white; }
.btn-icon.whatsapp:active { background: #1ebe5d; }
.card-no-phone {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 18px; font-size: 13px; font-weight: 600;
  font-family: 'Manrope', sans-serif;
  background: var(--bg-secondary); border: 1.5px dashed var(--border);
  border-radius: 20px; color: var(--text-light);
  cursor: pointer; text-decoration: none;
}
.card-no-phone svg { width: 14px; height: 14px; stroke: var(--text-light); stroke-width: 2; fill: none; }

/* ===== EMPTY STATE ===== */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  padding: 60px 24px; text-align: center;
}
.empty-icon {
  width: 72px; height: 72px; border-radius: 24px;
  background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 20px;
}
.empty-icon svg { width: 36px; height: 36px; stroke: var(--primary); stroke-width: 1.5; fill: none; }
.empty-title { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.empty-subtitle { font-size: 14px; color: var(--text-secondary); line-height: 1.5; max-width: 260px; margin-bottom: 24px; }
.btn-reset {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 24px; border-radius: 12px; border: none;
  background: var(--primary); color: white;
  font-family: 'Manrope', sans-serif;
  font-size: 14px; font-weight: 700;
  cursor: pointer; text-decoration: none;
}

/* ===== ПАГИНАЦИЯ ===== */
.pagination {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; padding: 16px 14px 24px;
}
.page-btn {
  width: 40px; height: 40px; border-radius: 12px; border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Manrope', sans-serif; font-size: 14px; font-weight: 700;
  color: var(--text-secondary); cursor: pointer; background: var(--bg);
  text-decoration: none; transition: all 0.15s;
}
.page-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.page-btn:active { transform: scale(0.95); }
.page-btn.disabled { opacity: 0.4; pointer-events: none; }
.page-btn svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2.5; fill: none; }

/* ===== FILTER MODAL ===== */
.filter-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.5);
  z-index: 200; display: none; opacity: 0; transition: opacity 0.25s;
}
.filter-modal-overlay.active { display: flex; align-items: flex-end; opacity: 1; }
.filter-modal {
  width: 100%; max-width: 430px; margin: 0 auto;
  background: var(--bg); border-radius: 24px 24px 0 0;
  transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
  max-height: 85vh;
  display: flex; flex-direction: column;
  overflow: hidden;
}
.filter-modal-overlay.active .filter-modal { transform: translateY(0); }

/* Скроллится только контент, кнопка — фиксирована внизу */
.filter-modal-body {
  flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.filter-modal-footer {
  padding: 12px 20px calc(12px + env(safe-area-inset-bottom, 0px));
  background: var(--bg);
  border-top: 1px solid var(--border-light);
  flex-shrink: 0;
}
.filter-modal-handle {
  width: 36px; height: 4px; border-radius: 99px;
  background: var(--border); margin: 12px auto 0;
}
.filter-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border-light);
}
.filter-modal-title { font-size: 17px; font-weight: 800; color: var(--text); }
.filter-reset {
  font-size: 14px; font-weight: 600; color: var(--primary);
  border: none; background: none; cursor: pointer; padding: 4px 8px;
  border-radius: 8px;
}
.filter-reset:active { background: var(--primary-light); }
.filter-section { padding: 16px 20px; border-bottom: 1px solid var(--border-light); }
.filter-section-title { font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
.filter-cats { display: flex; flex-wrap: wrap; gap: 8px; }
.filter-cat-btn {
  padding: 8px 14px; border-radius: 12px;
  border: 1.5px solid var(--border);
  background: var(--bg);
  font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 600;
  color: var(--text-secondary); cursor: pointer; transition: all 0.15s;
}
.filter-cat-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
.filter-cat-btn:active { transform: scale(0.96); }
.filter-cities { display: flex; flex-direction: column; gap: 4px; }
.filter-city-btn {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px; border-radius: 12px; border: 1.5px solid transparent;
  background: var(--bg-secondary);
  font-family: 'Manrope', sans-serif; font-size: 14px; font-weight: 600;
  color: var(--text); cursor: pointer; transition: all 0.15s;
}
.filter-city-btn.active { background: var(--primary-light); border-color: var(--primary); color: var(--primary); }
.filter-city-btn svg { width: 16px; height: 16px; stroke: var(--primary); stroke-width: 2.5; fill: none; display: none; }
.filter-city-btn.active svg { display: block; }
.filter-ratings { display: flex; gap: 8px; }
.filter-rating-btn {
  flex: 1; padding: 10px 4px; border-radius: 12px;
  border: 1.5px solid var(--border); background: var(--bg);
  display: flex; align-items: center; justify-content: center; gap: 4px;
  font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700;
  color: var(--text-secondary); cursor: pointer; transition: all 0.15s;
}
.filter-rating-btn svg { width: 13px; height: 13px; fill: var(--warning); }
.filter-rating-btn.active { background: #FFF8E1; border-color: var(--warning); color: #92400E; }
.filter-apply {
  width: 100%;
  padding: 14px; border-radius: 14px; border: none;
  background: var(--primary); color: white;
  font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 800;
  cursor: pointer; transition: all 0.15s;
}
.filter-apply:active { background: var(--primary-dark); transform: scale(0.99); }

/* ===== АНИМАЦИИ ===== */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
.service-card { animation: fadeUp 0.3s ease both; }
.service-card:nth-child(1) { animation-delay: 0.04s; }
.service-card:nth-child(2) { animation-delay: 0.08s; }
.service-card:nth-child(3) { animation-delay: 0.12s; }
.service-card:nth-child(4) { animation-delay: 0.16s; }
.service-card:nth-child(5) { animation-delay: 0.20s; }

/* ===== SKELETON LOADER ===== */
.skeleton { background: linear-gradient(90deg, var(--bg-secondary) 25%, var(--border-light) 50%, var(--bg-secondary) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

::-webkit-scrollbar { display: none; }

/* ── КНОПКА 9 ТОЧЕК ── */
.btn-grid {
  width: 38px; height: 38px;
  border-radius: 12px; border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s, transform 0.1s;
}
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }

/* ── КНОПКА ЗАКРЫТЬ ФИЛЬТР ── */
.filter-close {
  width: 32px; height: 32px;
  border-radius: 50%; border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s;
  margin-left: 6px;
}
.filter-close svg { width: 15px; height: 15px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }
.filter-close:active { background: var(--danger-bg); }
.filter-close:active svg { stroke: var(--danger); }

/* ── ANN МОДАЛ (СВЕЖИЕ СЕРВИСЫ) ── */
.ann-modal {
  position: fixed; inset: 0;
  background: var(--bg-secondary);
  z-index: 500;
  display: none; flex-direction: column;
  max-width: 430px; margin: 0 auto;
}
.ann-modal.active { display: flex; animation: slideUp 0.3s cubic-bezier(.4,0,.2,1); }
@keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ann-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px; height: 58px;
  background: var(--bg); border-bottom: 1px solid var(--border-light); flex-shrink: 0;
}
.ann-header-icon { font-size: 20px; }
.ann-title { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; flex: 1; }
.ann-close {
  width: 34px; height: 34px; border-radius: 50%; border: none;
  background: var(--bg-secondary); display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s;
}
.ann-close:active { background: var(--border); }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }
.ann-city { padding: 10px 14px; background: var(--bg); border-bottom: 1px solid var(--border-light); flex-shrink: 0; }
.city-select {
  width: 100%; padding: 9px 36px 9px 13px;
  border: 1.5px solid var(--border); border-radius: 10px;
  font-size: 14px; font-weight: 600; color: var(--text);
  background: var(--bg-secondary); outline: none; cursor: pointer;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 11px center;
  font-family: 'Manrope', sans-serif; transition: border-color 0.15s;
}
.city-select:focus { border-color: var(--primary); }
.ann-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 14px; }
.ann-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; gap: 14px; }
.spinner { width: 32px; height: 32px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }
.ann-category { margin-bottom: 20px; }
.ann-cat-title { font-size: 15px; font-weight: 800; color: var(--text); letter-spacing: -0.3px; margin-bottom: 10px; padding-left: 2px; }
.ann-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
.ann-item { background: var(--bg); border-radius: 10px; overflow: hidden; cursor: pointer; transition: transform 0.15s; border: 1px solid var(--border-light); position: relative; }
.ann-item:active { transform: scale(0.94); }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--bg-secondary); }
.ann-date { position: absolute; top: 5px; right: 5px; background: rgba(59,108,244,0.9); color: white; padding: 3px 7px; border-radius: 6px; font-size: 9.5px; font-weight: 700; }
.ann-item-name { font-size: 11.5px; font-weight: 600; color: var(--text); padding: 7px 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }
.ann-add-card { background: var(--bg-secondary); border: 2px dashed var(--border); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; aspect-ratio: 1; cursor: pointer; transition: all 0.15s; gap: 5px; padding: 8px; }
.ann-add-card:active { border-color: var(--primary); background: var(--primary-light); transform: scale(0.95); }
.ann-add-card svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.ann-add-card span { font-size: 9.5px; color: var(--text-secondary); text-align: center; line-height: 1.3; font-weight: 600; }
.ann-empty { display: flex; flex-direction: column; align-items: center; padding: 50px 20px; text-align: center; gap: 10px; }
.ann-empty-icon { width: 64px; height: 64px; border-radius: 18px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
.ann-empty-icon svg { width: 30px; height: 30px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.ann-empty h3 { font-size: 16px; font-weight: 700; color: var(--text); }
.ann-empty p { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.6; }
.ann-add-btn { display: inline-flex; align-items: center; gap: 7px; background: var(--primary); color: white; padding: 11px 22px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; font-family: inherit; transition: opacity 0.15s, transform 0.1s; }
.ann-add-btn:active { opacity: 0.85; transform: scale(0.97); }
.ann-add-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2.5; }
.ann-add-free { font-size: 11.5px; color: var(--text-light); font-weight: 500; }

/* ── ЯЗЫКОВАЯ МОДАЛКА ── */
.lang-check-item {
  display: flex; align-items: center;
  padding: 13px 0; border-bottom: 1px solid var(--border-light);
  cursor: pointer; gap: 12px;
  font-size: 15px; font-weight: 600; color: var(--text);
}
.lang-check-item:last-child { border-bottom: none; }
.lang-check-item input[type="checkbox"] {
  width: 20px; height: 20px; flex-shrink: 0;
  accent-color: var(--primary); cursor: pointer;
}
.lang-check-item .lang-flag { font-size: 20px; flex-shrink: 0; }

</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">

  <!-- HEADER -->
  <div class="results-header">
    <div class="header-top">
      <div style="width:84px;display:flex;align-items:center;">
        <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
            <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
          </svg>
        </button>
      </div>
      <div class="header-logo">
        <a href="/">
          <img src="/logo.png" alt="Poisq" onerror="this.style.display='none'">
        </a>
      </div>
      <div style="width:84px;display:flex;align-items:center;justify-content:flex-end;gap:8px;">
        <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
        <button class="btn-add" onclick="openSlotsModal()" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <?php else: ?>
        <a href="<?php echo $isLoggedIn ? '/add-service.php' : '/register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </a>
        <?php endif; ?>
        <button class="btn-burger" id="menuToggle" aria-label="Меню">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>

    <div class="header-search">
      <div class="search-bar" style="cursor:pointer;position:relative;">
        <label for="soInput" onclick="openSearchOverlay()" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;cursor:pointer;overflow:hidden;">
          <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:var(--text-light);stroke-width:2.5;fill:none;flex-shrink:0;"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
          <span style="flex:1;font-size:15px;font-weight:500;color:<?php echo $searchQuery ? 'var(--text)' : 'var(--text-light)'; ?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?php echo $searchQuery ? htmlspecialchars($searchQuery) : 'Поиск сервисов...'; ?>
          </span>
        </label>
        <?php if ($searchQuery): ?>
        <button type="button" class="search-clear visible" aria-label="Очистить"
          onclick="window.location.href='/<?php echo $countryCode; ?>/'">
          <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Фильтры-чипсы -->
    <div class="filters-row">
      <div class="filter-chip <?php echo empty($categoryFilter) && !$ratingFilter && !$verifiedFilter && !$cityFilter ? 'active' : ''; ?>"
        onclick="resetFilters()">Все</div>
      <div class="filter-chip <?php echo $verifiedFilter ? 'active' : ''; ?>"
        onclick="toggleFilter('verified')"><span class="dot"></span> Проверено</div>
      <div class="filter-chip <?php echo $ratingFilter >= 4.5 ? 'active' : ''; ?>"
        onclick="toggleRating(4.5)">★ 4.5+</div>
      <div class="filter-chip <?php echo !empty($languagesFilter) ? 'active' : ''; ?>"
        id="langChip" onclick="openLangModal()">Языки<?php echo !empty($languagesFilter) ? ' (' . count($languagesFilter) . ')' : ''; ?></div>
      <div class="filter-chip" onclick="openFilterModal()">Фильтры ⚙</div>
    </div>

    <!-- Мета строка -->
    <div class="results-meta">
      <div class="results-count">
        Найдено: <span><?php echo $totalCount; ?></span>
        <?php if ($searchQuery): ?>
          по «<?php echo htmlspecialchars($searchQuery); ?>»
        <?php endif; ?>
        · <span style="cursor:pointer;color:var(--primary)" onclick="openFilterModal()">Фильтры</span>
      </div>
      <div></div>
    </div>
  </div>

  <!-- РЕЗУЛЬТАТЫ -->
  <div class="results-list" id="resultsList">
    <?php if (empty($services)): ?>
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      </div>
      <div class="empty-title">Ничего не найдено</div>
      <div class="empty-subtitle">
        <?php if ($searchQuery): ?>
          По запросу «<?php echo htmlspecialchars($searchQuery); ?>» сервисов не нашлось. Попробуйте другой запрос.
        <?php else: ?>
          В этой категории пока нет сервисов. Попробуйте другой фильтр.
        <?php endif; ?>
      </div>
      <a href="/<?php echo $countryCode; ?>/" class="btn-reset">
        Сбросить фильтры
      </a>
    </div>
    <?php else: ?>

    <?php foreach ($services as $svc):
      $photo = !empty($svc['photo_arr']) ? $svc['photo_arr'][0] : '';
      $phone = $svc['phone'] ?? '';
      $whatsapp = $svc['whatsapp'] ?? '';
      $langs = $svc['languages_arr'];
      $svcList = array_slice($svc['service_list_arr'], 0, 2);
      $catLabel = $categories[$svc['category']] ?? $svc['category'];
      $subcatLabel = $svc['subcategory'] ?? '';
      $isNew = (time() - strtotime($svc['created_at'])) < 7 * 86400;
      $flagMap = [
        'ru' => '🇷🇺 Русский', 'fr' => '🇫🇷 Français', 'en' => '🇬🇧 English',
        'de' => '🇩🇪 Deutsch', 'es' => '🇪🇸 Español', 'it' => '🇮🇹 Italiano',
        'uk' => '🇺🇦 Українська', 'he' => '🇮🇱 עברית', 'tr' => '🇹🇷 Türkçe',
      ];
      // Первая буква для favicon
      $faviconLetter = mb_strtoupper(mb_substr($svc['name'], 0, 1));
      // Цвет favicon по категории
      $faviconColors = [
        'health' => ['bg'=>'#FCE4EC','color'=>'#C62828'],
        'legal'  => ['bg'=>'#E3F2FD','color'=>'#1565C0'],
        'family' => ['bg'=>'#F3E5F5','color'=>'#6A1B9A'],
        'shops'  => ['bg'=>'#FFF8E1','color'=>'#F57F17'],
        'home'   => ['bg'=>'#E8F5E9','color'=>'#2E7D32'],
        'education' => ['bg'=>'#E0F2F1','color'=>'#00695C'],
        'business'  => ['bg'=>'#E8EAF6','color'=>'#283593'],
        'transport' => ['bg'=>'#FBE9E7','color'=>'#BF360C'],
        'it'        => ['bg'=>'#E1F5FE','color'=>'#0277BD'],
        'events'    => ['bg'=>'#FCE4EC','color'=>'#880E4F'],
        'realestate'=> ['bg'=>'#F1F8E9','color'=>'#33691E'],
      ];
      $fc = $faviconColors[$svc['category']] ?? ['bg'=>'#E8F0FE','color'=>'#1A73E8'];

      // Breadcrumb: poisq.com › Категория › Город
      $crumbCat = strip_tags($catLabel);
      $crumbCity = $svc['city_name'] ?? '';

      // Заголовок карточки — как у Google: "Название — категория в Городе"
      $cardTitle = htmlspecialchars($svc['name']);
      if ($crumbCity) {
        $cardTitle .= ' — ' . htmlspecialchars($crumbCity);
      }

      // Звёзды рейтинга
      $rating = floatval($svc['rating']);
      $fullStars = floor($rating);
      $halfStar  = ($rating - $fullStars) >= 0.5;
      $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

      $starFilled = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
      $starHalf   = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="opacity:0.55"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
      $starEmpty  = '<svg class="card-star-empty" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
      $starsHtml = str_repeat($starFilled, $fullStars) . ($halfStar ? $starHalf : '') . str_repeat($starEmpty, $emptyStars);

      $isMessengerCard = ($svc['category'] === 'messengers');
      $groupLink = trim($svc['group_link'] ?? '');
      $isTelegram = $groupLink && (strpos($groupLink, 't.me') !== false || strpos($groupLink, 'telegram') !== false);
    ?>
    <div class="service-card" onclick="sessionStorage.setItem('resultsScroll',window.scrollY);window.location.href='<?php echo serviceUrl($svc['id'], $svc['name']); ?>'">

      <!-- Строка 1: favicon + название + breadcrumb -->
      <div class="card-url-row">
        <div class="card-favicon" style="background:<?php echo $fc['bg']; ?>;color:<?php echo $fc['color']; ?>;border-color:<?php echo $fc['bg']; ?>">
          <?php if ($photo): ?>
            <img src="<?php echo htmlspecialchars($photo); ?>" alt="" loading="lazy"
              onerror="this.style.display='none';this.parentElement.innerHTML='<?php echo $faviconLetter; ?>'">
          <?php else: ?>
            <?php echo $faviconLetter; ?>
          <?php endif; ?>
        </div>
        <div class="card-site-info">
          <div class="card-site-name">
            <?php echo htmlspecialchars($svc['name']); ?>
            <?php if ($svc['verified'] && ($svc['verified_until'] === null || $svc['verified_until'] >= date('Y-m-d'))): ?>
              <span class="verified-dot" title="Проверено">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path d="m5 13 4 4L19 7"/></svg>
              </span>
            <?php endif; ?>

          </div>
          <div class="card-breadcrumb">
            poisq.com › <?php echo htmlspecialchars($crumbCat); ?><?php if ($crumbCity): ?> › <?php echo htmlspecialchars($crumbCity); ?><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Строка 2: крупный заголовок-ссылка -->
      <div class="card-title"><?php echo $cardTitle; ?></div>

      <!-- Строка 3: рейтинг -->
      <div class="card-rating-row">
        <?php if ($rating > 0): ?>
        <span class="card-rating-num"><?php echo number_format($rating, 1); ?></span>
        <div class="card-stars"><?php echo $starsHtml; ?></div>
        <?php if ($svc['reviews_count'] > 0): ?>
          <span class="card-reviews-cnt">(<?php echo $svc['reviews_count']; ?> <?php echo $svc['reviews_count'] === 1 ? 'отзыв' : ($svc['reviews_count'] < 5 ? 'отзыва' : 'отзывов'); ?>)</span>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($isNew): ?><span class="badge-new">НОВОЕ</span><?php endif; ?>
      </div>

      <!-- Строка 4: сниппет -->
      <?php if ($svc['description']): ?>
      <div class="card-snippet"><?php echo htmlspecialchars($svc['description']); ?></div>
      <?php endif; ?>

      <!-- Строка 5: теги -->
      <?php if (!empty($langs) || !empty($svcList)): ?>
      <div class="card-tags">
        <?php foreach ($langs as $lang): ?>
          <span class="card-tag tag-lang"><?php echo $flagMap[$lang] ?? strtoupper($lang); ?></span>
        <?php endforeach; ?>
        <?php foreach ($svcList as $s): ?>
          <?php $sName = is_array($s) ? ($s['name'] ?? '') : $s; ?>
          <?php if ($sName): ?>
            <span class="card-tag tag-service"><?php echo htmlspecialchars($sName); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Строка 6: кнопки действий -->
      <div class="card-actions" onclick="event.stopPropagation()">
        <?php if ($isMessengerCard && $groupLink): ?>
          <a href="<?php echo htmlspecialchars($groupLink); ?>" target="_blank"
            class="btn-call <?php echo $isTelegram ? 'btn-call-tg' : 'btn-call-wa'; ?>">
            <?php if ($isTelegram): ?>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.69 7.96c-.12.56-.46.7-.93.43l-2.58-1.9-1.24 1.19c-.14.14-.25.25-.52.25l.19-2.66 4.84-4.37c.21-.19-.05-.29-.32-.1L7.5 14.47l-2.54-.8c-.55-.17-.56-.55.12-.82l9.92-3.82c.46-.17.86.11.64.77z"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
            <?php endif; ?>
            Посмотреть группу
          </a>
        <?php elseif (!$isMessengerCard && $phone): ?>
          <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="btn-call">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            Позвонить
          </a>
        <?php else: ?>
          <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="card-no-phone">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
            Подробнее
          </a>
        <?php endif; ?>

        <?php if (!$isMessengerCard && $whatsapp): ?>
          <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>?text=<?php echo urlencode('Здравствуйте! Нашёл вас на Poisq.com. Меня интересует ваш сервис «' . $svc['name'] . '».'); ?>"
            target="_blank" class="btn-icon whatsapp" aria-label="WhatsApp" title="WhatsApp">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
          </a>
        <?php endif; ?>

        <?php if (!$isMessengerCard && $svc['address']): ?>
          <a href="https://maps.google.com/?q=<?php echo urlencode($svc['address']); ?>"
            target="_blank" class="btn-icon" aria-label="На карте" title="На карте">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 00-16 0c0 3 2.7 7 8 11.7z"/></svg>
          </a>
        <?php endif; ?>

        <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="btn-icon" aria-label="Подробнее" title="Подробнее">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
        </a>
      </div>

    </div>
    <?php endforeach; ?>

    <!-- ПАГИНАЦИЯ -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
        // Строим базовый ЧПУ URL для пагинации
        $baseUrlSeo = '/' . $countryCode . '/';
        if (!empty($citySlug)) $baseUrlSeo .= $citySlug . '/';
        elseif ($cityFilter > 0 && $detectedCity && !empty($detectedCity['name_lat'])) {
            $baseUrlSeo .= strtolower($detectedCity['name_lat']) . '/';
        }
        if (!empty($searchQuery)) $baseUrlSeo .= urlencode($searchQuery);
        $extraPag = [];
        if ($categoryFilter) $extraPag[] = 'category=' . urlencode($categoryFilter);
        if ($ratingFilter)   $extraPag[] = 'rating=' . $ratingFilter;
        if ($verifiedFilter) $extraPag[] = 'verified=1';
        $baseUrl = $baseUrlSeo . (empty($extraPag) ? '?' : '?' . implode('&', $extraPag) . '&');
      ?>
      <a href="<?php echo $baseUrl; ?>&page=<?php echo max(1, $page-1); ?>"
        class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      <?php for ($p = max(1, $page-2); $p <= min($totalPages, $page+2); $p++): ?>
      <a href="<?php echo $baseUrl; ?>&page=<?php echo $p; ?>"
        class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
        <?php echo $p; ?>
      </a>
      <?php endfor; ?>
      <a href="<?php echo $baseUrl; ?>&page=<?php echo min($totalPages, $page+1); ?>"
        class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
        <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div><!-- /results-list -->
<?php if (!empty($servicesExtra)): ?>
<div class="results-list" style="padding-top:0;margin-top:-28px">
  <div style="display:flex;align-items:center;gap:10px;padding:2px 2px 14px;">
    <div style="flex:1;height:1.5px;background:var(--border)"></div>
    <span style="font-size:15px;font-weight:700;color:var(--text);white-space:nowrap">
      📍 Похожее в твоей стране
    </span>
    <div style="flex:1;height:1.5px;background:var(--border)"></div>
  </div>
  <?php foreach ($servicesExtra as $svc):
    $photo = !empty($svc['photo_arr']) ? $svc['photo_arr'][0] : '';
    $phone = $svc['phone'] ?? '';
    $whatsapp = $svc['whatsapp'] ?? '';
    $langs = $svc['languages_arr'];
    $svcList = array_slice($svc['service_list_arr'], 0, 2);
    $catLabel = $categories[$svc['category']] ?? $svc['category'];
    $isNew = (time() - strtotime($svc['created_at'])) < 7 * 86400;
    $flagMap = [
      'ru' => '🇷🇺 Русский', 'fr' => '🇫🇷 Français', 'en' => '🇬🇧 English',
      'de' => '🇩🇪 Deutsch', 'es' => '🇪🇸 Español', 'it' => '🇮🇹 Italiano',
      'uk' => '🇺🇦 Українська', 'he' => '🇮🇱 עברית', 'tr' => '🇹🇷 Türkçe',
    ];
    $faviconLetter = mb_strtoupper(mb_substr($svc['name'], 0, 1));
    $faviconColorsEx = [
      'health'=>['bg'=>'#FCE4EC','color'=>'#C62828'],'legal'=>['bg'=>'#E3F2FD','color'=>'#1565C0'],
      'family'=>['bg'=>'#F3E5F5','color'=>'#6A1B9A'],'shops'=>['bg'=>'#FFF8E1','color'=>'#F57F17'],
      'home'=>['bg'=>'#E8F5E9','color'=>'#2E7D32'],'education'=>['bg'=>'#E0F2F1','color'=>'#00695C'],
      'business'=>['bg'=>'#E8EAF6','color'=>'#283593'],'transport'=>['bg'=>'#FBE9E7','color'=>'#BF360C'],
      'it'=>['bg'=>'#E1F5FE','color'=>'#0277BD'],'events'=>['bg'=>'#FCE4EC','color'=>'#880E4F'],
      'realestate'=>['bg'=>'#F1F8E9','color'=>'#33691E'],
    ];
    $fc = $faviconColorsEx[$svc['category']] ?? ['bg'=>'#E8F0FE','color'=>'#1A73E8'];
    $crumbCat = strip_tags($catLabel);
    $crumbCity = $svc['city_name'] ?? '';
    $cardTitle = htmlspecialchars($svc['name']);
    if ($crumbCity) $cardTitle .= ' — ' . htmlspecialchars($crumbCity);
    $rating = floatval($svc['rating']);
    $fullStars = floor($rating);
    $halfStar  = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    $starFilled = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starHalf   = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="opacity:0.55"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starEmpty  = '<svg class="card-star-empty" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starsHtml = str_repeat($starFilled, $fullStars) . ($halfStar ? $starHalf : '') . str_repeat($starEmpty, $emptyStars);
    $isMessengerCard = ($svc['category'] === 'messengers');
    $groupLink = trim($svc['group_link'] ?? '');
    $isTelegram = $groupLink && (strpos($groupLink, 't.me') !== false || strpos($groupLink, 'telegram') !== false);
  ?>
  <div class="service-card" onclick="sessionStorage.setItem('resultsScroll',window.scrollY);window.location.href='<?php echo serviceUrl($svc['id'], $svc['name']); ?>'">
    <div class="card-url-row">
      <div class="card-favicon" style="background:<?php echo $fc['bg']; ?>;color:<?php echo $fc['color']; ?>;border-color:<?php echo $fc['bg']; ?>">
        <?php if ($photo): ?>
          <img src="<?php echo htmlspecialchars($photo); ?>" alt="" loading="lazy"
            onerror="this.style.display='none';this.parentElement.innerHTML='<?php echo $faviconLetter; ?>'">
        <?php else: ?>
          <?php echo $faviconLetter; ?>
        <?php endif; ?>
      </div>
      <div class="card-site-info">
        <div class="card-site-name">
          <?php echo htmlspecialchars($svc['name']); ?>
          <?php if ($svc['verified'] && ($svc['verified_until'] === null || $svc['verified_until'] >= date('Y-m-d'))): ?>
            <span class="verified-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path d="m5 13 4 4L19 7"/></svg></span>
          <?php endif; ?>

        </div>
        <div class="card-breadcrumb">
          poisq.com › <?php echo htmlspecialchars($crumbCat); ?><?php if ($crumbCity): ?> › <?php echo htmlspecialchars($crumbCity); ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card-title"><?php echo $cardTitle; ?></div>
    <div class="card-rating-row">
      <?php if ($rating > 0): ?>
      <span class="card-rating-num"><?php echo number_format($rating, 1); ?></span>
      <div class="card-stars"><?php echo $starsHtml; ?></div>
      <?php if ($svc['reviews_count'] > 0): ?>
        <span class="card-reviews-cnt">(<?php echo $svc['reviews_count']; ?> <?php echo $svc['reviews_count'] === 1 ? 'отзыв' : ($svc['reviews_count'] < 5 ? 'отзыва' : 'отзывов'); ?>)</span>
      <?php endif; ?>
      <?php endif; ?>
      <?php if ($isNew): ?><span class="badge-new">НОВОЕ</span><?php endif; ?>
    </div>
    <?php if ($svc['description']): ?>
    <div class="card-snippet"><?php echo htmlspecialchars($svc['description']); ?></div>
    <?php endif; ?>
    <?php if (!empty($langs)): ?>
    <div class="card-tags">
      <?php foreach ($langs as $lang): ?>
        <span class="card-tag tag-lang"><?php echo $flagMap[$lang] ?? strtoupper($lang); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="card-actions" onclick="event.stopPropagation()">
      <?php if ($isMessengerCard && $groupLink): ?>
      <a href="<?php echo htmlspecialchars($groupLink); ?>" target="_blank" class="btn-call <?php echo $isTelegram ? 'btn-call-tg' : 'btn-call-wa'; ?>">
        <?php if ($isTelegram): ?>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.69 7.96c-.12.56-.46.7-.93.43l-2.58-1.9-1.24 1.19c-.14.14-.25.25-.52.25l.19-2.66 4.84-4.37c.21-.19-.05-.29-.32-.1L7.5 14.47l-2.54-.8c-.55-.17-.56-.55.12-.82l9.92-3.82c.46-.17.86.11.64.77z"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
        <?php endif; ?>
        Посмотреть группу
      </a>
      <?php elseif (!$isMessengerCard && $phone): ?>
      <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="btn-call">
        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        Позвонить
      </a>
      <?php else: ?>
      <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="card-no-phone">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        Подробнее
      </a>
      <?php endif; ?>
      <?php if (!$isMessengerCard && $whatsapp): ?>
      <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>?text=<?php echo urlencode('Здравствуйте! Нашёл вас на Poisq.com. Меня интересует ваш сервис «' . $svc['name'] . '».'); ?>"
        target="_blank" class="btn-icon whatsapp" aria-label="WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
      </a>
      <?php endif; ?>
      <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="btn-icon" aria-label="Подробнее">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($servicesGlobal)): ?>
<div class="results-list" style="padding-top:0">
  <div style="display:flex;align-items:center;gap:10px;padding:2px 2px 14px;">
    <div style="flex:1;height:1.5px;background:var(--border)"></div>
    <span style="font-size:15px;font-weight:700;color:var(--text);white-space:nowrap">
      🌍 Похожее в других странах
    </span>
    <div style="flex:1;height:1.5px;background:var(--border)"></div>
  </div>
  <?php foreach ($servicesGlobal as $svc):
    $photo = !empty($svc['photo_arr']) ? $svc['photo_arr'][0] : '';
    $phone = $svc['phone'] ?? '';
    $whatsapp = $svc['whatsapp'] ?? '';
    $langs = $svc['languages_arr'];
    $svcList = array_slice($svc['service_list_arr'], 0, 2);
    $catLabel = $categories[$svc['category']] ?? $svc['category'];
    $isNew = (time() - strtotime($svc['created_at'])) < 7 * 86400;
    $flagMap = [
      'ru' => '🇷🇺 Русский', 'fr' => '🇫🇷 Français', 'en' => '🇬🇧 English',
      'de' => '🇩🇪 Deutsch', 'es' => '🇪🇸 Español', 'it' => '🇮🇹 Italiano',
      'uk' => '🇺🇦 Українська', 'he' => '🇮🇱 עברית', 'tr' => '🇹🇷 Türkçe',
    ];
    $faviconLetter = mb_strtoupper(mb_substr($svc['name'], 0, 1));
    $faviconColorsEx = [
      'health'=>['bg'=>'#FCE4EC','color'=>'#C62828'],'legal'=>['bg'=>'#E3F2FD','color'=>'#1565C0'],
      'family'=>['bg'=>'#F3E5F5','color'=>'#6A1B9A'],'shops'=>['bg'=>'#FFF8E1','color'=>'#F57F17'],
      'home'=>['bg'=>'#E8F5E9','color'=>'#2E7D32'],'education'=>['bg'=>'#E0F2F1','color'=>'#00695C'],
      'business'=>['bg'=>'#E8EAF6','color'=>'#283593'],'transport'=>['bg'=>'#FBE9E7','color'=>'#BF360C'],
      'it'=>['bg'=>'#E1F5FE','color'=>'#0277BD'],'events'=>['bg'=>'#FCE4EC','color'=>'#880E4F'],
      'realestate'=>['bg'=>'#F1F8E9','color'=>'#33691E'],
    ];
    $fc = $faviconColorsEx[$svc['category']] ?? ['bg'=>'#E8F0FE','color'=>'#1A73E8'];
    $crumbCat = strip_tags($catLabel);
    $crumbCity = $svc['city_name'] ?? '';
    $cardTitle = htmlspecialchars($svc['name']);
    if ($crumbCity) $cardTitle .= ' — ' . htmlspecialchars($crumbCity);
    $rating = floatval($svc['rating']);
    $fullStars = floor($rating);
    $halfStar  = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    $starFilled = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starHalf   = '<svg class="card-star-filled" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="opacity:0.55"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starEmpty  = '<svg class="card-star-empty" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    $starsHtml = str_repeat($starFilled, $fullStars) . ($halfStar ? $starHalf : '') . str_repeat($starEmpty, $emptyStars);
    $isMessengerCard = ($svc['category'] === 'messengers');
    $groupLink = trim($svc['group_link'] ?? '');
    $isTelegram = $groupLink && (strpos($groupLink, 't.me') !== false || strpos($groupLink, 'telegram') !== false);
  ?>
  <div class="service-card" onclick="sessionStorage.setItem('resultsScroll',window.scrollY);window.location.href='<?php echo serviceUrl($svc['id'], $svc['name']); ?>'">
    <div class="card-url-row">
      <div class="card-favicon" style="background:<?php echo $fc['bg']; ?>;color:<?php echo $fc['color']; ?>;border-color:<?php echo $fc['bg']; ?>">
        <?php if ($photo): ?>
          <img src="<?php echo htmlspecialchars($photo); ?>" alt="" loading="lazy"
            onerror="this.style.display='none';this.parentElement.innerHTML='<?php echo $faviconLetter; ?>'">
        <?php else: ?>
          <?php echo $faviconLetter; ?>
        <?php endif; ?>
      </div>
      <div class="card-site-info">
        <div class="card-site-name">
          <?php echo htmlspecialchars($svc['name']); ?>
          <?php if ($svc['verified'] && ($svc['verified_until'] === null || $svc['verified_until'] >= date('Y-m-d'))): ?>
            <span class="verified-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><path d="m5 13 4 4L19 7"/></svg></span>
          <?php endif; ?>

        </div>
        <div class="card-breadcrumb">
          poisq.com › <?php echo htmlspecialchars($crumbCat); ?><?php if ($crumbCity): ?> › <?php echo htmlspecialchars($crumbCity); ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card-title"><?php echo $cardTitle; ?></div>
    <div class="card-rating-row">
      <?php if ($rating > 0): ?>
      <span class="card-rating-num"><?php echo number_format($rating, 1); ?></span>
      <div class="card-stars"><?php echo $starsHtml; ?></div>
      <?php if ($svc['reviews_count'] > 0): ?>
        <span class="card-reviews-cnt">(<?php echo $svc['reviews_count']; ?> <?php echo $svc['reviews_count'] === 1 ? 'отзыв' : ($svc['reviews_count'] < 5 ? 'отзыва' : 'отзывов'); ?>)</span>
      <?php endif; ?>
      <?php endif; ?>
      <?php if ($isNew): ?><span class="badge-new">НОВОЕ</span><?php endif; ?>
    </div>
    <?php if ($svc['description']): ?>
    <div class="card-snippet"><?php echo htmlspecialchars($svc['description']); ?></div>
    <?php endif; ?>
    <?php if (!empty($langs)): ?>
    <div class="card-tags">
      <?php foreach ($langs as $lang): ?>
        <span class="card-tag tag-lang"><?php echo $flagMap[$lang] ?? strtoupper($lang); ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="card-actions" onclick="event.stopPropagation()">
      <?php if ($isMessengerCard && $groupLink): ?>
      <a href="<?php echo htmlspecialchars($groupLink); ?>" target="_blank" class="btn-call <?php echo $isTelegram ? 'btn-call-tg' : 'btn-call-wa'; ?>">
        <?php if ($isTelegram): ?>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.69 7.96c-.12.56-.46.7-.93.43l-2.58-1.9-1.24 1.19c-.14.14-.25.25-.52.25l.19-2.66 4.84-4.37c.21-.19-.05-.29-.32-.1L7.5 14.47l-2.54-.8c-.55-.17-.56-.55.12-.82l9.92-3.82c.46-.17.86.11.64.77z"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
        <?php endif; ?>
        Посмотреть группу
      </a>
      <?php elseif (!$isMessengerCard && $phone): ?>
      <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="btn-call">
        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        Позвонить
      </a>
      <?php else: ?>
      <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="card-no-phone">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        Подробнее
      </a>
      <?php endif; ?>
      <?php if (!$isMessengerCard && $whatsapp): ?>
      <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp); ?>?text=<?php echo urlencode('Здравствуйте! Нашёл вас на Poisq.com. Меня интересует ваш сервис «' . $svc['name'] . '».'); ?>"
        target="_blank" class="btn-icon whatsapp" aria-label="WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
      </a>
      <?php endif; ?>
      <a href="<?php echo serviceUrl($svc['id'], $svc['name']); ?>" class="btn-icon" aria-label="Подробнее">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- /app-container -->

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- FILTER MODAL -->
<div class="filter-modal-overlay" id="filterModalOverlay">
  <div class="filter-modal" id="filterModal">
    <div class="filter-modal-handle"></div>
    <div class="filter-modal-header">
      <div class="filter-modal-title">Фильтры</div>
      <button class="filter-reset" onclick="resetFilters()">Сбросить</button>
      <button class="filter-close" onclick="closeFilterModal()" aria-label="Закрыть">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Скроллируемый контент -->
    <div class="filter-modal-body">
      <div class="filter-section">
        <div class="filter-section-title">Категория</div>
        <div class="filter-cats">
          <?php foreach ($categories as $key => $label): ?>
          <button class="filter-cat-btn <?php echo $categoryFilter === $key ? 'active' : ''; ?>"
            onclick="setCategory('<?php echo $key; ?>')" data-cat="<?php echo $key; ?>">
            <?php echo $label; ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Город</div>
        <div class="filter-cities">
          <button class="filter-city-btn <?php echo !$cityFilter ? 'active' : ''; ?>"
            onclick="setCity(0)">
            Все города
            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
          </button>
          <?php foreach ($cities as $city): ?>
          <button class="filter-city-btn <?php echo $cityFilter === (int)$city['id'] ? 'active' : ''; ?>"
            onclick="setCity(<?php echo $city['id']; ?>)">
            <?php
              $cityLabel = $city['name_lat']
                ? htmlspecialchars($city['name_lat']) . ' <span style="color:var(--text-light);font-size:11px">(' . htmlspecialchars($city['name']) . ')</span>'
                : htmlspecialchars($city['name']);
              echo $cityLabel;
            ?>
            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Минимальный рейтинг</div>
        <div class="filter-ratings">
          <?php foreach ([0, 3.0, 4.0, 4.5, 4.8] as $r): ?>
          <button class="filter-rating-btn <?php echo $ratingFilter == $r ? 'active' : ''; ?>"
            onclick="setRating(<?php echo $r; ?>)">
            <?php if ($r > 0): ?><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><?php endif; ?>
            <?php echo $r > 0 ? $r . '+' : 'Любой'; ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div><!-- /filter-modal-body -->

    <!-- Фиксированная кнопка внизу -->
    <div class="filter-modal-footer">
      <button class="filter-apply" onclick="applyFilters()">Показать результаты</button>
    </div>

  </div>
</div>

<!-- LANG MODAL -->
<div class="filter-modal-overlay" id="langModalOverlay">
  <div class="filter-modal" id="langModal">
    <div class="filter-modal-handle"></div>
    <div class="filter-modal-header">
      <div class="filter-modal-title">Язык сервиса</div>
      <button class="filter-reset" id="langResetBtn" onclick="resetLangs()" style="display:none">Сбросить</button>
      <button class="filter-close" onclick="closeLangModal()" aria-label="Закрыть">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="filter-modal-body">
      <div class="filter-section" style="border-bottom:none;">
        <?php
        $langOptions = [
          'ru' => ['flag' => '🇷🇺', 'name' => 'Русский'],
          'en' => ['flag' => '🇬🇧', 'name' => 'Английский'],
          'fr' => ['flag' => '🇫🇷', 'name' => 'Французский'],
          'de' => ['flag' => '🇩🇪', 'name' => 'Немецкий'],
          'es' => ['flag' => '🇪🇸', 'name' => 'Испанский'],
        ];
        foreach ($langOptions as $code => $info):
        ?>
        <label class="lang-check-item">
          <input type="checkbox" class="lang-checkbox" value="<?php echo $code; ?>"
            <?php echo in_array($code, $languagesFilter) ? 'checked' : ''; ?>
            onchange="onLangChange()">
          <span class="lang-flag"><?php echo $info['flag']; ?></span>
          <span><?php echo $info['name']; ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="filter-modal-footer">
      <button class="filter-apply" onclick="applyLangs()">Применить</button>
    </div>
  </div>
</div>

<!-- SEARCH OVERLAY -->
<div class="search-overlay" id="searchOverlay">
  <div class="so-header">
    <button class="so-back" onclick="closeSearchOverlay()" aria-label="Назад">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </button>
    <div class="so-input-wrap">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="search" class="so-input" id="soInput"
             placeholder="Найти сервис…"
             autocomplete="off" autocorrect="off"
             autocapitalize="off" spellcheck="false"
             inputmode="search" autofocus>
      <button class="so-clear-btn" id="soClearBtn" aria-label="Очистить">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
  <div class="so-content" id="soContent"></div>
</div>

<script>
// === ПЕРЕМЕННЫЕ ФИЛЬТРОВ ===
let currentCategory = '<?php echo $categoryFilter; ?>';
let currentCity = <?php echo $cityFilter; ?>;
let currentRating = <?php echo $ratingFilter; ?>;
let currentVerified = <?php echo $verifiedFilter; ?>;
let currentLanguages = <?php echo json_encode($languagesFilter); ?>;
const countryCode = '<?php echo $countryCode; ?>';
// === ПОИСК — OVERLAY ===
const soInput   = document.getElementById('soInput');
const soClearBtn = document.getElementById('soClearBtn');
const soContent = document.getElementById('soContent');
const searchOverlay = document.getElementById('searchOverlay');

const HISTORY_KEY = 'poisq_search_history';
const POPULAR = [
  { emoji: '🩺', text: 'Врач' },
  { emoji: '⚖️', text: 'Юрист' },
  { emoji: '📚', text: 'Репетитор' },
  { emoji: '🌐', text: 'Переводчик' },
  { emoji: '🧠', text: 'Психолог' },
  { emoji: '💅', text: 'Красота' },
  { emoji: '🦷', text: 'Стоматолог' },
  { emoji: '📸', text: 'Фотограф' },
  { emoji: '💆', text: 'Массаж' },
  { emoji: '📊', text: 'Бухгалтер' },
];

function getHistory() {
  try { return JSON.parse(localStorage.getItem(HISTORY_KEY)) || []; } catch { return []; }
}
function saveHistory(q) {
  if (!q.trim() || q.length < 2) return;
  let h = getHistory().filter(x => x !== q);
  h.unshift(q);
  localStorage.setItem(HISTORY_KEY, JSON.stringify(h.slice(0, 8)));
}
function clearHistory() {
  localStorage.removeItem(HISTORY_KEY);
  renderSoContent('');
}

function openSearchOverlay() {
  history.pushState({ searchOpen: true }, '');
  searchOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  soInput.value = '<?php echo addslashes($searchQuery); ?>';
  soClearBtn.classList.toggle('visible', soInput.value.length > 0);
  soInput.focus();
  renderSoContent(soInput.value);
}

function hideKeyboard() {
  soInput.blur();
  document.activeElement && document.activeElement.blur();
  const tmp = document.createElement('input');
  tmp.setAttribute('type', 'text');
  tmp.setAttribute('readonly', 'readonly');
  tmp.style.cssText = 'position:fixed;top:-100px;left:-100px;width:1px;height:1px;opacity:0;';
  document.body.appendChild(tmp);
  tmp.focus();
  tmp.blur();
  setTimeout(function() { document.body.removeChild(tmp); }, 300);
  // Хак для Opera Android
  if (/OPR|Opera/i.test(navigator.userAgent)) {
    setTimeout(function() {
      window.scrollTo(0, window.scrollY + 1);
      window.scrollTo(0, window.scrollY - 1);
    }, 50);
  }
}
function closeSearchOverlay() {
  hideKeyboard();
  searchOverlay.classList.remove('active');
  document.body.style.overflow = '';
  soInput.value = '';
  soClearBtn.classList.remove('visible');
}

window.addEventListener('popstate', () => {
  if (searchOverlay.classList.contains('active')) closeSearchOverlay();
});

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function hlMatch(str, q) {
  const i = str.toLowerCase().indexOf(q.toLowerCase());
  if (i === -1) return escHtml(str);
  return escHtml(str.slice(0,i))
       + `<span class="hl">${escHtml(str.slice(i, i+q.length))}</span>`
       + escHtml(str.slice(i+q.length));
}

let suggestAbort = null;

async function renderSoContent(q) {
  const hist = getHistory();
  let html = '';

  if (!q) {
    if (hist.length) {
      html += `<div class="so-section-label">Недавние</div>`;
      html += hist.map(h => `
        <div class="so-item" onclick="soSearch('${escHtml(h)}')">
          <div class="so-item-icon history">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
          </div>
          <span class="so-item-text">${escHtml(h)}</span>
          <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </div>`).join('');
      html += `<div class="so-clear-history" onclick="clearHistory()">
        <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        Очистить историю
      </div>`;
    }
    soContent.innerHTML = html;
    return;
  }

  const matchHist = hist.filter(h => h.toLowerCase().includes(q.toLowerCase()));
  if (matchHist.length) {
    html += `<div class="so-section-label">Из истории</div>`;
    html += matchHist.slice(0, 3).map(h => `
      <div class="so-item" onclick="soSearch('${escHtml(h)}')">
        <div class="so-item-icon history">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
        </div>
        <span class="so-item-text">${hlMatch(h, q)}</span>
        <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
      </div>`).join('');
  }

  html += `<div id="so-live-results">
    <div style="padding:14px 18px;color:var(--text-light);font-size:13px;font-weight:500">Ищем подсказки…</div>
  </div>`;
  soContent.innerHTML = html;

  if (suggestAbort) suggestAbort.abort();
  suggestAbort = new AbortController();
  const country = countryCode || localStorage.getItem('poisq_country') || 'fr';

  try {
    const resp = await fetch(
      `/api/suggest.php?q=${encodeURIComponent(q)}&country=${encodeURIComponent(country)}`,
      { signal: suggestAbort.signal }
    );
    const suggestions = await resp.json();
    const liveDiv = document.getElementById('so-live-results');
    if (!liveDiv) return;

    if (!suggestions || !suggestions.length) {
      liveDiv.innerHTML = `<div style="padding:16px 18px;color:var(--text-light);font-size:13px;font-weight:500">Ничего не найдено — попробуйте другой запрос</div>`;
      return;
    }

    let liveHtml = `<div class="so-section-label">Подсказки</div>`;
    suggestions.forEach((s, i) => {
      const c = s.country || country;
      const citySlug = s.city_slug || '';
      let url;
      if (s.q && citySlug) {
        url = '/' + c + '/' + citySlug + '/' + encodeURIComponent(s.q);
      } else if (s.q) {
        url = '/' + c + '/' + encodeURIComponent(s.q);
      } else if (citySlug) {
        url = '/' + c + '/' + citySlug + '/';
      } else {
        url = '/' + c + '/';
      }
      window._suggest = window._suggest || [];
      window._suggest[i] = { q: s.q, url: url };
      const iconHtml = s.photo
        ? `<div class="so-item-icon suggest" style="background:none;padding:0;overflow:hidden;border-radius:8px;"><img src="${escHtml(s.photo)}" style="width:36px;height:36px;object-fit:cover;border-radius:8px;" onerror="this.style.display='none'"></div>`
        : `<div class="so-item-icon suggest"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>`;
      const subtitleParts = [];
      if (s.subtitle) subtitleParts.push(escHtml(s.subtitle));
      if (s.rating)   subtitleParts.push("⭐ " + s.rating);
      const subtitleHtml = subtitleParts.length
        ? `<span class="so-item-sub">${subtitleParts.join(" · ")}</span>`
        : "";
      liveHtml += `
        <div class="so-item" onclick="soGoTo(${i})">
          ${iconHtml}
          <div class="so-item-body">
            <span class="so-item-text">${hlMatch(escHtml(s.text), q)}</span>
            ${subtitleHtml}
          </div>
          <svg class="so-item-arrow" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
        </div>`;
    });
    liveDiv.innerHTML = liveHtml;

  } catch (e) {
    if (e.name === 'AbortError') return;
    const liveDiv = document.getElementById('so-live-results');
    if (liveDiv) liveDiv.innerHTML = '';
  }
}

function soGoTo(i) {
  const s = (window._suggest || [])[i];
  if (!s) return;
  if (s.q) saveHistory(sanitizeQuery(s.q));
  closeSearchOverlay();
  setTimeout(function() {
    if (s.q) {
      const clean = sanitizeQuery(s.q);
      const c = s.country || countryCode || 'fr';
      const citySlug = s.city_slug || '';
      if (citySlug) {
        window.location.href = '/' + c + '/' + citySlug + '/' + encodeURIComponent(clean);
      } else {
        window.location.href = '/' + c + '/' + encodeURIComponent(clean);
      }
    } else {
      window.location.href = s.url;
    }
  }, 100);
}
function sanitizeQuery(q) {
  // Заменяем слеш на пробел — иначе ломается ЧПУ URL
  return q.replace(/\//g, ' ').replace(/\s+/g, ' ').trim();
}

function soSearch(q) {
  if (!q.trim()) return;
  const clean = sanitizeQuery(q);
  saveHistory(clean);
  closeSearchOverlay();
  setTimeout(function() {
    const c = countryCode || localStorage.getItem('poisq_country') || 'fr';
    window.location.href = '/' + c + '/' + encodeURIComponent(clean);
  }, 100);
}

let soTimer = null;
soInput.addEventListener('input', () => {
  const q = soInput.value.trim();
  soClearBtn.classList.toggle('visible', q.length > 0);
  clearTimeout(soTimer);
  soTimer = setTimeout(() => renderSoContent(q), 300);
});
soInput.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); soSearch(soInput.value.trim()); }
});
soClearBtn.addEventListener('click', () => {
  soInput.value = '';
  soClearBtn.classList.remove('visible');
  soInput.focus();
  renderSoContent('');
});

// === FILTER MODAL ===
const filterOverlay = document.getElementById('filterModalOverlay');

function openFilterModal() {
  filterOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeFilterModal() {
  filterOverlay.classList.remove('active');
  document.body.style.overflow = '';
}
filterOverlay.addEventListener('click', (e) => {
  if (e.target === filterOverlay) closeFilterModal();
});

// === ФИЛЬТРЫ ===
function setCategory(cat) {
  currentCategory = currentCategory === cat ? '' : cat;
  document.querySelectorAll('.filter-cat-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.cat === currentCategory);
  });
}

function setCity(id) {
  currentCity = id;
  document.querySelectorAll('.filter-city-btn').forEach((btn, i) => {
    btn.classList.toggle('active', i === 0 ? id === 0 : false);
  });
  // Находим кнопку с нужным onclick
  document.querySelectorAll('.filter-city-btn').forEach(btn => {
    const match = btn.getAttribute('onclick')?.match(/setCity\((\d+)\)/);
    if (match) btn.classList.toggle('active', parseInt(match[1]) === id);
  });
}

function setRating(r) {
  currentRating = r;
  document.querySelectorAll('.filter-rating-btn').forEach(btn => {
    const match = btn.getAttribute('onclick')?.match(/setRating\(([0-9.]+)\)/);
    if (match) btn.classList.toggle('active', parseFloat(match[1]) === r);
  });
}

function toggleFilter(type) {
  if (type === 'verified') {
    currentVerified = currentVerified ? 0 : 1;
    document.querySelectorAll('.filter-chip').forEach(chip => {
      if (chip.getAttribute('onclick') === "toggleFilter('verified')") {
        chip.classList.toggle('active', currentVerified === 1);
      }
    });
    applyFilters();
  }
}

function toggleRating(r) {
  currentRating = currentRating === r ? 0 : r;
  applyFilters();
}

function resetFilters() {
  currentCategory = '';
  currentCity = 0;
  currentRating = 0;
  currentVerified = 0;
  currentLanguages = [];
  const q = '<?php echo addslashes($searchQuery); ?>';
  window.location.href = '/' + countryCode + '/' + (q ? encodeURIComponent(q) : '');
}

function applyFilters() {
  closeFilterModal();
  const q = soInput.value.trim() || '<?php echo addslashes($searchQuery); ?>';
  const params = new URLSearchParams();
  if (currentCategory) params.set('category', currentCategory);
  if (currentCity > 0) params.set('city_id', currentCity);
  if (currentRating > 0) params.set('rating', currentRating);
  if (currentVerified) params.set('verified', 1);
  if (currentLanguages.length > 0) params.set('languages', currentLanguages.join(','));
  const base = '/' + countryCode + '/' + (q ? encodeURIComponent(q) : '');
  const qs = params.toString();
  window.location.href = base + (qs ? '?' + qs : '');
}

// === LANGUAGE MODAL ===
const langOverlay = document.getElementById('langModalOverlay');
const langResetBtn = document.getElementById('langResetBtn');

function openLangModal() {
  // Синхронизируем чекбоксы с currentLanguages
  document.querySelectorAll('.lang-checkbox').forEach(cb => {
    cb.checked = currentLanguages.includes(cb.value);
  });
  updateLangResetBtn();
  langOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeLangModal() {
  langOverlay.classList.remove('active');
  document.body.style.overflow = '';
}
langOverlay.addEventListener('click', (e) => {
  if (e.target === langOverlay) closeLangModal();
});
function onLangChange() {
  updateLangResetBtn();
}
function updateLangResetBtn() {
  const any = Array.from(document.querySelectorAll('.lang-checkbox')).some(cb => cb.checked);
  langResetBtn.style.display = any ? '' : 'none';
}
function resetLangs() {
  document.querySelectorAll('.lang-checkbox').forEach(cb => cb.checked = false);
  updateLangResetBtn();
}
function applyLangs() {
  currentLanguages = Array.from(document.querySelectorAll('.lang-checkbox'))
    .filter(cb => cb.checked).map(cb => cb.value);
  // Обновляем чип
  const chip = document.getElementById('langChip');
  if (chip) {
    chip.textContent = currentLanguages.length > 0
      ? 'Языки (' + currentLanguages.length + ')'
      : 'Языки';
    chip.classList.toggle('active', currentLanguages.length > 0);
  }
  closeLangModal();
  applyFilters();
}

// Восстановление позиции скролла
window.addEventListener('load', () => {
  const pos = sessionStorage.getItem('resultsScroll');
  if (pos) { window.scrollTo(0, parseInt(pos)); sessionStorage.removeItem('resultsScroll'); }
});
document.querySelectorAll('.service-card').forEach(card => {
  card.addEventListener('click', () => sessionStorage.setItem('resultsScroll', window.scrollY));
});
</script>

<script>
// ════════════════════════════════════════
// СВЕЖИЕ СЕРВИСЫ (РУПОР)
// ════════════════════════════════════════
let annCityId = null;

async function openAnnModal() {
  const modal = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const cr = await fetch('/api/get-user-country.php');
    const cd = await cr.json();
    const cc = cd.country_code || countryCode || 'fr';
    const cir = await fetch('/api/get-cities.php?country=' + cc);
    const cities = await cir.json();
    const sel = document.getElementById('annCitySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name_lat ? c.name_lat + ' (' + c.name + ')' : c.name;
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch(e) {
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение и попробуйте снова.');
  }
}

function closeAnnModal() {
  document.getElementById('annModal').classList.remove('active');
  document.body.style.overflow = '';
}

async function filterByCity() {
  annCityId = document.getElementById('annCitySelect').value;
  await loadAnnServices(annCityId);
}

async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = '<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>';
  try {
    const r = await fetch('/api/get-services.php?city_id=' + cityId + '&days=5');
    const d = await r.json();
    const sv = d.services || [];
    if (!sv.length) {
      content.innerHTML = '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div><h3>Пока нет сервисов</h3><p>В этом городе нет новых сервисов<br>за последние 5 дней</p></div>';
      return;
    }
    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });
    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += '<div class="ann-category"><div class="ann-cat-title">' + cat + '</div><div class="ann-grid">';
      list.forEach(s => {
        let photo = 'https://via.placeholder.com/200?text=Poisq';
        if (s.photo) { try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; } catch(e) { photo = s.photo; } }
        html += '<div class="ann-item" onclick="location.href=\'service.php?id=' + s.id + '\'"><img src="' + photo + '" alt="' + s.name + '" loading="lazy" onerror="this.src=\'https://via.placeholder.com/200?text=Poisq\'"><div class="ann-date">' + fmtAnnDate(s.created_at) + '</div><div class="ann-item-name">' + s.name + '</div></div>';
      });
      html += '</div></div>';
    }
    content.innerHTML = html;
  } catch(e) {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}

function annErr(t, p) {
  return '<div class="ann-empty"><div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><h3>' + t + '</h3><p>' + p + '</p></div>';
}

function fmtAnnDate(ds) {
  const d = new Date(ds), now = new Date();
  const diff = Math.floor((now - d) / 86400000);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Вчера';
  if (diff < 5) return diff + ' дн.';
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}
</script>

<!-- ANN MODAL (СВЕЖИЕ СЕРВИСЫ) -->
<div class="ann-modal" id="annModal">
  <div class="ann-header">
    <span class="ann-header-icon">📢</span>
    <span class="ann-title">Свежие сервисы</span>
    <button class="ann-close" onclick="closeAnnModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="ann-city">
    <select id="annCitySelect" class="city-select" onchange="filterByCity()">
      <option>Загрузка...</option>
    </select>
  </div>
  <div class="ann-content" id="annContent">
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div>
  </div>
</div>

<?php if ($isLoggedIn && $slotsLeft <= 0): ?>
<div id="slotsModal" style="display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,0.5);align-items:flex-end;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:430px;border-radius:24px 24px 0 0;padding:32px 24px 40px;animation:slideUpSlots 0.3s ease-out;">
    <div style="text-align:center;margin-bottom:20px;">
      <div style="width:64px;height:64px;border-radius:50%;background:#FEF2F2;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#EF4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="font-size:19px;font-weight:700;color:#1F2937;margin-bottom:8px;">Все слоты заняты</div>
      <div style="font-size:14px;color:#6B7280;line-height:1.6;">Вы разместили максимальное количество сервисов (3 из 3).<br>Удалите один из существующих, чтобы добавить новый.</div>
    </div>
    <div style="background:#F0FDF4;border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#065F46;line-height:1.5;">💡 Перейдите в <strong>«Мои сервисы»</strong>, чтобы удалить или управлять сервисами</div>
    <a href="/my-services.php" style="display:block;width:100%;padding:14px;background:#3B6CF4;color:white;border-radius:12px;text-align:center;font-size:15px;font-weight:600;text-decoration:none;margin-bottom:10px;">Перейти в Мои сервисы</a>
    <button onclick="closeSlotsModal()" style="display:block;width:100%;padding:14px;background:#F3F4F6;color:#374151;border-radius:12px;border:none;font-size:15px;cursor:pointer;">Закрыть</button>
  </div>
</div>
<style>@keyframes slideUpSlots{from{transform:translateY(100%)}to{transform:translateY(0)}}</style>
<script>
function openSlotsModal(){document.getElementById("slotsModal").style.display="flex";document.body.style.overflow="hidden";}
function closeSlotsModal(){document.getElementById("slotsModal").style.display="none";document.body.style.overflow="";}
document.getElementById("slotsModal").addEventListener("click",function(e){if(e.target===this)closeSlotsModal();});
</script>
<?php endif; ?>
</body>
</html>