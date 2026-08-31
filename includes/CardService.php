<?php
/**
 * Card Service
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CardService {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Search and filter cards with pagination
     */
    public function searchCards(array $filters = []): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 40)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Search text (Name or Rules text)
        if (!empty($filters['query'])) {
            $where[] = "(`name` LIKE :q_name OR `functional_text_plain` LIKE :q_text OR `type_text` LIKE :q_type)";
            $params[':q_name'] = '%' . $filters['query'] . '%';
            $params[':q_text'] = '%' . $filters['query'] . '%';
            $params[':q_type'] = '%' . $filters['query'] . '%';
        }

        // Set ID
        if (!empty($filters['set_id'])) {
            $where[] = "`set_id` = :set_id";
            $params[':set_id'] = $filters['set_id'];
        }

        // Pitch (1 = Red, 2 = Yellow, 3 = Blue)
        if (isset($filters['pitch']) && $filters['pitch'] !== '' && $filters['pitch'] !== 'all') {
            $where[] = "`pitch` = :pitch";
            $params[':pitch'] = (int)$filters['pitch'];
        }

        // Cost
        if (isset($filters['cost']) && $filters['cost'] !== '' && $filters['cost'] !== 'all') {
            $where[] = "`cost` = :cost";
            $params[':cost'] = (string)$filters['cost'];
        }

        // Class Filter
        if (!empty($filters['class']) && $filters['class'] !== 'all') {
            $where[] = "(`types_json` LIKE :class OR `type_text` LIKE :class2)";
            $params[':class'] = '%' . $filters['class'] . '%';
            $params[':class2'] = '%' . $filters['class'] . '%';
        }

        // Card Type (Hero, Weapon, Equipment, Action, Attack, Defense Reaction, Instant, Item, Aura)
        if (!empty($filters['card_type']) && $filters['card_type'] !== 'all') {
            switch (strtolower($filters['card_type'])) {
                case 'hero':
                    $where[] = "`is_hero` = 1";
                    break;
                case 'weapon':
                    $where[] = "`is_weapon` = 1";
                    break;
                case 'equipment':
                    $where[] = "`is_equipment` = 1";
                    break;
                case 'action':
                    $where[] = "`is_action` = 1";
                    break;
                case 'attack':
                    $where[] = "`is_attack` = 1";
                    break;
                case 'defense_reaction':
                case 'defense reaction':
                    $where[] = "`is_defense_reaction` = 1";
                    break;
                case 'instant':
                    $where[] = "`is_instant` = 1";
                    break;
                case 'item':
                    $where[] = "`is_item` = 1";
                    break;
                case 'aura':
                    $where[] = "`is_aura` = 1";
                    break;
                default:
                    $where[] = "`types_json` LIKE :type_filter";
                    $params[':type_filter'] = '%' . $filters['card_type'] . '%';
            }
        }

        // Rarity
        if (!empty($filters['rarity']) && $filters['rarity'] !== 'all') {
            $where[] = "`rarity` = :rarity";
            $params[':rarity'] = $filters['rarity'];
        }

        // Format Legality
        if (!empty($filters['format'])) {
            if ($filters['format'] === 'Blitz') {
                $where[] = "`blitz_legal` = 1";
            } elseif ($filters['format'] === 'Classic Constructed' || $filters['format'] === 'CC') {
                $where[] = "`cc_legal` = 1";
            } elseif ($filters['format'] === 'Commoner') {
                $where[] = "`commoner_legal` = 1";
            }
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM `cards` {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Order
        $orderBy = "`name` ASC, `pitch` ASC";
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'name_desc': $orderBy = "`name` DESC"; break;
                case 'pitch_asc': $orderBy = "`pitch` ASC, `name` ASC"; break;
                case 'pitch_desc': $orderBy = "`pitch` DESC, `name` ASC"; break;
                case 'cost_asc': $orderBy = "CAST(`cost` AS UNSIGNED) ASC, `name` ASC"; break;
                case 'cost_desc': $orderBy = "CAST(`cost` AS UNSIGNED) DESC, `name` ASC"; break;
                case 'power_desc': $orderBy = "CAST(`power` AS UNSIGNED) DESC"; break;
                case 'defense_desc': $orderBy = "CAST(`defense` AS UNSIGNED) DESC"; break;
                case 'set_asc': $orderBy = "`set_id` ASC, `collector_number` ASC"; break;
            }
        }

        // Fetch items
        $sql = "
            SELECT `id`, `unique_id`, `name`, `color`, `pitch`, `cost`, `power`, `defense`,
                   `health`, `intelligence`, `arcane`, `type_text`, `functional_text`,
                   `types_json`, `keywords_json`, `set_id`, `collector_number`, `rarity`,
                   `image_url`, `is_hero`, `is_weapon`, `is_equipment`, `is_action`,
                   `is_attack`, `is_defense_reaction`, `is_instant`, `is_item`, `is_aura`,
                   `blitz_legal`, `cc_legal`, `commoner_legal`
            FROM `cards`
            {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $cards = $stmt->fetchAll();

        // Format json arrays
        foreach ($cards as &$c) {
            $c['types'] = !empty($c['types_json']) ? json_decode($c['types_json'], true) : [];
            $c['keywords'] = !empty($c['keywords_json']) ? json_decode($c['keywords_json'], true) : [];
            unset($c['types_json'], $c['keywords_json']);
        }

        return [
            'cards' => $cards,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get single card by unique_id
     */
    public function getCardByUniqueId(string $uniqueId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM `cards` WHERE `unique_id` = :uid LIMIT 1");
        $stmt->execute([':uid' => $uniqueId]);
        $card = $stmt->fetch();
        if (!$card) return null;

        $card['types'] = !empty($card['types_json']) ? json_decode($card['types_json'], true) : [];
        $card['keywords'] = !empty($card['keywords_json']) ? json_decode($card['keywords_json'], true) : [];
        unset($card['types_json'], $card['keywords_json']);
        return $card;
    }

    /**
     * Get multiple cards by unique IDs (for deck loading)
     */
    public function getCardsByUniqueIds(array $uniqueIds): array {
        if (empty($uniqueIds)) return [];

        $uniqueIds = array_values(array_unique(array_filter($uniqueIds)));
        if (empty($uniqueIds)) return [];

        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM `cards` WHERE `unique_id` IN ($placeholders)");
        $stmt->execute($uniqueIds);
        $cards = $stmt->fetchAll();

        $map = [];
        foreach ($cards as $card) {
            $card['types'] = !empty($card['types_json']) ? json_decode($card['types_json'], true) : [];
            $card['keywords'] = !empty($card['keywords_json']) ? json_decode($card['keywords_json'], true) : [];
            unset($card['types_json'], $card['keywords_json']);
            $map[$card['unique_id']] = $card;
        }

        return $map;
    }

    /**
     * Get list of all card sets with card counts
     */
    public function getSets(): array {
        $stmt = $this->pdo->query("SELECT `id`, `name`, `card_count` FROM `sets` ORDER BY `name` ASC");
        return $stmt->fetchAll();
    }

    /**
     * Get all available Heroes
     */
    public function getHeroes(): array {
        $stmt = $this->pdo->query("
            SELECT `unique_id`, `name`, `health`, `intelligence`, `image_url`, `type_text`, `types_json`
            FROM `cards`
            WHERE `is_hero` = 1
            ORDER BY `name` ASC
        ");
        $heroes = $stmt->fetchAll();
        foreach ($heroes as &$h) {
            $h['types'] = !empty($h['types_json']) ? json_decode($h['types_json'], true) : [];
            unset($h['types_json']);
        }
        return $heroes;
    }
}
