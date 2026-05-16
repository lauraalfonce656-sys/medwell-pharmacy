/**
 * ============================================================
 * MedWell Pharmacy - POS (Point of Sale) JavaScript
 * ============================================================
 */

'use strict';

const MedWellPOS = (function () {

  /* ----------------------------------------------------------
     Configuration
     ---------------------------------------------------------- */
  const config = {
    taxRate: 0.12,             // 12% VAT
    discountMaxPercent: 100,
    discountMaxFixed: 50000,
    maxCartItems: 50,
    barcodePrefix: '*',
    apiEndpoints: {
      search: '/pos/search',
      completeSale: '/pos/sale',
      holdCart: '/pos/hold',
      recallCart: '/pos/recall',
      productDetails: '/pos/product/',
    },
  };

  /* ----------------------------------------------------------
     Cart State
     ---------------------------------------------------------- */
  let cart = {
    items: [],
    discount: { type: 'none', value: 0 },   // type: 'none' | 'percent' | 'fixed'
    paymentMethod: 'cash',
    customerId: null,
    customerName: 'Walk-in Customer',
    heldCarts: [],
  };

  /* ----------------------------------------------------------
     Audio Feedback (Sound Effects)
     ---------------------------------------------------------- */
  const AudioCtx = window.AudioContext || window.webkitAudioContext;
  let audioCtx = null;

  function getAudioCtx() {
    if (!audioCtx && AudioCtx) {
      audioCtx = new AudioCtx();
    }
    return audioCtx;
  }

  function playBeep(frequency = 880, duration = 100, volume = 0.15) {
    try {
      const ctx = getAudioCtx();
      if (!ctx) return;
      const oscillator = ctx.createOscillator();
      const gainNode = ctx.createGain();
      oscillator.connect(gainNode);
      gainNode.connect(ctx.destination);
      oscillator.frequency.value = frequency;
      oscillator.type = 'sine';
      gainNode.gain.value = volume;
      oscillator.start();
      setTimeout(() => {
        oscillator.stop();
      }, duration);
    } catch (e) {
      // Silently fail if audio not available
    }
  }

  function playScanSound() {
    playBeep(1200, 80, 0.12);
    setTimeout(() => playBeep(1600, 60, 0.1), 100);
  }

  function playAddSound() {
    playBeep(800, 60, 0.1);
  }

  function playErrorSound() {
    playBeep(300, 200, 0.12);
  }

  function playSuccessSound() {
    playBeep(700, 80, 0.1);
    setTimeout(() => playBeep(1000, 80, 0.1), 120);
    setTimeout(() => playBeep(1400, 120, 0.12), 240);
  }

  /* ----------------------------------------------------------
     Cart Management
     ---------------------------------------------------------- */

  /**
   * Add item to cart
   */
  function addItem(product) {
    if (cart.items.length >= config.maxCartItems) {
      MedWell.showToast('Cart is full. Maximum ' + config.maxCartItems + ' items.', 'warning');
      return false;
    }

    const existingIndex = cart.items.findIndex(item => item.id === product.id);

    if (existingIndex !== -1) {
      // Increment quantity if item already in cart
      const item = cart.items[existingIndex];
      if (item.qty >= product.stock) {
        MedWell.showToast('Insufficient stock for ' + product.name, 'warning');
        playErrorSound();
        return false;
      }
      item.qty++;
      item.total = item.qty * item.price;
    } else {
      // Add new item
      if (product.stock <= 0) {
        MedWell.showToast(product.name + ' is out of stock.', 'danger');
        playErrorSound();
        return false;
      }
      cart.items.push({
        id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        qty: 1,
        stock: parseInt(product.stock),
        unit: product.unit || 'pc',
        total: parseFloat(product.price),
        category: product.category || '',
        batch: product.batch || '',
        expiry: product.expiry || '',
      });
    }

    playAddSound();
    updateCartUI();
    return true;
  }

  /**
   * Remove item from cart
   */
  function removeItem(productId) {
    cart.items = cart.items.filter(item => item.id !== productId);
    updateCartUI();
  }

  /**
   * Update quantity of a cart item
   */
  function updateQuantity(productId, newQty) {
    const item = cart.items.find(i => i.id === productId);
    if (!item) return;

    newQty = parseInt(newQty);
    if (isNaN(newQty) || newQty < 1) {
      removeItem(productId);
      return;
    }

    if (newQty > item.stock) {
      MedWell.showToast('Only ' + item.stock + ' ' + item.unit + '(s) available.', 'warning');
      return;
    }

    item.qty = newQty;
    item.total = item.qty * item.price;
    updateCartUI();
  }

  /**
   * Increment item quantity
   */
  function incrementQty(productId) {
    const item = cart.items.find(i => i.id === productId);
    if (item) updateQuantity(productId, item.qty + 1);
  }

  /**
   * Decrement item quantity
   */
  function decrementQty(productId) {
    const item = cart.items.find(i => i.id === productId);
    if (item && item.qty > 1) {
      updateQuantity(productId, item.qty - 1);
    } else {
      removeItem(productId);
    }
  }

  /**
   * Clear entire cart
   */
  function clearCart() {
    if (cart.items.length === 0) return;
    if (confirm('Are you sure you want to clear the cart?')) {
      cart.items = [];
      cart.discount = { type: 'none', value: 0 };
      updateCartUI();
      MedWell.showToast('Cart cleared.', 'info');
    }
  }

  /* ----------------------------------------------------------
     Live Medicine Search with Debounce
     ---------------------------------------------------------- */
  let searchResults = [];
  let searchTimeout = null;

  function initSearch() {
    const searchInput = document.getElementById('pos-search-input');
    if (!searchInput) return;

    searchInput.addEventListener('input', MedWell.debounce((e) => {
      const query = e.target.value.trim();
      if (query.length < 2) {
        hideSearchResults();
        return;
      }
      searchMedicines(query);
    }, 250));

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const query = searchInput.value.trim();
        if (query) searchMedicines(query);
      }
    });
  }

  function searchMedicines(query) {
    // Show loading state
    const grid = document.querySelector('.pos-products-grid');
    if (grid) {
      grid.classList.add('searching');
    }

    MedWell.ajax(config.apiEndpoints.search, { q: query }, (err, result) => {
      if (grid) grid.classList.remove('searching');

      if (err) {
        MedWell.showToast('Search failed. Please try again.', 'danger');
        return;
      }

      searchResults = result.data || result || [];
      renderSearchResults(searchResults);
    }, 'GET');
  }

  function renderSearchResults(results) {
    const grid = document.querySelector('.pos-products-grid');
    if (!grid) return;

    if (results.length === 0) {
      grid.innerHTML = `
        <div class="empty-state" style="grid-column: 1 / -1;">
          <i class="fas fa-search"></i>
          <h4>No products found</h4>
          <p>Try a different search term or barcode.</p>
        </div>`;
      return;
    }

    grid.innerHTML = results.map(product => `
      <div class="pos-product-card ${product.stock <= 0 ? 'out-of-stock' : ''}"
           data-product-id="${product.id}"
           onclick="MedWellPOS.quickAdd(${product.id})">
        <div class="product-name">${MedWell.sanitizeHTML(product.name)}</div>
        <div class="product-stock">${product.stock > 0 ? product.stock + ' ' + (product.unit || 'pc') + ' in stock' : 'Out of stock'}</div>
        <div class="product-price">${MedWell.formatCurrency(product.price)}</div>
      </div>
    `).join('');
  }

  function hideSearchResults() {
    // Reload default product listing
    if (typeof loadAllProducts === 'function') loadAllProducts();
  }

  /* ----------------------------------------------------------
     Calculate Totals
     ---------------------------------------------------------- */
  function calculateSubtotal() {
    return cart.items.reduce((sum, item) => sum + item.total, 0);
  }

  function calculateTax(subtotal) {
    return subtotal * config.taxRate;
  }

  function calculateDiscount(subtotal) {
    if (cart.discount.type === 'percent') {
      return subtotal * (Math.min(cart.discount.value, config.discountMaxPercent) / 100);
    } else if (cart.discount.type === 'fixed') {
      return Math.min(cart.discount.value, config.discountMaxFixed);
    }
    return 0;
  }

  function calculateGrandTotal() {
    const subtotal = calculateSubtotal();
    const discount = calculateDiscount(subtotal);
    const taxableAmount = subtotal - discount;
    const tax = calculateTax(taxableAmount);
    return {
      subtotal: subtotal,
      discount: discount,
      tax: tax,
      grandTotal: taxableAmount + tax,
    };
  }

  /* ----------------------------------------------------------
     Discount Application
     ---------------------------------------------------------- */
  function applyDiscount(type, value) {
    value = parseFloat(value);
    if (isNaN(value) || value < 0) {
      MedWell.showToast('Invalid discount value.', 'danger');
      return;
    }

    if (type === 'percent' && value > config.discountMaxPercent) {
      MedWell.showToast('Discount cannot exceed ' + config.discountMaxPercent + '%.', 'warning');
      return;
    }

    if (type === 'fixed' && value > config.discountMaxFixed) {
      MedWell.showToast('Discount cannot exceed ' + MedWell.formatCurrency(config.discountMaxFixed) + '.', 'warning');
      return;
    }

    cart.discount = { type, value };
    updateCartUI();
    MedWell.showToast('Discount applied: ' + (type === 'percent' ? value + '%' : MedWell.formatCurrency(value)), 'success');
  }

  function removeDiscount() {
    cart.discount = { type: 'none', value: 0 };
    updateCartUI();
  }

  /* ----------------------------------------------------------
     Payment Method Selection
     ---------------------------------------------------------- */
  function setPaymentMethod(method) {
    const validMethods = ['cash', 'card', 'gcash', 'bank_transfer'];
    if (!validMethods.includes(method)) {
      MedWell.showToast('Invalid payment method.', 'danger');
      return;
    }
    cart.paymentMethod = method;
    updatePaymentUI();
  }

  function updatePaymentUI() {
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.method === cart.paymentMethod);
    });
  }

  /* ----------------------------------------------------------
     Complete Sale (AJAX to Backend)
     ---------------------------------------------------------- */
  function completeSale(cashTendered = null) {
    if (cart.items.length === 0) {
      MedWell.showToast('Cart is empty. Add items to proceed.', 'warning');
      playErrorSound();
      return;
    }

    const totals = calculateGrandTotal();

    if (cart.paymentMethod === 'cash') {
      if (cashTendered === null) {
        // Prompt for cash amount
        const input = prompt('Enter cash tendered amount:', totals.grandTotal.toFixed(2));
        if (input === null) return; // User cancelled
        cashTendered = parseFloat(input);
      }

      if (isNaN(cashTendered) || cashTendered < totals.grandTotal) {
        MedWell.showToast('Insufficient cash amount. Total is ' + MedWell.formatCurrency(totals.grandTotal), 'danger');
        playErrorSound();
        return;
      }
    }

    const saleData = {
      items: cart.items.map(item => ({
        product_id: item.id,
        name: item.name,
        qty: item.qty,
        price: item.price,
        total: item.total,
      })),
      subtotal: totals.subtotal,
      discount_type: cart.discount.type,
      discount_value: cart.discount.value,
      discount_amount: totals.discount,
      tax: totals.tax,
      grand_total: totals.grandTotal,
      payment_method: cart.paymentMethod,
      cash_tendered: cashTendered,
      change_amount: cashTendered ? cashTendered - totals.grandTotal : 0,
      customer_id: cart.customerId,
    };

    MedWell.showLoading('Processing sale...');

    MedWell.ajax(config.apiEndpoints.completeSale, saleData, (err, result) => {
      MedWell.hideLoading();

      if (err) {
        MedWell.showToast('Sale failed. Please try again.', 'danger');
        playErrorSound();
        return;
      }

      playSuccessSound();
      MedWell.showToast('Sale completed successfully!', 'success');

      // Show receipt or change
      if (result && result.receipt_id) {
        showReceiptModal(result, saleData, cashTendered);
      }

      // Clear cart after successful sale
      cart.items = [];
      cart.discount = { type: 'none', value: 0 };
      updateCartUI();
    });
  }

  function showReceiptModal(saleResult, saleData, cashTendered) {
    const totals = calculateGrandTotal();
    const change = cashTendered ? cashTendered - totals.grandTotal : 0;

    const receiptHtml = `
      <div class="receipt-print" id="receipt-content">
        <div class="receipt-header">
          <h3>MedWell Pharmacy</h3>
          <p>123 Health Street, Medical City</p>
          <p>Tel: (02) 1234-5678</p>
          <p>VAT Reg. TIN: 123-456-789-000</p>
        </div>
        <div class="receipt-divider"></div>
        <div class="receipt-line">
          <span>Receipt #:</span>
          <span>${saleResult.receipt_id || 'N/A'}</span>
        </div>
        <div class="receipt-line">
          <span>Date:</span>
          <span>${MedWell.formatDate(new Date(), 'MM/DD/YYYY HH:mm')}</span>
        </div>
        <div class="receipt-line">
          <span>Cashier:</span>
          <span>${saleResult.cashier || 'Admin'}</span>
        </div>
        <div class="receipt-line">
          <span>Customer:</span>
          <span>${cart.customerName}</span>
        </div>
        <div class="receipt-divider"></div>
        ${saleData.items.map(item => `
          <div class="receipt-line">
            <span>${item.name} x${item.qty}</span>
            <span>${MedWell.formatCurrency(item.total)}</span>
          </div>
        `).join('')}
        <div class="receipt-divider"></div>
        <div class="receipt-line">
          <span>Subtotal:</span>
          <span>${MedWell.formatCurrency(totals.subtotal)}</span>
        </div>
        ${totals.discount > 0 ? `
        <div class="receipt-line">
          <span>Discount:</span>
          <span>-${MedWell.formatCurrency(totals.discount)}</span>
        </div>` : ''}
        <div class="receipt-line">
          <span>VAT (${(config.taxRate * 100).toFixed(0)}%):</span>
          <span>${MedWell.formatCurrency(totals.tax)}</span>
        </div>
        <div class="receipt-line receipt-total">
          <span>TOTAL:</span>
          <span>${MedWell.formatCurrency(totals.grandTotal)}</span>
        </div>
        <div class="receipt-divider"></div>
        ${cashTendered ? `
        <div class="receipt-line">
          <span>Cash Tendered:</span>
          <span>${MedWell.formatCurrency(cashTendered)}</span>
        </div>
        <div class="receipt-line">
          <span>Change:</span>
          <span>${MedWell.formatCurrency(change)}</span>
        </div>` : ''}
        <div class="receipt-divider"></div>
        <p style="text-align:center; font-size:10pt;">Thank you for your purchase!</p>
      </div>
    `;

    // Populate modal with receipt
    const modalBody = document.querySelector('#receipt-modal .modal-body');
    if (modalBody) {
      modalBody.innerHTML = receiptHtml;
      MedWell.openModal('receipt-modal');
    }
  }

  /* ----------------------------------------------------------
     Print Receipt
     ---------------------------------------------------------- */
  function printReceipt() {
    MedWell.printReceipt('receipt-content');
  }

  /* ----------------------------------------------------------
     Hold / Recall Cart
     ---------------------------------------------------------- */
  function holdCart(customerName = '') {
    if (cart.items.length === 0) {
      MedWell.showToast('Cannot hold an empty cart.', 'warning');
      return;
    }

    const name = customerName || prompt('Enter a name for this held order:', cart.customerName);
    if (!name) return;

    const heldCart = {
      id: MedWell.generateId('held'),
      name: name,
      items: [...cart.items],
      discount: { ...cart.discount },
      paymentMethod: cart.paymentMethod,
      timestamp: new Date().toISOString(),
    };

    cart.heldCarts.push(heldCart);

    // Optionally sync to backend
    MedWell.ajax(config.apiEndpoints.holdCart, heldCart, (err) => {
      if (err) console.warn('Failed to sync held cart to server.');
    });

    // Clear current cart
    cart.items = [];
    cart.discount = { type: 'none', value: 0 };
    updateCartUI();
    updateHeldCartsUI();

    MedWell.showToast('Cart held successfully for ' + name, 'success');
    playAddSound();
  }

  function recallCart(heldCartId) {
    const index = cart.heldCarts.findIndex(hc => hc.id === heldCartId);
    if (index === -1) {
      MedWell.showToast('Held cart not found.', 'danger');
      return;
    }

    if (cart.items.length > 0) {
      if (!confirm('Current cart has items. Replace with held order?')) return;
    }

    const held = cart.heldCarts[index];
    cart.items = [...held.items];
    cart.discount = { ...held.discount };
    cart.paymentMethod = held.paymentMethod;
    cart.customerName = held.name;

    // Remove from held carts
    cart.heldCarts.splice(index, 1);
    updateCartUI();
    updateHeldCartsUI();

    MedWell.showToast('Cart recalled for ' + held.name, 'success');
    playAddSound();
  }

  function updateHeldCartsUI() {
    const container = document.querySelector('.held-carts-list');
    if (!container) return;

    if (cart.heldCarts.length === 0) {
      container.innerHTML = '<p class="text-muted text-center p-2" style="font-size:0.82rem;">No held orders</p>';
      return;
    }

    container.innerHTML = cart.heldCarts.map(hc => `
      <div class="held-cart-item d-flex align-center justify-between p-1 rounded-sm" 
           style="border: 1px solid var(--border-color); margin-bottom:6px; padding:8px 10px; cursor:pointer;"
           onclick="MedWellPOS.recallCart('${hc.id}')">
        <div>
          <div style="font-weight:600; font-size:0.85rem;">${MedWell.sanitizeHTML(hc.name)}</div>
          <div style="font-size:0.75rem; color:var(--text-muted);">${hc.items.length} item(s) &middot; ${MedWell.timeAgo(hc.timestamp)}</div>
        </div>
        <i class="fas fa-undo" style="color:var(--primary);"></i>
      </div>
    `).join('');
  }

  /* ----------------------------------------------------------
     Quick Add by Barcode Scan
     ---------------------------------------------------------- */
  let barcodeBuffer = '';
  let barcodeTimeout = null;

  function initBarcodeScanner() {
    document.addEventListener('keypress', (e) => {
      // Only capture if not focused on an input
      if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;

      clearTimeout(barcodeTimeout);

      barcodeBuffer += e.key;
      playBeep(1400, 30, 0.05);

      barcodeTimeout = setTimeout(() => {
        if (barcodeBuffer.length >= 3) {
          processBarcodeScan(barcodeBuffer.trim());
        }
        barcodeBuffer = '';
      }, 100);
    });
  }

  function processBarcodeScan(barcode) {
    playScanSound();

    MedWell.ajax(config.apiEndpoints.productDetails + encodeURIComponent(barcode), {}, (err, result) => {
      if (err || !result || !result.data) {
        MedWell.showToast('Product not found: ' + barcode, 'danger');
        playErrorSound();
        return;
      }
      addItem(result.data);
    }, 'GET');
  }

  function quickAdd(productId) {
    // Find product in the displayed grid or search via API
    const card = document.querySelector(`[data-product-id="${productId}"]`);
    if (card) {
      const product = {
        id: productId,
        name: card.querySelector('.product-name')?.textContent || 'Unknown',
        price: parseCurrency(card.querySelector('.product-price')?.textContent || '0'),
        stock: parseInt(card.querySelector('.product-stock')?.textContent) || 0,
      };
      addItem(product);
      return;
    }

    // Fallback: search via API
    MedWell.ajax(config.apiEndpoints.productDetails + productId, {}, (err, result) => {
      if (err || !result || !result.data) {
        MedWell.showToast('Product not found.', 'danger');
        playErrorSound();
        return;
      }
      addItem(result.data);
    }, 'GET');
  }

  function parseCurrency(str) {
    return parseFloat(str.replace(/[^0-9.-]/g, '')) || 0;
  }

  /* ----------------------------------------------------------
     Cart Item Count Badge
     ---------------------------------------------------------- */
  function updateCartBadge() {
    const badge = document.querySelector('.pos-cart-header .cart-count');
    if (badge) {
      const count = cart.items.reduce((sum, item) => sum + item.qty, 0);
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  }

  /* ----------------------------------------------------------
     Keyboard Shortcuts for POS
     ---------------------------------------------------------- */
  function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // F2: Focus search
      if (e.key === 'F2') {
        e.preventDefault();
        const searchInput = document.getElementById('pos-search-input');
        if (searchInput) searchInput.focus();
      }

      // F4: Pay / Complete sale
      if (e.key === 'F4') {
        e.preventDefault();
        completeSale();
      }

      // F8: Clear cart
      if (e.key === 'F8') {
        e.preventDefault();
        clearCart();
      }

      // F6: Hold cart
      if (e.key === 'F6') {
        e.preventDefault();
        holdCart();
      }

      // F9: Print receipt
      if (e.key === 'F9') {
        e.preventDefault();
        printReceipt();
      }
    });
  }

  /* ----------------------------------------------------------
     UI Update Functions
     ---------------------------------------------------------- */
  function updateCartUI() {
    renderCartItems();
    updateCartSummary();
    updateCartBadge();
  }

  function renderCartItems() {
    const container = document.querySelector('.pos-cart-items');
    if (!container) return;

    if (cart.items.length === 0) {
      container.innerHTML = `
        <div class="pos-cart-empty">
          <i class="fas fa-shopping-cart"></i>
          <p>No items in cart</p>
          <span style="font-size:0.78rem;">Search or scan products to add</span>
        </div>`;
      return;
    }

    container.innerHTML = cart.items.map(item => `
      <div class="pos-cart-item" data-item-id="${item.id}">
        <div class="item-info">
          <div class="item-name">${MedWell.sanitizeHTML(item.name)}</div>
          <div class="item-price">${MedWell.formatCurrency(item.price)} / ${item.unit}</div>
        </div>
        <div class="item-qty">
          <button class="qty-btn" onclick="MedWellPOS.decrementQty(${item.id})" aria-label="Decrease quantity">-</button>
          <span class="qty-value">${item.qty}</span>
          <button class="qty-btn" onclick="MedWellPOS.incrementQty(${item.id})" aria-label="Increase quantity">+</button>
        </div>
        <div class="item-total">${MedWell.formatCurrency(item.total)}</div>
        <button class="item-remove" onclick="MedWellPOS.removeItem(${item.id})" aria-label="Remove item">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `).join('');
  }

  function updateCartSummary() {
    const totals = calculateGrandTotal();
    const summaryEl = document.querySelector('.pos-cart-summary');
    if (!summaryEl) return;

    summaryEl.innerHTML = `
      <div class="pos-summary-row">
        <span class="label">Subtotal</span>
        <span class="value">${MedWell.formatCurrency(totals.subtotal)}</span>
      </div>
      ${totals.discount > 0 ? `
      <div class="pos-summary-row">
        <span class="label">Discount ${cart.discount.type === 'percent' ? '(' + cart.discount.value + '%)' : ''}</span>
        <span class="value text-danger">-${MedWell.formatCurrency(totals.discount)}</span>
      </div>` : ''}
      <div class="pos-summary-row">
        <span class="label">VAT (${(config.taxRate * 100).toFixed(0)}%)</span>
        <span class="value">${MedWell.formatCurrency(totals.tax)}</span>
      </div>
      <div class="pos-summary-row total">
        <span class="label">Grand Total</span>
        <span class="value">${MedWell.formatCurrency(totals.grandTotal)}</span>
      </div>
    `;
  }

  /* ----------------------------------------------------------
     Set Customer
     ---------------------------------------------------------- */
  function setCustomer(customerId, customerName) {
    cart.customerId = customerId;
    cart.customerName = customerName || 'Walk-in Customer';
    const display = document.querySelector('.pos-customer-name');
    if (display) display.textContent = cart.customerName;
  }

  /* ----------------------------------------------------------
     Initialization
     ---------------------------------------------------------- */
  function init() {
    initSearch();
    initBarcodeScanner();
    initKeyboardShortcuts();
    updateCartUI();
    updateHeldCartsUI();

    // Payment method buttons
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        setPaymentMethod(btn.dataset.method);
      });
    });

    // Discount form
    const discountForm = document.getElementById('discount-form');
    if (discountForm) {
      discountForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const type = discountForm.querySelector('[name="discount_type"]').value;
        const value = discountForm.querySelector('[name="discount_value"]').value;
        applyDiscount(type, value);
      });
    }

    // Complete sale button
    const completeBtn = document.getElementById('complete-sale-btn');
    if (completeBtn) {
      completeBtn.addEventListener('click', () => completeSale());
    }

    // Clear cart button
    const clearBtn = document.getElementById('clear-cart-btn');
    if (clearBtn) {
      clearBtn.addEventListener('click', clearCart);
    }

    // Hold cart button
    const holdBtn = document.getElementById('hold-cart-btn');
    if (holdBtn) {
      holdBtn.addEventListener('click', () => holdCart());
    }
  }

  document.addEventListener('DOMContentLoaded', init);

  /* ----------------------------------------------------------
     Public API
     ---------------------------------------------------------- */
  return {
    // Cart management
    addItem,
    removeItem,
    updateQuantity,
    incrementQty,
    decrementQty,
    clearCart,
    // Search
    searchMedicines,
    // Totals
    calculateSubtotal,
    calculateTax,
    calculateDiscount,
    calculateGrandTotal,
    // Discount
    applyDiscount,
    removeDiscount,
    // Payment
    setPaymentMethod,
    // Sale
    completeSale,
    // Receipt
    printReceipt,
    // Hold/Recall
    holdCart,
    recallCart,
    // Quick add
    quickAdd,
    processBarcodeScan,
    // Customer
    setCustomer,
    // Sound
    playScanSound,
    playAddSound,
    playErrorSound,
    playSuccessSound,
    // Cart state (read-only access)
    getCart: () => ({ ...cart, items: [...cart.items] }),
    getCartItems: () => [...cart.items],
    getCartItemCount: () => cart.items.reduce((sum, i) => sum + i.qty, 0),
  };

})();
