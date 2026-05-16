# MedWell Pharmacy - Pharmacy Management System

A premium, enterprise-grade Pharmacy Management System built with PHP + MySQL, designed for real-world deployment on Hostinger shared hosting.

## 🏥 Overview

MedWell Pharmacy is a complete pharmacy management solution featuring a modern, healthcare-inspired UI with an avocado green theme. It provides comprehensive tools for medicine management, point-of-sale operations, inventory tracking, customer and supplier management, and detailed reporting.

## ✨ Features

### Core Modules
- **Authentication System** - Secure login, role-based access (Admin/Pharmacist/Cashier), password reset
- **Dashboard** - Analytics, charts, sales overview, alerts, quick actions
- **Medicine Management** - CRUD operations, categories, batch tracking, barcode support, expiry management
- **Point of Sale (POS)** - Premium POS interface, cart system, invoice generation, receipt printing
- **Inventory Management** - Real-time stock tracking, low stock alerts, expiry alerts, movement logs
- **Customer Management** - Profiles, purchase history, loyalty tracking
- **Supplier Management** - Supplier database, contact info, linked medicines
- **Reports & Analytics** - Sales, profit, inventory, expiry, customer, supplier reports with CSV/PDF export
- **Settings** - Pharmacy info, system settings, user management, password updates

### Premium Features
- 🌗 Dark/Light mode toggle
- 🔔 Notification system
- 📊 Chart.js analytics dashboard
- 🔍 Searchable DataTables
- 🖨️ Receipt printing (thermal 58mm/80mm support)
- 🔒 CSRF protection, bcrypt password hashing, prepared statements
- 📱 Fully responsive design
- ⌨️ Keyboard shortcuts (Ctrl+K search, F2-F9 POS shortcuts)
- 🎨 Premium avocado green medical SaaS theme

## 🛠️ Technology Stack

| Technology | Version |
|------------|---------|
| PHP | 8.0+ |
| MySQL | 5.7+ / 8.0+ |
| Bootstrap | 5.3 |
| Chart.js | 4.4 |
| DataTables.js | 1.13 |
| Font Awesome | 6 |
| Remix Icons | 4 |
| jQuery | 3.7.1 |

## 📁 Project Structure

```
medwell-pharmacy/
├── ajax/                          # AJAX handler endpoints
│   ├── adjust_stock.php
│   ├── check_barcode.php
│   ├── complete_sale.php
│   ├── get_dashboard_stats.php
│   ├── get_medicine.php
│   ├── get_notifications.php
│   ├── mark_notification_read.php
│   ├── refund_sale.php
│   ├── search_medicines.php
│   └── validate_csrf.php
├── assets/
│   ├── css/
│   │   └── style.css              # Premium avocado green theme
│   ├── js/
│   │   ├── app.js                 # Core application module
│   │   ├── charts.js              # Chart.js initialization
│   │   └── pos.js                 # POS cart & sale logic
│   ├── images/
│   └── plugins/
├── config/
│   ├── config.php                 # App configuration & constants
│   └── database.php               # PDO database connection
├── database/
│   └── medwell_pharmacy.sql       # Complete database schema
├── includes/
│   ├── auth.php                   # Authentication & session management
│   ├── functions.php              # Utility functions
│   ├── Customer.class.php         # Customer model
│   ├── Medicine.class.php         # Medicine model
│   ├── Notification.class.php     # Notification model
│   ├── Report.class.php           # Report model
│   ├── Sale.class.php             # Sale model
│   ├── Supplier.class.php         # Supplier model
│   ├── User.class.php             # User model
│   ├── header.php                 # Dashboard header template
│   ├── sidebar.php                # Sidebar navigation template
│   ├── footer.php                 # Dashboard footer template
│   ├── login_header.php           # Login page header
│   ├── login_footer.php           # Login page footer
│   └── templates/                 # Alternative template directory
│       ├── header.php
│       ├── sidebar.php
│       └── footer.php
├── modules/
│   ├── auth/                      # Authentication pages
│   │   ├── login.php
│   │   ├── forgot_password.php
│   │   ├── reset_password.php
│   │   └── logout.php
│   ├── dashboard/                 # Dashboard
│   │   └── index.php
│   ├── medicines/                 # Medicine management
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   ├── delete.php
│   │   └── categories.php
│   ├── pos/                       # Point of Sale
│   │   ├── index.php
│   │   ├── receipt.php
│   │   └── sales.php
│   ├── inventory/                 # Inventory management
│   │   ├── index.php
│   │   ├── adjust.php
│   │   ├── logs.php
│   │   └── expiry.php
│   ├── customers/                 # Customer management
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── delete.php
│   ├── suppliers/                 # Supplier management
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── delete.php
│   ├── reports/                   # Reports & Analytics
│   │   ├── index.php
│   │   ├── sales.php
│   │   ├── profit.php
│   │   ├── inventory.php
│   │   ├── expiry.php
│   │   ├── customers.php
│   │   └── suppliers.php
│   └── settings/                  # System settings
│       └── index.php
├── uploads/                       # File uploads directory
│   ├── logos/
│   └── profiles/
├── .htaccess                      # Apache security & routing
├── install.php                    # Installation wizard
├── index.php                      # Entry point (redirects to login)
├── robots.txt                     # Search engine directives
└── README.md                      # This file
```

## 🚀 Installation

### Method 1: Automatic Installation (Recommended)

1. Upload all files to your Hostinger `public_html` directory
2. Visit `https://yourdomain.com/install.php` in your browser
3. Follow the 6-step installation wizard:
   - **Step 1**: System requirements check
   - **Step 2**: Database configuration
   - **Step 3**: Import SQL schema
   - **Step 4**: Create admin account
   - **Step 5**: Site configuration
   - **Step 6**: Complete installation
4. **Delete `install.php`** after installation for security

### Method 2: Manual Installation

1. **Create MySQL Database**
   - Log into Hostinger cPanel
   - Go to MySQL Databases
   - Create a new database (e.g., `medwell_pharmacy`)
   - Create a database user with full privileges
   - Note the database name, username, and password

2. **Import Database Schema**
   - Open phpMyAdmin from cPanel
   - Select the created database
   - Click "Import" tab
   - Upload `database/medwell_pharmacy.sql`
   - Click "Go" to execute

3. **Configure Database Connection**
   - Open `config/config.php`
   - Update the following constants:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

4. **Upload Files**
   - Upload all files to your `public_html` directory via File Manager or FTP
   - Ensure `uploads/` directory has write permissions (755 or 777)

5. **Access the System**
   - Visit `https://yourdomain.com/`
   - Default admin credentials:
     - Username: `admin`
     - Password: `admin123`
   - **Change the default password immediately after first login**

## 🔧 Hostinger Deployment Guide

### Step-by-Step Hostinger Setup

1. **Purchase Hosting Plan**
   - Any Hostinger shared hosting plan works (Premium or Business recommended)
   - PHP 8.0+ is required

2. **Access hPanel**
   - Log into Hostinger hPanel
   - Navigate to Hosting → Manage

3. **Create Database**
   - Go to Databases → MySQL Databases
   - Create database with name like `u123456789_medwell`
   - Create user with strong password
   - Assign user to database with ALL PRIVILEGES

4. **Configure PHP**
   - Go to Advanced → PHP Configuration
   - Set PHP version to 8.1 or 8.2
   - Enable extensions: PDO, pdo_mysql, mbstring, openssl, json, curl

5. **Upload Files**
   - Go to Files → File Manager
   - Navigate to `public_html/`
   - Upload the ZIP file and extract, OR
   - Use FTP (FileZilla) for large file uploads

6. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/logos/
   chmod 755 uploads/profiles/
   ```

7. **Run Installer**
   - Visit `https://yourdomain.com/install.php`
   - Follow the wizard steps

8. **Post-Installation Security**
   - Delete `install.php`
   - Change default admin password
   - Enable SSL/HTTPS in hPanel → Security → SSL

### SSL Configuration

1. In Hostinger hPanel, go to Security → SSL
2. Enable free SSL (Let's Encrypt)
3. Uncomment the HTTPS redirect in `.htaccess`:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

## 🔒 Security Features

- **Password Hashing**: bcrypt with cost factor 12
- **SQL Injection Protection**: PDO prepared statements throughout
- **CSRF Protection**: Token-based with automatic rotation
- **Session Security**: HttpOnly, SameSite=Strict cookies, session fixation prevention
- **Input Validation**: Server-side validation and sanitization
- **File Upload Security**: MIME type validation, random filenames, no PHP execution in uploads/
- **Directory Protection**: .htaccess denies access to config/, includes/, ajax/ directories
- **Security Headers**: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

## 🎨 Theme Customization

The color palette is defined in `assets/css/style.css` using CSS custom properties:

```css
:root {
  --primary: #7CB342;        /* Avocado green */
  --primary-light: #9CCC65;
  --primary-dark: #558B2F;   /* Muted dark green */
  --bg-body: #f5f7f0;        /* Soft cream */
  --accent: #33691E;
}
```

To change the theme colors, simply modify these CSS variables.

## 👥 User Roles

| Role | Access Level |
|------|-------------|
| **Admin** | Full access to all modules including settings and user management |
| **Pharmacist** | Access to dashboard, medicines, POS, inventory, customers, reports |
| **Cashier** | Access to dashboard, POS (limited), sales history |

## 📊 Database Schema

The system includes 12 tables:

| Table | Purpose |
|-------|---------|
| `users` | System users with role-based access |
| `medicines` | Medicine inventory with batch tracking |
| `medicine_categories` | Medicine categorization |
| `suppliers` | Supplier information |
| `customers` | Customer profiles with loyalty points |
| `sales` | Sales transactions |
| `sale_items` | Individual items in each sale |
| `inventory_logs` | Stock movement audit trail |
| `payments` | Payment records |
| `settings` | System configuration key-value store |
| `password_resets` | Password reset tokens |
| `notifications` | User notifications |

Plus 4 views for optimized dashboard queries and 1 stored procedure for invoice number generation.

## ⌨️ Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Open global search |
| `Ctrl+/` | Toggle sidebar |
| `Esc` | Close modals/dropdowns |
| `F2` | POS: Focus search |
| `F4` | POS: Open payment |
| `F6` | POS: Hold cart |
| `F8` | POS: Clear cart |
| `F9` | POS: Print receipt |

## 📝 License

This project is proprietary software. All rights reserved.

## 🤝 Support

For support and inquiries, contact the development team.

---

**MedWell Pharmacy** - *Your Health, Our Priority*
