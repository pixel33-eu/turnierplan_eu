# Block 10 – Umsetzung und Abnahme

Stand: 21. September 2026. Status: abgeschlossen.

## Gemeinsamer Renderer

`EmbedRenderer` ist die einzige Komponente, die Iframe-Markup erzeugt. Shortcode, der dynamische Block aus Block 11, Presets aus Block 12 und spätere Vorschauen können dadurch dieselbe geprüfte Ausgabe verwenden. Der Renderer führt keinen Metadaten- oder sonstigen WordPress-HTTP-Abruf aus. Er prüft vor jeder Frameausgabe die Dienstfreigabe aus Block 9 und erzeugt bei fehlender Freigabe oder unsicherer WordPress-Adresse nur einen lokalen Hinweis mit öffentlichem Turnierlink.

Jede tatsächliche Einbettung besitzt:

- eine mit `wp_generate_uuid4()` erzeugte Instanzkennung;
- eine ausschließlich vom festen `EmbedUrlBuilder` erzeugte HTTPS-URL;
- einen Titel aus Ansicht und Turnierreferenz beziehungsweise einem später übergebenen, geprüften Turniernamen;
- `width="100%"`, responsive lokale Styles, validierte Mindest- und Maximalhöhe sowie `loading="lazy"`;
- `referrerpolicy="strict-origin-when-cross-origin"`;
- die getestete Sandbox `allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox`;
- eine Permissions Policy, die Kamera, Mikrofon, Standort und Zwischenablage verweigert;
- einen dauerhaft außerhalb des Frames erreichbaren Link zur vollständigen Turnierseite.

URL, Attribute und sichtbare Texte werden im jeweiligen HTML-Kontext maskiert. Das funktionale Fallback ist der einzige zusätzliche Turnierplan.eu-Link; ein Werbecredit wird nicht erzeugt.

## Frontend-Verhalten

Stylesheet und gebündeltes Skript werden erst eingereiht, wenn der Renderer wirklich ein gültiges und freigegebenes Embed ausgibt. Ungültige Shortcodes und eine widerrufene Dienstfreigabe laden keine Frontend-Assets.

Das Frontend initialisiert beliebig viele Einbettungen unabhängig voneinander und verwendet den geprüften Nachrichten-Controller aus Block 7. Es beginnt die Bereitschaftsfrist erst, wenn ein lazy geladenes Frame in die Nähe des sichtbaren Bereichs kommt. Die Zustände Laden, bereit, leer, veraltet, Fehler und Zeitüberschreitung besitzen verständliche lokale Texte. Dynamisch eingefügte Einbettungen werden über einen einzigen dokumentweiten `MutationObserver` erkannt; entfernte Einbettungen geben Listener und Observer wieder frei. Zusätzlich steht eine explizite Initialisierung für integrierende Komponenten bereit.

## Shortcode und Allowlist

Der Handler registriert `[turnierplan]` über die WordPress Shortcode API, entfernt WordPress-Slashes einmal an der Eingabegrenze, ignoriert unbekannte Attribute und gibt immer einen String zurück. Beliebige Remote-URLs werden nicht angenommen. Eine Preset-Auswahl bleibt bis Block 12 als verständlicher lokaler Zustand sichtbar.

Die dokumentierte Attribut-Allowlist lautet:

| Attribut | Bedeutung |
| --- | --- |
| `tournament` | öffentliche Turnierreferenz für eine Inline-Konfiguration |
| `preset` | positive WordPress-ID einer gespeicherten Einbettung; ab Block 12 auflösbar |
| `view` | `standings` oder `matches`; im Generator immer ausdrücklich enthalten |
| `lang` | `auto` oder unterstützter Sprachcode |
| `group`, `participant` | öffentliche Filterkennungen |
| `match_from`, `match_to` | inklusiver Spielnummernbereich |
| `date_from`, `date_to` | inklusiver Datumsbereich im Format `YYYY-MM-DD` |
| `theme` | `auto`, `light` oder `dark` |
| `density` | `comfortable` oder `compact` |
| `accent` | sechsstellige Hex-Farbe, mit oder ohne `#` |
| `branding` | `show` oder `hide` |
| `links` | `new-tab` oder `same-tab` |
| `min_height`, `max_height` | validierte Fallback-Höhen |
| `show` | kommaseparierte, ansichtsabhängige Feld-Allowlist |
| `date` | `auto`, `show` oder `hide` für die Datumsanzeige |

`preset` darf nicht mit Inline-Attributen kombiniert werden. Ohne `preset` ist `tournament` erforderlich. Alle erkannten Werte durchlaufen das gemeinsame Konfigurationsmodell aus Block 8.

## Kompakter Generator

`ShortcodeGenerator` erzeugt aus einer bereits validierten Auswahl kanonischen Text. Referenz und Ansicht bleiben bei Inline-Konfigurationen immer lesbar; unveränderte Darstellungsstandards werden ausgelassen. Geänderte Optionen werden in stabiler Reihenfolge ergänzt. Eine gültige Preset-Auswahl wird als `[turnierplan preset="87"]` dargestellt. Das Auswahlmodell lässt weder eine leere Inline-Konfiguration noch einen nicht positiven Preset-Verweis zu, daher entsteht kein scheinbar verwendbarer Shortcode ohne Ziel.

## Dateien

- neu: `src/render/` mit gemeinsamem Renderer und WordPress-Adaptern für Origin, UUID und Assets
- neu: `src/shortcode/` mit Handler und kanonischem Generator
- neu: `assets/src/embed-frontend.js` für Status, Lazy-Start, Mehrfachinstanzen und dynamische Inhalte
- neu: `assets/css/embed.css` für die responsive, vom Theme unabhängige Hülle
- neu: `tests/embed-frontend.test.mjs` und `tests/Unit/ShortcodeGeneratorTest.php`
- erweitert: WordPress-Playground-Smoke-Test um reale Shortcode-Ausgabe und Frontend-Grenzen

Block 10 verändert keine Datei unter `D:\2025\Turnierplan.eu`. Er nutzt ausschließlich die in den Blöcken 4 bis 7 bereits ausgelieferten Verträge.

## Abnahme

Die Unit-Tests prüfen kompakte Tabellen-, umfangreiche Spiele- und Preset-Shortcodes. Der JavaScript-Test prüft mehrere Frames, einmalige Initialisierung, Lazy-Start, Statuswechsel, dynamisches Einfügen und Aufräumen nach dem Entfernen.

Der WordPress-6.5-/PHP-8.3-Smoke-Test aktiviert das Plugin und rendert zwei reale Shortcodes. Er prüft eindeutige UUIDs, festen Ursprung, `parent_origin`, Titel-/Sicherheitsattribute, responsive Breite, Fallback-Link, das Ignorieren eines unbekannten URL-Attributs und das bedingte Laden lokaler Assets. Ein HTTP-Guard weist nach, dass dabei kein WordPress-HTTP-Aufruf entsteht. Nach Widerruf der Dienstfreigabe erscheinen weder Iframe noch Assets.
