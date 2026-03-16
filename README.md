# Document Management & Archiving System
### Converted from Next.js + Prisma → PHP + HTML + CSS + JavaScript

---

## Stack

| Layer      | Technology                    |
|------------|-------------------------------|
| Backend    | PHP 8.1+ (no framework)       |
| Database   | PostgreSQL (or MySQL/MariaDB) |
| Frontend   | Vanilla HTML + CSS + JS       |
| Auth       | JWT via HS256 (pure PHP)      |
| Styling    | Custom CSS (no Tailwind)      |

---

## Project Structure

```
dms-php/
├── index.php                   # Root redirect
├── login.php                   # Login page
├── profile.php                 # User profile + password change
├── documents.php               # User: my documents + borrow history
│
├── admin/
│   ├── storage.php             # Admin: document storage management
│   ├── tracking.php            # Admin: borrow/return tracking
│   ├── retrieval.php           # Admin: archived document retrieval
│   ├── backup.php              # Admin: backup management
│   ├── users.php               # Admin: user management
│   └── audit-trail.php         # Admin: full audit log viewer
│
├── api/
│   ├── auth/
│   │   └── logout.php
│   ├── documents/
│   │   ├── create.php          # POST: add document
│   │   ├── get.php             # GET:  fetch document by id
│   │   ├── update.php          # POST: edit document
│   │   ├── archive.php         # POST: archive / unarchive
│   │   ├── renew.php           # POST: create new version
│   │   ├── backup.php          # POST: mark as backed up
│   │   └── delete.php          # POST: soft delete
│   ├── borrow/
│   │   ├── create.php          # POST: admin creates borrow
│   │   ├── request.php         # POST: user requests borrow
│   │   ├── approve.php         # POST: admin approves pending
│   │   ├── refuse.php          # POST: admin refuses pending
│   │   └── return.php          # POST: mark as returned
│   └── users/
│       ├── get.php             # GET:  fetch user by id
│       ├── create.php          # POST: add user
│       ├── update.php          # POST: edit user
│       └── delete.php          # POST: delete user
│
├── includes/
│   ├── db.php                  # PDO database connection
│   ├── auth.php                # JWT session + helper functions
│   ├── layout.php              # Shared sidebar + topbar header
│   └── layout_footer.php       # Closing HTML + scripts
│
├── assets/
│   ├── css/style.css           # Full design system CSS
│   ├── js/main.js              # Toast, modals, dropdowns, fetch helper
│   └── img/logo.png            # Place your logo here
│
├── schema.sql                  # PostgreSQL database schema
└── .htaccess                   # Apache security + caching rules
```

---

## Setup Instructions

### 1. Database

```sql
-- Create the database
CREATE DATABASE document_management;

-- Run the schema
psql -U postgres -d document_management -f schema.sql
```

For **MySQL**, convert the enum types to VARCHAR with CHECK constraints,
and replace `ILIKE` with `LIKE` in queries.

### 2. Configure Database Connection

Edit `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'document_management');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_PORT', '5432');       // 5432 for PostgreSQL, 3306 for MySQL
define('DB_DRIVER', 'pgsql');    // 'pgsql' or 'mysql'
```

### 3. Set Session Secret

Edit `includes/auth.php`, line 6:
```php
define('SESSION_SECRET', 'your-very-long-random-secret-key-here');
```
Generate a strong key: `openssl rand -hex 32`

### 4. Web Server

**Apache** — place the project in your document root (e.g. `/var/www/html/dms`).
Enable `mod_rewrite` and `mod_headers`:
```bash
a2enmod rewrite headers
systemctl restart apache2
```

**Nginx** — add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### 5. File Permissions

```bash
chmod 755 /var/www/html/dms
chmod -R 644 /var/www/html/dms
find /var/www/html/dms -type d -exec chmod 755 {} \;
mkdir -p /var/www/html/dms/uploads /var/www/html/dms/backups
chmod 777 /var/www/html/dms/uploads /var/www/html/dms/backups
```

### 6. Add Logo

Place your logo image at `assets/img/logo.png`.

---

## Default Login

| Email             | Password   | Role  |
|-------------------|------------|-------|
| admin@dms.local   | Admin@123  | ADMIN |

**Change the admin password immediately after first login.**

---

## Features

### Admin
- **Storage** — Add, edit, archive, renew, delete documents with filtering and pagination
- **Tracking** — Manage borrow transactions; approve/refuse pending requests; mark returns
- **Retrieval** — Browse and restore archived documents
- **Backup** — Backup individual or all documents; track backup status
- **Users** — Full CRUD for user accounts with role management
- **Audit Trail** — Complete event log with filtering by action, date range, and user

### User
- **Profile** — Update personal info and change password
- **My Documents** — View owned documents and borrow history; submit borrow requests

---

## Roles & Access

| Route pattern     | Required Role |
|-------------------|---------------|
| `/login.php`      | Public        |
| `/profile.php`    | USER or ADMIN |
| `/documents.php`  | USER          |
| `/admin/*`        | ADMIN only    |
| `/api/*`          | Authenticated |

Role enforcement is done in every page and API endpoint via `requireAuth()`.

---

## Security Notes

- Passwords hashed with `password_hash(..., PASSWORD_BCRYPT)`
- JWT tokens signed with HMAC-SHA256
- All DB queries use PDO prepared statements (no SQL injection)
- XSS protection: all output escaped with `htmlspecialchars()`
- `.htaccess` blocks direct access to `.sql`, `.json`, and `includes/` files
- HTTP security headers set via `.htaccess`
