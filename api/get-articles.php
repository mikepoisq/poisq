<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

$country = trim($_GET['country'] ?? 'fr');
$country = preg_replace('/[^a-z]/', '', strtolower($country));

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT id, title, excerpt, category, country_code, slug, photo, read_time,
               DATE_FORMAT(created_at, '%d.%m.%Y') as date
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
    echo json_encode(['success' => false, 'articles' => [], 'error' => 'DB error']);
}
