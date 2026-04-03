<?php
// api/service-actions.php — API для операций с сервисами
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Необходимо авторизоваться']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/meilisearch.php';

$userId    = $_SESSION['user_id'];
$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$serviceId = intval($_POST['service_id'] ?? $_GET['service_id'] ?? 0);

try {
    $pdo = getDbConnection();

    // ── TOGGLE ВКЛ/ВЫКЛ ──────────────────────────────────────
    if ($action === 'toggle_visibility') {
        $stmt = $pdo->prepare("SELECT status, is_visible FROM services WHERE id = ? AND user_id = ?");
        $stmt->execute([$serviceId, $userId]);
        $service = $stmt->fetch();

        if (!$service) { echo json_encode(['success' => false, 'error' => 'Сервис не найден']); exit; }
        if ($service['status'] !== 'approved') { echo json_encode(['success' => false, 'error' => 'Можно включать/выключать только активные сервисы']); exit; }

        $newVisibility = $service['is_visible'] ? 0 : 1;
        $pdo->prepare("UPDATE services SET is_visible = ?, updated_at = NOW() WHERE id = ? AND user_id = ?")->execute([$newVisibility, $serviceId, $userId]);

        // Синхронизация: если скрыли — удаляем из индекса, если показали — добавляем
        if ($newVisibility === 0) {
            meiliDeleteDocument($serviceId);
        } else {
            $row = $pdo->prepare("SELECT s.*, c.name AS city_name, c.name_lat AS city_slug FROM services s LEFT JOIN cities c ON s.city_id = c.id WHERE s.id = ? LIMIT 1");
            $row->execute([$serviceId]);
            $svc = $row->fetch(PDO::FETCH_ASSOC);
            if ($svc) meiliAddDocument(meiliPrepareDoc($svc));
        }

        echo json_encode(['success' => true, 'is_visible' => $newVisibility, 'message' => $newVisibility ? 'Сервис включён' : 'Сервис выключен']);
        exit;
    }

    // ── ОТПРАВИТЬ НА МОДЕРАЦИЮ ────────────────────────────────
    if ($action === 'submit_for_moderation') {
        $stmt = $pdo->prepare("SELECT status FROM services WHERE id = ? AND user_id = ?");
        $stmt->execute([$serviceId, $userId]);
        $service = $stmt->fetch();

        if (!$service) { echo json_encode(['success' => false, 'error' => 'Сервис не найден']); exit; }
        if (!in_array($service['status'], ['draft', 'rejected'])) { echo json_encode(['success' => false, 'error' => 'Нельзя отправить на модерацию']); exit; }

        $pdo->prepare("UPDATE services SET status = 'pending', moderation_comment = NULL, updated_at = NOW() WHERE id = ? AND user_id = ?")->execute([$serviceId, $userId]);

        // Отправляем уведомление администратору
        try {
            $info = $pdo->prepare("
                SELECT s.name, s.category, c.name AS city_name, u.name AS owner_name, u.email AS owner_email
                FROM services s
                LEFT JOIN cities c ON s.city_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $info->execute([$serviceId]);
            $svcInfo = $info->fetch(PDO::FETCH_ASSOC);

            if ($svcInfo) {
                require_once __DIR__ . '/../config/email.php';
                $sent = sendAdminModerationEmail(
                    $serviceId,
                    $svcInfo['name'],
                    $svcInfo['category'] ?? '—',
                    $svcInfo['city_name'] ?? '—',
                    $svcInfo['owner_name'] ?? '—',
                    $svcInfo['owner_email'] ?? '—'
                );
                error_log('Moderation email for service #' . $serviceId . ' (' . $svcInfo['name'] . '): ' . ($sent ? 'SENT' : 'FAILED'));
            }
        } catch (Exception $e) {
            error_log('Moderation notify error: ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Сервис отправлен на модерацию']);
        exit;
    }

    // ── ОТОЗВАТЬ С МОДЕРАЦИИ ──────────────────────────────────
    if ($action === 'recall_from_moderation') {
        $stmt = $pdo->prepare("SELECT status FROM services WHERE id = ? AND user_id = ?");
        $stmt->execute([$serviceId, $userId]);
        $service = $stmt->fetch();

        if (!$service) { echo json_encode(['success' => false, 'error' => 'Сервис не найден']); exit; }
        if ($service['status'] !== 'pending') { echo json_encode(['success' => false, 'error' => 'Можно отозвать только сервисы на модерации']); exit; }

        $pdo->prepare("UPDATE services SET status = 'draft', updated_at = NOW() WHERE id = ? AND user_id = ?")->execute([$serviceId, $userId]);

        echo json_encode(['success' => true, 'message' => 'Сервис отозван с модерации']);
        exit;
    }

    // ── УДАЛИТЬ СЕРВИС ────────────────────────────────────────
    if ($action === 'delete_service') {
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ? AND user_id = ?");
        $stmt->execute([$serviceId, $userId]);

        if ($stmt->rowCount() > 0) {
            // Удаляем из индекса Meilisearch
            meiliDeleteDocument($serviceId);
            echo json_encode(['success' => true, 'message' => 'Сервис удалён']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось удалить сервис']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);

} catch (PDOException $e) {
    error_log('Service Actions API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
}
