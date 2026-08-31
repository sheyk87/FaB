<?php
/**
 * Game API Endpoint
 * Flesh and Blood TCG Sandbox
 * State Synchronization & Action Execution
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/GameService.php';

$user = Security::requireAuth();
$userId = (int)$user['id'];

$action = $_GET['action'] ?? 'state';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$gameService = new GameService();

if ($action === 'state') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    if ($roomId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Invalid room ID'], 400);
    }

    $state = $gameService->getRoomState($roomId, $userId);
    if (!$state) {
        Security::jsonResponse(['success' => false, 'error' => 'Room not found'], 404);
    }

    Security::jsonResponse(array_merge(['success' => true], $state));
}

if ($action === 'action' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();

    $roomId = (int)($input['room_id'] ?? 0);
    $actionType = $input['action_type'] ?? '';
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

    if ($roomId <= 0 || empty($actionType)) {
        Security::jsonResponse(['success' => false, 'error' => 'Room ID and action type required'], 400);
    }

    try {
        $newState = $gameService->processAction($roomId, $userId, $actionType, $payload);
        Security::jsonResponse(array_merge(['success' => true], $newState));
    } catch (Exception $e) {
        Security::jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

// Fetch delta actions for real-time polling
if ($action === 'poll_actions') {
    $roomId = (int)($_GET['room_id'] ?? 0);
    $sinceSeq = (int)($_GET['since_seq'] ?? 0);

    if ($roomId <= 0) {
        Security::jsonResponse(['success' => false, 'error' => 'Invalid room ID'], 400);
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        SELECT `id`, `player_id`, `action_type`, `action_payload`, `sequence_num`, `created_at`
        FROM `game_actions`
        WHERE `room_id` = :rid AND `sequence_num` > :seq
        ORDER BY `sequence_num` ASC
        LIMIT 50
    ");
    $stmt->execute([':rid' => $roomId, ':seq' => $sinceSeq]);
    $actions = $stmt->fetchAll();

    foreach ($actions as &$act) {
        $act['action_payload'] = json_decode($act['action_payload'], true) ?: [];
    }

    Security::jsonResponse([
        'success' => true,
        'actions' => $actions
    ]);
}

Security::jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
