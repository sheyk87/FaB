/**
 * Card Compendium & Library Controller
 * Flesh and Blood TCG Sandbox
 */

const Compendium = {
  currentPage: 1,
  totalPages: 1,
  limit: 36,
  filters: {
    query: '',
    set_id: '',
    pitch: 'all',
    cost: 'all',
    class: 'all',
    card_type: 'all',
    format: '',
    sort: ''
  },
  setsLoaded: false,

  init() {
    this.bindEvents();
  },

  async onActivate() {
    if (!this.setsLoaded) {
      await this.loadSets();
    }
    this.fetchCards();
  },

  bindEvents() {
    // Search input with debounce
    const searchInput = document.getElementById('comp-search-input');
    let debounceTimer;
    searchInput?.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        this.filters.query = e.target.value.trim();
        this.currentPage = 1;
        this.fetchCards();
      }, 350);
    });

    // Pitch filter buttons
    document.querySelectorAll('.pitch-toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.pitch-toggle-btn').forEach(b => {
          b.classList.remove('active-all', 'active-red', 'active-yellow', 'active-blue');
        });
        const pitch = btn.getAttribute('data-pitch');
        this.filters.pitch = pitch;
        if (pitch === 'all') btn.classList.add('active-all');
        if (pitch === '1') btn.classList.add('active-red');
        if (pitch === '2') btn.classList.add('active-yellow');
        if (pitch === '3') btn.classList.add('active-blue');

        this.currentPage = 1;
        this.fetchCards();
      });
    });

    // Select filters
    document.getElementById('comp-set-select')?.addEventListener('change', (e) => {
      this.filters.set_id = e.target.value;
      this.currentPage = 1;
      this.fetchCards();
    });

    document.getElementById('comp-class-select')?.addEventListener('change', (e) => {
      this.filters.class = e.target.value;
      this.currentPage = 1;
      this.fetchCards();
    });

    document.getElementById('comp-type-select')?.addEventListener('change', (e) => {
      this.filters.card_type = e.target.value;
      this.currentPage = 1;
      this.fetchCards();
    });

    document.getElementById('comp-cost-select')?.addEventListener('change', (e) => {
      this.filters.cost = e.target.value;
      this.currentPage = 1;
      this.fetchCards();
    });

    document.getElementById('comp-sort-select')?.addEventListener('change', (e) => {
      this.filters.sort = e.target.value;
      this.currentPage = 1;
      this.fetchCards();
    });

    // Pagination
    document.getElementById('comp-prev-page')?.addEventListener('click', () => {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.fetchCards();
        window.scrollTo({ top: 150, behavior: 'smooth' });
      }
    });

    document.getElementById('comp-next-page')?.addEventListener('click', () => {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.fetchCards();
        window.scrollTo({ top: 150, behavior: 'smooth' });
      }
    });

    // Modal close
    document.getElementById('card-modal-close')?.addEventListener('click', () => {
      document.getElementById('card-detail-modal')?.classList.remove('active');
    });
  },

  async loadSets() {
    try {
      const res = await API.get('api/cards.php?action=sets');
      if (res.success && res.sets) {
        const select = document.getElementById('comp-set-select');
        if (select) {
          select.innerHTML = '<option value="">All Sets</option>' +
            res.sets.map(s => `<option value="${s.id}">${s.name} (${s.id}) - ${s.card_count} cards</option>`).join('');
        }
        this.setsLoaded = true;
      }
    } catch (e) {
      console.error('Failed to load sets', e);
    }
  },

  async fetchCards() {
    const grid = document.getElementById('compendium-card-grid');
    if (!grid) return;

    grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:3rem; color:var(--text-muted);">Fetching cards from Rathe...</div>';

    const params = new URLSearchParams({
      action: 'search',
      page: this.currentPage,
      limit: this.limit,
      query: this.filters.query,
      set_id: this.filters.set_id,
      pitch: this.filters.pitch,
      cost: this.filters.cost,
      class: this.filters.class,
      card_type: this.filters.card_type,
      sort: this.filters.sort
    });

    try {
      const res = await API.get(`api/cards.php?${params.toString()}`);
      if (res.success) {
        this.totalPages = res.total_pages || 1;
        this.renderCards(res.cards);
        this.updatePagination(res.total, res.page, res.total_pages);
      }
    } catch (e) {
      grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:3rem; color:var(--color-life);">Error loading cards: ${e.message}</div>`;
    }
  },

  renderCards(cards) {
    const grid = document.getElementById('compendium-card-grid');
    if (!grid) return;

    if (!cards || cards.length === 0) {
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:4rem; color:var(--text-dim); font-size:1.1rem;">No Flesh and Blood cards match your filter criteria.</div>';
      return;
    }

    grid.innerHTML = cards.map(c => {
      let pitchClass = '';
      if (c.pitch === 1) pitchClass = 'pitch-red';
      else if (c.pitch === 2) pitchClass = 'pitch-yellow';
      else if (c.pitch === 3) pitchClass = 'pitch-blue';

      const statsBadges = [];
      if (c.cost !== null && c.cost !== '') statsBadges.push(`<span title="Cost" class="glyph glyph-resource">{${c.cost}}</span>`);
      if (c.pitch) statsBadges.push(`<span title="Pitch ${c.pitch}" class="glyph glyph-pitch-${c.pitch}">${c.pitch}</span>`);
      if (c.power !== null && c.power !== '') statsBadges.push(`<span title="Power" style="color:var(--color-attack)">⚔️${c.power}</span>`);
      if (c.defense !== null && c.defense !== '') statsBadges.push(`<span title="Defense" style="color:var(--color-defense)">🛡️${c.defense}</span>`);
      if (c.health !== null && c.health !== '') statsBadges.push(`<span title="Health" style="color:var(--color-life)">❤️${c.health}</span>`);

      const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='280' viewBox='0 0 200 280'%3E%3Crect width='200' height='280' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='14'%3E" + encodeURIComponent(c.name) + "%3C/text%3E%3C/svg%3E";

      const rKey = (c.rarity || 'C').toUpperCase();
      const hasFoil = ['F', 'L', 'M', 'S', 'R'].includes(rKey);
      const foilType = rKey === 'F' ? 'fabled' : (rKey === 'L' ? 'legendary' : (rKey === 'M' ? 'majestic' : (rKey === 'S' ? 'super-rare' : 'rare')));
      const rarityGlowClass = hasFoil ? `card-rarity-${rKey.toLowerCase()}` : '';

      return `
        <div class="fab-card-item ${pitchClass} ${rarityGlowClass}" onclick="Compendium.openCardDetail('${c.unique_id}')">
          <div class="card-img-wrap rarity-card-wrap ${rarityGlowClass}">
            <img class="card-img" src="${c.image_url || imgPlaceholder}" alt="${API.escapeHtml(c.name)}" loading="lazy" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
            ${hasFoil ? `<div class="foil-sheen-overlay foil-${foilType}"></div>` : ''}
            <div class="card-badge-row">
              <span class="card-rarity-pill">${c.rarity || 'C'}</span>
              <span class="card-rarity-pill">${c.set_id || ''}</span>
            </div>
          </div>
          <div class="card-meta-bar">
            <div class="card-name-label" title="${API.escapeHtml(c.name)}">${API.escapeHtml(c.name)}</div>
            <div class="card-type-label">${API.escapeHtml(c.type_text || '')}</div>
            <div class="card-stats-pill-row">${statsBadges.join(' ')}</div>
          </div>
        </div>
      `;
    }).join('');
  },

  updatePagination(total, page, totalPages) {
    const info = document.getElementById('comp-page-info');
    if (info) {
      info.textContent = `Page ${page} of ${totalPages} (${total} cards)`;
    }
    const prev = document.getElementById('comp-prev-page');
    const next = document.getElementById('comp-next-page');
    if (prev) prev.disabled = (page <= 1);
    if (next) next.disabled = (page >= totalPages);
  },

  async openCardDetail(uniqueId) {
    try {
      const res = await API.get(`api/cards.php?action=detail&id=${uniqueId}`);
      if (res.success && res.card) {
        const c = res.card;
        const modal = document.getElementById('card-detail-modal');
        const body = document.getElementById('card-detail-modal-body');
        if (!modal || !body) return;

        const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='420' viewBox='0 0 300 420'%3E%3Crect width='300' height='420' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='16'%3E" + encodeURIComponent(c.name) + "%3C/text%3E%3C/svg%3E";

        const rarityKey = (c.rarity || 'C').toUpperCase();
        let rarityLabel = 'Common';
        let pillClass = 'rarity-pill-c';
        let glowClass = '';
        let foilClass = '';

        if (rarityKey === 'F') {
          rarityLabel = '🌟 Fabled';
          pillClass = 'rarity-pill-f';
          glowClass = 'rarity-glow-f';
          foilClass = 'foil-fabled';
        } else if (rarityKey === 'L') {
          rarityLabel = '👑 Legendary';
          pillClass = 'rarity-pill-l';
          glowClass = 'rarity-glow-l';
          foilClass = 'foil-legendary';
        } else if (rarityKey === 'M') {
          rarityLabel = '💎 Majestic';
          pillClass = 'rarity-pill-m';
          glowClass = 'rarity-glow-m';
          foilClass = 'foil-majestic';
        } else if (rarityKey === 'S') {
          rarityLabel = '⚡ Super Rare';
          pillClass = 'rarity-pill-s';
          glowClass = 'rarity-glow-s';
          foilClass = 'foil-super-rare';
        } else if (rarityKey === 'R') {
          rarityLabel = '⚔️ Rare';
          pillClass = 'rarity-pill-r';
          glowClass = 'rarity-glow-r';
          foilClass = 'foil-rare';
        } else if (rarityKey === 'T') {
          rarityLabel = 'Token';
          pillClass = 'rarity-pill-t';
        }

        body.innerHTML = `
          <div class="card-detail-layout">
            <div>
              <div class="rarity-card-wrap ${glowClass}" id="detail-card-interactive-box">
                <img class="card-detail-preview-img" src="${c.image_url || imgPlaceholder}" alt="${API.escapeHtml(c.name)}" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
                ${foilClass ? `<div class="foil-sheen-overlay ${foilClass}"></div>` : ''}
              </div>
            </div>
            <div class="card-detail-info">
              <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:0.4rem;">
                <h2 class="detail-card-name" style="margin:0;">${API.escapeHtml(c.name)}</h2>
                <span class="rarity-pill-badge ${pillClass}">${rarityLabel}</span>
              </div>
              <div class="detail-type-text">${API.escapeHtml(c.type_text || '')}</div>
              
              <div class="detail-stats-bar">
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:var(--color-resource);">${c.cost !== null && c.cost !== '' ? c.cost : '-'}</span>
                  <span class="detail-stat-lbl">Cost</span>
                </div>
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:var(--pitch-${c.pitch || '1'});">${c.pitch || '-'}</span>
                  <span class="detail-stat-lbl">Pitch</span>
                </div>
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:var(--color-attack);">${c.power !== null && c.power !== '' ? c.power : '-'}</span>
                  <span class="detail-stat-lbl">Power</span>
                </div>
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:var(--color-defense);">${c.defense !== null && c.defense !== '' ? c.defense : '-'}</span>
                  <span class="detail-stat-lbl">Defense</span>
                </div>
                ${c.health ? `
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:var(--color-life);">${c.health}</span>
                  <span class="detail-stat-lbl">Health</span>
                </div>` : ''}
                ${c.intelligence ? `
                <div class="detail-stat-item">
                  <span class="detail-stat-val" style="color:#c084fc;">${c.intelligence}</span>
                  <span class="detail-stat-lbl">Intellect</span>
                </div>` : ''}
              </div>

              ${c.functional_text ? `
              <div class="detail-oracle-text">${API.formatRulesGlyphs(c.functional_text)}</div>
              ` : ''}

              <div style="display:flex; gap:0.5rem; font-size:0.85rem; color:var(--text-muted); align-items:center; flex-wrap:wrap;">
                <span><strong>Set:</strong> ${c.set_id || '-'}</span>
                <span>•</span>
                <span><strong>Rarity:</strong> ${c.rarity || '-'}</span>
                <span>•</span>
                <span><strong>Collector #:</strong> ${c.collector_number || '-'}</span>
              </div>

              <div style="margin-top:auto; padding-top:1rem; display:flex; gap:1rem;">
                <button class="btn btn-primary" onclick="DeckBuilder.addCardToActiveDeck('${c.unique_id}')">➕ Add to Active Deck</button>
                <button class="btn btn-secondary" onclick="RulesCodex.searchFromCompendium('${c.keywords?.[0] || ''}')">📖 Check Rules</button>
              </div>
            </div>
          </div>
        `;

        // Interactive mouse tilt and sheen angle reflection
        const cardBox = document.getElementById('detail-card-interactive-box');
        if (cardBox) {
          cardBox.addEventListener('mousemove', (e) => {
            const rect = cardBox.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * -10;
            const rotateY = ((x - centerX) / centerX) * 10;
            cardBox.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
          });

          cardBox.addEventListener('mouseleave', () => {
            cardBox.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
          });
        }

        modal.classList.add('active');
      }
    } catch (e) {
      API.toast('Failed to load card details', 'error');
    }
  }
};

window.Compendium = Compendium;

