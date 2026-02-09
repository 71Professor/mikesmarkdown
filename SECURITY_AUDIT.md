# Security Audit Report – MikesMarkdown

**Datum:** 2026-02-09
**Projekt:** MikesMarkdown (Web-basierte Markdown-Notizen-App)
**Stack:** PHP 7.4+ / MySQL / Vanilla JavaScript (SPA)
**Auditor:** Automatisiertes Security Audit

---

## Zusammenfassung

MikesMarkdown ist eine Full-Stack-Webanwendung mit PHP-Backend und Vanilla-JavaScript-Frontend. Das Audit hat **keine kritischen Injection-Schwachstellen** gefunden. Die Anwendung nutzt durchgängig Prepared Statements, bcrypt-Passwort-Hashing und DOMPurify für XSS-Schutz. Es wurden jedoch mehrere Konfigurations- und Härtungsprobleme identifiziert und behoben.

### Risikobewertung: **MITTEL** (nach Fixes: NIEDRIG-MITTEL)

---

## 1. Behobene Schwachstellen

### 1.1 CORS-Fehlkonfiguration (HOCH)
- **Datei:** `api.php:29-32` (vorher)
- **Problem:** `Access-Control-Allow-Origin: *` mit `Access-Control-Allow-Credentials: true` ist widersprüchlich und ermöglichte potenziell Cross-Origin-Zugriff auf authentifizierte Endpoints.
- **Fix:** Wildcard-CORS-Header entfernt. Als Same-Origin-SPA werden keine CORS-Header benötigt.

### 1.2 Fehlende Session-Cookie-Härtung (HOCH)
- **Datei:** `api.php` (Session-Konfiguration)
- **Problem:** PHP-Standard-Session-Konfiguration ohne explizite HttpOnly, Secure, SameSite-Flags.
- **Fix:** Hinzugefügt:
  - `session.cookie_httponly = 1` (verhindert JavaScript-Zugriff auf Session-Cookie)
  - `session.cookie_samesite = Strict` (CSRF-Schutz)
  - `session.cookie_secure = 1` (nur bei HTTPS, verhindert Klartext-Übertragung)
  - `session.use_strict_mode = 1` (lehnt nicht-initialisierte Session-IDs ab)

### 1.3 Fehlende Security-Header (MITTEL)
- **Datei:** `api.php`, `.htaccess`
- **Problem:** Keine defensiven HTTP-Header vorhanden.
- **Fix:** Hinzugefügt:
  - `X-Content-Type-Options: nosniff` (verhindert MIME-Type-Sniffing)
  - `X-Frame-Options: DENY` (Clickjacking-Schutz)
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy: camera=(), microphone=(), geolocation=()`
  - `Content-Security-Policy` (schränkt Skript-/Ressourcen-Quellen ein)

### 1.4 Fehlendes Rate-Limiting (MITTEL)
- **Datei:** `api.php` (Login/Register)
- **Problem:** Kein Brute-Force-Schutz bei Login und Registrierung.
- **Fix:** Session-basiertes Rate-Limiting implementiert:
  - Login: max. 5 Versuche pro 5 Minuten pro IP
  - Registrierung: max. 5 Versuche pro 10 Minuten pro IP
  - Gibt HTTP 429 mit `Retry-After`-Header zurück

### 1.5 .git-Verzeichnis nicht blockiert (MITTEL)
- **Datei:** `.htaccess`
- **Problem:** Das `.git`-Verzeichnis war potenziell über HTTP erreichbar, was Quellcode-Leak ermöglicht.
- **Fix:** `RedirectMatch 404 /\.git` in `.htaccess` hinzugefügt.

### 1.6 setup.php nicht blockiert (HOCH)
- **Datei:** `.htaccess`
- **Problem:** `setup.php` war ohne Authentifizierung erreichbar und konnte die Datenbank neu initialisieren.
- **Fix:** `setup.php` wird jetzt per `.htaccess` blockiert. Für die Ersteinrichtung muss die Regel temporär entfernt werden.

### 1.7 Unversionierte CDN-Abhängigkeiten (MITTEL)
- **Datei:** `index.html`
- **Problem:** `marked.js` wurde ohne Versions-Pin geladen (`/npm/marked/marked.min.js`), was automatische Updates auf potenziell kompromittierte Versionen ermöglicht.
- **Fix:**
  - marked.js auf v17.0.1 gepinnt
  - SRI-Hash für highlight.js (v11.9.0) hinzugefügt
  - `crossorigin="anonymous"` für alle CDN-Scripts gesetzt

### 1.8 Directory Listing aktiv (NIEDRIG)
- **Datei:** `.htaccess`
- **Problem:** Apache-Standard erlaubt Verzeichnislisting.
- **Fix:** `Options -Indexes` hinzugefügt.

---

## 2. Bestehende Sicherheitsstärken

### 2.1 SQL-Injection-Schutz (STARK)
Alle Datenbankabfragen nutzen ausnahmslos Prepared Statements mit `bind_param()`:
- `api.php:159-160` – User-Lookup
- `api.php:172-173` – User-Insert
- `api.php:203-204` – Login-Query
- `api.php:272-278` – Notizen-Suche (LIKE mit Prepared Statement)
- Alle CRUD-Operationen nutzen parametrisierte Queries

### 2.2 Passwort-Sicherheit (STARK)
- `password_hash()` mit `PASSWORD_DEFAULT` (bcrypt) in `api.php:170`
- `password_verify()` für timing-sicheren Vergleich in `api.php:210`
- Mindestlänge 6 Zeichen bei Registrierung
- Passwort-Hash wird nie in Responses zurückgegeben

### 2.3 XSS-Schutz (STARK)
- **Frontend:** DOMPurify v3.0.6 sanitisiert allen Markdown-HTML-Output (`app.js:564`)
- **Frontend:** `escapeHtml()` Utility für Titel, Tags, Fehlermeldungen (`app.js:425-429`)
- **Backend:** `htmlspecialchars()` in `setup.php` für Fehlerausgaben
- Kein unsicheres `innerHTML` mit unsanitisiertem User-Content

### 2.4 Benutzer-Isolation (STARK)
- Alle Notiz-Queries enthalten `WHERE user_id = ?`
- Foreign-Key-Constraint auf `notes.user_id → users.id`
- Kein Zugriff auf fremde Notizen möglich

### 2.5 Session-Management (GUT)
- `session_regenerate_id(true)` nach Login (verhindert Session-Fixation)
- Vollständige Session-Zerstörung beim Logout
- `requireAuth()` Guard auf allen geschützten Endpoints
- `credentials: 'same-origin'` im Frontend-Fetch

### 2.6 Sensible Dateien geschützt (GUT)
- `.env` und `config.php` per `.htaccess` blockiert
- `.env` in `.gitignore` gelistet
- Keine Secrets im Quellcode

---

## 3. Verbleibende Empfehlungen

### 3.1 HTTPS erzwingen (HOCH, Deployment)
- Strict-Transport-Security (HSTS) Header konfigurieren
- HTTP→HTTPS-Redirect aktivieren (vorbereitete `.htaccess`-Regel entkommentieren)
- Ohne HTTPS werden Passwörter im Klartext übertragen

### 3.2 CSRF-Token-Schutz (MITTEL)
- Aktuell durch `SameSite=Strict`-Cookie-Attribut gemildert
- Für zusätzliche Sicherheit: serverseitige CSRF-Tokens pro Session implementieren
- Besonders relevant wenn `SameSite`-Support in älteren Browsern benötigt wird

### 3.3 SRI-Hashes vervollständigen (MITTEL)
- Highlight.js hat SRI-Hash ✓
- marked.js und DOMPurify benötigen noch SRI-Hashes
- Hashes generieren mit: `curl -s <URL> | openssl dgst -sha384 -binary | openssl base64 -A`

### 3.4 DOMPurify auf neueste Version aktualisieren (NIEDRIG)
- Aktuell: v3.0.6 (Oktober 2023)
- Neuere Versionen enthalten zusätzliche Bypass-Fixes
- Empfehlung: Update auf v3.2.x+

### 3.5 Passwort-Policy stärken (NIEDRIG)
- Aktuell: Minimum 6 Zeichen
- Empfehlung: Minimum 8 Zeichen, Komplexitätsanforderungen oder zxcvbn-basierte Prüfung

### 3.6 Content-Size-Limits (NIEDRIG)
- Kein Limit für Notiz-Inhalt (`LONGTEXT` = max 4GB)
- Kein Limit für Drag-and-Drop-Dateigröße
- Empfehlung: PHP `post_max_size` und Frontend-Limits setzen

### 3.7 setup.php entfernen (POST-DEPLOYMENT)
- Datei wird jetzt per `.htaccess` blockiert
- Sollte nach erstmaliger Einrichtung trotzdem vom Server gelöscht werden

### 3.8 Produktions-PHP-Konfiguration (DEPLOYMENT)
- `display_errors = Off`
- `expose_php = Off`
- `log_errors = On`
- `error_log = /path/to/secure/error.log`

---

## 4. Datei-Risiko-Matrix

| Datei | Risiko | Status |
|---|---|---|
| `api.php` | Backend-API, Auth, CRUD | **Gehärtet** ✓ |
| `config.php` | DB-Credentials | Geschützt via .htaccess ✓ |
| `setup.php` | DB-Initialisierung | Blockiert via .htaccess ✓ |
| `.env` | Credentials | Geschützt via .htaccess + .gitignore ✓ |
| `.htaccess` | Sicherheitsregeln | Erweitert ✓ |
| `index.html` | Frontend-Shell | CDN-Versions gepinnt ✓ |
| `app.js` | Client-Logik | DOMPurify + escapeHtml ✓ |
| `styles.css` | Styling | Kein Risiko |

---

## 5. OWASP Top 10 Prüfung

| # | Kategorie | Status | Details |
|---|---|---|---|
| A01 | Broken Access Control | **OK** | User-Isolation, requireAuth() Guard |
| A02 | Cryptographic Failures | **OK** | bcrypt Hashing, keine Klartext-Secrets |
| A03 | Injection | **OK** | Prepared Statements durchgängig |
| A04 | Insecure Design | **OK** | Saubere API-Architektur |
| A05 | Security Misconfiguration | **Behoben** | CORS, Headers, .htaccess gehärtet |
| A06 | Vulnerable Components | **Teilweise** | Versionen gepinnt, SRI teilweise |
| A07 | Auth Failures | **Behoben** | Rate-Limiting, Session-Härtung |
| A08 | Data Integrity Failures | **OK** | SRI für highlight.js |
| A09 | Logging & Monitoring | **Offen** | Kein Audit-Log implementiert |
| A10 | SSRF | **N/A** | Keine Server-seitigen URL-Abrufe |

---

*Bericht erstellt am 2026-02-09 im Rahmen eines automatisierten Security Audits.*
