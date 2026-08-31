<?php
/**
 * Deck Service
 * Flesh and Blood TCG Sandbox
 * Format Validation & Deck Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/CardService.php';

class DeckService {
    private PDO $pdo;
    private CardService $cardService;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->cardService = new CardService($this->pdo);
    }

    /**
     * Get all decks for a user
     */
    public function getUserDecks(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT `id`, `user_id`, `name`, `hero_card_id`, `hero_name`, `hero_image_url`,
                   `format`, `description`, `cards_json`, `created_at`, `updated_at`
            FROM `decks`
            WHERE `user_id` = :uid
            ORDER BY `updated_at` DESC
        ");
        $stmt->execute([':uid' => $userId]);
        $decks = $stmt->fetchAll();

        foreach ($decks as &$deck) {
            $deck['cards'] = json_decode($deck['cards_json'], true) ?: [];
            $deck['main_count'] = count($deck['cards']['main_deck'] ?? []);
            $deck['equipment_count'] = count($deck['cards']['equipment'] ?? []) + count($deck['cards']['weapons'] ?? []);
            unset($deck['cards_json']);
        }

        return $decks;
    }

    /**
     * Get a single deck by ID with full populated card objects and statistics
     */
    public function getDeck(int $deckId, ?int $userId = null): ?array {
        $sql = "SELECT * FROM `decks` WHERE `id` = :id";
        $params = [':id' => $deckId];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :uid";
            $params[':uid'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $deck = $stmt->fetch();

        if (!$deck) return null;

        $cardsData = json_decode($deck['cards_json'], true) ?: [
            'hero' => '',
            'weapons' => [],
            'equipment' => [],
            'main_deck' => [],
            'inventory' => []
        ];

        // Gather all unique card IDs
        $allIds = [];
        if (!empty($cardsData['hero'])) $allIds[] = $cardsData['hero'];
        foreach ($cardsData['weapons'] ?? [] as $id) $allIds[] = $id;
        foreach ($cardsData['equipment'] ?? [] as $id) $allIds[] = $id;
        foreach ($cardsData['main_deck'] ?? [] as $id) $allIds[] = $id;
        foreach ($cardsData['inventory'] ?? [] as $id) $allIds[] = $id;

        $cardMap = $this->cardService->getCardsByUniqueIds($allIds);

        // Populate card details
        $populatedHero = !empty($cardsData['hero']) ? ($cardMap[$cardsData['hero']] ?? null) : null;
        $populatedWeapons = array_values(array_filter(array_map(fn($id) => $cardMap[$id] ?? null, $cardsData['weapons'] ?? [])));
        $populatedEquipment = array_values(array_filter(array_map(fn($id) => $cardMap[$id] ?? null, $cardsData['equipment'] ?? [])));
        $populatedMain = array_values(array_filter(array_map(fn($id) => $cardMap[$id] ?? null, $cardsData['main_deck'] ?? [])));
        $populatedInventory = array_values(array_filter(array_map(fn($id) => $cardMap[$id] ?? null, $cardsData['inventory'] ?? [])));

        $validation = $this->validateDeckComposition($deck['format'], $populatedHero, $populatedMain, $populatedEquipment, $populatedWeapons);
        $stats = $this->calculateDeckStatistics($populatedMain);

        return [
            'id' => (int)$deck['id'],
            'user_id' => (int)$deck['user_id'],
            'name' => $deck['name'],
            'hero_card_id' => $deck['hero_card_id'],
            'hero_name' => $deck['hero_name'],
            'hero_image_url' => $deck['hero_image_url'],
            'format' => $deck['format'],
            'description' => $deck['description'],
            'cards' => [
                'hero' => $populatedHero,
                'weapons' => $populatedWeapons,
                'equipment' => $populatedEquipment,
                'main_deck' => $populatedMain,
                'inventory' => $populatedInventory
            ],
            'raw_card_ids' => $cardsData,
            'validation' => $validation,
            'stats' => $stats,
            'created_at' => $deck['created_at'],
            'updated_at' => $deck['updated_at']
        ];
    }

    /**
     * Save (create or update) a deck
     */
    public function saveDeck(int $userId, array $data, ?int $deckId = null): array {
        $name = Security::sanitizeString($data['name'] ?? 'New Deck', 100);
        $format = in_array($data['format'] ?? '', ['Blitz', 'Classic Constructed', 'Commoner'], true) ? $data['format'] : 'Blitz';
        $desc = Security::sanitizeString($data['description'] ?? '', 1000);

        $heroId = $data['hero_card_id'] ?? ($data['cards']['hero'] ?? '');
        $heroCard = !empty($heroId) ? $this->cardService->getCardByUniqueId($heroId) : null;

        $heroName = $heroCard ? $heroCard['name'] : 'Unknown Hero';
        $heroImage = $heroCard ? $heroCard['image_url'] : null;

        // Clean card lists (arrays of unique_id strings)
        $cardsPayload = [
            'hero' => $heroId,
            'weapons' => is_array($data['weapons'] ?? null) ? $data['weapons'] : ($data['cards']['weapons'] ?? []),
            'equipment' => is_array($data['equipment'] ?? null) ? $data['equipment'] : ($data['cards']['equipment'] ?? []),
            'main_deck' => is_array($data['main_deck'] ?? null) ? $data['main_deck'] : ($data['cards']['main_deck'] ?? []),
            'inventory' => is_array($data['inventory'] ?? null) ? $data['inventory'] : ($data['cards']['inventory'] ?? [])
        ];

        $cardsJson = json_encode($cardsPayload, JSON_UNESCAPED_UNICODE);

        if ($deckId !== null) {
            // Update
            $stmt = $this->pdo->prepare("
                UPDATE `decks`
                SET `name` = :name,
                    `hero_card_id` = :hid,
                    `hero_name` = :hname,
                    `hero_image_url` = :himg,
                    `format` = :fmt,
                    `description` = :desc,
                    `cards_json` = :json
                WHERE `id` = :id AND `user_id` = :uid
            ");
            $stmt->execute([
                ':name' => $name,
                ':hid' => $heroId,
                ':hname' => $heroName,
                ':himg' => $heroImage,
                ':fmt' => $format,
                ':desc' => $desc,
                ':json' => $cardsJson,
                ':id' => $deckId,
                ':uid' => $userId
            ]);
            $savedId = $deckId;
        } else {
            // Insert
            $stmt = $this->pdo->prepare("
                INSERT INTO `decks` (`user_id`, `name`, `hero_card_id`, `hero_name`, `hero_image_url`, `format`, `description`, `cards_json`)
                VALUES (:uid, :name, :hid, :hname, :himg, :fmt, :desc, :json)
            ");
            $stmt->execute([
                ':uid' => $userId,
                ':name' => $name,
                ':hid' => $heroId,
                ':hname' => $heroName,
                ':himg' => $heroImage,
                ':fmt' => $format,
                ':desc' => $desc,
                ':json' => $cardsJson
            ]);
            $savedId = (int)$this->pdo->lastInsertId();
        }

        return $this->getDeck($savedId, $userId) ?? [];
    }

    /**
     * Delete deck
     */
    public function deleteDeck(int $deckId, int $userId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM `decks` WHERE `id` = :id AND `user_id` = :uid");
        $stmt->execute([':id' => $deckId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Flesh and Blood Deck format validator
     */
    public function validateDeckComposition(string $format, ?array $hero, array $mainDeck, array $equipment, array $weapons): array {
        $errors = [];
        $warnings = [];

        if (!$hero) {
            $errors[] = 'A Hero card is required for the deck.';
        }

        $mainCount = count($mainDeck);
        $maxCopies = ($format === 'Blitz' || $format === 'Commoner') ? 2 : 3;

        if ($format === 'Blitz') {
            if ($mainCount < 40) {
                $warnings[] = "Blitz decks typically contain 40 cards in main deck (current: {$mainCount}).";
            }
        } elseif ($format === 'Classic Constructed') {
            if ($mainCount < 60) {
                $errors[] = "Classic Constructed requires at least 60 cards in main deck (current: {$mainCount}).";
            }
        }

        // Count copies by unique_id or name+pitch
        $copyCounts = [];
        foreach ($mainDeck as $card) {
            if (!$card) continue;
            $key = $card['name'] . '_' . ($card['pitch'] ?? '0');
            $copyCounts[$key] = ($copyCounts[$key] ?? 0) + 1;
            if ($copyCounts[$key] > $maxCopies) {
                $errors[] = "Too many copies of '{$card['name']}' (Pitch: {$card['pitch']}). Max allowed in {$format} is {$maxCopies}.";
            }
        }

        // Class matching check
        if ($hero && !empty($hero['types'])) {
            $heroClasses = array_diff($hero['types'], ['Hero', 'Young']);
            foreach ($mainDeck as $card) {
                if (!$card) continue;
                $cardTypes = $card['types'] ?? [];
                if (in_array('Generic', $cardTypes, true)) continue;

                // Check intersection with hero types
                $intersects = array_intersect($cardTypes, $heroClasses);
                if (empty($intersects) && !empty($cardTypes)) {
                    // Check if card has any class at all
                    $knownClasses = ['Brute', 'Guardian', 'Ninja', 'Warrior', 'Wizard', 'Runeblade', 'Mechanologist', 'Ranger', 'Illusionist', 'Assassin', 'Merchant', 'Bard', 'Adjudicator', 'Shapeshifter', 'Necromancer'];
                    $cardClassList = array_intersect($cardTypes, $knownClasses);
                    if (!empty($cardClassList)) {
                        $warnings[] = "Card '{$card['name']}' class (" . implode('/', $cardClassList) . ") does not match Hero " . $hero['name'];
                    }
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => array_unique($errors),
            'warnings' => array_unique($warnings)
        ];
    }

    /**
     * Compute pitch curve, cost curve, and type breakdown
     */
    public function calculateDeckStatistics(array $mainDeck): array {
        $pitch1 = 0; // Red
        $pitch2 = 0; // Yellow
        $pitch3 = 0; // Blue
        $pitch0 = 0; // No pitch

        $costs = ['0' => 0, '1' => 0, '2' => 0, '3+' => 0];
        $types = [
            'attacks' => 0,
            'non_attacks' => 0,
            'defense_reactions' => 0,
            'instants' => 0,
            'items' => 0,
            'auras' => 0,
            'other' => 0
        ];

        $totalDefense = 0;

        foreach ($mainDeck as $card) {
            if (!$card) continue;

            // Pitch
            $p = $card['pitch'] ?? null;
            if ($p === 1 || $p === '1') $pitch1++;
            elseif ($p === 2 || $p === '2') $pitch2++;
            elseif ($p === 3 || $p === '3') $pitch3++;
            else $pitch0++;

            // Cost
            $c = $card['cost'] ?? '0';
            if ($c === '0' || $c === '' || $c === null) $costs['0']++;
            elseif ($c === '1') $costs['1']++;
            elseif ($c === '2') $costs['2']++;
            else $costs['3+']++;

            // Type
            if (!empty($card['is_defense_reaction'])) $types['defense_reactions']++;
            elseif (!empty($card['is_attack'])) $types['attacks']++;
            elseif (!empty($card['is_action'])) $types['non_attacks']++;
            elseif (!empty($card['is_instant'])) $types['instants']++;
            elseif (!empty($card['is_item'])) $types['items']++;
            elseif (!empty($card['is_aura'])) $types['auras']++;
            else $types['other']++;

            // Defense
            if (!empty($card['defense']) && is_numeric($card['defense'])) {
                $totalDefense += (int)$card['defense'];
            }
        }

        $total = count($mainDeck);
        return [
            'total_cards' => $total,
            'pitch_curve' => [
                'red' => $pitch1,
                'yellow' => $pitch2,
                'blue' => $pitch3,
                'none' => $pitch0
            ],
            'cost_curve' => $costs,
            'type_breakdown' => $types,
            'total_defense' => $totalDefense,
            'avg_defense' => $total > 0 ? round($totalDefense / $total, 1) : 0
        ];
    }

    /**
     * Get list of preconstructed decks with hero metadata
     */
    public function getPreconstructedCatalog(): array {
        $precons = [
            [
                'key' => 'dorinthea_warrior',
                'name' => 'Dorinthea - Steelblade Blitz',
                'hero_name' => 'Dorinthea',
                'class' => 'Warrior',
                'format' => 'Blitz',
                'difficulty' => 'Beginner',
                'description' => 'Fast, relentless weapon attacks powered by Dawnblade and attack reactions that grant Go Again and bonus power.',
                'hero_query' => ['name' => 'Dorinthea', 'is_hero' => '1'],
                'weapon_queries' => ['Dawnblade'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Driving Blade', 'Biting Blade', 'Ironsong Response', 'Steelblade Supremacy',
                    'Overpower', 'Stroke of Foresight', 'Sharpen Steel', 'Hit and Run',
                    'Razor Reflex', 'Sink Below', 'Pummel', 'Scar for a Scar', 'Snatch'
                ]
            ],
            [
                'key' => 'rhinar_brute',
                'name' => 'Rhinar - Apex Predator Blitz',
                'hero_name' => 'Rhinar',
                'class' => 'Brute',
                'format' => 'Blitz',
                'difficulty' => 'Beginner',
                'description' => 'Crushing massive attacks with 6+ power, Intimidate mechanics to bypass opponent defenses, and high resource burst.',
                'hero_query' => ['name' => 'Rhinar', 'is_hero' => '1'],
                'weapon_queries' => ['Romping Club'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Pack Hunt', 'Wrecker Romp', 'Smash Instinct', 'Savage Feast',
                    'Barraging Beatdown', 'Savage Swing', 'Bone Head Barrier', 'Breakneck Battery',
                    'Pummel', 'Sink Below', 'Awakening', 'Primeval Bellow'
                ]
            ],
            [
                'key' => 'katsu_ninja',
                'name' => 'Katsu - Harmonized Combo Blitz',
                'hero_name' => 'Katsu',
                'class' => 'Ninja',
                'format' => 'Blitz',
                'difficulty' => 'Intermediate',
                'description' => 'Blistering flurries of low-cost attacks utilizing Kodachis and combo lines (Leg Tap, Rising Knee Thrust, Blackout Kick).',
                'hero_query' => ['name' => 'Katsu', 'is_hero' => '1'],
                'weapon_queries' => ['Harmonized Kodachi', 'Harmonized Kodachi'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Leg Tap', 'Rising Knee Thrust', 'Blackout Kick', 'Fluster Fist',
                    'Surging Strike', 'Whelming Gustwave', 'Mugenshi: RELEASE', 'Lord of Wind',
                    'Razor Reflex', 'Snatch', 'Scar for a Scar', 'Sink Below'
                ]
            ],
            [
                'key' => 'bravo_guardian',
                'name' => 'Bravo - Showstopper Slam Blitz',
                'hero_name' => 'Bravo',
                'class' => 'Guardian',
                'format' => 'Blitz',
                'difficulty' => 'Beginner',
                'description' => 'Titanic defense paired with the Anothos hammer and devastating Crush attacks with Dominate.',
                'hero_query' => ['name' => 'Bravo', 'is_hero' => '1'],
                'weapon_queries' => ['Anothos'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Crippling Crush', 'Cartilage Crush', 'Buckling Blow', 'Disable',
                    'Spinal Crush', 'Pummel', 'Debilitate', 'Chokeslam',
                    'Emerging Power', 'Blessing of Deliverance', 'Sink Below', 'Staunch Response'
                ]
            ],
            [
                'key' => 'dash_mechanologist',
                'name' => 'Dash - Overclocked Blitz',
                'hero_name' => 'Dash',
                'class' => 'Mechanologist',
                'format' => 'Blitz',
                'difficulty' => 'Intermediate',
                'description' => 'Fast-paced steampunk synergy utilizing the Boost mechanic to grant attacks Go Again while recycling items and pistol shots.',
                'hero_query' => ['name' => 'Dash', 'is_hero' => '1'],
                'weapon_queries' => ['Teklo Plasma Pistol'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Zero to Sixty', 'Zipper Gape', 'Combustible Courier', 'Throttle',
                    'High Speed Impact', 'Over Loop', 'Pedal to the Metal', 'Teklo Pounder',
                    'Spark of Genius', 'Convection Amplifier', 'Sink Below', 'Hyper Driver'
                ]
            ],
            [
                'key' => 'viserai_runeblade',
                'name' => 'Viserai - Rune Blood Blitz',
                'hero_name' => 'Viserai',
                'class' => 'Runeblade',
                'format' => 'Blitz',
                'difficulty' => 'Advanced',
                'description' => 'Generates Runechants that deal unavoidable arcane damage alongside swift physical blade strikes.',
                'hero_query' => ['name' => 'Viserai', 'is_hero' => '1'],
                'weapon_queries' => ['Nebula Blade'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Spellblade Assault', 'Meat Axe', 'Rune Flash', 'Reduce to Runechant',
                    'Oath of the Arknight', 'Amplify the Arknight', 'Arknight Ascendancy',
                    'Mauvrion Skies', 'Bloodspill Invocation', 'Pummel', 'Sink Below'
                ]
            ],
            [
                'key' => 'kano_wizard',
                'name' => 'Kano - Aether Burn Blitz',
                'hero_name' => 'Kano',
                'class' => 'Wizard',
                'format' => 'Blitz',
                'difficulty' => 'Advanced',
                'description' => 'Manipulates the top of the deck to cast devastating arcane instant spells during any priority window.',
                'hero_query' => ['name' => 'Kano', 'is_hero' => '1'],
                'weapon_queries' => ['Crucible of Voltagem'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Zap', 'Voltic Bolt', 'Scalding Rain', 'Reverberate',
                    'Aether Spindle', 'Sonic Boom', 'Forked Lightning', 'Tome of Fyendal',
                    'Lessons in Chivalry', 'Energy Potion', 'Timesnap Potion', 'Sink Below'
                ]
            ],
            [
                'key' => 'ira_ninja',
                'name' => 'Ira - Crimson Haze Starter',
                'hero_name' => 'Ira, Crimson Haze',
                'class' => 'Ninja',
                'format' => 'Blitz',
                'difficulty' => 'Beginner',
                'description' => 'The premier Flesh and Blood tutorial deck! Passive +1 Power on second attack each turn.',
                'hero_query' => ['name' => 'Ira, Crimson Haze', 'is_hero' => '1'],
                'weapon_queries' => ['Edge of Autumn'],
                'equip_queries' => ['Ironrot Helm', 'Ironrot Plate', 'Ironrot Gauntlet', 'Ironrot Legs'],
                'main_queries' => [
                    'Flying Kick', 'Whirling Mist Blossom', 'Bittering Thorns', 'Salt the Wound',
                    'Leg Tap', 'Rising Knee Thrust', 'Head Jab', 'Scar for a Scar',
                    'Snatch', 'Springing Titan', 'Sink Below', 'Fluster Fist'
                ]
            ]
        ];

        // Enrich with live Hero image URLs from DB
        foreach ($precons as &$p) {
            $hero = $this->pdo->prepare("SELECT `unique_id`, `name`, `health`, `intelligence`, `image_url` FROM `cards` WHERE `name` LIKE :n AND `is_hero` = 1 LIMIT 1");
            $hero->execute([':n' => '%' . $p['hero_name'] . '%']);
            $hData = $hero->fetch();
            $p['hero_data'] = $hData ?: null;
            $p['image_url'] = $hData['image_url'] ?? '';
        }

        return $precons;
    }

    /**
     * Clone a preconstructed deck into user's saved decks
     */
    public function clonePreconstructedDeck(int $userId, string $preconKey): array {
        $catalog = $this->getPreconstructedCatalog();
        $target = null;
        foreach ($catalog as $item) {
            if ($item['key'] === $preconKey) {
                $target = $item;
                break;
            }
        }

        if (!$target) {
            throw new Exception("Preconstructed deck '{$preconKey}' not found.");
        }

        // 1. Find Hero Card
        $heroStmt = $this->pdo->prepare("SELECT * FROM `cards` WHERE `name` LIKE :n AND `is_hero` = 1 LIMIT 1");
        $heroStmt->execute([':n' => '%' . $target['hero_name'] . '%']);
        $heroCard = $heroStmt->fetch();

        // 2. Find Weapons
        $weapons = [];
        foreach ($target['weapon_queries'] as $wName) {
            $wStmt = $this->pdo->prepare("SELECT `unique_id` FROM `cards` WHERE `name` LIKE :n AND `is_weapon` = 1 LIMIT 1");
            $wStmt->execute([':n' => '%' . $wName . '%']);
            $wId = $wStmt->fetchColumn();
            if ($wId) $weapons[] = $wId;
        }

        // 3. Find Equipment
        $equipment = [];
        foreach ($target['equip_queries'] as $eName) {
            $eStmt = $this->pdo->prepare("SELECT `unique_id` FROM `cards` WHERE `name` LIKE :n AND `is_equipment` = 1 LIMIT 1");
            $eStmt->execute([':n' => '%' . $eName . '%']);
            $eId = $eStmt->fetchColumn();
            if ($eId) $equipment[] = $eId;
        }

        // If specific equipment not found, fallback to any basic equipment
        if (empty($equipment)) {
            $equipment = $this->pdo->query("SELECT `unique_id` FROM `cards` WHERE `is_equipment` = 1 LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
        }

        // 4. Populate 40-card Main Deck
        $mainDeck = [];
        foreach ($target['main_queries'] as $mName) {
            $mStmt = $this->pdo->prepare("SELECT `unique_id` FROM `cards` WHERE `name` LIKE :n AND `is_hero` = 0 AND `is_weapon` = 0 AND `is_equipment` = 0 LIMIT 3");
            $mStmt->execute([':n' => '%' . $mName . '%']);
            $mIds = $mStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($mIds as $mid) {
                if (count($mainDeck) < 40) {
                    $mainDeck[] = $mid;
                }
            }
        }

        // Fill remaining up to 40 cards with matching class or generic actions
        if (count($mainDeck) < 40) {
            $needed = 40 - count($mainDeck);
            $cls = $target['class'];
            $fillStmt = $this->pdo->prepare("
                SELECT `unique_id` FROM `cards` 
                WHERE (`types_json` LIKE :c OR `types_json` LIKE '%Generic%')
                  AND `is_action` = 1
                LIMIT :lim
            ");
            $fillStmt->bindValue(':c', '%' . $cls . '%');
            $fillStmt->bindValue(':lim', $needed, PDO::PARAM_INT);
            $fillStmt->execute();
            $fills = $fillStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($fills as $fid) {
                $mainDeck[] = $fid;
            }
        }

        // Repeat if still under 40
        while (count($mainDeck) < 40 && !empty($mainDeck)) {
            $mainDeck[] = $mainDeck[array_rand($mainDeck)];
        }

        $deckPayload = [
            'name' => $target['name'],
            'format' => $target['format'],
            'description' => $target['description'],
            'hero_card_id' => $heroCard['unique_id'] ?? '',
            'weapons' => $weapons,
            'equipment' => $equipment,
            'main_deck' => $mainDeck,
            'inventory' => []
        ];

        return $this->saveDeck($userId, $deckPayload);
    }
}
