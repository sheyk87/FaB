<?php
/**
 * Matchmaker Service
 * Flesh and Blood TCG Sandbox
 * Concurrency-Safe Atomic 1v1 FIFO Matchmaking
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/GameService.php';

class Matchmaker {
    private PDO $pdo;
    private GameService $gameService;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->gameService = new GameService($this->pdo);
    }

    /**
     * Enter player into matchmaking queue
     */
    public function joinQueue(int $userId, int $deckId, string $format = 'Blitz'): array {
        // Ensure user is marked online
        $this->pdo->prepare("UPDATE `users` SET `is_online` = 1, `last_seen` = NOW() WHERE `id` = :uid")
            ->execute([':uid' => $userId]);

        // Insert or update queue entry
        $stmt = $this->pdo->prepare("
            INSERT INTO `matchmaking_queue` (`user_id`, `deck_id`, `format`, `status`, `queued_at`, `updated_at`)
            VALUES (:uid, :did, :fmt, 'waiting', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `deck_id` = :did2,
                `format` = :fmt2,
                `status` = 'waiting',
                `matched_game_id` = NULL,
                `queued_at` = NOW(),
                `updated_at` = NOW()
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':did' => $deckId,
            ':fmt' => $format,
            ':did2' => $deckId,
            ':fmt2' => $format
        ]);

        // Immediately attempt atomic pairing
        $this->processQueue($format);

        return $this->checkQueueStatus($userId);
    }

    /**
     * Check current queue status and keep heartbeat alive
     */
    public function checkQueueStatus(int $userId): array {
        // Update user heartbeat & queue heartbeat
        $this->pdo->prepare("UPDATE `users` SET `is_online` = 1, `last_seen` = NOW() WHERE `id` = :uid")
            ->execute([':uid' => $userId]);

        $this->pdo->prepare("UPDATE `matchmaking_queue` SET `updated_at` = NOW() WHERE `user_id` = :uid")
            ->execute([':uid' => $userId]);

        $stmt = $this->pdo->prepare("
            SELECT q.*, r.`room_code`
            FROM `matchmaking_queue` q
            LEFT JOIN `game_rooms` r ON q.`matched_game_id` = r.`id`
            WHERE q.`user_id` = :uid
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['in_queue' => false, 'status' => 'none'];
        }

        // If waiting, try processing queue in case another player just joined
        if ($row['status'] === 'waiting') {
            $this->processQueue($row['format']);
            // Re-fetch in case we just got matched
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch();
        }

        return [
            'in_queue' => true,
            'status' => $row['status'],
            'format' => $row['format'],
            'queued_at' => $row['queued_at'],
            'room_id' => $row['matched_game_id'] ? (int)$row['matched_game_id'] : null,
            'room_code' => $row['room_code'] ?? null
        ];
    }

    /**
     * Leave matchmaking queue
     */
    public function leaveQueue(int $userId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM `matchmaking_queue` WHERE `user_id` = :uid");
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atomic pairing engine:
     * - Cleans stale entries
     * - Pairs 1st + 2nd, 3rd + 4th, etc.
     * - Odd player remains waiting
     */
    public function processQueue(string $format): void {
        // 1. Clean stale queue entries (> 30s without heartbeat)
        $this->pdo->exec("
            DELETE FROM `matchmaking_queue` 
            WHERE `status` = 'waiting' 
              AND `updated_at` < DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");

        try {
            $this->pdo->beginTransaction();

            // 2. Select waiting players with exclusive row lock
            $stmt = $this->pdo->prepare("
                SELECT `id`, `user_id`, `deck_id`, `format`
                FROM `matchmaking_queue`
                WHERE `status` = 'waiting' AND `format` = :fmt
                ORDER BY `queued_at` ASC
                FOR UPDATE
            ");
            $stmt->execute([':fmt' => $format]);
            $waitingPlayers = $stmt->fetchAll();

            $total = count($waitingPlayers);
            // Pair players 2 by 2
            for ($i = 0; $i + 1 < $total; $i += 2) {
                $p1 = $waitingPlayers[$i];
                $p2 = $waitingPlayers[$i + 1];

                // Create battle room
                $room = $this->gameService->createGameRoom(
                    (int)$p1['user_id'],
                    (int)$p1['deck_id'],
                    (int)$p2['user_id'],
                    (int)$p2['deck_id'],
                    $format
                );

                $roomId = $room['id'];

                // Update queue records to matched
                $upd = $this->pdo->prepare("
                    UPDATE `matchmaking_queue`
                    SET `status` = 'matched', `matched_game_id` = :rid, `updated_at` = NOW()
                    WHERE `id` IN (:id1, :id2)
                ");
                $upd->execute([
                    ':rid' => $roomId,
                    ':id1' => $p1['id'],
                    ':id2' => $p2['id']
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Matchmaker Error: " . $e->getMessage());
        }
    }

    /**
     * Create a private custom room
     */
    public function createCustomRoom(int $userId, int $deckId, string $format = 'Blitz'): array {
        // Leave any standard queue
        $this->leaveQueue($userId);

        $roomCode = 'FAB-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $stmt = $this->pdo->prepare("
            INSERT INTO `game_rooms` (
                `room_code`, `player1_id`, `player1_deck_id`, `format`, `status`, `created_at`, `updated_at`
            ) VALUES (
                :code, :p1, :d1, :fmt, 'waiting', NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':code' => $roomCode,
            ':p1' => $userId,
            ':d1' => $deckId,
            ':fmt' => $format
        ]);

        $roomId = (int)$this->pdo->lastInsertId();
        return [
            'room_id' => $roomId,
            'room_code' => $roomCode,
            'status' => 'waiting'
        ];
    }

    /**
     * Join a custom room with code
     */
    public function joinCustomRoom(int $userId, int $deckId, string $roomCode): ?array {
        // Leave any standard queue
        $this->leaveQueue($userId);

        $stmt = $this->pdo->prepare("
            SELECT * FROM `game_rooms` 
            WHERE `room_code` = :code AND `status` = 'waiting'
            LIMIT 1
        ");
        $stmt->execute([':code' => trim(strtoupper($roomCode))]);
        $room = $stmt->fetch();

        if (!$room) return null;
        if ((int)$room['player1_id'] === $userId) {
            // Re-joining own room
            return $room;
        }

        // Initialize state with both players
        $p1Id = (int)$room['player1_id'];
        $p1DeckId = (int)$room['player1_deck_id'];
        $p2Id = $userId;
        $p2DeckId = $deckId;

        return $this->gameService->initializeActiveRoom((int)$room['id'], $p1Id, $p1DeckId, $p2Id, $p2DeckId, $room['format']);
    }

    /**
     * Cancel a custom room
     */
    public function cancelCustomRoom(int $userId, int $roomId): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM `game_rooms` 
            WHERE `id` = :rid AND `player1_id` = :uid AND `status` = 'waiting'
        ");
        $stmt->execute([':rid' => $roomId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }
}

