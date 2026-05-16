<?php
/**
 * MedWell Pharmacy - Sidebar Template
 * 
 * Navigation sidebar with active state support.
 * Expects $currentPage variable for highlighting.
 */
declare(strict_types=1);

$user = getCurrentUser();
$userInitials = '';
if ($user) {
    $parts = explode(' ', $user['full_name']);
    foreach ($parts as $p) {
        $userInitials .= strtoupper(substr($p, 0, 1));
    }
    $userInitials = substr($userInitials, 0, 2);
}
$currentPage = $currentPage ?? '';
?>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class="ri-capsule-line"></i>
        </div>
        <div class="logo-text">Med<span>Well</span></div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section-title">Main</div>
        <div class="nav-item">
            <a href="/modules/dashboard/" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="ri-dashboard-3-line"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/modules/pos/" class="nav-link <?= $currentPage === 'pos' ? 'active' : '' ?>">
                <i class="ri-shopping-cart-2-line"></i>
                <span>Point of Sale</span>
            </a>
        </div>

        <!-- Pharmacy Section -->
        <div class="nav-section-title">Pharmacy</div>
        <div class="nav-item">
            <a href="/modules/medicines/" class="nav-link <?= $currentPage === 'medicines' ? 'active' : '' ?>">
                <i class="ri-medicine-bottle-line"></i>
                <span>Medicines</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/modules/categories/" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>">
                <i class="ri-archive-line"></i>
                <span>Categories</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/modules/sales/" class="nav-link <?= $currentPage === 'sales' ? 'active' : '' ?>">
                <i class="ri-receipt-line"></i>
                <span>Sales</span>
            </a>
        </div>

        <!-- People Section -->
        <div class="nav-section-title">People</div>
        <div class="nav-item">
            <a href="/modules/customers/" class="nav-link <?= $currentPage === 'customers' ? 'active' : '' ?>">
                <i class="ri-user-heart-line"></i>
                <span>Customers</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/modules/suppliers/" class="nav-link <?= $currentPage === 'suppliers' ? 'active' : '' ?>">
                <i class="ri-truck-line"></i>
                <span>Suppliers</span>
            </a>
        </div>

        <!-- Analytics Section -->
        <div class="nav-section-title">Analytics</div>
        <div class="nav-item">
            <a href="/modules/reports/" class="nav-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <i class="ri-bar-chart-2-line"></i>
                <span>Reports</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/modules/inventory/" class="nav-link <?= $currentPage === 'inventory' ? 'active' : '' ?>">
                <i class="ri-stack-line"></i>
                <span>Inventory</span>
                <?php if (!empty($lowStockCount) && $lowStockCount > 0): ?>
                <span class="nav-badge"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- System Section -->
        <div class="nav-section-title">System</div>
        <div class="nav-item">
            <a href="/modules/settings/" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="ri-settings-4-line"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" title="Toggle sidebar">
        <i class="ri-menu-fold-line"></i>
    </button>
</aside>
