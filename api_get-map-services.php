<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

$pdo = getDbConnection();

$country     = $_GET['country'] ?? '';
$city_id     = intval($_GET['city_id'] ?? 0);
$category    = $_GET['category'] ?? '';
$rating      = floatval($_GET['rating'] ?? 0);
$verified    = isset($_GET['verified']) ? 1 : 0;
$q           = trim($_GET['q'] ?? '');
$focus_id    = intval($_GET['focus'] ?? 0);
$isGlobal    = isset($_GET['global']) && $_GET['global'] === '1';

// Multi-country support (comma-separated country codes)
$countriesList = [];
foreach (explode(',', $_GET['countries'] ?? '') as $c) {
    $c = trim($c);
    if (preg_match('/^[a-z]{2}$/', $c)) $countriesList[] = $c;
}

// Focus mode: return single service
if ($focus_id > 0) {
    $stmt = $pdo->prepare("SELECT s.id, s.name, s.category, s.subcategory, s.lat, s.lng,
               s.phone, s.whatsapp, s.photo, s.address, s.description,
               s.rating, s.reviews_count, c.name as city_name, s.country_code
        FROM services s
        LEFT JOIN cities c ON s.city_id = c.id
        WHERE s.id = ? AND s.status = 'approved' AND s.is_visible = 1
          AND s.lat IS NOT NULL AND s.lng IS NOT NULL");
    $stmt->execute([$focus_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as &$s) {
        $photos = json_decode($s['photo'], true);
        $s['photo'] = (!empty($photos) && is_array($photos)) ? $photos[0] : null;
    }
    echo json_encode(['success' => true, 'services' => $services, 'fallback_level' => 'city',
        'searched_city' => null, 'searched_country' => $country, 'clean_q' => ''],
        JSON_UNESCAPED_UNICODE);
    exit;
}

// Strip city name from query if city_id already given
if ($q && $city_id) {
    try {
        $cs = $pdo->prepare("SELECT name, name_lat FROM cities WHERE id = ? LIMIT 1");
        $cs->execute([$city_id]);
        $fc = $cs->fetch(PDO::FETCH_ASSOC);
        if ($fc) {
            $q = trim(preg_replace('/'.preg_quote($fc['name'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/'.preg_quote($fc['name_lat'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/\s+/', ' ', $q));
        }
    } catch (Exception $e) {}
}

// Auto-detect city from query text (if city_id not given)
if ($q && !$city_id) {
    $qwords = array_filter(explode(' ', mb_strtolower($q, 'UTF-8')), fn($w) => mb_strlen($w) >= 3);
    foreach ($qwords as $qw) {
        $cs = $pdo->prepare("SELECT id, name, name_lat, country_code FROM cities
            WHERE LOWER(name) LIKE ? OR LOWER(name_lat) LIKE ? LIMIT 1");
        $cs->execute(['%'.$qw.'%', '%'.$qw.'%']);
        $fc = $cs->fetch(PDO::FETCH_ASSOC);
        if ($fc) {
            $city_id = $fc['id'];
            $country = $fc['country_code']; // always update country from detected city
            $q = trim(preg_replace('/'.preg_quote($fc['name'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/'.preg_quote($fc['name_lat'], '/').'/iu', '', $q));
            $q = trim(preg_replace('/\s+/', ' ', $q));
            break;
        }
    }
}

// Save clean query (after city stripped) — returned in response for JS expand logic
$cleanQ = $q;

// Strip country name from query text (always, even if country already set)
$countryStripList = [
    'франция','германия','испания','италия','швейцария','австрия','бельгия',
    'нидерланды','голландия','португалия','польша','великобритания','англия',
    'швеция','израиль','турция','эмираты','оаэ','греция','финляндия','дания','норвегия','чехия',
];
foreach ($countryStripList as $name) {
    if (mb_stripos($q, $name) !== false) {
        $q = trim(preg_replace('/'.preg_quote($name, '/').'/iu', '', $q));
        $q = trim(preg_replace('/\s+/', ' ', $q));
        $cleanQ = $q;
        break;
    }
}

// Auto-detect country from query text (if country not given)
if (!$country && !$city_id) {
    $countryHints = [
        'франция'=>'fr','германия'=>'de','испания'=>'es','италия'=>'it',
        'швейцария'=>'ch','австрия'=>'at','бельгия'=>'be','нидерланды'=>'nl',
        'голландия'=>'nl','португалия'=>'pt','польша'=>'pl','великобритания'=>'gb',
        'англия'=>'gb','швеция'=>'se','израиль'=>'il','турция'=>'tr',
        'эмираты'=>'ae','оаэ'=>'ae','греция'=>'gr','финляндия'=>'fi',
        'дания'=>'dk','норвегия'=>'no','чехия'=>'cz',
    ];
    $qLowerForCountry = mb_strtolower($q, 'UTF-8');
    foreach ($countryHints as $name => $code) {
        if (mb_strpos($qLowerForCountry, $name) !== false) {
            $country = $code;
            // Убираем название страны из запроса
            $q = trim(preg_replace('/'.preg_quote($name, '/').'/iu', '', $q));
            $q = trim(preg_replace('/\s+/', ' ', $q));
            $cleanQ = $q;
            break;
        }
    }
}

// Base conditions (always applied)
$baseWhere = [
    "s.status = 'approved'",
    "s.is_visible = 1",
    "(s.lat IS NOT NULL OR (s.category = 'messengers' AND c2.lat IS NOT NULL))",
];

// Category / rating / verified filters
$filterWhere  = [];
$filterParams = [];
if ($category) {
    $filterWhere[] = "s.category = ?";
    $filterParams[] = $category;
}
if ($rating > 0) {
    $filterWhere[] = "s.rating >= ?";
    $filterParams[] = $rating;
}
if ($verified) {
    $filterWhere[] = "s.verified = 1";
}

// Text / search conditions
$textWhere  = [];
$textParams = [];

// Strip russian language words (same as results.php)
$russianStopWords = [
    'русскоязычный','русскоязычная','русскоязычное','русскоязычные','русскоязычных',
    'русскоговорящий','русскоговорящая','русскоговорящие','русскоговорящих',
    'русский','русская','русское','русские','русских','русского','русскому',
];
foreach ($russianStopWords as $sw) {
    $q = trim(preg_replace('/'.preg_quote($sw,'/').'\b/iu', '', $q));
}
$q = trim(preg_replace('/\s+/', ' ', $q));
$cleanQ = $q;

// Messenger detection
$messengerKeywords = [
    'WhatsApp группа' => ['ватсап','вотсап','whatsapp','ватсапп'],
    'Telegram группа' => ['телеграм','telegram','телеграмм','тг'],
];
$messengerFound = false;
foreach ($messengerKeywords as $subcatValue => $keywords) {
    foreach ($keywords as $kw) {
        if (mb_strpos(mb_strtolower($q, 'UTF-8'), $kw) !== false) {
            $textWhere[]  = "s.subcategory = ?";
            $textParams[] = $subcatValue;
            $q = '';
            $messengerFound = true;
            break 2;
        }
    }
}

// Generic группа/группы — ищем по category=messengers
if (!$messengerFound) {
    $groupKeywords = ['группы','группа','group','groups'];
    foreach ($groupKeywords as $gkw) {
        if (mb_strpos(mb_strtolower($q, 'UTF-8'), $gkw) !== false) {
            $textWhere[]  = "s.category = ?";
            $textParams[] = 'messengers';
            $messengerFound = true;
            $q = '';
            break;
        }
    }
}

// Словарь синонимов → подкатегория/категория
$synonymMap = [
    // health
    'врач'            => ['subcategory' => 'Врачи'],
    'врачи'           => ['subcategory' => 'Врачи'],
    'доктор'          => ['subcategory' => 'Врачи'],
    'доктора'         => ['subcategory' => 'Врачи'],
    'стоматолог'      => ['subcategory' => 'Стоматология'],
    'стоматологи'     => ['subcategory' => 'Стоматология'],
    'дантист'         => ['subcategory' => 'Стоматология'],
    'психолог'        => ['subcategory' => 'Психология'],
    'психологи'       => ['subcategory' => 'Психология'],
    'психотерапевт'   => ['subcategory' => 'Психология'],
    'салон красоты'   => ['subcategory' => 'Салоны красоты'],
    'салон'           => ['subcategory' => 'Салоны красоты'],
    'красота'         => ['subcategory' => 'Салоны красоты'],
    'парикмахер'      => ['subcategory' => 'Салоны красоты'],
    'фитнес'          => ['subcategory' => 'Фитнес и спорт'],
    'спортзал'        => ['subcategory' => 'Фитнес и спорт'],
    'аптека'          => ['subcategory' => 'Аптеки'],
    'аптеки'          => ['subcategory' => 'Аптеки'],
    // legal
    'юрист'           => ['subcategory' => 'Консультации', 'category' => 'legal'],
    'юристы'          => ['subcategory' => 'Консультации', 'category' => 'legal'],
    'адвокат'         => ['subcategory' => 'Адвокаты'],
    'адвокаты'        => ['subcategory' => 'Адвокаты'],
    'нотариус'        => ['subcategory' => 'Нотариус'],
    'нотариусы'       => ['subcategory' => 'Нотариус'],
    'иммиграция'      => ['subcategory' => 'Иммиграция'],
    'виза'            => ['subcategory' => 'Иммиграция'],
    'визы'            => ['subcategory' => 'Иммиграция'],
    'вид на жительство' => ['subcategory' => 'Иммиграция'],
    'внж'             => ['subcategory' => 'Иммиграция'],
    // family
    'няня'            => ['subcategory' => 'Няни'],
    'няни'            => ['subcategory' => 'Няни'],
    'бэбиситтер'      => ['subcategory' => 'Бэбиситтеры'],
    'бебиситтер'      => ['subcategory' => 'Бэбиситтеры'],
    'репетитор'       => ['subcategory' => 'Репетиторы'],
    'репетиторы'      => ['subcategory' => 'Репетиторы'],
    'детский кружок'  => ['subcategory' => 'Детские кружки'],
    'кружок'          => ['subcategory' => 'Детские кружки'],
    // education
    'курсы'           => ['category' => 'education'],
    'курс'            => ['category' => 'education'],
    'обучение'        => ['category' => 'education'],
    'языковые курсы'  => ['subcategory' => 'Языковые курсы'],
    'русский язык'    => ['subcategory' => 'Русский язык'],
    'музыкальная школа' => ['subcategory' => 'Музыка'],
    'музыка'          => ['subcategory' => 'Музыка'],
    // business
    'бухгалтер'       => ['subcategory' => 'Бухгалтерия'],
    'бухгалтеры'      => ['subcategory' => 'Бухгалтерия'],
    'бухгалтерия'     => ['subcategory' => 'Бухгалтерия'],
    'налоги'          => ['subcategory' => 'Налоги'],
    'налог'           => ['subcategory' => 'Налоги'],
    'страховка'       => ['subcategory' => 'Страхование'],
    'страхование'     => ['subcategory' => 'Страхование'],
    'перевод денег'   => ['subcategory' => 'Переводы денег'],
    'денежный перевод' => ['subcategory' => 'Переводы денег'],
    'банк'            => ['subcategory' => 'Банковские услуги'],
    // home
    'ремонт'          => ['subcategory' => 'Ремонт', 'category' => 'home'],
    'уборка'          => ['subcategory' => 'Уборка'],
    'клининг'         => ['subcategory' => 'Уборка'],
    'переезд'         => ['subcategory' => 'Переезды'],
    'переезды'        => ['subcategory' => 'Переезды'],
    'грузчики'        => ['subcategory' => 'Переезды'],
    'охрана'          => ['subcategory' => 'Охрана'],
    'животные'        => ['subcategory' => 'Животные'],
    'ветеринар'       => ['subcategory' => 'Животные'],
    'химчистка'       => ['subcategory' => 'Химчистка'],
    // transport
    'такси'           => ['subcategory' => 'Такси/Трансфер'],
    'трансфер'        => ['subcategory' => 'Такси/Трансфер'],
    'автошкола'       => ['subcategory' => 'Автошкола'],
    'аренда авто'     => ['subcategory' => 'Аренда авто'],
    'автосервис'      => ['subcategory' => 'Авто сервис'],
    'автомеханик'     => ['subcategory' => 'Авто сервис'],
    'мотосервис'      => ['subcategory' => 'Мото сервис'],
    // shops
    'ресторан'        => ['subcategory' => 'Рестораны'],
    'рестораны'       => ['subcategory' => 'Рестораны'],
    'кафе'            => ['subcategory' => 'Рестораны'],
    'магазин'         => ['category' => 'shops'],
    'магазины'        => ['category' => 'shops'],
    'доставка продуктов' => ['subcategory' => 'Доставка продуктов'],
    'доставка еды'    => ['subcategory' => 'Доставка продуктов'],
    'пекарня'         => ['subcategory' => 'Пекарни'],
    'хлеб'            => ['subcategory' => 'Пекарни'],
    'мясо'            => ['subcategory' => 'Мясные лавки'],
    'мясная лавка'    => ['subcategory' => 'Мясные лавки'],
    // realestate
    'аренда квартиры' => ['subcategory' => 'Аренда', 'category' => 'realestate'],
    'снять квартиру'  => ['subcategory' => 'Аренда', 'category' => 'realestate'],
    'квартира'        => ['category' => 'realestate'],
    'недвижимость'    => ['category' => 'realestate'],
    'ипотека'         => ['subcategory' => 'Ипотека'],
    // it
    'программист'     => ['category' => 'it'],
    'программисты'    => ['category' => 'it'],
    'разработчик'     => ['subcategory' => 'Веб разработка'],
    'разработчики'    => ['subcategory' => 'Веб разработка'],
    'дизайнер'        => ['subcategory' => 'Дизайн'],
    'дизайнеры'       => ['subcategory' => 'Дизайн'],
    'smm'             => ['subcategory' => 'SMM/Маркетинг'],
    'маркетинг'       => ['subcategory' => 'SMM/Маркетинг'],
    // events
    'фотограф'        => ['subcategory' => 'Фотографы'],
    'фотографы'       => ['subcategory' => 'Фотографы'],
    'видеограф'       => ['subcategory' => 'Видеографы'],
    'видеографы'      => ['subcategory' => 'Видеографы'],
    'туризм'          => ['subcategory' => 'Туризм'],
    'тур'             => ['subcategory' => 'Туризм'],
];

// Ищем совпадение в словаре (сначала длинные фразы, потом короткие)
$synonymMatched = false;
if ($q && !$messengerFound) {
    $qLowerSyn = mb_strtolower($q, 'UTF-8');
    // Сортируем по длине ключа (длинные фразы приоритетнее)
    $synonymKeys = array_keys($synonymMap);
    usort($synonymKeys, fn($a,$b) => mb_strlen($b) - mb_strlen($a));
    foreach ($synonymKeys as $keyword) {
        if (mb_strpos($qLowerSyn, $keyword) !== false) {
            $match = $synonymMap[$keyword];
            if (isset($match['subcategory'])) {
                $textWhere[]  = "s.subcategory = ?";
                $textParams[] = $match['subcategory'];
                if (isset($match['category']) && !$category) {
                    $filterWhere[]  = "s.category = ?";
                    $filterParams[] = $match['category'];
                }
            } elseif (isset($match['category']) && !$category) {
                $textWhere[]  = "s.category = ?";
                $textParams[] = $match['category'];
            }
            $synonymMatched = true;
            break;
        }
    }
}

if ($q && !$messengerFound && !$synonymMatched) {
    $subStmt = $pdo->prepare("SELECT category_slug, name FROM service_subcategories WHERE is_active=1");
    $subStmt->execute();
    $allSubs = $subStmt->fetchAll(PDO::FETCH_ASSOC);

    $catStmt = $pdo->prepare("SELECT slug, name FROM service_categories WHERE is_active=1");
    $catStmt->execute();
    $allCats = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $matchedSubcategory = null;
    $matchedCategory    = null;
    $qLower = mb_strtolower($q, 'UTF-8');

    foreach ($allSubs as $sub) {
        $subLower = mb_strtolower($sub['name'], 'UTF-8');
        if ($subLower === $qLower || strpos($subLower, $qLower) !== false || strpos($qLower, $subLower) !== false) {
            $matchedSubcategory = $sub['name'];
            $matchedCategory    = $sub['category_slug'];
            break;
        }
    }
    if (!$matchedSubcategory) {
        foreach ($allCats as $cat) {
            $catLower = mb_strtolower($cat['name'], 'UTF-8');
            if ($catLower === $qLower || strpos($catLower, $qLower) !== false || strpos($qLower, $catLower) !== false) {
                $matchedCategory = $cat['slug'];
                break;
            }
        }
    }

    if ($matchedSubcategory) {
        $textWhere[]  = "s.subcategory = ?";
        $textParams[] = $matchedSubcategory;
    } elseif ($matchedCategory && !$category) {
        $textWhere[]  = "s.category = ?";
        $textParams[] = $matchedCategory;
    } else {
        $textWhere[]  = "(s.name LIKE ? OR s.description LIKE ? OR s.subcategory LIKE ?)";
        $textParams[] = '%' . $q . '%';
        $textParams[] = '%' . $q . '%';
        $textParams[] = '%' . $q . '%';
    }
}

$selectSql = "SELECT s.id, s.name, s.category, s.subcategory,
               COALESCE(s.lat, c2.lat) as lat,
               COALESCE(s.lng, c2.lng) as lng,
               s.phone, s.whatsapp, s.photo, s.address, s.description,
               s.rating, s.reviews_count, c2.name as city_name, s.country_code, s.group_link
        FROM services s
        LEFT JOIN cities c2 ON s.city_id = c2.id";
$orderBy = "s.verified DESC, s.rating DESC, s.views DESC";

function runQuery($pdo, $selectSql, $baseWhere, $locationWhere, $filterWhere, $textWhere,
                  $locationParams, $filterParams, $textParams, $limit, $orderBy) {
    $allWhere  = array_merge($baseWhere, $locationWhere, $filterWhere, $textWhere);
    $allParams = array_merge($locationParams, $filterParams, $textParams);
    $sql = $selectSql . ' WHERE ' . implode(' AND ', $allWhere)
         . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch searched city info for response (coords for JS zoom)
$searchedCity    = null;
$searchedCountry = $country;
if ($city_id) {
    $cs = $pdo->prepare("SELECT id, name, lat, lng FROM cities WHERE id = ? LIMIT 1");
    $cs->execute([$city_id]);
    $cityRow = $cs->fetch(PDO::FETCH_ASSOC);
    if ($cityRow) $searchedCity = $cityRow;
}

$services      = [];
$fallbackLevel = 'global';

if (!$isGlobal) {

    // ── Level 1: specific city ──────────────────────────────────────────────
    if ($city_id) {
        $services = runQuery($pdo, $selectSql, $baseWhere,
            ["s.city_id = ?"], $filterWhere, $textWhere,
            [$city_id], $filterParams, $textParams, 200, $orderBy);

        if (!empty($services)) {
            $fallbackLevel = 'city';
        } else {
            // City has 0 results — return immediately, JS zooms to city + shows expand banner
            echo json_encode([
                'success'          => true,
                'services'         => [],
                'fallback_level'   => 'city_empty',
                'searched_city'    => $searchedCity,
                'searched_country' => $searchedCountry,
                'clean_q'          => $cleanQ,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── Level 2: country search (no specific city) ──────────────────────────
    elseif ($country) {
        $services = runQuery($pdo, $selectSql, $baseWhere,
            ["s.country_code = ?"], $filterWhere, $textWhere,
            [$country], $filterParams, $textParams, 200, $orderBy);

        if (!empty($services)) {
            $fallbackLevel = 'country';
        } else {
            // Country also empty — JS shows "nearby countries" banner
            echo json_encode([
                'success'          => true,
                'services'         => [],
                'fallback_level'   => 'country_empty',
                'searched_city'    => null,
                'searched_country' => $searchedCountry,
                'clean_q'          => $cleanQ,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ── Multi-country search (banner tap: "nearby countries") ───────────────
    elseif (!empty($countriesList)) {
        $placeholders = implode(',', array_fill(0, count($countriesList), '?'));
        $services = runQuery($pdo, $selectSql, $baseWhere,
            ["s.country_code IN ($placeholders)"], $filterWhere, $textWhere,
            $countriesList, $filterParams, $textParams, 30, $orderBy);
        if (!empty($services)) $fallbackLevel = 'nearby';
        // if still empty — falls through to global below
    }
}

// ── Level 3 (global): global=1 or nothing found above ──────────────────────
if (empty($services)) {
    $services = runQuery($pdo, $selectSql, $baseWhere,
        [], $filterWhere, $textWhere,
        [], $filterParams, $textParams, 20, $orderBy);
    $fallbackLevel = 'global';
}

foreach ($services as &$s) {
    $photos = json_decode($s['photo'], true);
    $s['photo'] = (!empty($photos) && is_array($photos)) ? $photos[0] : null;
}

echo json_encode([
    'success'          => true,
    'services'         => $services,
    'fallback_level'   => $fallbackLevel,
    'searched_city'    => $searchedCity,
    'searched_country' => $searchedCountry,
    'clean_q'          => $cleanQ,
], JSON_UNESCAPED_UNICODE);
