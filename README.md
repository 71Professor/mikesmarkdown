# MikesMarkdown

Eine moderne, sichere und webbasierte Markdown-Notizen-App mit Live-Vorschau, Benutzerverwaltung und serverseitiger Speicherung.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?logo=javascript&logoColor=black)
![Security](https://img.shields.io/badge/Security-Audited-brightgreen?logo=shield)
![License](https://img.shields.io/badge/License-MIT-blue.svg)

## Features

- **Split-View-Editor** – Bearbeitung und Live-Vorschau nebeneinander mit verstellbarem Trennbalken.
- **Syntax-Highlighting** – Automatische Einfärbung von Code-Blöcken via Highlight.js.
- **Auto-Save** – Änderungen werden automatisch nach 1,2 Sekunden verzögerungsfrei gespeichert.
- **Benutzerverwaltung** – Sichere Registrierung mit E-Mail-Verifizierung, Login und Session-Management.
- **Passwort-Reset** – Sicheres Zurücksetzen des Passworts per E-Mail-Link (gültig 1 Stunde).
- **Passwort ändern** – Passwortänderung im eingeloggten Zustand mit Verifikation des aktuellen Passworts.
- **Notiz-Übersicht** – Dashboard mit Karten-Layout, Suche, Sortierung und Anheft-Funktion.
- **Tags & Metadata** – Unterstützung für YAML-Front-Matter zur Filterung von Notizen.
- **Dark Mode** – Automatische Erkennung der Systemeinstellung mit manuellem Toggle.
- **Export** – Download als Markdown oder gestyltes HTML sowie Kopie in die Zwischenablage.

## Sicherheit & Logging

Das Projekt wurde einem umfassenden Security-Audit unterzogen und gehärtet:

* **Schutz vor SQL-Injection**: Konsequente Nutzung von Prepared Statements für alle Datenbankoperationen.
* **XSS-Prävention**: Sanitisierung des Outputs durch **DOMPurify** und Escaping von User-Input.
* **Passwort-Sicherheit**: Hashing nach Industriestandard mittels `password_hash()` (bcrypt).
* **E-Mail-Verifizierung**: Neue Konten müssen per E-Mail-Link bestätigt werden, bevor ein Login möglich ist.
* **Passwort-Anforderungen**: Mindestens 8 Zeichen mit Groß-/Kleinbuchstaben, Ziffern und Sonderzeichen (Pflicht bei Registrierung und Passwort-Reset).
* **Gehärtetes Session-Management**: Nutzung von `HttpOnly`, `SameSite=Lax` und `Secure`-Flags für Cookies sowie automatischer Session-Timeout nach Inaktivität.
* **Brute-Force-Schutz**: Implementiertes Rate-Limiting für Login und Registrierung.
* **Logging-System**: Detailliertes internes Fehler- und Sicherheits-Logging mit automatischer Anonymisierung sensibler Daten.
* **Infrastruktur-Härtung**: Schutz sensibler Dateien wie `.env` und Sperrung des `.git`-Verzeichnisses via `.htaccess`.

## Technologien

| Bereich  | Technologie                                        |
|----------|----------------------------------------------------|
| Frontend | HTML5, CSS3, Vanilla JavaScript (ES6+)             |
| Backend  | PHP 7.4+, MySQL/MariaDB, Composer                  |
| Libs     | Marked.js, DOMPurify, Highlight.js, PHPMailer      |

## Installation

### Voraussetzungen
- Webserver mit PHP 7.4+ (z. B. Apache)
- MySQL- oder MariaDB-Datenbank
- [Composer](https://getcomposer.org/) (für PHP-Abhängigkeiten)
- SMTP-Zugang für den E-Mail-Versand (Passwort-Reset & E-Mail-Verifizierung)

### Einrichtung
1. **Repository klonen**: `git clone https://github.com/71Professor/mikesmarkdown.git`.
2. **PHP-Abhängigkeiten installieren**: `composer install` (installiert PHPMailer).
3. **Konfiguration**: Erstelle eine `.env` Datei basierend auf der `.env.example` und trage deine DB- und SMTP-Daten ein.
4. **Datenbank initialisieren**: Rufe `setup_pw.php` einmalig auf und lösche die Datei danach sofort vom Server.
5. **Logging**: Das System loggt standardmäßig nach `./logs`. Stelle sicher, dass das Verzeichnis existiert und beschreibbar ist.

## Projektstruktur

```text
mikesmarkdown/
├── index.html                       # Frontend-Shell
├── app.js                           # Frontend-Logik & XSS-Schutz
├── styles.css                       # Stylesheet
├── api.php                          # Gehärtetes REST-API-Backend
├── config.php                       # Datenbank- & App-Konfiguration
├── mailer.php                       # E-Mail-Versand via PHPMailer
├── logger.php                       # Internes Logging-System
├── setup_pw.php                     # Datenbank-Initialisierung (einmalig ausführen, dann löschen)
├── migration_email_verification.sql # SQL-Migration für bestehende Installationen
├── composer.json                    # PHP-Abhängigkeiten (PHPMailer)
├── .htaccess                        # Sicherheitsregeln & Header
├── AUTH_IMPLEMENTATION_GUIDE.md     # Implementierungsdetails zum Auth-System
├── LOGGING.md                       # Details zum Logging-System
└── DEPLOY.md                        # Deployment-Guide für Webhoster