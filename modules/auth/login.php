<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../../modules/dashboard/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            // Attempt authentication
            $user = authenticateUser($username, $password);

            if ($user) {
                // Check if user is active
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Set session
                    setUserSession($user);

                    // Set remember me cookie if checked
                    if ($remember) {
                        setRememberMeCookie($user['id']);
                    }

                    // Regenerate session ID for security
                    session_regenerate_id(true);

                    // Redirect to dashboard
                    header('Location: ../../modules/dashboard/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid username/email or password.';
            }
        }
    }
}

// Check for success messages from other pages (e.g., password reset)
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --avocado-50: #f7fee7;
            --avocado-100: #ecfccb;
            --avocado-200: #d9f99d;
            --avocado-300: #bef264;
            --avocado-400: #a3e635;
            --avocado-500: #84cc16;
            --avocado-600: #65a30d;
            --avocado-700: #4d7c0f;
            --avocado-800: #3f6212;
            --avocado-900: #365314;
            --cream: #fefdf5;
            --cream-dark: #fdf9e9;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --border-focus: #84cc16;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --error-border: #fecaca;
            --success-bg: #f0fdf4;
            --success-text: #16a34a;
            --success-border: #bbf7d0;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--cream);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background pattern */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(163, 230, 53, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(132, 204, 22, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 40% 80%, rgba(190, 242, 100, 0.05) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
            z-index: 0;
        }

        @keyframes bgShift {
            0% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, -2%) rotate(1deg); }
            66% { transform: translate(-1%, 1%) rotate(-0.5deg); }
            100% { transform: translate(1%, -1%) rotate(0.5deg); }
        }

        /* Floating pharmacy crosses in background */
        .bg-cross {
            position: absolute;
            opacity: 0.04;
            z-index: 0;
            animation: floatCross 15s ease-in-out infinite;
        }

        .bg-cross:nth-child(1) { top: 10%; left: 5%; font-size: 80px; animation-delay: 0s; }
        .bg-cross:nth-child(2) { top: 70%; left: 10%; font-size: 60px; animation-delay: -3s; }
        .bg-cross:nth-child(3) { top: 20%; right: 8%; font-size: 100px; animation-delay: -6s; }
        .bg-cross:nth-child(4) { bottom: 15%; right: 12%; font-size: 70px; animation-delay: -9s; }
        .bg-cross:nth-child(5) { top: 50%; left: 50%; font-size: 90px; animation-delay: -12s; }

        @keyframes floatCross {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Login card */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .login-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            padding: 48px 40px;
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--avocado-400), var(--avocado-600), var(--avocado-400));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Logo & Branding */
        .brand-section {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeIn 0.8s ease-out 0.2s both;
        }

        .brand-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, var(--avocado-400), var(--avocado-600));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(132, 204, 22, 0.25);
            position: relative;
        }

        .brand-icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        .brand-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: calc(var(--radius-lg) + 3px);
            background: linear-gradient(135deg, var(--avocado-300), var(--avocado-500));
            opacity: 0.2;
            z-index: -1;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .brand-name span {
            color: var(--avocado-600);
        }

        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* Welcome heading */
        .welcome-heading {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }

        .welcome-heading h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .welcome-heading p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* Alert messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeIn 0.4s ease-out;
        }

        .alert svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            margin-top: 1px;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }

        .alert-error svg { fill: var(--error-text); }

        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        .alert-success svg { fill: var(--success-text); }

        /* Form groups */
        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease-out both;
        }

        .form-group:nth-child(1) { animation-delay: 0.35s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            letter-spacing: 0.01em;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            fill: var(--text-muted);
            pointer-events: none;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--card-bg);
            transition: var(--transition);
            outline: none;
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-input:hover {
            border-color: #cbd5e1;
        }

        .form-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(132, 204, 22, 0.15);
        }

        .form-input:focus ~ .input-icon,
        .form-input:focus + .input-icon {
            fill: var(--avocado-600);
        }

        .input-wrapper:focus-within .input-icon {
            fill: var(--avocado-600);
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: var(--transition);
            border-radius: 4px;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
            background: rgba(0, 0, 0, 0.04);
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* Remember me & Forgot */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            animation: fadeIn 0.8s ease-out 0.45s both;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--avocado-600);
            cursor: pointer;
        }

        .checkbox-wrapper label {
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .forgot-link {
            font-size: 13px;
            color: var(--avocado-600);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--avocado-700);
            text-decoration: underline;
        }

        /* Login button */
        .btn-login {
            width: 100%;
            padding: 13px 24px;
            background: linear-gradient(135deg, var(--avocado-500), var(--avocado-700));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out 0.5s both;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--avocado-600), var(--avocado-800));
            box-shadow: 0 8px 20px rgba(132, 204, 22, 0.3);
            transform: translateY(-1px);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(132, 204, 22, 0.25);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            animation: fadeIn 0.8s ease-out 0.55s both;
        }

        .login-footer p {
            font-size: 13px;
            color: var(--text-muted);
        }

        .login-footer a {
            color: var(--avocado-600);
            text-decoration: none;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-wrapper {
                padding: 16px;
            }

            .login-card {
                padding: 36px 24px;
            }

            .brand-icon {
                width: 60px;
                height: 60px;
            }

            .brand-icon svg {
                width: 32px;
                height: 32px;
            }

            .brand-name {
                font-size: 20px;
            }
        }

        /* Loading spinner */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-login.loading .spinner {
            display: inline-block;
        }

        .btn-login.loading .btn-text {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- Background decorative crosses -->
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Brand Section -->
            <div class="brand-section">
                <div class="brand-icon">
                    <!-- Pharmacy Cross Icon -->
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.5 2.5h3v5h5v3h-5v5h-3v-5h-5v-3h5v-5z"/>
                        <rect x="4" y="10" width="16" height="12" rx="2" fill="white" opacity="0.3"/>
                        <rect x="7" y="12" width="10" height="2" rx="1" fill="white"/>
                        <rect x="11" y="8" width="2" height="10" rx="1" fill="white" opacity="0"/>
                    </svg>
                </div>
                <div class="brand-name">Med<span>Well</span></div>
                <div class="brand-subtitle">Pharmacy Management System</div>
            </div>

            <!-- Welcome Heading -->
            <div class="welcome-heading">
                <h2>Welcome Back</h2>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Username / Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                        </svg>
                        <input
                            type="text"
                            class="form-input"
                            id="username"
                            name="username"
                            placeholder="Enter your username or email"
                            required
                            autofocus
                            autocomplete="username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <input
                            type="password"
                            class="form-input"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <!-- Eye icon (show) -->
                            <svg id="eyeOpen" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                            <!-- Eye-off icon (hide) -->
                            <svg id="eyeClosed" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Sign In</span>
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> MedWell Pharmacy. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeOpen.style.display = isPassword ? 'none' : 'block';
            eyeClosed.style.display = isPassword ? 'block' : 'none';
        });

        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function () {
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
        });

        // Auto-hide alerts after 8 seconds
        document.querySelectorAll('.alert').forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 8000);
        });
    </script>
</body>
</html>
