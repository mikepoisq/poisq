<?php
session_start();
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/layout.php';
requireModeratorAuth('faq');

$pdo = getDbConnection();
$msg = '';
$editItem = null;

// ── Обработка POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $question   = trim($_POST['question'] ?? '');
        $answer     = trim($_POST['answer']   ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active  = isset($_POST['is_active']) ? 1 : 0;

        if ($question && $answer) {
            if ($id > 0) {
                $pdo->prepare("UPDATE faq SET question=?, answer=?, sort_order=?, is_active=? WHERE id=?")
                    ->execute([$question, $answer, $sort_order, $is_active, $id]);
                $msg = 'updated';
            } else {
                $pdo->prepare("INSERT INTO faq (question, answer, sort_order, is_active) VALUES (?,?,?,?)")
                    ->execute([$question, $answer, $sort_order, $is_active]);
                $msg = 'created';
            }
        }
        header('Location: /mod/faq.php?msg=' . $msg);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $pdo->prepare("DELETE FROM faq WHERE id=?")->execute([$id]);
        header('Location: /mod/faq.php?msg=deleted');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $pdo->prepare("UPDATE faq SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
        header('Location: /mod/faq.php');
        exit;
    }
}

$msg = $_GET['msg'] ?? $msg;

$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editItem = $pdo->prepare("SELECT * FROM faq WHERE id=?");
    $editItem->execute([$editId]);
    $editItem = $editItem->fetch(PDO::FETCH_ASSOC);
}

$items = $pdo->query("SELECT * FROM faq ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<?php if ($msg): ?>
<div class="alert <?php echo $msg === 'deleted' ? 'alert-danger' : 'alert-success'; ?>" style="margin-bottom:16px">
    <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php echo ['created'=>'Вопрос добавлен','updated'=>'Вопрос обновлён','deleted'=>'Вопрос удалён'][$msg] ?? 'Готово'; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 420px;gap:20px;align-items:start">

<!-- Список FAQ -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">❓ FAQ — Помощь (<?php echo count($items); ?>)</div>
        <a href="https://poisq.com/help.php" target="_blank" class="btn btn-secondary btn-sm">
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Страница помощи
        </a>
    </div>
    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">❓</div>
        <div class="empty-state-title">Нет вопросов</div>
        <div class="empty-state-text">Добавьте первый вопрос с помощью формы справа</div>
    </div>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th style="width:40px">#</th>
            <th>Вопрос</th>
            <th style="width:80px">Порядок</th>
            <th style="width:80px">Статус</th>
            <th style="width:100px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
        <tr style="<?php echo $item['is_active'] ? '' : 'opacity:0.5'; ?>">
            <td style="color:var(--text-light);font-size:12px"><?php echo $item['id']; ?></td>
            <td>
                <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px;max-width:400px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?php echo htmlspecialchars($item['question']); ?>
                </div>
                <div style="font-size:12px;color:var(--text-light);max-width:400px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?php echo htmlspecialchars(mb_substr($item['answer'], 0, 80)) . (mb_strlen($item['answer']) > 80 ? '…' : ''); ?>
                </div>
            </td>
            <td style="font-size:13px;font-weight:600;color:var(--text-secondary)"><?php echo $item['sort_order']; ?></td>
            <td>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <button type="submit" class="badge <?php echo $item['is_active'] ? 'badge-green' : 'badge-gray'; ?>" style="border:none;cursor:pointer;font-family:inherit">
                        <?php echo $item['is_active'] ? 'Активен' : 'Скрыт'; ?>
                    </button>
                </form>
            </td>
            <td>
                <div style="display:flex;gap:4px">
                    <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-secondary btn-sm" title="Редактировать">✏️</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Удалить вопрос?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Удалить">🗑</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Форма добавления/редактирования -->
<div class="panel" style="position:sticky;top:80px">
    <div class="panel-header">
        <div class="panel-title"><?php echo $editItem ? '✏️ Редактировать вопрос' : '➕ Добавить вопрос'; ?></div>
        <?php if ($editItem): ?>
        <a href="/mod/faq.php" class="btn btn-secondary btn-sm">Отмена</a>
        <?php endif; ?>
    </div>
    <div style="padding:16px">
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editItem ? $editItem['id'] : 0; ?>">

            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Вопрос *</label>
                <input type="text" name="question" class="form-control" required
                    value="<?php echo htmlspecialchars($editItem['question'] ?? ''); ?>"
                    placeholder="Как найти специалиста?">
            </div>

            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Ответ *</label>
                <textarea name="answer" class="form-control" required
                    style="min-height:140px;resize:vertical"
                    placeholder="Подробный ответ на вопрос..."><?php echo htmlspecialchars($editItem['answer'] ?? ''); ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:0.4px">Порядок</label>
                    <input type="number" name="sort_order" class="form-control"
                        value="<?php echo $editItem ? $editItem['sort_order'] : count($items) * 10; ?>"
                        min="0" step="10">
                </div>
                <div style="display:flex;align-items:flex-end;padding-bottom:6px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:500">
                        <input type="checkbox" name="is_active" value="1"
                            <?php echo (!$editItem || $editItem['is_active']) ? 'checked' : ''; ?>>
                        Активен
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%">
                <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg>
                <?php echo $editItem ? 'Сохранить изменения' : 'Добавить вопрос'; ?>
            </button>
        </form>
    </div>
</div>

</div>
<?php
$content = ob_get_clean();
renderModLayout('FAQ — Помощь', $content);
?>
