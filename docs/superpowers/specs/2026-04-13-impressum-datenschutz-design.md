# Design: Impressum & Datenschutz

**Datum:** 2026-04-13

## Ziel

Rechtlich notwendige Seiten (Impressum, Datenschutz) in die App einbinden. Links erscheinen in der Notizenübersicht links neben dem Nutzernamen und öffnen separate Fenster.

## Änderungen

### 1. `index.html` – Links in der Übersichts-Header-Leiste

In `#overview-header-actions` werden zwei `<a>`-Elemente **vor** `#user-greeting` eingefügt:

```html
<a href="impressum.html" target="_blank" rel="noopener noreferrer" class="legal-link">Impressum</a>
<span class="status-sep">·</span>
<a href="datenschutz.html" target="_blank" rel="noopener noreferrer" class="legal-link">Datenschutz</a>
```

CSS-Klasse `.legal-link`: `color: var(--text-secondary)`, `font-size: 13px`, `text-decoration: none`, Hover: `color: var(--text)`.

### 2. `impressum.html` (neu)

Standalone-HTML-Datei im Projektstamm. Kein PHP, kein Backend.

- Struktur: Karte auf hellem Hintergrund (wie feedbackspinne), Inter-Font
- Akzentfarbe: `#2563eb` (MikesMarkdown-Blau)
- Inhalt: Angaben gemäß § 5 TMG (Michael Kohl, Vilshofener Str. 24, 92286 Rieden), Kontakt `hallo@mikesmarkdown.de`, Haftungsausschluss, Kleinunternehmer-Hinweis
- "← Zurück"-Link: `onclick="window.close()"` / `href="javascript:history.back()"`

### 3. `datenschutz.html` (neu)

Standalone-HTML-Datei im Projektstamm.

- Gleiche Gestaltung wie `impressum.html`
- Inhalt auf MikesMarkdown angepasst (Notizen-App statt Feedback-Tool):
  - Verantwortlicher: Michael Kohl, `hallo@mikesmarkdown.de`
  - Verarbeitete Daten: Nutzerkonto (E-Mail, bcrypt-Passwort), Notizen, technische Zugriffsdaten
  - Cookies: nur technisch notwendige Session-Tokens (JWT)
  - Keine Tracking-Dienste, keine Datenweitergabe an Dritte
  - DSGVO-Rechte, Beschwerderecht BayLDA
  - Stand: April 2026

## Nicht im Scope

- Dark Mode für die Legal-Seiten
- Einbettung als Modal (öffnet bewusst als neues Fenster)
- Login-Seite erhält keine Links (nur die Übersicht nach Login)
