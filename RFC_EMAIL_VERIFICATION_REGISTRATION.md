# RFC: Nutzerregistrierung mit E-Mail-Verifikation (mikesmarkdown.de Referenz)

- **Status:** Informational / Referenzimplementierung
- **Version:** 1.0
- **Datum:** 2026-02-19
- **Ziel:** Übertragbare Spezifikation für ein anderes Projekt

## 1. Zusammenfassung

Diese RFC beschreibt den in mikesmarkdown.de umgesetzten Registrierungsprozess mit E-Mail-Versand und Bestätigungslink.

Kernprinzipien:

1. Registrierung erstellt zunächst einen **nicht verifizierten** Nutzer.
2. Verifikation erfolgt über einen **zeitlich begrenzten, einmalig nutzbaren Token** per E-Mail-Link.
3. Login ist bis zur Bestätigung der E-Mail-Adresse gesperrt.
4. Sicherheitsmechanismen (Rate Limit, Enumeration-Schutz, Logging, Session-Härtung) sind integriert.

---

## 2. Architekturüberblick

### 2.1 Komponenten

- **Backend API (`api.php`)**
  - `register`
  - `verifyEmail`
  - `resendVerificationEmail`
  - `login` (mit Verifikations-Check)
- **Mailer (`mailer.php`)**
  - `sendVerificationEmail()` via PHPMailer + SMTP
- **Config (`config.php`)**
  - SMTP-/APP_URL-Konfiguration aus `.env`
- **Frontend (`app.js`)**
  - Registrierung
  - Token-Verarbeitung aus URL (`verify_email`)
  - Resend-Flow
- **DB Setup/Migration (`setup_pw.php`)**
  - Tabellen/Felder für Verifikation

### 2.2 Sequenz (vereinfacht)

1. User -> `POST register`
2. API validiert + erstellt User (unverifiziert)
3. API erzeugt Token + speichert Token (24h)
4. API versendet E-Mail mit `?verify_email=<token>`
5. User klickt Link -> Frontend ruft `verifyEmail` auf
6. API validiert Token und markiert User als verifiziert
7. Login ist nun möglich

---

## 3. Datenmodell

### 3.1 Tabelle `users`

Erweiterungen für Verifikation:

- `is_email_verified TINYINT(1) NOT NULL DEFAULT 0`
- `email_verified_at DATETIME NULL`
- Index `idx_email_verified (is_email_verified)`

### 3.2 Tabelle `email_verification_tokens`

- `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `user_id INT UNSIGNED NOT NULL` (FK -> `users.id`, `ON DELETE CASCADE`)
- `token VARCHAR(64) NOT NULL UNIQUE`
- `created DATETIME NOT NULL`
- `expires DATETIME NOT NULL`
- `used TINYINT(1) NOT NULL DEFAULT 0`
- Indexe: `idx_token`, `idx_expires`, `idx_user_id`

### 3.3 Invarianten

- Ein Token darf nur für **genau einen** User gelten.
- Ein Token ist nur gültig, wenn:
  - `used = 0`
  - `expires > now`
- Verifikation ist idempotent-freundlich (bereits verifiziert -> freundliche Meldung statt harter Fehler in bestimmten Pfaden).

---

## 4. API-Vertrag

## 4.1 `POST register`

### Request

```json
{
  "action": "register",
  "username": "string",
  "email": "string",
  "password": "string"
}
```

### Verhalten

1. Rate Limit: `register` max. 5 Versuche/10 Minuten.
2. Validierung von Feldern, E-Mail-Format, Passwort-Komplexität.
3. Prüfung auf bereits belegten Username/E-Mail.
4. User-Insert (initial unverifiziert).
5. Token-Erzeugung: `bin2hex(random_bytes(32))`.
6. Insert in `email_verification_tokens` (`expires = now + 24h`).
7. Versand via `sendVerificationEmail(email, token)`.
8. Antwort ohne Auto-Login.

### Response (Erfolg)

```json
{
  "ok": true,
  "data": {
    "message": "Registrierung erfolgreich! Bitte prüfen Sie Ihre E-Mails zur Bestätigung.",
    "email": "user@example.com"
  }
}
```

---

## 4.2 `POST verifyEmail`

### Request

```json
{
  "action": "verifyEmail",
  "token": "hex-token"
}
```

### Verhalten

1. Rate Limit: `email_verification` max. 10/10 Minuten.
2. Suche nach Token mit `used = 0` und `expires > now`.
3. Falls gültig:
   - `users.is_email_verified = 1`
   - `users.email_verified_at = now`
   - `email_verification_tokens.used = 1`
4. Falls ungültig/abgelaufen: Fehler.
5. Falls Token bekannt, User aber bereits verifiziert: Erfolgsmeldung "bereits bestätigt".

### Response (Erfolg)

```json
{
  "ok": true,
  "data": {
    "message": "Danke. Ihre E-Mail ist bestätigt. Loggen Sie sich jetzt auf der Login-Seite ein."
  }
}
```

---

## 4.3 `POST resendVerificationEmail`

### Request

```json
{
  "action": "resendVerificationEmail",
  "email": "user@example.com"
}
```

### Verhalten

1. Rate Limit: `resend_verification` max. 3/10 Minuten.
2. User per E-Mail suchen.
3. Bei unbekannter E-Mail: **immer generische Erfolgsmeldung** (Enumeration-Schutz).
4. Bei bereits verifizierter E-Mail: Fehler.
5. Bestehende Tokens des Users löschen.
6. Neuen Token erzeugen und speichern (24h).
7. Verifizierungs-E-Mail neu versenden.
8. Generische Erfolgsmeldung zurückgeben.

---

## 4.4 `POST login`

Nach Passwortprüfung zusätzliche Geschäftsregel:

- Wenn `is_email_verified = 0`, dann `403` mit Hinweis auf Bestätigung der E-Mail-Adresse.

---

## 5. Frontend-Verhalten

## 5.1 Registrierung

- Formular sendet `register`.
- Bei Erfolg: UI-Hinweis auf versendete Verifizierungs-E-Mail.

## 5.2 Verifikationslink

- Beim Laden der Seite wird `verify_email` aus Query-Param gelesen.
- Frontend ruft `verifyEmail` auf.
- Erfolg/Fehler wird im Login-Bereich dargestellt.
- Query-Parameter wird anschließend aus URL entfernt (`history.replaceState`).

## 5.3 Resend

- Bei Login-Fehler "nicht bestätigt" wird Link zum erneuten Versand eingeblendet.
- User kann E-Mail erneut anfordern (`resendVerificationEmail`).

---

## 6. E-Mail-Versand

## 6.1 Abhängigkeiten

- PHPMailer (`phpmailer/phpmailer`)

## 6.2 Konfiguration

Aus `.env` via `config.php`:

- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_SECURE`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`
- `APP_URL`

## 6.3 Linkformat

- Verifikation: `APP_URL + "?verify_email=" + urlencode(token)`

## 6.4 Inhalt

- HTML + Plaintext
- Hinweis auf 24h Gültigkeit

---

## 7. Sicherheitsanforderungen

1. **Token-Sicherheit**
   - Kryptographisch sicher: `random_bytes(32)`
   - Einmalnutzung (`used`)
   - Ablaufdatum (`expires`)
2. **Rate Limiting**
   - Register, Verify, Resend separat begrenzen
3. **Enumeration-Schutz**
   - Für Resend (und analog in weiteren Flows) generische Antworten
4. **Session-Härtung**
   - `HttpOnly`, `SameSite=Lax`, `use_strict_mode`
5. **Audit Logging**
   - Registrierung, Mailversand, Verifikation, Fehlversuche

---

## 8. Portierung in ein anderes Projekt (Implementierungsplan)

1. DB-Schema erweitern (`users`, `email_verification_tokens`).
2. Endpunkte `register`, `verifyEmail`, `resendVerificationEmail` implementieren.
3. Login-Guard auf `is_email_verified` setzen.
4. Mail-Service kapseln (`sendVerificationEmail`).
5. Frontend-Handling für `verify_email`-Query-Param ergänzen.
6. Rate Limits und Logging integrieren.
7. Security-Tests durchführen (abgelaufene/benutzte Tokens, Resend-Rotation, Brute-Force-Rate-Limits).

---

## 9. Akzeptanzkriterien

- [ ] Neu registrierter User hat `is_email_verified = 0`.
- [ ] Verifikations-Token wird erzeugt, gespeichert und per E-Mail versendet.
- [ ] Login vor Verifikation liefert 403.
- [ ] Gültiger Verifikationslink setzt `is_email_verified = 1`, `email_verified_at` und `used = 1`.
- [ ] Abgelaufene/ungültige Links schlagen kontrolliert fehl.
- [ ] Resend erstellt neuen Token und invalidiert alte Tokens.
- [ ] Nicht existierende E-Mails erzeugen keine Enumeration-Leaks.

---

## 10. Referenz auf konkrete Implementierungsstellen (mikesmarkdown)

- API-Flow: `api.php`
- Mailversand: `mailer.php`
- Frontend-Flow: `app.js`
- DB-Setup/Migration: `setup_pw.php`
- SMTP/APP_URL-Konfig: `config.php`

