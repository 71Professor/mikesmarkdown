# Impressum & Datenschutz Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Impressum- und Datenschutz-Links in der Notizenübersicht links neben dem Nutzernamen einbinden, die je eine neue statische HTML-Seite im neuen Fenster öffnen.

**Architecture:** Zwei neue standalone HTML-Dateien im Projektstamm (`impressum.html`, `datenschutz.html`). Die Links werden direkt in `index.html` als `<a target="_blank">` eingefügt. Styling über eine neue CSS-Klasse `.legal-link` in `styles.css`.

**Tech Stack:** Vanilla HTML, CSS (bestehende CSS-Variablen), kein JavaScript, kein Backend.

---

### Task 1: `impressum.html` erstellen

**Files:**
- Create: `impressum.html`

- [ ] **Schritt 1: Datei erstellen**

Inhalt von `/mnt/c/Git/mikesmarkdown/impressum.html`:

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum – MikesMarkdown</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: #1a1d23;
            background: linear-gradient(180deg, #f0f2f5 0%, #ffffff 60%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }
        .container { max-width: 640px; width: 100%; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #1a1d23; }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(26,29,35,.06);
            padding: 40px;
        }
        h1 { font-size: 24px; font-weight: 800; color: #1a1d23; margin-bottom: 8px; }
        .subtitle { color: #6b7280; font-size: 14px; margin-bottom: 32px; }
        h2 { font-size: 15px; font-weight: 700; color: #1a1d23; margin-top: 28px; margin-bottom: 8px; }
        p { color: #6b7280; font-size: 14px; line-height: 1.7; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { color: #1d4ed8; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
        @media (max-width: 480px) {
            .card { padding: 24px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-link">← Zurück</a>

        <div class="card">
            <h1>Impressum</h1>
            <p class="subtitle">Angaben gemäß § 5 TMG</p>

            <h2>Betreiber</h2>
            <p>
                Michael Kohl<br>
                Vilshofener Str. 24<br>
                92286 Rieden<br>
                Deutschland
            </p>

            <h2>Kontakt</h2>
            <p>E-Mail: <a href="mailto:hallo@mikesmarkdown.de">hallo@mikesmarkdown.de</a></p>

            <hr class="divider">

            <h2>Haftungsausschluss</h2>
            <p>
                Die Inhalte dieser Website wurden mit größtmöglicher Sorgfalt erstellt. Für die
                Richtigkeit, Vollständigkeit und Aktualität der Inhalte kann ich jedoch keine Gewähr
                übernehmen. Als Diensteanbieter bin ich gemäß § 7 Abs. 1 TMG für eigene Inhalte auf
                diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG bin
                ich als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte
                fremde Informationen zu überwachen.
            </p>

            <h2>Umsatzsteuer</h2>
            <p>
                Ich bin Kleinunternehmer im Sinne von § 19 UStG und weise daher keine
                Umsatzsteuer aus.
            </p>
        </div>
    </div>
</body>
</html>
```

- [ ] **Schritt 2: Visuell prüfen**

Datei im Browser öffnen und prüfen: Karte sichtbar, Links funktionieren, "← Zurück" schließt/navigiert zurück.

- [ ] **Schritt 3: Commit**

```bash
git add impressum.html
git commit -m "feat: add impressum.html"
```

---

### Task 2: `datenschutz.html` erstellen

**Files:**
- Create: `datenschutz.html`

- [ ] **Schritt 1: Datei erstellen**

Inhalt von `/mnt/c/Git/mikesmarkdown/datenschutz.html`:

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutzerklärung – MikesMarkdown</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            color: #1a1d23;
            background: linear-gradient(180deg, #f0f2f5 0%, #ffffff 60%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px;
        }
        .container { max-width: 720px; width: 100%; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #1a1d23; }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(26,29,35,.06);
            padding: 40px;
        }
        h1 { font-size: 24px; font-weight: 800; color: #1a1d23; margin-bottom: 8px; }
        .subtitle { color: #6b7280; font-size: 14px; margin-bottom: 32px; }
        h2 { font-size: 16px; font-weight: 700; color: #1a1d23; margin-top: 32px; margin-bottom: 10px; }
        h3 { font-size: 14px; font-weight: 600; color: #1a1d23; margin-top: 20px; margin-bottom: 6px; }
        p { color: #6b7280; font-size: 14px; line-height: 1.75; margin-bottom: 12px; }
        ul { list-style: none; padding: 0; margin-bottom: 12px; }
        ul li {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.75;
            padding-left: 16px;
            position: relative;
        }
        ul li::before { content: "–"; position: absolute; left: 0; color: #2563eb; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { color: #1d4ed8; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
        @media (max-width: 480px) {
            .card { padding: 24px; }
            h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-link">← Zurück</a>

        <div class="card">
            <h1>Datenschutzerklärung</h1>
            <p class="subtitle">Stand: April 2026</p>

            <h2>1. Verantwortlicher</h2>
            <p>
                Verantwortlicher im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:<br><br>
                Michael Kohl<br>
                Vilshofener Str. 24<br>
                92286 Rieden<br>
                Deutschland<br><br>
                E-Mail: <a href="mailto:hallo@mikesmarkdown.de">hallo@mikesmarkdown.de</a>
            </p>

            <hr class="divider">

            <h2>2. Welche Daten werden verarbeitet?</h2>

            <h3>2.1 Automatisch erfasste Zugriffsdaten</h3>
            <p>
                Beim Aufruf der Website erfasst der Webserver automatisch technische Informationen,
                die für den Betrieb der Seite und zum Schutz vor Missbrauch (Rate-Limiting) notwendig
                sind:
            </p>
            <ul>
                <li>IP-Adresse des anfragenden Geräts</li>
                <li>Datum und Uhrzeit der Anfrage</li>
                <li>Aufgerufene Seite / URL</li>
                <li>HTTP-Statuscode</li>
            </ul>
            <p>
                Die IP-Adresse wird ausschließlich zur Missbrauchsverhinderung (Rate-Limiting)
                verwendet und nicht dauerhaft gespeichert. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f
                DSGVO (berechtigtes Interesse am sicheren Betrieb des Dienstes).
            </p>

            <h3>2.2 Nutzerkonten</h3>
            <p>
                Zur Nutzung von MikesMarkdown ist ein Konto erforderlich. Dabei werden gespeichert:
            </p>
            <ul>
                <li>Benutzername</li>
                <li>E-Mail-Adresse</li>
                <li>Passwort (ausschließlich als bcrypt-Hash, nicht im Klartext)</li>
                <li>Zeitpunkt der Registrierung</li>
            </ul>
            <p>
                Diese Daten sind für die Bereitstellung des Dienstes erforderlich. Rechtsgrundlage
                ist Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).
            </p>

            <h3>2.3 Notizen</h3>
            <p>
                Die von dir erstellten Notizen werden auf dem Server gespeichert und sind
                ausschließlich für dein Konto zugänglich. Sie werden nicht an Dritte weitergegeben
                und nicht für andere Zwecke verarbeitet. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b
                DSGVO (Vertragserfüllung).
            </p>

            <h3>2.4 Authentifizierung (JWT)</h3>
            <p>
                Nach dem Login wird ein technisch notwendiges JWT-Token (JSON Web Token) im
                Browser-Speicher (localStorage) abgelegt, um deine Sitzung zu verwalten. Es werden
                keine Tracking-Cookies oder Cookies zu Werbezwecken eingesetzt.
            </p>

            <hr class="divider">

            <h2>3. Weitergabe von Daten an Dritte</h2>
            <p>
                Es findet keine Weitergabe personenbezogener Daten an Dritte statt. MikesMarkdown
                nutzt keine externen Analyse-, Tracking- oder Werbedienste. Es werden keine Daten
                außerhalb der EU/EWR übertragen.
            </p>

            <hr class="divider">

            <h2>4. Speicherdauer</h2>
            <ul>
                <li>Zugriffsdaten / IP-Adressen: temporär während einer Sitzung, kein dauerhaftes Protokoll</li>
                <li>Nutzerkontodaten und Notizen: bis zur Löschung des Kontos auf Anfrage</li>
            </ul>

            <hr class="divider">

            <h2>5. Deine Rechte als betroffene Person</h2>
            <p>Du hast gemäß DSGVO folgende Rechte:</p>
            <ul>
                <li><strong>Auskunft</strong> (Art. 15 DSGVO): Du kannst Auskunft über die zu deiner Person gespeicherten Daten verlangen.</li>
                <li><strong>Berichtigung</strong> (Art. 16 DSGVO): Du kannst die Berichtigung unrichtiger Daten verlangen.</li>
                <li><strong>Löschung</strong> (Art. 17 DSGVO): Du kannst die Löschung deiner Daten verlangen, soweit keine gesetzliche Aufbewahrungspflicht entgegensteht.</li>
                <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO): Du kannst unter bestimmten Voraussetzungen die Einschränkung der Verarbeitung verlangen.</li>
                <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO): Du kannst deine Daten in einem gängigen, maschinenlesbaren Format erhalten.</li>
                <li><strong>Widerspruch</strong> (Art. 21 DSGVO): Du kannst der Verarbeitung deiner Daten widersprechen, soweit sie auf Art. 6 Abs. 1 lit. f DSGVO gestützt wird.</li>
            </ul>
            <p>
                Zur Ausübung deiner Rechte wende dich bitte per E-Mail an:
                <a href="mailto:hallo@mikesmarkdown.de">hallo@mikesmarkdown.de</a>
            </p>

            <hr class="divider">

            <h2>6. Beschwerderecht bei der Aufsichtsbehörde</h2>
            <p>
                Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren.
                Die zuständige Aufsichtsbehörde für Bayern ist:
            </p>
            <p>
                Bayerisches Landesamt für Datenschutzaufsicht (BayLDA)<br>
                Promenade 27<br>
                91522 Ansbach<br>
                <a href="https://www.lda.bayern.de" target="_blank" rel="noopener noreferrer">www.lda.bayern.de</a>
            </p>
        </div>
    </div>
</body>
</html>
```

- [ ] **Schritt 2: Visuell prüfen**

Datei im Browser öffnen und prüfen: Alle Abschnitte vorhanden, Links korrekt, Layout stimmt.

- [ ] **Schritt 3: Commit**

```bash
git add datenschutz.html
git commit -m "feat: add datenschutz.html"
```

---

### Task 3: Links in `index.html` + CSS in `styles.css`

**Files:**
- Modify: `index.html` (Zeile ~167, `#overview-header-actions`)
- Modify: `styles.css` (neue Klasse `.legal-link`)

- [ ] **Schritt 1: Links in `index.html` einfügen**

In `#overview-header-actions` vor `<span class="user-greeting" id="user-greeting">` einfügen:

```html
<a href="impressum.html" target="_blank" rel="noopener noreferrer" class="legal-link">Impressum</a>
<span class="status-sep">·</span>
<a href="datenschutz.html" target="_blank" rel="noopener noreferrer" class="legal-link">Datenschutz</a>
```

- [ ] **Schritt 2: CSS-Klasse in `styles.css` ergänzen**

Im Bereich der bestehenden `.link-btn`-Styles (oder am Ende des globalen Abschnitts) einfügen:

```css
.legal-link {
  color: var(--text-secondary);
  font-size: 13px;
  text-decoration: none;
  white-space: nowrap;
}
.legal-link:hover {
  color: var(--text);
}
```

- [ ] **Schritt 3: Im Browser prüfen**

App laden, einloggen, Übersicht öffnen. Prüfen:
- "Impressum" und "Datenschutz" erscheinen links neben dem Nutzernamen
- Klick öffnet jeweils ein neues Fenster/Tab
- Links erscheinen im Editor-Modus nicht (sie liegen in `.overview-actions`)
- Dark Mode: Links nutzen `--text-secondary` / `--text` → passen sich an

- [ ] **Schritt 4: Commit**

```bash
git add index.html styles.css
git commit -m "feat: add Impressum and Datenschutz links to overview header"
```
