<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/../config/database.php";
requireAdmin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $serviceId = (int)($_POST["service_id"] ?? 0);
    if ($serviceId > 0) {
        $pdo = getDbConnection();
        // Удаляем фото с диска
        $stmt = $pdo->prepare("SELECT photo FROM services WHERE id=?");
        $stmt->execute([$serviceId]);
        $row = $stmt->fetch();
        if ($row && $row["photo"]) {
            $photos = json_decode($row["photo"], true) ?: [];
            foreach ($photos as $p) {
                $path = __DIR__ . "/../" . ltrim($p, "/");
                if (file_exists($path)) unlink($path);
            }
        }
        $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$serviceId]);
    }
}
header("Location: /panel-5588/services.php");
exit;
?>