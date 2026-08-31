<?php
/**
 * Chat API Endpoint
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$user = Security::requireAuth();
$userId = (int)$user['id'];
$userName = $user['display_name'] ?? $user['username'];

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Database::getConnection();

if ($action === 'list') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    $sinceId = (int)($_GET['since_id'] ?? 0);

    if ($roomId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Invalid room ID'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT `id`, `room_id`, `user_id`, `user_name`, `message`, `is_system`, `created_at`
        FROM `chat_messages`
        WHERE `room_id` = :rid AND `id` > :since
        ORDER BY `id` ASC
        LIMIT 50
    ");
    $stmt->execute([':rid' => $roomId, ':since' => $sinceId]);
    $messages = $stmt->fetchAll();

    Security::jsonResponse(['success' => true, 'messages' => $messages]);
}

if ($action === 'send' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $roomId = (int)($input['room_id'] ?? 0);
    $message = Security::sanitizeString($input['message'] ?? '', 500);

    if ($roomId <= 0 || empty($message)) {
        Security::jsonResponse(['success' => false, 'error' => 'Room ID and message are required.'], 400);
    }

    // Rate limiting: max 30 messages per minute
    if (!Security::rateLimit('chat', 30, 60)) {
        Security::jsonResponse(['success' => false, 'error' => 'You are sending messages too fast.'], 429);
    }

    $stmt = $pdo->prepare("
        INSERT INTO `chat_messages` (`room_id`, `user_id`, `user_name`, `message`, `is_system`, `created_at`)
        VALUES (:rid, :uid, :name, :msg, 0, NOW())
    ");
    $stmt->execute([
        ':rid' => $roomId,
        ':uid' => $userId,
        ':name' => $userName,
        ':msg' => $message
    ]);

    $msgId = (int)$pdo->lastInsertId();

    Security::jsonResponse([
        'success' => true,
        'message_id' => $msgId,
        'message' => [
            'id' => $msgId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'user_name' => $userName,
            'message' => $message,
            'is_system' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

Security::jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
