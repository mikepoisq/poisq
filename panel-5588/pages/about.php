<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../layout.php';
requireAdmin();

$pdo = getDbConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $html = $_POST['content_html'] ?? '';
    $pdo->prepare("UPDATE pages SET content_html=?, updated_at=NOW() WHERE slug='about'")
        ->execute([$html]);
    $msg = 'success';
}

$row = $pdo->query("SELECT * FROM pages WHERE slug='about'")->fetch(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="panel">
    <div class="panel-header">
        <div class="panel-title">ℹ️ О нас</div>
        <a href="https://poisq.com/about.php" target="_blank" class="btn btn-secondary btn-sm">
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Открыть страницу
        </a>
    </div>
    <div style="padding:20px">
        <?php if ($msg === 'success'): ?>
        <div class="alert alert-success" style="margin-bottom:16px">
            <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Сохранено успешно
        </div>
        <?php endif; ?>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
            Редактируйте HTML-контент страницы. Если поле пусто — показывается статичный контент из <code>about.php</code>.
        </p>
        <form method="POST">
            <div style="margin-bottom:12px">
                <label style="font-size:12px;font-weight:700;color:var(--text-secondary);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.4px">HTML контент</label>
                <textarea name="content_html" class="form-control"
                    style="min-height:480px;font-family:monospace;font-size:13px;line-height:1.6;resize:vertical"
                    placeholder="Оставьте пустым чтобы показывать статичный контент из about.php"><?php echo htmlspecialchars($row['content_html'] ?? ''); ?></textarea>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13"/><polyline points="7 3 7 8 15 8"/></svg>
                    Сохранить
                </button>
                <?php if ($row['updated_at']): ?>
                <span style="font-size:12px;color:var(--text-light)">Обновлено: <?php echo date('d.m.Y H:i', strtotime($row['updated_at'])); ?></span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
renderLayout('О нас', $content);
