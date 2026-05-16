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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if user exists with this email
            $user = findUserByEmail($email);

            if ($user) {
                // Generate a secure reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in password_resets table
                storePasswordResetToken($user['id'], $token, $expires);

                // In production, send email with reset link:
                // $resetLink = BASE_URL . "/modules/auth/reset_password.php?token=" . $token;
                // sendPasswordResetEmail($email, $resetLink);

                // For now, log the token for development purposes
                error_log("Password reset token for $email: $token");
            }

            // Always show success message to prevent email enumeration
            $success = 'If an account exists with that email, we\'ve sent a password reset link. Please check your inbox.';

            $email = ''; // Clear email field on success
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
    <title>Forgot Password - MedWell Pharmacy</title>
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

        .forgot-wrapper {
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

        .forgot-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            padding: 48px 40px;
            border: 1px solid rgba(226, 232, 240, 0.6);
            position: relative;
            overflow: hidden;
        }

        .forgot-card::before {
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

        .brand-name span {
            color: var(--avocado-600);
        }

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

        .form-group {
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease-out 0.35s both;
        }

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

        .input-wrapper:focus-within .input-icon {
            fill: var(--avocado-600);
        }

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
            animation: fadeIn 0.8s ease-out 0.4s both;
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

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

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
            animation: fadeIn 0.8s ease-out 0.45s both;
        }

        .back-link svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
            transition: transform 0.2s ease;
        }

        .back-link:hover {
            color: var(--avocado-600);
        }

        .back-link:hover svg {
            transform: translateX(-3px);
        }

        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            animation: fadeIn 0.8s ease-out 0.5s both;
        }

        .login-footer p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Spinner */
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

        .btn-submit.loading .spinner {
            display: inline-block;
        }

        .btn-submit.loading .btn-text {
            opacity: 0.8;
        }

        @media (max-width: 480px) {
            .forgot-wrapper { padding: 16px; }
            .forgot-card { padding: 36px 24px; }
            .brand-icon { width: 60px; height: 60px; }
            .brand-icon svg { width: 32px; height: 32px; }
            .brand-name { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>
    <div class="bg-cross">&#x2695;</div>

    <div class="forgot-wrapper">
        <div class="forgot-card">
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

            <!-- Heading -->
            <div class="heading-section">
                <h2>Forgot Password?</h2>
                <p>No worries! Enter your email address and we'll send you a link to reset your password.</p>
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

            <!-- Forgot Password Form -->
            <form method="POST" action="" id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        <input
                            type="email"
                            class="form-input"
                            id="email"
                            name="email"
                            placeholder="Enter your registered email"
                            required
                            autofocus
                            autocomplete="email"
                            value="<?php echo htmlspecialchars($email); ?>"
                        >
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Send Reset Link</span>
                </button>
            </form>

            <a href="login.php" class="back-link">
                <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
                Back to Login
            </a>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> MedWell Pharmacy. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        const forgotForm = document.getElementById('forgotForm');
        const submitBtn = document.getElementById('submitBtn');

        forgotForm.addEventListener('submit', function () {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });

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
