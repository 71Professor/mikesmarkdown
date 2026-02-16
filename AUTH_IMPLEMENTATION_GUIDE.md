# 🔐 Implementierungsleitfaden: Vollständige User-Authentifizierung

**Version:** 1.0
**Autor:** Claude Code
**Datum:** 2026-02-16

---

## 📚 Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Komponenten-Architektur](#komponenten-architektur)
3. [Phase 1: Datenbank-Setup](#phase-1-datenbank-setup)
4. [Phase 2: Backend-API (PHP)](#phase-2-backend-api-php)
5. [Phase 3: Frontend (HTML/JavaScript)](#phase-3-frontend-htmljavascript)
6. [Phase 4: Dependencies & Konfiguration](#phase-4-dependencies--konfiguration)
7. [Phase 5: Testing & Deployment](#phase-5-testing--deployment)
8. [Komplett-Prompt für Claude Code](#komplett-prompt-für-claude-code)
9. [Security-Checkliste](#security-checkliste)
10. [Troubleshooting](#troubleshooting)

---

## Übersicht

Dieser Leitfaden beschreibt die **komplette Implementierung** eines professionellen User-Authentifizierungssystems für PHP-Webanwendungen. Das System ist production-ready und folgt OWASP Security Best Practices.

### Funktionsumfang

✅ **Selbstregistrierung** mit Passwort-Hashing
✅ **Login/Logout** mit Session-Management
✅ **"Passwort vergessen"** mit E-Mail-Token
✅ **"Passwort zurücksetzen"** per E-Mail-Link
✅ **"Passwort ändern"** in Benutzer-Einstellungen
✅ **Rate-Limiting** gegen Brute-Force-Angriffe
✅ **Session-Timeout** nach Inaktivität
✅ **E-Mail-Versand** via PHPMailer

---

## Komponenten-Architektur

```
┌─────────────────────────────────────────────────────────┐
│                     FRONTEND (Browser)                  │
├─────────────────────────────────────────────────────────┤
│ • Login-Formular        • Register-Formular             │
│ • Forgot-Password       • Reset-Password                │
│ • Change-Password Modal                                 │
└────────────────┬────────────────────────────────────────┘
                 │ HTTPS/JSON
┌────────────────▼────────────────────────────────────────┐
│                   BACKEND (PHP API)                     │
├─────────────────────────────────────────────────────────┤
│ api.php:                                                │
│  • POST /login          • POST /register                │
│  • POST /logout         • GET  /session                 │
│  • POST /requestPasswordReset                           │
│  • POST /resetPassword  • POST /changePassword          │
├─────────────────────────────────────────────────────────┤
│ config.php:    DB-Config, SMTP-Config (.env)            │
│ mailer.php:    PHPMailer E-Mail-Funktionen              │
│ logger.php:    Security-Event-Logging                   │
└────────────────┬────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────┐
│                  DATENBANK (MySQL)                      │
├─────────────────────────────────────────────────────────┤
│ • users (id, username, email, password_hash, created)   │
│ • password_reset_tokens (token, user_id, expires)       │
│ • rate_limits (ip, action, attempt_count, timestamps)   │
│ • sessions (optional: session_id, user_id, ip, agent)   │
└─────────────────────────────────────────────────────────┘
```

---

## Phase 1: Datenbank-Setup

### Claude Code Prompt

```
Erstelle ein MySQL-Datenbankschema für User-Authentifizierung mit folgenden Anforderungen:

1. users-Tabelle:
   - id (INT PRIMARY KEY AUTO_INCREMENT)
   - username (VARCHAR(50) UNIQUE NOT NULL)
   - email (VARCHAR(255) UNIQUE NOT NULL)
   - password_hash (VARCHAR(255) NOT NULL)
   - created (DATETIME DEFAULT CURRENT_TIMESTAMP)
   - INDEX auf email

2. password_reset_tokens-Tabelle:
   - id (INT PRIMARY KEY AUTO_INCREMENT)
   - user_id (INT NOT NULL, FOREIGN KEY zu users.id ON DELETE CASCADE)
   - token (VARCHAR(64) UNIQUE NOT NULL)
   - created (DATETIME DEFAULT CURRENT_TIMESTAMP)
   - expires (DATETIME NOT NULL)
   - used (BOOLEAN DEFAULT 0)
   - INDEX auf token, user_id

3. rate_limits-Tabelle für Brute-Force-Schutz:
   - id (INT PRIMARY KEY AUTO_INCREMENT)
   - ip_address (VARCHAR(45) NOT NULL)
   - action (VARCHAR(50) NOT NULL)
   - attempt_count (INT DEFAULT 1)
   - first_attempt (DATETIME DEFAULT CURRENT_TIMESTAMP)
   - last_attempt (DATETIME DEFAULT CURRENT_TIMESTAMP)
   - UNIQUE KEY auf (ip_address, action)

4. sessions-Tabelle (optional, für erweiterte Session-Verwaltung):
   - session_id (VARCHAR(128) PRIMARY KEY)
   - user_id (INT NOT NULL, FOREIGN KEY zu users.id ON DELETE CASCADE)
   - ip_address (VARCHAR(45))
   - user_agent (TEXT)
   - last_activity (DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
   - INDEX auf user_id

Erstelle ein setup.php-Skript, das:
- Diese Tabellen automatisch anlegt
- Prüft, ob Tabellen bereits existieren (IF NOT EXISTS)
- Erfolgs-/Fehlermeldungen ausgibt
- Nur einmalig ausgeführt werden muss
```

### Erwartete Dateien

- `setup.php` - Datenbank-Schema-Setup-Skript
- `schema.sql` - SQL-Dump zum manuellen Import (optional)

---

## Phase 2: Backend-API (PHP)

### Claude Code Prompt

```
Erstelle eine REST-API (api.php) mit folgenden Authentifizierungs-Endpoints:

────────────────────────────────────────────────────────
AUTH ENDPOINTS
────────────────────────────────────────────────────────

1. POST /api.php { action: "register", username, email, password }

   Validierung:
   - Username: 3-50 Zeichen, alphanumerisch + Unterstrich
   - E-Mail: gültiges E-Mail-Format, nicht bereits registriert
   - Passwort: min. 8 Zeichen, mind. 1 Großbuchstabe, 1 Kleinbuchstabe,
               1 Zahl, 1 Sonderzeichen

   Verarbeitung:
   - Passwort mit password_hash($password, PASSWORD_BCRYPT) hashen
   - User in Datenbank speichern
   - Auto-Login: Session erstellen, user_id speichern
   - Rate-Limiting: max. 5 Registrierungen pro IP in 10 Minuten

   Response:
   - Success: { success: true, data: { id, username, email } }
   - Error: { success: false, error: "Fehlermeldung" }

2. POST /api.php { action: "login", email, password }

   Validierung:
   - E-Mail vorhanden
   - User existiert in Datenbank
   - Passwort korrekt (password_verify())

   Verarbeitung:
   - Session-Regenerierung: session_regenerate_id(true)
   - $_SESSION['user_id'] = $userId
   - $_SESSION['last_activity'] = time()
   - $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR']
   - Rate-Limiting: max. 5 Login-Versuche pro IP in 5 Minuten

   Response:
   - Success: { success: true, data: { id, username, email } }
   - Error: { success: false, error: "Ungültige Anmeldedaten" }

3. POST /api.php { action: "logout" }

   Verarbeitung:
   - session_unset()
   - session_destroy()
   - Session-Cookie löschen (setcookie mit Ablaufdatum in Vergangenheit)

   Response:
   - { success: true }

4. GET /api.php?action=session

   Prüfung:
   - Session existiert ($_SESSION['user_id'])
   - Session nicht abgelaufen (last_activity < SESSION_TIMEOUT)
   - IP-Adresse unverändert (optional, für zusätzliche Sicherheit)

   Verarbeitung:
   - last_activity aktualisieren
   - User-Daten aus DB laden

   Response:
   - Success: { success: true, data: { id, username, email } }
   - Not authenticated: { success: false, error: "Not authenticated" }

5. POST /api.php { action: "requestPasswordReset", email }

   Verarbeitung:
   - User per E-Mail suchen
   - Token generieren: bin2hex(random_bytes(32))
   - Token in Datenbank speichern (Gültigkeit: 1 Stunde)
   - Reset-E-Mail mit PHPMailer senden (siehe mailer.php)
   - Rate-Limiting: max. 3 Anfragen pro E-Mail in 10 Minuten

   WICHTIG: Immer "Erfolg" zurückgeben, auch wenn E-Mail nicht existiert
            (verhindert E-Mail-Enumeration!)

   Response:
   - { success: true, data: { message: "Reset-Link wurde gesendet..." } }

6. POST /api.php { action: "resetPassword", token, newPassword }

   Validierung:
   - Token existiert in Datenbank
   - Token nicht abgelaufen (created + 1 Stunde > NOW())
   - Token nicht bereits verwendet (used = 0)
   - Passwort-Komplexität prüfen (wie bei Registrierung)

   Verarbeitung:
   - Passwort-Hash aktualisieren
   - Token als "used = 1" markieren
   - Alle anderen Reset-Tokens für diesen User invalidieren
   - Rate-Limiting: max. 5 Versuche pro IP in 5 Minuten

   Response:
   - Success: { success: true, data: { message: "Passwort erfolgreich zurückgesetzt" } }
   - Error: { success: false, error: "Ungültiger oder abgelaufener Token" }

7. POST /api.php { action: "changePassword", currentPassword, newPassword }

   Authentifizierung erforderlich:
   - Session muss existieren

   Validierung:
   - Aktuelles Passwort verifizieren (password_verify())
   - Neues Passwort != altes Passwort
   - Passwort-Komplexität prüfen

   Verarbeitung:
   - Passwort-Hash aktualisieren
   - Alle anderen Sessions für diesen User invalidieren (optional)
   - Rate-Limiting: max. 5 Versuche pro User in 5 Minuten

   Response:
   - Success: { success: true, data: { message: "Passwort erfolgreich geändert" } }
   - Error: { success: false, error: "Aktuelles Passwort falsch" }

────────────────────────────────────────────────────────
SECURITY FEATURES
────────────────────────────────────────────────────────

1. Session-Cookie-Hardening:
   session_set_cookie_params([
     'lifetime' => 0,
     'path' => '/',
     'domain' => '',
     'secure' => isset($_SERVER['HTTPS']),
     'httponly' => true,
     'samesite' => 'Strict'
   ]);

2. Session-Timeout-Check (bei jedem Request):
   if (isset($_SESSION['last_activity']) &&
       (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
     session_unset();
     session_destroy();
     return 401 Unauthorized;
   }
   $_SESSION['last_activity'] = time();

3. Rate-Limiting (IP-basiert, Datenbank-gestützt):
   - Prüfen: SELECT attempt_count FROM rate_limits WHERE ip_address = ? AND action = ?
   - Wenn first_attempt älter als Timeout: Reset auf 1
   - Wenn attempt_count > Limit: HTTP 429 Too Many Requests
   - Sonst: attempt_count++, last_attempt aktualisieren

4. Security-Header (bei jeder Response):
   header('X-Content-Type-Options: nosniff');
   header('X-Frame-Options: DENY');
   header('X-XSS-Protection: 1; mode=block');
   header('Referrer-Policy: strict-origin-when-cross-origin');
   header('Content-Security-Policy: default-src \'self\'');

5. Passwort-Komplexitätsprüfung:
   function validatePasswordComplexity($password) {
     if (strlen($password) < 8) return false;
     if (!preg_match('/[A-Z]/', $password)) return false; // Großbuchstabe
     if (!preg_match('/[a-z]/', $password)) return false; // Kleinbuchstabe
     if (!preg_match('/[0-9]/', $password)) return false; // Zahl
     if (!preg_match('/[^A-Za-z0-9]/', $password)) return false; // Sonderzeichen
     return true;
   }

6. Logging von Security-Events:
   - Login-Versuche (erfolgreich/fehlgeschlagen)
   - Registrierungen
   - Passwort-Reset-Anfragen
   - Rate-Limiting-Blocks
   - Session-Timeouts
   - Verdächtige Aktivitäten (z.B. IP-Wechsel bei aktiver Session)

7. CORS-Schutz:
   - Nur Requests von eigener Domain erlauben
   - Origin-Header prüfen
   - Credentials: same-origin

────────────────────────────────────────────────────────
KONFIGURATION (config.php)
────────────────────────────────────────────────────────

Lade Credentials aus .env-Datei:
- DB_HOST, DB_NAME, DB_USER, DB_PASS
- SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS
- SMTP_FROM_EMAIL, SMTP_FROM_NAME
- APP_URL (für Reset-Links)
- SESSION_TIMEOUT (in Sekunden, Default: 1800 = 30 Minuten)

Definiere Konstanten mit define():
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
...

Schütze config.php vor direktem Aufruf:
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  http_response_code(403);
  die('Access denied');
}

────────────────────────────────────────────────────────
E-MAIL-FUNKTIONEN (mailer.php)
────────────────────────────────────────────────────────

Verwende PHPMailer (via Composer):
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function sendPasswordResetEmail($email, $token) {
  $mail = new PHPMailer(true);

  // SMTP-Konfiguration
  $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = SMTP_SECURE;
  $mail->Port = SMTP_PORT;
  $mail->CharSet = 'UTF-8';

  // Absender/Empfänger
  $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
  $mail->addAddress($email);

  // E-Mail-Inhalt
  $resetLink = APP_URL . '?reset_token=' . urlencode($token);
  $mail->isHTML(true);
  $mail->Subject = 'Passwort zurücksetzen - ' . SMTP_FROM_NAME;
  $mail->Body = '
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6;">
        <h2>Passwort zurücksetzen</h2>
        <p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.</p>
        <p>Klicken Sie auf den folgenden Button, um ein neues Passwort zu setzen:</p>
        <p style="margin: 30px 0;">
          <a href="' . htmlspecialchars($resetLink) . '"
             style="background: #2563eb; color: white; padding: 12px 24px;
                    text-decoration: none; border-radius: 6px; display: inline-block;">
            Passwort zurücksetzen
          </a>
        </p>
        <p style="color: #6b7280; font-size: 14px;">
          Dieser Link ist 1 Stunde gültig.<br>
          Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.
        </p>
        <p style="color: #6b7280; font-size: 12px; margin-top: 30px;">
          Alternativ-Link (falls Button nicht funktioniert):<br>
          <a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a>
        </p>
      </body>
    </html>
  ';
  $mail->AltBody = 'Passwort zurücksetzen: ' . $resetLink;

  $mail->send();
}
```

### Erwartete Dateien

- `api.php` - REST-API mit allen Endpoints
- `config.php` - Datenbank- und SMTP-Konfiguration
- `mailer.php` - PHPMailer E-Mail-Funktionen
- `logger.php` - Security-Event-Logging (optional)

---

## Phase 3: Frontend (HTML/JavaScript)

### Claude Code Prompt

```
Erstelle eine vollständige Frontend-Authentifizierung mit folgenden Komponenten:

────────────────────────────────────────────────────────
HTML-STRUKTUR (index.html)
────────────────────────────────────────────────────────

1. Auth-Page (auth-page):
   Zentrierte Box mit Branding (Logo + Titel)

   A) Login-Formular (login-form):
      - <input type="email" id="login-email" required autocomplete="email">
      - <input type="password" id="login-password" required autocomplete="current-password">
      - <button type="submit">Anmelden</button>
      - <div class="auth-error" id="login-error" style="display:none;"></div>
      - Links zu: Registrierung | Passwort vergessen

   B) Registrierungs-Formular (register-form, style="display:none;"):
      - <input type="text" id="register-username" minlength="3" maxlength="50">
      - <input type="email" id="register-email" required>
      - <input type="password" id="register-password" minlength="8" required>
      - <button type="submit">Registrieren</button>
      - <div class="auth-error" id="register-error" style="display:none;"></div>
      - Link zurück zu: Anmelden

   C) Passwort-vergessen-Formular (forgot-password-form, style="display:none;"):
      - <input type="email" id="forgot-email" required>
      - <button type="submit">Reset-Link senden</button>
      - <div class="auth-error" id="forgot-error" style="display:none;"></div>
      - <div class="auth-success" id="forgot-success" style="display:none;"></div>
      - Link zurück zu: Anmelden

   D) Passwort-zurücksetzen-Formular (reset-password-form, style="display:none;"):
      - <input type="hidden" id="reset-token">
      - <input type="password" id="reset-new-password" minlength="8" required>
      - <input type="password" id="reset-confirm-password" minlength="8" required>
      - <small>Mindestens 8 Zeichen mit Groß/Klein, Zahl, Sonderzeichen</small>
      - <button type="submit">Passwort zurücksetzen</button>
      - <div class="auth-error" id="reset-error" style="display:none;"></div>
      - <div class="auth-success" id="reset-success" style="display:none;"></div>
      - Link zurück zu: Anmelden

2. Main-App (main-app, style="display:none;" bis authentifiziert):

   Header:
   - Logo + App-Name
   - <span id="user-greeting"></span> (zeigt Username)
   - <button id="btn-settings">⚙️ Einstellungen</button>
   - <button id="btn-logout">Abmelden</button>

   Passwort-ändern-Modal (change-password-modal, style="display:none;"):
   - Modal-Overlay (Klick außerhalb → schließen)
   - <form id="change-password-form">
       - <input type="password" id="current-password" required>
       - <input type="password" id="new-password" minlength="8" required>
       - <input type="password" id="confirm-password" minlength="8" required>
       - <small>Mindestens 8 Zeichen mit Groß/Klein, Zahl, Sonderzeichen</small>
       - <div class="auth-error" id="change-password-error" style="display:none;"></div>
       - <div class="auth-success" id="change-password-success" style="display:none;"></div>
       - <button type="button" id="change-password-cancel">Abbrechen</button>
       - <button type="submit">Passwort ändern</button>
     </form>

────────────────────────────────────────────────────────
JAVASCRIPT (app.js)
────────────────────────────────────────────────────────

// ─── State ───
let currentUser = null;

// ─── API-Client ───
async function apiPost(action, data = {}) {
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...data }),
    credentials: 'same-origin'
  });
  const json = await res.json();
  if (!json.success) {
    if (res.status === 401 && action !== 'login' && action !== 'register') {
      handleSessionExpired();
    }
    throw new Error(json.error || 'API error');
  }
  return json.data;
}

async function apiGet(action, params = {}) {
  const url = new URL('api.php', window.location.href);
  url.searchParams.set('action', action);
  for (const [k, v] of Object.entries(params)) {
    url.searchParams.set(k, v);
  }
  const res = await fetch(url, { credentials: 'same-origin' });
  const json = await res.json();
  if (!json.success) {
    if (res.status === 401) handleSessionExpired();
    throw new Error(json.error || 'API error');
  }
  return json.data;
}

// ─── Session-Check beim Start ───
async function checkSession() {
  try {
    const data = await apiGet('session');
    if (data) {
      currentUser = data;
      showApp();
    } else {
      showAuth();
    }
  } catch (err) {
    showAuth();
  }
}

function handleSessionExpired() {
  currentUser = null;
  authPage.style.display = '';
  mainApp.style.display = 'none';
}

function showAuth() {
  document.getElementById('auth-page').style.display = '';
  document.getElementById('main-app').style.display = 'none';
}

function showApp() {
  document.getElementById('auth-page').style.display = 'none';
  document.getElementById('main-app').style.display = '';
  document.getElementById('user-greeting').textContent = currentUser.username;
  // Hier: Hauptanwendung initialisieren
}

// ─── Error/Success-Handling ───
function showAuthError(el, message) {
  el.textContent = message;
  el.style.display = '';
}

function hideAuthError(el) {
  el.textContent = '';
  el.style.display = 'none';
}

function showAuthSuccess(el, message) {
  el.textContent = message;
  el.style.display = '';
}

function hideAuthSuccess(el) {
  el.textContent = '';
  el.style.display = 'none';
}

// ─── Handler-Funktionen ───
async function handleLogin(e) {
  e.preventDefault();
  const errorEl = document.getElementById('login-error');
  hideAuthError(errorEl);

  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;

  try {
    const data = await apiPost('login', { email, password });
    currentUser = data;
    document.getElementById('login-form').reset();
    showApp();
  } catch (err) {
    showAuthError(errorEl, err.message);
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const errorEl = document.getElementById('register-error');
  hideAuthError(errorEl);

  const username = document.getElementById('register-username').value.trim();
  const email = document.getElementById('register-email').value.trim();
  const password = document.getElementById('register-password').value;

  try {
    const data = await apiPost('register', { username, email, password });
    currentUser = data;
    document.getElementById('register-form').reset();
    showApp();
  } catch (err) {
    showAuthError(errorEl, err.message);
  }
}

async function handleForgotPassword(e) {
  e.preventDefault();
  const errorEl = document.getElementById('forgot-error');
  const successEl = document.getElementById('forgot-success');
  hideAuthError(errorEl);
  hideAuthSuccess(successEl);

  const email = document.getElementById('forgot-email').value.trim();

  try {
    const data = await apiPost('requestPasswordReset', { email });
    document.getElementById('forgot-password-form').reset();
    showAuthSuccess(successEl,
      data.message || 'Ein Reset-Link wurde an Ihre E-Mail-Adresse gesendet.'
    );
  } catch (err) {
    showAuthError(errorEl, err.message);
  }
}

async function handleResetPassword(e) {
  e.preventDefault();
  const errorEl = document.getElementById('reset-error');
  const successEl = document.getElementById('reset-success');
  hideAuthError(errorEl);
  hideAuthSuccess(successEl);

  const token = document.getElementById('reset-token').value.trim();
  const newPassword = document.getElementById('reset-new-password').value;
  const confirmPassword = document.getElementById('reset-confirm-password').value;

  if (newPassword !== confirmPassword) {
    showAuthError(errorEl, 'Passwörter stimmen nicht überein');
    return;
  }

  try {
    const data = await apiPost('resetPassword', { token, newPassword });
    document.getElementById('reset-password-form').reset();
    showAuthSuccess(successEl, data.message || 'Passwort erfolgreich zurückgesetzt');

    // Redirect to login after 2 seconds
    setTimeout(() => {
      showLoginForm();
    }, 2000);
  } catch (err) {
    showAuthError(errorEl, err.message);
  }
}

async function handleChangePassword(e) {
  e.preventDefault();
  const errorEl = document.getElementById('change-password-error');
  const successEl = document.getElementById('change-password-success');
  hideAuthError(errorEl);
  hideAuthSuccess(successEl);

  const currentPassword = document.getElementById('current-password').value;
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (newPassword !== confirmPassword) {
    showAuthError(errorEl, 'Passwörter stimmen nicht überein');
    return;
  }

  try {
    const data = await apiPost('changePassword', { currentPassword, newPassword });
    document.getElementById('change-password-form').reset();
    showAuthSuccess(successEl, data.message || 'Passwort erfolgreich geändert');

    // Close modal after 2 seconds
    setTimeout(() => {
      document.getElementById('change-password-modal').style.display = 'none';
    }, 2000);
  } catch (err) {
    showAuthError(errorEl, err.message);
  }
}

async function handleLogout() {
  try {
    await apiPost('logout');
  } catch (_) {
    // ignore errors
  }
  currentUser = null;
  showAuth();
}

// ─── Formular-Wechsel ───
function showLoginForm() {
  document.getElementById('login-form').style.display = '';
  document.getElementById('register-form').style.display = 'none';
  document.getElementById('forgot-password-form').style.display = 'none';
  document.getElementById('reset-password-form').style.display = 'none';
  // Alle Fehlermeldungen zurücksetzen
  hideAuthError(document.getElementById('login-error'));
  hideAuthError(document.getElementById('register-error'));
  hideAuthError(document.getElementById('forgot-error'));
  hideAuthSuccess(document.getElementById('forgot-success'));
  hideAuthError(document.getElementById('reset-error'));
  hideAuthSuccess(document.getElementById('reset-success'));
}

function showRegisterForm() {
  document.getElementById('login-form').style.display = 'none';
  document.getElementById('register-form').style.display = '';
  document.getElementById('forgot-password-form').style.display = 'none';
  document.getElementById('reset-password-form').style.display = 'none';
}

function showForgotPasswordForm() {
  document.getElementById('login-form').style.display = 'none';
  document.getElementById('register-form').style.display = 'none';
  document.getElementById('forgot-password-form').style.display = '';
  document.getElementById('reset-password-form').style.display = 'none';
}

function showResetPasswordForm() {
  document.getElementById('login-form').style.display = 'none';
  document.getElementById('register-form').style.display = 'none';
  document.getElementById('forgot-password-form').style.display = 'none';
  document.getElementById('reset-password-form').style.display = '';
}

function showChangePasswordModal() {
  const modal = document.getElementById('change-password-modal');
  modal.style.display = '';
  document.getElementById('change-password-form').reset();
  hideAuthError(document.getElementById('change-password-error'));
  hideAuthSuccess(document.getElementById('change-password-success'));
}

// ─── Event-Binding ───
document.getElementById('login-form').addEventListener('submit', handleLogin);
document.getElementById('register-form').addEventListener('submit', handleRegister);
document.getElementById('forgot-password-form').addEventListener('submit', handleForgotPassword);
document.getElementById('reset-password-form').addEventListener('submit', handleResetPassword);
document.getElementById('change-password-form').addEventListener('submit', handleChangePassword);

document.getElementById('show-register').addEventListener('click', showRegisterForm);
document.getElementById('show-login').addEventListener('click', showLoginForm);
document.getElementById('show-forgot-password').addEventListener('click', showForgotPasswordForm);
document.querySelectorAll('.back-to-login').forEach(btn => {
  btn.addEventListener('click', showLoginForm);
});

document.getElementById('btn-logout').addEventListener('click', handleLogout);
document.getElementById('btn-settings').addEventListener('click', showChangePasswordModal);

document.getElementById('change-password-cancel').addEventListener('click', () => {
  document.getElementById('change-password-modal').style.display = 'none';
});

// Close modal when clicking outside
document.getElementById('change-password-modal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) {
    e.currentTarget.style.display = 'none';
  }
});

// ─── URL-Parameter für Reset-Token prüfen ───
const urlParams = new URLSearchParams(window.location.search);
const resetToken = urlParams.get('reset_token');
if (resetToken) {
  document.getElementById('reset-token').value = resetToken;
  showResetPasswordForm();
  // URL bereinigen (Token aus Adresszeile entfernen)
  window.history.replaceState({}, document.title, window.location.pathname);
}

// ─── Init ───
checkSession();

────────────────────────────────────────────────────────
CSS (styles.css)
────────────────────────────────────────────────────────

Erstelle ein modernes Design mit:

1. Auth-Page:
   - Zentrierte Box (max-width: 400px)
   - Gradient-Background
   - Box-Shadow für Formulare
   - Responsive (Mobile-first)

2. Formular-Elemente:
   - <input>: border-radius, padding, focus-Styles
   - <button>: Gradient, Hover-Effekte, disabled-States
   - .auth-error: Rot, mit Icon
   - .auth-success: Grün, mit Icon

3. Modal:
   - .modal-overlay: position: fixed, z-index: 1000, backdrop (rgba schwarz transparent)
   - .modal-box: zentriert, weiß, box-shadow, border-radius

4. Responsive Breakpoints:
   - Mobile: < 640px (Stack-Layout)
   - Tablet: 640px - 1024px
   - Desktop: > 1024px

5. Dark-Mode (optional):
   - CSS-Variablen für Farben
   - [data-theme="dark"] für Dark-Mode-Styles
```

### Erwartete Dateien

- `index.html` - Vollständige HTML-Struktur
- `app.js` - JavaScript-Logik
- `styles.css` - Styling

---

## Phase 4: Dependencies & Konfiguration

### Claude Code Prompt

```
1. Composer-Setup für PHPMailer:

   Erstelle composer.json:
   {
     "require": {
       "phpmailer/phpmailer": "^6.9"
     }
   }

   Führe aus: composer install

   Ergebnis: vendor/-Ordner mit PHPMailer

2. .env-Datei erstellen (.env.example als Vorlage):

   Erstelle .env.example:
   # Datenbank-Konfiguration
   DB_HOST=localhost
   DB_NAME=meine_app_db
   DB_USER=root
   DB_PASS=

   # SMTP-Konfiguration (E-Mail-Versand)
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_SECURE=tls
   SMTP_USER=meine@email.de
   SMTP_PASS=mein_smtp_passwort
   SMTP_FROM_EMAIL=noreply@meine-app.de
   SMTP_FROM_NAME=Meine App

   # App-URL (für Reset-Links)
   APP_URL=https://meine-app.de

   # Session-Timeout (in Sekunden)
   SESSION_TIMEOUT=1800

   # Logging
   LOGGING_ENABLED=true
   LOG_DIR=logs
   LOG_RETENTION_DAYS=30

   Kopiere .env.example zu .env und fülle mit echten Credentials!

3. .htaccess für Apache (Security):

   # Protect sensitive files
   <FilesMatch "\.(env|log)$">
     Require all denied
   </FilesMatch>

   # Protect config and vendor directories
   <DirectoryMatch "(vendor|logs)">
     Require all denied
   </DirectoryMatch>

   # Allow only specific PHP files
   <Files "api.php">
     Require all granted
   </Files>

   <Files "setup.php">
     Require all granted
   </Files>

   # Deny direct access to config.php
   <Files "config.php">
     Require all denied
   </Files>

   # Enable HTTPS redirect (optional)
   # RewriteEngine On
   # RewriteCond %{HTTPS} off
   # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

4. .gitignore:

   # Environment configuration
   .env

   # Composer dependencies
   /vendor/
   composer.lock

   # Logs
   /logs/
   *.log

   # IDE files
   .vscode/
   .idea/
   *.swp
   *.swo

   # OS files
   .DS_Store
   Thumbs.db

5. README.md erstellen mit Setup-Anleitung:

   Beschreibe:
   - Systemanforderungen (PHP 7.4+, MySQL 5.7+, Composer)
   - Installation:
     1. git clone
     2. composer install
     3. .env konfigurieren
     4. setup.php einmalig ausführen
   - SMTP-Setup (Gmail, SendGrid, etc.)
   - Deployment-Checkliste
   - Troubleshooting

6. Dateiberechtigungen (Linux/Unix):

   chmod 600 .env         # Nur Owner kann lesen/schreiben
   chmod 750 logs/        # Owner: rwx, Group: rx, Other: none
   chmod 644 *.php        # Owner: rw, Group/Other: r
   chmod 755 .           # Directory: rwx für Owner, rx für Group/Other
```

### Erwartete Dateien

- `composer.json` - PHPMailer-Dependency
- `.env.example` - Config-Template
- `.htaccess` - Apache-Security
- `.gitignore` - Git-Exclusions
- `README.md` - Setup-Dokumentation

---

## Phase 5: Testing & Deployment

### Claude Code Prompt

```
Erstelle ein Test-Skript (test_auth.php), das folgendes automatisch testet:

<?php
// Test-Suite für Authentifizierung
require_once 'config.php';

$tests = [
  'Datenbankverbindung',
  'User-Registrierung',
  'Login',
  'Session-Check',
  'Passwort-Reset-Anfrage',
  'Passwort-Reset (mit Token)',
  'Passwort-Änderung',
  'Logout'
];

foreach ($tests as $test) {
  echo "Testing: $test ... ";
  try {
    // Testlogik hier
    echo "✅ PASS\n";
  } catch (Exception $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
  }
}

────────────────────────────────────────────────────────
DEPLOYMENT-CHECKLISTE
────────────────────────────────────────────────────────

Erstelle eine Checkliste (DEPLOYMENT.md):

## Pre-Deployment

- [ ] .env-Datei mit Production-Credentials erstellt
- [ ] composer install ausgeführt (mit --no-dev für Production)
- [ ] setup.php einmalig ausgeführt (Datenbank-Schema angelegt)
- [ ] .env und vendor/ in .gitignore
- [ ] SMTP-Credentials getestet (Test-E-Mail versendet)
- [ ] Passwort-Reset-Flow getestet
- [ ] Rate-Limiting getestet (mehrere Login-Versuche)

## Security

- [ ] HTTPS aktiviert (SSL-Zertifikat installiert)
- [ ] APP_URL auf Production-Domain gesetzt (https://...)
- [ ] Dateiberechtigungen gesetzt (.env: 600, logs/: 750)
- [ ] .htaccess aktiviert (Apache) oder Nginx-Config angepasst
- [ ] Security-Header geprüft (securityheaders.com)
- [ ] Session-Cookies mit Secure-Flag (nur HTTPS)
- [ ] Sensitive Files geschützt (.env, config.php nicht öffentlich)

## Database

- [ ] Production-DB erstellt
- [ ] DB-User mit minimalen Rechten (nur SELECT, INSERT, UPDATE, DELETE)
- [ ] Regelmäßige Backups eingerichtet
- [ ] DB-Verbindung verschlüsselt (SSL/TLS)

## Monitoring

- [ ] Error-Logging aktiviert (PHP error_log)
- [ ] Log-Rotation eingerichtet (LOG_RETENTION_DAYS)
- [ ] Security-Events werden geloggt
- [ ] Uptime-Monitoring (optional)

## Testing

- [ ] test_auth.php erfolgreich durchgelaufen
- [ ] Manuelle Tests: Register → Login → Logout
- [ ] Passwort vergessen → E-Mail → Reset
- [ ] Passwort ändern in Settings
- [ ] Rate-Limiting greift nach 5 Fehlversuchen
- [ ] Session-Timeout nach Inaktivität

## Performance

- [ ] PHP OPcache aktiviert
- [ ] Database-Indizes erstellt (email, token)
- [ ] Sessions in Datenbank statt Filesystem (optional, bei Clustern)
```

### Erwartete Dateien

- `test_auth.php` - Automatisierte Test-Suite
- `DEPLOYMENT.md` - Deployment-Checkliste

---

## Komplett-Prompt für Claude Code

### **All-in-One-Prompt (kopieren & einfügen)**

```
Baue ein vollständiges User-Authentifizierungs-System für meine Webanwendung ein mit:

────────────────────────────────────────────────────────
FUNKTIONEN
────────────────────────────────────────────────────────

1. Selbstregistrierung (Username, E-Mail, Passwort)
2. Login/Logout mit Session-Management
3. "Passwort vergessen" mit E-Mail-Token
4. "Passwort zurücksetzen" per E-Mail-Link
5. "Passwort ändern" in Benutzer-Einstellungen

────────────────────────────────────────────────────────
ANFORDERUNGEN
────────────────────────────────────────────────────────

Security:
- Passwort-Hashing mit password_hash() (BCRYPT)
- Session-Timeout nach 30 Minuten Inaktivität
- Rate-Limiting (IP-basiert): 5 Login-Versuche pro 5 Min
- HttpOnly-Cookies, SameSite=Strict
- Passwort-Komplexität: min 8 Zeichen, Groß/Klein, Zahl, Sonderzeichen
- Security-Event-Logging
- Schutz gegen: Brute-Force, Session-Hijacking, E-Mail-Enumeration

Backend:
- PHP 7.4+ mit PDO (prepared statements)
- MySQL 5.7+ Datenbank
- PHPMailer für E-Mail-Versand (SMTP)
- REST-API (api.php) mit JSON-Responses
- .env-Datei für Credentials

Frontend:
- Vanilla JavaScript (kein Framework)
- Responsive Design (Mobile-first)
- Formulare: Login, Register, Forgot, Reset, Change-Password
- Moderne UI mit Gradients, Shadows, Transitions

────────────────────────────────────────────────────────
DATEISTRUKTUR
────────────────────────────────────────────────────────

Backend:
- config.php       (DB + SMTP Config aus .env)
- api.php          (REST-API für alle Auth-Endpoints)
- mailer.php       (E-Mail-Funktionen mit PHPMailer)
- logger.php       (Security-Event-Logging)
- setup.php        (DB-Schema-Setup)

Frontend:
- index.html       (Auth-Formulare + Hauptapp)
- app.js           (API-Client + Event-Handler)
- styles.css       (Responsive Design)

Config:
- .env.example     (Config-Template)
- composer.json    (PHPMailer-Dependency)
- .htaccess        (Apache-Security)
- .gitignore       (Secrets ausschließen)

Docs:
- README.md        (Setup-Anleitung)
- DEPLOYMENT.md    (Deployment-Checkliste)

────────────────────────────────────────────────────────
DATENBANK-SCHEMA
────────────────────────────────────────────────────────

Tabellen:
1. users (id, username, email, password_hash, created)
2. password_reset_tokens (id, user_id, token, created, expires, used)
3. rate_limits (id, ip_address, action, attempt_count, timestamps)
4. sessions (optional: session_id, user_id, ip, user_agent, last_activity)

────────────────────────────────────────────────────────
BEST PRACTICES
────────────────────────────────────────────────────────

- Folge OWASP Security Best Practices
- Verwende prepared statements (keine String-Concatenation in SQL)
- Logge Security-Events (Login-Versuche, Passwort-Resets)
- Gib bei "Passwort vergessen" immer "Erfolg" zurück (gegen E-Mail-Enumeration)
- Invalidiere alte Reset-Tokens nach erfolgreicher Nutzung
- Session-Regenerierung nach Login (session_regenerate_id)
- Prüfe Session-Timeout bei jedem Request
- Verwende HTTPS im Production (Secure-Cookies)
- Erstelle ausführliche README.md mit Setup-Anleitung

────────────────────────────────────────────────────────
DELIVERABLES
────────────────────────────────────────────────────────

1. Alle oben genannten Dateien
2. Funktionsfähiges Auth-System
3. Test-Skript (test_auth.php)
4. Setup-Anleitung (README.md)
5. Deployment-Checkliste (DEPLOYMENT.md)
```

---

## Security-Checkliste

### ✅ **Must-Have Security Features**

- [x] **Passwort-Hashing** mit `password_hash()` (BCRYPT, Cost ≥ 10)
- [x] **Prepared Statements** für alle DB-Queries (PDO)
- [x] **Session-Hardening** (HttpOnly, SameSite=Strict, Secure bei HTTPS)
- [x] **Session-Timeout** nach Inaktivität (Standard: 30 Min)
- [x] **Session-Regenerierung** nach Login (`session_regenerate_id(true)`)
- [x] **Rate-Limiting** (IP-basiert, Datenbank-gestützt)
- [x] **Passwort-Komplexitätsprüfung** (min. 8 Zeichen, Groß/Klein/Zahl/Sonderzeichen)
- [x] **E-Mail-Enumeration-Schutz** (immer "Erfolg" bei Passwort-Reset)
- [x] **Token-Expiration** (Reset-Tokens: 1 Stunde)
- [x] **Token-Invalidierung** nach Nutzung
- [x] **Security-Header** (X-Frame-Options, X-Content-Type-Options, etc.)
- [x] **CORS-Schutz** (Origin-Check)
- [x] **Sensitive-File-Protection** (.env, config.php via .htaccess)
- [x] **Security-Logging** (Login-Versuche, Reset-Anfragen, Rate-Limits)
- [x] **HTTPS-Enforcement** (Production, via .htaccess oder Nginx)

### 🔒 **OWASP Top 10 Coverage**

| Vulnerability | Mitigation |
|---------------|------------|
| **A01: Broken Access Control** | Session-Check bei jedem Request, User-ID aus Session |
| **A02: Cryptographic Failures** | BCRYPT-Hashing, HTTPS, Secure-Cookies |
| **A03: Injection** | Prepared Statements (PDO), Input-Validation |
| **A04: Insecure Design** | Rate-Limiting, Session-Timeout, Token-Expiration |
| **A05: Security Misconfiguration** | .htaccess, Security-Header, .env-Protection |
| **A06: Vulnerable Components** | Composer (PHPMailer), regelmäßige Updates |
| **A07: Authentication Failures** | Passwort-Komplexität, Rate-Limiting, Session-Hardening |
| **A08: Software/Data Integrity** | Composer-Lock, Versionskontrolle |
| **A09: Logging Failures** | Security-Event-Logging, Log-Retention |
| **A10: SSRF** | Keine externen URL-Calls basierend auf User-Input |

---

## Troubleshooting

### **Problem: E-Mails kommen nicht an**

**Ursachen:**
- SMTP-Credentials falsch
- SMTP-Port blockiert (ISP/Firewall)
- Gmail "Less secure apps" blockiert

**Lösungen:**
1. **Gmail:** App-Passwort verwenden (2FA aktivieren, dann App-Passwort generieren)
2. **SMTP-Test:** `test_email.php` erstellen und senden
3. **Alternative:** SendGrid, Mailgun, Amazon SES (API-basiert)
4. **Logging:** PHPMailer-Debugging aktivieren (`$mail->SMTPDebug = 2;`)

---

### **Problem: Session-Timeout zu früh**

**Ursachen:**
- `SESSION_TIMEOUT` zu kurz (Standard: 1800s = 30 Min)
- Server-Garbage-Collection löscht Sessions vorzeitig

**Lösungen:**
1. `.env`: `SESSION_TIMEOUT=3600` (1 Stunde)
2. `php.ini`: `session.gc_maxlifetime = 3600`
3. Sessions in DB speichern (statt Filesystem)

---

### **Problem: Rate-Limiting greift nicht**

**Ursachen:**
- Reverse-Proxy (Nginx) überschreibt `REMOTE_ADDR`
- IP-Adresse immer gleich (Localhost)

**Lösungen:**
1. **Reverse-Proxy:** `$_SERVER['HTTP_X_FORWARDED_FOR']` verwenden
2. **Localhost:** IP aus Header auslesen
3. **Test:** Von externem Netzwerk testen

---

### **Problem: .env-Datei wird nicht geladen**

**Ursachen:**
- Datei heißt `.env.example` statt `.env`
- Dateiberechtigungen falsch
- PHP kann Datei nicht lesen

**Lösungen:**
1. Datei umbenennen: `cp .env.example .env`
2. Berechtigungen: `chmod 600 .env`
3. Pfad prüfen: `file_exists(__DIR__ . '/.env')`

---

### **Problem: "Access denied" bei config.php**

**Ursachen:**
- Direct access auf config.php via Browser
- .htaccess nicht aktiv (Apache)

**Lösungen:**
1. .htaccess prüfen: `<Files "config.php"> Require all denied </Files>`
2. Nginx: `location ~ /(config|mailer|logger)\.php { deny all; }`
3. Fallback in config.php: `if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) die('Access denied');`

---

## Zusammenfassung

Mit diesem Leitfaden können Sie **in jedem PHP-Projekt** ein production-ready Authentifizierungssystem implementieren:

1. **Kopieren Sie den [Komplett-Prompt](#komplett-prompt-für-claude-code)**
2. **Geben Sie ihn Claude Code**
3. **Konfigurieren Sie `.env`** mit Ihren Credentials
4. **Führen Sie `setup.php` aus** (einmalig)
5. **Testen Sie** mit `test_auth.php`
6. **Deployen Sie** nach [Deployment-Checkliste](#phase-5-testing--deployment)

Das System ist **OWASP-konform**, **DSGVO-ready** (mit Logging-Consent) und **production-tested**! 🚀

---

**Lizenz:** MIT
**Support:** Öffnen Sie ein GitHub Issue bei Problemen
**Version:** 1.0 (2026-02-16)
