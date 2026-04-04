<?php
// api/reviews.php — API для отзывов
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходимо авторизоваться']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();

    // ── ОТПРАВИТЬ ОТЗЫВ ──────────────────────────────────────────
    if ($action === 'submit_review') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $rating    = (int)($_POST['rating'] ?? 0);
        $text      = trim($_POST['text'] ?? '');

        if ($serviceId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный сервис']); exit;
        }
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Выберите оценку от 1 до 5']); exit;
        }
        if (mb_strlen($text, 'UTF-8') < 20) {
            echo json_encode(['success' => false, 'error' => 'Текст отзыва минимум 20 символов']); exit;
        }

        // Проверяем что сервис существует и юзер не владелец
        $stmtSvc = $pdo->prepare("SELECT user_id, name FROM services WHERE id = ? AND status = 'approved' LIMIT 1");
        $stmtSvc->execute([$serviceId]);
        $svc = $stmtSvc->fetch(PDO::FETCH_ASSOC);

        if (!$svc) {
            echo json_encode(['success' => false, 'error' => 'Сервис не найден']); exit;
        }
        if ((int)$svc['user_id'] === $userId) {
            echo json_encode(['success' => false, 'error' => 'Нельзя оставить отзыв на свой сервис']); exit;
        }

        // Проверяем что ещё нет отзыва
        $stmtChk = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND service_id = ? LIMIT 1");
        $stmtChk->execute([$userId, $serviceId]);
        if ($stmtChk->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Вы уже оставили отзыв на этот сервис']); exit;
        }

        // Обработка фото
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['photo'];
            $maxSize = 5 * 1024 * 1024; // 5 MB
            if ($file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'error' => 'Фото не должно превышать 5 МБ']); exit;
            }
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                echo json_encode(['success' => false, 'error' => 'Допустимые форматы: JPG, PNG']); exit;
            }
            $uploadDir = '/home/mike/web/poisq.com/public_html/uploads/reviews/';
            $ext       = ($mime === 'image/png') ? 'png' : 'jpg';
            $filename  = 'review_' . $userId . '_' . $serviceId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $photoPath = '/uploads/reviews/' . $filename;
            }
        }

        // Сохраняем отзыв
        $editedUntil = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $pdo->prepare("
            INSERT INTO reviews (service_id, user_id, rating, text, photo, status, edited_until)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$serviceId, $userId, $rating, $text, $photoPath, $editedUntil]);

        // Уведомляем владельца сервиса (если он не автор отзыва)
        if ((int)$svc['user_id'] !== $userId) {
            try {
                $ownerStmt = $pdo->prepare("SELECT u.email, u.name FROM users u WHERE u.id = ? LIMIT 1");
                $ownerStmt->execute([$svc['user_id']]);
                $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
                if ($owner) {
                    sendOwnerNewReviewEmail($owner['email'], $owner['name'], $svc['name'], $rating, $text);
                }
            } catch (Exception $e) {
                error_log('Owner review notify error: ' . $e->getMessage());
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ── РЕДАКТИРОВАТЬ ОТЗЫВ ───────────────────────────────────────
    if ($action === 'edit_review') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $rating   = (int)($_POST['rating'] ?? 0);
        $text     = trim($_POST['text'] ?? '');

        if ($reviewId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный отзыв']); exit;
        }
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Выберите оценку от 1 до 5']); exit;
        }
        if (mb_strlen($text, 'UTF-8') < 20) {
            echo json_encode(['success' => false, 'error' => 'Текст отзыва минимум 20 символов']); exit;
        }

        // Проверяем что отзыв принадлежит юзеру и в окне редактирования
        $stmtRev = $pdo->prepare("
            SELECT id, photo, edited_until, service_id
            FROM reviews WHERE id = ? AND user_id = ? LIMIT 1
        ");
        $stmtRev->execute([$reviewId, $userId]);
        $review = $stmtRev->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            echo json_encode(['success' => false, 'error' => 'Отзыв не найден']); exit;
        }
        if (empty($review['edited_until']) || strtotime($review['edited_until']) <= time()) {
            echo json_encode(['success' => false, 'error' => 'Время редактирования истекло']); exit;
        }

        // Обработка нового фото
        $photoPath = $review['photo']; // сохраняем старое если нового нет
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['photo'];
            $maxSize = 5 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                echo json_encode(['success' => false, 'error' => 'Фото не должно превышать 5 МБ']); exit;
            }
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                echo json_encode(['success' => false, 'error' => 'Допустимые форматы: JPG, PNG']); exit;
            }
            $uploadDir = '/home/mike/web/poisq.com/public_html/uploads/reviews/';
            $ext       = ($mime === 'image/png') ? 'png' : 'jpg';
            $filename  = 'review_' . $userId . '_' . $review['service_id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $photoPath = '/uploads/reviews/' . $filename;
            }
        }

        // Обновляем отзыв — статус снова pending
        $pdo->prepare("
            UPDATE reviews SET rating = ?, text = ?, photo = ?, status = 'pending', updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ")->execute([$rating, $text, $photoPath, $reviewId, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ── ОТВЕТ ВЛАДЕЛЬЦА ───────────────────────────────────────────
    if ($action === 'submit_owner_reply') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $text     = trim($_POST['text'] ?? '');

        if ($reviewId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Неверный отзыв']); exit;
        }
        if (mb_strlen($text, 'UTF-8') < 5) {
            echo json_encode(['success' => false, 'error' => 'Ответ слишком короткий']); exit;
        }

        // Проверяем что отзыв одобрен и принадлежит сервису пользователя
        $stmtRev = $pdo->prepare("
            SELECT r.id, r.service_id, s.user_id AS svc_owner, s.name AS svc_name
            FROM reviews r
            JOIN services s ON s.id = r.service_id
            WHERE r.id = ? AND r.status = 'approved' LIMIT 1
        ");
        $stmtRev->execute([$reviewId]);
        $review = $stmtRev->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            echo json_encode(['success' => false, 'error' => 'Отзыв не найден']); exit;
        }
        if ((int)$review['svc_owner'] !== $userId) {
            echo json_encode(['success' => false, 'error' => 'Нет доступа']); exit;
        }

        // Проверяем что ещё нет ответа
        $stmtChk = $pdo->prepare("SELECT id FROM review_owner_replies WHERE review_id = ? LIMIT 1");
        $stmtChk->execute([$reviewId]);
        if ($stmtChk->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Вы уже ответили на этот отзыв']); exit;
        }

        $pdo->prepare("
            INSERT INTO review_owner_replies (review_id, owner_user_id, text, status)
            VALUES (?, ?, ?, 'approved')
        ")->execute([$reviewId, $userId, $text]);

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);

} catch (PDOException $e) {
    error_log('Reviews API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
}
