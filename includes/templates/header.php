<?php
/**
 * MedWell Pharmacy - Header Template
 * 
 * Top header bar with search, notifications, dark mode toggle, and user menu.
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
$unreadNotifications = $unreadNotifications ?? 0;
?>

<!-- Top Header -->
<header class="top-header">
    <div class="header-left">
        <button class="mobile-menu-btn" aria-label="Toggle menu">
            <i class="ri-menu-line"></i>
        </button>
        <div class="search-bar">
            <i class="ri-search-line search-icon"></i>
            <input type="text" placeholder="Search medicines, customers, invoices..." autocomplete="off">
            <span class="search-shortcut">Ctrl+K</span>
        </div>
    </div>
    <div class="header-right">
        <!-- Notifications -->
        <button class="header-icon-btn" data-dropdown="notifications" title="Notifications">
            <i class="ri-notification-3-line"></i>
            <?php if ($unreadNotifications > 0): ?>
            <span class="notification-dot"></span>
            <?php endif; ?>
        </button>

        <!-- Dark Mode Toggle -->
        <button class="dark-mode-toggle" title="Toggle dark mode" onclick="MedWell.toggleTheme()"></button>

        <!-- User Dropdown -->
        <div class="user-dropdown">
            <button class="user-dropdown-toggle">
                <div class="user-avatar"><?= sanitize($userInitials) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= sanitize($user['full_name'] ?? 'User') ?></div>
                    <div class="user-role"><?= ucfirst($user['role'] ?? 'cashier') ?></div>
                </div>
                <i class="ri-arrow-down-s-line" style="color: var(--text-muted); font-size: 1.1rem;"></i>
            </button>
            <div class="user-dropdown-menu">
                <a href="/modules/settings/profile.php">
                    <i class="ri-user-line"></i> My Profile
                </a>
                <a href="/modules/settings/">
                    <i class="ri-settings-4-line"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="/logout.php" style="color: var(--danger);">
                    <i class="ri-logout-box-r-line"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
