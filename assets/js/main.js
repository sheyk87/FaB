/**
 * Main Application Controller & View Router
 * Flesh and Blood TCG Sandbox
 */

const App = {
  currentView: 'menu',
  kpiInterval: null,
  settings: {
    rarityEffects: true,
    rarityAnimated: true,
    diceAnim: true,
    screenFlash: true
  },

  async init() {
    console.log('Initializing Flesh and Blood TCG Sandbox...');

    // 1. Initialize session & CSRF
    const sess = await API.initSession();
    this.updateUserUI();

    // 2. Setup navigation links
    document.querySelectorAll('[data-view-target]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const view = btn.getAttribute('data-view-target');
        this.navigateTo(view);
      });
    });

    // 3. Setup auth modal actions & Settings
    this.bindAuthEvents();
    this.initSettings();
    ModalDialog.init();

    // 4. Initialize KPIs polling (every 4 seconds)
    this.startKpiPolling();
    this.fetchKpis();

    // 5. Initialize individual view controllers
    Compendium.init();
    DeckBuilder.init();
    Matchmaking.init();
    RulesCodex.init();
    GameEngine.init();
  },

  initSettings() {
    try {
      const saved = localStorage.getItem('fab_arena_settings');
      if (saved) {
        this.settings = { ...this.settings, ...JSON.parse(saved) };
      }
    } catch (e) {
      console.warn('Could not load settings', e);
    }

    this.applySettings();

    // Bind settings toggles
    const rarityToggle = document.getElementById('setting-rarity-effects');
    const rarityAnimToggle = document.getElementById('setting-rarity-animated');
    const diceToggle = document.getElementById('setting-dice-anim');
    const flashToggle = document.getElementById('setting-screen-flash');

    if (rarityToggle) {
      rarityToggle.checked = !!this.settings.rarityEffects;
      rarityToggle.addEventListener('change', () => {
        this.settings.rarityEffects = rarityToggle.checked;
        this.saveSettings();
      });
    }

    if (rarityAnimToggle) {
      rarityAnimToggle.checked = !!this.settings.rarityAnimated;
      rarityAnimToggle.addEventListener('change', () => {
        this.settings.rarityAnimated = rarityAnimToggle.checked;
        this.saveSettings();
      });
    }

    if (diceToggle) {
      diceToggle.checked = !!this.settings.diceAnim;
      diceToggle.addEventListener('change', () => {
        this.settings.diceAnim = diceToggle.checked;
        this.saveSettings();
      });
    }

    if (flashToggle) {
      flashToggle.checked = !!this.settings.screenFlash;
      flashToggle.addEventListener('change', () => {
        this.settings.screenFlash = flashToggle.checked;
        this.saveSettings();
      });
    }

    // Open settings button
    document.getElementById('btn-open-settings')?.addEventListener('click', () => {
      document.getElementById('settings-modal')?.classList.add('active');
    });
  },

  saveSettings() {
    try {
      localStorage.setItem('fab_arena_settings', JSON.stringify(this.settings));
    } catch (e) {}
    this.applySettings();
  },

  applySettings() {
    document.body.classList.toggle('rarity-effects-disabled', !this.settings.rarityEffects);
    document.body.classList.toggle('rarity-animations-disabled', !this.settings.rarityAnimated);

    // Dim animated toggle if effects are completely disabled
    const animRow = document.getElementById('setting-row-rarity-animated');
    const animInput = document.getElementById('setting-rarity-animated');
    if (animRow && animInput) {
      animInput.disabled = !this.settings.rarityEffects;
      animRow.style.opacity = this.settings.rarityEffects ? '1' : '0.45';
      animRow.style.pointerEvents = this.settings.rarityEffects ? 'auto' : 'none';
    }
  },

  /**
   * Switch active view
   */
  navigateTo(viewName, params = {}) {
    // If leaving an active battlefield match, prompt for confirmation first
    if (this.currentView === 'game' && viewName !== 'game' && GameEngine.currentRoomId && GameEngine.gameState?.status !== 'finished' && !GameEngine.gameState?.winner_id) {
      GameEngine.promptLeaveMatch();
      return;
    }

    if (this.currentView === viewName && viewName !== 'game') return;

    // Update nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.classList.toggle('active', btn.getAttribute('data-view-target') === viewName);
    });

    // Hide all view sections
    document.querySelectorAll('.view-section').forEach(sec => {
      sec.classList.remove('active');
    });

    // Show target view
    const target = document.getElementById(`view-${viewName}`);
    if (target) {
      target.classList.add('active');
      this.currentView = viewName;
      window.scrollTo({ top: 0, behavior: 'smooth' });

      // View-specific activation hooks
      if (viewName === 'compendium') Compendium.onActivate();
      if (viewName === 'deckbuilder') DeckBuilder.onActivate(params);
      if (viewName === 'rules') RulesCodex.onActivate(params);
      if (viewName === 'game') GameEngine.onActivate(params);
      if (viewName === 'menu') this.fetchKpis();
    }
  },

  /**
   * Fetch real-time KPIs and update dashboard counters with animation
   */
  async fetchKpis() {
    try {
      const res = await API.get('api/kpis.php');
      if (res.success && res.kpis) {
        this.animateCounter('kpi-online', res.kpis.online_players);
        this.animateCounter('kpi-queue', res.kpis.players_in_queue);
        this.animateCounter('kpi-battles', res.kpis.active_battles);
        this.animateCounter('kpi-decks', res.kpis.total_decks);
      }
    } catch (e) {
      console.warn('KPI fetch error', e);
    }
  },

  startKpiPolling() {
    if (this.kpiInterval) clearInterval(this.kpiInterval);
    this.kpiInterval = setInterval(() => {
      this.fetchKpis();
    }, 4000);
  },

  animateCounter(elementId, targetVal) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const current = parseInt(el.textContent) || 0;
    if (current === targetVal) return;

    // Smooth increment/decrement animation
    const duration = 500;
    const steps = 15;
    const stepDiff = (targetVal - current) / steps;
    let stepCount = 0;

    const timer = setInterval(() => {
      stepCount++;
      const val = Math.round(current + stepDiff * stepCount);
      el.textContent = val;
      if (stepCount >= steps) {
        clearInterval(timer);
        el.textContent = targetVal;
      }
    }, duration / steps);
  },

  /**
   * Update header user UI
   */
  updateUserUI() {
    const userWrap = document.getElementById('user-profile-widget');
    if (!userWrap) return;

    if (API.currentUser) {
      const initial = (API.currentUser.display_name || API.currentUser.username || 'P').charAt(0).toUpperCase();
      userWrap.innerHTML = `
        <div class="user-badge" title="User Profile">
          <div class="user-avatar">${initial}</div>
          <span class="user-name">${API.escapeHtml(API.currentUser.display_name || API.currentUser.username)}</span>
        </div>
        <button class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" id="btn-logout">Logout</button>
      `;
      document.getElementById('btn-logout')?.addEventListener('click', () => this.handleLogout());
    } else {
      userWrap.innerHTML = `
        <button class="btn btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" id="btn-open-login">Sign In</button>
        <button class="btn btn-primary" style="padding:0.4rem 0.8rem; font-size:0.8rem;" id="btn-quick-play">Quick Play</button>
      `;
      document.getElementById('btn-open-login')?.addEventListener('click', () => this.openAuthModal());
      document.getElementById('btn-quick-play')?.addEventListener('click', () => this.handleQuickPlay());
    }
  },

  openAuthModal() {
    const modal = document.getElementById('auth-modal');
    if (modal) modal.classList.add('active');
  },

  closeAuthModal() {
    const modal = document.getElementById('auth-modal');
    if (modal) modal.classList.remove('active');
  },

  async handleQuickPlay() {
    try {
      const res = await API.post('api/auth.php?action=guest');
      if (res.success) {
        API.currentUser = res.user;
        this.updateUserUI();
        API.toast(`Welcome, ${res.user.display_name}!`, 'success');
        this.fetchKpis();
      }
    } catch (e) {
      API.toast(e.message || 'Quick Play failed', 'error');
    }
  },

  async handleLogout() {
    if (this.currentView === 'game' && GameEngine.currentRoomId && GameEngine.gameState?.status !== 'finished' && !GameEngine.gameState?.winner_id) {
      GameEngine.promptLeaveMatch();
      return;
    }

    try {
      await API.post('api/auth.php?action=logout');
      API.currentUser = null;
      this.updateUserUI();
      API.toast('Logged out successfully.', 'info');
      this.navigateTo('menu');
      this.fetchKpis();
    } catch (e) {
      API.toast(e.message || 'Logout failed', 'error');
    }
  },

  bindAuthEvents() {
    document.getElementById('auth-modal-close')?.addEventListener('click', () => this.closeAuthModal());
    document.getElementById('btn-guest-login')?.addEventListener('click', () => {
      this.closeAuthModal();
      this.handleQuickPlay();
    });

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        try {
          const res = await API.post('api/auth.php?action=login', { username, password });
          if (res.success) {
            API.currentUser = res.user;
            this.updateUserUI();
            this.closeAuthModal();
            API.toast(`Welcome back, ${res.user.display_name}!`, 'success');
            this.fetchKpis();
          }
        } catch (err) {
          API.toast(err.message || 'Login failed', 'error');
        }
      });
    }

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
      registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('reg-username').value;
        const displayName = document.getElementById('reg-displayname').value;
        const password = document.getElementById('reg-password').value;
        try {
          const res = await API.post('api/auth.php?action=register', {
            username,
            display_name: displayName,
            password
          });
          if (res.success) {
            API.currentUser = res.user;
            this.updateUserUI();
            this.closeAuthModal();
            API.toast(`Account created! Welcome, ${res.user.display_name}!`, 'success');
            this.fetchKpis();
          }
        } catch (err) {
          API.toast(err.message || 'Registration failed', 'error');
        }
      });
    }

    // Tab switcher in Auth Modal
    document.querySelectorAll('.auth-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.auth-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const targetTab = btn.getAttribute('data-auth-tab');
        document.getElementById('auth-login-pane').style.display = (targetTab === 'login') ? 'block' : 'none';
        document.getElementById('auth-register-pane').style.display = (targetTab === 'register') ? 'block' : 'none';
      });
    });
  }
};

window.App = App;

window.addEventListener('DOMContentLoaded', () => {
  App.init();
});
