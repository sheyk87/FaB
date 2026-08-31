# Flesh and Blood (FaB) TCG Sandbox Arena

A feature-rich, server-authoritative **Flesh and Blood (FaB) TCG Sandbox** web application built with **HTML5, Vanilla CSS, Vanilla JavaScript, PHP 8+, and MySQL**, designed specifically for Windows 11 with XAMPP.

---

## 🌟 Key Features

### 1. 🏰 Main Menu & Live Telemetry KPIs
- **Live Online Players Counter**: Auto-refreshes every 4 seconds.
- **Players in Queue Counter**: Real-time counter of players searching for a battle.
- **Active Combat Chains / Battles Counter**: Live tally of concurrent active rooms.
- **Total Decks Created**: Number of constructed player decks in the database.
- **Quick Play & Authenticated Access**: Seamless guest login or full user registration.

### 2. ⚔️ Concurrency-Safe 1v1 Matchmaking & Custom Rooms
- **Atomic FIFO Pairing**: Pairs 1st + 2nd players into Room 1. An odd 3rd player remains waiting in the queue until a 4th player arrives, instantly creating Room 2.
- **Multi-Room Concurrency**: Designed to support hundreds of concurrent players and battles.
- **Radar Scanner Queue UI**: Live queue timer with smooth radar animation and status polling.
- **Custom Rooms**: Generate unique 6-character room codes (e.g. `FAB-A1B2C3`) to battle friends directly.

### 3. 🛡️ Sandbox Battlefield (Server-Authoritative)
- **Authentic Flesh and Blood Zones**:
  - **Hero Zone**: Portrait, Health counter (+ / - / direct), soul counters, status tokens, tap/exhaust.
  - **Equipment & Weapons Slots**: Head, Chest, Arms, Legs, Primary Weapon, Off-Hand/Shield with tap and counter mechanics.
  - **Arsenal Zone**: Staging slot for face-down or face-up cards.
  - **Interactive Hand Fan**: Pitch values, cost, stats, hover magnification, and quick actions (Play Attack, Pitch, Defend, Arsenal, Discard, Banish).
  - **Pitch Zone & Resource Pool**: Real-time Resource Point tracking with Red/Yellow/Blue indicators.
  - **Action Points Tracker**: AP counter with "Go Again" indicators.
  - **Deck Controls**: Draw card, Shuffle deck with visual feedback, Deck count.
  - **Graveyard & Banish Zones**: Counter badges and full-card zone inspectors.
  - **Central Combat Chain**:
    - Multi-link support (Link 1, Link 2...).
    - Attack Power vs Total Defense calculation.
    - Attack and Defense reaction staging.
    - Hit/Damage evaluation and "Close Combat Chain" mechanics.
  - **End Phase Automation**: Automatically puts pitched cards on the bottom of the player's deck, resets AP/Resources, draws cards up to Hero Intelligence, and passes the turn.
  - **3D-Style Dice Roller**: Roll D6, D20, or flip a coin with authoritative results in the combat log.
  - **In-Game Chat & Combat Log**: Synchronized real-time log of every move.

### 4. 🎴 Advanced Deck Builder
- **Card Pool Explorer**: Instant search with type, pitch, and keyword filters.
- **Format Validation**: Blitz (40 cards + 1 Young Hero, max 2 copies) and Classic Constructed (60+ cards + 1 Adult Hero, max 3 copies).
- **Pitch Ratio & Cost Curve**: Live graphical distribution of Red, Yellow, and Blue cards.
- **Hero & Equipment Customizer**: Dedicated slots for hero, weapons, and armor.
- **Import / Export**: Paste text decklists or copy to clipboard in universal format.

### 5. 📚 4,950+ Card Compendium & Library
- **Source Data**: Populated directly from `card.json` across 90 sets.
- **Dynamic Image Loading**: Loads card illustrations dynamically via image URLs from `card.json` (no local image downloads).
- **Multi-Attribute Filters**: Set, Class, Card Type, Pitch (1 Red, 2 Yellow, 3 Blue), Cost, and Sort options.
- **High-Res Card Detail Inspector**: Formatted rules text with `{r}`, `{p}`, `{d}`, `{h}`, `{i}` glyphs and printings information.

### 6. 📜 Official Comprehensive Rules Codex
- Structured reader mirroring the 10 chapters of the official Flesh and Blood Comprehensive Rules (Game Concepts, Object Properties, Zones, Game Structure, Layers & Abilities, Effects, Combat, Keywords, Formats, Glossary).
- Quick keyword pills (Go Again, Dominate, Overpower, Phantasm, Crush, Reprise, Arcane Barrier, Ward, Blood Debt, Opt, Boost, Stealth).
- Instant keyword search bar.

---

## 🔒 Security & OWASP Top 10 Compliance

1. **SQL Injection Prevention**: 100% Prepared Statements via PDO for all database queries.
2. **Cross-Site Scripting (XSS)**: Strict HTML entity escaping (`htmlspecialchars`) and client-side sanitization.
3. **Cross-Site Request Forgery (CSRF)**: Cryptographically secure CSRF token issuance and header/payload validation on all mutating POST requests.
4. **Session Security**: Session fixation defense with `session_regenerate_id(true)`, `HttpOnly`, and `SameSite=Lax` cookies.
5. **Anti-Cheat State Masking**: Opponent's hand and deck cards are masked server-side so players cannot inspect opponent hidden cards via browser developer tools.
6. **Rate Limiting**: IP and session-based throttling on chat and authentication endpoints.

---

## 🚀 How to Run with XAMPP on Windows 11

1. **Start Apache and MySQL in XAMPP Control Panel**.
2. **Open your browser and navigate to**:
   ```
   http://localhost/FaB/
   ```
3. The database `fab_tcg` and tables are automatically initialized with all cards and sample starter decks.
4. Enjoy playing Flesh and Blood TCG Sandbox!
