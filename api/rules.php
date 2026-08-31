<?php
/**
 * Rules Codex API Endpoint
 * Flesh and Blood TCG Comprehensive Rules Reference
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';

$action = $_GET['action'] ?? 'all';
$query = Security::sanitizeString($_GET['q'] ?? '', 100);

$rulesData = [
    'chapters' => [
        [
            'id' => '01-game-concepts',
            'num' => '01',
            'title' => 'Game Concepts',
            'subtitle' => 'Fundamentals, Golden Rule, Players & States',
            'sections' => [
                [
                    'id' => '1.1',
                    'title' => '1.1 General',
                    'content' => 'Flesh and Blood is a card game for two or more players. Each player takes on the role of a powerful Hero, equipping weapons and armor, and using attacks, actions, and reactions to reduce their opponent\'s health to zero.'
                ],
                [
                    'id' => '1.2',
                    'title' => '1.2 The Golden Rule',
                    'content' => 'If the text of a card contradicts the Comprehensive Rules, the card text takes precedence. If a card\'s effect prevents something from happening while another effect allows or instructs it to happen, the prevention effect takes precedence.'
                ],
                [
                    'id' => '1.3',
                    'title' => '1.3 Players & Priority',
                    'content' => 'A player is an active participant in the game. The active player is the player whose turn it currently is. Priority is the system that governs when players may perform actions or play cards/instants.'
                ]
            ]
        ],
        [
            'id' => '02-object-properties',
            'num' => '02',
            'title' => 'Object Properties',
            'subtitle' => 'Card Values, Pitch, Cost, Power & Defense',
            'sections' => [
                [
                    'id' => '2.1',
                    'title' => '2.1 Pitch Value & Color',
                    'content' => 'Every card with a pitch value can be pitched to generate Resource Points {r}.
- **Red (Pitch 1)**: Grants 1 Resource Point {r}. Powerful impact, low resources.
- **Yellow (Pitch 2)**: Grants 2 Resource Points {r}. Balanced utility.
- **Blue (Pitch 3)**: Grants 3 Resource Points {r}. High resources to fuel costly attacks and equipment.'
                ],
                [
                    'id' => '2.2',
                    'title' => '2.2 Cost & Resource Pool',
                    'content' => 'The resource cost {r} in the top-right corner specifies how many resource points must be paid to play the card. Resource points remain in a player\'s pool until the end of the turn phase.'
                ],
                [
                    'id' => '2.3',
                    'title' => '2.3 Power, Defense & Health',
                    'content' => '- **Power {p}**: The baseline damage an attack card deals on the combat chain.
- **Defense {d}**: The value a card contributes when defending an incoming attack.
- **Health {h}**: The starting life total provided by the Hero card (e.g. 20 for Young Heroes, 40 for Adult Heroes).
- **Intelligence {i}**: The number of cards the player draws at the end of their turn (standard is 4).'
                ]
            ]
        ],
        [
            'id' => '03-zones',
            'num' => '03',
            'title' => 'Zones of Play',
            'subtitle' => 'Arena, Equipment, Hand, Arsenal & Combat Chain',
            'sections' => [
                [
                    'id' => '3.1',
                    'title' => '3.1 Hero & Equipment Slots',
                    'content' => 'The Hero card sits in the Hero zone. The equipment slots surround the hero: Head, Chest, Arms, Legs, and up to two Weapon / Off-Hand slots.'
                ],
                [
                    'id' => '3.2',
                    'title' => '3.2 Hand, Deck & Pitch Zone',
                    'content' => '- **Hand**: Private zone containing playable cards.
- **Deck**: Face-down stack from which cards are drawn.
- **Pitch Zone**: Public zone where pitched cards sit during the turn. At the end of the turn, pitched cards are placed on the bottom of the owner\'s deck in any order.'
                ],
                [
                    'id' => '3.3',
                    'title' => '3.3 Arsenal Zone',
                    'content' => 'Each player has an Arsenal slot. During the End Phase, if the Arsenal is empty, the active player may place one card from their hand face-down into their Arsenal. Cards in Arsenal can be played on future turns, but cannot be pitched for resources or used to defend.'
                ],
                [
                    'id' => '3.4',
                    'title' => '3.4 Combat Chain Zone',
                    'content' => 'When an attack is declared, the Combat Chain opens. Each consecutive attack creates a Chain Link. The chain remains open until the active player closes it or takes a non-attack action without "Go Again".'
                ]
            ]
        ],
        [
            'id' => '04-game-structure',
            'num' => '04',
            'title' => 'Game Structure',
            'subtitle' => 'Turn Phases & End Step',
            'sections' => [
                [
                    'id' => '4.1',
                    'title' => '4.1 Start of Game',
                    'content' => 'Players reveal their chosen Hero and Weapons/Equipment. Each player shuffles their main deck and draws cards equal to their Hero\'s Intelligence (usually 4). First player is determined.'
                ],
                [
                    'id' => '4.2',
                    'title' => '4.2 Action Phase',
                    'content' => 'The active player begins with 1 Action Point (AP). They may play Action cards or activate Action abilities by spending 1 AP and paying any required Resource costs. Attacks open or continue the Combat Chain.'
                ],
                [
                    'id' => '4.3',
                    'title' => '4.3 End Phase',
                    'content' => '1. Active player may put one card from hand into their empty Arsenal face-down.
2. All cards in the Pitch Zone are placed on the bottom of their owner\'s deck.
3. Both players\' remaining resource points are emptied.
4. Active player draws cards until their hand count equals their Hero\'s Intelligence.
5. Action points are reset to 1, and turn passes to the opponent.'
                ]
            ]
        ],
        [
            'id' => '05-layers-cards-abilities',
            'num' => '05',
            'title' => 'Layers & Abilities',
            'subtitle' => 'Playing Cards, Instants & Priority',
            'sections' => [
                [
                    'id' => '5.1',
                    'title' => '5.1 Action Points & Go Again',
                    'content' => 'Playing an Action card requires 1 Action Point. If an action or attack card has **Go Again**, resolving that card grants 1 Action Point back to the active player, allowing them to continue attacking or acting.'
                ],
                [
                    'id' => '5.2',
                    'title' => '5.2 Instants & Priority',
                    'content' => 'Instant cards and Instant abilities do not require Action Points and can be played during any priority window, even on the opponent\'s turn.'
                ]
            ]
        ],
        [
            'id' => '06-effects',
            'num' => '06',
            'title' => 'Effects & Modifiers',
            'subtitle' => 'Continuous, Replacement & Triggered Effects',
            'sections' => [
                [
                    'id' => '6.1',
                    'title' => '6.1 Continuous Effects',
                    'content' => 'Continuous effects modify the game rules or object properties for a given duration (e.g. "+3 Power until end of turn").'
                ],
                [
                    'id' => '6.2',
                    'title' => '6.2 Replacement Effects',
                    'content' => 'Replacement effects modify how an event occurs (e.g. "If damage would be dealt, prevent X damage instead").'
                ]
            ]
        ],
        [
            'id' => '07-combat',
            'num' => '07',
            'title' => 'Combat System',
            'subtitle' => 'Attack, Defend, Reaction & Damage Steps',
            'sections' => [
                [
                    'id' => '7.1',
                    'title' => '7.1 Step 1: Attack Step',
                    'content' => 'The active player declares an attack card or weapon attack, pays the costs and action points. This creates or advances a Chain Link.'
                ],
                [
                    'id' => '7.2',
                    'title' => '7.2 Step 2: Defend Step',
                    'content' => 'The defending player chooses any number of cards from their hand and/or equipment cards to defend the attack. Total defense is calculated.'
                ],
                [
                    'id' => '7.3',
                    'title' => '7.3 Step 3: Reaction Step',
                    'content' => '- **Attack Reactions**: The attacker has priority to play Attack Reaction cards to increase power or add on-hit effects.
- **Defense Reactions**: The defender can play Defense Reaction cards from hand or arsenal to boost defense.'
                ],
                [
                    'id' => '7.4',
                    'title' => '7.4 Step 4: Damage & Hit Step',
                    'content' => 'Damage dealt = (Final Attack Power - Final Total Defense). If Damage > 0, the attack "Hits", triggering on-hit effects (Crush, Reprise, Hit abilities), and the defending hero loses that amount of life.'
                ],
                [
                    'id' => '7.5',
                    'title' => '7.5 Step 5: Chain Link Resolution & Close',
                    'content' => 'If the attack has **Go Again**, 1 Action Point is refunded. The attacker may declare a new attack to open Link 2, or close the combat chain to send all attack/defend cards to the graveyard.'
                ]
            ]
        ],
        [
            'id' => '08-keywords',
            'num' => '08',
            'title' => 'Keywords & Mechanics',
            'subtitle' => 'Comprehensive Keyword Reference',
            'sections' => [
                [
                    'id' => 'kw-go-again',
                    'title' => 'Go Again',
                    'content' => 'When this action resolves, gain 1 Action Point.'
                ],
                [
                    'id' => 'kw-dominate',
                    'title' => 'Dominate',
                    'content' => 'The defending hero can not defend this attack with more than 1 card from their hand.'
                ],
                [
                    'id' => 'kw-overpower',
                    'title' => 'Overpower',
                    'content' => 'This attack can not be defended by more than 1 action card from hand.'
                ],
                [
                    'id' => 'kw-phantasm',
                    'title' => 'Phantasm',
                    'content' => 'If this attack is defended by a non-Illusionist attack action card with 6 or more base power, destroy this attack and close the combat chain.'
                ],
                [
                    'id' => 'kw-crush',
                    'title' => 'Crush',
                    'content' => 'A keyword on Guardian attacks that triggers a bonus effect if the attack deals 4 or more damage to the opposing hero.'
                ],
                [
                    'id' => 'kw-reprise',
                    'title' => 'Reprise',
                    'content' => 'A Warrior mechanic that grants additional effects if the defending hero has defended with at least one card from their hand this chain link.'
                ],
                [
                    'id' => 'kw-arcane-barrier',
                    'title' => 'Arcane Barrier X',
                    'content' => 'If your hero would take arcane damage, you may pay X resources to prevent X arcane damage.'
                ],
                [
                    'id' => 'kw-ward',
                    'title' => 'Ward X',
                    'content' => 'If your hero would be dealt damage, prevent X damage that would be dealt, then destroy this object.'
                ],
                [
                    'id' => 'kw-blood-debt',
                    'title' => 'Blood Debt',
                    'content' => 'While this card is in your banished zone, at the end of your turn, lose 1 life unless you played a Shadow card.'
                ],
                [
                    'id' => 'kw-opt',
                    'title' => 'Opt X',
                    'content' => 'Look at the top X cards of your deck. You may put any number on the top or bottom of your deck in any order.'
                ],
                [
                    'id' => 'kw-boost',
                    'title' => 'Boost',
                    'content' => 'As an additional cost to play this, you may banish the top card of your deck. If it was a Mechanologist card, this attack gains Go Again.'
                ],
                [
                    'id' => 'kw-stealth',
                    'title' => 'Stealth',
                    'content' => 'An Assassin trait that activates special interactions and combo cards.'
                ]
            ]
        ],
        [
            'id' => '09-additional-rules',
            'num' => '09',
            'title' => 'Formats & Additional Rules',
            'subtitle' => 'Blitz, Classic Constructed & Tokens',
            'sections' => [
                [
                    'id' => '9.1',
                    'title' => '9.1 Blitz Format',
                    'content' => '- 1 Young Hero (starting at 20 health).
- Exactly 40 cards in main deck (maximum 2 copies of any unique card name + pitch).
- Up to 11 equipment / weapon cards.
- Fast-paced 30-minute games.'
                ],
                [
                    'id' => '9.2',
                    'title' => '9.2 Classic Constructed (CC) Format',
                    'content' => '- 1 Adult Hero (starting at 40 health).
- Minimum 60 cards in main deck (maximum 3 copies of any unique card name + pitch).
- Up to 80 cards in total deck pool (including equipment, weapons, and inventory).
- Premier competitive 55-minute format.'
                ]
            ]
        ],
        [
            'id' => 'glossary',
            'num' => '10',
            'title' => 'Glossary',
            'subtitle' => 'Index of Terms',
            'sections' => [
                [
                    'id' => 'gl-ap',
                    'title' => 'Action Point (AP)',
                    'content' => 'Currency required to perform actions on your turn. Players start each turn with 1 AP.'
                ],
                [
                    'id' => 'gl-resource',
                    'title' => 'Resource Point {r}',
                    'content' => 'Energy generated by pitching cards, used to pay for card costs and ability activations.'
                ],
                [
                    'id' => 'gl-chain-link',
                    'title' => 'Chain Link',
                    'content' => 'An individual attack and its defending cards within an open Combat Chain.'
                ],
                [
                    'id' => 'gl-arsenal',
                    'title' => 'Arsenal',
                    'content' => 'A staging slot for a card placed face-down at the end of the turn.'
                ]
            ]
        ]
    ]
];

// If search query is provided, filter sections
if (!empty($query)) {
    $qLower = mb_strtolower($query);
    $matchedSections = [];

    foreach ($rulesData['chapters'] as $ch) {
        foreach ($ch['sections'] as $sec) {
            if (
                mb_stripos($sec['title'], $qLower) !== false ||
                mb_stripos($sec['content'], $qLower) !== false ||
                mb_stripos($ch['title'], $qLower) !== false
            ) {
                $matchedSections[] = [
                    'chapter_id' => $ch['id'],
                    'chapter_title' => $ch['title'],
                    'section' => $sec
                ];
            }
        }
    }

    Security::jsonResponse([
        'success' => true,
        'query' => $query,
        'matches' => $matchedSections
    ]);
}

Security::jsonResponse(array_merge(['success' => true], $rulesData));
