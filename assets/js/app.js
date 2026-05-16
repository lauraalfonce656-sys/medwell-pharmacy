/**
 * ============================================================
 * MedWell Pharmacy - Main Application JavaScript
 * ============================================================
 */

'use strict';

const MedWell = (function () {

  /* ----------------------------------------------------------
     Configuration
     ---------------------------------------------------------- */
  const config = {
    apiBaseUrl: '/api',
    csrfToken: null,
    currency: '₱',
    dateFormat: 'MM/DD/YYYY',
    toastDuration: 4000,
    debounceDelay: 300,
  };

  /* ----------------------------------------------------------
     State
     ---------------------------------------------------------- */
  const state = {
    theme: 'light',
    sidebarCollapsed: false,
    loadingCount: 0,
  };

  /* ----------------------------------------------------------
     DOMContentLoaded Initialization
     ---------------------------------------------------------- */
  function init() {
    loadTheme();
    initSidebar();
    initDropdowns();
    initSearch();
    initKeyboardShortcuts();
    initResponsiveSidebar();
    initTooltips();
    initForms();
    retrieveCsrfToken();
  }

  document.addEventListener('DOMContentLoaded', init);

  /* ----------------------------------------------------------
     Theme Toggle (Dark/Light Mode)
     ---------------------------------------------------------- */
  function loadTheme() {
    const saved = localStorage.getItem('medwell-theme');
    if (saved) {
      state.theme = saved;
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      state.theme = 'dark';
    }
    applyTheme(state.theme);
  }

  function applyTheme(theme) {
    state.theme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('medwell-theme', theme);

    const toggle = document.querySelector('.dark-mode-toggle');
    if (toggle) {
      toggle.classList.toggle('active', theme === 'dark');
    }
  }

  function toggleTheme() {
    applyTheme(state.theme === 'dark' ? 'light' : 'dark');
  }

  /* ----------------------------------------------------------
     Sidebar Toggle (Expand/Collapse)
     ---------------------------------------------------------- */
  function initSidebar() {
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', toggleSidebar);
    }

    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    if (mobileMenuBtn) {
      mobileMenuBtn.addEventListener('click', toggleMobileSidebar);
    }

    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
      overlay.addEventListener('click', closeMobileSidebar);
    }

    // Set active nav link based on current URL
    highlightActiveNav();
  }

  function toggleSidebar() {
    state.sidebarCollapsed = !state.sidebarCollapsed;
    document.body.classList.toggle('sidebar-collapsed', state.sidebarCollapsed);
    localStorage.setItem('medwell-sidebar-collapsed', state.sidebarCollapsed);
  }

  function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) sidebar.classList.toggle('mobile-open');
    if (overlay) overlay.classList.toggle('active');
  }

  function closeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) sidebar.classList.remove('mobile-open');
    if (overlay) overlay.classList.remove('active');
  }

  function highlightActiveNav() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && currentPath.startsWith(href) && href !== '/') {
        link.classList.add('active');
      }
    });
  }

  /* ----------------------------------------------------------
     Toast Notification System
     ---------------------------------------------------------- */
  function showToast(message, type = 'info', title = '') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const icons = {
      success: 'fas fa-check-circle',
      danger: 'fas fa-exclamation-circle',
      warning: 'fas fa-exclamation-triangle',
      info: 'fas fa-info-circle',
    };

    const titles = {
      success: title || 'Success',
      danger: title || 'Error',
      warning: title || 'Warning',
      info: title || 'Info',
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <i class="toast-icon ${icons[type] || icons.info}"></i>
      <div class="toast-content">
        <div class="toast-title">${titles[type]}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" aria-label="Close">&times;</button>
    `;

    container.appendChild(toast);

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => removeToast(toast));

    setTimeout(() => removeToast(toast), config.toastDuration);
  }

  function removeToast(toast) {
    if (!toast || toast.classList.contains('toast-exit')) return;
    toast.classList.add('toast-exit');
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 300);
  }

  /* ----------------------------------------------------------
     Form Validation Helpers
     ---------------------------------------------------------- */
  function initForms() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
      form.addEventListener('submit', handleFormValidation);
    });

    // Real-time validation on input
    document.querySelectorAll('.form-control[data-rules]').forEach(input => {
      input.addEventListener('blur', () => validateField(input));
      input.addEventListener('input', debounce(() => validateField(input), config.debounceDelay));
    });
  }

  function handleFormValidation(e) {
    const form = e.target;
    const fields = form.querySelectorAll('.form-control[data-rules]');
    let isValid = true;

    fields.forEach(field => {
      if (!validateField(field)) {
        isValid = false;
      }
    });

    if (!isValid) {
      e.preventDefault();
      e.stopPropagation();
      showToast('Please fix the errors in the form.', 'danger');
    }
  }

  function validateField(field) {
    const rules = (field.dataset.rules || '').split('|');
    const value = field.value.trim();
    let error = '';

    for (const rule of rules) {
      const [ruleName, ruleValue] = rule.split(':');

      switch (ruleName) {
        case 'required':
          if (!value) error = 'This field is required.';
          break;
        case 'min':
          if (value.length < parseInt(ruleValue)) error = `Minimum ${ruleValue} characters required.`;
          break;
        case 'max':
          if (value.length > parseInt(ruleValue)) error = `Maximum ${ruleValue} characters allowed.`;
          break;
        case 'email':
          if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) error = 'Invalid email address.';
          break;
        case 'numeric':
          if (value && !/^\d+(\.\d+)?$/.test(value)) error = 'Must be a number.';
          break;
        case 'phone':
          if (value && !/^[\d\s\-+()]{7,15}$/.test(value)) error = 'Invalid phone number.';
          break;
      }

      if (error) break;
    }

    setFieldValidation(field, error);
    return !error;
  }

  function setFieldValidation(field, error) {
    field.classList.remove('is-valid', 'is-invalid');
    const feedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');

    if (error) {
      field.classList.add('is-invalid');
      if (feedback) {
        feedback.className = 'invalid-feedback';
        feedback.textContent = error;
      }
    } else if (field.value.trim()) {
      field.classList.add('is-valid');
      if (feedback) {
        feedback.className = 'valid-feedback';
        feedback.textContent = '';
      }
    }
  }

  /* ----------------------------------------------------------
     AJAX Wrapper Function
     ---------------------------------------------------------- */
  function medwellAjax(url, data = {}, callback = null, method = 'POST') {
    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    };

    const csrfToken = getCsrfToken();
    if (csrfToken && method !== 'GET') {
      headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const options = {
      method: method,
      headers: headers,
      credentials: 'same-origin',
    };

    if (method === 'GET') {
      const params = new URLSearchParams(data);
      if (params.toString()) url += '?' + params.toString();
    } else {
      if (data instanceof FormData) {
        options.body = data;
      } else {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
      }
    }

    return fetch(config.apiBaseUrl + url, options)
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => {
            throw err;
          }).catch(() => {
            throw { message: `Request failed with status ${response.status}` };
          });
        }
        return response.json();
      })
      .then(result => {
        if (callback) callback(null, result);
        return result;
      })
      .catch(error => {
        const msg = error.message || 'An unexpected error occurred.';
        showToast(msg, 'danger');
        if (callback) callback(error, null);
        throw error;
      });
  }

  /* ----------------------------------------------------------
     CSRF Token Management
     ---------------------------------------------------------- */
  function retrieveCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
      config.csrfToken = meta.getAttribute('content');
    }
  }

  function getCsrfToken() {
    if (!config.csrfToken) retrieveCsrfToken();
    return config.csrfToken;
  }

  /* ----------------------------------------------------------
     DataTable Initialization Helper
     ---------------------------------------------------------- */
  function initDataTable(selector, options = {}) {
    if (typeof $.fn.DataTable === 'undefined') {
      console.warn('DataTables library not loaded.');
      return null;
    }

    const defaults = {
      responsive: true,
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
      language: {
        search: '',
        searchPlaceholder: 'Search records...',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_',
        paginate: {
          previous: '<i class="fas fa-chevron-left"></i>',
          next: '<i class="fas fa-chevron-right"></i>',
        },
      },
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
      drawCallback: function () {
        // Re-init tooltips after table redraw
        initTooltips();
      },
    };

    const settings = deepMerge(defaults, options);
    return $(selector).DataTable(settings);
  }

  /* ----------------------------------------------------------
     Chart.js Initialization Helpers
     ---------------------------------------------------------- */
  function initChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
      console.warn(`Canvas element #${canvasId} not found.`);
      return null;
    }

    if (typeof Chart === 'undefined') {
      console.warn('Chart.js library not loaded.');
      return null;
    }

    const ctx = canvas.getContext('2d');
    const defaultOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            font: { family: "'Inter', sans-serif", size: 12 },
            color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#636e72',
          },
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleFont: { family: "'Inter', sans-serif", size: 13 },
          bodyFont: { family: "'Inter', sans-serif", size: 12 },
          padding: 12,
          cornerRadius: 8,
        },
      },
      scales: {
        x: {
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { font: { family: "'Inter', sans-serif", size: 11 } },
        },
        y: {
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { font: { family: "'Inter', sans-serif", size: 11 } },
        },
      },
    };

    if (type === 'doughnut' || type === 'pie') {
      delete defaultOptions.scales;
    }

    const chartOptions = deepMerge(defaultOptions, options);
    return new Chart(ctx, { type, data, options: chartOptions });
  }

  /* ----------------------------------------------------------
     Number Formatting (Currency)
     ---------------------------------------------------------- */
  function formatCurrency(amount, decimals = 2) {
    const num = parseFloat(amount);
    if (isNaN(num)) return config.currency + '0.00';
    return config.currency + num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function formatNumber(num, decimals = 0) {
    const n = parseFloat(num);
    if (isNaN(n)) return '0';
    return n.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  /* ----------------------------------------------------------
     Date Formatting
     ---------------------------------------------------------- */
  function formatDate(date, format = 'MM/DD/YYYY') {
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d.getTime())) return '';

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const map = {
      'YYYY': d.getFullYear(),
      'YY': String(d.getFullYear()).slice(-2),
      'MMMM': fullMonths[d.getMonth()],
      'MMM': months[d.getMonth()],
      'MM': String(d.getMonth() + 1).padStart(2, '0'),
      'M': d.getMonth() + 1,
      'DD': String(d.getDate()).padStart(2, '0'),
      'D': d.getDate(),
      'HH': String(d.getHours()).padStart(2, '0'),
      'H': d.getHours(),
      'mm': String(d.getMinutes()).padStart(2, '0'),
      'm': d.getMinutes(),
      'ss': String(d.getSeconds()).padStart(2, '0'),
      's': d.getSeconds(),
    };

    let result = format;
    for (const [key, value] of Object.entries(map)) {
      result = result.replace(key, value);
    }
    return result;
  }

  function timeAgo(date) {
    const d = date instanceof Date ? date : new Date(date);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return formatDate(d, 'MMM DD, YYYY');
  }

  /* ----------------------------------------------------------
     Search Functionality
     ---------------------------------------------------------- */
  function initSearch() {
    const searchInput = document.querySelector('.search-bar input');
    if (!searchInput) return;

    const searchHandler = debounce((e) => {
      const query = e.target.value.trim();
      if (query.length < 2) return;

      // Dispatch custom event for pages to listen to
      const event = new CustomEvent('medwell:search', { detail: { query } });
      document.dispatchEvent(event);
    }, config.debounceDelay);

    searchInput.addEventListener('input', searchHandler);
  }

  /* ----------------------------------------------------------
     Dropdown Menus
     ---------------------------------------------------------- */
  function initDropdowns() {
    // User dropdown
    const userToggle = document.querySelector('.user-dropdown-toggle');
    const userMenu = document.querySelector('.user-dropdown-menu');
    if (userToggle && userMenu) {
      userToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('active');
        closeOtherDropdowns(userMenu);
      });
    }

    // Notification dropdown
    const notifBtn = document.querySelector('.header-icon-btn[data-dropdown="notifications"]');
    const notifDropdown = document.querySelector('.notification-dropdown');
    if (notifBtn && notifDropdown) {
      notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('active');
        closeOtherDropdowns(notifDropdown);
      });
    }

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
      closeAllDropdowns();
    });
  }

  function closeOtherDropdowns(except) {
    document.querySelectorAll('.user-dropdown-menu.active, .notification-dropdown.active').forEach(el => {
      if (el !== except) el.classList.remove('active');
    });
  }

  function closeAllDropdowns() {
    document.querySelectorAll('.user-dropdown-menu.active, .notification-dropdown.active').forEach(el => {
      el.classList.remove('active');
    });
  }

  /* ----------------------------------------------------------
     Modal Helpers
     ---------------------------------------------------------- */
  function openModal(modalId) {
    const overlay = document.getElementById(modalId) || document.querySelector('.modal-overlay');
    if (overlay) {
      overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }

  function closeModal(modalId) {
    const overlay = document.getElementById(modalId) || document.querySelector('.modal-overlay.active');
    if (overlay) {
      overlay.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  function initModals() {
    // Close buttons
    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modal = btn.closest('.modal-overlay');
        if (modal) {
          modal.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });

    // Close on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });
  }

  /* ----------------------------------------------------------
     Loading State Management
     ---------------------------------------------------------- */
  function showLoading(message = 'Loading...') {
    state.loadingCount++;
    let overlay = document.querySelector('.spinner-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'spinner-overlay';
      overlay.innerHTML = `<div class="spinner"></div><div class="spinner-text">${message}</div>`;
      document.body.appendChild(overlay);
    } else {
      overlay.querySelector('.spinner-text').textContent = message;
      overlay.style.display = 'flex';
      overlay.style.opacity = '1';
    }
  }

  function hideLoading() {
    state.loadingCount = Math.max(0, state.loadingCount - 1);
    if (state.loadingCount === 0) {
      const overlay = document.querySelector('.spinner-overlay');
      if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => {
          overlay.style.display = 'none';
        }, 300);
      }
    }
  }

  /* ----------------------------------------------------------
     Responsive Sidebar Auto-Collapse
     ---------------------------------------------------------- */
  function initResponsiveSidebar() {
    const mql = window.matchMedia('(max-width: 1024px)');
    function handleResize(e) {
      if (e.matches) {
        closeMobileSidebar();
      }
    }
    mql.addEventListener('change', handleResize);
    handleResize(mql);

    // Restore collapsed state from localStorage
    const saved = localStorage.getItem('medwell-sidebar-collapsed');
    if (saved === 'true' && !window.matchMedia('(max-width: 1024px)').matches) {
      state.sidebarCollapsed = true;
      document.body.classList.add('sidebar-collapsed');
    }
  }

  /* ----------------------------------------------------------
     Keyboard Shortcuts
     ---------------------------------------------------------- */
  function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Ctrl+K / Cmd+K: Focus search
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) searchInput.focus();
      }

      // Esc: Close modals, dropdowns
      if (e.key === 'Escape') {
        closeModal();
        closeAllDropdowns();
        closeMobileSidebar();
      }

      // Ctrl+/: Toggle sidebar (custom shortcut)
      if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        toggleSidebar();
      }
    });
  }

  /* ----------------------------------------------------------
     Tooltips Init
     ---------------------------------------------------------- */
  function initTooltips() {
    if (typeof $.fn.tooltip !== 'undefined') {
      $('[data-toggle="tooltip"]').tooltip();
    }
  }

  /* ----------------------------------------------------------
     Print Receipt Function
     ---------------------------------------------------------- */
  function printReceipt(elementId) {
    const content = document.getElementById(elementId);
    if (!content) {
      showToast('Receipt content not found.', 'danger');
      return;
    }

    const printWindow = window.open('', '_blank', 'width=400,height=600');
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Receipt</title>
        <style>
          body { font-family: 'Courier New', monospace; font-size: 12pt; margin: 10px; }
          .receipt { max-width: 300px; margin: 0 auto; }
          .receipt-header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
          .receipt-line { display: flex; justify-content: space-between; padding: 2px 0; }
          .receipt-divider { border-top: 1px dashed #000; margin: 6px 0; }
          .receipt-total { font-weight: bold; font-size: 14pt; }
        </style>
      </head>
      <body>
        <div class="receipt">${content.innerHTML}</div>
      </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  }

  /* ----------------------------------------------------------
     Utility: Debounce
     ---------------------------------------------------------- */
  function debounce(func, wait = config.debounceDelay) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /* ----------------------------------------------------------
     Utility: Throttle
     ---------------------------------------------------------- */
  function throttle(func, limit = 200) {
    let inThrottle;
    return function executedFunction(...args) {
      if (!inThrottle) {
        func(...args);
        inThrottle = true;
        setTimeout(() => { inThrottle = false; }, limit);
      }
    };
  }

  /* ----------------------------------------------------------
     Utility: Deep Merge
     ---------------------------------------------------------- */
  function deepMerge(target, source) {
    const result = { ...target };
    for (const key of Object.keys(source)) {
      if (source[key] instanceof Object && key in target && target[key] instanceof Object) {
        result[key] = deepMerge(target[key], source[key]);
      } else {
        result[key] = source[key];
      }
    }
    return result;
  }

  /* ----------------------------------------------------------
     Utility: Generate Unique ID
     ---------------------------------------------------------- */
  function generateId(prefix = 'mw') {
    return prefix + '_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
  }

  /* ----------------------------------------------------------
     Utility: Sanitize HTML
     ---------------------------------------------------------- */
  function sanitizeHTML(str) {
    const temp = document.createElement('div');
    temp.textContent = str;
    return temp.innerHTML;
  }

  /* ----------------------------------------------------------
     Public API
     ---------------------------------------------------------- */
  return {
    config,
    state,
    // Theme
    toggleTheme,
    applyTheme,
    // Sidebar
    toggleSidebar,
    closeMobileSidebar,
    // Toast
    showToast,
    // Forms
    validateField,
    // AJAX
    ajax: medwellAjax,
    // CSRF
    getCsrfToken,
    // DataTable
    initDataTable,
    // Chart
    initChart,
    // Formatting
    formatCurrency,
    formatNumber,
    formatDate,
    timeAgo,
    // Modal
    openModal,
    closeModal,
    initModals,
    // Loading
    showLoading,
    hideLoading,
    // Print
    printReceipt,
    // Utilities
    debounce,
    throttle,
    deepMerge,
    generateId,
    sanitizeHTML,
  };

})();
