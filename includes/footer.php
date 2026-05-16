        <!-- ─── Main Content Wrapper Close ─── -->
        </div><!-- /.mw-main-content -->

    </div><!-- /.page-wrapper -->

    <!-- ═══════════════════════════════════════════════════════════
         LOADING OVERLAY
         ═══════════════════════════════════════════════════════════ -->
    <div class="mw-loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="mw-loading-spinner">
            <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem; border-width: 4px;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mw-loading-text">Processing...</div>
        </div>
    </div>

    <style>
        .mw-loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease;
        }

        [data-bs-theme="dark"] .mw-loading-overlay {
            background: rgba(15, 23, 42, 0.85);
        }

        .mw-loading-spinner {
            text-align: center;
        }

        .mw-loading-text {
            margin-top: 16px;
            font-size: 14px;
            font-weight: 600;
            color: var(--mw-primary);
        }
    </style>

    <!-- ═══════════════════════════════════════════════════════════
         TOAST CONTAINER
         ═══════════════════════════════════════════════════════════ -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 9998;">
        <!-- Toasts injected here dynamically -->
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         FLASH MESSAGE DISPLAY (Auto-show toast from session)
         ═══════════════════════════════════════════════════════════ -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const flashType = '<?php echo $_SESSION['flash_type'] ?? 'success'; ?>';
                const flashMsg = <?php echo json_encode($_SESSION['flash_message']); ?>;
                const flashTitle = '<?php echo $_SESSION['flash_title'] ?? ''; ?>';

                const iconMap = {
                    success: 'ri-check-line',
                    error: 'ri-close-circle-line',
                    warning: 'ri-alert-line',
                    info: 'ri-information-line'
                };

                const bgMap = {
                    success: '#7CB342',
                    error: '#ef4444',
                    warning: '#f59e0b',
                    info: '#3b82f6'
                };

                const titleMap = {
                    success: 'Success',
                    error: 'Error',
                    warning: 'Warning',
                    info: 'Info'
                };

                showToast({
                    type: flashType,
                    title: flashTitle || titleMap[flashType] || 'Notification',
                    message: flashMsg,
                    icon: iconMap[flashType] || 'ri-information-line',
                    bgColor: bgMap[flashType] || bgMap.info
                });
            });
        </script>
        <?php
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_title']);
        ?>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
         CSRF TOKEN
         ═══════════════════════════════════════════════════════════ -->
    <?php
    $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
    ?>
    <script>
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>

    <!-- ═══════════════════════════════════════════════════════════
         JAVASCRIPT INCLUDES
         ═══════════════════════════════════════════════════════════ -->

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- Custom JS Files -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/charts.js"></script>

    <!-- ═══════════════════════════════════════════════════════════
         CORE APP SCRIPTS
         ═══════════════════════════════════════════════════════════ -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // ─── Sidebar Toggle ───
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    // Mobile: toggle overlay sidebar
                    sidebar.classList.toggle('show');
                } else {
                    // Desktop: toggle collapsed sidebar
                    document.body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
                }
            });
        }

        // Close mobile sidebar on overlay click
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
            });
        }

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth >= 992) {
            document.body.classList.add('sidebar-collapsed');
        }

        // ─── Theme Toggle ───
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const html = document.documentElement;
                const currentTheme = html.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }

        // Restore theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }

        // ─── Mobile Search ───
        const mobileSearchToggle = document.getElementById('mobileSearchToggle');
        const searchWrapper = document.getElementById('searchWrapper');
        if (mobileSearchToggle && searchWrapper) {
            mobileSearchToggle.addEventListener('click', function() {
                searchWrapper.classList.toggle('show-mobile');
                if (searchWrapper.classList.contains('show-mobile')) {
                    document.getElementById('globalSearch').focus();
                }
            });
        }

        // ─── Global Search Shortcut (Ctrl+K) ───
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('globalSearch');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        // ─── Notifications (AJAX Load) ───
        const notifDropdown = document.getElementById('notificationDropdown');
        if (notifDropdown) {
            notifDropdown.addEventListener('shown.bs.dropdown', function() {
                loadNotifications();
            });
        }

        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetch(BASE_URL + '/api/notifications/mark-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ _token: CSRF_TOKEN })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('notifBadge');
                        if (badge) badge.remove();
                        loadNotifications();
                    }
                })
                .catch(err => console.error('Error marking notifications as read:', err));
            });
        }

        function loadNotifications() {
            const list = document.getElementById('notificationList');
            if (!list) return;

            fetch(BASE_URL + '/api/notifications/recent.php?limit=5')
                .then(response => response.json())
                .then(data => {
                    if (data.notifications && data.notifications.length > 0) {
                        list.innerHTML = data.notifications.map(notif => `
                            <div class="mw-notification-item ${notif.is_read ? '' : 'unread'}" data-id="${notif.id}">
                                <div class="notif-icon bg-primary-light">
                                    <i class="${notif.icon || 'ri-notification-3-line'}"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-title">${notif.title}</div>
                                    <div class="notif-text">${notif.message}</div>
                                    <div class="notif-time">${notif.time_ago}</div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        list.innerHTML = `
                            <div class="text-center py-4">
                                <i class="ri-notification-off-line" style="font-size: 32px; color: var(--mw-text-muted);"></i>
                                <p class="mt-2 mb-0" style="color: var(--mw-text-muted); font-size: 13px;">No new notifications</p>
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    list.innerHTML = `
                        <div class="text-center py-4">
                            <i class="ri-error-warning-line" style="font-size: 32px; color: #ef4444;"></i>
                            <p class="mt-2 mb-0" style="color: var(--mw-text-muted); font-size: 13px;">Failed to load notifications</p>
                        </div>
                    `;
                });
        }

        // ─── Toast Utility ───
        window.showToast = function({ type = 'info', title = '', message = '', icon = '', bgColor = '' } = {}) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const id = 'toast-' + Date.now();
            const iconMap = {
                success: 'ri-check-line',
                error: 'ri-close-circle-line',
                warning: 'ri-alert-line',
                info: 'ri-information-line'
            };
            const bgMap = {
                success: '#7CB342',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6'
            };

            const toastIcon = icon || iconMap[type] || iconMap.info;
            const toastBg = bgColor || bgMap[type] || bgMap.info;

            const html = `
                <div id="${id}" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 320px; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.12);">
                    <div class="d-flex" style="background: var(--bs-body-bg); padding: 4px 0;">
                        <div style="width: 4px; background: ${toastBg}; border-radius: 4px 0 0 4px; margin: 8px 0 8px 4px;"></div>
                        <div class="toast-body d-flex align-items-start gap-3 flex-grow-1" style="padding: 14px 16px;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: ${toastBg}15; color: ${toastBg}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                                <i class="${toastIcon}"></i>
                            </div>
                            <div class="flex-grow-1">
                                ${title ? `<div style="font-weight: 600; font-size: 14px; margin-bottom: 2px; color: var(--mw-text-primary);">${title}</div>` : ''}
                                <div style="font-size: 13px; color: var(--mw-text-secondary);">${message}</div>
                            </div>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Close" style="margin-top: 2px; opacity: 0.5;"></button>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', html);
            const toastEl = document.getElementById(id);
            const toast = new bootstrap.Toast(toastEl, { delay: 4000, autohide: true });
            toast.show();

            toastEl.addEventListener('hidden.bs.toast', function() {
                toastEl.remove();
            });
        };

        // ─── Loading Overlay Utility ───
        window.showLoading = function(text = 'Processing...') {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.querySelector('.mw-loading-text').textContent = text;
                overlay.style.display = 'flex';
            }
        };

        window.hideLoading = function() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        };

        // ─── CSRF Token for AJAX ───
        $.ajaxSetup({
            data: { _token: CSRF_TOKEN }
        });

        // ─── Initialize Tooltips ───
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover',
                container: 'body'
            });
        });

        // Re-initialize tooltips on sidebar toggle (for collapsed state)
        const observer = new MutationObserver(function() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                const existingTooltip = bootstrap.Tooltip.getInstance(el);
                if (existingTooltip) existingTooltip.dispose();
                new bootstrap.Tooltip(el, { trigger: 'hover', container: 'body' });
            });
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });

    });
    </script>

    <!-- ═══════════════════════════════════════════════════════════
         PAGE-SPECIFIC JS INITIALIZATION PLACEHOLDER
         ═══════════════════════════════════════════════════════════ -->
    <?php if (isset($pageJS)): ?>
        <?php foreach ((array)$pageJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($inlineJS)): ?>
        <script>
            <?php echo $inlineJS; ?>
        </script>
    <?php endif; ?>

</body>
</html>
