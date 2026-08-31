/**
 * API Client & Network Utilities
 * Flesh and Blood TCG Sandbox
 */

const API = {
  csrfToken: '',
  currentUser: null,

  /**
   * Initialize session & CSRF token
   */
  async initSession() {
    try {
      const res = await this.get('api/auth.php?action=session');
      if (res.success) {
        this.csrfToken = res.csrf_token || '';
        this.currentUser = res.user || null;
      }
      return res;
    } catch (e) {
      console.error('Session init failed', e);
      return { success: false };
    }
  },

  /**
   * Universal GET request
   */
  async get(url) {
    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': this.csrfToken
        }
      });
      const data = await response.json();
      if (!response.ok && !data.success) {
        throw new Error(data.error || `HTTP ${response.status}`);
      }
      return data;
    } catch (err) {
      console.error(`GET ${url} Error:`, err);
      throw err;
    }
  },

  /**
   * Universal POST request
   */
  async post(url, bodyData = {}) {
    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': this.csrfToken
        },
        body: JSON.stringify(bodyData)
      });
      const data = await response.json();
      if (data.csrf_token) {
        this.csrfToken = data.csrf_token;
      }
      if (!response.ok && !data.success) {
        throw new Error(data.error || `HTTP ${response.status}`);
      }
      return data;
    } catch (err) {
      console.error(`POST ${url} Error:`, err);
      throw err;
    }
  },

  /**
   * Show toast notification
   */
  toast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '⚔️';
    if (type === 'error') icon = '⚠️';
    if (type === 'success') icon = '🛡️';

    toast.innerHTML = `<span>${icon}</span> <span>${API.escapeHtml(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      toast.style.transition = '0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  },

  /**
   * XSS Escaping for client strings
   */
  escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  /**
   * Replace {r}, {p}, {d}, {h}, {i} with glyph badges
   */
  formatRulesGlyphs(text) {
    if (!text) return '';
    let formatted = this.escapeHtml(text);
    formatted = formatted.replace(/\{r\}/gi, '<span class="glyph glyph-resource">R</span>');
    formatted = formatted.replace(/\{p\}/gi, '<span class="glyph glyph-power">P</span>');
    formatted = formatted.replace(/\{d\}/gi, '<span class="glyph glyph-defense">D</span>');
    formatted = formatted.replace(/\{h\}/gi, '<span class="glyph glyph-health">H</span>');
    formatted = formatted.replace(/\{i\}/gi, '<span class="glyph glyph-intel">I</span>');
    return formatted;
  }
};

/**
 * Universal Thematic Modal Dialog System
 */
const ModalDialog = {
  activeResolver: null,

  init() {
    document.getElementById('dialog-btn-confirm')?.addEventListener('click', () => {
      const input = document.getElementById('dialog-input-val');
      const val = (input && input.style.display !== 'none') ? input.value : true;
      this.close(val);
    });

    document.getElementById('dialog-btn-cancel')?.addEventListener('click', () => {
      this.close(false);
    });

    document.getElementById('dialog-btn-ok')?.addEventListener('click', () => {
      this.close(true);
    });

    document.getElementById('dialog-modal-close')?.addEventListener('click', () => {
      this.close(false);
    });

    document.getElementById('dialog-input-val')?.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        const input = document.getElementById('dialog-input-val');
        this.close(input.value);
      }
    });
  },

  alert(message, title = 'Notice', icon = '🛡️') {
    return new Promise(resolve => {
      this.activeResolver = resolve;
      const modal = document.getElementById('app-dialog-modal');
      const titleEl = document.getElementById('dialog-title');
      const msgEl = document.getElementById('dialog-message');
      const iconEl = document.getElementById('dialog-icon');
      const inputEl = document.getElementById('dialog-input-val');
      const okBtn = document.getElementById('dialog-btn-ok');
      const confirmBtn = document.getElementById('dialog-btn-confirm');
      const cancelBtn = document.getElementById('dialog-btn-cancel');

      if (titleEl) titleEl.textContent = title;
      if (iconEl) iconEl.textContent = icon;
      if (msgEl) msgEl.innerHTML = API.escapeHtml(message).replace(/\n/g, '<br>');
      if (inputEl) inputEl.style.display = 'none';

      if (okBtn) okBtn.style.display = 'inline-flex';
      if (confirmBtn) confirmBtn.style.display = 'none';
      if (cancelBtn) cancelBtn.style.display = 'none';

      modal?.classList.add('active');
    });
  },

  confirm(message, title = 'Confirmation', icon = '⚔️', confirmLabel = 'Confirm', cancelLabel = 'Cancel') {
    return new Promise(resolve => {
      this.activeResolver = resolve;
      const modal = document.getElementById('app-dialog-modal');
      const titleEl = document.getElementById('dialog-title');
      const msgEl = document.getElementById('dialog-message');
      const iconEl = document.getElementById('dialog-icon');
      const inputEl = document.getElementById('dialog-input-val');
      const okBtn = document.getElementById('dialog-btn-ok');
      const confirmBtn = document.getElementById('dialog-btn-confirm');
      const cancelBtn = document.getElementById('dialog-btn-cancel');

      if (titleEl) titleEl.textContent = title;
      if (iconEl) iconEl.textContent = icon;
      if (msgEl) msgEl.innerHTML = API.escapeHtml(message).replace(/\n/g, '<br>');
      if (inputEl) inputEl.style.display = 'none';

      if (okBtn) okBtn.style.display = 'none';
      if (confirmBtn) {
        confirmBtn.style.display = 'inline-flex';
        confirmBtn.textContent = confirmLabel;
      }
      if (cancelBtn) {
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = cancelLabel;
      }

      modal?.classList.add('active');
    });
  },

  prompt(message, defaultValue = '', title = 'Input Required', icon = '✏️', confirmLabel = 'Submit') {
    return new Promise(resolve => {
      this.activeResolver = resolve;
      const modal = document.getElementById('app-dialog-modal');
      const titleEl = document.getElementById('dialog-title');
      const msgEl = document.getElementById('dialog-message');
      const iconEl = document.getElementById('dialog-icon');
      const inputEl = document.getElementById('dialog-input-val');
      const okBtn = document.getElementById('dialog-btn-ok');
      const confirmBtn = document.getElementById('dialog-btn-confirm');
      const cancelBtn = document.getElementById('dialog-btn-cancel');

      if (titleEl) titleEl.textContent = title;
      if (iconEl) iconEl.textContent = icon;
      if (msgEl) msgEl.innerHTML = API.escapeHtml(message).replace(/\n/g, '<br>');
      
      if (inputEl) {
        inputEl.style.display = 'block';
        inputEl.value = defaultValue;
        setTimeout(() => inputEl.focus(), 100);
      }

      if (okBtn) okBtn.style.display = 'none';
      if (confirmBtn) {
        confirmBtn.style.display = 'inline-flex';
        confirmBtn.textContent = confirmLabel;
      }
      if (cancelBtn) {
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = 'Cancel';
      }

      modal?.classList.add('active');
    });
  },

  close(result) {
    const modal = document.getElementById('app-dialog-modal');
    modal?.classList.remove('active');
    if (this.activeResolver) {
      const resolve = this.activeResolver;
      this.activeResolver = null;
      resolve(result);
    }
  }
};

window.API = API;
window.ModalDialog = ModalDialog;
