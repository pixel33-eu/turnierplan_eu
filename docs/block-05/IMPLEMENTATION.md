# Block 5 – Umsetzung und Abnahme

Stand: 12. September 2026. Status: abgeschlossen.

## Eigenständige Tabellenansicht

Das getrennte Website-Projekt unter `D:\2025\Turnierplan.eu` stellt jetzt folgenden versionierten Frame-Quellstand bereit:

```http
GET /embed/v1/tournaments/{reference}?view=standings&lang=de&instance=550e8400-e29b-41d4-a716-446655440000&parent_origin=https%3A%2F%2Fverein.example
Accept: text/html
```

Die neue Implementierung liegt vollständig unter `embed/v1` und verwendet die in den Blöcken 3 und 4 erstellte, getrennte Embed-API. Bestehende Turnierseiten, Navigation, Backoffice, Turniererstellung, Kopier- und Löschabläufe sowie Ergebnis-Schreibvorgänge wurden für Block 5 nicht geändert.

Die Route prüft die Turnierreferenz, alle Query-Parameter, die erforderliche UUID-v4-Instanz und den erforderlichen HTTPS-Eltern-Origin. Die Ansicht `standings` unterstützt Gruppenwahl, Sprache, helles/dunkles/automatisches Farbschema, normale/kompakte Dichte, Akzentfarbe, Brandingwunsch, Linkziel und die dokumentierte `show`-Positivliste. Unbekannte oder ansichtsfremde Werte enden in einer übersetzten HTML-Fehlerseite. Eine nicht mehr vorhandene, syntaktisch gültige Gruppe wird ignoriert und im Frame erklärt. Die Ansicht `matches` bleibt bis Block 6 mit HTTP 409 `view_unavailable` gesperrt.

## Öffentliche Tabellendaten

Der API-Adapter überträgt jetzt zusätzlich:

- positiv gelistete Spaltenkennungen und lokalisierte Kurzbeschriftungen;
- ausschließlich benötigte Tabellenwerte je öffentlicher Teilnehmerreferenz;
- Ergebnisbilanzen, Spiele, Siege, Remis, Niederlagen und Tabellenpunkte;
- Abschlussplatzierungen mit öffentlichen Teilnehmerreferenzen;
- lokale oder gleichursprüngliche Logos nur als optionale Anzeigeinformation.

Die vorhandene Wertungsberechnung bleibt die fachliche Grundlage. Tore, Sätze, Legs, Punkte und Schachwerte verwenden deren Spalten und Formate. Für kombinierte Satz-/Leg-Wertungen sowie Zeitwertungen liest ausschließlich der neue Adapter die vollständigen gespeicherten `result_data` und berechnet die fehlenden Darstellungswerte mit den vorhandenen Wertungsklassen. Dadurch werden Satz- und Legbilanzen sowie vollständige Zeitangaben korrekt ausgegeben, ohne den alten Live-Transport oder bestehende Schreibabläufe zu erweitern.

## Gestaltung und Barrierefreiheit

`embed/v1/assets/embed-v1.css` ist ein eigenes, lokales und versioniertes Stylesheet ohne Framework oder CDN. Es enthält:

- helle, dunkle und automatische Systemdarstellung;
- komfortable und kompakte Tabellendichte;
- sichtbare Tastaturfokusse und berücksichtigte Bewegungsreduktion;
- umbrechende lange Turnier- und Teilnehmernamen;
- fokussierbare, beschriftete Tabellenbereiche mit internem horizontalem Scrollen;
- Gruppenlinks, die ohne JavaScript per Tastatur funktionieren;
- Initialenplatzhalter für fehlende oder nicht erlaubte Logos;
- getrennte Leerzustände und Abschlussplatzierungen.

Tabellen verwenden `caption`, `thead`, `tbody` und `th scope="col"`. Ein eigener Browserlauf mit den fiktiven Abnahmedaten wurde bei 320 und 1024 Pixel Breite geprüft. Bei 320 Pixel bleiben Rang und Teilnehmername direkt sichtbar; zusätzliche Werte sind innerhalb der Tabelle horizontal erreichbar. Gruppenwahl, Status, lange Namen, Abschlussplatzierungen und Footer bleiben im Viewport.

Beliebige gültige Hex-Akzentfarben werden durch einen gleichursprünglichen CSS-Endpunkt ausgegeben. Der Endpunkt berechnet für Text auf hellem und dunklem Hintergrund jeweils mindestens 4,5:1 Kontrast und für Akzentflächen eine passende schwarze oder weiße Vordergrundfarbe. Er akzeptiert keine freien CSS-Werte.

## HTTP, Sicherheit und Datenschutz

Erfolgreiche Frame-Antworten enthalten:

```text
Content-Type: text/html; charset=utf-8
Cache-Control: public, max-age=30
ETag: "…"
Content-Language: …
Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; form-action 'none'; object-src 'none'; frame-ancestors https:
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()
Referrer-Policy: strict-origin-when-cross-origin
X-Content-Type-Options: nosniff
```

Die lokale `.htaccess` entfernt einen eventuell global geerbten `X-Frame-Options`-Wert nur für diese Frame-Routen. Der PHP-Renderer selbst sendet keinen widersprüchlichen Header. Vor Cache und konditionalem `304` wird das Turnier erneut über die gemeinsame Freigabeprüfung aufgelöst. Der Frame ist auf 2 MiB unkomprimiertes HTML begrenzt und verwendet das bestehende Embed-Limit von 120 Abrufen pro IP-Hash und 60 Sekunden.

Alle Datenbank- und Querywerte werden vor der Verwendung validiert oder positiv ausgewählt und bei der HTML-Ausgabe kontextbezogen maskiert. Logos sind auf tatsächlich vorhandene Bilddateien unter dem eigenen Ursprung begrenzt. Die Umsetzung folgt damit dem aktuellen WordPress-Grundsatz, fremde Eingaben einschließlich Datenbankwerte zu validieren und Ausgaben spät zu maskieren: [Security – Common APIs Handbook](https://developer.wordpress.org/apis/security/).

Frame und Assets starten keine PHP-Sitzung, setzen keine Cookies und verwenden weder Local Storage noch Session Storage, Tracking, Werbung, externe Skripte, Webfonts oder allgemeine CDN-Bibliotheken. Die [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) erlauben die dokumentierte Anbindung eines eigenständigen Dienstes, verlangen aber unter anderem transparente Dienstangaben, untersagen Tracking ohne Zustimmung und verlangen lokal mitgelieferten, nicht dienstbezogenen Code. Block 5 hält diese Grenzen ein; die vollständige Dienst- und Datenschutzdokumentation bleibt Bestandteil von Block 15.

## Dateien im Website-Projekt

- `embed/v1/.htaccess`
- `embed/v1/index.php`
- `embed/v1/_frame.php`
- `embed/v1/assets/embed-v1.css`
- `embed/v1/assets/accent.php`
- `embed/v1/README.md`
- `tests/embed_standings_test.php`
- erweitert: `api/embed/v1/_adapter.php`
- erweitert: `api/embed/v1/_public.php` ausschließlich um das intern für Sonderwertungen benötigte Punkteschema
- aktualisiert: `api/embed/v1/README.md`

## Abnahme

Der neue Test `tests/embed_standings_test.php` verwendet ausschließlich eigene fiktive Turnier-, Gruppen-, Teilnehmer- und Ergebnisdaten. Er prüft insbesondere:

- vollständige HTML-Antwort und lokale versionierte Assets;
- helles/dunkles/automatisches Farbschema, Dichte und sichere Akzentfarbe;
- Standard-, leere und eingeschränkte `show`-Listen;
- Gruppenwahl sowie Hinweise für entfernte Filter und nicht verfügbare Optionen;
- lange und als HTML interpretierbare Namen, sichere Logos und Platzhalter;
- leere Ranglisten und Abschlussplatzierungen;
- Tore, Sätze, Legs, Zeit- und Schachwerte;
- vollständige Satz-/Leg- und Zeit-Neuberechnung im API-Adapter;
- erforderliche Instanz und HTTPS-Eltern-Origin;
- 400-, 404-, 405-, 409- und 429-Fehlerseiten;
- Freigabeprüfung vor `304`, ETag, `Retry-After`, CSP und Einbettungsheader;
- fehlende Cookies, Sitzungen, Web Storage, Tracking und CDN-Abhängigkeiten;
- Tastaturstruktur, internen Tabellenüberlauf und Bewegungsreduktion.

Alle PHP-Dateien des Embed-Pfads wurden einzeln mit `php -l` geprüft. Unter `E_ALL` laufen der neue Test und alle bisherigen Embed-, Ergebnis-, Zeitzonen- und Schachregressionen ohne Warnung durch. Die Plugin-Prüfkette wird nach dieser Dokumentationsänderung erneut ausgeführt.

Die Produktionsmigration aus Block 3 wurde weiterhin nicht ausgeführt. Die neue Route wurde nicht deployt und ist daher auf der produktiven Website noch nicht erreichbar. Kontrollierte Migration, Deployment und ein Test mit einem ausdrücklich veröffentlichten Turnier bleiben Teil der Inbetriebnahme.
