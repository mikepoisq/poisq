<?php
define('MOD_PANEL', true);
session_start();
require_once __DIR__ . '/auth.php';

$error = '';
$accessError = '';

// Проверяем ошибку доступа ДО редиректа
$hasAccessError = (($_GET['error'] ?? '') === 'noaccess');

// Уже залогинен — редиректим на dashboard, НО только если нет ошибки доступа
// (иначе будет бесконечный цикл: dashboard → noaccess → dashboard → ...)
if (isModeratorLoggedIn() && !$hasAccessError) {
    header('Location: /mod/dashboard.php');
    exit;
}

if ($hasAccessError) {
    $accessError = 'Нет доступа к этому разделу';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Введите email и пароль';
        } else {
            $result = moderatorLogin($email, $password);
            if ($result['success']) {
                header('Location: /mod/dashboard.php');
                exit;
            }
            $error = $result['error'];
        }
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Poisq Moderator</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#F5F5F7;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-box{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:40px 36px;width:100%;max-width:380px;box-shadow:0 4px 20px rgba(0,0,0,.06);}
.login-logo{text-align:center;margin-bottom:28px;}
.login-logo-mark{width:48px;height:48px;background:#10B981;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;}
.login-logo-mark svg{width:26px;height:26px;stroke:white;fill:none;stroke-width:2.5;}
.login-title{font-size:20px;font-weight:800;color:#1F2937;margin-bottom:4px;}
.login-sub{font-size:13px;color:#9CA3AF;}
label{display:block;font-size:12px;font-weight:600;color:#6B7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
input{width:100%;padding:10px 14px;border:1px solid #E5E7EB;border-radius:8px;font-size:15px;font-family:inherit;color:#1F2937;outline:none;transition:border-color .15s;}
input:focus{border-color:#10B981;box-shadow:0 0 0 3px rgba(16,185,129,.1);}
.field{margin-bottom:18px;}
.btn-submit{width:100%;padding:12px;background:#10B981;color:white;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;margin-top:4px;}
.btn-submit:hover{background:#059669;}
.error{background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:10px 14px;font-size:13px;color:#991B1B;margin-bottom:18px;}
.back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#9CA3AF;text-decoration:none;}
.back:hover{color:#6B7280;}
</style>
</head>
<body>
<div class="login-box">
    <div class="login-logo">
        <div class="login-logo-mark">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="login-title">Poisq Moderator</div>
        <div class="login-sub">Вход в панель модератора</div>
    </div>

    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($accessError && isModeratorLoggedIn()): ?>
    <div class="error"><?php echo htmlspecialchars($accessError); ?></div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px;">
        <a href="/mod/dashboard.php" style="display:block;text-align:center;padding:12px;background:#10B981;color:white;border-radius:8px;font-size:15px;font-weight:700;text-decoration:none;">← На главную</a>
        <a href="/mod/logout.php" style="display:block;text-align:center;padding:12px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Выйти</a>
    </div>
    <?php else: ?>
    <?php if ($accessError): ?>
    <div class="error"><?php echo htmlspecialchars($accessError); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   required autofocus autocomplete="email" placeholder="your@email.com">
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <button type="submit" class="btn-submit">Войти</button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
