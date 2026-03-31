<?php
require_once __DIR__ . "/auth.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login    = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";
    if (adminLogin($login, $password)) {
        header("Location: /panel-5588/dashboard.php");
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}

if (isAdminLoggedIn()) {
    header("Location: /panel-5588/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — Poisq Admin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #F5F5F7; display: flex; align-items: center;
    justify-content: center; min-height: 100vh; padding: 20px;
}
.card {
    background: #fff; border-radius: 16px; padding: 36px 28px;
    width: 100%; max-width: 380px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}
.logo { text-align: center; margin-bottom: 28px; }
.logo-text { font-size: 26px; font-weight: 800; color: #2E73D8; letter-spacing: -1px; }
.logo-sub { font-size: 13px; color: #9CA3AF; margin-top: 4px; }
.error {
    background: #FEF2F2; border: 1px solid #FECACA;
    color: #DC2626; padding: 10px 14px; border-radius: 8px;
    font-size: 14px; margin-bottom: 18px;
}
.field { margin-bottom: 16px; }
label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
input {
    width: 100%; padding: 12px 14px; border: 1.5px solid #D1D5DB;
    border-radius: 10px; font-size: 15px; outline: none;
    transition: border-color 0.15s;
}
input:focus { border-color: #2E73D8; }
button {
    width: 100%; padding: 13px; background: #2E73D8; color: #fff;
    border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
    cursor: pointer; margin-top: 8px; transition: background 0.15s;
}
button:active { background: #1A5AB8; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-text">Poisq</div>
        <div class="logo-sub">Панель управления</div>
    </div>
    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" autocomplete="username" required>
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>