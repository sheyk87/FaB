/**
 * Deck Builder Controller
 * Flesh and Blood TCG Sandbox
 */

const DeckBuilder = {
  currentDeckId: null,
  activeDeck: {
    name: 'New Blitz Deck',
    format: 'Blitz',
    description: '',
    hero: null,
    weapons: [],
    equipment: [],
    main_deck: [],
    inventory: []
  },
  poolQuery: '',
  poolType: 'all',
  poolPitch: 'all',
  poolPage: 1,

  init() {
    this.bindEvents();
  },

  async onActivate(params = {}) {
    if (!API.currentUser) {
      API.toast('Please sign in or use Quick Play to manage decks.', 'info');
      App.openAuthModal();
      return;
    }
    await this.loadUserDeckList();
    this.searchCardPool();
  },

  bindEvents() {
    // Deck Selector dropdown
    document.getElementById('deck-selector')?.addEventListener('change', async (e) => {
      const id = e.target.value;
      if (id === 'new') {
        this.createNewDeck();
      } else if (id) {
        await this.loadDeck(parseInt(id));
      }
    });

    // Save Deck button
    document.getElementById('btn-save-deck')?.addEventListener('click', () => this.saveCurrentDeck());

    // New Deck button
    document.getElementById('btn-new-deck')?.addEventListener('click', () => this.createNewDeck());

    // Preconstructed Decks Catalog button
    document.getElementById('btn-open-precons')?.addEventListener('click', () => this.openPreconModal());

    // Delete Deck button
    document.getElementById('btn-delete-deck')?.addEventListener('click', () => this.deleteCurrentDeck());

    // Import / Export buttons
    document.getElementById('btn-export-deck')?.addEventListener('click', () => this.openExportModal());
    document.getElementById('btn-import-deck')?.addEventListener('click', () => this.openImportModal());

    // Hero Slot click -> Hero Selector
    document.getElementById('hero-slot-box')?.addEventListener('click', () => this.openHeroSelectorModal());

    // Deck Name & Format inputs
    document.getElementById('deck-name-input')?.addEventListener('input', (e) => {
      this.activeDeck.name = e.target.value;
    });

    document.getElementById('deck-format-select')?.addEventListener('change', (e) => {
      this.activeDeck.format = e.target.value;
      this.updateDeckUI();
    });

    // Pool search input with debounce
    let poolDebounce;
    document.getElementById('pool-search-input')?.addEventListener('input', (e) => {
      clearTimeout(poolDebounce);
      poolDebounce = setTimeout(() => {
        this.poolQuery = e.target.value.trim();
        this.poolPage = 1;
        this.searchCardPool();
      }, 300);
    });

    // Pool Type filter
    document.getElementById('pool-type-select')?.addEventListener('change', (e) => {
      this.poolType = e.target.value;
      this.poolPage = 1;
      this.searchCardPool();
    });

    // Import submit
    document.getElementById('btn-submit-import')?.addEventListener('click', () => this.handleImportDeck());
  },

  async loadUserDeckList() {
    try {
      const res = await API.get('api/decks.php?action=list');
      if (res.success && res.decks) {
        const sel = document.getElementById('deck-selector');
        if (sel) {
          sel.innerHTML = '<option value="new">+ Create New Deck</option>' +
            res.decks.map(d => `<option value="${d.id}" ${d.id === this.currentDeckId ? 'selected' : ''}>${API.escapeHtml(d.name)} (${d.format}) - ${d.main_count} cards</option>`).join('');
        }

        if (res.decks.length > 0 && !this.currentDeckId) {
          await this.loadDeck(res.decks[0].id);
        }
      }
    } catch (e) {
      console.error('Failed to load user decks', e);
    }
  },

  async loadDeck(deckId) {
    try {
      const res = await API.get(`api/decks.php?action=get&id=${deckId}`);
      if (res.success && res.deck) {
        this.currentDeckId = res.deck.id;
        this.activeDeck = {
          name: res.deck.name,
          format: res.deck.format,
          description: res.deck.description || '',
          hero: res.deck.cards.hero,
          weapons: res.deck.cards.weapons || [],
          equipment: res.deck.cards.equipment || [],
          main_deck: res.deck.cards.main_deck || [],
          inventory: res.deck.cards.inventory || []
        };
        this.updateDeckUI();
      }
    } catch (e) {
      API.toast('Failed to load deck', 'error');
    }
  },

  createNewDeck() {
    this.currentDeckId = null;
    this.activeDeck = {
      name: 'New Custom Deck',
      format: 'Blitz',
      description: '',
      hero: null,
      weapons: [],
      equipment: [],
      main_deck: [],
      inventory: []
    };
    const sel = document.getElementById('deck-selector');
    if (sel) sel.value = 'new';
    this.updateDeckUI();
  },

  async saveCurrentDeck() {
    if (!API.currentUser) {
      API.toast('Please sign in to save your deck.', 'info');
      App.openAuthModal();
      return;
    }

    if (!this.activeDeck.name.trim()) {
      API.toast('Please provide a name for your deck.', 'error');
      return;
    }

    const payload = {
      id: this.currentDeckId,
      name: this.activeDeck.name,
      format: this.activeDeck.format,
      description: this.activeDeck.description,
      cards: {
        hero: this.activeDeck.hero ? this.activeDeck.hero.unique_id : '',
        weapons: this.activeDeck.weapons.map(c => c.unique_id),
        equipment: this.activeDeck.equipment.map(c => c.unique_id),
        main_deck: this.activeDeck.main_deck.map(c => c.unique_id),
        inventory: this.activeDeck.inventory.map(c => c.unique_id)
      }
    };

    try {
      const res = await API.post('api/decks.php?action=save', payload);
      if (res.success && res.deck) {
        this.currentDeckId = res.deck.id;
        API.toast('Deck saved successfully!', 'success');
        await this.loadUserDeckList();
        App.fetchKpis();
      }
    } catch (e) {
      API.toast(e.message || 'Failed to save deck', 'error');
    }
  },

  async deleteCurrentDeck() {
    if (!this.currentDeckId) {
      this.createNewDeck();
      return;
    }

    const confirmed = await ModalDialog.confirm('Are you sure you want to permanently delete this deck?', 'Delete Deck', '🗑️', 'Delete Deck', 'Cancel');
    if (!confirmed) return;

    try {
      const res = await API.post(`api/decks.php?action=delete&id=${this.currentDeckId}`);
      if (res.success) {
        API.toast('Deck deleted.', 'info');
        this.currentDeckId = null;
        await this.loadUserDeckList();
        this.createNewDeck();
        App.fetchKpis();
      }
    } catch (e) {
      API.toast(e.message || 'Failed to delete deck', 'error');
    }
  },

  async searchCardPool() {
    const grid = document.getElementById('deck-pool-grid');
    if (!grid) return;

    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:2rem; color:var(--text-dim);">Searching cards...</div>';

    const params = new URLSearchParams({
      action: 'search',
      query: this.poolQuery,
      card_type: this.poolType,
      pitch: this.poolPitch,
      page: this.poolPage,
      limit: 28
    });

    try {
      const res = await API.get(`api/cards.php?${params.toString()}`);
      if (res.success && res.cards) {
        grid.innerHTML = res.cards.map(c => {
          const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='130' height='182' viewBox='0 0 130 182'%3E%3Crect width='130' height='182' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='11'%3E" + encodeURIComponent(c.name) + "%3C/text%3E%3C/svg%3E";

          const rKey = (c.rarity || 'C').toUpperCase();
          const hasFoil = ['F', 'L', 'M', 'S', 'R'].includes(rKey);
          const foilType = rKey === 'F' ? 'fabled' : (rKey === 'L' ? 'legendary' : (rKey === 'M' ? 'majestic' : (rKey === 'S' ? 'super-rare' : 'rare')));
          const rarityGlowClass = hasFoil ? `card-rarity-${rKey.toLowerCase()}` : '';

          return `
            <div class="pool-card-card rarity-card-wrap ${rarityGlowClass}" onclick="DeckBuilder.addCardToActiveDeck('${c.unique_id}')" title="Click to add ${API.escapeHtml(c.name)}">
              <img class="pool-card-img" src="${c.image_url || imgPlaceholder}" alt="${API.escapeHtml(c.name)}" loading="lazy" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
              ${hasFoil ? `<div class="foil-sheen-overlay foil-${foilType}"></div>` : ''}
              <div class="pool-card-info">
                <div class="pool-card-title">${API.escapeHtml(c.name)}</div>
              </div>
              <div class="pool-add-overlay">➕</div>
            </div>
          `;
        }).join('');
      }
    } catch (e) {
      grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem; color:var(--color-life);">Error: ${e.message}</div>`;
    }
  },

  async addCardToActiveDeck(uniqueId) {
    try {
      const res = await API.get(`api/cards.php?action=detail&id=${uniqueId}`);
      if (res.success && res.card) {
        const card = res.card;

        if (card.is_hero) {
          this.activeDeck.hero = card;
          API.toast(`Hero set to ${card.name}`, 'info');
        } else if (card.is_weapon) {
          if (this.activeDeck.weapons.length < 2) {
            this.activeDeck.weapons.push(card);
          } else {
            this.activeDeck.weapons[1] = card;
          }
        } else if (card.is_equipment) {
          if (this.activeDeck.equipment.length < 4) {
            this.activeDeck.equipment.push(card);
          } else {
            this.activeDeck.equipment.push(card);
          }
        } else {
          // Check copies limit
          const maxCopies = (this.activeDeck.format === 'Classic Constructed') ? 3 : 2;
          const currentCopies = this.activeDeck.main_deck.filter(c => c.name === card.name && c.pitch === card.pitch).length;

          if (currentCopies >= maxCopies) {
            API.toast(`Maximum copies (${maxCopies}) of ${card.name} reached for ${this.activeDeck.format}.`, 'error');
            return;
          }

          this.activeDeck.main_deck.push(card);
        }

        this.updateDeckUI();
        API.toast(`Added ${card.name} to deck`, 'success');
      }
    } catch (e) {
      API.toast('Could not add card', 'error');
    }
  },

  removeCardFromMain(name, pitch) {
    const idx = this.activeDeck.main_deck.findIndex(c => c.name === name && c.pitch === pitch);
    if (idx !== -1) {
      this.activeDeck.main_deck.splice(idx, 1);
      this.updateDeckUI();
    }
  },

  removeEquipment(slotIndex) {
    if (this.activeDeck.equipment[slotIndex]) {
      this.activeDeck.equipment.splice(slotIndex, 1);
      this.updateDeckUI();
    }
  },

  removeWeapon(slotIndex) {
    if (this.activeDeck.weapons[slotIndex]) {
      this.activeDeck.weapons.splice(slotIndex, 1);
      this.updateDeckUI();
    }
  },

  updateDeckUI() {
    // 1. Deck Name & Format
    const nameInput = document.getElementById('deck-name-input');
    if (nameInput) nameInput.value = this.activeDeck.name;

    const fmtSelect = document.getElementById('deck-format-select');
    if (fmtSelect) fmtSelect.value = this.activeDeck.format;

    // 2. Hero Box
    const heroBox = document.getElementById('hero-slot-box');
    if (heroBox) {
      if (this.activeDeck.hero) {
        const h = this.activeDeck.hero;
        heroBox.innerHTML = `
          <img class="hero-slot-img" src="${h.image_url || ''}" alt="${API.escapeHtml(h.name)}" />
          <div class="hero-slot-meta">
            <div class="hero-slot-name">${API.escapeHtml(h.name)}</div>
            <div class="hero-slot-stats">
              <span style="color:var(--color-life);">❤️ ${h.health || 20}</span>
              <span style="color:#c084fc;">🧠 ${h.intelligence || 4}</span>
            </div>
          </div>
          <button class="btn btn-secondary" style="padding:4px 8px; font-size:0.75rem;" onclick="event.stopPropagation(); DeckBuilder.openHeroSelectorModal();">Change</button>
        `;
      } else {
        heroBox.innerHTML = `
          <div style="text-align:center; width:100%; color:var(--text-dim); padding:1rem; cursor:pointer;">
            ➕ Click to Choose Hero
          </div>
        `;
      }
    }

    // 3. Equipment & Weapons list
    const eqList = document.getElementById('deck-equipment-list');
    if (eqList) {
      const eqItems = [];
      this.activeDeck.weapons.forEach((w, i) => {
        eqItems.push(`
          <div class="equipment-slot-item" onclick="DeckBuilder.removeWeapon(${i})" title="Click to remove">
            <span class="glyph glyph-resource">⚔️</span>
            <span style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${API.escapeHtml(w.name)}</span>
            <span style="margin-left:auto; color:var(--color-life);">✕</span>
          </div>
        `);
      });
      this.activeDeck.equipment.forEach((eq, i) => {
        eqItems.push(`
          <div class="equipment-slot-item" onclick="DeckBuilder.removeEquipment(${i})" title="Click to remove">
            <span class="glyph glyph-defense">🛡️</span>
            <span style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${API.escapeHtml(eq.name)}</span>
            <span style="margin-left:auto; color:var(--color-life);">✕</span>
          </div>
        `);
      });

      eqList.innerHTML = eqItems.length > 0 ? eqItems.join('') : '<div style="grid-column:span 2; font-size:0.8rem; color:var(--text-dim); text-align:center;">No weapons or equipment added.</div>';
    }

    // 4. Main Deck Rows (Grouped by Name + Pitch)
    const cardMap = {};
    this.activeDeck.main_deck.forEach(c => {
      const key = `${c.name}___${c.pitch || 0}`;
      if (!cardMap[key]) {
        cardMap[key] = { card: c, count: 0 };
      }
      cardMap[key].count++;
    });

    const mainList = document.getElementById('deck-main-cards-list');
    const mainCount = this.activeDeck.main_deck.length;
    document.getElementById('deck-main-count-label').textContent = `Main Deck (${mainCount} cards)`;

    if (mainList) {
      const rows = Object.values(cardMap).map(item => {
        const c = item.card;
        const pitchClass = `pitch-${c.pitch || '1'}`;
        return `
          <div class="deck-card-row ${pitchClass}">
            <span class="glyph glyph-pitch-${c.pitch || '1'}">${c.pitch || '-'}</span>
            <span class="deck-card-name" title="${API.escapeHtml(c.name)}">${API.escapeHtml(c.name)}</span>
            <div class="deck-card-qty-ctrls">
              <button class="qty-btn" onclick="DeckBuilder.removeCardFromMain('${API.escapeHtml(c.name)}', ${c.pitch || 0})">-</button>
              <span style="font-family:var(--font-mono); font-weight:700; min-width:18px; text-align:center;">${item.count}</span>
              <button class="qty-btn" onclick="DeckBuilder.addCardToActiveDeck('${c.unique_id}')">+</button>
            </div>
          </div>
        `;
      });

      mainList.innerHTML = rows.length > 0 ? rows.join('') : '<div style="text-align:center; padding:2rem; color:var(--text-dim); font-size:0.9rem;">No cards in main deck yet. Click cards from the pool on the left to add.</div>';
    }

    // 5. Update Pitch Stats
    let redCount = 0, yellowCount = 0, blueCount = 0;
    this.activeDeck.main_deck.forEach(c => {
      if (c.pitch === 1) redCount++;
      else if (c.pitch === 2) yellowCount++;
      else if (c.pitch === 3) blueCount++;
    });

    const totalPitch = Math.max(1, mainCount);
    const redPct = (redCount / totalPitch) * 100;
    const yelPct = (yellowCount / totalPitch) * 100;
    const bluPct = (blueCount / totalPitch) * 100;

    document.getElementById('pitch-bar-red').style.width = `${redPct}%`;
    document.getElementById('pitch-bar-yellow').style.width = `${yelPct}%`;
    document.getElementById('pitch-bar-blue').style.width = `${bluPct}%`;

    document.getElementById('pitch-count-red').textContent = `Red: ${redCount}`;
    document.getElementById('pitch-count-yellow').textContent = `Yellow: ${yellowCount}`;
    document.getElementById('pitch-count-blue').textContent = `Blue: ${blueCount}`;

    // 6. Validation Badge
    const valBadge = document.getElementById('deck-validation-badge');
    if (valBadge) {
      let isValid = true;
      let msg = 'Legal for ' + this.activeDeck.format;

      if (!this.activeDeck.hero) {
        isValid = false;
        msg = 'Hero required';
      } else if (this.activeDeck.format === 'Blitz' && mainCount < 40) {
        msg = `Blitz requires 40 cards (${mainCount}/40)`;
      } else if (this.activeDeck.format === 'Classic Constructed' && mainCount < 60) {
        isValid = false;
        msg = `CC requires 60+ cards (${mainCount}/60)`;
      }

      valBadge.className = `validation-badge ${isValid ? 'valid' : 'invalid'}`;
      valBadge.innerHTML = `<span>${isValid ? '✅' : '⚠️'}</span> <span>${msg}</span>`;
    }
  },

  async openHeroSelectorModal() {
    try {
      const res = await API.get('api/cards.php?action=heroes');
      if (res.success && res.heroes) {
        const modal = document.getElementById('hero-selector-modal');
        const grid = document.getElementById('hero-selector-grid');
        if (!modal || !grid) return;

        grid.innerHTML = res.heroes.map(h => {
          const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='196' viewBox='0 0 140 196'%3E%3Crect width='140' height='196' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='12'%3E" + encodeURIComponent(h.name) + "%3C/text%3E%3C/svg%3E";

          return `
            <div class="fab-card-item" onclick="DeckBuilder.selectHero('${h.unique_id}')">
              <div class="card-img-wrap">
                <img class="card-img" src="${h.image_url || imgPlaceholder}" alt="${API.escapeHtml(h.name)}" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
              </div>
              <div class="card-meta-bar">
                <div class="card-name-label">${API.escapeHtml(h.name)}</div>
                <div class="card-type-label">${API.escapeHtml(h.type_text || '')}</div>
              </div>
            </div>
          `;
        }).join('');

        modal.classList.add('active');
      }
    } catch (e) {
      API.toast('Failed to load heroes', 'error');
    }
  },

  async selectHero(uniqueId) {
    await this.addCardToActiveDeck(uniqueId);
    document.getElementById('hero-selector-modal')?.classList.remove('active');
  },

  async openPreconModal() {
    try {
      const res = await API.get('api/decks.php?action=precons');
      if (res.success && res.precons) {
        const modal = document.getElementById('precon-selector-modal');
        const grid = document.getElementById('precon-cards-grid');
        if (!modal || !grid) return;

        grid.innerHTML = res.precons.map(p => {
          const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='196' viewBox='0 0 140 196'%3E%3Crect width='140' height='196' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='12'%3E" + encodeURIComponent(p.hero_name) + "%3C/text%3E%3C/svg%3E";

          let diffColor = '#10b981';
          if (p.difficulty === 'Intermediate') diffColor = '#f59e0b';
          if (p.difficulty === 'Advanced') diffColor = '#ef4444';

          return `
            <div class="menu-card" style="padding:1.25rem; gap:0.75rem;">
              <div style="display:flex; gap:0.75rem; align-items:center;">
                <img src="${p.image_url || imgPlaceholder}" alt="${API.escapeHtml(p.hero_name)}" style="width:50px; height:70px; border-radius:5px; object-fit:cover; border:1px solid var(--border-gold);" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
                <div>
                  <h4 style="font-family:var(--font-title); color:#fff; font-size:1.05rem; margin-bottom:2px;">${API.escapeHtml(p.hero_name)}</h4>
                  <div style="display:flex; gap:0.4rem; font-size:0.75rem;">
                    <span class="card-rarity-pill">${p.class}</span>
                    <span class="card-rarity-pill" style="border-color:${diffColor}; color:${diffColor};">${p.difficulty}</span>
                  </div>
                </div>
              </div>
              <p style="font-size:0.82rem; color:var(--text-muted); line-height:1.4; margin:0.25rem 0;">
                ${API.escapeHtml(p.description)}
              </p>
              <button class="btn btn-primary" style="width:100%; padding:0.5rem; font-size:0.85rem;" onclick="DeckBuilder.selectPreconDeck('${p.key}')">
                ⚡ Clone & Load Deck
              </button>
            </div>
          `;
        }).join('');

        modal.classList.add('active');
      }
    } catch (e) {
      API.toast('Failed to load preconstructed decks', 'error');
    }
  },

  async selectPreconDeck(preconKey) {
    if (!API.currentUser) {
      API.toast('Please sign in or use Quick Play to clone starter decks.', 'info');
      App.openAuthModal();
      return;
    }

    try {
      const res = await API.post('api/decks.php?action=clone_precon', { precon_key: preconKey });
      if (res.success && res.deck) {
        document.getElementById('precon-selector-modal')?.classList.remove('active');
        this.currentDeckId = res.deck.id;
        API.toast(`Preconstructed deck '${res.deck.name}' loaded!`, 'success');
        await this.loadUserDeckList();
        await this.loadDeck(res.deck.id);
        App.fetchKpis();
      }
    } catch (e) {
      API.toast(e.message || 'Failed to clone deck', 'error');
    }
  },

  openExportModal() {
    const modal = document.getElementById('export-modal');
    const textarea = document.getElementById('export-textarea');
    if (!modal || !textarea) return;

    let text = `// Flesh and Blood Deck: ${this.activeDeck.name}\n// Format: ${this.activeDeck.format}\n\n`;
    if (this.activeDeck.hero) text += `1x ${this.activeDeck.hero.name}\n\n`;

    text += `// Weapons & Equipment\n`;
    this.activeDeck.weapons.forEach(w => text += `1x ${w.name}\n`);
    this.activeDeck.equipment.forEach(e => text += `1x ${e.name}\n`);

    text += `\n// Main Deck\n`;
    const cardMap = {};
    this.activeDeck.main_deck.forEach(c => {
      const key = `${c.name} (${c.pitch || 1})`;
      cardMap[key] = (cardMap[key] || 0) + 1;
    });
    Object.entries(cardMap).forEach(([name, count]) => {
      text += `${count}x ${name}\n`;
    });

    textarea.value = text;
    modal.classList.add('active');
  },

  openImportModal() {
    const modal = document.getElementById('import-modal');
    if (modal) modal.classList.add('active');
  },

  async handleImportDeck() {
    const text = document.getElementById('import-textarea')?.value;
    const fmt = document.getElementById('import-format-select')?.value || 'Blitz';
    const name = document.getElementById('import-name-input')?.value || 'Imported Deck';

    if (!text || !text.trim()) {
      API.toast('Please paste a decklist to import.', 'error');
      return;
    }

    try {
      const res = await API.post('api/decks.php?action=import', {
        text,
        format: fmt,
        name
      });
      if (res.success && res.deck) {
        document.getElementById('import-modal')?.classList.remove('active');
        API.toast('Deck imported successfully!', 'success');
        this.currentDeckId = res.deck.id;
        await this.loadUserDeckList();
        await this.loadDeck(res.deck.id);
      }
    } catch (e) {
      API.toast(e.message || 'Import failed', 'error');
    }
  }
};

window.DeckBuilder = DeckBuilder;

