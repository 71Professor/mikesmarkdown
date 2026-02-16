# SMTP Konfiguration für MikesMarkdown

Dieser Guide hilft Ihnen, die E-Mail-Funktionalität (Passwort-Reset) korrekt einzurichten.

## Schritt 1: .env Datei erstellen

```bash
cp .env.example .env
```

## Schritt 2: SMTP-Einstellungen konfigurieren

Bearbeiten Sie die `.env` Datei mit den korrekten KAS-Server-Einstellungen:

```env
# SMTP configuration for password reset emails
# KAS Server settings (All-Inkl)
SMTP_HOST=w01faadd.kasserver.com
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=passwort@mikesmarkdown.de
SMTP_PASS=IHR_ECHTES_PASSWORT_HIER
SMTP_FROM_EMAIL=passwort@mikesmarkdown.de
SMTP_FROM_NAME=MikesMarkdown
APP_URL=https://mikesmarkdown.de
```

**Wichtig:** Ersetzen Sie `IHR_ECHTES_PASSWORT_HIER` mit dem tatsächlichen Passwort!

## Schritt 3: Konfiguration testen

Führen Sie das Test-Script aus:

```bash
php test_smtp.php
```

Oder öffnen Sie im Browser:
```
https://mikesmarkdown.de/test_smtp.php
```

**⚠️ WICHTIG:** Löschen Sie `test_smtp.php` nach dem Test aus Sicherheitsgründen!

## Häufige Fehler und Lösungen

### ❌ "SMTP Error: Could not authenticate"

**Ursachen:**
1. Falsches Passwort in der `.env` Datei
2. Falscher Benutzername (muss vollständige E-Mail-Adresse sein)
3. E-Mail-Konto existiert nicht auf dem Server
4. Zwei-Faktor-Authentifizierung aktiviert (benötigt App-Passwort)

**Lösung:**
1. Überprüfen Sie das Passwort für `passwort@mikesmarkdown.de`
2. Stellen Sie sicher, dass das E-Mail-Konto im KAS-Server existiert
3. Versuchen Sie, sich mit den Credentials in einem E-Mail-Client anzumelden

### ❌ "SMTP connect() failed"

**Ursachen:**
1. Falsche Server-Adresse oder Port
2. Firewall blockiert ausgehende Verbindungen auf Port 465
3. SSL/TLS-Einstellungen falsch

**Lösung:**
1. Überprüfen Sie die Server-Adresse im KAS-Panel
2. Testen Sie die Verbindung: `telnet w01faadd.kasserver.com 465`
3. Verwenden Sie Port 587 mit TLS als Alternative

### ❌ ".env file not found"

**Lösung:**
```bash
cp .env.example .env
chmod 600 .env  # Nur für Besitzer lesbar
```

## Alternative SMTP-Einstellungen (falls Port 465 blockiert ist)

Wenn Port 465 nicht funktioniert, versuchen Sie:

```env
SMTP_HOST=w01faadd.kasserver.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=passwort@mikesmarkdown.de
SMTP_PASS=IHR_PASSWORT
```

## Sicherheitshinweise

1. **Niemals** die `.env` Datei in Git committen
2. `.gitignore` enthält bereits `.env`
3. Setzen Sie Dateiberechtigungen: `chmod 600 .env`
4. Löschen Sie `test_smtp.php` nach dem Testen

## KAS-Server SMTP-Einstellungen

Laut Ihrem KAS-Panel sollten die Einstellungen sein:

- **Server:** w01faadd.kasserver.com
- **Port:** 465
- **Verschlüsselung:** SSL
- **Authentifizierung:** Passwort
- **Benutzername:** passwort@mikesmarkdown.de

## Logs überprüfen

Bei Problemen überprüfen Sie die Logs:

```bash
tail -f logs/app-*.log | grep -i smtp
```

Die Logs enthalten detaillierte Informationen über SMTP-Verbindungen und Fehler.

## Support

Wenn Probleme weiterhin bestehen:

1. Überprüfen Sie die Logs in `logs/app-*.log`
2. Führen Sie `test_smtp.php` aus und teilen Sie die Ausgabe
3. Überprüfen Sie, ob das E-Mail-Konto im KAS-Panel korrekt konfiguriert ist

---

**Version:** 1.0
**Erstellt:** 2026-02-16
