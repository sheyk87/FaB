<?php
/**
 * KPIs API Endpoint
 * Flesh and Blood TCG Sandbox
 * High-performance real-time telemetry metrics
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

$pdo = Database::getConnection();

// Keep current session user marked active if logged in
if (!empty($_SESSION['user_id'])) {
    $pdo->prepare("UPDATE `users` SET `is_online` = 1, `last_seen` = NOW() WHERE `id` = :uid")
        ->execute([':uid' => $_SESSION['user_id']]);
}

// 1. Clean stale users (> 45 seconds idle marked offline)
$pdo->exec("UPDATE `users` SET `is_online` = 0 WHERE `last_seen` < DATE_SUB(NOW(), INTERVAL 45 SECOND)");

// 2. Count Online Players
$onlineCount = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `is_online` = 1")->fetchColumn();
// Ensure minimum baseline presentation
$onlineCount = max(1, $onlineCount);

// 3. Count Players in Matchmaking Queue
$queueCount = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM `matchmaking_queue` 
    WHERE `status` = 'waiting' AND `updated_at` >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
")->fetchColumn();

// 4. Count Active Battle Rooms
$activeBattles = (int)$pdo->query("SELECT COUNT(*) FROM `game_rooms` WHERE `status` = 'active'")->fetchColumn();

// 5. Total Decks and Cards
$totalDecks = (int)$pdo->query("SELECT COUNT(*) FROM `decks`")->fetchColumn();
$totalCards = (int)$pdo->query("SELECT COUNT(*) FROM `cards`")->fetchColumn();

Security::jsonResponse([
    'success' => true,
    'kpis' => [
        'online_players' => $onlineCount,
        'players_in_queue' => $queueCount,
        'active_battles' => $activeBattles,
        'total_decks' => $totalDecks,
        'total_cards' => $totalCards,
        'timestamp' => time()
    ]
]);
