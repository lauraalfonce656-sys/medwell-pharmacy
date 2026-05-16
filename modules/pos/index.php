<?php
/**
 * MedWell Pharmacy - Point of Sale (POS)
 * 
 * Premium, modern POS interface with avocado green theme.
 * Full-screen layout with product grid and cart sidebar.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/Sale.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'pos';

$medicine = new Medicine();
$customer = new Customer();
$sale     = new Sale();

$medicines = $medicine->getAll(['is_active' => 1], '', 100, 0);
$customers = $customer->getAll('', 100, 0);
$settings  = getSettings();
$taxRate   = defined('TAX_RATE') ? TAX_RATE : 18;

// Category tabs - extract unique categories from medicines
$categories = [];
foreach ($medicines as $med) {
    $catName = $med['category_name'] ?? 'Uncategorized';
    $catId   = $med['category_id'] ?? 0;
    if (!isset($categories[$catId])) {
        $categories[$catId] = $catName;
    }
}

$pharmacyName    = $settings['pharmacy_name'] ?? APP_NAME;
$pharmacyAddress = $settings['pharmacy_address'] ?? '';
$pharmacyPhone   = $settings['pharmacy_phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Point of Sale - <?= sanitize($pharmacyName) ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        /* ═══════════════════════════════════════════════════════════
           POS FULL-SCREEN LAYOUT
           ═══════════════════════════════════════════════════════════ */

        /* Override layout for POS full-screen */
        body.pos-fullscreen .sidebar,
        body.pos-fullscreen .sidebar-overlay,
        body.pos-fullscreen .top-header {
            display: none !important;
        }
        body.pos-fullscreen .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        body.pos-fullscreen .pos-layout {
            height: 100vh;
            margin: 0;
        }

        .pos-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            height: calc(100vh - 64px);
            margin-top: -24px;
            margin-left: -28px;
            margin-right: -28px;
            margin-bottom: -28px;
            overflow: hidden;
            background: var(--bg-body);
        }

        /* ─── Left Panel: Products ──────────────────────────────── */
        .pos-products-panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--bg-body);
            padding: 20px 24px;
            gap: 16px;
        }

        /* Top Bar */
        .pos-top-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .pos-fullscreen-toggle {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 1.15rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .pos-fullscreen-toggle:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }

        .pos-search {
            flex: 1;
            position: relative;
        }
        .pos-search input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 500;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: var(--transition);
            outline: none;
            font-family: var(--font-sans);
        }
        .pos-search input::placeholder {
            color: var(--text-muted);
        }
        .pos-search input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 179, 66, 0.12);
        }
        .pos-search .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.15rem;
            transition: var(--transition);
            pointer-events: none;
        }
        .pos-search input:focus ~ .search-icon {
            color: var(--primary);
        }
        .pos-search .barcode-hint {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-50);
            color: var(--primary-dark);
            font-size: 0.72rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            pointer-events: none;
        }
        .pos-search .barcode-hint i { font-size: 0.85rem; }

        /* Category Tabs */
        .pos-category-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            flex-shrink: 0;
            scrollbar-width: none;
        }
        .pos-category-tabs::-webkit-scrollbar { display: none; }

        .pos-category-tab {
            padding: 8px 18px;
            border-radius: 24px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            white-space: nowrap;
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .pos-category-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .pos-category-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(124, 179, 66, 0.3);
        }

        /* Products Grid */
        .pos-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
            gap: 14px;
            overflow-y: auto;
            flex: 1;
            padding: 4px 2px;
            align-content: start;
        }

        .pos-product-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }
        .pos-product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: var(--transition);
        }
        .pos-product-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 25px rgba(124, 179, 66, 0.15);
            transform: translateY(-3px);
        }
        .pos-product-card:hover::before {
            opacity: 1;
        }

        .pos-product-card .product-category {
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            margin-bottom: 2px;
        }
        .pos-product-card .product-name {
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
            min-height: 2.2em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .pos-product-card .product-generic {
            font-size: 0.72rem;
            color: var(--text-muted);
            font-style: italic;
        }
        .pos-product-card .product-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px dashed var(--border-color);
        }
        .pos-product-card .product-price {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary-dark);
        }
        .pos-product-card .product-stock {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            white-space: nowrap;
        }
        .pos-product-card .product-stock.in-stock {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }
        .pos-product-card .product-stock.low-stock {
            background: rgba(243, 156, 18, 0.1);
            color: #e67e22;
        }
        .pos-product-card .product-stock.out-of-stock {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .pos-product-card .quick-add-btn {
            position: absolute;
            bottom: 12px;
            right: 12px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.7);
            transition: var(--transition);
            box-shadow: 0 3px 10px rgba(124, 179, 66, 0.35);
        }
        .pos-product-card:hover .quick-add-btn {
            opacity: 1;
            transform: scale(1);
        }
        .pos-product-card .quick-add-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 5px 15px rgba(124, 179, 66, 0.5);
        }

        .pos-product-card.out-of-stock {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Product added animation */
        .pos-product-card.just-added {
            animation: cardPulse 0.4s ease;
        }
        @keyframes cardPulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); border-color: var(--primary); box-shadow: 0 0 0 4px rgba(124, 179, 66, 0.2); }
            100% { transform: scale(1); }
        }

        /* Load More */
        .pos-load-more {
            grid-column: 1 / -1;
            text-align: center;
            padding: 16px 0;
        }
        .pos-load-more button {
            padding: 10px 28px;
            border-radius: var(--radius-xl);
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.86rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .pos-load-more button:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }

        /* ─── Right Panel: Cart Sidebar ─────────────────────────── */
        .pos-cart-panel {
            background: var(--bg-card);
            border-left: 1.5px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Cart Header */
        .pos-cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1.5px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
            flex-shrink: 0;
        }
        .pos-cart-header .cart-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pos-cart-header .cart-title h3 {
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0;
        }
        .pos-cart-header .cart-title i {
            font-size: 1.3rem;
            color: var(--primary);
        }
        .pos-cart-header .cart-count-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            min-width: 26px;
            height: 26px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0 6px;
            box-shadow: 0 2px 8px rgba(124, 179, 66, 0.35);
        }
        .pos-cart-header .cart-header-actions {
            display: flex;
            gap: 6px;
        }
        .pos-cart-header .cart-header-actions button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        .pos-cart-header .cart-header-actions button:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .pos-cart-header .cart-header-actions button.btn-danger-hover:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.08);
        }

        /* Customer Selection */
        .pos-customer-select {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .pos-customer-select label {
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 6px;
            display: block;
        }
        .pos-customer-select select {
            width: 100%;
            padding: 9px 36px 9px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            font-weight: 500;
            background: var(--bg-body);
            color: var(--text-primary);
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23636e72' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px 14px;
            cursor: pointer;
            outline: none;
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .pos-customer-select select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
        }

        /* Cart Items */
        .pos-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 12px 16px;
        }
        .pos-cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 8px;
            background: var(--bg-card);
            transition: var(--transition);
            animation: slideIn 0.2s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .pos-cart-item:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }
        .pos-cart-item .item-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 8px;
            background: var(--primary-100);
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .pos-cart-item .item-info {
            flex: 1;
            min-width: 0;
        }
        .pos-cart-item .item-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pos-cart-item .item-price {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .pos-cart-item .item-qty {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .pos-cart-item .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-weight: 700;
        }
        .pos-cart-item .qty-btn:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .pos-cart-item .qty-value {
            font-size: 0.9rem;
            font-weight: 700;
            min-width: 28px;
            text-align: center;
            color: var(--text-primary);
        }
        .pos-cart-item .item-total {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-primary);
            min-width: 72px;
            text-align: right;
        }
        .pos-cart-item .item-remove {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        .pos-cart-item .item-remove:hover {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        /* Cart Empty State */
        .pos-cart-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            color: var(--text-muted);
            text-align: center;
        }
        .pos-cart-empty .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-50);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .pos-cart-empty .empty-icon i {
            font-size: 2.2rem;
            color: var(--primary-200);
        }
        .pos-cart-empty h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .pos-cart-empty p {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        /* Cart Summary */
        .pos-cart-summary {
            border-top: 1.5px solid var(--border-color);
            padding: 14px 18px;
            flex-shrink: 0;
        }
        .pos-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.88rem;
        }
        .pos-summary-row .label {
            color: var(--text-secondary);
        }
        .pos-summary-row .value {
            font-weight: 600;
            color: var(--text-primary);
        }
        .pos-summary-row.discount .value {
            color: var(--danger);
        }
        .pos-summary-row.total {
            font-size: 1.15rem;
            padding-top: 10px;
            margin-top: 8px;
            border-top: 2px solid var(--border-color);
        }
        .pos-summary-row.total .label {
            font-weight: 700;
            color: var(--text-primary);
        }
        .pos-summary-row.total .value {
            color: var(--primary-dark);
            font-size: 1.35rem;
            font-weight: 800;
        }

        /* Discount Input */
        .pos-discount-input {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 18px 10px;
            flex-shrink: 0;
        }
        .pos-discount-input select,
        .pos-discount-input input {
            padding: 7px 10px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 500;
            outline: none;
            transition: var(--transition);
            font-family: var(--font-sans);
            background: var(--bg-body);
            color: var(--text-primary);
        }
        .pos-discount-input select:focus,
        .pos-discount-input input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
        }
        .pos-discount-input select {
            width: 70px;
        }
        .pos-discount-input input {
            flex: 1;
        }
        .pos-discount-input .apply-discount-btn {
            padding: 7px 12px;
            border: none;
            border-radius: 6px;
            background: var(--primary-100);
            color: var(--primary-dark);
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
        }
        .pos-discount-input .apply-discount-btn:hover {
            background: var(--primary);
            color: #fff;
        }

        /* Payment Method Selection */
        .pos-payment-methods {
            display: flex;
            gap: 8px;
            padding: 0 18px 14px;
            flex-shrink: 0;
        }
        .payment-method-btn {
            flex: 1;
            padding: 10px 8px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-body);
            color: var(--text-secondary);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            font-family: var(--font-sans);
        }
        .payment-method-btn i {
            font-size: 1.2rem;
        }
        .payment-method-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .payment-method-btn.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
            color: var(--primary-dark);
            box-shadow: 0 2px 8px rgba(124, 179, 66, 0.2);
        }

        /* Cart Actions */
        .pos-cart-actions {
            padding: 0 18px 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }
        .btn-complete-sale {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #7CB342, #558B2F);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(124, 179, 66, 0.4);
            letter-spacing: 0.02em;
        }
        .btn-complete-sale:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(124, 179, 66, 0.5);
        }
        .btn-complete-sale:active {
            transform: translateY(0);
        }
        .btn-complete-sale:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .btn-complete-sale i {
            font-size: 1.2rem;
        }

        .pos-action-row {
            display: flex;
            gap: 8px;
        }
        .btn-hold-cart,
        .btn-clear-cart {
            flex: 1;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: var(--font-sans);
        }
        .btn-hold-cart {
            color: var(--text-secondary);
        }
        .btn-hold-cart:hover {
            border-color: var(--warning);
            color: var(--warning);
            background: rgba(243, 156, 18, 0.06);
        }
        .btn-clear-cart {
            color: var(--text-secondary);
        }
        .btn-clear-cart:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.06);
        }

        /* Held Carts Section */
        .pos-held-carts {
            border-top: 1px solid var(--border-color);
            padding: 12px 18px;
            flex-shrink: 0;
            max-height: 150px;
            overflow-y: auto;
            display: none;
        }
        .pos-held-carts.has-items {
            display: block;
        }
        .pos-held-carts .held-title {
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .held-cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: var(--transition);
        }
        .held-cart-item:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }
        .held-cart-item .held-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .held-cart-item .held-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* Keyboard Shortcuts Bar */
        .pos-shortcuts {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 8px 24px;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
            font-size: 0.74rem;
            color: var(--text-muted);
        }
        .pos-shortcuts .shortcut {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .pos-shortcuts kbd {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 20px;
            padding: 0 5px;
            border-radius: 4px;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            font-size: 0.68rem;
            font-weight: 700;
            font-family: var(--font-sans);
            color: var(--text-secondary);
        }

        /* ─── Cash Payment Modal ────────────────────────────────── */
        .pos-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 5000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        .pos-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .pos-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 420px;
            max-width: 95%;
            transform: scale(0.9) translateY(20px);
            transition: var(--transition);
            overflow: hidden;
        }
        .pos-modal-overlay.active .pos-modal {
            transform: scale(1) translateY(0);
        }
        .pos-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .pos-modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }
        .pos-modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        .pos-modal-close:hover {
            background: var(--primary-50);
            color: var(--danger);
        }
        .pos-modal-body {
            padding: 24px;
        }
        .pos-modal-footer {
            display: flex;
            gap: 10px;
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            justify-content: flex-end;
        }

        /* Cash Payment Specific */
        .cash-amount-display {
            text-align: center;
            padding: 20px;
            background: var(--primary-50);
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        .cash-amount-display .amount-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cash-amount-display .amount-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-top: 4px;
        }
        .cash-input-group {
            margin-bottom: 16px;
        }
        .cash-input-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .cash-input-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1.1rem;
            font-weight: 700;
            text-align: right;
            outline: none;
            transition: var(--transition);
            font-family: var(--font-sans);
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .cash-input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
        }
        .cash-quick-amounts {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .cash-quick-amounts button {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-body);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
            color: var(--text-secondary);
        }
        .cash-quick-amounts button:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .cash-change-display {
            text-align: center;
            padding: 14px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border-color);
            background: var(--bg-body);
        }
        .cash-change-display .change-label {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .cash-change-display .change-value {
            font-size: 1.4rem;
            font-weight: 800;
            margin-top: 4px;
        }
        .cash-change-display .change-value.sufficient {
            color: var(--success);
        }
        .cash-change-display .change-value.insufficient {
            color: var(--danger);
        }

        /* ─── Responsive ─────────────────────────────────────────── */
        @media (max-width: 991.98px) {
            .pos-layout {
                grid-template-columns: 1fr;
                height: auto;
                min-height: calc(100vh - 64px);
            }
            .pos-products-panel {
                height: 55vh;
            }
            .pos-cart-panel {
                border-left: none;
                border-top: 1.5px solid var(--border-color);
                height: 45vh;
            }
            .pos-shortcuts { display: none; }
        }

        @media (max-width: 575.98px) {
            .pos-products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .pos-top-bar { flex-wrap: wrap; }
            .pos-search input { font-size: 0.88rem; padding: 10px 14px 10px 40px; }
            .pos-category-tabs { gap: 6px; }
            .pos-category-tab { padding: 6px 14px; font-size: 0.76rem; }
            .pos-payment-methods { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Header -->
        <?php include __DIR__ . '/../../includes/templates/header.php'; ?>

        <!-- ═══════════════════════════════════════════════════════════
             POS LAYOUT
             ═══════════════════════════════════════════════════════════ -->
        <div class="pos-layout" id="posLayout">

            <!-- ─── Left Panel: Product Selection ─── -->
            <div class="pos-products-panel">

                <!-- Top Bar -->
                <div class="pos-top-bar">
                    <button class="pos-fullscreen-toggle" id="fullscreenToggle" title="Toggle fullscreen (F11)">
                        <i class="ri-fullscreen-line"></i>
                    </button>
                    <div class="pos-search">
                        <i class="ri-search-line search-icon"></i>
                        <input type="text" id="pos-search-input" placeholder="Search medicines or scan barcode..." autocomplete="off">
                        <span class="barcode-hint"><i class="ri-barcode-line"></i> F2 to focus</span>
                    </div>
                </div>

                <!-- Category Tabs -->
                <div class="pos-category-tabs" id="categoryTabs">
                    <button class="pos-category-tab active" data-category="0">All</button>
                    <?php foreach ($categories as $catId => $catName): ?>
                        <button class="pos-category-tab" data-category="<?= (int) $catId ?>">
                            <?= sanitize($catName) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Products Grid -->
                <div class="pos-products-grid" id="productsGrid">
                    <?php if (!empty($medicines)): ?>
                        <?php foreach ($medicines as $med): ?>
                            <?php
                            $stock    = (int) ($med['quantity'] ?? 0);
                            $minStock = (int) ($med['min_stock_level'] ?? 10);
                            $price    = (float) ($med['selling_price'] ?? 0);

                            $stockClass = $stock <= 0 ? 'out-of-stock' : ($stock <= $minStock ? 'low-stock' : 'in-stock');
                            $stockText  = $stock <= 0 ? 'Out of stock' : ($stock <= $minStock ? $stock . ' left' : $stock . ' ' . ($med['unit'] ?? 'pc'));
                            $cardClass  = $stock <= 0 ? 'out-of-stock' : '';
                            ?>
                            <div class="pos-product-card <?= $cardClass ?>"
                                 data-product-id="<?= (int) $med['id'] ?>"
                                 data-category="<?= (int) ($med['category_id'] ?? 0) ?>"
                                 data-name="<?= sanitize($med['name'] ?? '') ?>"
                                 data-price="<?= $price ?>"
                                 data-stock="<?= $stock ?>">
                                <div class="product-category"><?= sanitize($med['category_name'] ?? 'General') ?></div>
                                <div class="product-name"><?= sanitize($med['name'] ?? 'Unknown') ?></div>
                                <?php if (!empty($med['generic_name'])): ?>
                                <div class="product-generic"><?= sanitize($med['generic_name']) ?></div>
                                <?php endif; ?>
                                <div class="product-bottom">
                                    <div class="product-price"><?= formatCurrency($price) ?></div>
                                    <span class="product-stock <?= $stockClass ?>"><?= $stockText ?></span>
                                </div>
                                <?php if ($stock > 0): ?>
                                <button class="quick-add-btn" onclick="event.stopPropagation(); POS.addItemFromCard(this.closest('.pos-product-card'))" title="Add to cart">
                                    <i class="ri-add-line"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="ri-medicine-bottle-line"></i>
                            <h4>No medicines found</h4>
                            <p>Add medicines to your inventory to start selling.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Load More -->
                <?php if (count($medicines) >= 100): ?>
                <div class="pos-load-more">
                    <button onclick="POS.loadMore()"><i class="ri-arrow-down-line"></i> Load More Products</button>
                </div>
                <?php endif; ?>

                <!-- Keyboard Shortcuts -->
                <div class="pos-shortcuts">
                    <span class="shortcut"><kbd>F2</kbd> Search</span>
                    <span class="shortcut"><kbd>F4</kbd> Pay</span>
                    <span class="shortcut"><kbd>F6</kbd> Hold</span>
                    <span class="shortcut"><kbd>F8</kbd> Clear</span>
                    <span class="shortcut"><kbd>F9</kbd> Print</span>
                </div>
            </div>

            <!-- ─── Right Panel: Cart Sidebar ─── -->
            <div class="pos-cart-panel">

                <!-- Cart Header -->
                <div class="pos-cart-header">
                    <div class="cart-title">
                        <i class="ri-shopping-cart-2-line"></i>
                        <h3>Current Sale</h3>
                        <span class="cart-count-badge" id="cartCountBadge">0</span>
                    </div>
                    <div class="cart-header-actions">
                        <button id="hold-cart-btn" title="Hold Cart (F6)"><i class="ri-pause-line"></i></button>
                        <button id="clear-cart-btn" class="btn-danger-hover" title="Clear Cart (F8)"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>

                <!-- Customer Selection -->
                <div class="pos-customer-select">
                    <label><i class="ri-user-3-line"></i> Customer</label>
                    <select id="customerSelect">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?= (int) $cust['id'] ?>"><?= sanitize($cust['full_name'] ?? '') ?><?= !empty($cust['phone']) ? ' - ' . sanitize($cust['phone']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cart Items -->
                <div class="pos-cart-items" id="cartItemsList">
                    <div class="pos-cart-empty" id="cartEmpty">
                        <div class="empty-icon">
                            <i class="ri-shopping-cart-line"></i>
                        </div>
                        <h4>Cart is empty</h4>
                        <p>Search or scan products to add</p>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="pos-cart-summary" id="cartSummary">
                    <div class="pos-summary-row">
                        <span class="label">Subtotal</span>
                        <span class="value" id="subtotalValue"><?= formatCurrency(0) ?></span>
                    </div>
                    <div class="pos-summary-row discount" id="discountRow" style="display:none;">
                        <span class="label">Discount</span>
                        <span class="value" id="discountValue">-<?= formatCurrency(0) ?></span>
                    </div>
                    <div class="pos-summary-row">
                        <span class="label">Tax (<?= $taxRate ?>%)</span>
                        <span class="value" id="taxValue"><?= formatCurrency(0) ?></span>
                    </div>
                    <div class="pos-summary-row total">
                        <span class="label">Grand Total</span>
                        <span class="value" id="grandTotalValue"><?= formatCurrency(0) ?></span>
                    </div>
                </div>

                <!-- Discount Input -->
                <div class="pos-discount-input">
                    <select id="discountType">
                        <option value="percent">%</option>
                        <option value="fixed"><?= CURRENCY ?></option>
                    </select>
                    <input type="number" id="discountValueInput" placeholder="Discount" min="0" step="any">
                    <button class="apply-discount-btn" id="applyDiscountBtn">Apply</button>
                </div>

                <!-- Payment Methods -->
                <div class="pos-payment-methods">
                    <button class="payment-method-btn active" data-method="cash" id="pmCash">
                        <i class="ri-money-dollar-circle-line"></i>
                        Cash
                    </button>
                    <button class="payment-method-btn" data-method="card" id="pmCard">
                        <i class="ri-bank-card-line"></i>
                        Card
                    </button>
                    <button class="payment-method-btn" data-method="mobile" id="pmMobile">
                        <i class="ri-smartphone-line"></i>
                        Mobile
                    </button>
                </div>

                <!-- Cart Actions -->
                <div class="pos-cart-actions">
                    <button class="btn-complete-sale" id="completeSaleBtn">
                        <i class="ri-check-double-line"></i>
                        Complete Sale
                        <span style="font-size:0.78rem; opacity:0.8;">(F4)</span>
                    </button>
                    <div class="pos-action-row">
                        <button class="btn-hold-cart" id="holdCartBtn">
                            <i class="ri-pause-line"></i> Hold Cart
                        </button>
                        <button class="btn-clear-cart" id="clearCartBtn">
                            <i class="ri-delete-bin-line"></i> Clear Cart
                        </button>
                    </div>
                </div>

                <!-- Held Carts Section -->
                <div class="pos-held-carts" id="heldCartsSection">
                    <div class="held-title">
                        <i class="ri-time-line"></i> Held Orders
                    </div>
                    <div class="held-carts-list" id="heldCartsList">
                        <p style="font-size:0.78rem; color:var(--text-muted); text-align:center; padding:8px 0;">No held orders</p>
                    </div>
                </div>
            </div>

        </div><!-- /.pos-layout -->

    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CASH PAYMENT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="pos-modal-overlay" id="cashModal">
    <div class="pos-modal">
        <div class="pos-modal-header">
            <h3><i class="ri-money-dollar-circle-line" style="color: var(--primary); margin-right: 8px;"></i> Cash Payment</h3>
            <button class="pos-modal-close" onclick="POS.closeCashModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="pos-modal-body">
            <div class="cash-amount-display">
                <div class="amount-label">Amount Due</div>
                <div class="amount-value" id="cashAmountDue"><?= formatCurrency(0) ?></div>
            </div>

            <div class="cash-input-group">
                <label>Cash Tendered</label>
                <input type="number" id="cashTenderedInput" placeholder="0.00" min="0" step="any" autofocus>
            </div>

            <div class="cash-quick-amounts" id="quickAmounts">
                <!-- Populated by JS -->
            </div>

            <div class="cash-change-display">
                <div class="change-label">Change</div>
                <div class="change-value sufficient" id="changeDisplay"><?= formatCurrency(0) ?></div>
            </div>
        </div>
        <div class="pos-modal-footer">
            <button class="btn btn-outline-secondary" onclick="POS.closeCashModal()" style="padding:10px 20px; border-radius:var(--radius-sm); border:2px solid var(--border-color); background:var(--bg-card); color:var(--text-secondary); font-weight:600; cursor:pointer; font-family:var(--font-sans);">Cancel</button>
            <button class="btn-complete-sale" id="confirmCashSale" style="width:auto; padding:12px 28px;">
                <i class="ri-check-line"></i> Confirm Payment
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     RECEIPT PREVIEW MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="pos-modal-overlay" id="receiptModal">
    <div class="pos-modal" style="width: 380px;">
        <div class="pos-modal-header">
            <h3><i class="ri-receipt-line" style="color: var(--primary); margin-right: 8px;"></i> Receipt</h3>
            <button class="pos-modal-close" onclick="POS.closeReceiptModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="pos-modal-body" id="receiptContent" style="padding: 0;">
            <!-- Receipt content injected by JS -->
        </div>
        <div class="pos-modal-footer" style="justify-content: center;">
            <button class="btn-hold-cart" onclick="POS.printReceipt()" style="border-color:var(--primary); color:var(--primary);">
                <i class="ri-printer-line"></i> Print Receipt
            </button>
            <button class="btn-complete-sale" onclick="POS.closeReceiptModal()" style="width:auto; padding:10px 20px;">
                <i class="ri-check-line"></i> Done
            </button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="/assets/js/app.js"></script>
<script src="/assets/js/pos.js"></script>

<!-- POS Page-Specific Script -->
<script>
'use strict';

const POS = (function() {

    // ─── State ──────────────────────────────────────────────────
    const TAX_RATE = <?= $taxRate ?> / 100;
    const CURRENCY = '<?= CURRENCY ?>';
    const CURRENCY_SYMBOL = '<?= CURRENCY ?>';

    let cart = {
        items: [],
        discount: { type: 'none', value: 0 },
        paymentMethod: 'cash',
        customerId: null,
        customerName: 'Walk-in Customer',
        heldCarts: []
    };

    // ─── Helpers ────────────────────────────────────────────────
    function formatCurrency(amount) {
        return numberFormat(amount, 2) + ' ' + CURRENCY_SYMBOL;
    }

    function numberFormat(num, decimals) {
        return Number(num).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function parseCurrency(str) {
        return parseFloat(String(str).replace(/[^0-9.-]/g, '')) || 0;
    }

    // ─── Cart Management ────────────────────────────────────────
    function addItemFromCard(cardEl) {
        const productId = parseInt(cardEl.dataset.productId);
        const name = cardEl.dataset.name;
        const price = parseFloat(cardEl.dataset.price);
        const stock = parseInt(cardEl.dataset.stock);

        if (stock <= 0) {
            showToast('Product is out of stock', 'warning');
            return;
        }

        const existingIndex = cart.items.findIndex(i => i.id === productId);
        if (existingIndex !== -1) {
            const item = cart.items[existingIndex];
            if (item.qty >= stock) {
                showToast('Only ' + stock + ' units available', 'warning');
                return;
            }
            item.qty++;
            item.total = item.qty * item.price;
        } else {
            cart.items.push({
                id: productId,
                name: name,
                price: price,
                qty: 1,
                stock: stock,
                unit: 'pc',
                total: price
            });
        }

        // Visual feedback
        cardEl.classList.add('just-added');
        setTimeout(() => cardEl.classList.remove('just-added'), 400);

        playAddSound();
        updateCartUI();
    }

    function removeItem(productId) {
        cart.items = cart.items.filter(i => i.id !== productId);
        updateCartUI();
    }

    function incrementQty(productId) {
        const item = cart.items.find(i => i.id === productId);
        if (item) {
            if (item.qty >= item.stock) {
                showToast('Only ' + item.stock + ' units available', 'warning');
                return;
            }
            item.qty++;
            item.total = item.qty * item.price;
            updateCartUI();
        }
    }

    function decrementQty(productId) {
        const item = cart.items.find(i => i.id === productId);
        if (item) {
            if (item.qty > 1) {
                item.qty--;
                item.total = item.qty * item.price;
            } else {
                removeItem(productId);
                return;
            }
            updateCartUI();
        }
    }

    function clearCart() {
        if (cart.items.length === 0) return;
        if (confirm('Clear all items from the cart?')) {
            cart.items = [];
            cart.discount = { type: 'none', value: 0 };
            document.getElementById('discountValueInput').value = '';
            updateCartUI();
            showToast('Cart cleared', 'info');
        }
    }

    // ─── Totals ─────────────────────────────────────────────────
    function calculateSubtotal() {
        return cart.items.reduce((sum, i) => sum + i.total, 0);
    }

    function calculateDiscount(subtotal) {
        if (cart.discount.type === 'percent') {
            return subtotal * (Math.min(cart.discount.value, 100) / 100);
        } else if (cart.discount.type === 'fixed') {
            return Math.min(cart.discount.value, subtotal);
        }
        return 0;
    }

    function calculateTotals() {
        const subtotal = calculateSubtotal();
        const discount = calculateDiscount(subtotal);
        const taxable = subtotal - discount;
        const tax = taxable * TAX_RATE;
        const grandTotal = taxable + tax;
        return { subtotal, discount, tax, grandTotal };
    }

    // ─── UI Updates ─────────────────────────────────────────────
    function updateCartUI() {
        renderCartItems();
        updateCartSummary();
        updateCartBadge();
    }

    function updateCartBadge() {
        const badge = document.getElementById('cartCountBadge');
        if (badge) {
            const count = cart.items.reduce((sum, i) => sum + i.qty, 0);
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'flex';
        }
    }

    function renderCartItems() {
        const container = document.getElementById('cartItemsList');
        const emptyEl = document.getElementById('cartEmpty');

        if (cart.items.length === 0) {
            container.innerHTML = '';
            container.appendChild(createEmptyState());
            return;
        }

        container.innerHTML = cart.items.map(item => `
            <div class="pos-cart-item" data-item-id="${item.id}">
                <div class="item-icon"><i class="ri-capsule-line"></i></div>
                <div class="item-info">
                    <div class="item-name">${escapeHtml(item.name)}</div>
                    <div class="item-price">${formatCurrency(item.price)} / ${item.unit}</div>
                </div>
                <div class="item-qty">
                    <button class="qty-btn" onclick="POS.decrementQty(${item.id})">-</button>
                    <span class="qty-value">${item.qty}</span>
                    <button class="qty-btn" onclick="POS.incrementQty(${item.id})">+</button>
                </div>
                <div class="item-total">${formatCurrency(item.total)}</div>
                <button class="item-remove" onclick="POS.removeItem(${item.id})" title="Remove">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        `).join('');
    }

    function createEmptyState() {
        const div = document.createElement('div');
        div.className = 'pos-cart-empty';
        div.id = 'cartEmpty';
        div.innerHTML = `
            <div class="empty-icon"><i class="ri-shopping-cart-line"></i></div>
            <h4>Cart is empty</h4>
            <p>Search or scan products to add</p>
        `;
        return div;
    }

    function updateCartSummary() {
        const totals = calculateTotals();

        document.getElementById('subtotalValue').textContent = formatCurrency(totals.subtotal);
        document.getElementById('taxValue').textContent = formatCurrency(totals.tax);
        document.getElementById('grandTotalValue').textContent = formatCurrency(totals.grandTotal);

        const discountRow = document.getElementById('discountRow');
        if (totals.discount > 0) {
            discountRow.style.display = 'flex';
            const discountLabel = cart.discount.type === 'percent' ? `Discount (${cart.discount.value}%)` : 'Discount';
            discountRow.querySelector('.label').textContent = discountLabel;
            document.getElementById('discountValue').textContent = '-' + formatCurrency(totals.discount);
        } else {
            discountRow.style.display = 'none';
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ─── Discount ───────────────────────────────────────────────
    function applyDiscount() {
        const type = document.getElementById('discountType').value;
        const value = parseFloat(document.getElementById('discountValueInput').value);

        if (isNaN(value) || value <= 0) {
            showToast('Enter a valid discount value', 'warning');
            return;
        }

        if (type === 'percent' && value > 100) {
            showToast('Discount cannot exceed 100%', 'warning');
            return;
        }

        cart.discount = { type, value };
        updateCartUI();
        showToast('Discount applied: ' + (type === 'percent' ? value + '%' : formatCurrency(value)), 'success');
    }

    function removeDiscount() {
        cart.discount = { type: 'none', value: 0 };
        document.getElementById('discountValueInput').value = '';
        updateCartUI();
    }

    // ─── Payment Method ─────────────────────────────────────────
    function setPaymentMethod(method) {
        cart.paymentMethod = method;
        document.querySelectorAll('.payment-method-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.method === method);
        });
    }

    // ─── Complete Sale ──────────────────────────────────────────
    function completeSale() {
        if (cart.items.length === 0) {
            showToast('Add items to cart first', 'warning');
            return;
        }

        if (cart.paymentMethod === 'cash') {
            openCashModal();
        } else {
            processSale(null);
        }
    }

    function openCashModal() {
        const totals = calculateTotals();
        const modal = document.getElementById('cashModal');
        document.getElementById('cashAmountDue').textContent = formatCurrency(totals.grandTotal);
        document.getElementById('cashTenderedInput').value = '';
        document.getElementById('changeDisplay').textContent = formatCurrency(0);

        // Generate quick amount buttons
        const quickContainer = document.getElementById('quickAmounts');
        const grandTotal = totals.grandTotal;
        const quickAmounts = [
            Math.ceil(grandTotal),
            Math.ceil(grandTotal / 5000) * 5000,
            Math.ceil(grandTotal / 10000) * 10000,
            Math.ceil(grandTotal / 20000) * 20000,
            Math.ceil(grandTotal / 50000) * 50000,
        ].filter((v, i, a) => a.indexOf(v) === i && v >= grandTotal).slice(0, 5);

        quickContainer.innerHTML = quickAmounts.map(amt =>
            `<button onclick="POS.setCashAmount(${amt})">${formatCurrency(amt)}</button>`
        ).join('');

        // Add exact amount button
        quickContainer.innerHTML = `<button onclick="POS.setCashAmount(${grandTotal})">Exact</button>` + quickContainer.innerHTML;

        modal.classList.add('active');
        setTimeout(() => document.getElementById('cashTenderedInput').focus(), 200);
    }

    function closeCashModal() {
        document.getElementById('cashModal').classList.remove('active');
    }

    function setCashAmount(amount) {
        document.getElementById('cashTenderedInput').value = amount;
        updateChangeDisplay();
    }

    function updateChangeDisplay() {
        const totals = calculateTotals();
        const tendered = parseFloat(document.getElementById('cashTenderedInput').value) || 0;
        const change = tendered - totals.grandTotal;
        const display = document.getElementById('changeDisplay');

        display.textContent = formatCurrency(Math.abs(change));
        if (change >= 0) {
            display.className = 'change-value sufficient';
            display.textContent = formatCurrency(change);
        } else {
            display.className = 'change-value insufficient';
            display.textContent = '-' + formatCurrency(Math.abs(change));
        }
    }

    function processSale(cashTendered) {
        const totals = calculateTotals();

        const saleData = {
            _token: document.querySelector('meta[name="csrf-token"]')?.content || '',
            items: cart.items.map(i => ({
                medicine_id: i.id,
                name: i.name,
                quantity: i.qty,
                unit_price: i.price,
                total_price: i.total
            })),
            customer_id: cart.customerId || null,
            payment_method: cart.paymentMethod,
            discount_type: cart.discount.type,
            discount_value: cart.discount.value,
            discount_amount: totals.discount,
            subtotal: totals.subtotal,
            tax_amount: totals.tax,
            grand_total: totals.grandTotal,
            cash_tendered: cashTendered,
            change_amount: cashTendered ? cashTendered - totals.grandTotal : 0
        };

        // Show loading
        showLoading();

        fetch('/api/sales/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saleData)
        })
        .then(r => r.json())
        .then(result => {
            hideLoading();

            if (result.success) {
                playSuccessSound();
                showToast('Sale completed successfully!', 'success');

                // Show receipt
                showReceiptModal(result.data || result, saleData, cashTendered);

                // Clear cart
                cart.items = [];
                cart.discount = { type: 'none', value: 0 };
                document.getElementById('discountValueInput').value = '';
                updateCartUI();
                closeCashModal();
            } else {
                showToast(result.message || 'Sale failed. Please try again.', 'error');
                playErrorSound();
            }
        })
        .catch(err => {
            hideLoading();
            showToast('Network error. Please try again.', 'error');
            playErrorSound();
            console.error('Sale error:', err);
        });
    }

    // ─── Receipt Modal ──────────────────────────────────────────
    function showReceiptModal(saleResult, saleData, cashTendered) {
        const totals = calculateTotals();
        const change = cashTendered ? cashTendered - (saleData.grand_total || totals.grandTotal) : 0;
        const invoiceNumber = saleResult.invoice_number || 'N/A';
        const now = new Date();

        const receiptHtml = `
            <div style="padding: 24px; font-family: 'Inter', monospace; font-size: 12px;" id="printableReceipt">
                <div style="text-align: center; margin-bottom: 16px;">
                    <h3 style="margin:0 0 4px; font-size:16px; color:var(--primary-dark);"><?= sanitize($pharmacyName) ?></h3>
                    <?php if ($pharmacyAddress): ?><p style="margin:0; font-size:11px; color:var(--text-muted);"><?= sanitize($pharmacyAddress) ?></p><?php endif; ?>
                    <?php if ($pharmacyPhone): ?><p style="margin:0; font-size:11px; color:var(--text-muted);">Tel: <?= sanitize($pharmacyPhone) ?></p><?php endif; ?>
                </div>
                <div style="border-top: 1px dashed var(--border-color); margin: 12px 0;"></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Invoice:</span><strong>${invoiceNumber}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Date:</span><span>${now.toLocaleDateString()} ${now.toLocaleTimeString()}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Customer:</span><span>${escapeHtml(cart.customerName)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Payment:</span><span style="text-transform:capitalize;">${cart.paymentMethod}</span>
                </div>
                <div style="border-top: 1px dashed var(--border-color); margin: 12px 0;"></div>
                ${(saleData.items || []).map(item => `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                        <span>${escapeHtml(item.name)} x${item.quantity}</span>
                        <span>${formatCurrency(item.total_price)}</span>
                    </div>
                `).join('')}
                <div style="border-top: 1px dashed var(--border-color); margin: 12px 0;"></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Subtotal:</span><span>${formatCurrency(saleData.subtotal || totals.subtotal)}</span>
                </div>
                ${(saleData.discount_amount > 0) ? `
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px; color:var(--danger);">
                    <span>Discount:</span><span>-${formatCurrency(saleData.discount_amount)}</span>
                </div>` : ''}
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Tax (<?= $taxRate ?>%):</span><span>${formatCurrency(saleData.tax_amount || totals.tax)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size:14px; font-weight:800; margin-top:8px; padding-top:8px; border-top:2px solid var(--text-primary);">
                    <span>TOTAL:</span><span style="color:var(--primary-dark);">${formatCurrency(saleData.grand_total || totals.grandTotal)}</span>
                </div>
                ${cashTendered ? `
                <div style="border-top: 1px dashed var(--border-color); margin: 12px 0;"></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Cash Tendered:</span><span>${formatCurrency(cashTendered)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Change:</span><span style="color:var(--success); font-weight:700;">${formatCurrency(change)}</span>
                </div>` : ''}
                <div style="border-top: 1px dashed var(--border-color); margin: 16px 0;"></div>
                <div style="text-align: center; color:var(--text-muted); font-size:11px;">
                    <p style="margin:0;">Thank you for your purchase!</p>
                    <p style="margin:4px 0 0; font-size:10px;"><?= sanitize($pharmacyName) ?></p>
                </div>
            </div>
        `;

        document.getElementById('receiptContent').innerHTML = receiptHtml;
        document.getElementById('receiptModal').classList.add('active');
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.remove('active');
    }

    function printReceipt() {
        const content = document.getElementById('printableReceipt');
        if (!content) return;

        const printWindow = window.open('', '_blank', 'width=320,height=600');
        printWindow.document.write(`
            <html><head><title>Receipt</title>
            <style>
                body { font-family: 'Courier New', monospace; font-size: 12px; margin: 10px; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
            </style>
            </head><body>${content.innerHTML}</body></html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 300);
    }

    // ─── Hold / Recall Cart ─────────────────────────────────────
    function holdCart() {
        if (cart.items.length === 0) {
            showToast('Cannot hold an empty cart', 'warning');
            return;
        }

        const name = prompt('Enter a name for this held order:', cart.customerName);
        if (!name) return;

        cart.heldCarts.push({
            id: Date.now().toString(),
            name: name,
            items: [...cart.items],
            discount: { ...cart.discount },
            paymentMethod: cart.paymentMethod,
            timestamp: new Date().toISOString()
        });

        cart.items = [];
        cart.discount = { type: 'none', value: 0 };
        document.getElementById('discountValueInput').value = '';
        updateCartUI();
        updateHeldCartsUI();
        showToast('Cart held for ' + name, 'success');
    }

    function recallCart(heldCartId) {
        const index = cart.heldCarts.findIndex(hc => hc.id === heldCartId);
        if (index === -1) return;

        if (cart.items.length > 0) {
            if (!confirm('Current cart has items. Replace with held order?')) return;
        }

        const held = cart.heldCarts[index];
        cart.items = [...held.items];
        cart.discount = { ...held.discount };
        cart.paymentMethod = held.paymentMethod;
        cart.customerName = held.name;

        cart.heldCarts.splice(index, 1);
        updateCartUI();
        updateHeldCartsUI();
        showToast('Cart recalled for ' + held.name, 'success');
    }

    function updateHeldCartsUI() {
        const section = document.getElementById('heldCartsSection');
        const list = document.getElementById('heldCartsList');

        if (cart.heldCarts.length === 0) {
            section.classList.remove('has-items');
            list.innerHTML = '<p style="font-size:0.78rem; color:var(--text-muted); text-align:center; padding:8px 0;">No held orders</p>';
            return;
        }

        section.classList.add('has-items');
        list.innerHTML = cart.heldCarts.map(hc => `
            <div class="held-cart-item" onclick="POS.recallCart('${hc.id}')">
                <div>
                    <div class="held-name">${escapeHtml(hc.name)}</div>
                    <div class="held-meta">${hc.items.length} item(s)</div>
                </div>
                <i class="ri-arrow-go-back-line" style="color:var(--primary);"></i>
            </div>
        `).join('');
    }

    // ─── Search & Filter ────────────────────────────────────────
    function initSearch() {
        const searchInput = document.getElementById('pos-search-input');
        if (!searchInput) return;

        let timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => filterProducts(this.value.trim()), 250);
        });
    }

    function filterProducts(query) {
        const cards = document.querySelectorAll('.pos-product-card');
        let visible = 0;

        cards.forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const match = !query || name.includes(query.toLowerCase());
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
    }

    function filterByCategory(categoryId) {
        const cards = document.querySelectorAll('.pos-product-card');

        cards.forEach(card => {
            if (categoryId === 0 || card.dataset.category === String(categoryId)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // ─── Audio ──────────────────────────────────────────────────
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;

    function getAudioCtx() {
        if (!audioCtx && AudioCtx) audioCtx = new AudioCtx();
        return audioCtx;
    }

    function playBeep(freq, dur, vol) {
        try {
            const ctx = getAudioCtx();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            gain.gain.value = vol;
            osc.start();
            setTimeout(() => osc.stop(), dur);
        } catch(e) {}
    }

    function playAddSound() { playBeep(800, 60, 0.1); }
    function playErrorSound() { playBeep(300, 200, 0.12); }
    function playSuccessSound() {
        playBeep(700, 80, 0.1);
        setTimeout(() => playBeep(1000, 80, 0.1), 120);
        setTimeout(() => playBeep(1400, 120, 0.12), 240);
    }

    // ─── Toast Notification ─────────────────────────────────────
    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast({
                type: type || 'info',
                message: message,
                title: type === 'success' ? 'Success' : type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Info'
            });
            return;
        }

        // Fallback toast
        const container = document.getElementById('toastContainer') || document.body;
        const toast = document.createElement('div');
        const colors = {
            success: '#7CB342',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };
        const bgColors = {
            success: 'rgba(124, 179, 66, 0.1)',
            error: 'rgba(231, 76, 60, 0.1)',
            warning: 'rgba(243, 156, 18, 0.1)',
            info: 'rgba(52, 152, 219, 0.1)'
        };

        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 14px 20px; border-radius: 10px;
            background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e0e0e0);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            font-family: 'Inter', sans-serif; font-size: 0.88rem; font-weight: 500;
            color: var(--text-primary, #2d3436);
            border-left: 4px solid ${colors[type] || colors.info};
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ─── Loading ────────────────────────────────────────────────
    function showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.style.display = 'none';
    }

    // ─── Fullscreen Toggle ──────────────────────────────────────
    function toggleFullscreen() {
        document.body.classList.toggle('pos-fullscreen');
        const icon = document.querySelector('#fullscreenToggle i');
        if (document.body.classList.contains('pos-fullscreen')) {
            icon.className = 'ri-fullscreen-exit-line';
        } else {
            icon.className = 'ri-fullscreen-line';
        }
    }

    // ─── Keyboard Shortcuts ─────────────────────────────────────
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('pos-search-input')?.focus();
            }
            if (e.key === 'F4') {
                e.preventDefault();
                completeSale();
            }
            if (e.key === 'F6') {
                e.preventDefault();
                holdCart();
            }
            if (e.key === 'F8') {
                e.preventDefault();
                clearCart();
            }
            if (e.key === 'F9') {
                e.preventDefault();
                printReceipt();
            }
            if (e.key === 'Escape') {
                closeCashModal();
                closeReceiptModal();
            }
        });
    }

    // ─── Barcode Scanner Support ────────────────────────────────
    let barcodeBuffer = '';
    let barcodeTimeout = null;

    function initBarcodeScanner() {
        document.addEventListener('keypress', function(e) {
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;

            clearTimeout(barcodeTimeout);
            barcodeBuffer += e.key;

            barcodeTimeout = setTimeout(() => {
                if (barcodeBuffer.length >= 3) {
                    processBarcodeScan(barcodeBuffer.trim());
                }
                barcodeBuffer = '';
            }, 100);
        });
    }

    function processBarcodeScan(barcode) {
        playBeep(1200, 80, 0.1);
        // Search for product matching barcode
        const cards = document.querySelectorAll('.pos-product-card');
        for (const card of cards) {
            const name = card.dataset.name || '';
            if (name.toLowerCase().includes(barcode.toLowerCase())) {
                addItemFromCard(card);
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('just-added');
                setTimeout(() => card.classList.remove('just-added'), 400);
                return;
            }
        }
        showToast('Product not found: ' + barcode, 'error');
        playErrorSound();
    }

    // ─── Load More Products ─────────────────────────────────────
    let currentPage = 1;
    function loadMore() {
        showToast('Loading more products...', 'info');
        // In a real app, fetch next page via AJAX
    }

    // ─── Init ───────────────────────────────────────────────────
    function init() {
        initSearch();
        initKeyboardShortcuts();
        initBarcodeScanner();
        updateCartUI();

        // Category tabs
        document.querySelectorAll('.pos-category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.pos-category-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterByCategory(parseInt(this.dataset.category));
            });
        });

        // Customer select
        const customerSelect = document.getElementById('customerSelect');
        if (customerSelect) {
            customerSelect.addEventListener('change', function() {
                cart.customerId = this.value || null;
                cart.customerName = this.options[this.selectedIndex].text;
            });
        }

        // Payment method buttons
        document.querySelectorAll('.payment-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                setPaymentMethod(this.dataset.method);
            });
        });

        // Complete sale button
        document.getElementById('completeSaleBtn')?.addEventListener('click', completeSale);
        document.getElementById('holdCartBtn')?.addEventListener('click', holdCart);
        document.getElementById('clearCartBtn')?.addEventListener('click', clearCart);
        document.getElementById('hold-cart-btn')?.addEventListener('click', holdCart);
        document.getElementById('clear-cart-btn')?.addEventListener('click', clearCart);

        // Discount
        document.getElementById('applyDiscountBtn')?.addEventListener('click', applyDiscount);

        // Cash modal
        document.getElementById('cashTenderedInput')?.addEventListener('input', updateChangeDisplay);
        document.getElementById('confirmCashSale')?.addEventListener('click', function() {
            const totals = calculateTotals();
            const tendered = parseFloat(document.getElementById('cashTenderedInput').value);
            if (isNaN(tendered) || tendered < totals.grandTotal) {
                showToast('Insufficient cash amount', 'error');
                return;
            }
            processSale(tendered);
        });

        // Fullscreen toggle
        document.getElementById('fullscreenToggle')?.addEventListener('click', toggleFullscreen);

        // Product card click to add
        document.querySelectorAll('.pos-product-card:not(.out-of-stock)').forEach(card => {
            card.addEventListener('click', function() {
                addItemFromCard(this);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', init);

    // Public API
    return {
        addItemFromCard,
        removeItem,
        incrementQty,
        decrementQty,
        clearCart,
        holdCart,
        recallCart,
        completeSale,
        setPaymentMethod,
        applyDiscount,
        removeDiscount,
        closeCashModal,
        closeReceiptModal,
        printReceipt,
        setCashAmount,
        loadMore,
        getCart: () => ({ ...cart, items: [...cart.items] })
    };
})();
</script>

<style>
    /* Toast animation keyframes */
    @keyframes slideInRight {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; transform: translateY(-10px); }
    }
</style>

</body>
</html>
