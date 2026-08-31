/**
 * Matchmaking & Room Controller
 * Flesh and Blood TCG Sandbox
 */

const Matchmaking = {
  isSearching: false,
  queueTimerInterval: null,
  pollInterval: null,
  customRoomPollInterval: null,
  searchStartTime: 0,
  waitingRoomId: null,
  waitingRoomCode: '',

  init() {
    this.bindEvents();
  },

  bindEvents() {
    // Play Online trigger from Main Menu
    document.getElementById('btn-play-online')?.addEventListener('click', () => this.openQueueModal());

    // Start Searching Match button
    document.getElementById('btn-start-queue')?.addEventListener('click', () => this.startQueue());

    // Cancel Queue button
    document.getElementById('btn-cancel-queue')?.addEventListener('click', () => this.cancelQueue());

    // Modal Close
    document.getElementById('matchmaking-modal-close')?.addEventListener('click', () => {
      this.closeModal();
    });

    // Custom Room Buttons
    document.getElementById('btn-create-custom-room')?.addEventListener('click', () => this.createCustomRoom());
    document.getElementById('btn-join-custom-room')?.addEventListener('click', () => this.showJoinPane());

    // Copy Code button
    document.getElementById('btn-copy-room-code')?.addEventListener('click', () => this.copyRoomCode());

    // Cancel Custom Room Waiting button
    document.getElementById('btn-cancel-custom-room')?.addEventListener('click', () => this.cancelCustomRoom());

    // Join Room buttons
    document.getElementById('btn-back-join-room')?.addEventListener('click', () => this.showConfigPane());
    document.getElementById('btn-submit-join-room')?.addEventListener('click', () => this.submitJoinRoom());

    // Enter key support for join room code
    document.getElementById('input-join-room-code')?.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') this.submitJoinRoom();
    });
  },

  showPane(paneId) {
    ['queue-config-pane', 'queue-active-pane', 'queue-custom-waiting-pane', 'queue-custom-join-pane'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = (id === paneId) ? 'block' : 'none';
    });
  },

  showConfigPane() {
    this.showPane('queue-config-pane');
  },

  showJoinPane() {
    this.showPane('queue-custom-join-pane');
    const input = document.getElementById('input-join-room-code');
    if (input) {
      input.value = '';
      setTimeout(() => input.focus(), 100);
    }
  },

  closeModal() {
    if (this.isSearching) this.cancelQueue();
    if (this.waitingRoomId) this.cancelCustomRoom();
    this.showConfigPane();
    document.getElementById('matchmaking-modal')?.classList.remove('active');
  },

  async openQueueModal() {
    if (!API.currentUser) {
      API.toast('Please sign in or use Quick Play to join matchmaking.', 'info');
      App.openAuthModal();
      return;
    }

    // Populate deck selection for matchmaking
    const sel = document.getElementById('queue-deck-select');
    if (sel) {
      try {
        const res = await API.get('api/decks.php?action=list');
        if (res.success && res.decks && res.decks.length > 0) {
          sel.innerHTML = res.decks.map(d => `<option value="${d.id}">${API.escapeHtml(d.name)} (${d.format}) - ${d.main_count} cards</option>`).join('');
        } else {
          sel.innerHTML = '<option value="">No decks available - Create one in Deck Builder</option>';
        }
      } catch (e) {
        console.error('Failed to load decks for matchmaking', e);
      }
    }

    this.showConfigPane();
    document.getElementById('matchmaking-modal')?.classList.add('active');
  },

  async startQueue() {
    const deckId = parseInt(document.getElementById('queue-deck-select')?.value);
    const format = document.getElementById('queue-format-select')?.value || 'Blitz';

    if (!deckId) {
      API.toast('Please select a deck before searching for a battle.', 'error');
      return;
    }

    try {
      const res = await API.post('api/matchmaking.php?action=join', {
        deck_id: deckId,
        format: format
      });

      if (res.success) {
        this.isSearching = true;
        this.searchStartTime = Date.now();

        // Switch to radar view
        this.showPane('queue-active-pane');
        document.getElementById('queue-format-display').textContent = `Format: ${format}`;

        // Start timer animation
        this.startTimer();

        // If instantly matched (e.g. 2nd player)
        if (res.status === 'matched' && res.room_id) {
          this.handleMatchFound(res.room_id, res.room_code);
          return;
        }

        // Start continuous polling (every 1.5 seconds)
        this.startPolling();
        App.fetchKpis();
      }
    } catch (e) {
      API.toast(e.message || 'Failed to enter matchmaking queue', 'error');
    }
  },

  startTimer() {
    if (this.queueTimerInterval) clearInterval(this.queueTimerInterval);
    const timerEl = document.getElementById('queue-timer-text');

    this.queueTimerInterval = setInterval(() => {
      const elapsed = Math.floor((Date.now() - this.searchStartTime) / 1000);
      const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
      const secs = String(elapsed % 60).padStart(2, '0');
      if (timerEl) timerEl.textContent = `${mins}:${secs}`;
    }, 1000);
  },

  startPolling() {
    if (this.pollInterval) clearInterval(this.pollInterval);

    this.pollInterval = setInterval(async () => {
      if (!this.isSearching) return;

      try {
        const res = await API.get('api/matchmaking.php?action=status');
        if (res.success) {
          if (res.status === 'matched' && res.room_id) {
            this.handleMatchFound(res.room_id, res.room_code);
          }
        }
      } catch (e) {
        console.warn('Queue poll error', e);
      }
    }, 1500);
  },

  handleMatchFound(roomId, roomCode) {
    this.stopQueue();
    document.getElementById('matchmaking-modal')?.classList.remove('active');
    API.toast('Opponent found! Entering the Arena...', 'success');

    // Launch game view
    App.navigateTo('game', { room_id: roomId, room_code: roomCode });
  },

  async cancelQueue() {
    try {
      await API.post('api/matchmaking.php?action=leave');
    } catch (e) {
      console.warn('Cancel queue error', e);
    }
    this.stopQueue();
    this.showConfigPane();
    App.fetchKpis();
  },

  stopQueue() {
    this.isSearching = false;
    if (this.queueTimerInterval) clearInterval(this.queueTimerInterval);
    if (this.pollInterval) clearInterval(this.pollInterval);
    this.queueTimerInterval = null;
    this.pollInterval = null;
  },

  /**
   * Create Private Room & Open Waiting Lobby
   */
  async createCustomRoom() {
    const deckSelect = document.getElementById('queue-deck-select');
    const deckId = parseInt(deckSelect?.value);
    const deckName = deckSelect?.options[deckSelect.selectedIndex]?.text || 'Selected Deck';
    const format = document.getElementById('queue-format-select')?.value || 'Blitz';

    if (!deckId) {
      API.toast('Please select a deck.', 'error');
      return;
    }

    try {
      const res = await API.post('api/matchmaking.php?action=create_room', {
        deck_id: deckId,
        format: format
      });

      if (res.success && res.room_code) {
        this.waitingRoomId = res.room_id;
        this.waitingRoomCode = res.room_code;

        document.getElementById('waiting-room-code-val').textContent = res.room_code;
        document.getElementById('waiting-room-details').textContent = `Format: ${format} • Deck: ${deckName}`;

        this.showPane('queue-custom-waiting-pane');
        this.startCustomRoomPolling();
      }
    } catch (e) {
      API.toast(e.message || 'Could not create room', 'error');
    }
  },

  startCustomRoomPolling() {
    if (this.customRoomPollInterval) clearInterval(this.customRoomPollInterval);

    this.customRoomPollInterval = setInterval(async () => {
      if (!this.waitingRoomId) return;

      try {
        const res = await API.get(`api/matchmaking.php?action=room_status&room_id=${this.waitingRoomId}`);
        if (res.success) {
          if (res.status === 'active' || res.has_opponent) {
            const roomId = this.waitingRoomId;
            const roomCode = this.waitingRoomCode;
            this.stopCustomRoomPolling();

            document.getElementById('matchmaking-modal')?.classList.remove('active');
            API.toast('Opponent connected! Entering the Arena...', 'success');
            App.navigateTo('game', { room_id: roomId, room_code: roomCode });
          }
        }
      } catch (e) {
        console.warn('Custom room poll error', e);
      }
    }, 1200);
  },

  stopCustomRoomPolling() {
    if (this.customRoomPollInterval) clearInterval(this.customRoomPollInterval);
    this.customRoomPollInterval = null;
    this.waitingRoomId = null;
    this.waitingRoomCode = '';
  },

  async cancelCustomRoom() {
    if (this.waitingRoomId) {
      try {
        await API.post('api/matchmaking.php?action=cancel_room', {
          room_id: this.waitingRoomId
        });
      } catch (e) {
        console.warn('Cancel custom room error', e);
      }
    }
    this.stopCustomRoomPolling();
    this.showConfigPane();
  },

  copyRoomCode() {
    if (!this.waitingRoomCode) return;
    navigator.clipboard.writeText(this.waitingRoomCode).then(() => {
      API.toast('Room code copied to clipboard! Share it with your opponent.', 'success');
    }).catch(() => {
      ModalDialog.alert(`Your Battle Room Code is:<br><strong style="font-size:1.5rem; color:var(--text-gold); font-family:var(--font-mono);">${this.waitingRoomCode}</strong>`, 'Room Code', '📋');
    });
  },

  async submitJoinRoom() {
    const deckId = parseInt(document.getElementById('queue-deck-select')?.value);
    const codeInput = document.getElementById('input-join-room-code');
    const code = codeInput?.value?.trim().toUpperCase();

    if (!code) {
      API.toast('Please enter the 6-character room code.', 'error');
      codeInput?.focus();
      return;
    }

    if (!deckId) {
      API.toast('Please select a deck before joining.', 'error');
      return;
    }

    try {
      const res = await API.post('api/matchmaking.php?action=join_room', {
        deck_id: deckId,
        room_code: code
      });

      if (res.success && res.room) {
        this.stopCustomRoomPolling();
        document.getElementById('matchmaking-modal')?.classList.remove('active');
        this.showConfigPane();
        API.toast('Joined custom room successfully! Entering Arena...', 'success');
        App.navigateTo('game', { room_id: res.room.id, room_code: res.room.room_code });
      }
    } catch (e) {
      API.toast(e.message || 'Could not join room. Check the code and try again.', 'error');
    }
  }
};

window.Matchmaking = Matchmaking;

