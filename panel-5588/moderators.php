<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/layout.php";
requireAdmin(); // Только супер-админ

$pdo = getDbConnection();

$success = '';
$error   = '';

$allPermissions = [
    'moderation'      => 'Модерация сервисов',
    'services'        => 'Список сервисов',
    'services_create' => 'Создание новых сервисов',
    'cities'          => 'Города и страны',
    'articles'        => 'Статьи (Полезное)',
    'faq'             => 'FAQ (Помощь)',
    'my_stats'        => 'Моя статистика',
    'analytics'       => 'Аналитика',
];

// ── POST обработка ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ошибка CSRF. Обновите страницу';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $modId    = (int)($_POST['mod_id'] ?? 0);
            $name     = trim($_POST['name']     ?? '');
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password']      ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $perms    = $_POST['permissions']   ?? [];
            $perms    = array_values(array_intersect(array_keys($allPermissions), $perms));

            if (empty($name) || empty($email)) {
                $error = 'Имя и email обязательны';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Некорректный email';
            } else {
                $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);
                if ($modId > 0) {
                    // Редактирование
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $error = 'Пароль должен быть не менее 6 символов';
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $pdo->prepare("UPDATE moderators SET name=?,email=?,password_hash=?,is_active=?,permissions=? WHERE id=?")
                                ->execute([$name, $email, $hash, $isActive, $permsJson, $modId]);
                            $success = 'Модератор обновлён';
                        }
                    } else {
                        $pdo->prepare("UPDATE moderators SET name=?,email=?,is_active=?,permissions=? WHERE id=?")
                            ->execute([$name, $email, $isActive, $permsJson, $modId]);
                        $success = 'Модератор обновлён';
                    }
                } else {
                    // Создание
                    if (empty($password)) {
                        $error = 'Пароль обязателен при создании';
                    } elseif (strlen($password) < 6) {
                        $error = 'Пароль должен быть не менее 6 символов';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        try {
                            $pdo->prepare("INSERT INTO moderators (name, email, password_hash, is_active, permissions, created_by) VALUES (?,?,?,?,?,?)")
                                ->execute([$name, $email, $hash, $isActive, $permsJson, SUPER_ADMIN_ID]);
                            $success = 'Модератор добавлен';
                        } catch (PDOException $e) {
                            $error = 'Email уже занят';
                        }
                    }
                }
            }
        } elseif ($action === 'deactivate') {
            $modId = (int)($_POST['mod_id'] ?? 0);
            if ($modId > 0) {
                $pdo->prepare("UPDATE moderators SET is_active=0 WHERE id=?")->execute([$modId]);
                $success = 'Модератор деактивирован';
            }
        } elseif ($action === 'activate') {
            $modId = (int)($_POST['mod_id'] ?? 0);
            if ($modId > 0) {
                $pdo->prepare("UPDATE moderators SET is_active=1 WHERE id=?")->execute([$modId]);
                $success = 'Модератор активирован';
            }
        }
    }
}

// ── Данные ──
$moderators = $pdo->query("
    SELECT m.*,
        (SELECT logged_in_at FROM moderator_sessions WHERE moderator_id=m.id ORDER BY logged_in_at DESC LIMIT 1) as last_login,
        (SELECT COUNT(*) FROM moderator_stats WHERE moderator_id=m.id AND action='created'   AND stat_date >= DATE_SUB(CURDATE(),INTERVAL 29 DAY)) as month_created,
        (SELECT COUNT(*) FROM moderator_stats WHERE moderator_id=m.id AND action='reached'   AND stat_date >= DATE_SUB(CURDATE(),INTERVAL 29 DAY)) as month_reached
    FROM moderators m
    ORDER BY m.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Редактируемый модератор
$editMod = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM moderators WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editMod = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='pending'")->fetchColumn();
$pendingReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn();
$csrf = csrfToken();

ob_start();
?>

<?php if ($success): ?>
<div class="alert alert-success"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Список модераторов -->
<div class="panel" style="margin-bottom:20px;">
    <div class="panel-header">
        <div class="panel-title">Модераторы (<?php echo count($moderators); ?>)</div>
        <a href="?add=1" class="btn btn-primary btn-sm">+ Добавить модератора</a>
    </div>
    <?php if (empty($moderators)): ?>
    <div class="empty-state"><div class="empty-state-icon">👤</div><div class="empty-state-title">Модераторов пока нет</div></div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Имя / Email</th>
                <th>Статус</th>
                <th>Разрешения</th>
                <th>Последний вход</th>
                <th>Месяц: создано</th>
                <th>Месяц: дозвонились</th>
                <th style="width:120px">Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($moderators as $m):
            $mPerms = json_decode($m['permissions'] ?? '[]', true) ?: [];
        ?>
        <tr>
            <td>
                <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($m['name']); ?></div>
                <div style="font-size:12px;color:#6B7280;"><?php echo htmlspecialchars($m['email']); ?></div>
            </td>
            <td>
                <?php if ($m['is_active']): ?>
                <span class="badge badge-green">Активен</span>
                <?php else: ?>
                <span class="badge badge-gray">Неактивен</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;max-width:200px;">
                <?php foreach ($mPerms as $p): ?>
                <span style="display:inline-block;background:var(--primary-light);color:var(--primary-dark);padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600;margin:2px;"><?php echo htmlspecialchars($allPermissions[$p] ?? $p); ?></span>
                <?php endforeach; ?>
            </td>
            <td style="font-size:13px;color:#6B7280;">
                <?php echo $m['last_login'] ? date('d.m.Y H:i', strtotime($m['last_login'])) : '—'; ?>
            </td>
            <td style="font-size:14px;font-weight:600;"><?php echo (int)$m['month_created']; ?></td>
            <td style="font-size:14px;font-weight:600;color:#10B981;"><?php echo (int)$m['month_reached']; ?></td>
            <td>
                <div style="display:flex;gap:4px;">
                    <a href="?edit=<?php echo $m['id']; ?>" class="btn btn-secondary btn-sm" title="Редактировать">✏️</a>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="mod_id" value="<?php echo $m['id']; ?>">
                        <?php if ($m['is_active']): ?>
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-danger btn-sm" title="Деактивировать" onclick="return confirm('Деактивировать модератора?')">🚫</button>
                        <?php else: ?>
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Активировать">✅</button>
                        <?php endif; ?>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Форма добавления/редактирования -->
<?php if (isset($_GET['add']) || $editMod): ?>
<?php
$fm = $editMod ?? [];
$fmPerms = json_decode($fm['permissions'] ?? '[]', true) ?: [];
$formTitle = $editMod ? 'Редактировать модератора: ' . htmlspecialchars($fm['name']) : 'Добавить модератора';
?>
<div class="panel">
    <div class="panel-header"><div class="panel-title"><?php echo $formTitle; ?></div></div>
    <div style="padding:20px;">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="mod_id" value="<?php echo (int)($fm['id'] ?? 0); ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:6px;">Имя *</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($fm['name'] ?? ''); ?>" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:6px;">Email *</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($fm['email'] ?? ''); ?>" required>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:6px;">
                    Пароль <?php echo $editMod ? '(оставьте пустым чтобы не менять)' : '*'; ?>
                </label>
                <input type="password" name="password" class="form-control" <?php echo $editMod ? '' : 'required'; ?> placeholder="Минимум 6 символов">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#6B7280;display:block;margin-bottom:6px;">Статус</label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:9px 0;">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($fm['is_active'] ?? 1) ? 'checked' : ''; ?> style="width:18px;height:18px;accent-color:var(--primary);">
                    <span style="font-size:14px;font-weight:500;">Активен</span>
                </label>
            </div>
        </div>

        <div style="margin-bottom:20px;">
            <div style="font-size:12px;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Права доступа</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
                <?php foreach ($allPermissions as $key => $label): ?>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;transition:background .15s;background:var(--bg-white);color:var(--text);"
                       onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='var(--bg-white)'">
                    <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>"
                           <?php echo in_array($key, $fmPerms) ? 'checked' : ''; ?>
                           style="width:16px;height:16px;accent-color:var(--primary);">
                    <?php echo htmlspecialchars($label); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;gap:10px;">
            <a href="/panel-5588/moderators.php" class="btn btn-secondary">← Отмена</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="white" fill="none" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Сохранить
            </button>
        </div>
    </form>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
renderLayout('Модераторы', $content, $pendingCount, 0, $pendingReviewCount);
?>
