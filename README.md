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
- **Benutzerverwaltung** – Sichere Registrierung, Login und Session-Management.
- **Notiz-Übersicht** – Dashboard mit Karten-Layout, Suche, Sortierung und Anheft-Funktion.
- **Tags & Metadata** – Unterstützung für YAML-Front-Matter zur Filterung von Notizen.
- **Dark Mode** – Automatische Erkennung der Systemeinstellung mit manuellem Toggle.
- **Export** – Download als Markdown oder gestyltes HTML sowie Kopie in die Zwischenablage.

## Sicherheit & Logging

Das Projekt wurde einem umfassenden Security-Audit unterzogen und gehärtet:

* **Schutz vor SQL-Injection**: Konsequente Nutzung von Prepared Statements für alle Datenbankoperationen.
* **XSS-Prävention**: Sanitisierung des Outputs durch **DOMPurify** und Escaping von User-Input.
* **Passwort-Sicherheit**: Hashing nach Industriestandard mittels `password_hash()` (bcrypt).
* **Gehärtetes Session-Management**: Nutzung von `HttpOnly`, `SameSite=Strict` und `Secure`-Flags für Cookies.
* **Brute-Force-Schutz**: Implementiertes Rate-Limiting für Login und Registrierung.
* **Logging-System**: Detailliertes internes Fehler- und Sicherheits-Logging mit automatischer Anonymisierung sensibler Daten.
* **Infrastruktur-Härtung**: Schutz sensibler Dateien wie `.env` und Sperrung des `.git`-Verzeichnisses via `.htaccess`.

## Technologien

| Bereich  | Technologie                          |
|----------|--------------------------------------|
| Frontend | HTML5, CSS3, Vanilla JavaScript (ES6+) |
| Backend  | PHP 7.4+, MySQL/MariaDB             |
| Libs     | Marked.js, DOMPurify, Highlight.js  |

## Installation

### Voraussetzungen
- Webserver mit PHP 7.4+ (z. B. Apache)
- MySQL- oder MariaDB-Datenbank

### Einrichtung
1. **Repository klonen**: `git clone https://github.com/71Professor/mikesmarkdown.git`.
2. **Konfiguration**: Erstelle eine `.env` Datei basierend auf der `.env.example` und trage deine DB-Daten ein.
3. **Datenbank initialisieren**: Rufe `setup.php` einmalig auf und lösche die Datei danach sofort vom Server.
4. **Logging**: Das System loggt standardmäßig nach `./logs`. Stelle sicher, dass das Verzeichnis existiert und beschreibbar ist.

## Projektstruktur

```text
mikesmarkdown/
├── index.html      # Frontend-Shell
├── app.js          # Frontend-Logik & XSS-Schutz
├── api.php         # Gehärtetes REST-API-Backend
├── config.php      # Datenbank-Konfiguration
├── setup.php       # Datenbank-Initialisierung
├── .htaccess       # Sicherheitsregeln & Header
├── LOGGING.md      # Details zum Logging-System
└── DEPLOY.md       # Deployment-Guide für Webhoster