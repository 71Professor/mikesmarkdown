# Deploying to all-inkl.com

This project uses PHP + MySQL for server-side note storage.

## Files to upload

- `index.html` – Frontend
- `styles.css` – Styles
- `app.js` – Frontend logic
- `api.php` – REST API backend
- `config.php` – Database credentials (create from template)
- `setup.php` – One-time database setup (delete after use)

## Setup

### 1. Create a database

1. Log in to KAS (https://kas.all-inkl.com/)
2. Go to **Datenbanken** → **Neue Datenbank anlegen**
3. Note the database name, username, and password

### 2. Configure

1. Copy `config.example.php` to `config.php`
2. Fill in your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'db12345678');
   define('DB_USER', 'db12345678');
   define('DB_PASS', 'your_password');
   ```

### 3. Upload files

1. Connect via FTP/SFTP to your all-inkl webspace
2. Upload all files to `htdocs/` (or your domain folder)

### 4. Initialize database

1. Open `https://your-domain.com/setup.php` in your browser
2. You should see "Table notes created successfully"
3. **Delete `setup.php` from the server** (security)

### 5. Done

Open `https://your-domain.com/` – your notes app is ready.

## Updating later

Re-upload `index.html`, `styles.css`, `app.js`, and `api.php` after changes.
Never overwrite `config.php` on the server (it contains your credentials).
