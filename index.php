<?php
// index.php — Главная страница Poisq
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $isLoggedIn ? ($_SESSION['user_name']   ?? '') : '';
$userAvatar = $isLoggedIn ? ($_SESSION['user_avatar'] ?? '') : '';
$userInitial = $userName ? strtoupper(substr($userName, 0, 1)) : '';

// Страны из БД для выбора страны
require_once __DIR__ . '/config/database.php';
$_jsCountries = [];
try {
    $_pdo = getDbConnection();
    foreach ($_pdo->query("SELECT code, name_ru FROM countries WHERE is_active=1 ORDER BY name_ru")->fetchAll(PDO::FETCH_ASSOC) as $_r) {
        $_jsCountries[] = ['code' => $_r['code'], 'name' => $_r['name_ru']];
    }
} catch (Exception $_e) { error_log('Countries DB: ' . $_e->getMessage()); }
if (empty($_jsCountries)) {
    $_jsCountries = [
        ['code'=>'ae','name'=>'ОАЭ'],['code'=>'ar','name'=>'Аргентина'],['code'=>'au','name'=>'Австралия'],
        ['code'=>'at','name'=>'Австрия'],['code'=>'be','name'=>'Бельгия'],['code'=>'br','name'=>'Бразилия'],
        ['code'=>'by','name'=>'Беларусь'],['code'=>'ca','name'=>'Канада'],['code'=>'ch','name'=>'Швейцария'],
        ['code'=>'cl','name'=>'Чили'],['code'=>'co','name'=>'Колумбия'],['code'=>'cz','name'=>'Чехия'],
        ['code'=>'de','name'=>'Германия'],['code'=>'dk','name'=>'Дания'],['code'=>'es','name'=>'Испания'],
        ['code'=>'fi','name'=>'Финляндия'],['code'=>'fr','name'=>'Франция'],['code'=>'gb','name'=>'Великобритания'],
        ['code'=>'gr','name'=>'Греция'],['code'=>'hk','name'=>'Гонконг'],['code'=>'ie','name'=>'Ирландия'],
        ['code'=>'il','name'=>'Израиль'],['code'=>'it','name'=>'Италия'],['code'=>'jp','name'=>'Япония'],
        ['code'=>'kr','name'=>'Южная Корея'],['code'=>'kz','name'=>'Казахстан'],['code'=>'mx','name'=>'Мексика'],
        ['code'=>'nl','name'=>'Нидерланды'],['code'=>'no','name'=>'Норвегия'],['code'=>'nz','name'=>'Новая Зеландия'],
        ['code'=>'pl','name'=>'Польша'],['code'=>'pt','name'=>'Португалия'],['code'=>'ru','name'=>'Россия'],
        ['code'=>'se','name'=>'Швеция'],['code'=>'sg','name'=>'Сингапур'],['code'=>'th','name'=>'Таиланд'],
        ['code'=>'tr','name'=>'Турция'],['code'=>'ua','name'=>'Украина'],['code'=>'us','name'=>'США'],['code'=>'za','name'=>'ЮАР'],
    ];
}

// Проверка слотов
$slotsLeft = 3;
if ($isLoggedIn) {
    try {
        $pdo = getDbConnection();
        $st = $pdo->prepare("SELECT COUNT(*) FROM services WHERE user_id = ? AND status = 'approved'");
        $st->execute([$_SESSION['user_id']]);
        $slotsLeft = max(0, 3 - (int)$st->fetchColumn());
    } catch (Exception $e) { $slotsLeft = 3; }
}

function getCountryByIP() {
    $ip = $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost', ''])) {
        return ['code' => 'fr', 'name' => 'Франция'];
    }
    $cacheFile = sys_get_temp_dir() . '/poisq_geo_' . md5($ip);
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }
    $context  = stream_context_create(['http' => ['timeout' => 3, 'user_agent' => 'Poisq/1.0']]);
    $response = @file_get_contents('https://ipwhois.app/json/' . urlencode($ip), false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['country_code'])) {
            $code   = strtolower($data['country_code']);
            $result = ['code' => $code, 'name' => getCountryName($code)];
            @file_put_contents($cacheFile, json_encode($result));
            return $result;
        }
    }
    return ['code' => 'fr', 'name' => 'Франция'];
}

function getCountryName($code) {
    $map = [
        'af'=>'Афганистан','al'=>'Албания','dz'=>'Алжир','ar'=>'Аргентина',
        'am'=>'Армения','au'=>'Австралия','at'=>'Австрия','az'=>'Азербайджан',
        'bs'=>'Багамы','bh'=>'Бахрейн','bd'=>'Бангладеш','by'=>'Беларусь',
        'be'=>'Бельгия','bz'=>'Белиз','bo'=>'Боливия','ba'=>'Босния и Герцеговина',
        'br'=>'Бразилия','bg'=>'Болгария','kh'=>'Камбоджа','cm'=>'Камерун',
        'ca'=>'Канада','cl'=>'Чили','cn'=>'Китай','co'=>'Колумбия',
        'cr'=>'Коста-Рика','hr'=>'Хорватия','cu'=>'Куба','cy'=>'Кипр',
        'cz'=>'Чехия','dk'=>'Дания','do'=>'Доминикана','ec'=>'Эквадор',
        'eg'=>'Египет','sv'=>'Сальвадор','ee'=>'Эстония','fi'=>'Финляндия',
        'fr'=>'Франция','ge'=>'Грузия','de'=>'Германия','gr'=>'Греция',
        'gt'=>'Гватемала','hn'=>'Гондурас','hk'=>'Гонконг','hu'=>'Венгрия',
        'is'=>'Исландия','in'=>'Индия','id'=>'Индонезия','ir'=>'Иран',
        'iq'=>'Ирак','ie'=>'Ирландия','il'=>'Израиль','it'=>'Италия',
        'jp'=>'Япония','jo'=>'Иордания','kz'=>'Казахстан','ke'=>'Кения',
        'kw'=>'Кувейт','kg'=>'Кыргызстан','lv'=>'Латвия','lb'=>'Ливан',
        'lt'=>'Литва','lu'=>'Люксембург','my'=>'Малайзия','mv'=>'Мальдивы',
        'mt'=>'Мальта','mx'=>'Мексика','md'=>'Молдова','mc'=>'Монако',
        'mn'=>'Монголия','me'=>'Черногория','ma'=>'Марокко','np'=>'Непал',
        'nl'=>'Нидерланды','nz'=>'Новая Зеландия','ni'=>'Никарагуа','ng'=>'Нигерия',
        'mk'=>'Северная Македония','no'=>'Норвегия','om'=>'Оман','pk'=>'Пакистан',
        'pa'=>'Панама','py'=>'Парагвай','pe'=>'Перу','ph'=>'Филиппины',
        'pl'=>'Польша','pt'=>'Португалия','qa'=>'Катар','ro'=>'Румыния',
        'ru'=>'Россия','sa'=>'Саудовская Аравия','rs'=>'Сербия','sg'=>'Сингапур',
        'sk'=>'Словакия','si'=>'Словения','za'=>'ЮАР','kr'=>'Южная Корея',
        'es'=>'Испания','lk'=>'Шри-Ланка','se'=>'Швеция','ch'=>'Швейцария',
        'sy'=>'Сирия','tw'=>'Тайвань','tj'=>'Таджикистан','tz'=>'Танзания',
        'th'=>'Таиланд','tr'=>'Турция','tm'=>'Туркменистан','ua'=>'Украина',
        'ae'=>'ОАЭ','gb'=>'Великобритания','us'=>'США','uy'=>'Уругвай',
        'uz'=>'Узбекистан','ve'=>'Венесуэла','vn'=>'Вьетнам','xk'=>'Косово',
    ];
    return $map[$code] ?? $code;
}

$detectedCountry = getCountryByIP();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Poisq — русскоязычные сервисы за рубежом</title>
<meta name="description" content="Найдите русскоязычных специалистов рядом с вами — врачей, юристов, репетиторов и других профессионалов в вашем городе">
<meta property="og:title" content="Poisq — русскоязычные сервисы за рубежом">
<meta property="og:description" content="Найдите русскоязычных специалистов рядом с вами — врачей, юристов, репетиторов и других профессионалов в вашем городе">
<meta property="og:image" content="https://poisq.com/apple-touch-icon.png?v=2">
<meta property="og:url" content="https://poisq.com/">
<meta property="og:type" content="website">
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
*, *::before, *::after {
  margin: 0; padding: 0; box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

:root {
  --primary:       #3B6CF4;
  --primary-light: #EEF2FF;
  --primary-dark:  #2952D9;
  --text:          #0F172A;
  --text-secondary:#64748B;
  --text-light:    #94A3B8;
  --bg:            #FFFFFF;
  --bg-secondary:  #F8FAFC;
  --border:        #E2E8F0;
  --border-light:  #F1F5F9;
  --success:       #10B981;
  --success-bg:    #ECFDF5;
  --danger:        #EF4444;
  --danger-bg:     #FEF2F2;
  --shadow-sm:  0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:  0 4px 20px rgba(59,108,244,0.12), 0 2px 8px rgba(0,0,0,0.06);
  --shadow-card:0 2px 12px rgba(0,0,0,0.06);
  --radius:    16px;
  --radius-sm: 10px;
  --radius-xs:  8px;
}

html {
  -webkit-overflow-scrolling: touch;
  overflow-y: auto; height: auto;
}
body {
  font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg);
  color: var(--text);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  touch-action: manipulation;
  overflow-y: auto;
}

.app-container {
  max-width: 430px;
  margin: 0 auto;
  background: var(--bg);
  min-height: 100vh; min-height: 100dvh;
  display: flex; flex-direction: column;
  position: relative;
}

/* ── ШАПКА ─────────────────────────────────────── */
.header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 14px;
  height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
  position: sticky; top: 0; z-index: 100;
}

.header-side { display: flex; align-items: center; gap: 6px; }
.header-side:last-child { gap: 12px; }

/* Кнопка-сетка (свежие сервисы) */
.btn-grid {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
}
.btn-grid svg { width: 18px; height: 18px; fill: var(--text-secondary); }
.btn-grid:active { transform: scale(0.92); background: var(--primary); }
.btn-grid:active svg { fill: white; }

/* Кнопка + добавить */
.btn-add {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none;
  background: var(--primary-light);
  color: var(--primary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 22px; font-weight: 300; line-height: 1;
  transition: background 0.15s, transform 0.1s;
  text-decoration: none;
}
.btn-add:active { transform: scale(0.92); background: var(--primary); color: white; }
.btn-add svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2.5; }

/* Бургер */
.btn-burger {
  width: 38px; height: 38px;
  display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px;
  cursor: pointer; background: var(--bg-secondary); border: none;
  border-radius: var(--radius-xs);
  transition: background 0.15s;
}
.btn-burger span {
  display: block; width: 18px; height: 2px;
  background: var(--text-secondary); border-radius: 2px;
  transition: all 0.22s cubic-bezier(.4,0,.2,1);
  transform-origin: center;
}
.btn-burger:active { background: var(--border); }
.btn-burger.active span:nth-child(1) { transform: translateY(7px) rotate(45deg);  background: var(--text); }
.btn-burger.active span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.btn-burger.active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--text); }

/* ── ОСНОВНОЙ КОНТЕНТ ───────────────────────────── */
.main {
  flex: 1;
  display: flex; flex-direction: column; align-items: center;
  justify-content: center;
  padding: 0 20px 0;
  gap: 20px;
  min-height: 0;
}

/* Логотип */
.logo-wrap { text-align: center; }
.logo-wrap a { display: inline-block; }
.logo {
  height: 84px; width: auto; max-width: 390px;
  object-fit: contain; display: block;
}
.logo-tagline {
  margin-top: 8px;
  font-size: 12.5px; font-weight: 600;
  color: var(--text-light);
  letter-spacing: 0.3px;
}

/* ── СТРОКА ПОИСКА ──────────────────────────────── */
.search-wrap {
  width: 100%;
  position: relative;
}

.search-box {
  display: flex; align-items: center; gap: 10px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: 99px;
  padding: 11px 16px;
  box-shadow: var(--shadow-sm);
  transition: border-color 0.2s, box-shadow 0.2s;
}
.search-box:focus-within {
  border-color: var(--primary);
  box-shadow: var(--shadow-md);
}

.search-icon svg {
  width: 18px; height: 18px;
  stroke: var(--text-light); fill: none; stroke-width: 2.5;
  display: block; flex-shrink: 0;
  transition: stroke 0.15s;
}
.search-box:focus-within .search-icon svg { stroke: var(--primary); }

.search-input {
  flex: 1; border: none; outline: none;
  font-size: 15.5px; font-weight: 500;
  color: var(--text); background: transparent;
  font-family: 'Manrope', sans-serif;
  -webkit-appearance: none; appearance: none;
  caret-color: var(--primary);
}
.search-input::placeholder { color: var(--text-light); font-weight: 500; }
.search-input::-webkit-search-decoration,
.search-input::-webkit-search-cancel-button { display: none; }

.search-clear {
  width: 26px; height: 26px;
  border-radius: 50%; border: none;
  background: var(--border);
  cursor: pointer;
  display: none; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s, transform 0.1s;
  padding: 0;
}
.search-clear.visible { display: flex; }
.search-clear svg { width: 12px; height: 12px; stroke: var(--text-secondary); fill: none; stroke-width: 3; }
.search-clear:active { background: var(--primary); transform: scale(0.9); }
.search-clear:active svg { stroke: white; }

/* Результаты поиска */
.search-results {
  position: absolute; top: calc(100% + 8px); left: 0; right: 0;
  background: var(--bg);
  border-radius: var(--radius);
  box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
  border: 1px solid var(--border-light);
  max-height: 380px; overflow-y: auto;
  z-index: 50; display: none;
  animation: dropIn 0.18s ease;
}
@keyframes dropIn {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}
.search-results.visible { display: block; }

.search-section-label {
  font-size: 11px; font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.5px;
  padding: 12px 16px 6px;
}

.search-result-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 16px;
  cursor: pointer;
  transition: background 0.12s;
  border-bottom: 1px solid var(--border-light);
}
.search-result-item:last-of-type { border-bottom: none; }
.search-result-item:active { background: var(--bg-secondary); }

.search-result-icon {
  width: 32px; height: 32px;
  border-radius: var(--radius-xs);
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.search-result-icon svg { width: 15px; height: 15px; stroke: var(--text-light); fill: none; stroke-width: 2; }
.search-result-icon.history { background: var(--bg-secondary); }
.search-result-icon.suggest { background: var(--primary-light); }
.search-result-icon.suggest svg { stroke: var(--primary); }

.search-result-text { flex: 1; font-size: 14.5px; font-weight: 500; color: var(--text); }
.search-result-text .highlight { color: var(--primary); font-weight: 700; }

.search-history-clear {
  font-size: 13px; font-weight: 600;
  color: var(--primary);
  padding: 12px 16px;
  cursor: pointer; text-align: center;
  border-top: 1px solid var(--border-light);
  transition: background 0.12s;
}
.search-history-clear:active { background: var(--bg-secondary); }

/* ── SEARCH OVERLAY (Google-style) ─────────────── */
.search-overlay {
  position: fixed; inset: 0;
  background: var(--bg);
  z-index: 300;
  display: flex; flex-direction: column;
  transform: translateY(-100%);
  transition: transform 0.28s cubic-bezier(.4,0,.2,1);
}
.search-overlay.active {
  transform: translateY(0);
}

/* Шапка оверлея */
.so-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 14px;
  height: 58px;
  flex-shrink: 0;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg);
}

.so-back {
  width: 38px; height: 38px;
  border-radius: var(--radius-xs);
  border: none; background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s, transform 0.1s;
}
.so-back:active { transform: scale(0.92); background: var(--border); }
.so-back svg { width: 18px; height: 18px; stroke: var(--text); fill: none; stroke-width: 2.2; }

/* Поле поиска в оверлее */
.so-input-wrap {
  flex: 1;
  display: flex; align-items: center; gap: 10px;
  background: var(--bg-secondary);
  border: 1.5px solid var(--border);
  border-radius: 99px;
  padding: 9px 14px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.so-input-wrap:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59,108,244,0.1);
}
.so-input-wrap svg { width: 17px; height: 17px; stroke: var(--text-light); fill: none; stroke-width: 2.5; flex-shrink: 0; }

.so-input {
  flex: 1; border: none; outline: none;
  font-size: 16px; font-weight: 500;
  color: var(--text); background: transparent;
  font-family: 'Manrope', sans-serif;
  -webkit-appearance: none; appearance: none;
  caret-color: var(--primary);
}
.so-input::placeholder { color: var(--text-light); }
.so-input::-webkit-search-decoration,
.so-input::-webkit-search-cancel-button { display: none; }

.so-clear {
  width: 24px; height: 24px;
  border-radius: 50%; border: none;
  background: var(--border);
  display: none; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background 0.15s;
  padding: 0;
}
.so-clear.visible { display: flex; }
.so-clear svg { width: 11px; height: 11px; stroke: var(--text-secondary); fill: none; stroke-width: 3; }
.so-clear:active { background: var(--primary); }
.so-clear:active svg { stroke: white; }

/* Контент оверлея (история + подсказки) */
.so-content {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

/* Секция */
.so-section-label {
  font-size: 11px; font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase; letter-spacing: 0.6px;
  padding: 16px 18px 8px;
}

/* Строка результата */
.so-item {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 18px;
  cursor: pointer;
  transition: background 0.12s;
  border-bottom: 1px solid var(--border-light);
}
.so-item:last-of-type { border-bottom: none; }
.so-item:active { background: var(--bg-secondary); }


.so-item-body { display:flex; flex-direction:column; gap:2px; flex:1; min-width:0; }
.so-item-text { font-size:14px; font-weight:500; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.so-item-sub  { font-size:12px; color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.so-item-icon {
  width: 36px; height: 36px;
  border-radius: var(--radius-xs);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.so-item-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
.so-item-icon.history { background: var(--bg-secondary); color: var(--text-light); }
.so-item-icon.suggest { background: var(--primary-light); color: var(--primary); }
.so-item-icon.tag     { background: var(--bg-secondary); color: var(--text-secondary); font-size: 17px; }

.so-item-text {
  flex: 1;
  font-size: 15px; font-weight: 500; color: var(--text);
}
.so-item-text .hl { color: var(--primary); font-weight: 700; }

.so-item-arrow {
  width: 16px; height: 16px;
  stroke: var(--text-light); fill: none; stroke-width: 2.5;
  flex-shrink: 0;
}

/* Кнопка очистить историю */
.so-clear-history {
  display: flex; align-items: center; justify-content: center;
  gap: 7px;
  padding: 14px 18px;
  font-size: 13px; font-weight: 600;
  color: var(--danger);
  cursor: pointer;
  border-top: 1px solid var(--border-light);
  transition: background 0.12s;
}
.so-clear-history:active { background: var(--danger-bg); }
.so-clear-history svg { width: 14px; height: 14px; stroke: var(--danger); fill: none; stroke-width: 2; }

/* Популярные теги внутри оверлея */
.so-popular {
  padding: 4px 18px 16px;
  display: flex; flex-wrap: wrap; gap: 8px;
}
.so-tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 7px 13px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 99px;
  font-size: 13px; font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.so-tag:active {
  background: var(--primary-light);
  border-color: var(--primary);
  color: var(--primary);
  transform: scale(0.96);
}

/* ── БЫСТРЫЕ ТЕГИ ───────────────────────────────── */
.quick-tags {
  display: flex; gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
  width: 100%;
}
.tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 13px;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 99px;
  font-size: 13px; font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.tag:active {
  background: var(--primary-light);
  border-color: var(--primary);
  color: var(--primary);
  transform: scale(0.96);
}
.tag-emoji { font-size: 14px; }

/* ── НИЖНЯЯ ПАНЕЛЬ ──────────────────────────────── */
.bottom-bar {
  flex-shrink: 0;
  background: var(--bg);
  border-top: 1px solid var(--border-light);
}

/* Селектор страны */
.country-selector {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 18px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid var(--border-light);
  background: var(--bg);
}
.country-selector:active { background: var(--bg-secondary); }

.country-flag {
  width: 26px; height: 19px;
  border-radius: 4px; overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.14);
  flex-shrink: 0;
}
.country-flag img { width: 100%; height: 100%; object-fit: cover; display: block; }

.country-info { flex: 1; }
.country-name {
  font-size: 14px; font-weight: 600; color: var(--text);
  letter-spacing: -0.1px;
}
.country-hint {
  font-size: 11px; color: var(--text-light); font-weight: 500; margin-top: 1px;
}

.country-chevron {
  width: 18px; height: 18px; flex-shrink: 0;
}
.country-chevron svg { width: 100%; height: 100%; stroke: var(--text-light); fill: none; stroke-width: 2.5; }

/* Ссылки подвала */
.footer-links {
  display: flex; flex-wrap: wrap;
  justify-content: center; gap: 8px 16px;
  padding: 10px 20px 14px;
}
.footer-link {
  font-size: 12px; font-weight: 500;
  color: var(--text-secondary); text-decoration: none;
  transition: color 0.15s;
}
.footer-link:active { color: var(--primary); }

/* ── МОДАЛКА СТРАНЫ ─────────────────────────────── */
.country-modal {
  position: fixed; inset: 0;
  background: rgba(15,23,42,0.4);
  backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
  z-index: 500; display: none;
  align-items: flex-start; justify-content: center;
}
.country-modal.active { display: flex; }

.country-modal-sheet {
  background: var(--bg);
  width: 100%; max-width: 430px;
  max-height: 90vh;
  border-radius: 0 0 var(--radius) var(--radius);
  overflow: hidden; display: flex; flex-direction: column;
  animation: slideDown 0.25s cubic-bezier(.4,0,.2,1);
}
@keyframes slideDown {
  from { transform: translateY(-100%); }
  to   { transform: translateY(0); }
}

.cm-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.cm-title { font-size: 17px; font-weight: 800; color: var(--text); letter-spacing: -0.4px; }
.cm-close {
  width: 32px; height: 32px;
  border-radius: 50%; border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 18px; color: var(--text-light);
  transition: background 0.15s;
}
.cm-close:active { background: var(--border); }

.cm-search {
  padding: 10px 16px;
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.cm-search-input {
  width: 100%;
  padding: 9px 14px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-xs);
  font-size: 15px; font-weight: 500;
  font-family: 'Manrope', sans-serif;
  outline: none; background: var(--bg-secondary);
  transition: border-color 0.15s;
  color: var(--text);
}
.cm-search-input:focus { border-color: var(--primary); background: var(--bg); }

.cm-list { overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; padding: 4px 0; }
.cm-item {
  display: flex; align-items: center; gap: 13px;
  padding: 10px 20px; cursor: pointer;
  transition: background 0.12s;
}
.cm-item:active { background: var(--bg-secondary); }
.cm-item-flag { width: 30px; height: 22px; border-radius: 3px; overflow: hidden; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
.cm-item-flag img { width: 100%; height: 100%; object-fit: cover; }
.cm-item-name { font-size: 14.5px; font-weight: 600; color: var(--text); }
.cm-item.selected .cm-item-name { color: var(--primary); }
.cm-item-check { margin-left: auto; width: 18px; height: 18px; color: var(--primary); display: none; }
.cm-item.selected .cm-item-check { display: block; }

/* ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ─────────────────────── */
.ann-modal {
  position: fixed; inset: 0;
  background: var(--bg-secondary);
  z-index: 500;
  display: none; flex-direction: column;
}
.ann-modal.active {
  display: flex;
  animation: slideUp 0.3s cubic-bezier(.4,0,.2,1);
}
@keyframes slideUp {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0); opacity: 1; }
}

.ann-header {
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px; height: 58px;
  background: var(--bg);
  border-bottom: 1px solid var(--border-light);
  flex-shrink: 0;
}
.ann-header-icon { font-size: 20px; }
.ann-title { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; flex: 1; }
.ann-close {
  width: 34px; height: 34px;
  border-radius: 50%; border: none;
  background: var(--bg-secondary);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.15s;
}
.ann-close:active { background: var(--border); }
.ann-close svg { width: 16px; height: 16px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; }

.ann-city {
  padding: 10px 14px; background: var(--bg);
  border-bottom: 1px solid var(--border-light); flex-shrink: 0;
}
.city-select {
  width: 100%; padding: 9px 36px 9px 13px;
  border: 1.5px solid var(--border); border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 600; color: var(--text);
  background: var(--bg-secondary); outline: none; cursor: pointer;
  -webkit-appearance: none; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 11px center;
  font-family: 'Manrope', sans-serif;
  transition: border-color 0.15s;
}
.city-select:focus { border-color: var(--primary); }

.ann-content { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 14px; }

.ann-loading {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 60px 20px; gap: 14px;
}
.spinner {
  width: 32px; height: 32px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.ann-loading p { font-size: 14px; color: var(--text-secondary); font-weight: 500; }

.ann-category { margin-bottom: 20px; }
.ann-cat-title {
  font-size: 15px; font-weight: 800; color: var(--text);
  letter-spacing: -0.3px; margin-bottom: 10px; padding-left: 2px;
}
.ann-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }

.ann-item {
  background: var(--bg);
  border-radius: var(--radius-xs);
  overflow: hidden; cursor: pointer;
  transition: transform 0.15s;
  border: 1px solid var(--border-light);
  box-shadow: var(--shadow-sm);
  position: relative;
}
.ann-item:active { transform: scale(0.94); }
.ann-item img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: var(--bg-secondary); }
.ann-date {
  position: absolute; top: 5px; right: 5px;
  background: rgba(59,108,244,0.9);
  color: white; padding: 3px 7px;
  border-radius: 6px; font-size: 9.5px; font-weight: 700;
  backdrop-filter: blur(4px);
}
.ann-item-name {
  font-size: 11.5px; font-weight: 600; color: var(--text);
  padding: 7px 8px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  text-align: center;
}

.ann-add-card {
  background: var(--bg-secondary);
  border: 2px dashed var(--border);
  border-radius: var(--radius-xs);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  aspect-ratio: 1; cursor: pointer;
  transition: all 0.15s; gap: 5px; padding: 8px;
}
.ann-add-card:active { border-color: var(--primary); background: var(--primary-light); transform: scale(0.95); }
.ann-add-card svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2.5; }
.ann-add-card span { font-size: 9.5px; color: var(--text-secondary); text-align: center; line-height: 1.3; font-weight: 600; }

.ann-empty {
  display: flex; flex-direction: column; align-items: center;
  padding: 50px 20px; text-align: center; gap: 10px;
}
.ann-empty-icon {
  width: 64px; height: 64px; border-radius: 18px;
  background: var(--bg); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center; margin-bottom: 6px;
}
.ann-empty-icon svg { width: 30px; height: 30px; stroke: var(--text-light); fill: none; stroke-width: 1.5; }
.ann-empty h3 { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -0.3px; }
.ann-empty p  { font-size: 13.5px; color: var(--text-secondary); font-weight: 500; line-height: 1.6; }
.ann-add-btn {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--primary); color: white;
  padding: 11px 22px; border-radius: var(--radius-xs);
  font-size: 14px; font-weight: 700; text-decoration: none;
  cursor: pointer; border: none; font-family: inherit;
  box-shadow: 0 2px 10px rgba(59,108,244,0.28);
  transition: opacity 0.15s, transform 0.1s;
}
.ann-add-btn:active { opacity: 0.85; transform: scale(0.97); }
.ann-add-btn svg { width: 16px; height: 16px; stroke: white; fill: none; stroke-width: 2.5; }
.ann-add-free { font-size: 11.5px; color: var(--text-light); font-weight: 500; }

/* ── МЕДИА ──────────────────────────────────────── */
@media (max-height: 680px) {
  .logo { height: 64px; }
  .main { gap: 14px; }
  .logo-tagline { display: none; }
}
@media (max-height: 600px) {
  .logo { height: 52px; }
  .quick-tags { display: none; }
  .main { gap: 12px; }
}
@media (max-width: 360px) {
  .search-input { font-size: 15px; }
  .tag { font-size: 12px; padding: 5px 11px; }
}

::-webkit-scrollbar { display: none; }
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<div class="app-container">

  <!-- ── ШАПКА ── -->
  <header class="header">
    <div class="header-side">
      <button class="btn-grid" id="themeToggle" onclick="toggleTheme()" aria-label="Тёмная тема" title="Тёмная тема">
        <svg id="themeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
      </button>
      <button class="btn-grid" onclick="openAnnModal()" aria-label="Свежие сервисы">
        <svg viewBox="0 0 24 24">
          <circle cx="5"  cy="5"  r="2"/><circle cx="12" cy="5"  r="2"/><circle cx="19" cy="5"  r="2"/>
          <circle cx="5"  cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
          <circle cx="5"  cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
        </svg>
      </button>
    </div>
    <div class="header-side">
      <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
      <button class="btn-add" onclick="openSlotsModal()" aria-label="Добавить сервис">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <?php else: ?>
      <a href="<?php echo $isLoggedIn ? 'add-service.php' : 'register.php'; ?>" class="btn-add" aria-label="Добавить сервис">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </a>
      <?php endif; ?>
      <button class="btn-burger" id="menuToggle" aria-label="Меню">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- ── ОСНОВНОЙ КОНТЕНТ ── -->
  <main class="main">

    <!-- Логотип -->
    <div class="logo-wrap">
      <a href="index.php">
        <img src="logo.png" alt="Poisq" class="logo">
      </a>
      <div class="logo-tagline">русскоязычные сервисы за рубежом</div>
    </div>

    <!-- Поиск -->
    <div class="search-wrap">
      <div class="search-box">
        <div class="search-icon">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
        </div>
        <input type="text" class="search-input" id="searchInput"
               placeholder="Найти сервис…"
               autocomplete="off" autocorrect="off" autocapitalize="off"
               spellcheck="false" inputmode="search">
        <button type="button" class="search-clear" id="searchClear" aria-label="Очистить">
          <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="search-results" id="searchResults">
        <div id="recentSearchesList"></div>
        <div id="suggestionsList"></div>
        <div class="search-history-clear" id="clearHistory" style="display:none">Очистить историю</div>
      </div>
    </div>

    <!-- Быстрые теги -->
    <div class="quick-tags" id="quickTags">
      <div class="tag" onclick="setSearch('Врач')"><span class="tag-emoji">🩺</span>Врач</div>
      <div class="tag" onclick="setSearch('Юрист')"><span class="tag-emoji">⚖️</span>Юрист</div>
      <div class="tag" onclick="setSearch('Репетитор')"><span class="tag-emoji">📚</span>Репетитор</div>
      <div class="tag" onclick="setSearch('Переводчик')"><span class="tag-emoji">🌐</span>Переводчик</div>
      <div class="tag" onclick="setSearch('Психолог')"><span class="tag-emoji">🧠</span>Психолог</div>
      <div class="tag" onclick="setSearch('Красота')"><span class="tag-emoji">💅</span>Красота</div>
    </div>

  </main>

  <!-- ── НИЖНЯЯ ПАНЕЛЬ ── -->
  <div class="bottom-bar">
    <div class="country-selector" id="countrySelector">
      <div class="country-flag">
        <img src="https://flagcdn.com/w80/<?php echo htmlspecialchars($detectedCountry['code']); ?>.png"
             alt="<?php echo htmlspecialchars($detectedCountry['name']); ?>"
             id="currentFlag" loading="lazy"
             data-code="<?php echo htmlspecialchars($detectedCountry['code']); ?>">
      </div>
      <div class="country-info">
        <div class="country-name" id="currentCountryName"><?php echo htmlspecialchars($detectedCountry['name']); ?></div>
        <div class="country-hint">Нажмите чтобы сменить страну</div>
      </div>
      <div class="country-chevron">
        <svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </div>
    </div>
    <div class="footer-links">
      <a href="/useful.php" class="footer-link">Полезное</a>
      <a href="/help.php" class="footer-link">Помощь</a>
      <a href="/terms.php" class="footer-link">Условия</a>
      <a href="/about.php" class="footer-link">О нас</a>
      <a href="/contact.php" class="footer-link">Контакт</a>
    </div>
  </div>

  <!-- ── SEARCH OVERLAY ── -->
  <div class="search-overlay" id="searchOverlay">

    <!-- Шапка: кнопка назад + поле ввода -->
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
               inputmode="search">
        <button class="so-clear" id="soClear" aria-label="Очистить">
          <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Контент: история, теги, подсказки -->
    <div class="so-content" id="soContent"></div>

  </div>

<?php include __DIR__ . '/includes/menu.php'; ?>

<!-- ── МОДАЛКА СТРАНЫ ── -->
<div class="country-modal" id="countryModal" onclick="onCountryOverlay(event)">
  <div class="country-modal-sheet">
    <div class="cm-header">
      <span class="cm-title">Выберите страну</span>
      <button class="cm-close" onclick="closeCountryModal()">✕</button>
    </div>
    <div class="cm-search">
      <input type="text" class="cm-search-input" id="cmSearch" placeholder="Поиск страны…">
    </div>
    <div class="cm-list" id="cmList"></div>
  </div>
</div>

<!-- ── МОДАЛКА СВЕЖИЕ СЕРВИСЫ ── -->
<div class="ann-modal" id="annModal">
  <div class="ann-header">
    <span class="ann-header-icon">📢</span>
    <span class="ann-title">Свежие сервисы</span>
    <button class="ann-close" onclick="closeAnnModal()">
      <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="ann-city">
    <select id="citySelect" class="city-select" onchange="filterByCity()">
      <option>Загрузка...</option>
    </select>
  </div>
  <div class="ann-content" id="annContent">
    <div class="ann-loading"><div class="spinner"></div><p>Загрузка сервисов...</p></div>
  </div>
</div>

</div><!-- /app-container -->

<script>
// ════════════════════════════════════════
// SEARCH OVERLAY
// ════════════════════════════════════════
const searchOverlay = document.getElementById('searchOverlay');
const soInput       = document.getElementById('soInput');
const soClear       = document.getElementById('soClear');
const soContent     = document.getElementById('soContent');

// Старое поле на главной — делаем его "кнопкой" открытия
const mainSearchBox = document.querySelector('.search-box');
mainSearchBox.addEventListener('click', openSearchOverlay);
mainSearchBox.querySelector('.search-input').addEventListener('focus', openSearchOverlay);

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

function hideKeyboard() {
  soInput.blur();
  document.activeElement && document.activeElement.blur();
  // Хак для Android — создаём временный input вне экрана
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
  soClear.classList.remove('visible');
}

// Закрытие по кнопке Назад (Android)
window.addEventListener('popstate', () => {
  if (searchOverlay.classList.contains('active')) {
    closeSearchOverlay();
  }
});

// Открываем — пушим в историю браузера чтобы кнопка Back работала
function openSearchOverlay() {
  document.querySelector('.search-input').blur();
  history.pushState({ searchOpen: true }, '');
  searchOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
  soInput.focus();
  renderSoContent('');
}

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

// ════════════════════════════════════════════════════════════════
// renderSoContent — с реальными подсказками из /api/suggest.php
// ════════════════════════════════════════════════════════════════
let suggestAbort = null;

async function renderSoContent(q) {
  const history = getHistory();
  let html = '';

  if (!q) {
    // ── Пустой запрос: только история ──
    if (history.length) {
      html += `<div class="so-section-label">Недавние</div>`;
      html += history.map(h => `
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

  // ── Есть запрос: сначала мгновенно показываем историю ──
  const matchHist = history.filter(h => h.toLowerCase().includes(q.toLowerCase()));
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

  // Показываем "ищем..." пока грузятся реальные подсказки
  html += `<div id="so-live-results">
    <div style="padding:14px 18px;color:var(--text-light);font-size:13px;font-weight:500">Ищем подсказки…</div>
  </div>`;
  soContent.innerHTML = html;

  // ── Отменяем предыдущий запрос и делаем новый ──
  if (suggestAbort) suggestAbort.abort();
  suggestAbort = new AbortController();

  const country   = localStorage.getItem('poisq_country')   || 'fr';
  const cityId    = localStorage.getItem('poisq_city_id')   || '';
  const citySlug  = localStorage.getItem('poisq_city_slug') || '';

  let suggestUrl = `/api/suggest.php?q=${encodeURIComponent(q)}&country=${encodeURIComponent(country)}`;
  if (cityId)   suggestUrl += `&city_id=${encodeURIComponent(cityId)}`;
  if (citySlug) suggestUrl += `&city_slug=${encodeURIComponent(citySlug)}`;

  try {
    const resp = await fetch(suggestUrl, { signal: suggestAbort.signal });
    const suggestions = await resp.json();

    const liveDiv = document.getElementById('so-live-results');
    if (!liveDiv) return; // пользователь уже закрыл оверлей

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
      // Сохраняем данные в глобальный массив чтобы избежать проблем с кавычками в onclick
      window._suggest = window._suggest || [];
      window._suggest[i] = { q: s.q, url: url, country: s.country, city_slug: s.city_slug, service_id: s.service_id };
      const iconHtml = s.photo
        ? `<div class="so-item-icon suggest" style="background:none;padding:0;overflow:hidden;border-radius:8px;"><img src="${s.photo}" style="width:36px;height:36px;object-fit:cover;border-radius:8px;" onerror="this.style.display='none'"></div>`
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
    if (e.name === 'AbortError') return; // запрос отменён — нормально
    const liveDiv = document.getElementById('so-live-results');
    if (liveDiv) liveDiv.innerHTML = '';
  }
}

// Переход по подсказке из API
function soGoTo(i) {
  const s = (window._suggest || [])[i];
  if (!s) return;
  if (s.q) saveHistory(sanitizeQuery(s.q));
  closeSearchOverlay();
  // Небольшая задержка чтобы Android успел скрыть клавиатуру
  setTimeout(function() {
    if (s.q) {
      const clean = sanitizeQuery(s.q);
      const c = s.country || localStorage.getItem('poisq_country') || 'fr';
      const citySlug = s.city_slug || '';
      const pin = s.service_id ? '?pin=' + s.service_id : '';
      if (citySlug) {
        window.location.href = '/' + c + '/' + citySlug + '/' + encodeURIComponent(clean) + pin;
      } else {
        window.location.href = '/' + c + '/' + encodeURIComponent(clean) + pin;
      }
    } else {
      window.location.href = s.url;
    }
  }, 100);
}

// Обычный поиск (по Enter или тег без city_id)
function sanitizeQuery(q) {
  return q.replace(/\//g, ' ').replace(/\s+/g, ' ').trim();
}

function soSearch(q) {
  if (!q.trim()) return;
  const clean = sanitizeQuery(q);
  saveHistory(clean);
  closeSearchOverlay();
  const country = localStorage.getItem('poisq_country') || 'fr';
  window.location.href = '/' + country + '/' + encodeURIComponent(clean);
}

// Ввод текста в оверлее — задержка 300ms чтобы не спамить API
let soTimer = null;
soInput.addEventListener('input', () => {
  const q = soInput.value.trim();
  soClear.classList.toggle('visible', q.length > 0);
  clearTimeout(soTimer);
  soTimer = setTimeout(() => renderSoContent(q), 300);
});

soInput.addEventListener('keydown', e => {
  if (e.key === 'Enter' && soInput.value.trim()) {
    soSearch(soInput.value.trim());
  }
  if (e.key === 'Escape') closeSearchOverlay();
});

soClear.addEventListener('click', () => {
  soInput.value = '';
  soClear.classList.remove('visible');
  soInput.focus();
  renderSoContent('');
});

// Теги на главной — открывают оверлей с уже введённым текстом
function setSearch(val) {
  openSearchOverlay();
  setTimeout(() => {
    soInput.value = val;
    soClear.classList.add('visible');
    renderSoContent(val);
  }, 160);
}

// ════════════════════════════════════════
// ВЫБОР СТРАНЫ — из БД через PHP
// ════════════════════════════════════════
const countries = <?php echo json_encode($_jsCountries, JSON_UNESCAPED_UNICODE); ?>;
/* legacy countries block replaced */
const _legacy = [
  {code:'af',name:'Афганистан'},{code:'al',name:'Албания'},{code:'dz',name:'Алжир'},{code:'ar',name:'Аргентина'},
  {code:'am',name:'Армения'},{code:'au',name:'Австралия'},{code:'at',name:'Австрия'},{code:'az',name:'Азербайджан'},
  {code:'bs',name:'Багамы'},{code:'bh',name:'Бахрейн'},{code:'bd',name:'Бангладеш'},{code:'by',name:'Беларусь'},
  {code:'be',name:'Бельгия'},{code:'bo',name:'Боливия'},{code:'ba',name:'Босния и Герцеговина'},{code:'br',name:'Бразилия'},
  {code:'bg',name:'Болгария'},{code:'kh',name:'Камбоджа'},{code:'ca',name:'Канада'},{code:'cl',name:'Чили'},
  {code:'cn',name:'Китай'},{code:'co',name:'Колумбия'},{code:'cr',name:'Коста-Рика'},{code:'hr',name:'Хорватия'},
  {code:'cu',name:'Куба'},{code:'cy',name:'Кипр'},{code:'cz',name:'Чехия'},{code:'dk',name:'Дания'},
  {code:'do',name:'Доминикана'},{code:'ec',name:'Эквадор'},{code:'eg',name:'Египет'},{code:'ee',name:'Эстония'},
  {code:'fi',name:'Финляндия'},{code:'fr',name:'Франция'},{code:'ge',name:'Грузия'},{code:'de',name:'Германия'},
  {code:'gr',name:'Греция'},{code:'hk',name:'Гонконг'},{code:'hu',name:'Венгрия'},{code:'is',name:'Исландия'},
  {code:'in',name:'Индия'},{code:'id',name:'Индонезия'},{code:'ir',name:'Иран'},{code:'iq',name:'Ирак'},
  {code:'ie',name:'Ирландия'},{code:'il',name:'Израиль'},{code:'it',name:'Италия'},{code:'jp',name:'Япония'},
  {code:'jo',name:'Иордания'},{code:'kz',name:'Казахстан'},{code:'ke',name:'Кения'},{code:'kw',name:'Кувейт'},
  {code:'kg',name:'Кыргызстан'},{code:'lv',name:'Латвия'},{code:'lb',name:'Ливан'},{code:'lt',name:'Литва'},
  {code:'lu',name:'Люксембург'},{code:'my',name:'Малайзия'},{code:'mv',name:'Мальдивы'},{code:'mt',name:'Мальта'},
  {code:'mx',name:'Мексика'},{code:'md',name:'Молдова'},{code:'mc',name:'Монако'},{code:'mn',name:'Монголия'},
  {code:'me',name:'Черногория'},{code:'ma',name:'Марокко'},{code:'np',name:'Непал'},{code:'nl',name:'Нидерланды'},
  {code:'nz',name:'Новая Зеландия'},{code:'ng',name:'Нигерия'},{code:'mk',name:'Северная Македония'},{code:'no',name:'Норвегия'},
  {code:'om',name:'Оман'},{code:'pk',name:'Пакистан'},{code:'pa',name:'Панама'},{code:'py',name:'Парагвай'},
  {code:'pe',name:'Перу'},{code:'ph',name:'Филиппины'},{code:'pl',name:'Польша'},{code:'pt',name:'Португалия'},
  {code:'qa',name:'Катар'},{code:'ro',name:'Румыния'},{code:'ru',name:'Россия'},{code:'sa',name:'Саудовская Аравия'},
  {code:'rs',name:'Сербия'},{code:'sg',name:'Сингапур'},{code:'sk',name:'Словакия'},{code:'si',name:'Словения'},
  {code:'za',name:'ЮАР'},{code:'kr',name:'Южная Корея'},{code:'es',name:'Испания'},{code:'lk',name:'Шри-Ланка'},
  {code:'se',name:'Швеция'},{code:'ch',name:'Швейцария'},{code:'sy',name:'Сирия'},{code:'tw',name:'Тайвань'},
  {code:'tj',name:'Таджикистан'},{code:'tz',name:'Танзания'},{code:'th',name:'Таиланд'},{code:'tr',name:'Турция'},
  {code:'tm',name:'Туркменистан'},{code:'ua',name:'Украина'},{code:'ae',name:'ОАЭ'},{code:'gb',name:'Великобритания'},
  {code:'us',name:'США'},{code:'uy',name:'Уругвай'},{code:'uz',name:'Узбекистан'},{code:'ve',name:'Венесуэла'},
  {code:'vn',name:'Вьетнам'},{code:'xk',name:'Косово'}
]; // end _legacy

const currentFlag        = document.getElementById('currentFlag');
const currentCountryName = document.getElementById('currentCountryName');
let currentCode = localStorage.getItem('poisq_country') || currentFlag.dataset.code || 'fr';
let currentName = localStorage.getItem('poisq_country_name') || currentCountryName.textContent;

// Применяем сохранённую страну
if (localStorage.getItem('poisq_country')) {
  currentFlag.src = 'https://flagcdn.com/w80/' + currentCode + '.png';
  currentCountryName.textContent = currentName;
}

function renderCountryList(list) {
  const cmList = document.getElementById('cmList');
  if (!list.length) {
    cmList.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-light);font-size:14px;font-weight:500">Страны не найдены</div>';
    return;
  }
  cmList.innerHTML = list.map(c => `
    <div class="cm-item ${c.code === currentCode ? 'selected' : ''}" data-code="${c.code}" data-name="${c.name}">
      <div class="cm-item-flag"><img src="https://flagcdn.com/w80/${c.code}.png" alt="${c.name}" loading="lazy"></div>
      <span class="cm-item-name">${c.name}</span>
      <svg class="cm-item-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>`).join('');
  cmList.querySelectorAll('.cm-item').forEach(el => {
    el.addEventListener('click', () => selectCountry(el.dataset.code, el.dataset.name));
  });
}

function selectCountry(code, name) {
  currentCode = code; currentName = name;
  localStorage.setItem('poisq_country', code);
  localStorage.setItem('poisq_country_name', name);
  localStorage.setItem('poisq_country_manual', '1');
  localStorage.removeItem('poisq_city_id');
  localStorage.removeItem('poisq_city_slug');
  localStorage.removeItem('poisq_city_name');
  currentFlag.src = 'https://flagcdn.com/w80/' + code + '.png';
  currentFlag.dataset.code = code;
  currentCountryName.textContent = name;
  closeCountryModal();
}

function openCountryModal() {
  document.getElementById('countryModal').classList.add('active');
  document.body.style.overflow = 'hidden';
  document.getElementById('cmSearch').value = '';
  renderCountryList(countries);
  setTimeout(() => document.getElementById('cmSearch').focus(), 300);
}
function closeCountryModal() {
  document.getElementById('countryModal').classList.remove('active');
  document.body.style.overflow = '';
}
function onCountryOverlay(e) {
  if (e.target === document.getElementById('countryModal')) closeCountryModal();
}

document.getElementById('countrySelector').addEventListener('click', openCountryModal);
document.getElementById('cmSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  renderCountryList(countries.filter(c => c.name.toLowerCase().includes(q)));
});

// ════════════════════════════════════════
// ИНИЦИАЛИЗАЦИЯ ГЕОЛОКАЦИИ (СТРАНА + ГОРОД)
// Запускается один раз при загрузке страницы.
// Результат кешируется в localStorage на 24 часа.
// ════════════════════════════════════════
(async function initGeo() {
  const GEO_KEY    = 'poisq_geo_ts';    // timestamp последнего обновления
  const TTL        = 86400 * 1000;      // 24 часа в мс
  const lastUpdate = parseInt(localStorage.getItem(GEO_KEY) || '0', 10);

  // Если данные свежие — ничего не делаем
  if (Date.now() - lastUpdate < TTL &&
      localStorage.getItem('poisq_country') &&
      localStorage.getItem('poisq_city_id')) {
    return;
  }

  try {
    const resp = await fetch('/api/get-user-country.php');
    if (!resp.ok) return;
    const data = await resp.json();

    if (data.country_code) {
      // Страна — только если юзер не менял её вручную
      const manualCountry = localStorage.getItem('poisq_country_manual');
      if (!manualCountry) {
        const code = data.country_code;
        localStorage.setItem('poisq_country', code);
        // Обновляем флаг и название в шапке
        const flagEl = document.getElementById('currentFlag');
        const nameEl = document.getElementById('currentCountryName');
        if (flagEl) { flagEl.src = 'https://flagcdn.com/w80/' + code + '.png'; flagEl.dataset.code = code; }
        // Обновляем currentCode чтобы поиск работал с правильной страной
        currentCode = code;
      }
    }

    // Город — всегда сохраняем из геолокации
    if (data.city_id) {
      localStorage.setItem('poisq_city_id',   data.city_id);
      localStorage.setItem('poisq_city_slug', data.city_slug || '');
      localStorage.setItem('poisq_city_name', data.city_name || '');
    } else {
      localStorage.removeItem('poisq_city_id');
      localStorage.removeItem('poisq_city_slug');
      localStorage.removeItem('poisq_city_name');
    }

    localStorage.setItem(GEO_KEY, Date.now().toString());

  } catch (e) {
    // Молча игнорируем — не критично
  }
})();



// ════════════════════════════════════════
// СВЕЖИЕ СЕРВИСЫ (РУПОР)
// ════════════════════════════════════════
let annCityId = null;

async function openAnnModal() {
  const modal   = document.getElementById('annModal');
  const content = document.getElementById('annContent');
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;

  try {
    const cr  = await fetch('api/get-user-country.php');
    const cd  = await cr.json();
    const cc  = cd.country_code || 'fr';

    const cir = await fetch(`api/get-cities.php?country=${cc}`);
    const cities = await cir.json();

    const sel = document.getElementById('citySelect');
    sel.innerHTML = '';
    cities.forEach(c => {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name + (c.is_capital == 1 ? ' (столица)' : '');
      sel.appendChild(o);
      if (c.is_capital == 1 && !annCityId) annCityId = c.id;
    });
    if (!annCityId && cities.length) annCityId = cities[0].id;
    if (annCityId) sel.value = annCityId;
    await loadAnnServices(annCityId);
  } catch {
    document.getElementById('annContent').innerHTML = annErr('Ошибка загрузки', 'Проверьте соединение и попробуйте снова.');
  }
}

function closeAnnModal() {
  document.getElementById('annModal').classList.remove('active');
  document.body.style.overflow = '';
}

async function filterByCity() {
  annCityId = document.getElementById('citySelect').value;
  await loadAnnServices(annCityId);
}

async function loadAnnServices(cityId) {
  const content = document.getElementById('annContent');
  content.innerHTML = `<div class="ann-loading"><div class="spinner"></div><p>Загрузка...</p></div>`;
  try {
    const r  = await fetch(`api/get-services.php?city_id=${cityId}&days=5`);
    const d  = await r.json();
    const sv = d.services || [];

    if (!sv.length) {
      content.innerHTML = `
        <div class="ann-empty">
          <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
          <h3>Пока нет сервисов</h3>
          <p>В этом городе нет новых сервисов<br>за последние 5 дней</p>
          <button class="ann-add-btn" onclick="goAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Добавить сервис
          </button>
          <span class="ann-add-free">бесплатно</span>
        </div>`;
      return;
    }

    const byCat = {};
    sv.forEach(s => { (byCat[s.category] = byCat[s.category] || []).push(s); });

    let html = '';
    for (const [cat, list] of Object.entries(byCat)) {
      html += `<div class="ann-category">
        <div class="ann-cat-title">${cat}</div>
        <div class="ann-grid">
          ${list.map(s => {
            let photo = 'https://via.placeholder.com/200?text=Poisq';
            if (s.photo) {
              try { const p = JSON.parse(s.photo); photo = Array.isArray(p) ? p[0] : s.photo; }
              catch { photo = s.photo; }
            }
            return `
            <div class="ann-item" onclick="location.href='/service.php?id=${s.id}'">
              <img src="${photo}" alt="${s.name}" loading="lazy" onerror="this.src='https://via.placeholder.com/200?text=Poisq'">
              <div class="ann-date">${fmtDate(s.created_at)}</div>
              <div class="ann-item-name">${s.name}</div>
            </div>`;
          }).join('')}
          <div class="ann-add-card" onclick="goAdd()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Добавить свой сервис</span>
          </div>
        </div>
      </div>`;
    }
    content.innerHTML = html;
  } catch {
    content.innerHTML = annErr('Ошибка', 'Не удалось загрузить данные.');
  }
}
function goAdd() {
  <?php if ($isLoggedIn && $slotsLeft <= 0): ?>
  closeAnnModal(); setTimeout(() => openSlotsModal(), 300);
  <?php elseif ($isLoggedIn): ?>
  location.href = 'add-service.php';
  <?php else: ?>
  location.href = 'register.php';
  <?php endif; ?>
}

function annErr(t, p) {
  return `<div class="ann-empty">
    <div class="ann-empty-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
    <h3>${t}</h3><p>${p}</p>
  </div>`;
}

function fmtDate(ds) {
  const d = new Date(ds), now = new Date();
  const diff = Math.floor((now - d) / 86400000);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Вчера';
  if (diff < 5)  return diff + ' дн.';
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}
</script>
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
    <a href="my-services.php" style="display:block;width:100%;padding:14px;background:#3B6CF4;color:white;border-radius:12px;text-align:center;font-size:15px;font-weight:600;text-decoration:none;margin-bottom:10px;">Перейти в Мои сервисы</a>
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