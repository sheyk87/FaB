/**
 * Rules Codex & Comprehensive Rules Controller
 * Flesh and Blood TCG Sandbox
 */

const RulesCodex = {
  rulesData: null,
  activeChapterId: '01-game-concepts',

  init() {
    this.bindEvents();
  },

  async onActivate(params = {}) {
    if (!this.rulesData) {
      await this.loadRules();
    }
    if (params.search) {
      this.searchRules(params.search);
    }
  },

  bindEvents() {
    // Search input with debounce
    let searchDebounce;
    document.getElementById('rules-search-input')?.addEventListener('input', (e) => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        this.searchRules(e.target.value.trim());
      }, 300);
    });

    // Quick Keyword pills
    document.querySelectorAll('.kw-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        const kw = pill.getAttribute('data-keyword');
        const input = document.getElementById('rules-search-input');
        if (input) input.value = kw;
        this.searchRules(kw);
      });
    });
  },

  async loadRules() {
    try {
      const res = await API.get('api/rules.php?action=all');
      if (res.success && res.chapters) {
        this.rulesData = res.chapters;
        this.renderSidebar(res.chapters);
        this.renderContent(res.chapters);
      }
    } catch (e) {
      console.error('Failed to load rules', e);
    }
  },

  renderSidebar(chapters) {
    const nav = document.getElementById('rules-sidebar-nav');
    if (!nav) return;

    nav.innerHTML = `
      <div class="rules-nav-title">CR Chapters</div>
      ${chapters.map(ch => `
        <a class="rules-chapter-link ${ch.id === this.activeChapterId ? 'active' : ''}" data-ch-id="${ch.id}" onclick="RulesCodex.scrollToChapter('${ch.id}')">
          <span style="font-family:var(--font-mono); font-size:0.75rem; color:var(--text-gold);">${ch.num}</span>
          <span>${API.escapeHtml(ch.title)}</span>
        </a>
      `).join('')}
    `;
  },

  renderContent(chapters) {
    const container = document.getElementById('rules-content-container');
    if (!container) return;

    container.innerHTML = chapters.map(ch => `
      <div class="rules-chapter-block" id="chapter-${ch.id}">
        <h2 class="chapter-heading">${ch.num}. ${API.escapeHtml(ch.title)}</h2>
        <div class="chapter-subheading">${API.escapeHtml(ch.subtitle || '')}</div>
        
        <div class="rules-sections-list">
          ${ch.sections.map(sec => `
            <div class="rule-section-card" id="sec-${sec.id}">
              <h3 class="rule-section-title">${API.escapeHtml(sec.title)}</h3>
              <div class="rule-section-body">${API.formatRulesGlyphs(sec.content)}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `).join('');
  },

  scrollToChapter(chapterId) {
    this.activeChapterId = chapterId;
    document.querySelectorAll('.rules-chapter-link').forEach(l => {
      l.classList.toggle('active', l.getAttribute('data-ch-id') === chapterId);
    });

    const target = document.getElementById(`chapter-${chapterId}`);
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  },

  async searchRules(query) {
    const container = document.getElementById('rules-content-container');
    if (!container) return;

    if (!query) {
      if (this.rulesData) this.renderContent(this.rulesData);
      return;
    }

    try {
      const res = await API.get(`api/rules.php?action=search&q=${encodeURIComponent(query)}`);
      if (res.success && res.matches) {
        if (res.matches.length === 0) {
          container.innerHTML = `<div style="text-align:center; padding:4rem; color:var(--text-dim);">No rules found matching "<strong>${API.escapeHtml(query)}</strong>".</div>`;
          return;
        }

        container.innerHTML = `
          <div style="margin-bottom:1rem; color:var(--text-gold); font-size:1.1rem; font-family:var(--font-title);">
            Search Results for "${API.escapeHtml(query)}" (${res.matches.length} matches)
          </div>
          ${res.matches.map(m => `
            <div class="rule-section-card" style="border-left: 3px solid var(--border-gold);">
              <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase; margin-bottom:4px;">${API.escapeHtml(m.chapter_title)}</div>
              <h3 class="rule-section-title">${API.escapeHtml(m.section.title)}</h3>
              <div class="rule-section-body">${API.formatRulesGlyphs(m.section.content)}</div>
            </div>
          `).join('')}
        `;
      }
    } catch (e) {
      console.error('Rules search failed', e);
    }
  },

  searchFromCompendium(keyword) {
    document.getElementById('card-detail-modal')?.classList.remove('active');
    App.navigateTo('rules', { search: keyword });
  }
};

window.RulesCodex = RulesCodex;

