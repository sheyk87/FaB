<?php
/**
 * Cards API Endpoint
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/CardService.php';

$action = $_GET['action'] ?? 'search';
$cardService = new CardService();

// Si la base de datos se acaba de crear y no tiene cartas, importar automáticamente desde card.json
if ($cardService->getCardCount() === 0) {
    require_once __DIR__ . '/../config/setup.php';
    runSetup();
}

if ($action === 'search') {
    $filters = [
        'query' => Security::sanitizeString($_GET['query'] ?? '', 100),
        'set_id' => Security::sanitizeString($_GET['set_id'] ?? '', 20),
        'pitch' => $_GET['pitch'] ?? '',
        'cost' => $_GET['cost'] ?? '',
        'class' => Security::sanitizeString($_GET['class'] ?? '', 50),
        'card_type' => Security::sanitizeString($_GET['card_type'] ?? '', 50),
        'rarity' => Security::sanitizeString($_GET['rarity'] ?? '', 10),
        'format' => Security::sanitizeString($_GET['format'] ?? '', 30),
        'sort' => Security::sanitizeString($_GET['sort'] ?? '', 30),
        'page' => (int)($_GET['page'] ?? 1),
        'limit' => (int)($_GET['limit'] ?? 36)
    ];

    $result = $cardService->searchCards($filters);
    Security::jsonResponse(array_merge(['success' => true], $result));
}

if ($action === 'detail') {
    $uid = Security::sanitizeString($_GET['id'] ?? '', 64);
    $card = $cardService->getCardByUniqueId($uid);

    if (!$card) {
        Security::jsonResponse(['success' => false, 'error' => 'Card not found'], 404);
    }

    Security::jsonResponse(['success' => true, 'card' => $card]);
}

if ($action === 'sets') {
    $sets = $cardService->getSets();
    Security::jsonResponse(['success' => true, 'sets' => $sets]);
}

if ($action === 'heroes') {
    $heroes = $cardService->getHeroes();
    Security::jsonResponse(['success' => true, 'heroes' => $heroes]);
}

Security::jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
