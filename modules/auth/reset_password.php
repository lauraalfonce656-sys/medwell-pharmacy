<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../../modules/dashboard/index.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
$valid_token = false;
$error = '';
$success = '';
$token_error = '';

// Validate token
if (empty($token)) {
    $token_error = 'No reset token provided. Please request a new password reset link.';
} else {
    // Check if token exists and is valid
    $reset_data = validatePasswordResetToken($token);

    if (!$reset_data) {
        $token_error = 'This reset link is invalid. Please request a new password reset link.';
    } elseif (strtotime($reset_data['expires_at']) < time()) {
        $token_error = 'This reset link has expired. Please request a new password reset link.';
        // Clean up expired token
        deletePasswordResetToken($token);
    } else {
        $valid_token = true;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $error = 'Password must contain at least one number.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Get user ID from token
            $reset_data = validatePasswordResetToken($token);

            if ($reset_data) {
                // Update password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                updateUserPassword($reset_data['user_id'], $hashed);

                // Delete used token
                deletePasswordResetToken($token);

                // Delete all other reset tokens for this user
                deleteAllPasswordResetTokensForUser($reset_data['user_id']);

                // Set success message and redirect
                $_SESSION['success_message'] = 'Your password has been reset successfully. Please sign in with your new password.';
                header('Location: login.php');
                exit;
            } else {
                $error = 'Invalid or expired reset token. Please request a new one.';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MedWell Pharmacy</title>
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
            --warning-text: #d97706;
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

        @keyframes floatCross {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .reset-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .reset-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            padding: 48px 40px;
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }

        .reset-card::before {
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

        .brand-name span { color: var(--avocado-600); }

        .heading-section {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeIn 0.8s ease-out 0.3s both;
        }

        .heading-section h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .heading-section p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Token error state */
        .token-error {
            text-align: center;
            padding: 24px;
        }

        .token-error-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: var(--error-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .token-error-icon svg {
            width: 32px;
            height: 32px;
            fill: var(--error-text);
        }

        .token-error h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .token-error p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

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
            padding: 12px 44px 12px 44px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--card-bg);
            transition: var(--transition);
            outline: none;
        }

        .form-input::placeholder { color: var(--text-muted); }
        .form-input:hover { border-color: #cbd5e1; }

        .form-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(132, 204, 22, 0.15);
        }

        .input-wrapper:focus-within .input-icon { fill: var(--avocado-600); }

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

        /* Password strength indicator */
        .strength-section {
            margin-top: 10px;
            margin-bottom: 4px;
            animation: fadeIn 0.3s ease-out;
        }

        .strength-bar-bg {
            width: 100%;
            height: 4px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.4s ease, background 0.4s ease;
            width: 0%;
        }

        .strength-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
        }

        .strength-text {
            font-size: 12px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .strength-requirements {
            margin-top: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            transition: color 0.3s ease;
        }

        .requirement.met { color: var(--success-text); }

        .requirement-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--border);
            transition: background 0.3s ease;
            flex-shrink: 0;
        }

        .requirement.met .requirement-dot {
            background: var(--success-text);
        }

        /* Match indicator */
        .match-indicator {
            margin-top: 8px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .match-indicator.matched { color: var(--success-text); }
        .match-indicator.unmatched { color: var(--error-text); }

        .btn-submit {
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
            animation: fadeIn 0.8s ease-out 0.45s both;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--avocado-600), var(--avocado-800));
            box-shadow: 0 8px 20px rgba(132, 204, 22, 0.3);
            transform: translateY(-1px);
        }

        .btn-submit:hover::before { left: 100%; }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }

        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            animation: fadeIn 0.8s ease-out 0.5s both;
        }

        .back-link svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }

        .back-link:hover { color: var(--avocado-600); }
        .back-link:hover svg { transform: translateX(-3px); }

        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            animation: fadeIn 0.8s ease-out 0.55s both;
        }

        .login-footer p { font-size: 13px; color: var(--text-muted); }

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

        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-submit.loading .spinner { display: inline-block; }
        .btn-submit.loading .btn-text { opacity: 0.8; }

        @media (max-width: 480px) {
            .reset-wrapper { padding: 16px; }
            .reset-card { padding: 36px 24px; }
            .brand-icon { width: 60px; height: 60px; }
            .brand-icon svg { width: 32px; height: 32px; }
            .brand-name { font-size: 20px; }
            .strength-requirements { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>

    <div class="reset-wrapper">
        <div class="reset-card">
            <!-- Brand Section -->
            <div class="brand-section">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.5 2.5h3v5h5v3h-5v5h-3v-5h-5v-3h5v-5z"/>
                        <rect x="4" y="10" width="16" height="12" rx="2" fill="white" opacity="0.3"/>
                    </svg>
                </div>
                <div class="brand-name">Med<span>Well</span></div>
            </div>

            <?php if ($token_error): ?>
                <!-- Token Error State -->
                <div class="token-error">
                    <div class="token-error-icon">
                        <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h3>Invalid Link</h3>
                    <p><?php echo htmlspecialchars($token_error); ?></p>
                    <a href="forgot_password.php" style="color: var(--avocado-600); text-decoration: none; font-weight: 500; font-size: 14px;">Request New Reset Link</a>
                </div>
            <?php else: ?>
                <!-- Reset Password Form -->
                <div class="heading-section">
                    <h2>Reset Password</h2>
                    <p>Create a new password for your account. Make sure it's strong and secure.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <input
                                type="password"
                                class="form-input"
                                id="new_password"
                                name="new_password"
                                placeholder="Enter new password"
                                required
                                autofocus
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" data-target="new_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Password Strength Indicator -->
                        <div class="strength-section" id="strengthSection" style="display: none;">
                            <div class="strength-bar-bg">
                                <div class="strength-bar-fill" id="strengthBar"></div>
                            </div>
                            <div class="strength-label">
                                <span class="strength-text" id="strengthText"></span>
                            </div>
                            <div class="strength-requirements">
                                <div class="requirement" id="req-length">
                                    <span class="requirement-dot"></span>
                                    <span>8+ characters</span>
                                </div>
                                <div class="requirement" id="req-upper">
                                    <span class="requirement-dot"></span>
                                    <span>Uppercase letter</span>
                                </div>
                                <div class="requirement" id="req-lower">
                                    <span class="requirement-dot"></span>
                                    <span>Lowercase letter</span>
                                </div>
                                <div class="requirement" id="req-number">
                                    <span class="requirement-dot"></span>
                                    <span>Number</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <input
                                type="password"
                                class="form-input"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm new password"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Toggle password visibility">
                                <svg class="eye-open" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="display: none;">
                                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="match-indicator" id="matchIndicator" style="display: none;"></div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">Reset Password</span>
                    </button>
                </form>

                <a href="login.php" class="back-link">
                    <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Back to Login
                </a>
            <?php endif; ?>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> MedWell Pharmacy. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggles
        document.querySelectorAll('.password-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var input = document.getElementById(targetId);
                var eyeOpen = this.querySelector('.eye-open');
                var eyeClosed = this.querySelector('.eye-closed');

                if (input.type === 'password') {
                    input.type = 'text';
                    eyeOpen.style.display = 'none';
                    eyeClosed.style.display = 'block';
                } else {
                    input.type = 'password';
                    eyeOpen.style.display = 'block';
                    eyeClosed.style.display = 'none';
                }
            });
        });

        // Password strength checker
        var newPasswordInput = document.getElementById('new_password');
        var confirmPasswordInput = document.getElementById('confirm_password');
        var strengthSection = document.getElementById('strengthSection');
        var strengthBar = document.getElementById('strengthBar');
        var strengthText = document.getElementById('strengthText');
        var matchIndicator = document.getElementById('matchIndicator');

        var strengthLevels = [
            { label: 'Very Weak', color: '#ef4444', width: '20%' },
            { label: 'Weak', color: '#f97316', width: '40%' },
            { label: 'Fair', color: '#eab308', width: '60%' },
            { label: 'Good', color: '#84cc16', width: '80%' },
            { label: 'Strong', color: '#22c55e', width: '100%' }
        ];

        function checkStrength(password) {
            var score = 0;

            // Length check
            var hasLength = password.length >= 8;
            document.getElementById('req-length').classList.toggle('met', hasLength);

            // Uppercase check
            var hasUpper = /[A-Z]/.test(password);
            document.getElementById('req-upper').classList.toggle('met', hasUpper);

            // Lowercase check
            var hasLower = /[a-z]/.test(password);
            document.getElementById('req-lower').classList.toggle('met', hasLower);

            // Number check
            var hasNumber = /[0-9]/.test(password);
            document.getElementById('req-number').classList.toggle('met', hasNumber);

            if (hasLength) score++;
            if (hasUpper) score++;
            if (hasLower) score++;
            if (hasNumber) score++;

            // Bonus for special characters
            if (/[^A-Za-z0-9]/.test(password)) score = Math.min(score + 1, 5);
            // Bonus for length > 12
            if (password.length >= 12) score = Math.min(score + 1, 5);

            // Map score (0-5) to strength level (0-4)
            var levelIndex = Math.min(Math.floor(score * 5 / 6), 4);
            if (password.length === 0) levelIndex = -1;

            return levelIndex;
        }

        newPasswordInput.addEventListener('input', function() {
            var password = this.value;

            if (password.length > 0) {
                strengthSection.style.display = 'block';
                var level = checkStrength(password);

                if (level >= 0) {
                    var levelData = strengthLevels[level];
                    strengthBar.style.width = levelData.width;
                    strengthBar.style.background = levelData.color;
                    strengthText.textContent = levelData.label;
                    strengthText.style.color = levelData.color;
                } else {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                }
            } else {
                strengthSection.style.display = 'none';
            }

            checkMatch();
        });

        confirmPasswordInput.addEventListener('input', checkMatch);

        function checkMatch() {
            var newPass = newPasswordInput.value;
            var confirmPass = confirmPasswordInput.value;

            if (confirmPass.length > 0) {
                matchIndicator.style.display = 'flex';
                if (newPass === confirmPass) {
                    matchIndicator.className = 'match-indicator matched';
                    matchIndicator.innerHTML = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Passwords match';
                } else {
                    matchIndicator.className = 'match-indicator unmatched';
                    matchIndicator.innerHTML = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg> Passwords do not match';
                }
            } else {
                matchIndicator.style.display = 'none';
            }
        }

        // Form submission
        var resetForm = document.getElementById('resetForm');
        var submitBtn = document.getElementById('submitBtn');

        if (resetForm) {
            resetForm.addEventListener('submit', function() {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            });
        }
    </script>
</body>
</html>
