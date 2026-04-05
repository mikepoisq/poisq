<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth.php';
requireAdmin();

header('Content-Type: application/json');

$pdo    = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonOk($data = [])  { echo json_encode(['ok' => true]  + $data); exit; }
function jsonErr($msg)        { echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// ── GET: list ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $cats = $pdo->query("SELECT * FROM service_categories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $subs = $pdo->query("SELECT * FROM service_subcategories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    $subMap = [];
    foreach ($subs as $s) $subMap[$s['category_slug']][] = $s;
    foreach ($cats as &$c) $c['subcategories'] = $subMap[$c['slug']] ?? [];
    echo json_encode(['ok' => true, 'categories' => $cats]);
    exit;
}

// ── POST actions ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonErr('Bad request');

switch ($action) {

    // ── Category: add ────────────────────────────────────────────────────
    case 'cat_add': {
        $slug  = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $name  = trim($_POST['name'] ?? '');
        $icon  = mb_substr(trim($_POST['icon'] ?? ''), 0, 10);
        $order = (int)($_POST['sort_order'] ?? 0);
        if (!$slug || !$name) jsonErr('slug и name обязательны');
        try {
            $pdo->prepare("INSERT INTO service_categories (slug,name,icon,sort_order) VALUES (?,?,?,?)")
                ->execute([$slug, $name, $icon, $order]);
            jsonOk(['id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            jsonErr('Slug уже существует');
        }
    }

    // ── Category: edit ──────────────────────────────────────────────────
    case 'cat_edit': {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $icon  = mb_substr(trim($_POST['icon'] ?? ''), 0, 10);
        $order = (int)($_POST['sort_order'] ?? 0);
        $active = (int)(($_POST['is_active'] ?? 1));
        if (!$id || !$name) jsonErr('id и name обязательны');
        $pdo->prepare("UPDATE service_categories SET name=?,icon=?,sort_order=?,is_active=? WHERE id=?")
            ->execute([$name, $icon, $order, $active, $id]);
        jsonOk();
    }

    // ── Category: delete ────────────────────────────────────────────────
    case 'cat_delete': {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonErr('id обязателен');
        $cat = $pdo->prepare("SELECT slug FROM service_categories WHERE id=?");
        $cat->execute([$id]);
        $row = $cat->fetch();
        if (!$row) jsonErr('Категория не найдена');
        $cnt = (int)$pdo->prepare("SELECT COUNT(*) FROM services WHERE category=?")->execute([$row['slug']]) ? 0 : 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category=?");
        $stmt->execute([$row['slug']]);
        $cnt = (int)$stmt->fetchColumn();
        if ($cnt > 0) jsonErr("Нельзя удалить: $cnt сервисов используют эту категорию");
        $pdo->prepare("DELETE FROM service_categories WHERE id=?")->execute([$id]);
        jsonOk();
    }

    // ── Subcategory: add ────────────────────────────────────────────────
    case 'sub_add': {
        $catSlug = trim($_POST['category_slug'] ?? '');
        $name    = trim($_POST['name'] ?? '');
        $order   = (int)($_POST['sort_order'] ?? 0);
        if (!$catSlug || !$name) jsonErr('category_slug и name обязательны');
        $pdo->prepare("INSERT INTO service_subcategories (category_slug,name,sort_order) VALUES (?,?,?)")
            ->execute([$catSlug, $name, $order]);
        jsonOk(['id' => $pdo->lastInsertId()]);
    }

    // ── Subcategory: edit ───────────────────────────────────────────────
    case 'sub_edit': {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $order  = (int)($_POST['sort_order'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 1);
        if (!$id || !$name) jsonErr('id и name обязательны');
        $pdo->prepare("UPDATE service_subcategories SET name=?,sort_order=?,is_active=? WHERE id=?")
            ->execute([$name, $order, $active, $id]);
        jsonOk();
    }

    // ── Subcategory: delete ─────────────────────────────────────────────
    case 'sub_delete': {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonErr('id обязателен');
        $pdo->prepare("DELETE FROM service_subcategories WHERE id=?")->execute([$id]);
        jsonOk();
    }

    default: jsonErr('Неизвестное действие');
}
