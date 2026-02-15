# Deployment auf all-inkl.com

Dieses Projekt nutzt PHP + MySQL für die serverseitige Speicherung von Notizen. Durch das integrierte Security-Hardening ist die Anwendung für den Betrieb im Web optimiert.

## Erforderliche Dateien

Lade die folgenden Dateien in dein Zielverzeichnis (z. B. `htdocs/` oder ein Unterverzeichnis deiner Domain):

- `index.html`, `styles.css`, `app.js` – Frontend-Ressourcen
- `api.php`, `config.php`, `logger.php` – Backend-Logik & Datenbank-Schnittstelle
- `.htaccess` – Enthält wichtige Sicherheits-Header und Zugriffsbeschränkungen
- `.env` – Deine Datenbank-Zugangsdaten (erstellen aus `.env.example`)
- `setup.php` – Einmalige Datenbank-Einrichtung

## Installationsschritte

### 1. Datenbank erstellen
1. Logge dich im KAS ein (https://kas.all-inkl.com/).
2. Gehe zu **Datenbanken** → **Neue Datenbank anlegen**.
3. Notiere dir Datenbanknamen, Nutzername und Passwort.

### 2. Konfiguration (.env)
1. Kopiere die `.env.example` Datei lokal nach `.env`.
2. Befülle die Variablen mit deinen KAS-Daten:
   ```env
   DB_HOST=localhost
   DB_NAME=db12345
   DB_USER=db12345
   DB_PASS=dein_passwort
   LOGGING_ENABLED=true