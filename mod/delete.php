<?php
session_start();
define('MOD_PANEL', true);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
requireModeratorAuth('services');

$pdo = getDbConnection();
$serviceId = (int)($_POST['service_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '/mod/services.php';

if ($serviceId > 0) {
    // Получаем фото для удаления
    $svc = $pdo->prepare("SELECT photo FROM services WHERE id=?");
    $svc->execute([$serviceId]);
    $svc = $svc->fetch(PDO::FETCH_ASSOC);
    if ($svc) {
        $photos = json_decode($svc['photo'] ?? '[]', true) ?: [];
        foreach ($photos as $p) {
            $file = __DIR__ . '/../' . ltrim($p, '/');
            if (file_exists($file)) unlink($file);
        }
    }
    // Удаляем из Meilisearch
    if (file_exists(__DIR__ . '/../config/meilisearch.php')) {
        require_once __DIR__ . '/../config/meilisearch.php';
        meiliDeleteDocument($serviceId);
    }
    // Удаляем из БД
    $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$serviceId]);
}

header("Location: $redirect");
exit;
