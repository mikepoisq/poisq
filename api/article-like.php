<?php
// api/article-like.php — лайк статьи (без регистрации, по IP + cookie)
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$slug = trim($_POST['slug'] ?? $_GET['slug'] ?? '');
if (!$slug) { echo json_encode(['error' => 'no slug']); exit; }
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = trim(explode(',', $ip)[0]);
$cookieKey = 'liked_' . md5($slug);

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, likes FROM articles WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $article = $stmt->fetch();
    if (!$article) { echo json_encode(['error' => 'not found']); exit; }

    // Проверяем уже лайкнул ли (по cookie)
    $alreadyLiked = isset($_COOKIE[$cookieKey]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($alreadyLiked) {
            // Убираем лайк
            $pdo->prepare("UPDATE articles SET likes = GREATEST(0, likes - 1) WHERE id = ?")->execute([$article['id']]);
            setcookie($cookieKey, '', time() - 3600, '/', '', false, true);
            $likes = max(0, $article['likes'] - 1);
            echo json_encode(['likes' => $likes, 'liked' => false]);
        } else {
            // Ставим лайк
            $pdo->prepare("UPDATE articles SET likes = likes + 1 WHERE id = ?")->execute([$article['id']]);
            setcookie($cookieKey, '1', time() + 86400 * 365, '/', '', false, true);
            $likes = $article['likes'] + 1;
            echo json_encode(['likes' => $likes, 'liked' => true]);
        }
    } else {
        // GET — просто возвращаем текущее состояние
        echo json_encode(['likes' => (int)$article['likes'], 'liked' => $alreadyLiked]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
