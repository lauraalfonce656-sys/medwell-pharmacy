<?php
if (!isset($currentPage)) {
    $currentPage = '';
}
?>
<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR NAVIGATION
     ═══════════════════════════════════════════════════════════ -->
<aside class="mw-sidebar" id="sidebar">
    <!-- Sidebar Overlay (Mobile) -->
    <div class="mw-sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Content -->
    <div class="mw-sidebar-inner">

        <!-- ─── Logo Area ─── -->
        <div class="mw-sidebar-logo">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="mw-logo-link">
                <div class="mw-logo-icon">
                    <i class="ri-cross-line"></i>
                </div>
                <span class="mw-logo-text">MedWell</span>
            </a>
        </div>

        <!-- ─── Navigation ─── -->
        <nav class="mw-sidebar-nav">
            <ul class="mw-nav-list">

                <!-- MAIN -->
                <li class="mw-nav-section">
                    <span class="mw-nav-section-title">MAIN</span>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                        <i class="ri-dashboard-line"></i>
                        <span class="mw-nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- PHARMACY -->
                <li class="mw-nav-section">
                    <span class="mw-nav-section-title">PHARMACY</span>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'medicines' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/medicines.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Medicines">
                        <i class="ri-medicine-bottle-line"></i>
                        <span class="mw-nav-text">Medicines</span>
                    </a>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'categories' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/categories.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Categories">
                        <i class="ri-folder-line"></i>
                        <span class="mw-nav-text">Categories</span>
                    </a>
                </li>

                <li class="mw-nav-item mw-nav-pos <?php echo $currentPage === 'pos' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/pos.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="POS">
                        <i class="ri-shopping-cart-line"></i>
                        <span class="mw-nav-text">POS</span>
                        <span class="mw-nav-badge">SALE</span>
                    </a>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'inventory' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/inventory.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Inventory">
                        <i class="ri-archive-line"></i>
                        <span class="mw-nav-text">Inventory</span>
                    </a>
                </li>

                <!-- PEOPLE -->
                <li class="mw-nav-section">
                    <span class="mw-nav-section-title">PEOPLE</span>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'customers' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/customers.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Customers">
                        <i class="ri-user-line"></i>
                        <span class="mw-nav-text">Customers</span>
                    </a>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'suppliers' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/suppliers.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Suppliers">
                        <i class="ri-truck-line"></i>
                        <span class="mw-nav-text">Suppliers</span>
                    </a>
                </li>

                <!-- ANALYTICS -->
                <li class="mw-nav-section">
                    <span class="mw-nav-section-title">ANALYTICS</span>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/reports.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Reports">
                        <i class="ri-bar-chart-line"></i>
                        <span class="mw-nav-text">Reports</span>
                    </a>
                </li>

                <!-- SYSTEM -->
                <li class="mw-nav-section">
                    <span class="mw-nav-section-title">SYSTEM</span>
                </li>

                <li class="mw-nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>/settings.php" class="mw-nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Settings">
                        <i class="ri-settings-line"></i>
                        <span class="mw-nav-text">Settings</span>
                    </a>
                </li>

            </ul>
        </nav>

        <!-- ─── Bottom User Card ─── -->
        <div class="mw-sidebar-user">
            <div class="mw-sidebar-user-inner">
                <div class="mw-sidebar-user-avatar">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?php echo BASE_URL . '/uploads/avatars/' . $currentUser['avatar']; ?>" alt="<?php echo htmlspecialchars($currentUser['full_name']); ?>">
                    <?php else: ?>
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="mw-sidebar-user-info">
                    <div class="mw-sidebar-user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    <div class="mw-sidebar-user-role"><?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?></div>
                </div>
            </div>
        </div>

    </div>
</aside>

<style>
    /* ─── Sidebar ─── */
    .mw-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--mw-sidebar-width);
        height: 100vh;
        background: #ffffff;
        border-right: 1px solid var(--mw-border-color);
        z-index: 1040;
        transition: var(--mw-transition);
        display: flex;
        flex-direction: column;
    }

    [data-bs-theme="dark"] .mw-sidebar {
        background: #1e293b;
    }

    body.sidebar-collapsed .mw-sidebar {
        width: var(--mw-sidebar-collapsed);
    }

    /* Sidebar Overlay */
    .mw-sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: -1;
        backdrop-filter: blur(2px);
        transition: opacity 0.3s ease;
    }

    @media (max-width: 991.98px) {
        .mw-sidebar {
            transform: translateX(-100%);
            box-shadow: none;
        }
        .mw-sidebar.show {
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        .mw-sidebar.show .mw-sidebar-overlay {
            display: block;
        }
    }

    /* Sidebar Inner */
    .mw-sidebar-inner {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    /* ─── Logo ─── */
    .mw-sidebar-logo {
        height: var(--mw-navbar-height);
        display: flex;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid var(--mw-border-color);
        flex-shrink: 0;
    }

    .mw-logo-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--mw-primary);
    }

    .mw-logo-icon {
        width: 38px;
        height: 38px;
        background: var(--mw-primary);
        color: #ffffff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
        transition: var(--mw-transition);
    }

    .mw-logo-text {
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.5px;
        color: var(--mw-primary-dark);
        white-space: nowrap;
        overflow: hidden;
        transition: var(--mw-transition);
    }

    [data-bs-theme="dark"] .mw-logo-text {
        color: var(--mw-primary-light);
    }

    body.sidebar-collapsed .mw-logo-text {
        opacity: 0;
        width: 0;
        margin: 0;
    }

    body.sidebar-collapsed .mw-sidebar-logo {
        justify-content: center;
        padding: 0;
    }

    /* ─── Navigation ─── */
    .mw-sidebar-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 12px 12px;
        scrollbar-width: thin;
    }

    .mw-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    /* Section Headers */
    .mw-nav-section {
        margin-top: 16px;
        margin-bottom: 6px;
        padding: 0 12px;
    }

    .mw-nav-section:first-child {
        margin-top: 4px;
    }

    .mw-nav-section-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--mw-text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        white-space: nowrap;
        overflow: hidden;
        transition: var(--mw-transition);
    }

    body.sidebar-collapsed .mw-nav-section-title {
        opacity: 0;
        height: 0;
        margin: 0;
        overflow: hidden;
    }

    body.sidebar-collapsed .mw-nav-section {
        margin-top: 8px;
        margin-bottom: 4px;
    }

    /* Nav Items */
    .mw-nav-item {
        margin-bottom: 2px;
    }

    .mw-nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        color: var(--mw-text-secondary);
        text-decoration: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        transition: var(--mw-transition);
        white-space: nowrap;
        position: relative;
    }

    .mw-nav-link i {
        font-size: 20px;
        flex-shrink: 0;
        width: 22px;
        text-align: center;
        transition: var(--mw-transition);
    }

    .mw-nav-link:hover {
        background: var(--mw-primary-bg);
        color: var(--mw-primary-dark);
    }

    /* Active State */
    .mw-nav-item.active .mw-nav-link {
        background: var(--mw-primary);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(124, 179, 66, 0.35);
    }

    .mw-nav-item.active .mw-nav-link:hover {
        background: var(--mw-primary-dark);
        color: #ffffff;
    }

    /* POS Highlight */
    .mw-nav-pos .mw-nav-link {
        background: rgba(124, 179, 66, 0.06);
        border: 1.5px dashed rgba(124, 179, 66, 0.3);
    }

    .mw-nav-pos .mw-nav-link:hover {
        background: var(--mw-primary-bg-hover);
        border-color: var(--mw-primary);
    }

    .mw-nav-pos.active .mw-nav-link {
        background: var(--mw-primary);
        border: 1.5px solid var(--mw-primary);
    }

    /* Nav Badge */
    .mw-nav-badge {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.5px;
        background: var(--mw-primary);
        color: #ffffff;
        padding: 2px 7px;
        border-radius: 6px;
        margin-left: auto;
        line-height: 1.3;
    }

    .mw-nav-item.active .mw-nav-badge {
        background: rgba(255,255,255,0.3);
        color: #ffffff;
    }

    .mw-nav-pos .mw-nav-badge {
        background: #ef4444;
    }

    .mw-nav-pos.active .mw-nav-badge {
        background: rgba(255,255,255,0.3);
        color: #ffffff;
    }

    /* Collapsed State */
    body.sidebar-collapsed .mw-nav-text,
    body.sidebar-collapsed .mw-nav-badge {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }

    body.sidebar-collapsed .mw-nav-link {
        justify-content: center;
        padding: 10px;
    }

    body.sidebar-collapsed .mw-nav-link i {
        margin: 0;
    }

    /* Tooltips in collapsed mode */
    body.sidebar-collapsed .mw-nav-link .tooltip {
        font-size: 13px;
    }

    /* ─── Bottom User Card ─── */
    .mw-sidebar-user {
        padding: 12px;
        border-top: 1px solid var(--mw-border-color);
        flex-shrink: 0;
    }

    .mw-sidebar-user-inner {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: var(--mw-primary-bg);
        transition: var(--mw-transition);
    }

    .mw-sidebar-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: var(--mw-primary);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
        overflow: hidden;
    }

    .mw-sidebar-user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .mw-sidebar-user-info {
        overflow: hidden;
        transition: var(--mw-transition);
    }

    .mw-sidebar-user-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--mw-text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mw-sidebar-user-role {
        font-size: 11px;
        color: var(--mw-primary);
        font-weight: 500;
    }

    body.sidebar-collapsed .mw-sidebar-user-info {
        opacity: 0;
        width: 0;
    }

    body.sidebar-collapsed .mw-sidebar-user-inner {
        justify-content: center;
        padding: 10px;
    }

    body.sidebar-collapsed .mw-sidebar-user {
        padding: 12px 8px;
    }
</style>
