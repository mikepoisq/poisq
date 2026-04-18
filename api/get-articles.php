<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$action  = trim($_GET['action'] ?? '');
$country = trim($_GET['country'] ?? 'fr');
$country = preg_replace('/[^a-z]/', '', strtolower($country));

try {
    $pdo = getDbConnection();

    // ── ?action=categories — список активных рубрик из БД ──────
    if ($action === 'categories') {
        $cats = $pdo->query("
            SELECT name, slug, color, bg_color
            FROM article_categories
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'categories' => $cats], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Основной запрос — список статей ────────────────────────
    $stmt = $pdo->prepare("
        SELECT id, title, excerpt, category, country_code, slug, photo, read_time, author,
               DATE_FORMAT(created_at, '%d.%m.%Y') as date, created_at
        FROM articles
        WHERE status = 'published'
          AND (country_code = ? OR country_code = 'all')
        ORDER BY sort_order ASC, created_at DESC
    ");
    $stmt->execute([$country]);
    $articles = $stmt->fetchAll();
    echo json_encode(['success' => true, 'articles' => $articles], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'articles' => [], 'categories' => [], 'error' => 'DB error']);
}
