<?php
// api/update-avatar.php — API для обновления аватара
session_start();
header('Content-Type: application/json; charset=utf-8');

// 🔧 ПРОВЕРКА АВТОРИЗАЦИИ
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходимо авторизоваться']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

// 🔧 ЧИТАЕМ ACTION (ПОДДЕРЖКА И JSON И $_POST)
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверяем Content-Type
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        // 🔧 Читаем JSON
        $jsonInput = file_get_contents('php://input');
        $jsonData = json_decode($jsonInput, true);
        $action = $jsonData['action'] ?? '';
    } else {
        // 🔧 Читаем $_POST (для загрузки файла)
        $action = $_POST['action'] ?? '';
    }
}

try {
    $pdo = getDbConnection();
    
    // ================================================================================
    // 🔧 ЗАГРУЗКА АВАТАРА
    // ================================================================================
    if ($action === 'upload_avatar') {
        if (!isset($_FILES['avatar'])) {
            echo json_encode(['success' => false, 'error' => 'Файл не найден']);
            exit;
        }
        
        $file = $_FILES['avatar'];
        
        // Проверка ошибки загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
            exit;
        }
        
        // Проверка типа файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимый формат файла']);
            exit;
        }
        
        // Проверка размера (макс 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Файл слишком большой (макс 5MB)']);
            exit;
        }
        
        // Создаём папку для аватаров
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Генерируем уникальное имя файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;
        
        // Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
            exit;
        }
        
        // Ресайз изображения (макс 400x400)
        $imgInfo = getimagesize($targetPath);
        if ($imgInfo) {
            $width = $imgInfo[0];
            $height = $imgInfo[1];
            $maxSize = 400;

            $src = imagecreatefromstring(file_get_contents($targetPath));

            // 🔧 ИСПРАВЛЕНИЕ EXIF ORIENTATION (фото с телефона не переворачивается)
            if ($file['type'] === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($targetPath);
                $orientation = $exif['Orientation'] ?? 1;
                switch ($orientation) {
                    case 3: $src = imagerotate($src, 180, 0); break;
                    case 6: $src = imagerotate($src, -90, 0); break;
                    case 8: $src = imagerotate($src, 90, 0);  break;
                }
                // После поворота размеры могут поменяться местами
                $width  = imagesx($src);
                $height = imagesy($src);
            }

            $ratio     = min($maxSize / $width, $maxSize / $height, 1); // 1 = не увеличивать маленькие фото
            $newWidth  = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);

            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Сохраняем прозрачность для PNG
            if ($file['type'] === 'image/png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Сохраняем в том же формате
            if ($file['type'] === 'image/jpeg') {
                imagejpeg($dst, $targetPath, 85);
            } elseif ($file['type'] === 'image/png') {
                imagepng($dst, $targetPath);
            } elseif ($file['type'] === 'image/webp') {
                imagewebp($dst, $targetPath, 85);
            }

            imagedestroy($dst);
            imagedestroy($src);
        }
        
        // Путь для сохранения в БД
        $avatarUrl = '/uploads/avatars/' . $fileName;
        
        // Удаляем старый аватар если есть
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();
        
        if ($oldAvatar && file_exists(__DIR__ . '/../' . $oldAvatar)) {
            unlink(__DIR__ . '/../' . $oldAvatar);
        }
        
        // Обновляем в БД
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$avatarUrl, $userId]);
        
        // Обновляем сессию
        $_SESSION['user_avatar'] = $avatarUrl;
        
        echo json_encode([
            'success' => true,
            'avatar_url' => $avatarUrl,
            'message' => 'Фото профиля обновлено'
        ]);
        exit;
    }
    
    // ================================================================================
    // 🔧 УДАЛЕНИЕ АВАТАРА
    // ================================================================================
    if ($action === 'delete_avatar') {
        // Получаем текущий аватар
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldAvatar = $stmt->fetchColumn();
        
        // Удаляем файл
        if ($oldAvatar && file_exists(__DIR__ . '/../' . $oldAvatar)) {
            unlink(__DIR__ . '/../' . $oldAvatar);
        }
        
        // Обновляем в БД
        $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Обновляем сессию
        $_SESSION['user_avatar'] = '';
        
        echo json_encode([
            'success' => true,
            'message' => 'Фото профиля удалено'
        ]);
        exit;
    }
    
    // ================================================================================
    // 🔧 НЕИЗВЕСТНОЕ ДЕЙСТВИЕ
    // ================================================================================
    echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . $action]);
    
} catch (PDOException $e) {
    error_log('Avatar API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Avatar API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>