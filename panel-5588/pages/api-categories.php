<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

// Return JSON errors for AJAX (not redirects)
header('Content-Type: application/json; charset=utf-8');
if (!isAdminLoggedIn() || time() - ($_SESSION['admin_last_active'] ?? 0) > 7200) {
    echo json_encode(['success' => false, 'error' => 'auth']);
    exit;
}
$_SESSION['admin_last_active'] = time();

$pdo    = getDbConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {

        case 'list':
            $cats = $pdo->query("
                SELECT ac.id, ac.name, ac.slug, ac.color, ac.bg_color, ac.sort_order, ac.is_active,
                       COUNT(a.id) AS article_count
                FROM article_categories ac
                LEFT JOIN articles a
                    ON a.category COLLATE utf8mb4_general_ci = ac.name COLLATE utf8mb4_general_ci
                GROUP BY ac.id
                ORDER BY ac.sort_order ASC, ac.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $cats], JSON_UNESCAPED_UNICODE);
            break;

        case 'create':
            $name      = trim($_POST['name']       ?? '');
            $color     = trim($_POST['color']      ?? '#1a73e8');
            $bg_color  = trim($_POST['bg_color']   ?? '#e8f0fe');
            $sort_order = (int)($_POST['sort_order'] ?? 0);

            if (!$name) { echo json_encode(['success' => false, 'error' => 'Название обязательно']); break; }

            // Generate slug
            $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
                    'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
                    'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh',
                    'щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
            $slug = mb_strtolower($name);
            $slug = strtr($slug, $map);
            $slug = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9\-]+/', '-', $slug)), '-');

            // Ensure unique slug
            $base = $slug;
            $i = 1;
            while ($pdo->prepare("SELECT id FROM article_categories WHERE slug=?")->execute([$slug]) &&
                   $pdo->query("SELECT id FROM article_categories WHERE slug='$slug'")->fetchColumn()) {
                $slug = $base . '-' . $i++;
            }

            $st = $pdo->prepare("INSERT INTO article_categories (name, slug, color, bg_color, sort_order, is_active) VALUES (?,?,?,?,?,1)");
            $st->execute([$name, $slug, $color, $bg_color, $sort_order]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $slug]);
            break;

        case 'update':
            $id        = (int)($_POST['id']         ?? 0);
            $name      = trim($_POST['name']        ?? '');
            $color     = trim($_POST['color']       ?? '#1a73e8');
            $bg_color  = trim($_POST['bg_color']    ?? '#e8f0fe');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_active = (int)($_POST['is_active']  ?? 1);

            if (!$id || !$name) { echo json_encode(['success' => false, 'error' => 'Неверные данные']); break; }

            $pdo->prepare("UPDATE article_categories SET name=?, color=?, bg_color=?, sort_order=?, is_active=? WHERE id=?")
                ->execute([$name, $color, $bg_color, $sort_order, $is_active, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'error' => 'Нет ID']); break; }

            $stName = $pdo->prepare("SELECT name FROM article_categories WHERE id=?");
            $stName->execute([$id]);
            $catName = $stName->fetchColumn();
            if (!$catName) { echo json_encode(['success' => false, 'error' => 'Рубрика не найдена']); break; }

            $stCount = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE category=?");
            $stCount->execute([$catName]);
            $count = (int)$stCount->fetchColumn();
            if ($count > 0) {
                echo json_encode(['success' => false, 'error' => "Нельзя удалить: рубрика используется в $count статьях"]);
                break;
            }

            $pdo->prepare("DELETE FROM article_categories WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}
