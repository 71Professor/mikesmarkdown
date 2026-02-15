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


### 3. Upload & Berechtigungen
Verbinde dich per FTP/SFTP und lade alle Dateien hoch.

Wichtig: Stelle sicher, dass versteckte Dateien wie .htaccess und .env mit übertragen wurden.

Erstelle einen Ordner namens logs/ im Hauptverzeichnis und stelle sicher, dass der Webserver darin schreiben darf (Berechtigung 750 oder 755).

### 4. Datenbank initialisieren
Rufe https://deine-domain.de/setup.php im Browser auf.

Bei Erfolg erscheint die Meldung: "Table notes created successfully".

LÖSCHE die Datei setup.php sofort vom Server. Dies ist ein kritisches Sicherheitsrisiko, falls die Datei online bleibt.

### 5. Sicherheits-Check
Versuche, die Datei https://deine-domain.de/.env direkt im Browser aufzurufen. Du solltest einen 403 Forbidden Fehler erhalten. Die .htaccess sorgt dafür, dass sensible Daten geschützt bleiben.

Laufender Betrieb & Wartung
Logs: Überprüfe regelmäßig den Ordner /logs auf verdächtige Aktivitäten oder Fehlermeldungen.

Updates: Bei Updates müssen lediglich index.html, styles.css, app.js und api.php überschrieben werden. Deine .env bleibt unverändert.