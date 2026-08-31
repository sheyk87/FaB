<?php
/**
 * Matchmaking API Endpoint
 * Flesh and Blood TCG Sandbox
 * Concurrency-Safe Queue & Custom Rooms
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/Matchmaker.php';

$user = Security::requireAuth();
$userId = (int)$user['id'];

$action = $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$matchmaker = new Matchmaker();

// Check current queue status
if ($action === 'status') {
    $status = $matchmaker->checkQueueStatus($userId);
    Security::jsonResponse(array_merge(['success' => true], $status));
}

// Join Queue
if ($action === 'join' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $deckId = (int)($input['deck_id'] ?? 0);
    $format = in_array($input['format'] ?? '', ['Blitz', 'Classic Constructed', 'Commoner'], true) ? $input['format'] : 'Blitz';

    if ($deckId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Please select a valid deck before queuing.'], 400);
    }

    $res = $matchmaker->joinQueue($userId, $deckId, $format);
    Security::jsonResponse(array_merge(['success' => true], $res));
}

// Leave Queue
if ($action === 'leave' && $method === 'POST') {
    Security::requireCsrf();
    $matchmaker->leaveQueue($userId);
    Security::jsonResponse(['success' => true, 'message' => 'Left matchmaking queue.']);
}

// Create Custom Room
if ($action === 'create_room' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $deckId = (int)($input['deck_id'] ?? 0);
    $format = in_array($input['format'] ?? '', ['Blitz', 'Classic Constructed', 'Commoner'], true) ? $input['format'] : 'Blitz';

    if ($deckId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Please select a deck.'], 400);
    }

    $room = $matchmaker->createCustomRoom($userId, $deckId, $format);
    Security::jsonResponse(array_merge(['success' => true], $room));
}

// Join Custom Room
if ($action === 'join_room' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $roomCode = trim($input['room_code'] ?? '');
    $deckId = (int)($input['deck_id'] ?? 0);

    if (empty($roomCode) || $deckId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Room code and deck are required.'], 400);
    }

    $room = $matchmaker->joinCustomRoom($userId, $deckId, $roomCode);
    if (!$room) {
        Security::jsonResponse(['success' => false, 'error' => 'Room not found, expired, or already full.'], 404);
    }

    Security::jsonResponse(['success' => true, 'room' => $room]);
}

// Cancel Custom Room
if ($action === 'cancel_room' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();
    $roomId = (int)($input['room_id'] ?? 0);

    if ($roomId > 0) {
        $matchmaker->cancelCustomRoom($userId, $roomId);
    }
    Security::jsonResponse(['success' => true, 'message' => 'Room cancelled.']);
}

// Check status of a specific room (e.g. while host is waiting in private room lobby)
if ($action === 'room_status') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    if ($roomId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Invalid room ID'], 400);
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT `id`, `room_code`, `player1_id`, `player2_id`, `format`, `status`, `created_at`
        FROM `game_rooms`
        WHERE `id` = :rid AND (`player1_id` = :uid OR `player2_id` = :uid2)
        LIMIT 1
    ");
    $stmt->execute([':rid' => $roomId, ':uid' => $userId, ':uid2' => $userId]);
    $room = $stmt->fetch();

    if (!$room) {
        Security::jsonResponse(['success' => false, 'error' => 'Room not found'], 404);
    }

    Security::jsonResponse([
        'success' => true,
        'room_id' => (int)$room['id'],
        'room_code' => $room['room_code'],
        'status' => $room['status'],
        'has_opponent' => !empty($room['player2_id'])
    ]);
}

Security::jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);

