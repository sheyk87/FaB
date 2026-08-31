/**
 * Sandbox Battlefield Game Engine
 * Flesh and Blood TCG Sandbox
 * Server-Authoritative State & Interactive Tabletop
 */

const GameEngine = {
  currentRoomId: null,
  currentRoomCode: '',
  gameState: null,
  isMyTurn: false,
  myPlayerId: null,
  pollInterval: null,
  chatPollInterval: null,
  turnTimerInterval: null,
  matchTimerInterval: null,
  lastChatId: 0,
  lastSequenceNum: 0,
  lastTurnNumber: 0,
  hasShownResultModal: false,
  previousLife: null,

  init() {
    this.bindEvents();
    this.bindNavigationGuards();
  },

  onActivate(params = {}) {
    if (params.room_id) {
      this.currentRoomId = params.room_id;
      this.currentRoomCode = params.room_code || '';
      this.hasShownResultModal = false;
      this.previousLife = null;
      document.getElementById('arena-room-code-badge').textContent = `Room: ${this.currentRoomCode}`;
      this.loadGameRoom();
      this.startStateSync();
      this.startChatSync();
      this.startTimers();
    }
  },

  bindNavigationGuards() {
    // Warn before closing browser tab during active match
    window.addEventListener('beforeunload', (e) => {
      if (this.currentRoomId && this.gameState?.status !== 'finished' && !this.gameState?.winner_id) {
        e.preventDefault();
        e.returnValue = 'Are you sure you want to leave? Active match will be forfeited.';
        return e.returnValue;
      }
    });
  },

  bindEvents() {
    // End Turn Button
    document.getElementById('btn-arena-end-turn')?.addEventListener('click', () => {
      if (!this.isMyTurn) {
        API.toast('It is not your turn to pass!', 'error');
        return;
      }
      this.executeAction('end_turn', {});
    });

    // Close Combat Chain Button
    document.getElementById('btn-close-chain')?.addEventListener('click', () => {
      this.executeAction('close_combat_chain', {});
    });

    // Draw Card Button
    document.getElementById('btn-deck-draw')?.addEventListener('click', () => {
      this.executeAction('draw_card', { count: 1 });
    });

    // Shuffle Deck Button
    document.getElementById('btn-deck-shuffle')?.addEventListener('click', () => {
      this.executeAction('shuffle_deck', {});
    });

    // Roll Dice Buttons with 3D Animations
    document.getElementById('btn-roll-d6')?.addEventListener('click', () => this.handleDiceRoll('d6'));
    document.getElementById('btn-roll-d20')?.addEventListener('click', () => this.handleDiceRoll('d20'));
    document.getElementById('btn-flip-coin')?.addEventListener('click', () => this.handleDiceRoll('coin'));

    // Leave / Exit Match button (Safe Confirmation)
    document.getElementById('btn-arena-leave')?.addEventListener('click', () => this.promptLeaveMatch());
    document.getElementById('btn-confirm-leave-match')?.addEventListener('click', () => this.confirmLeaveMatch());
    document.getElementById('btn-cancel-leave-match')?.addEventListener('click', () => {
      document.getElementById('leave-match-modal')?.classList.remove('active');
    });

    // Life total modification buttons
    document.querySelectorAll('[data-life-delta]').forEach(btn => {
      btn.addEventListener('click', () => {
        const delta = parseInt(btn.getAttribute('data-life-delta'));
        const target = btn.getAttribute('data-life-target') === 'opponent' ? this.getOpponentId() : this.myPlayerId;
        this.executeAction('update_life', { delta, target_user_id: target });
      });
    });

    // Resource Points & AP adjustments
    document.getElementById('btn-res-plus')?.addEventListener('click', () => this.executeAction('update_resources', { delta: 1 }));
    document.getElementById('btn-res-minus')?.addEventListener('click', () => this.executeAction('update_resources', { delta: -1 }));
    document.getElementById('btn-ap-plus')?.addEventListener('click', () => this.executeAction('update_action_points', { delta: 1 }));
    document.getElementById('btn-ap-minus')?.addEventListener('click', () => this.executeAction('update_action_points', { delta: -1 }));

    // Concede Button with Thematic Modal
    document.getElementById('btn-arena-concede')?.addEventListener('click', async () => {
      const confirmed = await ModalDialog.confirm('Are you sure you want to concede this battle to your opponent?', 'Concede Match', '🏳️', 'Yes, Concede', 'Cancel');
      if (confirmed) {
        this.executeAction('concede', {});
      }
    });

    // Result Modal Action Buttons
    document.getElementById('btn-result-play-again')?.addEventListener('click', () => this.playAgain());
    document.getElementById('btn-result-return-home')?.addEventListener('click', () => this.exitToHome());

    // Drawer tabs (Log vs Chat)
    document.querySelectorAll('.drawer-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.drawer-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.getAttribute('data-drawer-tab');
        document.getElementById('drawer-log-pane').style.display = (tab === 'log') ? 'flex' : 'none';
        document.getElementById('drawer-chat-pane').style.display = (tab === 'chat') ? 'flex' : 'none';
      });
    });

    // Chat form submit
    const chatForm = document.getElementById('arena-chat-form');
    chatForm?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = document.getElementById('arena-chat-input');
      const msg = input.value.trim();
      if (!msg || !this.currentRoomId) return;

      input.value = '';
      try {
        await API.post('api/chat.php?action=send', {
          room_id: this.currentRoomId,
          message: msg
        });
        this.fetchChat();
      } catch (err) {
        API.toast(err.message || 'Failed to send chat', 'error');
      }
    });

    // Zone inspector modal close
    document.getElementById('zone-inspector-modal-close')?.addEventListener('click', () => {
      document.getElementById('zone-inspector-modal')?.classList.remove('active');
    });

    // Card Action Context Menu Modal close
    document.getElementById('card-action-menu-close')?.addEventListener('click', () => {
      document.getElementById('card-action-menu-modal')?.classList.remove('active');
    });
  },

  async loadGameRoom() {
    if (!this.currentRoomId) return;

    try {
      const res = await API.get(`api/game.php?action=state&room_id=${this.currentRoomId}`);
      if (res.success && res.state) {
        this.gameState = res.state;
        if (res.room) {
          this.gameState.status = res.room.status;
          this.gameState.winner_id = res.room.winner_id;
        }
        this.isMyTurn = res.is_my_turn;
        this.myPlayerId = res.my_player_id;
        this.renderBattlefield();
      }
    } catch (e) {
      API.toast('Error connecting to battle room', 'error');
    }
  },

  startStateSync() {
    if (this.pollInterval) clearInterval(this.pollInterval);

    this.pollInterval = setInterval(async () => {
      if (App.currentView !== 'game' || !this.currentRoomId) return;

      try {
        const res = await API.get(`api/game.php?action=state&room_id=${this.currentRoomId}`);
        if (res.success && res.state) {
          this.gameState = res.state;
          if (res.room) {
            this.gameState.status = res.room.status;
            this.gameState.winner_id = res.room.winner_id;
          }
          this.isMyTurn = res.is_my_turn;
          this.myPlayerId = res.my_player_id;
          this.renderBattlefield();
        }
      } catch (e) {
        console.warn('Sync error', e);
      }
    }, 1200);
  },

  startChatSync() {
    if (this.chatPollInterval) clearInterval(this.chatPollInterval);
    this.fetchChat();

    this.chatPollInterval = setInterval(() => {
      if (App.currentView === 'game' && this.currentRoomId) {
        this.fetchChat();
      }
    }, 2000);
  },

  async fetchChat() {
    if (!this.currentRoomId) return;
    try {
      const res = await API.get(`api/chat.php?action=list&room_id=${this.currentRoomId}&since_id=${this.lastChatId}`);
      if (res.success && res.messages && res.messages.length > 0) {
        const box = document.getElementById('arena-chat-messages');
        if (box) {
          res.messages.forEach(msg => {
            this.lastChatId = Math.max(this.lastChatId, msg.id);
            const div = document.createElement('div');
            div.className = 'log-entry';
            div.innerHTML = `<span class="log-time">${msg.created_at.split(' ')[1] || ''}</span> <strong>${API.escapeHtml(msg.user_name)}:</strong> ${API.escapeHtml(msg.message)}`;
            box.appendChild(div);
          });
          box.scrollTop = box.scrollHeight;
        }
      }
    } catch (e) {
      console.warn('Chat poll error', e);
    }
  },

  async executeAction(actionType, payload = {}) {
    if (!this.currentRoomId) return;

    try {
      const res = await API.post('api/game.php?action=action', {
        room_id: this.currentRoomId,
        action_type: actionType,
        payload: payload
      });

      if (res.success && res.state) {
        this.gameState = res.state;
        if (res.room) {
          this.gameState.status = res.room.status;
          this.gameState.winner_id = res.room.winner_id;
        }
        this.isMyTurn = res.is_my_turn;
        this.myPlayerId = res.my_player_id;
        this.renderBattlefield();
      }
    } catch (e) {
      API.toast(e.message || 'Action failed', 'error');
    }
  },

  getOpponentId() {
    if (!this.gameState || !this.gameState.players) return null;
    const ids = Object.keys(this.gameState.players);
    const opp = ids.find(id => parseInt(id) !== this.myPlayerId);
    return opp ? parseInt(opp) : null;
  },

  renderBattlefield() {
    if (!this.gameState || !this.gameState.players) return;

    const myKey = String(this.myPlayerId);
    const oppKey = String(this.getOpponentId());

    const myState = this.gameState.players[myKey];
    const oppState = this.gameState.players[oppKey] || {
      name: 'Waiting for Opponent...',
      life: 20,
      resources: 0,
      action_points: 0,
      hand: [],
      deck_count: 40,
      equipment: {},
      hero: null,
      graveyard: [],
      banished: [],
      pitch: [],
      arsenal: []
    };

    // 1. Check for Damage Flash
    if (myState && this.previousLife !== null && myState.life < this.previousLife) {
      this.triggerDamageFlash();
    }
    if (myState) {
      this.previousLife = myState.life;
    }

    // 2. Check for Turn Transition Splash
    const currentTurn = this.gameState.turn || 1;
    if (currentTurn !== this.lastTurnNumber) {
      this.lastTurnNumber = currentTurn;
      if (this.isMyTurn) {
        this.showTurnSplash();
      }
    }

    // 3. Check for Victory / Defeat Modal Trigger
    if (this.gameState.winner_id || this.gameState.status === 'finished') {
      const isWinner = (this.gameState.winner_id === this.myPlayerId);
      const winUser = (this.gameState.winner_id === this.myPlayerId) ? myState : oppState;
      this.showVictoryDefeatModal({
        id: this.gameState.winner_id,
        name: winUser?.name || (isWinner ? 'You' : 'Opponent'),
        is_me: isWinner
      });
    }

    // 4. Turn & Priority Status Indicator
    const turnLabel = document.getElementById('arena-turn-indicator');
    if (turnLabel) {
      const activeName = (this.gameState.active_player_id === this.myPlayerId) ? 'Your Turn' : `${oppState.name}'s Turn`;
      turnLabel.textContent = `Turn ${this.gameState.turn || 1} • ${activeName}`;
      turnLabel.style.color = (this.gameState.active_player_id === this.myPlayerId) ? 'var(--text-gold)' : 'var(--text-muted)';
    }

    // 5. Update End Turn button styling based on whether it's your turn
    const endTurnBtn = document.getElementById('btn-arena-end-turn');
    if (endTurnBtn) {
      if (this.isMyTurn) {
        endTurnBtn.classList.remove('btn-secondary');
        endTurnBtn.classList.add('btn-primary');
        endTurnBtn.removeAttribute('disabled');
      } else {
        endTurnBtn.classList.remove('btn-primary');
        endTurnBtn.classList.add('btn-secondary');
      }
    }

    // 6. Render Opponent Sector
    this.renderSector('opponent', oppState);

    // 7. Render Player Sector
    this.renderSector('player', myState);

    // 8. Render Combat Chain
    this.renderCombatChain();

    // 9. Render Action Log
    this.renderLog();
  },

  renderSector(type, pState) {
    if (!pState) return;

    const isSelf = (type === 'player');
    const prefix = isSelf ? 'p1' : 'p2';

    // Name & Life
    const nameEl = document.getElementById(`${prefix}-hero-name`);
    if (nameEl) nameEl.textContent = pState.name || 'Hero';

    const lifeEl = document.getElementById(`${prefix}-life-val`);
    if (lifeEl) lifeEl.textContent = pState.life ?? 20;

    const resEl = document.getElementById(`${prefix}-res-val`);
    if (resEl) resEl.textContent = pState.resources ?? 0;

    const apEl = document.getElementById(`${prefix}-ap-val`);
    if (apEl) apEl.textContent = pState.action_points ?? 1;

    // Hero Portrait
    const heroBox = document.getElementById(`${prefix}-hero-portrait`);
    if (heroBox && pState.hero) {
      const h = pState.hero;
      const hRarity = (h.rarity || 'c').toLowerCase();
      heroBox.className = `hero-portrait-card ${h.tapped ? 'tapped' : ''} card-rarity-${hRarity}`;
      heroBox.innerHTML = `
        <img class="hero-portrait-img" src="${h.image_url || ''}" alt="${API.escapeHtml(h.name)}" />
        ${(h.counters?.power || 0) > 0 ? `<div class="card-counter-badge">+${h.counters.power}</div>` : ''}
      `;
      if (isSelf) {
        heroBox.onclick = () => this.openCardActionMenu(h, 'hero', '');
      }
    }

    // Equipment line
    const eqCluster = document.getElementById(`${prefix}-equipment-cluster`);
    if (eqCluster) {
      const slots = ['head', 'chest', 'arms', 'legs', 'weapon1', 'weapon2'];
      eqCluster.innerHTML = slots.map(slot => {
        const item = pState.equipment ? pState.equipment[slot] : null;
        if (item) {
          const eqRarity = (item.rarity || 'c').toLowerCase();
          const hasFoil = ['f', 'l', 'm', 's', 'r'].includes(eqRarity);
          const foilType = eqRarity === 'f' ? 'fabled' : (eqRarity === 'l' ? 'legendary' : (eqRarity === 'm' ? 'majestic' : (eqRarity === 's' ? 'super-rare' : 'rare')));
          return `
            <div class="gear-slot ${item.tapped ? 'tapped' : ''} card-rarity-${eqRarity}" title="${API.escapeHtml(item.name)}" onclick="${isSelf ? `GameEngine.openCardActionMenuById('${item.instance_id}', 'equipment', '${slot}')` : ''}">
              <img class="gear-slot-img" src="${item.image_url || ''}" alt="${API.escapeHtml(item.name)}" />
              ${hasFoil ? `<div class="foil-sheen-overlay foil-${foilType}"></div>` : ''}
              ${(item.counters?.power || 0) > 0 ? `<div class="card-counter-badge">+${item.counters.power}</div>` : ''}
            </div>
          `;
        } else {
          return `<div class="gear-slot"><span class="gear-slot-label">${slot}</span></div>`;
        }
      }).join('');
    }

    // Zone Counts
    const gyCountEl = document.getElementById(`${prefix}-gy-count`);
    if (gyCountEl) {
      gyCountEl.textContent = pState.graveyard ? pState.graveyard.length : 0;
      gyCountEl.parentElement.onclick = () => this.openZoneInspector(`${pState.name}'s Graveyard`, pState.graveyard || []);
    }

    const banCountEl = document.getElementById(`${prefix}-ban-count`);
    if (banCountEl) {
      banCountEl.textContent = pState.banished ? pState.banished.length : 0;
      banCountEl.parentElement.onclick = () => this.openZoneInspector(`${pState.name}'s Banished`, pState.banished || []);
    }

    const pitchCountEl = document.getElementById(`${prefix}-pitch-count`);
    if (pitchCountEl) {
      pitchCountEl.textContent = pState.pitch ? pState.pitch.length : 0;
      pitchCountEl.parentElement.onclick = () => this.openZoneInspector(`${pState.name}'s Pitch Zone`, pState.pitch || []);
    }

    const deckCountEl = document.getElementById(`${prefix}-deck-count`);
    if (deckCountEl) deckCountEl.textContent = pState.deck_count ?? 40;

    // Arsenal Box
    const arsenalBox = document.getElementById(`${prefix}-arsenal-box`);
    if (arsenalBox) {
      const arsCard = pState.arsenal && pState.arsenal[0];
      if (arsCard) {
        if (arsCard.is_hidden || arsCard.face_down) {
          arsenalBox.innerHTML = `
            <div style="font-size:1.5rem;">🎴</div>
            <div class="zone-box-title">Arsenal (Down)</div>
          `;
        } else {
          arsenalBox.innerHTML = `
            <img class="gear-slot-img" src="${arsCard.image_url || ''}" alt="${API.escapeHtml(arsCard.name)}" />
          `;
        }
        if (isSelf) {
          arsenalBox.onclick = () => this.openCardActionMenu(arsCard, 'arsenal', '');
        }
      } else {
        arsenalBox.innerHTML = `
          <div class="zone-box-count">0</div>
          <div class="zone-box-title">Arsenal</div>
        `;
      }
    }

    // Hand Cards (Fan)
    const handContainer = document.getElementById(`${prefix}-hand-container`);
    if (handContainer) {
      if (!isSelf) {
        // Render masked cards for opponent
        const oppHandCount = pState.hand ? pState.hand.length : 0;
        handContainer.innerHTML = Array.from({ length: oppHandCount }).map(() => `
          <div class="hand-card-item" style="background:linear-gradient(135deg, #182030, #0a0d14); border-color:var(--border-dark); display:flex; align-items:center; justify-content:center;">
            <span style="font-size:1.4rem;">🎴</span>
          </div>
        `).join('');
      } else {
        // Render player's own fully interactive hand
        const cards = pState.hand || [];
        handContainer.innerHTML = cards.map(c => {
          const pitchClass = `pitch-${c.pitch || '1'}`;
          const cRarity = (c.rarity || 'c').toLowerCase();
          const hasFoil = ['f', 'l', 'm', 's', 'r'].includes(cRarity);
          const foilType = cRarity === 'f' ? 'fabled' : (cRarity === 'l' ? 'legendary' : (cRarity === 'm' ? 'majestic' : (cRarity === 's' ? 'super-rare' : 'rare')));
          const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='112' viewBox='0 0 80 112'%3E%3Crect width='80' height='112' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='9'%3E" + encodeURIComponent(c.name) + "%3C/text%3E%3C/svg%3E";

          return `
            <div class="hand-card-item ${pitchClass} card-rarity-${cRarity}" onclick="GameEngine.openCardActionMenuById('${c.instance_id}', 'hand', '')">
              <img class="hand-card-img" src="${c.image_url || imgPlaceholder}" alt="${API.escapeHtml(c.name)}" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
              ${hasFoil ? `<div class="foil-sheen-overlay foil-${foilType}"></div>` : ''}
              <div class="card-quick-actions">
                <button class="q-act-btn" onclick="event.stopPropagation(); GameEngine.executeAction('attack_card', { instance_id: '${c.instance_id}', from_zone: 'hand' })" title="Play Attack">⚔️Atk</button>
                <button class="q-act-btn" onclick="event.stopPropagation(); GameEngine.executeAction('pitch_card', { instance_id: '${c.instance_id}' })" title="Pitch for Resources">⚡Pitch</button>
                <button class="q-act-btn" onclick="event.stopPropagation(); GameEngine.executeAction('defend_card', { instance_id: '${c.instance_id}', from_zone: 'hand' })" title="Defend Chain Link">🛡️Def</button>
              </div>
            </div>
          `;
        }).join('');
      }
    }
  },

  renderCombatChain() {
    const arena = document.getElementById('arena-combat-chain-box');
    const linksRow = document.getElementById('arena-chain-links-row');
    if (!arena || !linksRow) return;

    const chain = this.gameState.combat_chain;
    if (!chain || !chain.is_open || !chain.links || chain.links.length === 0) {
      arena.classList.remove('chain-active');
      linksRow.innerHTML = '<div class="chain-empty-notice">Combat Chain is currently closed. Declare an attack to open link 1.</div>';
      document.getElementById('btn-close-chain').style.display = 'none';
      return;
    }

    arena.classList.add('chain-active');
    document.getElementById('btn-close-chain').style.display = 'inline-flex';

    linksRow.innerHTML = chain.links.map((link, idx) => {
      const atk = link.attack_card;
      const defCards = link.defend_cards || [];
      const atkPower = link.attack_power ?? link.base_power ?? 0;
      const totalDef = link.total_defense ?? 0;
      const diff = Math.max(0, atkPower - totalDef);
      const atkRarity = (atk?.rarity || 'c').toLowerCase();

      return `
        <div class="chain-link-item">
          <div class="link-tag">Link ${idx + 1}</div>
          <div class="attack-slot">
            <div class="rarity-card-wrap card-rarity-${atkRarity}">
              <img class="chain-card-thumb" src="${atk?.image_url || ''}" alt="${API.escapeHtml(atk?.name || 'Attack')}" onclick="Compendium.openCardDetail('${atk?.unique_id}')" />
            </div>
            <span style="font-size:0.75rem; font-weight:700; color:var(--color-attack); margin-top:4px;">${API.escapeHtml(atk?.name || 'Attack')}</span>
          </div>
          <div class="versus-dial">
            <span class="vs-power">⚔️ ${atkPower}</span>
            <span style="font-size:0.8rem; color:var(--text-dim);">VS</span>
            <span class="vs-defense">🛡️ ${totalDef}</span>
            <span class="vs-diff">${diff > 0 ? `Hits for ${diff} dmg` : 'Blocked'}</span>
          </div>
          <div class="defend-slot">
            ${defCards.length > 0 ? defCards.map(d => {
              const defRarity = (d.card?.rarity || 'c').toLowerCase();
              return `
                <div class="rarity-card-wrap card-rarity-${defRarity}">
                  <img class="chain-card-thumb" src="${d.card?.image_url || ''}" alt="${API.escapeHtml(d.card?.name || 'Defense')}" style="width:48px; height:68px;" onclick="Compendium.openCardDetail('${d.card?.unique_id}')" />
                </div>
              `;
            }).join('') : '<div style="font-size:0.75rem; color:var(--text-dim); padding:1rem;">No Defenders</div>'}
          </div>
        </div>
      `;
    }).join('');
  },

  renderLog() {
    const logBox = document.getElementById('arena-log-messages');
    if (!logBox || !this.gameState.log) return;

    logBox.innerHTML = this.gameState.log.map(item => `
      <div class="log-entry">
        <span class="log-time">${item.time || ''}</span>
        ${API.escapeHtml(item.text || '')}
      </div>
    `).join('');

    logBox.scrollTop = logBox.scrollHeight;
  },

  openCardActionMenuById(instanceId, zone, slot = '') {
    const myKey = String(this.myPlayerId);
    const pState = this.gameState.players[myKey];
    if (!pState) return;

    let card = null;
    if (zone === 'hand') {
      card = pState.hand?.find(c => c.instance_id === instanceId);
    } else if (zone === 'equipment') {
      card = pState.equipment ? pState.equipment[slot] : null;
    } else if (zone === 'arsenal') {
      card = pState.arsenal?.find(c => c.instance_id === instanceId);
    }

    if (card) {
      this.openCardActionMenu(card, zone, slot);
    }
  },

  openCardActionMenu(card, zone, slot) {
    const modal = document.getElementById('card-action-menu-modal');
    const title = document.getElementById('action-menu-card-title');
    const actionsBox = document.getElementById('action-menu-buttons-list');
    if (!modal || !title || !actionsBox) return;

    title.textContent = card.name;
    const instId = card.instance_id;

    const actionBtns = [];

    // Contextual actions
    if (zone === 'hand') {
      actionBtns.push(`<button class="btn btn-primary" onclick="GameEngine.executeAndCloseMenu('attack_card', { instance_id: '${instId}', from_zone: 'hand' })">⚔️ Play Attack</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('pitch_card', { instance_id: '${instId}' })">⚡ Pitch (+${card.pitch || 1} Resources)</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('defend_card', { instance_id: '${instId}', from_zone: 'hand' })">🛡️ Defend Link</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('arsenal_card', { instance_id: '${instId}' })">🎴 Place in Arsenal</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('move_card', { instance_id: '${instId}', from_zone: 'hand', to_zone: 'graveyard' })">⚰️ Discard to Graveyard</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('move_card', { instance_id: '${instId}', from_zone: 'hand', to_zone: 'banished' })">🌀 Banish Card</button>`);
    } else if (zone === 'equipment' || zone === 'hero') {
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('tap_card', { zone: '${zone}', slot: '${slot}', instance_id: '${instId}' })">🔄 Toggle Tap / Exhaust</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('modify_counter', { zone: '${zone}', slot: '${slot}', counter_type: 'power', delta: 1 })">➕ Add +1 Power Counter</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('modify_counter', { zone: '${zone}', slot: '${slot}', counter_type: 'power', delta: -1 })">➖ Remove Counter</button>`);
      if (zone === 'equipment') {
        actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('defend_card', { from_zone: 'equipment', equipment_slot: '${slot}' })">🛡️ Defend with Equipment</button>`);
      }
    } else if (zone === 'arsenal') {
      actionBtns.push(`<button class="btn btn-primary" onclick="GameEngine.executeAndCloseMenu('attack_card', { instance_id: '${instId}', from_zone: 'arsenal' })">⚔️ Play Attack from Arsenal</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('flip_card', { zone: 'arsenal', instance_id: '${instId}' })">👁️ Flip Face Up / Down</button>`);
      actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.executeAndCloseMenu('move_card', { instance_id: '${instId}', from_zone: 'arsenal', to_zone: 'hand' })">🖐️ Return to Hand</button>`);
    }

    actionBtns.push(`<button class="btn btn-secondary" onclick="GameEngine.inspectCard('${card.unique_id}')">🔍 Inspect Card Details</button>`);

    actionsBox.innerHTML = actionBtns.join('');
    modal.classList.add('active');
  },

  inspectCard(uniqueId) {
    document.getElementById('card-action-menu-modal')?.classList.remove('active');
    Compendium.openCardDetail(uniqueId);
  },

  executeAndCloseMenu(actionType, payload) {
    document.getElementById('card-action-menu-modal')?.classList.remove('active');
    this.executeAction(actionType, payload);
  },

  openZoneInspector(title, cardList) {
    const modal = document.getElementById('zone-inspector-modal');
    const titleEl = document.getElementById('zone-inspector-title');
    const grid = document.getElementById('zone-inspector-grid');
    if (!modal || !titleEl || !grid) return;

    titleEl.textContent = `${title} (${cardList.length} cards)`;

    if (cardList.length === 0) {
      grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:3rem; color:var(--text-dim);">Zone is currently empty.</div>';
    } else {
      grid.innerHTML = cardList.map(c => {
        const imgPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='168' viewBox='0 0 120 168'%3E%3Crect width='120' height='168' fill='%23121722'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23d4af37' font-family='sans-serif' font-size='10'%3E" + encodeURIComponent(c.name) + "%3C/text%3E%3C/svg%3E";

        return `
          <div class="fab-card-item" onclick="Compendium.openCardDetail('${c.unique_id}')">
            <div class="card-img-wrap" style="aspect-ratio:2.5/3.5;">
              <img class="card-img" src="${c.image_url || imgPlaceholder}" alt="${API.escapeHtml(c.name)}" onerror="this.onerror=null; this.src='${imgPlaceholder}';" />
            </div>
            <div class="card-meta-bar" style="padding:4px 6px;">
              <div class="card-name-label" style="font-size:0.75rem;">${API.escapeHtml(c.name)}</div>
            </div>
          </div>
        `;
      }).join('');
    }

    modal.classList.add('active');
  },

  startTimers() {
    if (this.turnTimerInterval) clearInterval(this.turnTimerInterval);
    if (this.matchTimerInterval) clearInterval(this.matchTimerInterval);

    this.turnTimerInterval = setInterval(() => {
      this.updateTimers();
    }, 1000);
  },

  updateTimers() {
    if (!this.gameState) return;

    const clockEl = document.getElementById('arena-turn-clock');
    const barEl = document.getElementById('arena-turn-progress-fill');
    const matchTimeEl = document.getElementById('arena-match-duration');

    const now = Math.floor(Date.now() / 1000);

    // 1. Match Duration Timer
    if (matchTimeEl && this.gameState.match_start_time) {
      const matchElapsed = Math.max(0, now - this.gameState.match_start_time);
      const mMins = String(Math.floor(matchElapsed / 60)).padStart(2, '0');
      const mSecs = String(matchElapsed % 60).padStart(2, '0');
      matchTimeEl.textContent = `⏱️ ${mMins}:${mSecs}`;
    }

    // 2. Turn Countdown Timer
    if (clockEl && barEl) {
      const duration = this.gameState.turn_duration_seconds || 75;
      const startTime = this.gameState.turn_start_time || now;
      const elapsed = Math.max(0, now - startTime);
      const remaining = Math.max(0, duration - elapsed);

      const rMins = String(Math.floor(remaining / 60)).padStart(2, '0');
      const rSecs = String(remaining % 60).padStart(2, '0');
      clockEl.textContent = `${rMins}:${rSecs}`;

      const pct = (remaining / duration) * 100;
      barEl.style.width = `${pct}%`;

      clockEl.className = 'turn-timer-clock';
      if (remaining <= 15) {
        clockEl.classList.add('danger');
      } else if (remaining <= 30) {
        clockEl.classList.add('warning');
      }
    }
  },

  async handleDiceRoll(type) {
    // 1. Show 3D dice animation overlay
    const overlay = document.getElementById('dice-anim-overlay');
    const cube = document.getElementById('dice-3d-cube-box');
    const label = document.getElementById('dice-result-anim-label');
    if (overlay && cube && label) {
      label.textContent = 'Rolling...';
      overlay.classList.add('active');
      cube.style.animation = 'none';
      cube.offsetHeight; // trigger reflow
      cube.style.animation = 'tumbleDice 1s cubic-bezier(0.2, 0.8, 0.2, 1)';
    }

    // 2. Execute action on server
    try {
      const res = await API.post('api/game.php?action=action', {
        room_id: this.currentRoomId,
        action_type: 'roll_dice',
        payload: { dice_type: type }
      });

      if (res.success && res.state) {
        this.gameState = res.state;
        this.renderBattlefield();

        // Extract last roll result from log
        const lastLog = res.state.log?.[res.state.log.length - 1];
        if (label && lastLog) {
          label.innerHTML = API.escapeHtml(lastLog.text);
        }

        setTimeout(() => {
          overlay?.classList.remove('active');
        }, 1200);
      }
    } catch (e) {
      overlay?.classList.remove('active');
      API.toast(e.message || 'Dice roll failed', 'error');
    }
  },

  showTurnSplash() {
    const banner = document.getElementById('turn-splash-banner');
    if (!banner) return;

    banner.textContent = '⚔️ YOUR TURN ⚔️';
    banner.classList.add('show');
    setTimeout(() => {
      banner.classList.remove('show');
    }, 1500);
  },

  triggerDamageFlash() {
    const flash = document.getElementById('damage-flash-overlay');
    if (!flash) return;

    flash.classList.add('flash');
    setTimeout(() => {
      flash.classList.remove('flash');
    }, 300);
  },

  showVictoryDefeatModal(winnerInfo) {
    if (this.hasShownResultModal) return;
    this.hasShownResultModal = true;

    const modal = document.getElementById('match-result-modal');
    const card = document.getElementById('match-result-card');
    const emblem = document.getElementById('result-emblem');
    const title = document.getElementById('result-title');
    const desc = document.getElementById('result-description');
    if (!modal || !card || !emblem || !title || !desc) return;

    const isWinner = winnerInfo ? winnerInfo.is_me : false;
    const winnerName = winnerInfo ? winnerInfo.name : 'Unknown';

    if (isWinner) {
      card.className = 'match-result-card victory';
      emblem.textContent = '🏆';
      title.className = 'result-title victory';
      title.textContent = 'VICTORY!';
      desc.textContent = `You have conquered the battlefield in Rathe! Outstanding victory, ${winnerName}!`;
    } else {
      card.className = 'match-result-card defeat';
      emblem.textContent = '💀';
      title.className = 'result-title defeat';
      title.textContent = 'DEFEATED';
      desc.textContent = `${winnerName} has claimed victory on the battlefield. Hone your deck and rise again!`;
    }

    // Populate Match Summary Statistics
    const turnsEl = document.getElementById('result-stat-turns');
    const formatEl = document.getElementById('result-stat-format');
    const durEl = document.getElementById('result-stat-duration');

    if (turnsEl) turnsEl.textContent = this.gameState?.turn || 1;
    if (formatEl) formatEl.textContent = this.gameState?.format || 'Blitz';
    if (durEl && this.gameState?.match_start_time) {
      const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - this.gameState.match_start_time);
      const mins = Math.floor(elapsed / 60);
      const secs = elapsed % 60;
      durEl.textContent = `${mins}m ${secs}s`;
    }

    modal.classList.add('active');
  },

  promptLeaveMatch() {
    if (!this.currentRoomId || this.gameState?.status === 'finished' || this.gameState?.winner_id) {
      this.exitToHome();
      return;
    }
    document.getElementById('leave-match-modal')?.classList.add('active');
  },

  stopAllSync() {
    if (this.pollInterval) clearInterval(this.pollInterval);
    if (this.chatPollInterval) clearInterval(this.chatPollInterval);
    if (this.turnTimerInterval) clearInterval(this.turnTimerInterval);
    if (this.matchTimerInterval) clearInterval(this.matchTimerInterval);
    this.pollInterval = null;
    this.chatPollInterval = null;
    this.turnTimerInterval = null;
    this.matchTimerInterval = null;
  },

  exitToHome() {
    this.stopAllSync();
    this.currentRoomId = null;
    this.gameState = null;
    this.hasShownResultModal = false;
    document.getElementById('match-result-modal')?.classList.remove('active');
    document.getElementById('leave-match-modal')?.classList.remove('active');
    document.getElementById('card-action-menu-modal')?.classList.remove('active');
    document.getElementById('zone-inspector-modal')?.classList.remove('active');
    App.navigateTo('menu');
  },

  playAgain() {
    this.exitToHome();
    Matchmaking.openQueueModal();
  },

  async confirmLeaveMatch() {
    document.getElementById('leave-match-modal')?.classList.remove('active');
    const roomId = this.currentRoomId;
    this.stopAllSync();
    this.currentRoomId = null;
    this.gameState = null;
    this.hasShownResultModal = false;

    if (roomId) {
      try {
        await API.post('api/game.php?action=action', {
          room_id: roomId,
          action_type: 'forfeit_match',
          payload: {}
        });
      } catch (e) {
        console.warn('Forfeit error', e);
      }
    }

    API.toast('You have left the match.', 'info');
    App.navigateTo('menu');
  }
};

window.GameEngine = GameEngine;


