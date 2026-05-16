    <!-- ═══════════════════════════════════════════════════════════
         LOGIN FOOTER
         ═══════════════════════════════════════════════════════════ -->

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom App JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>

    <!-- CSRF Token for Login Forms -->
    <?php
    $csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
    ?>
    <script>
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const BASE_URL = '<?php echo BASE_URL; ?>';
    </script>

    <!-- Login Page Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // ─── Password Visibility Toggle ───
        document.querySelectorAll('.mw-password-toggle .toggle-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('ri-eye-off-line');
                    icon.classList.add('ri-eye-line');
                } else {
                    input.type = 'password';
                    icon.classList.remove('ri-eye-line');
                    icon.classList.add('ri-eye-off-line');
                }
            });
        });

        // ─── Form Validation ───
        const authForm = document.querySelector('.mw-auth-form');
        if (authForm) {
            authForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('.mw-btn-auth');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Please wait...';
                }
            });
        }

        // ─── Flash Message Auto-Display ───
        <?php if (isset($_SESSION['flash_message'])): ?>
            (function() {
                const msg = <?php echo json_encode($_SESSION['flash_message']); ?>;
                const type = '<?php echo $_SESSION['flash_type'] ?? 'error'; ?>';
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert mw-auth-alert alert-' + (type === 'error' ? 'danger' : type);
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = '<i class="ri-' + (type === 'error' ? 'close-circle' : type === 'success' ? 'check' : 'alert') + '-line me-2"></i>' + msg;

                const card = document.querySelector('.mw-auth-card');
                if (card) {
                    card.insertBefore(alertDiv, card.firstChild);
                }
            })();
            <?php
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                unset($_SESSION['flash_title']);
            ?>
        <?php endif; ?>

        // ─── Theme Persistence ───
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        }

        // ─── Auto-focus first input ───
        const firstInput = document.querySelector('.mw-auth-form input:not([type="hidden"])');
        if (firstInput) {
            firstInput.focus();
        }

    });
    </script>

    <!-- Copyright -->
    <div style="position: fixed; bottom: 0; left: 0; right: 0; text-align: center; padding: 16px; pointer-events: none; z-index: 0;">
        <p style="font-size: 12px; color: #94a3b8; margin: 0;">
            &copy; <?php echo date('Y'); ?> MedWell Pharmacy. All rights reserved.
        </p>
    </div>

</body>
</html>
