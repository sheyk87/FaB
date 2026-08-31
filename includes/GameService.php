<?php
/**
 * Game Service
 * Flesh and Blood TCG Sandbox
 * Server-Authoritative Sandbox Game Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/CardService.php';
require_once __DIR__ . '/DeckService.php';

class GameService {
    private PDO $pdo;
    private CardService $cardService;
    private DeckService $deckService;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->cardService = new CardService($this->pdo);
        $this->deckService = new DeckService($this->pdo);
    }

    /**
     * Create active room for 2 players
     */
    public function createGameRoom(int $p1Id, int $p1DeckId, int $p2Id, int $p2DeckId, string $format = 'Blitz'): array {
        $roomCode = 'FAB-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $stmt = $this->pdo->prepare("
            INSERT INTO `game_rooms` (
                `room_code`, `player1_id`, `player2_id`, `player1_deck_id`, `player2_deck_id`,
                `format`, `status`, `current_turn_player_id`, `turn_number`, `created_at`, `updated_at`
            ) VALUES (
                :code, :p1, :p2, :d1, :d2, :fmt, 'active', :p1_turn, 1, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':code' => $roomCode,
            ':p1' => $p1Id,
            ':p2' => $p2Id,
            ':d1' => $p1DeckId,
            ':d2' => $p2DeckId,
            ':fmt' => $format,
            ':p1_turn' => $p1Id
        ]);

        $roomId = (int)$this->pdo->lastInsertId();
        return $this->initializeActiveRoom($roomId, $p1Id, $p1DeckId, $p2Id, $p2DeckId, $format);
    }

    /**
     * Initialize room state from decks
     */
    public function initializeActiveRoom(int $roomId, int $p1Id, int $p1DeckId, int $p2Id, int $p2DeckId, string $format): array {
        $p1Deck = $this->deckService->getDeck($p1DeckId);
        $p2Deck = $this->deckService->getDeck($p2DeckId);

        $p1User = $this->getUserInfo($p1Id);
        $p2User = $this->getUserInfo($p2Id);

        $p1State = $this->buildInitialPlayerState($p1User, $p1Deck, $format);
        $p2State = $this->buildInitialPlayerState($p2User, $p2Deck, $format);

        $now = time();
        $gameState = [
            'turn' => 1,
            'active_player_id' => $p1Id,
            'priority_player_id' => $p1Id,
            'phase' => 'Action',
            'match_start_time' => $now,
            'turn_start_time' => $now,
            'turn_duration_seconds' => 75,
            'player_last_seen' => [
                (string)$p1Id => $now,
                (string)$p2Id => $now
            ],
            'combat_chain' => [
                'is_open' => false,
                'current_link_index' => 0,
                'links' => []
            ],
            'players' => [
                (string)$p1Id => $p1State,
                (string)$p2Id => $p2State
            ],
            'log' => [
                [
                    'id' => 1,
                    'text' => "Match started in {$format} format. {$p1User['display_name']} goes first.",
                    'time' => date('H:i:s'),
                    'type' => 'system'
                ]
            ]
        ];

        $stateJson = json_encode($gameState, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare("
            UPDATE `game_rooms`
            SET `player2_id` = :p2,
                `player2_deck_id` = :d2,
                `status` = 'active',
                `current_turn_player_id` = :p1,
                `game_state` = :state,
                `updated_at` = NOW()
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':p2' => $p2Id,
            ':d2' => $p2DeckId,
            ':p1' => $p1Id,
            ':state' => $stateJson,
            ':id' => $roomId
        ]);

        return [
            'id' => $roomId,
            'room_code' => $this->getRoomCode($roomId),
            'status' => 'active',
            'game_state' => $gameState
        ];
    }

    /**
     * Build starting state for a player
     */
    private function buildInitialPlayerState(array $user, ?array $deck, string $format): array {
        $heroCard = $deck['cards']['hero'] ?? null;
        $health = 20;
        $intelligence = 4;

        if ($heroCard) {
            if (!empty($heroCard['health']) && is_numeric($heroCard['health'])) {
                $health = (int)$heroCard['health'];
            } elseif ($format === 'Classic Constructed') {
                $health = 40;
            }
            if (!empty($heroCard['intelligence']) && is_numeric($heroCard['intelligence'])) {
                $intelligence = (int)$heroCard['intelligence'];
            }
        }

        // Equipment map
        $equipSlots = ['head' => null, 'chest' => null, 'arms' => null, 'legs' => null, 'weapon1' => null, 'weapon2' => null];
        $weaps = $deck['cards']['weapons'] ?? [];
        if (!empty($weaps[0])) $equipSlots['weapon1'] = $this->createCardInstance($weaps[0]);
        if (!empty($weaps[1])) $equipSlots['weapon2'] = $this->createCardInstance($weaps[1]);

        $equips = $deck['cards']['equipment'] ?? [];
        foreach ($equips as $eq) {
            $types = $eq['types'] ?? [];
            if (in_array('Head', $types, true) && !$equipSlots['head']) $equipSlots['head'] = $this->createCardInstance($eq);
            elseif (in_array('Chest', $types, true) && !$equipSlots['chest']) $equipSlots['chest'] = $this->createCardInstance($eq);
            elseif (in_array('Arms', $types, true) && !$equipSlots['arms']) $equipSlots['arms'] = $this->createCardInstance($eq);
            elseif (in_array('Legs', $types, true) && !$equipSlots['legs']) $equipSlots['legs'] = $this->createCardInstance($eq);
            elseif (in_array('Off-Hand', $types, true) && !$equipSlots['weapon2']) $equipSlots['weapon2'] = $this->createCardInstance($eq);
            else {
                // Fill first empty slot
                foreach (['head', 'chest', 'arms', 'legs'] as $slot) {
                    if ($equipSlots[$slot] === null) {
                        $equipSlots[$slot] = $this->createCardInstance($eq);
                        break;
                    }
                }
            }
        }

        // Prepare main deck instances & shuffle
        $mainCards = $deck['cards']['main_deck'] ?? [];
        $deckInstances = [];
        foreach ($mainCards as $c) {
            $deckInstances[] = $this->createCardInstance($c);
        }
        shuffle($deckInstances);

        // Draw initial hand of `intelligence` cards
        $handInstances = [];
        for ($i = 0; $i < $intelligence; $i++) {
            if (!empty($deckInstances)) {
                $handInstances[] = array_shift($deckInstances);
            }
        }

        return [
            'user_id' => $user['id'],
            'name' => $user['display_name'] ?? $user['username'],
            'avatar' => $user['avatar'] ?? 'hero_default',
            'life' => $health,
            'max_life' => $health,
            'intelligence' => $intelligence,
            'resources' => 0,
            'action_points' => 1,
            'hero' => $heroCard ? $this->createCardInstance($heroCard) : null,
            'equipment' => $equipSlots,
            'hand' => $handInstances,
            'deck' => $deckInstances,
            'deck_count' => count($deckInstances),
            'arsenal' => [],
            'pitch' => [],
            'graveyard' => [],
            'banished' => [],
            'soul' => [],
            'auras' => [],
            'tokens' => []
        ];
    }

    /**
     * Create wrapped card instance with unique ID and state trackers
     */
    private function createCardInstance(array $card): array {
        return [
            'instance_id' => 'card_' . ($card['unique_id'] ?? 'gen') . '_' . bin2hex(random_bytes(4)),
            'unique_id' => $card['unique_id'] ?? '',
            'name' => $card['name'] ?? '',
            'pitch' => isset($card['pitch']) ? (int)$card['pitch'] : null,
            'cost' => $card['cost'] ?? '0',
            'power' => $card['power'] ?? null,
            'defense' => $card['defense'] ?? null,
            'health' => $card['health'] ?? null,
            'intelligence' => $card['intelligence'] ?? null,
            'type_text' => $card['type_text'] ?? '',
            'functional_text' => $card['functional_text'] ?? '',
            'types' => $card['types'] ?? [],
            'image_url' => $card['image_url'] ?? '',
            'rarity' => $card['rarity'] ?? 'C',
            'foiling' => $card['foiling'] ?? 'S',
            'is_hero' => !empty($card['is_hero']),
            'is_weapon' => !empty($card['is_weapon']),
            'is_equipment' => !empty($card['is_equipment']),
            'is_action' => !empty($card['is_action']),
            'is_attack' => !empty($card['is_attack']),
            'is_defense_reaction' => !empty($card['is_defense_reaction']),
            'is_instant' => !empty($card['is_instant']),
            'tapped' => false,
            'face_down' => false,
            'counters' => [
                'power' => 0,
                'defense' => 0,
                'steam' => 0,
                'runechant' => 0,
                'seismic' => 0,
                'soul' => 0,
                'energy' => 0,
                'custom' => 0
            ]
        ];
    }

    /**
     * Get room state masked for client
     */
    public function getRoomState(int $roomId, int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM `game_rooms` WHERE `id` = :id");
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        if (!$room) return null;

        $rawState = json_decode($room['game_state'] ?? '{}', true) ?: [];

        // Check if user is in room
        $isP1 = ((int)$room['player1_id'] === $userId);
        $isP2 = ((int)$room['player2_id'] === $userId);

        // Update player heartbeat in room state
        if ($isP1 || $isP2) {
            $rawState['player_last_seen'][(string)$userId] = time();
        }

        // Anti-AFK / Turn Timer check: If active match and turn timed out (> 75s), auto-pass turn
        $turnDuration = (int)($rawState['turn_duration_seconds'] ?? 75);
        $turnStartTime = (int)($rawState['turn_start_time'] ?? time());
        $now = time();

        if ($room['status'] === 'active' && ($now - $turnStartTime) > $turnDuration) {
            try {
                $actPlayerId = (int)$room['current_turn_player_id'];
                $this->processAction($roomId, $actPlayerId, 'end_turn', ['reason' => 'timeout']);
                // Reload state after auto end turn
                $reloaded = $this->pdo->query("SELECT * FROM `game_rooms` WHERE `id` = {$roomId}")->fetch();
                $room = $reloaded;
                $rawState = json_decode($room['game_state'] ?? '{}', true) ?: [];
            } catch (Exception $e) {
                // Ignore if in transition
            }
        }

        // Mask private opponent information (cards in deck and cards in hand)
        $clientState = $rawState;
        if (!empty($clientState['players'])) {
            foreach ($clientState['players'] as $pId => &$pData) {
                // Update live deck count
                $pData['deck_count'] = count($pData['deck'] ?? []);

                if ((int)$pId !== $userId) {
                    // Hide cards in opponent's deck
                    $pData['deck'] = [];
                    // Hide cards in opponent's hand except count
                    $maskedHand = [];
                    foreach ($pData['hand'] ?? [] as $card) {
                        $maskedHand[] = [
                            'instance_id' => $card['instance_id'],
                            'is_hidden' => true
                        ];
                    }
                    $pData['hand'] = $maskedHand;

                    // Hide face-down arsenal cards
                    if (!empty($pData['arsenal'])) {
                        foreach ($pData['arsenal'] as &$arsCard) {
                            if (!empty($arsCard['face_down'])) {
                                $arsCard = [
                                    'instance_id' => $arsCard['instance_id'],
                                    'is_hidden' => true,
                                    'face_down' => true,
                                    'counters' => $arsCard['counters'] ?? []
                                ];
                            }
                        }
                    }
                } else {
                    // For the user himself, hide the deck array elements except count to prevent deck-peeking
                    $pData['deck'] = [];
                }
            }
        }

        $winnerInfo = null;
        if (!empty($room['winner_id'])) {
            $winUser = $this->getUserInfo((int)$room['winner_id']);
            $winnerInfo = [
                'id' => (int)$room['winner_id'],
                'name' => $winUser['display_name'] ?? $winUser['username'],
                'is_me' => ((int)$room['winner_id'] === $userId)
            ];
        }

        // Mirror room status and winner onto the game state payload
        $clientState['status'] = $room['status'];
        $clientState['winner_id'] = $room['winner_id'] ? (int)$room['winner_id'] : ($clientState['winner_id'] ?? null);
        $clientState['winner_info'] = $winnerInfo;


        return [
            'room' => [
                'id' => (int)$room['id'],
                'room_code' => $room['room_code'],
                'player1_id' => (int)$room['player1_id'],
                'player2_id' => $room['player2_id'] ? (int)$room['player2_id'] : null,
                'format' => $room['format'],
                'status' => $room['status'],
                'current_turn_player_id' => (int)$room['current_turn_player_id'],
                'turn_number' => (int)$room['turn_number'],
                'winner_id' => $room['winner_id'] ? (int)$room['winner_id'] : null,
                'winner_info' => $winnerInfo
            ],
            'server_time' => time(),
            'state' => $clientState,
            'is_my_turn' => ((int)$room['current_turn_player_id'] === $userId),
            'my_player_id' => $userId
        ];
    }

    /**
     * Process Sandbox Action on authoritative state
     */
    public function processAction(int $roomId, int $userId, string $actionType, array $payload): array {
        $stmt = $this->pdo->prepare("SELECT * FROM `game_rooms` WHERE `id` = :id FOR UPDATE");
        $this->pdo->beginTransaction();
        $stmt->execute([':id' => $roomId]);
        $room = $stmt->fetch();

        if (!$room || $room['status'] === 'finished') {
            $this->pdo->rollBack();
            throw new Exception("Room is not active.");
        }

        $p1Id = (int)$room['player1_id'];
        $p2Id = (int)$room['player2_id'];

        if ($userId !== $p1Id && $userId !== $p2Id) {
            $this->pdo->rollBack();
            throw new Exception("You are not a player in this room.");
        }

        $state = json_decode($room['game_state'] ?? '{}', true) ?: [];
        $pKey = (string)$userId;
        $opponentId = ($userId === $p1Id) ? $p2Id : $p1Id;
        $oppKey = (string)$opponentId;

        $user = $this->getUserInfo($userId);
        $userName = $user['display_name'] ?? $user['username'];
        $logText = '';

        switch ($actionType) {
            // 1. Draw Card
            case 'draw_card':
                $count = max(1, min(10, (int)($payload['count'] ?? 1)));
                $drawnNames = [];
                for ($i = 0; $i < $count; $i++) {
                    if (!empty($state['players'][$pKey]['deck'])) {
                        $card = array_shift($state['players'][$pKey]['deck']);
                        $state['players'][$pKey]['hand'][] = $card;
                        $drawnNames[] = $card['name'];
                    }
                }
                $logText = "{$userName} drew " . count($drawnNames) . " card(s).";
                break;

            // 2. Pitch Card
            case 'pitch_card':
                $instId = $payload['instance_id'] ?? '';
                $card = $this->extractCardFromZone($state['players'][$pKey]['hand'], $instId);
                if ($card) {
                    $pitchVal = isset($card['pitch']) ? (int)$card['pitch'] : 1;
                    $state['players'][$pKey]['pitch'][] = $card;
                    $state['players'][$pKey]['resources'] = ($state['players'][$pKey]['resources'] ?? 0) + $pitchVal;
                    $logText = "{$userName} pitched '{$card['name']}' (+{$pitchVal} Resource Points).";
                }
                break;

            // 3. Play Attack / Open Combat Chain Link
            case 'attack_card':
            case 'play_attack':
                if ($userId !== (int)$room['current_turn_player_id']) {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: You can only declare attacks on your own turn.");
                }

                $instId = $payload['instance_id'] ?? '';
                $fromZone = $payload['from_zone'] ?? 'hand';
                $card = null;

                if ($fromZone === 'arsenal') {
                    $card = $this->extractCardFromZone($state['players'][$pKey]['arsenal'], $instId);
                } else {
                    $card = $this->extractCardFromZone($state['players'][$pKey]['hand'], $instId);
                }

                if ($card) {
                    $card['face_down'] = false;
                    $power = !empty($card['power']) && is_numeric($card['power']) ? (int)$card['power'] : 0;

                    // Open Combat Chain Link
                    $state['combat_chain']['is_open'] = true;
                    $linkIndex = count($state['combat_chain']['links']);
                    $state['combat_chain']['links'][] = [
                        'link_number' => $linkIndex + 1,
                        'attacker_id' => $userId,
                        'attack_card' => $card,
                        'base_power' => $power,
                        'attack_power' => $power,
                        'defend_cards' => [],
                        'total_defense' => 0,
                        'attack_reactions' => [],
                        'defense_reactions' => [],
                        'is_resolved' => false
                    ];
                    $state['combat_chain']['current_link_index'] = $linkIndex;

                    // Consume 1 action point by default if player has AP
                    if (($state['players'][$pKey]['action_points'] ?? 0) > 0) {
                        $state['players'][$pKey]['action_points']--;
                    }

                    $logText = "{$userName} attacks with '{$card['name']}' for {$power} Power (Chain Link " . ($linkIndex + 1) . ").";
                }
                break;

            // 4. Defend on Combat Chain Link
            case 'defend_card':
                if ($userId === (int)$room['current_turn_player_id']) {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: You cannot defend against your own attack.");
                }
                if (empty($state['combat_chain']['is_open'])) {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: No active combat chain to defend.");
                }

                $instId = $payload['instance_id'] ?? '';
                $fromZone = $payload['from_zone'] ?? 'hand';
                $linkIdx = (int)($payload['link_index'] ?? ($state['combat_chain']['current_link_index'] ?? 0));
                $card = null;

                if ($fromZone === 'equipment') {
                    // Defend from equipment slot
                    $slot = $payload['equipment_slot'] ?? '';
                    if (!empty($state['players'][$pKey]['equipment'][$slot])) {
                        $card = $state['players'][$pKey]['equipment'][$slot];
                        // Mark equipment tapped / used
                        $state['players'][$pKey]['equipment'][$slot]['tapped'] = true;
                    }
                } else {
                    $card = $this->extractCardFromZone($state['players'][$pKey]['hand'], $instId);
                }

                if ($card && isset($state['combat_chain']['links'][$linkIdx])) {
                    $defenseVal = !empty($card['defense']) && is_numeric($card['defense']) ? (int)$card['defense'] : 0;
                    $state['combat_chain']['links'][$linkIdx]['defend_cards'][] = [
                        'card' => $card,
                        'defender_id' => $userId,
                        'from_zone' => $fromZone
                    ];
                    $state['combat_chain']['links'][$linkIdx]['total_defense'] += $defenseVal;
                    $logText = "{$userName} defends Chain Link " . ($linkIdx + 1) . " with '{$card['name']}' (+{$defenseVal} Defense).";
                }
                break;

            // 5. Arsenal Card
            case 'arsenal_card':
                if (!empty($state['players'][$pKey]['arsenal'])) {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: Arsenal is already occupied. Max 1 card in Arsenal.");
                }

                $instId = $payload['instance_id'] ?? '';
                $card = $this->extractCardFromZone($state['players'][$pKey]['hand'], $instId);
                if ($card) {
                    $card['face_down'] = true;
                    $state['players'][$pKey]['arsenal'][] = $card;
                    $logText = "{$userName} placed a card in Arsenal (face down).";
                }
                break;

            // 6. Generic Move Card (Sandbox Flexibility)
            case 'move_card':
                $instId = $payload['instance_id'] ?? '';
                $from = $payload['from_zone'] ?? 'hand';
                $to = $payload['to_zone'] ?? 'graveyard';

                $card = $this->findAndRemoveCard($state['players'][$pKey], $from, $instId);
                if ($card) {
                    $this->addCardToZone($state['players'][$pKey], $to, $card);
                    $logText = "{$userName} moved '{$card['name']}' from " . ucfirst($from) . " to " . ucfirst($to) . ".";
                }
                break;

            // 7. Tap Card
            case 'tap_card':
                $zone = $payload['zone'] ?? 'equipment';
                $instId = $payload['instance_id'] ?? '';
                $slot = $payload['slot'] ?? '';

                if ($zone === 'hero' && !empty($state['players'][$pKey]['hero'])) {
                    $state['players'][$pKey]['hero']['tapped'] = !($state['players'][$pKey]['hero']['tapped'] ?? false);
                    $status = $state['players'][$pKey]['hero']['tapped'] ? 'exhausted' : 'ready';
                    $logText = "{$userName} set Hero to {$status}.";
                } elseif ($zone === 'equipment' && !empty($state['players'][$pKey]['equipment'][$slot])) {
                    $state['players'][$pKey]['equipment'][$slot]['tapped'] = !($state['players'][$pKey]['equipment'][$slot]['tapped'] ?? false);
                    $status = $state['players'][$pKey]['equipment'][$slot]['tapped'] ? 'exhausted' : 'ready';
                    $eqName = $state['players'][$pKey]['equipment'][$slot]['name'] ?? ucfirst($slot);
                    $logText = "{$userName} set '{$eqName}' to {$status}.";
                }
                break;

            // 8. Flip Card (Face Up / Face Down)
            case 'flip_card':
                $zone = $payload['zone'] ?? 'arsenal';
                $instId = $payload['instance_id'] ?? '';
                foreach ($state['players'][$pKey][$zone] ?? [] as &$c) {
                    if (($c['instance_id'] ?? '') === $instId) {
                        $c['face_down'] = !($c['face_down'] ?? false);
                        $face = $c['face_down'] ? 'face down' : 'face up';
                        $logText = "{$userName} flipped '{$c['name']}' {$face}.";
                        break;
                    }
                }
                break;

            // 9. Modify Counters
            case 'modify_counter':
                $counterType = $payload['counter_type'] ?? 'generic';
                $delta = (int)($payload['delta'] ?? 1);
                $zone = $payload['zone'] ?? 'hero';
                $instId = $payload['instance_id'] ?? '';
                $slot = $payload['slot'] ?? '';

                if ($zone === 'hero' && !empty($state['players'][$pKey]['hero'])) {
                    $cur = $state['players'][$pKey]['hero']['counters'][$counterType] ?? 0;
                    $state['players'][$pKey]['hero']['counters'][$counterType] = max(0, $cur + $delta);
                    $logText = "{$userName} updated Hero {$counterType} counters to " . $state['players'][$pKey]['hero']['counters'][$counterType] . ".";
                } elseif ($zone === 'equipment' && !empty($state['players'][$pKey]['equipment'][$slot])) {
                    $cur = $state['players'][$pKey]['equipment'][$slot]['counters'][$counterType] ?? 0;
                    $state['players'][$pKey]['equipment'][$slot]['counters'][$counterType] = max(0, $cur + $delta);
                    $eqName = $state['players'][$pKey]['equipment'][$slot]['name'] ?? ucfirst($slot);
                    $logText = "{$userName} updated '{$eqName}' {$counterType} counters to " . $state['players'][$pKey]['equipment'][$slot]['counters'][$counterType] . ".";
                }
                break;

            // 10. Update Life
            case 'update_life':
                $delta = (int)($payload['delta'] ?? 0);
                $targetUserId = (int)($payload['target_user_id'] ?? $userId);
                $tKey = (string)$targetUserId;

                if (isset($state['players'][$tKey])) {
                    $newLife = max(-50, min(100, $state['players'][$tKey]['life'] + $delta));
                    $state['players'][$tKey]['life'] = $newLife;
                    $tName = $state['players'][$tKey]['name'];
                    $sign = $delta >= 0 ? "+{$delta}" : "{$delta}";
                    $logText = "{$userName} modified {$tName}'s life by {$sign} (Current: {$newLife}).";

                    // Automatic Victory Detection if Life reaches 0 or below
                    if ($newLife <= 0 && $room['status'] !== 'finished') {
                        $loserUid = $targetUserId;
                        $winnerUid = ($loserUid === $p1Id) ? $p2Id : $p1Id;
                        $winnerUser = $this->getUserInfo($winnerUid);
                        $winnerName = $winnerUser['display_name'] ?? $winnerUser['username'];

                        $state['winner_id'] = $winnerUid;
                        $state['status'] = 'finished';
                        $logText .= " 🏆 {$winnerName} has won the match! ({$tName} reached 0 Life).";

                        $this->pdo->prepare("
                            UPDATE `game_rooms` 
                            SET `status` = 'finished', `winner_id` = :win, `updated_at` = NOW() 
                            WHERE `id` = :id
                        ")->execute([':win' => $winnerUid, ':id' => $roomId]);
                    }
                }
                break;

            // 11. Update Resources / Action Points
            case 'update_resources':
                $delta = (int)($payload['delta'] ?? 1);
                $cur = $state['players'][$pKey]['resources'] ?? 0;
                $state['players'][$pKey]['resources'] = max(0, $cur + $delta);
                $logText = "{$userName} resource points: " . $state['players'][$pKey]['resources'];
                break;

            case 'update_action_points':
                $delta = (int)($payload['delta'] ?? 1);
                $cur = $state['players'][$pKey]['action_points'] ?? 0;
                $state['players'][$pKey]['action_points'] = max(0, $cur + $delta);
                $logText = "{$userName} action points: " . $state['players'][$pKey]['action_points'];
                break;

            // 12. Roll Dice / Coin
            case 'roll_dice':
                $diceType = $payload['dice_type'] ?? 'd6';
                if ($diceType === 'coin') {
                    $result = random_int(0, 1) === 1 ? 'Heads' : 'Tails';
                    $logText = "🎲 {$userName} flipped a coin: **{$result}**!";
                } elseif ($diceType === 'd20') {
                    $roll = random_int(1, 20);
                    $logText = "🎲 {$userName} rolled a D20: **{$roll}**!";
                } else {
                    $roll = random_int(1, 6);
                    $logText = "🎲 {$userName} rolled a D6: **{$roll}**!";
                }
                break;

            // 13. Shuffle Deck
            case 'shuffle_deck':
                shuffle($state['players'][$pKey]['deck']);
                $logText = "{$userName} shuffled their deck (" . count($state['players'][$pKey]['deck']) . " cards).";
                break;

            // 14. Close Combat Chain
            case 'close_combat_chain':
                if ($userId !== (int)$room['current_turn_player_id']) {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: Only the attacking player can close the combat chain.");
                }

                if (!empty($state['combat_chain']['links'])) {
                    foreach ($state['combat_chain']['links'] as $link) {
                        // Move attack card to attacker's graveyard
                        if (!empty($link['attack_card'])) {
                            $atkId = (string)($link['attacker_id'] ?? $userId);
                            $state['players'][$atkId]['graveyard'][] = $link['attack_card'];
                        }
                        // Move defend cards to defender's graveyard (if from hand)
                        foreach ($link['defend_cards'] ?? [] as $defItem) {
                            if (($defItem['from_zone'] ?? '') !== 'equipment' && !empty($defItem['card'])) {
                                $defId = (string)($defItem['defender_id'] ?? $opponentId);
                                $state['players'][$defId]['graveyard'][] = $defItem['card'];
                            }
                        }
                    }
                }
                $state['combat_chain']['is_open'] = false;
                $state['combat_chain']['links'] = [];
                $state['combat_chain']['current_link_index'] = 0;
                $logText = "{$userName} closed the Combat Chain. All chain cards sent to graveyard.";
                break;

            // 15. End Turn (Flesh and Blood End Phase)
            case 'end_turn':
                // Check if it's the player's turn or system timeout
                if ($userId !== (int)$room['current_turn_player_id'] && ($payload['reason'] ?? '') !== 'timeout') {
                    $this->pdo->rollBack();
                    throw new Exception("Prohibited Move: It is not your turn to pass the turn.");
                }

                // Close chain if open
                if (!empty($state['combat_chain']['is_open'])) {
                    $this->processAction($roomId, $userId, 'close_combat_chain', []);
                    // Reload state after chain close
                    $reloaded = $this->pdo->query("SELECT `game_state` FROM `game_rooms` WHERE `id` = {$roomId}")->fetchColumn();
                    $state = json_decode($reloaded, true);
                }

                // 1. Move player's pitched cards to the bottom of player's deck
                $pitched = $state['players'][$pKey]['pitch'] ?? [];
                if (!empty($pitched)) {
                    foreach ($pitched as $pCard) {
                        $state['players'][$pKey]['deck'][] = $pCard;
                    }
                    $state['players'][$pKey]['pitch'] = [];
                }

                // 2. Reset resources and AP
                $state['players'][$pKey]['resources'] = 0;
                $state['players'][$oppKey]['resources'] = 0;
                $state['players'][$oppKey]['action_points'] = 1;

                // 3. Draw cards up to Hero Intelligence
                $intel = $state['players'][$pKey]['intelligence'] ?? 4;
                $handCount = count($state['players'][$pKey]['hand'] ?? []);
                $needDraw = max(0, $intel - $handCount);
                for ($d = 0; $d < $needDraw; $d++) {
                    if (!empty($state['players'][$pKey]['deck'])) {
                        $state['players'][$pKey]['hand'][] = array_shift($state['players'][$pKey]['deck']);
                    }
                }

                // 4. Advance turn and switch active player
                $state['turn'] = ($state['turn'] ?? 1) + 1;
                $state['active_player_id'] = $opponentId;
                $state['priority_player_id'] = $opponentId;
                $state['turn_start_time'] = time();

                $oppName = $state['players'][$oppKey]['name'] ?? 'Opponent';
                if (($payload['reason'] ?? '') === 'timeout') {
                    $logText = "⏰ {$userName}'s turn timer expired! Turn automatically passed to {$oppName} (Turn {$state['turn']}).";
                } else {
                    $logText = "{$userName} ended their turn. It is now {$oppName}'s turn (Turn {$state['turn']}).";
                }

                $updTurn = $this->pdo->prepare("
                    UPDATE `game_rooms` 
                    SET `current_turn_player_id` = :act, `turn_number` = :trn 
                    WHERE `id` = :id
                ");
                $updTurn->execute([
                    ':act' => $opponentId,
                    ':trn' => $state['turn'],
                    ':id' => $roomId
                ]);
                break;

            // 16. Concede / Forfeit
            case 'concede':
            case 'forfeit_match':
            case 'leave_match':
                $state['winner_id'] = $opponentId;
                $state['status'] = 'finished';
                $oppName = $state['players'][$oppKey]['name'] ?? 'Opponent';
                $logText = "🏳️ {$userName} has left/conceded the match. {$oppName} is victorious!";

                $this->pdo->prepare("
                    UPDATE `game_rooms` 
                    SET `status` = 'finished', `winner_id` = :win, `updated_at` = NOW() 
                    WHERE `id` = :id
                ")->execute([':win' => $opponentId, ':id' => $roomId]);
                break;

            default:
                $logText = "{$userName} performed {$actionType}.";
                break;
        }

        // Add log entry
        if (!empty($logText)) {
            $state['log'][] = [
                'id' => count($state['log']) + 1,
                'text' => $logText,
                'time' => date('H:i:s'),
                'type' => $actionType
            ];
            // Keep last 150 log entries
            if (count($state['log']) > 150) {
                $state['log'] = array_slice($state['log'], -150);
            }
        }

        // Update sequence in game_actions
        $seqStmt = $this->pdo->prepare("SELECT IFNULL(MAX(`sequence_num`), 0) + 1 FROM `game_actions` WHERE `room_id` = :rid");
        $seqStmt->execute([':rid' => $roomId]);
        $nextSeq = (int)$seqStmt->fetchColumn();

        $insAction = $this->pdo->prepare("
            INSERT INTO `game_actions` (`room_id`, `player_id`, `action_type`, `action_payload`, `sequence_num`, `created_at`)
            VALUES (:rid, :uid, :type, :payload, :seq, NOW())
        ");
        $insAction->execute([
            ':rid' => $roomId,
            ':uid' => $userId,
            ':type' => $actionType,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':seq' => $nextSeq
        ]);

        // Save updated room state
        $saveStmt = $this->pdo->prepare("UPDATE `game_rooms` SET `game_state` = :state, `updated_at` = NOW() WHERE `id` = :id");
        $saveStmt->execute([
            ':state' => json_encode($state, JSON_UNESCAPED_UNICODE),
            ':id' => $roomId
        ]);

        $this->pdo->commit();

        return $this->getRoomState($roomId, $userId) ?? [];
    }

    private function extractCardFromZone(array &$zone, string $instanceId): ?array {
        foreach ($zone as $idx => $c) {
            if (($c['instance_id'] ?? '') === $instanceId) {
                $found = $c;
                array_splice($zone, $idx, 1);
                return $found;
            }
        }
        return null;
    }

    private function findAndRemoveCard(array &$playerState, string $zoneName, string $instanceId): ?array {
        if ($zoneName === 'equipment') {
            foreach ($playerState['equipment'] as $slot => $eq) {
                if ($eq && ($eq['instance_id'] ?? '') === $instanceId) {
                    $playerState['equipment'][$slot] = null;
                    return $eq;
                }
            }
            return null;
        }

        if (isset($playerState[$zoneName]) && is_array($playerState[$zoneName])) {
            return $this->extractCardFromZone($playerState[$zoneName], $instanceId);
        }

        return null;
    }

    private function addCardToZone(array &$playerState, string $zoneName, array $card): void {
        if ($zoneName === 'deck_top') {
            array_unshift($playerState['deck'], $card);
        } elseif ($zoneName === 'deck_bottom' || $zoneName === 'deck') {
            $playerState['deck'][] = $card;
        } elseif (isset($playerState[$zoneName]) && is_array($playerState[$zoneName])) {
            $playerState[$zoneName][] = $card;
        }
    }

    private function getUserInfo(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT `id`, `username`, `display_name`, `avatar` FROM `users` WHERE `id` = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: ['id' => $userId, 'username' => 'Player', 'display_name' => 'Player', 'avatar' => 'hero_default'];
    }

    private function getRoomCode(int $roomId): string {
        $stmt = $this->pdo->prepare("SELECT `room_code` FROM `game_rooms` WHERE `id` = :id");
        $stmt->execute([':id' => $roomId]);
        return (string)$stmt->fetchColumn();
    }
}
