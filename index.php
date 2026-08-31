<?php
/**
 * Flesh and Blood TCG Sandbox
 * Main Single Page Application Gateway
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Flesh and Blood TCG Sandbox - Authentic multiplayer card game engine, deck builder, card compendium, and official rules codex." />
  <title>Flesh and Blood TCG | Sandbox Arena</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;900&family=JetBrains+Mono:wght@400;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- Stylesheets -->
  <link rel="stylesheet" href="assets/css/main.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/menu.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/compendium.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/deckbuilder.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/game.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/rules.css?v=<?= time() ?>" />
</head>
<body>

  <!-- Top App Navigation Header -->
  <header class="app-header">
    <div class="brand" data-view-target="menu">
      <svg class="brand-logo" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <polygon points="50,5 95,25 95,75 50,95 5,75 5,25" stroke="#d4af37" stroke-width="4" fill="rgba(212,175,55,0.15)"/>
        <path d="M50,20 L75,40 L60,80 L40,80 L25,40 Z" fill="#e53935" stroke="#d4af37" stroke-width="2"/>
        <circle cx="50" cy="50" r="10" fill="#f5d77f"/>
      </svg>
      <div>
        <h1 class="brand-title">FLESH AND BLOOD</h1>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="brand-badge">TCG SANDBOX</span>
          <span style="font-size:0.7rem; color:var(--text-dim);">Server-Authoritative</span>
        </div>
      </div>
    </div>

    <nav class="nav-links">
      <button class="nav-btn active" data-view-target="menu">🏰 Home</button>
      <button class="nav-btn" data-view-target="deckbuilder">🎴 Deck Builder</button>
      <button class="nav-btn" data-view-target="compendium">📚 Card Library</button>
      <button class="nav-btn" data-view-target="rules">📜 Rules Codex</button>
      <button class="nav-btn" id="nav-btn-arena" style="color:var(--text-gold); border:1px solid var(--border-gold);" data-view-target="game">⚔️ Battlefield</button>
      <button class="nav-btn" id="btn-open-settings" title="Settings">⚙️ Settings</button>
    </nav>

    <div class="user-controls" id="user-profile-widget">
      <!-- Populated dynamically via JS -->
      <button class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" id="btn-open-login">Sign In</button>
      <button class="btn btn-primary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" id="btn-quick-play">Quick Play</button>
    </div>
  </header>

  <!-- Main View Container -->
  <main class="view-container">

    <!-- ========================================== -->
    <!-- VIEW 1: MAIN MENU & KPI DASHBOARD          -->
    <!-- ========================================== -->
    <section class="view-section active" id="view-menu">
      <div class="hero-banner">
        <h2 class="hero-tagline">ENTER THE ARENA OF RATHE</h2>
        <p class="hero-subtext">A true tabletop Flesh and Blood experience with real-time matchmaking, sandbox freedom, server validation, and full official rules codex.</p>
      </div>

      <!-- Real-Time Telemetry KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Online Players</span>
            <span class="kpi-pulse"></span>
          </div>
          <div class="kpi-value" id="kpi-online">1</div>
          <div class="kpi-trend">Live in Rathe</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Searching Match</span>
            <span class="kpi-pulse queue"></span>
          </div>
          <div class="kpi-value" id="kpi-queue">0</div>
          <div class="kpi-trend">In Matchmaking Queue</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Active Battles</span>
            <span class="kpi-pulse battles"></span>
          </div>
          <div class="kpi-value" id="kpi-battles">0</div>
          <div class="kpi-trend">Combat Chains open</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Total Decks</span>
            <span class="glyph glyph-resource">🎴</span>
          </div>
          <div class="kpi-value" id="kpi-decks">0</div>
          <div class="kpi-trend">Constructed by players</div>
        </div>
      </div>

      <!-- Main Navigation Cards -->
      <div class="menu-grid">
        <div class="menu-card featured" id="menu-card-matchmaking">
          <span class="menu-card-badge">Multiplayer 1v1</span>
          <div class="menu-card-icon">⚔️</div>
          <h3 class="menu-card-title">Play Online</h3>
          <p class="menu-card-desc">Enter the automatic 1v1 matchmaking queue or create a private custom room with a room code to battle a friend.</p>
          <button class="btn btn-primary menu-card-action" id="btn-play-online">Find Match / Create Room</button>
        </div>

        <div class="menu-card" data-view-target="deckbuilder">
          <span class="menu-card-badge">Constructor</span>
          <div class="menu-card-icon">🎴</div>
          <h3 class="menu-card-title">Deck Builder</h3>
          <p class="menu-card-desc">Construct and optimize your decks with format validation for Blitz and Classic Constructed, pitch ratio curve charts, and import/export.</p>
          <button class="btn btn-secondary menu-card-action" data-view-target="deckbuilder">Open Deck Builder</button>
        </div>

        <div class="menu-card" data-view-target="compendium">
          <span class="menu-card-badge">4,950+ Cards</span>
          <div class="menu-card-icon">📚</div>
          <h3 class="menu-card-title">Card Library</h3>
          <p class="menu-card-desc">Browse all Flesh and Blood cards across 90 sets. Filter by pitch color (Red, Yellow, Blue), cost, class, card type, and keywords.</p>
          <button class="btn btn-secondary menu-card-action" data-view-target="compendium">Explore Compendium</button>
        </div>

        <div class="menu-card" data-view-target="rules">
          <span class="menu-card-badge">Official CR</span>
          <div class="menu-card-icon">📜</div>
          <h3 class="menu-card-title">Rules Codex</h3>
          <p class="menu-card-desc">Complete interactive Flesh and Blood Comprehensive Rules reader with quick keyword lookups (Go Again, Dominate, Phantasm, Reprise, Arcane Barrier).</p>
          <button class="btn btn-secondary menu-card-action" data-view-target="rules">Read Game Rules</button>
        </div>
      </div>
    </section>

    <!-- ========================================== -->
    <!-- VIEW 2: CARD COMPENDIUM & LIBRARY          -->
    <!-- ========================================== -->
    <section class="view-section" id="view-compendium">
      <div class="compendium-header">
        <div class="compendium-title-bar">
          <h2 class="page-title">Card Compendium</h2>
          <div style="color:var(--text-muted); font-size:0.95rem;">Browse all cards from <code>card.json</code></div>
        </div>

        <!-- Filter Toolbar -->
        <div class="filter-toolbar">
          <div class="filter-row">
            <div class="search-input-wrap">
              <span class="search-icon">🔍</span>
              <input type="text" class="search-input" id="comp-search-input" placeholder="Search cards by name or rules text..." />
            </div>

            <select class="filter-select" id="comp-set-select">
              <option value="">All Sets</option>
            </select>

            <select class="filter-select" id="comp-class-select">
              <option value="all">All Classes</option>
              <option value="Generic">Generic</option>
              <option value="Brute">Brute</option>
              <option value="Guardian">Guardian</option>
              <option value="Ninja">Ninja</option>
              <option value="Warrior">Warrior</option>
              <option value="Wizard">Wizard</option>
              <option value="Runeblade">Runeblade</option>
              <option value="Mechanologist">Mechanologist</option>
              <option value="Ranger">Ranger</option>
              <option value="Illusionist">Illusionist</option>
              <option value="Assassin">Assassin</option>
              <option value="Merchant">Merchant</option>
            </select>

            <select class="filter-select" id="comp-type-select">
              <option value="all">All Card Types</option>
              <option value="hero">Hero</option>
              <option value="weapon">Weapon</option>
              <option value="equipment">Equipment</option>
              <option value="action">Action</option>
              <option value="attack">Attack Action</option>
              <option value="defense_reaction">Defense Reaction</option>
              <option value="instant">Instant</option>
              <option value="item">Item</option>
              <option value="aura">Aura</option>
            </select>

            <select class="filter-select" id="comp-cost-select">
              <option value="all">Any Cost</option>
              <option value="0">0 Cost</option>
              <option value="1">1 Cost</option>
              <option value="2">2 Cost</option>
              <option value="3">3 Cost</option>
              <option value="4">4+ Cost</option>
            </select>
          </div>

          <div class="filter-row" style="justify-content: space-between;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
              <span style="font-size:0.85rem; font-weight:700; color:var(--text-muted);">Pitch:</span>
              <div class="pitch-btn-group">
                <button class="pitch-toggle-btn active-all" data-pitch="all">All</button>
                <button class="pitch-toggle-btn" data-pitch="1"><span class="glyph glyph-pitch-1">1</span> Red</button>
                <button class="pitch-toggle-btn" data-pitch="2"><span class="glyph glyph-pitch-2">2</span> Yellow</button>
                <button class="pitch-toggle-btn" data-pitch="3"><span class="glyph glyph-pitch-3">3</span> Blue</button>
              </div>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
              <span style="font-size:0.85rem; font-weight:700; color:var(--text-muted);">Sort:</span>
              <select class="filter-select" id="comp-sort-select">
                <option value="">Name (A - Z)</option>
                <option value="name_desc">Name (Z - A)</option>
                <option value="pitch_asc">Pitch (Red &rarr; Blue)</option>
                <option value="pitch_desc">Pitch (Blue &rarr; Red)</option>
                <option value="cost_asc">Cost (Low &rarr; High)</option>
                <option value="cost_desc">Cost (High &rarr; Low)</option>
                <option value="power_desc">Power (High &rarr; Low)</option>
                <option value="defense_desc">Defense (High &rarr; Low)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Grid -->
      <div class="card-grid" id="compendium-card-grid"></div>

      <!-- Pagination Bar -->
      <div class="pagination-bar">
        <button class="page-btn" id="comp-prev-page" disabled>&larr; Previous</button>
        <span class="page-info" id="comp-page-info">Page 1 of 1</span>
        <button class="page-btn" id="comp-next-page">Next &rarr;</button>
      </div>
    </section>

    <!-- ========================================== -->
    <!-- VIEW 3: DECK BUILDER                       -->
    <!-- ========================================== -->
    <section class="view-section" id="view-deckbuilder">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 class="page-title">Deck Builder</h2>
          <div style="font-size:0.9rem; color:var(--text-muted);">Select cards to build Blitz or Classic Constructed decks</div>
        </div>
        <div style="display:flex; gap:0.75rem; align-items:center;">
          <select class="filter-select" id="deck-selector" style="min-width:220px;"></select>
          <button class="btn btn-primary" id="btn-open-precons" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; border:none;">⚡ Prebuilt Decks</button>
          <button class="btn btn-secondary" id="btn-new-deck">+ New</button>
          <button class="btn btn-secondary" id="btn-import-deck">📥 Import</button>
          <button class="btn btn-secondary" id="btn-export-deck">📤 Export</button>
          <button class="btn btn-primary" id="btn-save-deck">💾 Save Deck</button>
          <button class="btn btn-danger" id="btn-delete-deck" style="padding:0.65rem 0.8rem;" title="Delete Deck">🗑️</button>
        </div>
      </div>

      <div class="deckbuilder-layout">
        <!-- Left: Card Pool Browser -->
        <div class="card-pool-panel">
          <div class="pool-search-row">
            <div class="search-input-wrap" style="flex:1;">
              <span class="search-icon">🔍</span>
              <input type="text" class="search-input" id="pool-search-input" placeholder="Search card pool..." />
            </div>
            <select class="filter-select" id="pool-type-select" style="min-width:130px;">
              <option value="all">All Types</option>
              <option value="action">Action</option>
              <option value="attack">Attack</option>
              <option value="defense_reaction">Defense Reaction</option>
              <option value="instant">Instant</option>
              <option value="equipment">Equipment</option>
              <option value="weapon">Weapon</option>
              <option value="hero">Hero</option>
            </select>
          </div>
          <div class="pool-grid" id="deck-pool-grid"></div>
        </div>

        <!-- Right: Active Deck Structure -->
        <div class="deck-roster-panel">
          <div class="deck-header-box">
            <input type="text" class="deck-title-input" id="deck-name-input" value="My Blitz Deck" placeholder="Deck Name..." />
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <select class="deck-format-select" id="deck-format-select">
                <option value="Blitz">Format: Blitz (40 cards)</option>
                <option value="Classic Constructed">Format: Classic Constructed (60+ cards)</option>
                <option value="Commoner">Format: Commoner (40 cards)</option>
              </select>
              <div id="deck-validation-badge" class="validation-badge valid">
                <span>✅</span> <span>Legal for Blitz</span>
              </div>
            </div>
          </div>

          <!-- Hero Slot -->
          <div id="hero-slot-box" class="hero-slot-card">
            <!-- Populated via JS -->
          </div>

          <!-- Weapons & Equipment -->
          <div>
            <div class="deck-section-title">Weapons & Equipment</div>
            <div class="hero-equipment-grid" id="deck-equipment-list"></div>
          </div>

          <!-- Statistics Charts -->
          <div class="deck-stats-box">
            <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:700;">
              <span>Pitch Ratio</span>
              <span id="deck-pitch-total-label"></span>
            </div>
            <div class="pitch-bar-chart">
              <div class="pitch-bar-seg red" id="pitch-bar-red" style="width: 33%;"></div>
              <div class="pitch-bar-seg yellow" id="pitch-bar-yellow" style="width: 33%;"></div>
              <div class="pitch-bar-seg blue" id="pitch-bar-blue" style="width: 34%;"></div>
            </div>
            <div class="pitch-legend-row">
              <span style="color:#ff8a80;" id="pitch-count-red">Red: 0</span>
              <span style="color:#fff59d;" id="pitch-count-yellow">Yellow: 0</span>
              <span style="color:#90caf9;" id="pitch-count-blue">Blue: 0</span>
            </div>
          </div>

          <!-- Main Deck List -->
          <div>
            <div class="deck-section-title">
              <span id="deck-main-count-label">Main Deck (0 cards)</span>
            </div>
            <div id="deck-main-cards-list"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================== -->
    <!-- VIEW 4: SANDBOX BATTLEFIELD ARENA          -->
    <!-- ========================================== -->
    <section class="view-section" id="view-game">
      <div class="game-arena">

        <!-- Top Arena Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:0.5rem 1rem; background:var(--bg-glass-heavy); border-bottom:1px solid var(--border-dark); flex-wrap:wrap; gap:0.5rem;">
          <div style="display:flex; align-items:center; gap:1rem;">
            <span class="brand-badge" id="arena-room-code-badge">Room: FAB-XXXXXX</span>
            <span style="font-weight:700; font-size:0.9rem;" id="arena-turn-indicator">Turn 1 • Active</span>
          </div>

          <!-- Timers Cluster -->
          <div class="timers-bar-cluster">
            <span class="match-time-badge" id="arena-match-duration">⏱️ 00:00</span>
            <span style="color:var(--border-dark);">|</span>
            <div class="turn-timer-wrap" title="Turn Timer">
              <span class="turn-timer-clock" id="arena-turn-clock">01:15</span>
              <div class="turn-timer-progress-bar">
                <div class="turn-timer-progress-fill" id="arena-turn-progress-fill"></div>
              </div>
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:0.5rem;">
            <!-- Dice Roller Bar -->
            <div class="dice-roller-bar">
              <button class="btn btn-secondary" style="padding:2px 8px; font-size:0.75rem;" id="btn-roll-d6">🎲 D6</button>
              <button class="btn btn-secondary" style="padding:2px 8px; font-size:0.75rem;" id="btn-roll-d20">🎲 D20</button>
              <button class="btn btn-secondary" style="padding:2px 8px; font-size:0.75rem;" id="btn-flip-coin">🪙 Coin</button>
            </div>
            <button class="btn btn-primary" style="padding:0.4rem 1rem; font-size:0.85rem;" id="btn-arena-end-turn">⏳ End Turn</button>
            <button class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.85rem;" onclick="document.getElementById('arena-drawer').classList.toggle('open')">📜 Log / Chat</button>
            <button class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.85rem; border-color:var(--border-dark);" id="btn-arena-leave" title="Leave Battlefield">🚪 Leave</button>
            <button class="btn btn-danger" style="padding:0.4rem 0.8rem; font-size:0.85rem;" id="btn-arena-concede">🏳️ Concede</button>
          </div>
        </div>

        <!-- Battlefield Board Surface -->
        <div class="arena-board">

          <!-- 1. OPPONENT SECTOR (Top) -->
          <div class="player-sector opponent">
            <div class="hero-hub">
              <div class="hero-portrait-card" id="p2-hero-portrait"></div>
              <div>
                <div style="font-family:var(--font-title); font-weight:700; font-size:1.1rem; color:#fff;" id="p2-hero-name">Opponent Hero</div>
                <div class="dials-cluster">
                  <div class="dial-item">
                    <span style="font-size:0.75rem; color:var(--color-life);">❤️ Life</span>
                    <span class="dial-val life" id="p2-life-val">20</span>
                  </div>
                  <div style="display:flex; gap:0.5rem;">
                    <div class="dial-item">
                      <span style="font-size:0.75rem; color:var(--color-resource);">⚡ Res</span>
                      <span class="dial-val resource" id="p2-res-val">0</span>
                    </div>
                    <div class="dial-item">
                      <span style="font-size:0.75rem; color:var(--color-ap);">⭐ AP</span>
                      <span class="dial-val ap" id="p2-ap-val">0</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Opponent Equipment -->
            <div class="equipment-cluster" id="p2-equipment-cluster"></div>

            <!-- Opponent Zones -->
            <div class="zones-cluster">
              <div class="zone-box" id="p2-arsenal-box">
                <div class="zone-box-count">0</div>
                <div class="zone-box-title">Arsenal</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p2-pitch-count">0</div>
                <div class="zone-box-title">Pitch</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p2-gy-count">0</div>
                <div class="zone-box-title">Graveyard</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p2-ban-count">0</div>
                <div class="zone-box-title">Banish</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p2-deck-count">40</div>
                <div class="zone-box-title">Deck</div>
              </div>
            </div>
          </div>

          <!-- Opponent Hand Fan (Masked cards) -->
          <div class="player-hand-container" id="p2-hand-container" style="min-height:90px;"></div>

          <!-- 2. CENTRAL COMBAT CHAIN ARENA -->
          <div class="combat-chain-arena" id="arena-combat-chain-box">
            <div class="chain-links-row" id="arena-chain-links-row">
              <div class="chain-empty-notice">Combat Chain is currently closed.</div>
            </div>
            <div class="chain-controls-bar">
              <button class="btn btn-secondary" style="padding:4px 10px; font-size:0.8rem; display:none;" id="btn-close-chain">⛓️ Close Combat Chain</button>
            </div>
          </div>

          <!-- 3. PLAYER HAND FAN (Bottom) -->
          <div class="player-hand-container" id="p1-hand-container"></div>

          <!-- 4. PLAYER SECTOR (Bottom) -->
          <div class="player-sector">
            <div class="hero-hub">
              <div class="hero-portrait-card" id="p1-hero-portrait"></div>
              <div>
                <div style="font-family:var(--font-title); font-weight:700; font-size:1.1rem; color:var(--text-gold);" id="p1-hero-name">Your Hero</div>
                <div class="dials-cluster">
                  <div class="dial-item">
                    <button class="dial-btn" data-life-delta="-1" data-life-target="player">-</button>
                    <span class="dial-val life" id="p1-life-val">20</span>
                    <button class="dial-btn" data-life-delta="1" data-life-target="player">+</button>
                  </div>
                  <div style="display:flex; gap:0.5rem;">
                    <div class="dial-item">
                      <button class="dial-btn" id="btn-res-minus">-</button>
                      <span class="dial-val resource" id="p1-res-val">0</span>
                      <button class="dial-btn" id="btn-res-plus">+</button>
                    </div>
                    <div class="dial-item">
                      <button class="dial-btn" id="btn-ap-minus">-</button>
                      <span class="dial-val ap" id="p1-ap-val">1</span>
                      <button class="dial-btn" id="btn-ap-plus">+</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Player Equipment -->
            <div class="equipment-cluster" id="p1-equipment-cluster"></div>

            <!-- Player Zones -->
            <div class="zones-cluster">
              <div class="zone-box" id="p1-arsenal-box">
                <div class="zone-box-count">0</div>
                <div class="zone-box-title">Arsenal</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p1-pitch-count">0</div>
                <div class="zone-box-title">Pitch</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p1-gy-count">0</div>
                <div class="zone-box-title">Graveyard</div>
              </div>
              <div class="zone-box">
                <div class="zone-box-count" id="p1-ban-count">0</div>
                <div class="zone-box-title">Banish</div>
              </div>
              <div class="zone-box" style="border-color:var(--border-gold);" id="p1-deck-box">
                <div class="zone-box-count" id="p1-deck-count">40</div>
                <div class="zone-box-title">Deck</div>
                <div style="display:flex; gap:2px; margin-top:2px;">
                  <button class="q-act-btn" id="btn-deck-draw" title="Draw 1 Card">Draw</button>
                  <button class="q-act-btn" id="btn-deck-shuffle" title="Shuffle Deck">🔀</button>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Sidebar Drawer (Combat Log & Chat) -->
        <aside class="game-sidebar-drawer" id="arena-drawer">
          <div class="drawer-tabs">
            <button class="drawer-tab-btn active" data-drawer-tab="log">📜 Combat Log</button>
            <button class="drawer-tab-btn" data-drawer-tab="chat">💬 Match Chat</button>
            <button class="btn btn-secondary" style="border:none; padding:0.5rem;" onclick="document.getElementById('arena-drawer').classList.remove('open')">✕</button>
          </div>

          <div class="drawer-content" id="drawer-log-pane">
            <div id="arena-log-messages" style="display:flex; flex-direction:column; gap:0.4rem;"></div>
          </div>

          <div class="drawer-content" id="drawer-chat-pane" style="display:none;">
            <div id="arena-chat-messages" style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:0.4rem;"></div>
            <form class="chat-input-row" id="arena-chat-form">
              <input type="text" class="search-input" id="arena-chat-input" placeholder="Type a message..." autocomplete="off" />
              <button type="submit" class="btn btn-primary" style="padding:0.4rem 0.8rem;">Send</button>
            </form>
          </div>
        </aside>

      </div>
    </section>

    <!-- ========================================== -->
    <!-- VIEW 5: RULES CODEX                        -->
    <!-- ========================================== -->
    <section class="view-section" id="view-rules">
      <div style="margin-bottom:1.5rem;">
        <h2 class="page-title">Flesh and Blood Comprehensive Rules Codex</h2>
        <div style="color:var(--text-muted); font-size:0.95rem;">Official Comprehensive Rules chapters & keyword reference</div>
      </div>

      <!-- Quick Keyword Jump Pills -->
      <div class="quick-keywords-bar">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-dim); margin-right:4px;">Quick Keywords:</span>
        <button class="kw-pill" data-keyword="Go Again">Go Again</button>
        <button class="kw-pill" data-keyword="Dominate">Dominate</button>
        <button class="kw-pill" data-keyword="Overpower">Overpower</button>
        <button class="kw-pill" data-keyword="Phantasm">Phantasm</button>
        <button class="kw-pill" data-keyword="Crush">Crush</button>
        <button class="kw-pill" data-keyword="Reprise">Reprise</button>
        <button class="kw-pill" data-keyword="Arcane Barrier">Arcane Barrier</button>
        <button class="kw-pill" data-keyword="Ward">Ward</button>
        <button class="kw-pill" data-keyword="Blood Debt">Blood Debt</button>
        <button class="kw-pill" data-keyword="Opt">Opt</button>
        <button class="kw-pill" data-keyword="Boost">Boost</button>
        <button class="kw-pill" data-keyword="Stealth">Stealth</button>
      </div>

      <div class="rules-layout">
        <!-- Sidebar Navigation -->
        <aside class="rules-sidebar" id="rules-sidebar-nav"></aside>

        <!-- Main Content Panel -->
        <div class="rules-content-panel">
          <div class="search-input-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" class="search-input" id="rules-search-input" placeholder="Search rules codex for terms, keywords or mechanics..." />
          </div>
          <div id="rules-content-container"></div>
        </div>
      </div>
    </section>

  </main>

  <!-- ========================================== -->
  <!-- MODALS                                     -->
  <!-- ========================================== -->

  <!-- 1. Matchmaking & Room Modal -->
  <div class="modal-overlay" id="matchmaking-modal">
    <div class="modal-card" style="max-width:480px;">
      <div class="modal-header">
        <h3 class="modal-title">⚔️ Online Multiplayer Arena</h3>
        <button class="modal-close" id="matchmaking-modal-close">&times;</button>
      </div>
      <div class="modal-body">
        
        <!-- Queue Configuration Pane -->
        <div id="queue-config-pane">
          <div style="display:flex; flex-direction:column; gap:1rem; margin-bottom:1.5rem;">
            <div>
              <label style="font-size:0.85rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.4rem;">Select Deck:</label>
              <select class="filter-select" id="queue-deck-select" style="width:100%;"></select>
            </div>
            <div>
              <label style="font-size:0.85rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.4rem;">Format:</label>
              <select class="filter-select" id="queue-format-select" style="width:100%;">
                <option value="Blitz">Blitz (40 cards • Young Hero)</option>
                <option value="Classic Constructed">Classic Constructed (60+ cards • Adult Hero)</option>
              </select>
            </div>
          </div>

          <div style="display:flex; flex-direction:column; gap:0.75rem;">
            <button class="btn btn-primary" id="btn-start-queue" style="width:100%;">🎯 Find Online Match</button>
            <div style="text-align:center; font-size:0.8rem; color:var(--text-dim); margin:0.25rem 0;">— OR CUSTOM ROOM —</div>
            <div style="display:flex; gap:0.75rem;">
              <button class="btn btn-secondary" id="btn-create-custom-room" style="flex:1;">🏠 Create Room</button>
              <button class="btn btn-secondary" id="btn-join-custom-room" style="flex:1;">🔑 Join with Code</button>
            </div>
          </div>
        </div>

        <!-- Radar Active Queue Pane -->
        <div id="queue-active-pane" style="display:none;">
          <div class="radar-container">
            <svg class="radar-svg" viewBox="0 0 160 160" width="150" height="150">
              <defs>
                <radialGradient id="radarGlow" cx="50%" cy="50%" r="50%">
                  <stop offset="0%" stop-color="#d4af37" stop-opacity="0.2" />
                  <stop offset="70%" stop-color="#d4af37" stop-opacity="0.04" />
                  <stop offset="100%" stop-color="transparent" stop-opacity="0" />
                </radialGradient>
                <linearGradient id="radarBeam" x1="0%" y1="100%" x2="100%" y2="0%">
                  <stop offset="0%" stop-color="#d4af37" stop-opacity="0" />
                  <stop offset="35%" stop-color="#d4af37" stop-opacity="0.08" />
                  <stop offset="100%" stop-color="#f5d77f" stop-opacity="0.65" />
                </linearGradient>
              </defs>
              <circle cx="80" cy="80" r="76" fill="url(#radarGlow)" stroke="rgba(212,175,55,0.4)" stroke-width="1.5" />
              <circle cx="80" cy="80" r="52" fill="none" stroke="rgba(212,175,55,0.25)" stroke-width="1" stroke-dasharray="3 3" />
              <circle cx="80" cy="80" r="26" fill="none" stroke="rgba(212,175,55,0.2)" stroke-width="1" />
              <g class="radar-rotator">
                <path d="M 80 80 L 15.91 43 A 74 74 0 0 1 80 6 Z" fill="url(#radarBeam)" />
                <line x1="80" y1="80" x2="80" y2="6" stroke="#f7df94" stroke-width="2" stroke-linecap="round" />
              </g>
            </svg>
            <div style="font-size:2rem; z-index:2; position:absolute;">⚔️</div>
          </div>
          <div class="queue-timer" id="queue-timer-text">00:00</div>
          <div class="queue-status-text">Searching for an opponent in Rathe...</div>
          <div id="queue-format-display" style="text-align:center; font-size:0.8rem; color:var(--text-gold); margin-top:4px;"></div>
          <button class="btn btn-danger" id="btn-cancel-queue" style="width:100%; margin-top:1.5rem;">Cancel Search</button>
        </div>

        <!-- Custom Room Waiting Lobby Pane -->
        <div id="queue-custom-waiting-pane" style="display:none;">
          <div style="text-align:center; margin-bottom:1rem;">
            <span class="room-waiting-pulse">Waiting for Opponent to Join...</span>
          </div>

          <div class="room-code-card">
            <div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted);">Battle Room Code</div>
            <div class="room-code-value" id="waiting-room-code-val">FAB-000000</div>
            <p style="font-size:0.82rem; color:var(--text-dim); margin-bottom:0.75rem;">Share this code with your friend to connect instantly.</p>
            <button class="btn btn-secondary" id="btn-copy-room-code" style="width:100%;">📋 Copy Room Code</button>
          </div>

          <div id="waiting-room-details" style="text-align:center; font-size:0.85rem; color:var(--text-muted); margin-bottom:1.5rem;"></div>

          <button class="btn btn-danger" id="btn-cancel-custom-room" style="width:100%;">Cancel & Close Room</button>
        </div>

        <!-- Custom Room Join Code Pane -->
        <div id="queue-custom-join-pane" style="display:none;">
          <div style="margin-bottom:1.25rem;">
            <label style="font-size:0.85rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:0.4rem;">Enter Room Code:</label>
            <input type="text" class="search-input room-code-input" id="input-join-room-code" placeholder="FAB-XXXXXX" maxlength="12" style="width:100%;" />
          </div>

          <div style="display:flex; gap:0.75rem;">
            <button class="btn btn-secondary" id="btn-back-join-room" style="flex:1;">Back</button>
            <button class="btn btn-primary" id="btn-submit-join-room" style="flex:2;">⚔️ Join Battle</button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- 2. Auth Modal (Sign In / Register / Guest) -->
  <div class="modal-overlay" id="auth-modal">
    <div class="modal-card" style="max-width:440px;">
      <div class="modal-header">
        <h3 class="modal-title">🛡️ Rathe Identity</h3>
        <button class="modal-close" id="auth-modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem; border-bottom:1px solid var(--border-dark); padding-bottom:0.5rem;">
          <button class="btn btn-secondary auth-tab-btn active" data-auth-tab="login" style="flex:1; padding:0.4rem;">Sign In</button>
          <button class="btn btn-secondary auth-tab-btn" data-auth-tab="register" style="flex:1; padding:0.4rem;">Register</button>
        </div>

        <form id="login-form">
          <div id="auth-login-pane">
            <div style="display:flex; flex-direction:column; gap:0.85rem; margin-bottom:1.25rem;">
              <input type="text" class="search-input" id="login-username" placeholder="Username" required />
              <input type="password" class="search-input" id="login-password" placeholder="Password" required />
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:0.75rem;">Sign In</button>
          </div>
        </form>

        <form id="register-form">
          <div id="auth-register-pane" style="display:none;">
            <div style="display:flex; flex-direction:column; gap:0.85rem; margin-bottom:1.25rem;">
              <input type="text" class="search-input" id="reg-username" placeholder="Username (alphanumeric)" required />
              <input type="text" class="search-input" id="reg-displayname" placeholder="Hero Display Name" required />
              <input type="password" class="search-input" id="reg-password" placeholder="Password (min 6 characters)" required />
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:0.75rem;">Create Account</button>
          </div>
        </form>

        <div style="text-align:center; margin:0.5rem 0; color:var(--text-dim); font-size:0.8rem;">— OR INSTANT ACCESS —</div>
        <button class="btn btn-secondary" id="btn-guest-login" style="width:100%;">⚡ Play as Guest Hero</button>
      </div>
    </div>
  </div>

  <!-- 3. Card Detail High-Res Modal -->
  <div class="modal-overlay" id="card-detail-modal">
    <div class="modal-card" style="max-width:760px;">
      <div class="modal-header">
        <h3 class="modal-title">Card Inspection</h3>
        <button class="modal-close" id="card-modal-close">&times;</button>
      </div>
      <div class="modal-body" id="card-detail-modal-body"></div>
    </div>
  </div>

  <!-- 4. Card Sandbox Action Sheet Modal -->
  <div class="modal-overlay" id="card-action-menu-modal">
    <div class="modal-card" style="max-width:400px;">
      <div class="modal-header">
        <h3 class="modal-title" id="action-menu-card-title">Card Action</h3>
        <button class="modal-close" id="card-action-menu-close">&times;</button>
      </div>
      <div class="modal-body">
        <div style="display:flex; flex-direction:column; gap:0.6rem;" id="action-menu-buttons-list"></div>
      </div>
    </div>
  </div>

  <!-- 5. Zone Inspector Modal (Graveyard, Banished, Pitch) -->
  <div class="modal-overlay" id="zone-inspector-modal">
    <div class="modal-card" style="max-width:700px;">
      <div class="modal-header">
        <h3 class="modal-title" id="zone-inspector-title">Zone Viewer</h3>
        <button class="modal-close" id="zone-inspector-modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="card-grid" id="zone-inspector-grid" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:0.75rem;"></div>
      </div>
    </div>
  </div>

  <!-- 6. Hero Selector Modal -->
  <div class="modal-overlay" id="hero-selector-modal">
    <div class="modal-card" style="max-width:820px;">
      <div class="modal-header">
        <h3 class="modal-title">Choose Your Hero</h3>
        <button class="modal-close" onclick="document.getElementById('hero-selector-modal').classList.remove('active')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="card-grid" id="hero-selector-grid" style="grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap:1rem; max-height:65vh; overflow-y:auto;"></div>
      </div>
    </div>
  </div>

  <!-- 7. Deck Export Modal -->
  <div class="modal-overlay" id="export-modal">
    <div class="modal-card" style="max-width:540px;">
      <div class="modal-header">
        <h3 class="modal-title">Export Decklist</h3>
        <button class="modal-close" onclick="document.getElementById('export-modal').classList.remove('active')">&times;</button>
      </div>
      <div class="modal-body">
        <textarea id="export-textarea" class="search-input" style="width:100%; height:260px; font-family:var(--font-mono); font-size:0.85rem;" readonly></textarea>
        <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
          <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('export-textarea').value); API.toast('Copied to clipboard!', 'success');">📋 Copy to Clipboard</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 8. Deck Import Modal -->
  <div class="modal-overlay" id="import-modal">
    <div class="modal-card" style="max-width:540px;">
      <div class="modal-header">
        <h3 class="modal-title">Import Decklist</h3>
        <button class="modal-close" onclick="document.getElementById('import-modal').classList.remove('active')">&times;</button>
      </div>
      <div class="modal-body">
        <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem;">
          <input type="text" class="search-input" id="import-name-input" placeholder="Deck Name..." />
          <select class="filter-select" id="import-format-select">
            <option value="Blitz">Format: Blitz</option>
            <option value="Classic Constructed">Format: Classic Constructed</option>
          </select>
          <textarea id="import-textarea" class="search-input" style="width:100%; height:200px; font-family:var(--font-mono); font-size:0.85rem;" placeholder="Paste decklist here (e.g. 1x Dorinthea Ironsong, 2x Ironsong Determination (1)...)"></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
          <button class="btn btn-secondary" onclick="document.getElementById('import-modal').classList.remove('active')">Cancel</button>
          <button class="btn btn-primary" id="btn-submit-import">📥 Import Deck</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 9. Preconstructed Decks Catalog Modal -->
  <div class="modal-overlay" id="precon-selector-modal">
    <div class="modal-card" style="max-width:880px;">
      <div class="modal-header">
        <h3 class="modal-title">⚡ Iconic Preconstructed Decks</h3>
        <button class="modal-close" onclick="document.getElementById('precon-selector-modal').classList.remove('active')">&times;</button>
      </div>
      <div class="modal-body">
        <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.25rem;">
          Select any of the iconic Flesh and Blood starter archetypes to instantly clone and play with full equipment and main deck synergies.
        </div>
        <div id="precon-cards-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; max-height:65vh; overflow-y:auto;"></div>
      </div>
    </div>
  </div>

  <!-- 10. Match Result (Victory / Defeat) Modal -->
  <div class="modal-overlay" id="match-result-modal">
    <div class="modal-card" style="max-width:540px;">
      <div id="match-result-card" class="match-result-card victory">
        <div class="result-icon-emblem" id="result-emblem">🏆</div>
        <h2 class="result-title victory" id="result-title">VICTORY!</h2>
        <p style="font-size:0.95rem; color:var(--text-muted); line-height:1.5;" id="result-description">
          You have conquered the battlefield in Rathe!
        </p>

        <!-- Match Statistics Breakdown -->
        <div class="result-stats-summary">
          <div>
            <div class="result-stat-val" id="result-stat-turns">1</div>
            <div class="result-stat-lbl">Turns</div>
          </div>
          <div>
            <div class="result-stat-val" id="result-stat-format">Blitz</div>
            <div class="result-stat-lbl">Format</div>
          </div>
          <div>
            <div class="result-stat-val" id="result-stat-duration">0m 0s</div>
            <div class="result-stat-lbl">Duration</div>
          </div>
        </div>

        <div style="display:flex; gap:0.75rem; justify-content:center;">
          <button class="btn btn-primary" id="btn-result-play-again" onclick="GameEngine.playAgain()">🎯 Play Again</button>
          <button class="btn btn-secondary" id="btn-result-return-home" onclick="GameEngine.exitToHome()">🏰 Return to Home</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 11. Leave Battlefield Confirmation Modal -->
  <div class="modal-overlay" id="leave-match-modal">
    <div class="modal-card" style="max-width:440px; border-color:var(--color-life);">
      <div class="modal-header" style="border-bottom-color:rgba(229,57,53,0.3);">
        <h3 class="modal-title" style="color:var(--color-life);">⚠️ Forfeit Match?</h3>
        <button class="modal-close" id="btn-cancel-leave-match">&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size:0.92rem; color:var(--text-muted); line-height:1.6; margin-bottom:1.5rem;">
          Are you sure you want to abandon the battlefield? Leaving an active match in progress will automatically forfeit the game and award victory to your opponent.
        </p>
        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
          <button class="btn btn-secondary" onclick="document.getElementById('leave-match-modal').classList.remove('active');">Cancel & Stay</button>
          <button class="btn btn-danger" id="btn-confirm-leave-match">🚪 Leave & Forfeit</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 12. Settings / Configuration Modal -->
  <div class="modal-overlay" id="settings-modal">
    <div class="modal-card" style="max-width:520px;">
      <div class="modal-header">
        <h3 class="modal-title">⚙️ Arena & Visual Settings</h3>
        <button class="modal-close" onclick="document.getElementById('settings-modal').classList.remove('active')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="settings-group">
          
          <div class="settings-row">
            <div>
              <div class="settings-label-title">🌟 Rarity Visual Effects</div>
              <div class="settings-label-desc">Holographic foil shimmers, glows, and card finishes</div>
            </div>
            <label class="switch">
              <input type="checkbox" id="setting-rarity-effects" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="settings-row" id="setting-row-rarity-animated">
            <div>
              <div class="settings-label-title">✨ Animated Rarity Shimmer</div>
              <div class="settings-label-desc">Dynamic sweeping light animation (turn off for static foil)</div>
            </div>
            <label class="switch">
              <input type="checkbox" id="setting-rarity-animated" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="settings-row">
            <div>
              <div class="settings-label-title">🎲 3D Tumbling Dice</div>
              <div class="settings-label-desc">3D animated rolling physics for D6, D20, and Coin</div>
            </div>
            <label class="switch">
              <input type="checkbox" id="setting-dice-anim" checked>
              <span class="slider"></span>
            </label>
          </div>

          <div class="settings-row">
            <div>
              <div class="settings-label-title">⚔️ Screen Flash & Splash</div>
              <div class="settings-label-desc">Damage screen flashes and turn transition banners</div>
            </div>
            <label class="switch">
              <input type="checkbox" id="setting-screen-flash" checked>
              <span class="slider"></span>
            </label>
          </div>

        </div>
        <div style="margin-top:1.5rem; text-align:right;">
          <button class="btn btn-primary" onclick="document.getElementById('settings-modal').classList.remove('active')">Save & Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 13. Thematic System Dialog Modal (Alert / Confirm / Prompt) -->
  <div class="modal-overlay" id="app-dialog-modal">
    <div class="modal-card" style="max-width:440px;">
      <div class="modal-header">
        <h3 class="modal-title" id="dialog-title">Notice</h3>
        <button class="modal-close" id="dialog-modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="dialog-emblem" id="dialog-icon">🛡️</div>
        <div class="dialog-msg-text" id="dialog-message">Message text...</div>
        <input type="text" class="search-input" id="dialog-input-val" style="width:100%; display:none; margin-bottom:1.25rem;" />
        <div style="display:flex; gap:0.75rem; justify-content:center;">
          <button class="btn btn-secondary" id="dialog-btn-cancel" style="display:none; flex:1;">Cancel</button>
          <button class="btn btn-primary" id="dialog-btn-confirm" style="display:none; flex:1;">Confirm</button>
          <button class="btn btn-primary" id="dialog-btn-ok" style="flex:1;">Understood</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 14. 3D Tumbling Dice Rolling Overlay -->
  <div class="dice-animation-overlay" id="dice-anim-overlay">
    <div class="dice-stage">
      <div class="dice-3d-cube" id="dice-3d-cube-box">
        <div class="dice-face" style="transform: rotateY(0deg) translateZ(50px);">🎲</div>
        <div class="dice-face" style="transform: rotateY(90deg) translateZ(50px);">⚔️</div>
        <div class="dice-face" style="transform: rotateY(180deg) translateZ(50px);">🛡️</div>
        <div class="dice-face" style="transform: rotateY(-90deg) translateZ(50px);">⚡</div>
        <div class="dice-face" style="transform: rotateX(90deg) translateZ(50px);">❤️</div>
        <div class="dice-face" style="transform: rotateX(-90deg) translateZ(50px);">✨</div>
      </div>
    </div>
    <div class="dice-result-label" id="dice-result-anim-label">Rolling...</div>
  </div>

  <!-- 15. Visual Splash Banners & Damage Flash -->
  <div class="turn-banner-splash" id="turn-splash-banner">⚔️ YOUR TURN ⚔️</div>
  <div class="damage-flash" id="damage-flash-overlay"></div>

  <!-- JavaScript Scripts with Cache Busting -->
  <script src="assets/js/api.js?v=<?= time() ?>"></script>
  <script src="assets/js/compendium.js?v=<?= time() ?>"></script>
  <script src="assets/js/deckbuilder.js?v=<?= time() ?>"></script>
  <script src="assets/js/matchmaking.js?v=<?= time() ?>"></script>
  <script src="assets/js/game.js?v=<?= time() ?>"></script>
  <script src="assets/js/rules.js?v=<?= time() ?>"></script>
  <script src="assets/js/main.js?v=<?= time() ?>"></script>
</body>
</html>
