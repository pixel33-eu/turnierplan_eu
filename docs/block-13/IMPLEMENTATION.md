# Block 13: Bedienqualität, Barrierefreiheit und Sprachen

Stand: 8. Oktober 2026

## Übersetzungen

Alle PHP-, Einstellungs-, Preset- und Gutenberg-Texte verwenden englische Originaltexte mit der Textdomain `turnierplan-eu`. WordPress lädt veröffentlichte Sprachpakete Just-in-Time anhand der Textdomain; das Plugin ruft `load_plugin_textdomain()` deshalb nicht selbst auf. Der reproduzierbare Build in `tools/build-translations.mjs` erzeugt aus dem geprüften deutschen PO-Katalog:

- `turnierplan-eu.pot` für alle 222 englischen Originaltexte;
- `turnierplan-eu-de_DE.mo` für PHP;
- handle- und quellpfadbasierte Jed-JSON-Dateien für Block- und Preset-Editor.

Der Build bricht bei fehlenden, leeren oder veralteten deutschen Einträgen ab. Der WordPress-Playground-Test lädt den MO-Katalog tatsächlich und prüft zentrale deutsche Begriffe. Die JavaScript-Kataloge werden über `wp_set_script_translations()` mit dem lokalen Sprachverzeichnis verbunden.

Die WordPress-Oberfläche folgt weiterhin der Sprache des angemeldeten WordPress-Benutzers. Die Sprache der eingebetteten Turnierausgabe ist davon getrennt: Ein gespeichertes `auto` wird unmittelbar vor Metadaten- oder Frame-Aufrufen aus der primären Sprache der Website-Locale aufgelöst. Der gespeicherte Wert bleibt `auto`. Eine ausdrücklich gewählte Ausgabesprache bleibt unverändert. Sie steuert neben dem Frame auch dessen lokale Status-, Titel-, Fehler- und Fallback-Linktexte. Der Renderer liest dafür den gebündelten Gettext-Katalog isoliert, ohne die globale WordPress-Locale oder weitere Einbettungen auf derselben Seite zu verändern. Metadaten-Zeitpunkte formatiert der Editor mit `Intl.DateTimeFormat` in der Dokumentsprache, statt einen rohen ISO-Zeitstempel anzuzeigen.

Diese Umsetzung folgt den offiziellen WordPress-Vorgaben zu [Internationalisierung](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/), [Lokalisierung](https://developer.wordpress.org/plugins/internationalization/localization/) und [JavaScript-Übersetzungen](https://developer.wordpress.org/reference/functions/wp_set_script_translations/).

## Tastatur, Fokus und Status

- Fehler im Block- und Preset-Editor erhalten programmatisch den Fokus und eine sichtbare Fokusmarkierung.
- Die Akzentfarbauswahl besitzt eine zugängliche Beschriftung.
- Der Shortcode-Kopierknopf behält einen stabilen Namen. Erfolg oder Fehler wird in einer höflichen Live-Region angesagt; für Umgebungen ohne Clipboard API besteht ein lokaler Rückfall.
- Turnierstatus erscheint als übersetzter Text und nicht nur als Farbe.
- Der gemeinsame Renderer behält seine beschriftete Iframe-Ausgabe und die atomare `role="status"`-Live-Region für Laden, Bereit, Leer, Veraltet, Fehler und Zeitüberschreitung.
- Links und nicht verfügbare Zustände besitzen sichtbare `:focus-visible`-Markierungen und umbrechen lange Inhalte.

## Darstellung und Testdaten

Die Plugin-Hüllen verwenden begrenzte logische Breiten, geerbtes `border-box` und lokale Überlaufbereiche. Die Einbettung kann dadurch die WordPress-Seite nicht horizontal verbreitern. Animationen und Übergänge werden bei `prefers-reduced-motion: reduce` entfernt beziehungsweise auf ein technisch minimales Maß verkürzt.

`tests/fixtures/accessibility-demo.json` enthält ausschließlich fiktive Daten für lange Turnier- und Vereinsnamen, ein fehlendes Logo und einen leeren Tabellenzustand. `tools/check-responsive-browser.mjs` lädt die ausgelieferten Plugin-Styles in echten Chromium-Iframes mit exakt 320, 375, 768 und 1440 Pixel Breite und weist zusätzlichen Seitenüberlauf zurück. Der Test lief lokal mit Chrome erfolgreich.

Die eigentliche Turnieransicht bleibt im getrennten Projekt `D:\2025\Turnierplan.eu`. Sie wurde in diesem Block nur gelesen. Ihre vorhandenen Frame-Tests für Tabellen, Spiele, Deutsch/Englisch, Leerzustände, Logo-Platzhalter, Zustände und Aktualisierung liefen erfolgreich. Das dortige lokale Stylesheet enthält helle, dunkle und automatische Darstellung, begrenzten Tabellenüberlauf, Fokuszustände, reduzierte Bewegung und kontrastberechnete Akzentvarianten. An der Website wurde für Block 13 keine Datei geändert.

## Reproduzierbare Prüfung

```powershell
npm run check:translations
npm run test:accessibility
npm run test:responsive-browser
npm run check
composer check --ignore-platform-req=php
```

Der Browser-Test benötigt Chrome, Edge oder einen über `TPEU_BROWSER_BIN` angegebenen Chromium-Browser. Der WordPress-Playground-Lauf prüft WordPress 6.5 mit PHP 8.3, die englische Standardoberfläche, den deutschen MO-Katalog, die aufgelöste `auto`-Sprache, explizit deutsche Wrappertexte bei `lang="de"`, die unveränderte WordPress-Locale danach sowie die geschützten REST-, Block-, Preset- und Shortcode-Abläufe.

Die vollständige manuelle Prüfung mit verschiedenen Betriebssystem-Zoomstufen und assistiven Technologien bleibt Teil der Gesamtprüfung in Block 14. Die in diesem Block behauptete Sprachabdeckung umfasst die ausgelieferten WordPress-Oberflächen auf Englisch und Deutsch sowie die bereits vorhandenen deutschen und englischen Frame-Texte.
