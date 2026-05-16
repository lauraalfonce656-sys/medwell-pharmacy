<?php
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MedWell Pharmacy Management System - Login">
    <meta name="author" content="MedWell">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | MedWell' : 'MedWell Pharmacy - Login'; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/favicon.png">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Custom Style -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">

    <style>
        :root {
            --mw-primary: #7CB342;
            --mw-primary-dark: #689F38;
            --mw-primary-light: #9CCC65;
            --mw-primary-bg: rgba(124, 179, 66, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0f7e6 0%, #e8f5e1 25%, #f5f7fa 50%, #ffffff 100%);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            position: relative;
            overflow: hidden;
        }

        [data-bs-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
        }

        /* Decorative Background Elements */
        body::before {
            content: '';
            position: fixed;
            top: -200px;
            right: -200px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124, 179, 66, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -150px;
            left: -150px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124, 179, 66, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Auth Card */
        .mw-auth-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .mw-auth-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02), 0 10px 40px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
        }

        [data-bs-theme="dark"] .mw-auth-card {
            background: #1e293b;
            border-color: rgba(255,255,255,0.06);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1), 0 10px 40px rgba(0,0,0,0.2);
        }

        /* Logo */
        .mw-auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .mw-auth-logo-icon {
            width: 56px;
            height: 56px;
            background: var(--mw-primary);
            color: #ffffff;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(124, 179, 66, 0.3);
        }

        .mw-auth-logo h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--mw-primary-dark);
            letter-spacing: -0.5px;
            margin: 0;
        }

        [data-bs-theme="dark"] .mw-auth-logo h1 {
            color: var(--mw-primary-light);
        }

        .mw-auth-logo p {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Form */
        .mw-auth-form .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        [data-bs-theme="dark"] .mw-auth-form .form-label {
            color: #cbd5e1;
        }

        .mw-auth-form .form-control {
            height: 46px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 0 14px;
            font-size: 14px;
            transition: all 0.25s ease;
            background: #f8fafc;
        }

        [data-bs-theme="dark"] .mw-auth-form .form-control {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        .mw-auth-form .form-control:focus {
            border-color: var(--mw-primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
            background: #ffffff;
        }

        [data-bs-theme="dark"] .mw-auth-form .form-control:focus {
            background: #1e293b;
        }

        .mw-auth-form .input-group-text {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #94a3b8;
            padding: 0 14px;
        }

        [data-bs-theme="dark"] .mw-auth-form .input-group-text {
            background: #0f172a;
            border-color: #334155;
            color: #64748b;
        }

        .mw-auth-form .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .mw-auth-form .input-group .form-control:focus {
            box-shadow: none;
        }

        .mw-auth-form .input-group:focus-within .input-group-text {
            border-color: var(--mw-primary);
            color: var(--mw-primary);
        }

        .mw-auth-form .input-group:focus-within .form-control {
            border-color: var(--mw-primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
        }

        /* Submit Button */
        .mw-btn-auth {
            height: 46px;
            background: var(--mw-primary);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(124, 179, 66, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .mw-btn-auth:hover {
            background: var(--mw-primary-dark);
            box-shadow: 0 6px 20px rgba(124, 179, 66, 0.4);
            transform: translateY(-1px);
            color: #ffffff;
        }

        .mw-btn-auth:active {
            transform: translateY(0);
        }

        /* Links */
        .mw-auth-links a {
            color: var(--mw-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
        }

        .mw-auth-links a:hover {
            color: var(--mw-primary-dark);
            text-decoration: underline;
        }

        /* Error Messages */
        .mw-auth-alert {
            border-radius: 12px;
            font-size: 13px;
            border: none;
            padding: 12px 16px;
        }

        .mw-auth-alert.alert-danger {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
        }

        .mw-auth-alert.alert-success {
            background: rgba(124, 179, 66, 0.08);
            color: var(--mw-primary-dark);
        }

        /* Password Toggle */
        .mw-password-toggle {
            position: relative;
        }

        .mw-password-toggle .toggle-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            z-index: 5;
        }

        .mw-password-toggle .toggle-btn:hover {
            color: var(--mw-primary);
        }

        /* Footer */
        .mw-auth-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        [data-bs-theme="dark"] .mw-auth-footer {
            border-top-color: #334155;
        }

        .mw-auth-footer p {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        .mw-auth-footer a {
            color: var(--mw-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .mw-auth-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .mw-auth-wrapper {
                padding: 16px;
            }
            .mw-auth-card {
                padding: 28px 24px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
