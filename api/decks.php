<?php
/**
 * Decks API Endpoint
 * Flesh and Blood TCG Sandbox
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/DeckService.php';

$user = Security::requireAuth();
$userId = (int)$user['id'];

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$deckService = new DeckService();

if ($action === 'list') {
    $decks = $deckService->getUserDecks($userId);
    Security::jsonResponse(['success' => true, 'decks' => $decks]);
}

if ($action === 'precons') {
    $catalog = $deckService->getPreconstructedCatalog();
    Security::jsonResponse(['success' => true, 'precons' => $catalog]);
}

if ($action === 'clone_precon' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();
    $key = trim($input['precon_key'] ?? '');

    if (empty($key)) {
        Security::jsonResponse(['success' => false, 'error' => 'Preconstructed deck key required'], 400);
    }

    try {
        $deck = $deckService->clonePreconstructedDeck($userId, $key);
        Security::jsonResponse(['success' => true, 'deck' => $deck]);
    } catch (Exception $e) {
        Security::jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'get') {
    $deckId = (int)($_GET['id'] ?? 0);
    $deck = $deckService->getDeck($deckId, $userId);

    if (!$deck) {
        Security::jsonResponse(['success' => false, 'error' => 'Deck not found'], 404);
    }

    Security::jsonResponse(['success' => true, 'deck' => $deck]);
}

if ($action === 'save' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();
    $deckId = !empty($input['id']) ? (int)$input['id'] : null;

    if (empty($input['name'])) {
        Security::jsonResponse(['success' => false, 'error' => 'Deck name is required'], 400);
    }

    try {
        $saved = $deckService->saveDeck($userId, $input, $deckId);
        Security::jsonResponse(['success' => true, 'deck' => $saved]);
    } catch (Exception $e) {
        Security::jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'delete' && $method === 'POST') {
    Security::requireCsrf();
    $deckId = (int)($_GET['id'] ?? 0);

    $deleted = $deckService->deleteDeck($deckId, $userId);
    if (!$deleted) {
        Security::jsonResponse(['success' => false, 'error' => 'Could not delete deck'], 400);
    }

    Security::jsonResponse(['success' => true, 'message' => 'Deck deleted.']);
}

if ($action === 'import' && $method === 'POST') {
    Security::requireCsrf();
    $input = Security::getJsonInput();
    $rawText = $input['text'] ?? '';
    $format = in_array($input['format'] ?? '', ['Blitz', 'Classic Constructed', 'Commoner'], true) ? $input['format'] : 'Blitz';
    $deckName = Security::sanitizeString($input['name'] ?? 'Imported Deck', 100);

    if (empty($rawText)) {
        Security::jsonResponse(['success' => false, 'error' => 'Import text is empty.'], 400);
    }

    $cardService = new CardService();
    $lines = preg_split('/\r\n|\r|\n/', $rawText);

    $heroId = null;
    $weapons = [];
    $equipment = [];
    $mainDeck = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, '//')) continue;

        // Pattern: "1x Card Name (Pitch)" or "1 Card Name"
        if (preg_match('/^(\d+)[xX]?\s+(.+)$/', $line, $matches)) {
            $qty = (int)$matches[1];
            $cardName = trim($matches[2]);
            $pitch = null;

            if (preg_match('/\(([1-3])\)$/', $cardName, $pMatch)) {
                $pitch = (int)$pMatch[1];
                $cardName = trim(preg_replace('/\(([1-3])\)$/', '', $cardName));
            }

            // Search card in DB
            $search = $cardService->searchCards(['query' => $cardName, 'pitch' => $pitch !== null ? (string)$pitch : '', 'limit' => 1]);
            if (!empty($search['cards'][0])) {
                $c = $search['cards'][0];
                if (!empty($c['is_hero']) && !$heroId) {
                    $heroId = $c['unique_id'];
                } elseif (!empty($c['is_weapon'])) {
                    for ($k = 0; $k < $qty; $k++) $weapons[] = $c['unique_id'];
                } elseif (!empty($c['is_equipment'])) {
                    for ($k = 0; $k < $qty; $k++) $equipment[] = $c['unique_id'];
                } else {
                    for ($k = 0; $k < $qty; $k++) $mainDeck[] = $c['unique_id'];
                }
            }
        }
    }

    $deckPayload = [
        'name' => $deckName,
        'format' => $format,
        'hero_card_id' => $heroId,
        'weapons' => $weapons,
        'equipment' => $equipment,
        'main_deck' => $mainDeck,
        'inventory' => []
    ];

    $saved = $deckService->saveDeck($userId, $deckPayload);
    Security::jsonResponse(['success' => true, 'deck' => $saved]);
}

Security::jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
