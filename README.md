# MikesMarkdown

Eine webbasierte Markdown-Notizen-App mit Live-Vorschau, Benutzerverwaltung und serverseitiger Speicherung.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?logo=javascript&logoColor=black)

## Features

- **Split-View-Editor** – Bearbeitung und Live-Vorschau nebeneinander, mit verstellbarem Trennbalken
- **Syntax-Highlighting** – Code-Blöcke werden automatisch eingefärbt (Highlight.js)
- **Auto-Save** – Änderungen werden automatisch nach 1,2 Sekunden gespeichert
- **Benutzerverwaltung** – Registrierung, Login und Sessions
- **Notiz-Übersicht** – Dashboard mit Karten-Layout, Suche und Sortierung
- **Notizen anheften** – Wichtige Notizen oben fixieren
- **Tags** – YAML-Front-Matter-Tags mit Filterung in der Übersicht
- **Dark Mode** – Automatische Erkennung der Systemeinstellung + manueller Toggle
- **Inhaltsverzeichnis** – Ausklappbare Sidebar mit allen Überschriften
- **Drag & Drop** – `.md`/`.txt`-Dateien direkt in den Editor ziehen
- **Export** – Download als Markdown oder gestyltes HTML, Kopie in die Zwischenablage
- **Statistiken** – Zeilen-, Wort- und Zeichenanzahl in Echtzeit
- **Tastenkürzel** – `Ctrl+B` (Fett), `Ctrl+I` (Kursiv), `Ctrl+S` (Download MD) u.v.m.

## Technologien

| Bereich  | Technologie                          |
|----------|--------------------------------------|
| Frontend | HTML, CSS, Vanilla JavaScript        |
| Backend  | PHP 7.4+, MySQL/MariaDB             |
| Libs     | Marked.js, DOMPurify, Highlight.js  |

## Installation

### Voraussetzungen

- Webserver mit PHP 7.4+ (z.B. Apache)
- MySQL- oder MariaDB-Datenbank

### Einrichtung

1. **Repository klonen**
   ```bash
   git clone https://github.com/71Professor/mikesmarkdown.git
   cd mikesmarkdown
   ```

2. **Datenbank-Zugangsdaten konfigurieren**
   ```bash
   cp .env.example .env
   ```
   Dann die `.env`-Datei mit den eigenen Zugangsdaten befüllen:
   ```
   DB_HOST=localhost
   DB_NAME=deine_datenbank
   DB_USER=dein_benutzer
   DB_PASS=dein_passwort
   ```

3. **Datenbank initialisieren**

   `setup.php` einmalig im Browser aufrufen, um die Tabellen zu erstellen.
   Danach `setup.php` vom Server löschen.

4. **Anwendung starten**

   Für lokale Entwicklung:
   ```bash
   php -S localhost:8000
   ```
   Dann im Browser `http://localhost:8000` öffnen.

## API-Endpunkte

### Authentifizierung

| Methode | Endpunkt                   | Beschreibung        |
|---------|----------------------------|---------------------|
| POST    | `api.php` action=register  | Registrierung       |
| POST    | `api.php` action=login     | Login               |
| POST    | `api.php` action=logout    | Logout              |
| GET     | `api.php?action=session`   | Session prüfen      |

### Notizen (erfordern Login)

| Methode | Endpunkt                          | Beschreibung          |
|---------|-----------------------------------|-----------------------|
| GET     | `api.php?action=list`             | Alle Notizen laden    |
| GET     | `api.php?action=get&id=...`       | Einzelne Notiz laden  |
| POST    | `api.php` action=create           | Neue Notiz erstellen  |
| POST    | `api.php` action=update           | Notiz aktualisieren   |
| POST    | `api.php` action=delete           | Notiz löschen         |
| POST    | `api.php` action=togglePin        | Notiz anheften/lösen  |

## Projektstruktur

```
mikesmarkdown/
├── index.html      Frontend (HTML-Shell)
├── app.js          Frontend-Logik & State-Management
├── styles.css      Styling mit Dark-Mode-Support
├── api.php         REST-API-Backend
├── config.php      Datenbank-Konfiguration
├── setup.php       Einmalige Datenbank-Initialisierung
├── .env.example    Vorlage für Umgebungsvariablen
├── .htaccess       Sicherheitsregeln (blockiert .env-Zugriff)
└── DEPLOY.md       Deployment-Anleitung (all-inkl.com)
```

## Sicherheit

- Passwörter werden mit `password_hash()` (bcrypt) gespeichert
- SQL-Injection-Schutz durch Prepared Statements
- XSS-Schutz durch DOMPurify-Sanitisierung
- `.env`-Datei per `.htaccess` vor Browserzugriff geschützt
- Session-Regeneration nach Login

## Deployment

Eine detaillierte Deployment-Anleitung für all-inkl.com findest du in [DEPLOY.md](DEPLOY.md).

## Lizenz

Dieses Projekt hat derzeit keine explizite Lizenz.
