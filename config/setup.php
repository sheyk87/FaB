<?php
/**
 * Database Setup & card.json Importer
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function runSetup(bool $forceReimport = false): array {
    @ini_set('memory_limit', '512M');
    @set_time_limit(300);
    $results = [];
    $dbName = Database::getDbName();

    // 1. Create Database if not exists (gracefully skips on shared hosting where DB is pre-assigned)
    try {
        $rawPdo = Database::getRawConnection();
        $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $results[] = "Database `{$dbName}` created or verified.";
    } catch (Throwable $e) {
        $results[] = "Using pre-assigned database `{$dbName}`.";
    }

    // Connect to configured database
    $pdo = Database::getConnection();

    // 2. Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `display_name` VARCHAR(100) NOT NULL,
            `avatar` VARCHAR(255) DEFAULT 'hero_default',
            `is_online` TINYINT(1) DEFAULT 0,
            `last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_online (`is_online`, `last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sets` (
            `id` VARCHAR(20) PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `card_count` INT UNSIGNED DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cards` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `unique_id` VARCHAR(64) NOT NULL UNIQUE,
            `name` VARCHAR(150) NOT NULL,
            `color` VARCHAR(20) DEFAULT NULL,
            `pitch` TINYINT DEFAULT NULL,
            `cost` VARCHAR(10) DEFAULT NULL,
            `power` VARCHAR(10) DEFAULT NULL,
            `defense` VARCHAR(10) DEFAULT NULL,
            `health` VARCHAR(10) DEFAULT NULL,
            `intelligence` VARCHAR(10) DEFAULT NULL,
            `arcane` VARCHAR(10) DEFAULT NULL,
            `type_text` VARCHAR(150) DEFAULT NULL,
            `functional_text` TEXT DEFAULT NULL,
            `functional_text_plain` TEXT DEFAULT NULL,
            `types_json` TEXT DEFAULT NULL,
            `keywords_json` TEXT DEFAULT NULL,
            `set_id` VARCHAR(20) DEFAULT NULL,
            `collector_number` VARCHAR(30) DEFAULT NULL,
            `rarity` VARCHAR(10) DEFAULT NULL,
            `image_url` VARCHAR(500) DEFAULT NULL,
            `is_hero` TINYINT(1) DEFAULT 0,
            `is_weapon` TINYINT(1) DEFAULT 0,
            `is_equipment` TINYINT(1) DEFAULT 0,
            `is_action` TINYINT(1) DEFAULT 0,
            `is_attack` TINYINT(1) DEFAULT 0,
            `is_defense_reaction` TINYINT(1) DEFAULT 0,
            `is_instant` TINYINT(1) DEFAULT 0,
            `is_item` TINYINT(1) DEFAULT 0,
            `is_aura` TINYINT(1) DEFAULT 0,
            `blitz_legal` TINYINT(1) DEFAULT 1,
            `cc_legal` TINYINT(1) DEFAULT 1,
            `commoner_legal` TINYINT(1) DEFAULT 0,
            INDEX idx_card_name (`name`),
            INDEX idx_card_pitch (`pitch`),
            INDEX idx_card_set (`set_id`),
            INDEX idx_card_hero (`is_hero`),
            INDEX idx_card_equip (`is_equipment`),
            INDEX idx_card_weapon (`is_weapon`),
            INDEX idx_card_rarity (`rarity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `decks` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `hero_card_id` VARCHAR(64) NOT NULL,
            `hero_name` VARCHAR(150) NOT NULL,
            `hero_image_url` VARCHAR(500) DEFAULT NULL,
            `format` ENUM('Blitz', 'Classic Constructed', 'Commoner') DEFAULT 'Blitz',
            `description` TEXT DEFAULT NULL,
            `cards_json` LONGTEXT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_deck_user (`user_id`),
            INDEX idx_deck_format (`format`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `matchmaking_queue` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL UNIQUE,
            `deck_id` INT UNSIGNED NOT NULL,
            `format` VARCHAR(30) DEFAULT 'Blitz',
            `status` ENUM('waiting', 'matched', 'cancelled') DEFAULT 'waiting',
            `matched_game_id` INT UNSIGNED DEFAULT NULL,
            `queued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_queue_status_format (`status`, `format`, `queued_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `game_rooms` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_code` VARCHAR(20) NOT NULL UNIQUE,
            `player1_id` INT UNSIGNED NOT NULL,
            `player2_id` INT UNSIGNED DEFAULT NULL,
            `player1_deck_id` INT UNSIGNED NOT NULL,
            `player2_deck_id` INT UNSIGNED DEFAULT NULL,
            `format` VARCHAR(30) DEFAULT 'Blitz',
            `status` ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
            `current_turn_player_id` INT UNSIGNED DEFAULT NULL,
            `turn_number` INT UNSIGNED DEFAULT 1,
            `winner_id` INT UNSIGNED DEFAULT NULL,
            `game_state` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`player1_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_room_code (`room_code`),
            INDEX idx_room_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `game_actions` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `player_id` INT UNSIGNED NOT NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `action_payload` LONGTEXT DEFAULT NULL,
            `sequence_num` INT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`room_id`) REFERENCES `game_rooms`(`id`) ON DELETE CASCADE,
            INDEX idx_room_seq (`room_id`, `sequence_num`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `user_name` VARCHAR(100) NOT NULL,
            `message` TEXT NOT NULL,
            `is_system` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`room_id`) REFERENCES `game_rooms`(`id`) ON DELETE CASCADE,
            INDEX idx_chat_room (`room_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $results[] = "Tables created or verified.";

    // 3. Check card count with concurrency lock
    $lockStmt = $pdo->query("SELECT GET_LOCK('fab_setup_cards_lock', 20)");
    $hasLock = (bool)($lockStmt ? $lockStmt->fetchColumn() : 0);

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `cards`");
        $cardCount = (int)$stmt->fetchColumn();

        if ($cardCount === 0 || $forceReimport) {
            $jsonPath = __DIR__ . '/../card.json';
            if (!file_exists($jsonPath)) {
                $results[] = "card.json not found in root path.";
                return $results;
            }

            $results[] = "Importing cards from card.json...";
            $jsonData = json_decode(file_get_contents($jsonPath), true);
            if (!$jsonData) {
                $results[] = "Failed to parse card.json.";
                return $results;
            }

            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM `cards`");
            $pdo->exec("DELETE FROM `sets`");

            $cardInsert = $pdo->prepare("
                INSERT INTO `cards` (
                    `unique_id`, `name`, `color`, `pitch`, `cost`, `power`, `defense`,
                    `health`, `intelligence`, `arcane`, `type_text`, `functional_text`,
                    `functional_text_plain`, `types_json`, `keywords_json`, `set_id`,
                    `collector_number`, `rarity`, `image_url`, `is_hero`, `is_weapon`,
                    `is_equipment`, `is_action`, `is_attack`, `is_defense_reaction`,
                    `is_instant`, `is_item`, `is_aura`, `blitz_legal`, `cc_legal`, `commoner_legal`
                ) VALUES (
                    :unique_id, :name, :color, :pitch, :cost, :power, :defense,
                    :health, :intelligence, :arcane, :type_text, :functional_text,
                    :functional_text_plain, :types_json, :keywords_json, :set_id,
                    :collector_number, :rarity, :image_url, :is_hero, :is_weapon,
                    :is_equipment, :is_action, :is_attack, :is_defense_reaction,
                    :is_instant, :is_item, :is_aura, :blitz_legal, :cc_legal, :commoner_legal
                )
            ");

            $setsMap = [];
            $importedCount = 0;

            foreach ($jsonData as $c) {
                $uniqueId = $c['unique_id'] ?? '';
                if (empty($uniqueId)) continue;

                $types = $c['types'] ?? [];
                $keywords = $c['card_keywords'] ?? [];
                $pitch = isset($c['pitch']) && is_numeric($c['pitch']) ? (int)$c['pitch'] : null;

                // Pick printing info
                $firstPrinting = $c['printings'][0] ?? [];
                $setId = $firstPrinting['set_id'] ?? 'GEN';
                $collectorNumber = $firstPrinting['id'] ?? '';
                $rarity = $firstPrinting['rarity'] ?? 'C';
                $imageUrl = $firstPrinting['image_url'] ?? '';

                // If image is RF, try to find normal printing
                if (!empty($c['printings'])) {
                    foreach ($c['printings'] as $pr) {
                        if (!empty($pr['image_url'])) {
                            $imageUrl = $pr['image_url'];
                            $setId = $pr['set_id'] ?? $setId;
                            $collectorNumber = $pr['id'] ?? $collectorNumber;
                            $rarity = $pr['rarity'] ?? $rarity;
                            break;
                        }
                    }
                }

                if (!empty($setId)) {
                    $setsMap[$setId] = ($setsMap[$setId] ?? 0) + 1;
                }

                $isHero = in_array('Hero', $types, true) ? 1 : 0;
                $isWeapon = in_array('Weapon', $types, true) ? 1 : 0;
                $isEquipment = in_array('Equipment', $types, true) ? 1 : 0;
                $isAction = in_array('Action', $types, true) ? 1 : 0;
                $isAttack = in_array('Attack', $types, true) ? 1 : 0;
                $isDefenseReaction = in_array('Defense Reaction', $types, true) ? 1 : 0;
                $isInstant = in_array('Instant', $types, true) ? 1 : 0;
                $isItem = in_array('Item', $types, true) ? 1 : 0;
                $isAura = in_array('Aura', $types, true) ? 1 : 0;

                $cardInsert->execute([
                    ':unique_id'             => $uniqueId,
                    ':name'                  => $c['name'] ?? '',
                    ':color'                 => $c['color'] ?? null,
                    ':pitch'                 => $pitch,
                    ':cost'                  => $c['cost'] ?? null,
                    ':power'                 => $c['power'] ?? null,
                    ':defense'               => $c['defense'] ?? null,
                    ':health'                => $c['health'] ?? null,
                    ':intelligence'          => $c['intelligence'] ?? null,
                    ':arcane'                => $c['arcane'] ?? null,
                    ':type_text'             => $c['type_text'] ?? '',
                    ':functional_text'       => $c['functional_text'] ?? '',
                    ':functional_text_plain' => $c['functional_text_plain'] ?? '',
                    ':types_json'            => json_encode($types, JSON_UNESCAPED_UNICODE),
                    ':keywords_json'         => json_encode($keywords, JSON_UNESCAPED_UNICODE),
                    ':set_id'                => $setId,
                    ':collector_number'      => $collectorNumber,
                    ':rarity'                => $rarity,
                    ':image_url'             => $imageUrl,
                    ':is_hero'               => $isHero,
                    ':is_weapon'             => $isWeapon,
                    ':is_equipment'          => $isEquipment,
                    ':is_action'             => $isAction,
                    ':is_attack'             => $isAttack,
                    ':is_defense_reaction'   => $isDefenseReaction,
                    ':is_instant'            => $isInstant,
                    ':is_item'               => $isItem,
                    ':is_aura'               => $isAura,
                    ':blitz_legal'           => (!empty($c['blitz_legal']) && empty($c['blitz_banned'])) ? 1 : 0,
                    ':cc_legal'              => (!empty($c['cc_legal']) && empty($c['cc_banned'])) ? 1 : 0,
                    ':commoner_legal'        => (!empty($c['commoner_legal']) && empty($c['commoner_banned'])) ? 1 : 0,
                ]);

                $importedCount++;
            }

            // Insert sets
            $setNames = [
                'WTR' => 'Welcome to Rathe',
                'ARC' => 'Arcane Rising',
                'CRU' => 'Crucible of War',
                'MON' => 'Monarch',
                'ELE' => 'Tales of Aria',
                'EVR' => 'Everfest',
                'UPR' => 'Uprising',
                'DYN' => 'Dynasty',
                'OUT' => 'Outsiders',
                'DTD' => 'Dusk till Dawn',
                'EVO' => 'Brightmeart / Evolution',
                'HVY' => 'Heavy Hitters',
                'MST' => 'Mistveil: Part the Mistveil',
                'ROS' => 'Rosetta',
                'HNT' => 'The Hunted',
                'LGS' => 'Promo & Armory',
                'HP1' => 'History Pack 1',
                'HP2' => 'History Pack 2'
            ];

            $setInsert = $pdo->prepare("INSERT INTO `sets` (`id`, `name`, `card_count`) VALUES (:id, :name, :card_count)");
            foreach ($setsMap as $sId => $cnt) {
                $sName = $setNames[$sId] ?? ('Set ' . $sId);
                $setInsert->execute([
                    ':id' => $sId,
                    ':name' => $sName,
                    ':card_count' => $cnt
                ]);
            }

            $pdo->commit();
            $results[] = "Successfully imported {$importedCount} cards and " . count($setsMap) . " sets.";
        } else {
            $results[] = "Card database already populated ({$cardCount} cards).";
        }
    } finally {
        if ($hasLock) {
            $pdo->query("SELECT RELEASE_LOCK('fab_setup_cards_lock')");
        }
    }

    // 4. Create starter / test users if no users exist
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($userCount === 0) {
        $passHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO `users` (`username`, `password_hash`, `display_name`, `avatar`, `is_online`, `last_seen`) 
            VALUES (:u, :p, :d, :a, 1, NOW())
        ");
        $stmt->execute([':u' => 'PlayerOne', ':p' => $passHash, ':d' => 'Hero of Rathe', ':a' => 'avatar_dorinthea']);
        $p1Id = (int)$pdo->lastInsertId();

        $stmt->execute([':u' => 'PlayerTwo', ':p' => $passHash, ':d' => 'Shadow Runeblade', ':a' => 'avatar_chane']);
        $p2Id = (int)$pdo->lastInsertId();

        // Create sample starter decks for test users
        createStarterDecks($pdo, $p1Id, $p2Id);
        $results[] = "Created demo users PlayerOne and PlayerTwo with starter decks.";
    } else {
        // Users already exist, check if starter decks exist
        $deckCount = (int)$pdo->query("SELECT COUNT(*) FROM `decks`")->fetchColumn();
        if ($deckCount === 0) {
            $p1 = $pdo->query("SELECT `id` FROM `users` WHERE `username` = 'PlayerOne' LIMIT 1")->fetchColumn();
            $p2 = $pdo->query("SELECT `id` FROM `users` WHERE `username` = 'PlayerTwo' LIMIT 1")->fetchColumn();
            $p1Id = $p1 ? (int)$p1 : 1;
            $p2Id = $p2 ? (int)$p2 : 2;
            createStarterDecks($pdo, $p1Id, $p2Id);
            $results[] = "Created starter decks for PlayerOne and PlayerTwo.";
        }
    }

    return $results;
}

function createStarterDecks(PDO $pdo, int $p1Id, int $p2Id): void {
    // Look up some hero cards
    $stmt = $pdo->query("SELECT `unique_id`, `name`, `image_url` FROM `cards` WHERE `is_hero` = 1 LIMIT 50");
    $heroes = $stmt->fetchAll();

    if (empty($heroes)) return;

    $hero1 = $heroes[0];
    $hero2 = $heroes[1] ?? $heroes[0];

    // Find some weapons, equipments, and action cards
    $weaps = $pdo->query("SELECT `unique_id` FROM `cards` WHERE `is_weapon` = 1 LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    $equips = $pdo->query("SELECT `unique_id` FROM `cards` WHERE `is_equipment` = 1 LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
    $actions = $pdo->query("SELECT `unique_id` FROM `cards` WHERE `is_action` = 1 LIMIT 40")->fetchAll(PDO::FETCH_COLUMN);

    $deckCards1 = [
        'hero' => $hero1['unique_id'],
        'weapons' => $weaps,
        'equipment' => $equips,
        'main_deck' => $actions,
        'inventory' => []
    ];

    $deckCards2 = [
        'hero' => $hero2['unique_id'],
        'weapons' => $weaps,
        'equipment' => $equips,
        'main_deck' => $actions,
        'inventory' => []
    ];

    $insDeck = $pdo->prepare("
        INSERT INTO `decks` (`user_id`, `name`, `hero_card_id`, `hero_name`, `hero_image_url`, `format`, `description`, `cards_json`)
        VALUES (:uid, :name, :hid, :hname, :himg, :fmt, :desc, :json)
    ");

    $insDeck->execute([
        ':uid'   => $p1Id,
        ':name'  => 'Starter Blitz - ' . $hero1['name'],
        ':hid'   => $hero1['unique_id'],
        ':hname' => $hero1['name'],
        ':himg'  => $hero1['image_url'],
        ':fmt'   => 'Blitz',
        ':desc'  => 'Default starter deck for quick testing and battles.',
        ':json'  => json_encode($deckCards1, JSON_UNESCAPED_UNICODE)
    ]);

    $insDeck->execute([
        ':uid'   => $p2Id,
        ':name'  => 'Starter Blitz - ' . $hero2['name'],
        ':hid'   => $hero2['unique_id'],
        ':hname' => $hero2['name'],
        ':himg'  => $hero2['image_url'],
        ':fmt'   => 'Blitz',
        ':desc'  => 'Default starter deck for opponent player.',
        ':json'  => json_encode($deckCards2, JSON_UNESCAPED_UNICODE)
    ]);
}

// Allow CLI or direct Web execution
$isCli = (php_sapi_name() === 'cli');
$isDirectWeb = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__));

if ($isCli || $isDirectWeb) {
    $force = $isCli ? in_array('--force', $argv ?? [], true) : isset($_GET['force']);

    if (!$isCli) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Inicialización de Base de Datos - Flesh and Blood TCG</title>';
        echo '<style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { background: #0f111a; color: #d6d9e0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2.5rem 1rem; line-height: 1.6; }
            .container { max-width: 720px; margin: 0 auto; background: #161925; border: 1px solid #282c3f; border-radius: 12px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.5); }
            h1 { color: #f5c518; font-size: 1.6rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
            p.sub { color: #8c93a8; font-size: 0.95rem; margin-bottom: 1.5rem; }
            ul.log { list-style: none; background: #0c0d14; border: 1px solid #232736; border-radius: 8px; padding: 1rem 1.25rem; font-family: monospace; font-size: 0.95rem; }
            ul.log li { padding: 0.4rem 0; border-bottom: 1px solid #1a1d29; color: #a4b1cd; }
            ul.log li:last-child { border-bottom: none; }
            ul.log li.ok { color: #4caf50; font-weight: 600; }
            .actions { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
            .btn { display: inline-flex; align-items: center; gap: 0.5rem; background: #e53935; color: #fff; text-decoration: none; padding: 0.85rem 1.5rem; border-radius: 8px; font-weight: 600; font-size: 1rem; transition: background 0.2s; }
            .btn:hover { background: #d32f2f; }
            .btn-secondary { background: #282c3f; color: #e1e4ea; }
            .btn-secondary:hover { background: #353a52; }
        </style></head><body><div class="container">';
        echo '<h1>⚔️ Flesh and Blood TCG</h1>';
        echo '<p class="sub">Inicializador y Carga de Cartas (card.json)</p>';
        echo '<ul class="log">';
    } else {
        echo "Starting setup...\n";
    }

    $log = runSetup($force);
    foreach ($log as $msg) {
        if ($isCli) {
            echo "- " . $msg . "\n";
        } else {
            echo '<li class="ok">✔ ' . htmlspecialchars($msg) . '</li>';
        }
    }

    if ($isCli) {
        echo "Setup finished successfully.\n";
    } else {
        echo '</ul>';
        echo '<div class="actions">';
        echo '<a href="../index.php" class="btn">🏰 Abrir Juego (Library & Decks)</a>';
        echo '<a href="setup.php?force=1" class="btn btn-secondary">🔄 Reimportar Forzado</a>';
        echo '</div></div></body></html>';
    }
}
