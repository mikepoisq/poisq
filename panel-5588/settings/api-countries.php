<?php
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth.php';
requireAdmin();

header('Content-Type: application/json');

$pdo    = getDbConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jok($d = []) { echo json_encode(['ok' => true] + $d); exit; }
function jerr($m)      { echo json_encode(['ok' => false, 'error' => $m]); exit; }

// ── GET: list ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $rows = $pdo->query("
        SELECT c.*, COUNT(ci.id) AS city_count
        FROM countries c
        LEFT JOIN cities ci ON ci.country_code = c.code
        GROUP BY c.code
        ORDER BY c.name_ru
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'countries' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('Bad request');

switch ($action) {

    // ── Create ──────────────────────────────────────────────────────────────
    case 'create': {
        $code    = preg_replace('/[^a-z]/', '', strtolower(trim($_POST['code'] ?? '')));
        $name_ru = trim($_POST['name_ru'] ?? '');
        $order   = (int)($_POST['sort_order'] ?? 0);
        if (strlen($code) !== 2) jerr('Код должен быть ровно 2 латинские буквы');
        if (!$name_ru)           jerr('Название обязательно');
        try {
            $pdo->prepare("INSERT INTO countries (code, name_ru, sort_order) VALUES (?,?,?)")
                ->execute([$code, $name_ru, $order]);
            jok(['code' => $code]);
        } catch (PDOException $e) {
            jerr('Страна с таким кодом уже существует');
        }
    }

    // ── Update ──────────────────────────────────────────────────────────────
    case 'update': {
        $code    = preg_replace('/[^a-z]/', '', strtolower(trim($_POST['code'] ?? '')));
        $name_ru = trim($_POST['name_ru'] ?? '');
        $order   = (int)($_POST['sort_order'] ?? 0);
        if (!$code || !$name_ru) jerr('code и name_ru обязательны');
        $pdo->prepare("UPDATE countries SET name_ru=?, sort_order=? WHERE code=?")
            ->execute([$name_ru, $order, $code]);
        jok();
    }

    // ── Toggle active ────────────────────────────────────────────────────────
    case 'toggle': {
        $code = preg_replace('/[^a-z]/', '', strtolower(trim($_POST['code'] ?? '')));
        if (!$code) jerr('code обязателен');
        $pdo->prepare("UPDATE countries SET is_active = 1 - is_active WHERE code=?")->execute([$code]);
        $row = $pdo->prepare("SELECT is_active FROM countries WHERE code=?");
        $row->execute([$code]);
        jok(['is_active' => (int)$row->fetchColumn()]);
    }

    // ── Delete ──────────────────────────────────────────────────────────────
    case 'delete': {
        $code = preg_replace('/[^a-z]/', '', strtolower(trim($_POST['code'] ?? '')));
        if (!$code) jerr('code обязателен');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cities WHERE country_code=?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() > 0) jerr('Нельзя удалить: в этой стране есть города');
        $pdo->prepare("DELETE FROM countries WHERE code=?")->execute([$code]);
        jok();
    }

    default: jerr('Неизвестное действие');
}
