<?php
// includes/header.php — общая шапка для всех публичных страниц
//
// Переменные (задать ДО include):
//   $pageTitle       — обязательно
//   $pageDescription — опционально
//   $canonicalUrl    — опционально
//   $pageRobots      — опционально (по умолчанию 'index, follow')
//   $ogTitle         — опционально (по умолчанию = $pageTitle)
//   $ogDescription   — опционально (по умолчанию = $pageDescription)
//   $ogImage         — опционально (по умолчанию og-image.png)
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="robots" content="<?= htmlspecialchars($pageRobots ?? 'index, follow') ?>">
<title><?= htmlspecialchars($pageTitle ?? 'Poisq') ?></title>
<?php if (!empty($pageDescription)): ?>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<?php endif; ?>
<?php if (!empty($canonicalUrl)): ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:title"       content="<?= htmlspecialchars($ogTitle ?? $pageTitle ?? 'Poisq') ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDescription ?? $pageDescription ?? '') ?>">
<meta property="og:image"       content="<?= htmlspecialchars($ogImage ?? 'https://poisq.com/og-image.png') ?>">
<meta property="og:url"         content="<?= htmlspecialchars($canonicalUrl ?? 'https://poisq.com/') ?>">
<meta property="og:type"        content="website">
<meta name="twitter:card"       content="summary_large_image">
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
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
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
  --warning:       #F59E0B;
  --warning-bg:    #FFFBEB;
  --danger:        #EF4444;
  --danger-bg:     #FEF2F2;
  --shadow-sm:     0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:     0 4px 20px rgba(59,108,244,0.12), 0 2px 8px rgba(0,0,0,0.06);
  --shadow-card:   0 2px 12px rgba(0,0,0,0.06);
  --radius:        16px;
  --radius-sm:     10px;
  --radius-xs:     8px;
}
html{-webkit-overflow-scrolling:touch;overflow-y:auto;height:auto;}
body{font-family:'Manrope',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;touch-action:manipulation;overflow-y:auto;}
.app-container{max-width:430px;margin:0 auto;background:var(--bg);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;position:relative;}
.header{display:flex;align-items:center;justify-content:space-between;padding:0 14px;height:58px;background:var(--bg);border-bottom:1px solid var(--border-light);flex-shrink:0;position:sticky;top:0;z-index:100;}
.header-side{display:flex;align-items:center;gap:6px;}
.header-title{font-size:17px;font-weight:700;color:var(--text);letter-spacing:-0.3px;}
.btn-icon-header{width:38px;height:38px;border-radius:var(--radius-xs);border:none;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background 0.15s;}
.btn-icon-header svg{width:20px;height:20px;stroke:var(--text-secondary);fill:none;stroke-width:2;}
.btn-icon-header:active{background:var(--border);}
.btn-burger{width:38px;height:38px;border-radius:var(--radius-xs);border:none;background:transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;cursor:pointer;transition:background 0.15s;}
.btn-burger span{display:block;width:20px;height:2px;background:var(--text-light);border-radius:2px;transition:all 0.25s;}
.btn-burger:active{background:var(--primary-light);}
.btn-burger.active span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.btn-burger.active span:nth-child(2){opacity:0;}
.btn-burger.active span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}
</style>
<script src="/assets/js/theme.js"></script>
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/assets/css/desktop.css">
<link rel="stylesheet" href="/assets/css/ann-modal.css">
</head>
<body>
<div class="app-container">
