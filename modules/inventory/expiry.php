<?php
/**
 * MedWell Pharmacy - Expiry Management Page
 *
 * Lists medicines grouped by expiry urgency with actions:
 * - Mark as expired (removes from available stock)
 * - Extend expiry (with reason)
 * Groups: Already Expired, <7 days, <30 days, <90 days
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'inventory';

$medicineObj = new Medicine();

// Fetch all active medicines with expiry dates
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare(
    'SELECT m.*, c.name AS category_name, s.name AS supplier_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN suppliers s ON m.supplier_id = s.id
     WHERE m.is_active = 1 AND m.expiry_date IS NOT NULL
     ORDER BY m.expiry_date ASC'
);
$stmt->execute();
$allWithExpiry = $stmt->fetchAll();

// Group by urgency
$alreadyExpired = [];
$expiring7 = [];
$expiring30 = [];
$expiring90 = [];

foreach ($allWithExpiry as $med) {
    $daysLeft = calculateExpiry($med['expiry_date']);
    if ($daysLeft < 0) {
        $alreadyExpired[] = $med;
    } elseif ($daysLeft <= 7) {
        $expiring7[] = $med;
    } elseif ($daysLeft <= 30) {
        $expiring30[] = $med;
    } elseif ($daysLeft <= 90) {
        $expiring90[] = $med;
    }
}

// Handle POST actions
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $medicineId = (int) ($_POST['medicine_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($medicineId <= 0) {
            $errorMsg = 'Invalid medicine selected.';
        } else {
            $med = $medicineObj->getById($medicineId);
            if (!$med) {
                $errorMsg = 'Medicine not found.';
            } elseif ($action === 'mark_expired') {
                // Remove from available stock - set quantity to 0 and log
                $qtyBefore = (int) $med['quantity'];
                $result = $medicineObj->update($medicineId, ['quantity' => 0, 'is_active' => 0]);
                if ($result) {
                    logInventory(
                        $medicineId,
                        'expired',
                        $qtyBefore,
                        0,
                        'expiry_management',
                        null,
                        $reason ?: 'Marked as expired - removed from available stock'
                    );
                    $successMsg = sanitize($med['name']) . ' marked as expired and removed from stock.';
                } else {
                    $errorMsg = 'Failed to update medicine. Please try again.';
                }
            } elseif ($action === 'extend_expiry') {
                $newExpiry = trim($_POST['new_expiry_date'] ?? '');
                if (empty($newExpiry)) {
                    $errorMsg = 'Please provide a new expiry date.';
                } else {
                    $result = $medicineObj->update($medicineId, ['expiry_date' => $newExpiry]);
                    if ($result) {
                        logInventory(
                            $medicineId,
                            'adjustment',
                            (int) $med['quantity'],
                            (int) $med['quantity'],
                            'expiry_extension',
                            null,
                            ($reason ?: 'Expiry date extended') . ' (from ' . $med['expiry_date'] . ' to ' . $newExpiry . ')'
                        );
                        $successMsg = sanitize($med['name']) . ' expiry date extended to ' . formatDate($newExpiry) . '.';
                    } else {
                        $errorMsg = 'Failed to extend expiry. Please try again.';
                    }
                }
            }
        }
        regenerateCsrfToken();
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Expiry Management - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ── Summary Stats Row ────────────────────────────────────── */
        .expiry-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .expiry-stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .expiry-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 4px 4px 0;
        }
        .expiry-stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .expiry-stat-card.stat-expired::before { background: var(--danger); }
        .expiry-stat-card.stat-critical::before { background: #e67e22; }
        .expiry-stat-card.stat-warning::before { background: var(--warning); }
        .expiry-stat-card.stat-info::before { background: var(--info); }

        .expiry-stat-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .stat-expired .expiry-stat-icon { background: rgba(231,76,60,0.1); color: var(--danger); }
        .stat-critical .expiry-stat-icon { background: rgba(230,126,34,0.1); color: #e67e22; }
        .stat-warning .expiry-stat-icon { background: rgba(243,156,18,0.1); color: var(--warning); }
        .stat-info .expiry-stat-icon { background: rgba(52,152,219,0.1); color: var(--info); }

        .expiry-stat-content {
            flex: 1;
        }
        .expiry-stat-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .expiry-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-top: 2px;
        }
        .stat-expired .expiry-stat-value { color: var(--danger); }
        .stat-critical .expiry-stat-value { color: #e67e22; }
        .stat-warning .expiry-stat-value { color: var(--warning); }
        .stat-info .expiry-stat-value { color: var(--info); }

        /* ── Section Cards ─────────────────────────────────────────── */
        .expiry-section {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
            transition: var(--transition);
        }
        .expiry-section:hover {
            box-shadow: var(--shadow-md);
        }
        .expiry-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .expiry-section-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .expiry-section-header .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            padding: 0 8px;
            border-radius: 13px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        /* Section themes */
        .section-expired .expiry-section-header {
            background: rgba(231,76,60,0.04);
            border-bottom-color: rgba(231,76,60,0.15);
        }
        .section-expired .expiry-section-header h3 { color: var(--danger); }
        .section-expired .count-badge { background: rgba(231,76,60,0.12); color: var(--danger); }

        .section-critical .expiry-section-header {
            background: rgba(230,126,34,0.04);
            border-bottom-color: rgba(230,126,34,0.15);
        }
        .section-critical .expiry-section-header h3 { color: #e67e22; }
        .section-critical .count-badge { background: rgba(230,126,34,0.12); color: #e67e22; }

        .section-warning .expiry-section-header {
            background: rgba(243,156,18,0.04);
            border-bottom-color: rgba(243,156,18,0.15);
        }
        .section-warning .expiry-section-header h3 { color: var(--warning); }
        .section-warning .count-badge { background: rgba(243,156,18,0.12); color: var(--warning); }

        .section-info .expiry-section-header {
            background: rgba(52,152,219,0.04);
            border-bottom-color: rgba(52,152,219,0.15);
        }
        .section-info .expiry-section-header h3 { color: var(--info); }
        .section-info .count-badge { background: rgba(52,152,219,0.12); color: var(--info); }

        .expiry-section-body {
            padding: 0;
        }

        /* Medicine item row */
        .expiry-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto auto auto;
            gap: 16px;
            align-items: center;
            padding: 14px 22px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .expiry-item:last-child {
            border-bottom: none;
        }
        .expiry-item:hover {
            background: var(--primary-50);
        }
        .expiry-item-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .expiry-item-detail {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .expiry-item-batch {
            font-size: 0.84rem;
            color: var(--text-secondary);
        }
        .expiry-item-batch code {
            background: var(--primary-50);
            color: var(--primary-dark);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .expiry-item-date {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }
        .expiry-item-days {
            text-align: center;
            min-width: 60px;
        }
        .days-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .days-badge.days-expired { background: rgba(231,76,60,0.1); color: var(--danger); }
        .days-badge.days-critical { background: rgba(230,126,34,0.1); color: #e67e22; }
        .days-badge.days-warning { background: rgba(243,156,18,0.1); color: var(--warning); }
        .days-badge.days-safe { background: rgba(52,152,219,0.1); color: var(--info); }
        .expiry-item-qty {
            font-size: 0.88rem;
            font-weight: 700;
            text-align: center;
            min-width: 40px;
        }
        .expiry-item-actions {
            display: flex;
            gap: 6px;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.78rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            font-family: var(--font-sans);
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        .action-btn.btn-mark-expired {
            color: var(--danger);
            border-color: rgba(231,76,60,0.3);
        }
        .action-btn.btn-mark-expired:hover {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
        }
        .action-btn.btn-extend {
            color: var(--info);
            border-color: rgba(52,152,219,0.3);
        }
        .action-btn.btn-extend:hover {
            background: var(--info);
            color: #fff;
            border-color: var(--info);
        }

        /* Empty state */
        .section-empty {
            padding: 24px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.88rem;
        }
        .section-empty i {
            font-size: 1.8rem;
            opacity: 0.3;
            display: block;
            margin-bottom: 8px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 20px;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-dialog {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: var(--transition);
        }
        .modal-overlay.active .modal-dialog {
            transform: scale(1) translateY(0);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        .modal-close:hover {
            background: var(--primary-50);
            color: var(--danger);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
        }

        /* Alert messages */
        .alert-card {
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.88rem;
        }
        .alert-card.alert-success-card {
            background: rgba(39,174,96,0.08);
            border: 1px solid rgba(39,174,96,0.2);
            color: #27ae60;
        }
        .alert-card.alert-error-card {
            background: rgba(231,76,60,0.08);
            border: 1px solid rgba(231,76,60,0.2);
            color: #e74c3c;
        }
        .alert-card i { font-size: 1.2rem; flex-shrink: 0; margin-top: 1px; }

        /* Footer */
        .app-footer {
            margin-top: 32px;
            padding: 18px 0;
            border-top: 1px solid var(--border-color);
        }
        .app-footer .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .app-footer .footer-content p { margin: 0; }
        .app-footer .footer-version { color: var(--text-muted); font-size: 0.76rem; }

        @media (max-width: 992px) {
            .expiry-stats-row { grid-template-columns: repeat(2, 1fr); }
            .expiry-item {
                grid-template-columns: 1fr auto;
                gap: 8px;
            }
            .expiry-item-batch,
            .expiry-item-date {
                display: none;
            }
            .expiry-item-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
            }
        }
        @media (max-width: 576px) {
            .expiry-stats-row { grid-template-columns: 1fr; }
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

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="ri-calendar-close-line" style="color: var(--primary); margin-right: 8px;"></i>Expiry Management</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <a href="/modules/inventory/">Inventory</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Expiry Management</span>
                </div>
            </div>
            <div>
                <a href="/modules/inventory/" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Back to Inventory
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($successMsg): ?>
        <div class="alert-card alert-success-card">
            <i class="ri-checkbox-circle-line"></i>
            <div><?= $successMsg ?></div>
        </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
        <div class="alert-card alert-error-card">
            <i class="ri-error-warning-line"></i>
            <div><?= sanitize($errorMsg) ?></div>
        </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════
             SUMMARY STATS
             ═══════════════════════════════════════════════════════════ -->
        <div class="expiry-stats-row">
            <div class="expiry-stat-card stat-expired">
                <div class="expiry-stat-icon"><i class="ri-delete-bin-line"></i></div>
                <div class="expiry-stat-content">
                    <div class="expiry-stat-label">Already Expired</div>
                    <div class="expiry-stat-value"><?= count($alreadyExpired) ?></div>
                </div>
            </div>
            <div class="expiry-stat-card stat-critical">
                <div class="expiry-stat-icon"><i class="ri-alarm-warning-line"></i></div>
                <div class="expiry-stat-content">
                    <div class="expiry-stat-label">Expiring &lt;7 Days</div>
                    <div class="expiry-stat-value"><?= count($expiring7) ?></div>
                </div>
            </div>
            <div class="expiry-stat-card stat-warning">
                <div class="expiry-stat-icon"><i class="ri-time-line"></i></div>
                <div class="expiry-stat-content">
                    <div class="expiry-stat-label">Expiring &lt;30 Days</div>
                    <div class="expiry-stat-value"><?= count($expiring30) ?></div>
                </div>
            </div>
            <div class="expiry-stat-card stat-info">
                <div class="expiry-stat-icon"><i class="ri-calendar-check-line"></i></div>
                <div class="expiry-stat-content">
                    <div class="expiry-stat-label">Expiring &lt;90 Days</div>
                    <div class="expiry-stat-value"><?= count($expiring90) ?></div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             SECTION 1: ALREADY EXPIRED (Red)
             ═══════════════════════════════════════════════════════════ -->
        <div class="expiry-section section-expired">
            <div class="expiry-section-header">
                <h3>
                    <i class="ri-delete-bin-line"></i>
                    Already Expired
                    <span class="count-badge"><?= count($alreadyExpired) ?></span>
                </h3>
                <?php if (count($alreadyExpired) > 0): ?>
                <span style="font-size:0.82rem; color:var(--danger); font-weight:600;">
                    <i class="ri-alert-line"></i> Immediate action required
                </span>
                <?php endif; ?>
            </div>
            <div class="expiry-section-body">
                <?php if (empty($alreadyExpired)): ?>
                    <div class="section-empty">
                        <i class="ri-shield-check-line"></i>
                        No expired medicines found. All clear!
                    </div>
                <?php else: ?>
                    <?php foreach ($alreadyExpired as $med): ?>
                        <?php
                        $daysLeft = calculateExpiry($med['expiry_date']);
                        $daysText = abs($daysLeft) . 'd overdue';
                        ?>
                        <div class="expiry-item">
                            <div>
                                <div class="expiry-item-name"><?= sanitize($med['name']) ?></div>
                                <div class="expiry-item-detail"><?= sanitize($med['category_name'] ?? 'Uncategorized') ?> &middot; Supplier: <?= sanitize($med['supplier_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="expiry-item-batch">
                                <code><?= sanitize($med['batch_number'] ?? 'N/A') ?></code>
                            </div>
                            <div class="expiry-item-date" style="color:var(--danger);"><?= formatDate($med['expiry_date']) ?></div>
                            <div class="expiry-item-days">
                                <span class="days-badge days-expired"><i class="ri-close-circle-line"></i> <?= $daysText ?></span>
                            </div>
                            <div class="expiry-item-qty" style="color:var(--danger);"><?= (int) $med['quantity'] ?></div>
                            <div class="expiry-item-actions">
                                <button class="action-btn btn-mark-expired" onclick="openMarkExpiredModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>')">
                                    <i class="ri-delete-bin-line"></i> Mark Expired
                                </button>
                                <button class="action-btn btn-extend" onclick="openExtendModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>', '<?= $med['expiry_date'] ?>')">
                                    <i class="ri-calendar-check-line"></i> Extend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             SECTION 2: EXPIRING IN <7 DAYS (Orange)
             ═══════════════════════════════════════════════════════════ -->
        <div class="expiry-section section-critical">
            <div class="expiry-section-header">
                <h3>
                    <i class="ri-alarm-warning-line"></i>
                    Expiring in &lt;7 Days
                    <span class="count-badge"><?= count($expiring7) ?></span>
                </h3>
                <?php if (count($expiring7) > 0): ?>
                <span style="font-size:0.82rem; color:#e67e22; font-weight:600;">
                    <i class="ri-error-warning-line"></i> Critical urgency
                </span>
                <?php endif; ?>
            </div>
            <div class="expiry-section-body">
                <?php if (empty($expiring7)): ?>
                    <div class="section-empty">
                        <i class="ri-shield-check-line"></i>
                        No medicines expiring within 7 days.
                    </div>
                <?php else: ?>
                    <?php foreach ($expiring7 as $med): ?>
                        <?php $daysLeft = calculateExpiry($med['expiry_date']); ?>
                        <div class="expiry-item">
                            <div>
                                <div class="expiry-item-name"><?= sanitize($med['name']) ?></div>
                                <div class="expiry-item-detail"><?= sanitize($med['category_name'] ?? 'Uncategorized') ?> &middot; Supplier: <?= sanitize($med['supplier_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="expiry-item-batch">
                                <code><?= sanitize($med['batch_number'] ?? 'N/A') ?></code>
                            </div>
                            <div class="expiry-item-date" style="color:#e67e22;"><?= formatDate($med['expiry_date']) ?></div>
                            <div class="expiry-item-days">
                                <span class="days-badge days-critical"><i class="ri-alarm-warning-line"></i> <?= $daysLeft ?>d left</span>
                            </div>
                            <div class="expiry-item-qty" style="color:#e67e22;"><?= (int) $med['quantity'] ?></div>
                            <div class="expiry-item-actions">
                                <button class="action-btn btn-mark-expired" onclick="openMarkExpiredModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>')">
                                    <i class="ri-delete-bin-line"></i> Mark Expired
                                </button>
                                <button class="action-btn btn-extend" onclick="openExtendModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>', '<?= $med['expiry_date'] ?>')">
                                    <i class="ri-calendar-check-line"></i> Extend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             SECTION 3: EXPIRING IN <30 DAYS (Yellow)
             ═══════════════════════════════════════════════════════════ -->
        <div class="expiry-section section-warning">
            <div class="expiry-section-header">
                <h3>
                    <i class="ri-time-line"></i>
                    Expiring in &lt;30 Days
                    <span class="count-badge"><?= count($expiring30) ?></span>
                </h3>
                <?php if (count($expiring30) > 0): ?>
                <span style="font-size:0.82rem; color:var(--warning); font-weight:600;">
                    <i class="ri-information-line"></i> Needs attention
                </span>
                <?php endif; ?>
            </div>
            <div class="expiry-section-body">
                <?php if (empty($expiring30)): ?>
                    <div class="section-empty">
                        <i class="ri-shield-check-line"></i>
                        No medicines expiring within 30 days.
                    </div>
                <?php else: ?>
                    <?php foreach ($expiring30 as $med): ?>
                        <?php $daysLeft = calculateExpiry($med['expiry_date']); ?>
                        <div class="expiry-item">
                            <div>
                                <div class="expiry-item-name"><?= sanitize($med['name']) ?></div>
                                <div class="expiry-item-detail"><?= sanitize($med['category_name'] ?? 'Uncategorized') ?> &middot; Supplier: <?= sanitize($med['supplier_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="expiry-item-batch">
                                <code><?= sanitize($med['batch_number'] ?? 'N/A') ?></code>
                            </div>
                            <div class="expiry-item-date" style="color:var(--warning);"><?= formatDate($med['expiry_date']) ?></div>
                            <div class="expiry-item-days">
                                <span class="days-badge days-warning"><i class="ri-time-line"></i> <?= $daysLeft ?>d left</span>
                            </div>
                            <div class="expiry-item-qty"><?= (int) $med['quantity'] ?></div>
                            <div class="expiry-item-actions">
                                <button class="action-btn btn-mark-expired" onclick="openMarkExpiredModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>')">
                                    <i class="ri-delete-bin-line"></i> Mark Expired
                                </button>
                                <button class="action-btn btn-extend" onclick="openExtendModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>', '<?= $med['expiry_date'] ?>')">
                                    <i class="ri-calendar-check-line"></i> Extend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             SECTION 4: EXPIRING IN <90 DAYS (Info)
             ═══════════════════════════════════════════════════════════ -->
        <div class="expiry-section section-info">
            <div class="expiry-section-header">
                <h3>
                    <i class="ri-calendar-check-line"></i>
                    Expiring in &lt;90 Days
                    <span class="count-badge"><?= count($expiring90) ?></span>
                </h3>
                <?php if (count($expiring90) > 0): ?>
                <span style="font-size:0.82rem; color:var(--info); font-weight:600;">
                    <i class="ri-information-line"></i> Monitor closely
                </span>
                <?php endif; ?>
            </div>
            <div class="expiry-section-body">
                <?php if (empty($expiring90)): ?>
                    <div class="section-empty">
                        <i class="ri-shield-check-line"></i>
                        No medicines expiring within 90 days.
                    </div>
                <?php else: ?>
                    <?php foreach ($expiring90 as $med): ?>
                        <?php $daysLeft = calculateExpiry($med['expiry_date']); ?>
                        <div class="expiry-item">
                            <div>
                                <div class="expiry-item-name"><?= sanitize($med['name']) ?></div>
                                <div class="expiry-item-detail"><?= sanitize($med['category_name'] ?? 'Uncategorized') ?> &middot; Supplier: <?= sanitize($med['supplier_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="expiry-item-batch">
                                <code><?= sanitize($med['batch_number'] ?? 'N/A') ?></code>
                            </div>
                            <div class="expiry-item-date" style="color:var(--info);"><?= formatDate($med['expiry_date']) ?></div>
                            <div class="expiry-item-days">
                                <span class="days-badge days-safe"><i class="ri-calendar-check-line"></i> <?= $daysLeft ?>d left</span>
                            </div>
                            <div class="expiry-item-qty"><?= (int) $med['quantity'] ?></div>
                            <div class="expiry-item-actions">
                                <button class="action-btn btn-extend" onclick="openExtendModal(<?= $med['id'] ?>, '<?= htmlspecialchars(addslashes($med['name']), ENT_QUOTES) ?>', '<?= $med['expiry_date'] ?>')">
                                    <i class="ri-calendar-check-line"></i> Extend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Mark as Expired
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="markExpiredModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 style="color:var(--danger);"><i class="ri-delete-bin-line"></i> Mark as Expired</h3>
            <button class="modal-close" onclick="closeModal('markExpiredModal')"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="mark_expired">
            <input type="hidden" name="medicine_id" id="markExpiredMedicineId">
            <div class="modal-body">
                <div style="background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.2); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:16px;">
                    <div style="font-size:0.85rem; color:var(--danger); font-weight:600;">
                        <i class="ri-alert-line"></i> This will remove the medicine from available stock and set quantity to 0.
                    </div>
                </div>
                <p style="font-size:0.92rem; color:var(--text-primary); margin-bottom:16px;">
                    Confirm marking <strong id="markExpiredMedicineName" style="color:var(--danger);"></strong> as expired?
                </p>
                <div class="form-group">
                    <label class="form-label" for="markExpiredReason">Reason (Optional)</label>
                    <textarea class="form-control" id="markExpiredReason" name="reason" rows="3"
                              placeholder="E.g., Found expired during stock audit..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal('markExpiredModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="ri-delete-bin-line"></i> Mark as Expired
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Extend Expiry
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="extendModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 style="color:var(--info);"><i class="ri-calendar-check-line"></i> Extend Expiry Date</h3>
            <button class="modal-close" onclick="closeModal('extendModal')"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="extend_expiry">
            <input type="hidden" name="medicine_id" id="extendMedicineId">
            <div class="modal-body">
                <p style="font-size:0.92rem; color:var(--text-primary); margin-bottom:16px;">
                    Extend expiry for <strong id="extendMedicineName" style="color:var(--info);"></strong>
                </p>
                <div style="display:flex; gap:12px; align-items:center; margin-bottom:16px;">
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:0.76rem; color:var(--text-muted);">Current Expiry</label>
                        <div style="font-weight:600; color:var(--danger);" id="extendCurrentExpiry">—</div>
                    </div>
                    <i class="ri-arrow-right-line" style="color:var(--text-muted); font-size:1.2rem;"></i>
                    <div style="flex:1;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="newExpiryDate">New Expiry Date</label>
                            <input type="date" class="form-control" id="newExpiryDate" name="new_expiry_date" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="extendReason">Reason <span style="color:var(--danger);">*</span></label>
                    <textarea class="form-control" id="extendReason" name="reason" rows="3" required
                              placeholder="E.g., Manufacturer confirmed extended shelf life, Relabelled with new expiry..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModal('extendModal')">Cancel</button>
                <button type="submit" class="btn btn-info">
                    <i class="ri-calendar-check-line"></i> Extend Expiry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ─── Modal Functions ───────────────────────────────────────
function openMarkExpiredModal(medicineId, medicineName) {
    document.getElementById('markExpiredMedicineId').value = medicineId;
    document.getElementById('markExpiredMedicineName').textContent = medicineName;
    document.getElementById('markExpiredModal').classList.add('active');
}

function openExtendModal(medicineId, medicineName, currentExpiry) {
    document.getElementById('extendMedicineId').value = medicineId;
    document.getElementById('extendMedicineName').textContent = medicineName;
    document.getElementById('extendCurrentExpiry').textContent = currentExpiry;

    // Set min date for new expiry to be after current
    const newDateInput = document.getElementById('newExpiryDate');
    newDateInput.min = currentExpiry;
    newDateInput.value = '';

    document.getElementById('extendModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});
</script>
</body>
</html>
