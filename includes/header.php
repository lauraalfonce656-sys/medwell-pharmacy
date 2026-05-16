<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$currentUser = getCurrentUser();
$settings = getSettings();
$notificationCount = (new Notification())->getUnreadCount(getCurrentUserId());
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MedWell Pharmacy Management System">
    <meta name="author" content="MedWell">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | MedWell' : 'MedWell Pharmacy'; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/favicon.png">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --mw-primary: #7CB342;
            --mw-primary-dark: #689F38;
            --mw-primary-light: #9CCC65;
            --mw-primary-bg: rgba(124, 179, 66, 0.08);
            --mw-primary-bg-hover: rgba(124, 179, 66, 0.15);
            --mw-sidebar-width: 260px;
            --mw-sidebar-collapsed: 72px;
            --mw-navbar-height: 64px;
            --mw-body-bg: #f5f7fa;
            --mw-card-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --mw-card-shadow-hover: 0 10px 25px rgba(0,0,0,0.08);
            --mw-border-color: #e8ecf1;
            --mw-text-primary: #1a202c;
            --mw-text-secondary: #64748b;
            --mw-text-muted: #94a3b8;
            --mw-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-bs-theme="dark"] {
            --mw-body-bg: #0f172a;
            --mw-card-shadow: 0 1px 3px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.15);
            --mw-card-shadow-hover: 0 10px 25px rgba(0,0,0,0.3);
            --mw-border-color: #1e293b;
            --mw-text-primary: #e2e8f0;
            --mw-text-secondary: #94a3b8;
            --mw-text-muted: #64748b;
            --mw-primary-bg: rgba(124, 179, 66, 0.12);
            --mw-primary-bg-hover: rgba(124, 179, 66, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--mw-body-bg);
            color: var(--mw-text-primary);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ─── Top Navbar ─── */
        .mw-navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--mw-sidebar-width);
            height: var(--mw-navbar-height);
            background: #ffffff;
            border-bottom: 1px solid var(--mw-border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 24px;
            transition: var(--mw-transition);
        }

        [data-bs-theme="dark"] .mw-navbar {
            background: #1e293b;
        }

        body.sidebar-collapsed .mw-navbar {
            left: var(--mw-sidebar-collapsed);
        }

        @media (max-width: 991.98px) {
            .mw-navbar {
                left: 0 !important;
            }
        }

        /* Hamburger Toggle */
        .mw-sidebar-toggle {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--mw-text-secondary);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--mw-transition);
            font-size: 20px;
            flex-shrink: 0;
        }

        .mw-sidebar-toggle:hover {
            background: var(--mw-primary-bg);
            color: var(--mw-primary);
        }

        /* Search Bar */
        .mw-search-wrapper {
            flex: 1;
            max-width: 480px;
            margin: 0 24px;
            position: relative;
        }

        .mw-search-wrapper .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mw-text-muted);
            font-size: 16px;
            transition: var(--mw-transition);
            pointer-events: none;
        }

        .mw-search-input {
            width: 100%;
            height: 42px;
            padding: 0 16px 0 42px;
            border: 1.5px solid var(--mw-border-color);
            border-radius: 12px;
            background: var(--mw-body-bg);
            color: var(--mw-text-primary);
            font-size: 14px;
            font-weight: 400;
            transition: var(--mw-transition);
            outline: none;
        }

        .mw-search-input::placeholder {
            color: var(--mw-text-muted);
        }

        .mw-search-input:focus {
            border-color: var(--mw-primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px var(--mw-primary-bg);
        }

        .mw-search-input:focus + .search-icon,
        .mw-search-input:focus ~ .search-icon {
            color: var(--mw-primary);
        }

        .mw-search-shortcut {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--mw-border-color);
            color: var(--mw-text-muted);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            pointer-events: none;
        }

        @media (max-width: 767.98px) {
            .mw-search-wrapper {
                display: none;
            }
            .mw-search-wrapper.show-mobile {
                display: block;
                position: absolute;
                top: var(--mw-navbar-height);
                left: 0;
                right: 0;
                max-width: 100%;
                margin: 0;
                padding: 12px 16px;
                background: #ffffff;
                border-bottom: 1px solid var(--mw-border-color);
                z-index: 1029;
            }
            [data-bs-theme="dark"] .mw-search-wrapper.show-mobile {
                background: #1e293b;
            }
            .mw-search-shortcut {
                display: none;
            }
        }

        /* Mobile search toggle */
        .mw-mobile-search-btn {
            display: none;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--mw-text-secondary);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--mw-transition);
            font-size: 18px;
        }

        .mw-mobile-search-btn:hover {
            background: var(--mw-primary-bg);
            color: var(--mw-primary);
        }

        @media (max-width: 767.98px) {
            .mw-mobile-search-btn {
                display: flex;
            }
        }

        /* Right Section */
        .mw-navbar-right {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            flex-shrink: 0;
        }

        .mw-nav-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: var(--mw-text-secondary);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--mw-transition);
            font-size: 18px;
            position: relative;
        }

        .mw-nav-btn:hover {
            background: var(--mw-primary-bg);
            color: var(--mw-primary);
        }

        /* Theme Toggle */
        .mw-theme-toggle .ri-sun-line,
        .mw-theme-toggle .fa-sun {
            display: none;
        }

        [data-bs-theme="dark"] .mw-theme-toggle .ri-sun-line,
        [data-bs-theme="dark"] .mw-theme-toggle .fa-sun {
            display: inline-block;
        }

        [data-bs-theme="dark"] .mw-theme-toggle .ri-moon-line,
        [data-bs-theme="dark"] .mw-theme-toggle .fa-moon {
            display: none;
        }

        [data-bs-theme="light"] .mw-theme-toggle .ri-moon-line,
        [data-bs-theme="light"] .mw-theme-toggle .fa-moon {
            display: inline-block;
        }

        /* Notification Badge */
        .mw-notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            min-width: 18px;
            height: 18px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            line-height: 1;
            border: 2px solid #ffffff;
        }

        [data-bs-theme="dark"] .mw-notification-badge {
            border-color: #1e293b;
        }

        /* Notification Dropdown */
        .mw-notification-dropdown {
            width: 360px;
            max-height: 420px;
            overflow: hidden;
            border: 1px solid var(--mw-border-color);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            padding: 0;
        }

        @media (max-width: 400px) {
            .mw-notification-dropdown {
                width: 300px;
            }
        }

        .mw-notification-dropdown .dropdown-header {
            background: var(--mw-primary-bg);
            color: var(--mw-primary-dark);
            padding: 14px 18px;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid var(--mw-border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mw-notification-dropdown .dropdown-header .mark-all-read {
            font-size: 12px;
            color: var(--mw-primary);
            cursor: pointer;
            font-weight: 500;
            background: none;
            border: none;
            padding: 0;
        }

        .mw-notification-dropdown .dropdown-header .mark-all-read:hover {
            text-decoration: underline;
        }

        .mw-notification-list {
            max-height: 310px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .mw-notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 18px;
            border-bottom: 1px solid var(--mw-border-color);
            cursor: pointer;
            transition: var(--mw-transition);
        }

        .mw-notification-item:hover {
            background: var(--mw-primary-bg);
        }

        .mw-notification-item.unread {
            background: rgba(124, 179, 66, 0.04);
        }

        .mw-notification-item .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .mw-notification-item .notif-icon.bg-primary-light {
            background: var(--mw-primary-bg);
            color: var(--mw-primary);
        }

        .mw-notification-item .notif-content {
            flex: 1;
            min-width: 0;
        }

        .mw-notification-item .notif-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--mw-text-primary);
            margin-bottom: 2px;
        }

        .mw-notification-item .notif-text {
            font-size: 12px;
            color: var(--mw-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mw-notification-item .notif-time {
            font-size: 11px;
            color: var(--mw-text-muted);
            margin-top: 2px;
        }

        .mw-notification-dropdown .dropdown-footer {
            padding: 10px;
            text-align: center;
            border-top: 1px solid var(--mw-border-color);
        }

        .mw-notification-dropdown .dropdown-footer a {
            color: var(--mw-primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .mw-notification-dropdown .dropdown-footer a:hover {
            color: var(--mw-primary-dark);
        }

        /* User Dropdown */
        .mw-user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 8px 4px 4px;
            border: 1.5px solid var(--mw-border-color);
            border-radius: 12px;
            background: transparent;
            cursor: pointer;
            transition: var(--mw-transition);
            margin-left: 6px;
        }

        .mw-user-btn:hover {
            border-color: var(--mw-primary);
            background: var(--mw-primary-bg);
        }

        .mw-user-avatar {
            width: 34px;
            height: 34px;
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

        .mw-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mw-user-info {
            text-align: left;
            line-height: 1.2;
        }

        .mw-user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--mw-text-primary);
        }

        .mw-user-role {
            font-size: 11px;
            color: var(--mw-primary);
            font-weight: 500;
        }

        .mw-user-chevron {
            font-size: 14px;
            color: var(--mw-text-muted);
            transition: var(--mw-transition);
        }

        .mw-user-btn[aria-expanded="true"] .mw-user-chevron {
            transform: rotate(180deg);
        }

        @media (max-width: 575.98px) {
            .mw-user-info {
                display: none;
            }
            .mw-user-btn {
                padding: 4px;
                border-radius: 10px;
            }
        }

        .mw-user-dropdown {
            min-width: 220px;
            border: 1px solid var(--mw-border-color);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            padding: 6px;
            overflow: hidden;
        }

        .mw-user-dropdown .dropdown-item {
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--mw-text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--mw-transition);
        }

        .mw-user-dropdown .dropdown-item:hover {
            background: var(--mw-primary-bg);
            color: var(--mw-primary);
        }

        .mw-user-dropdown .dropdown-item i {
            width: 18px;
            text-align: center;
            font-size: 16px;
        }

        .mw-user-dropdown .dropdown-divider {
            border-color: var(--mw-border-color);
            margin: 4px 0;
        }

        .mw-user-dropdown .dropdown-item.text-danger:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #ef4444;
        }

        /* ─── Main Content ─── */
        .mw-main-content {
            margin-left: var(--mw-sidebar-width);
            margin-top: var(--mw-navbar-height);
            padding: 24px;
            min-height: calc(100vh - var(--mw-navbar-height));
            transition: var(--mw-transition);
        }

        body.sidebar-collapsed .mw-main-content {
            margin-left: var(--mw-sidebar-collapsed);
        }

        @media (max-width: 991.98px) {
            .mw-main-content {
                margin-left: 0 !important;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--mw-text-muted);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--mw-text-secondary);
        }
    </style>

    <!-- Page-specific CSS -->
    <?php if (isset($pageCSS)): ?>
        <?php foreach ((array)$pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     TOP NAVBAR
     ═══════════════════════════════════════════════════════════ -->
<nav class="mw-navbar">
    <!-- Sidebar Toggle -->
    <button class="mw-sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
        <i class="ri-menu-line"></i>
    </button>

    <!-- Global Search -->
    <div class="mw-search-wrapper" id="searchWrapper">
        <i class="ri-search-line search-icon"></i>
        <input type="text"
               class="mw-search-input"
               id="globalSearch"
               placeholder="Search medicines, customers, invoices..."
               autocomplete="off">
        <span class="mw-search-shortcut">Ctrl+K</span>
    </div>

    <!-- Mobile Search Toggle -->
    <button class="mw-mobile-search-btn" id="mobileSearchToggle" type="button">
        <i class="ri-search-line"></i>
    </button>

    <!-- Right Section -->
    <div class="mw-navbar-right">

        <!-- Dark/Light Mode Toggle -->
        <button class="mw-nav-btn mw-theme-toggle" id="themeToggle" type="button" title="Toggle theme">
            <i class="ri-moon-line"></i>
            <i class="ri-sun-line"></i>
        </button>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="mw-nav-btn" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="ri-notification-3-line"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="mw-notification-badge" id="notifBadge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end mw-notification-dropdown" aria-labelledby="notificationDropdown">
                <div class="dropdown-header">
                    <span>Notifications</span>
                    <button class="mark-all-read" id="markAllRead" type="button">Mark all as read</button>
                </div>
                <div class="mw-notification-list" id="notificationList">
                    <!-- Notifications loaded via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="dropdown-footer">
                    <a href="<?php echo BASE_URL; ?>/notifications.php">View All Notifications</a>
                </div>
            </div>
        </div>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="mw-user-btn" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="mw-user-avatar">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?php echo BASE_URL . '/uploads/avatars/' . $currentUser['avatar']; ?>" alt="<?php echo htmlspecialchars($currentUser['full_name']); ?>">
                    <?php else: ?>
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="mw-user-info">
                    <div class="mw-user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    <div class="mw-user-role"><?php echo htmlspecialchars(ucfirst($currentUser['role'])); ?></div>
                </div>
                <i class="ri-arrow-down-s-line mw-user-chevron"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end mw-user-dropdown" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php">
                    <i class="ri-user-line"></i> My Profile
                </a>
                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/settings.php">
                    <i class="ri-settings-3-line"></i> Settings
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                    <i class="ri-logout-box-r-line"></i> Logout
                </a>
            </div>
        </div>

    </div>
</nav>
