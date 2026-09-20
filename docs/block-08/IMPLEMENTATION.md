# Block 8 – Umsetzung und Abnahme

Stand: 20. September 2026. Status: abgeschlossen.

## Gemeinsames Konfigurationsmodell

`EmbedConfig` ist das maßgebliche, unveränderliche PHP-Modell für Inline-Konfigurationen. Es ergänzt ausgelassene Werte mit den festen V1-Vertragsdefaults und prüft anschließend jeden Wert ohne stilles PHP-Typcasting. Unbekannte Felder werden abgewiesen. Das gilt insbesondere für frei eingegebene URLs, Zeitzonen oder Style-Werte, die nicht zum Schema gehören.

Das Modell normalisiert Sprache, Akzentfarbe sowie öffentliche Referenzen und prüft:

- Ansicht, Theme, Dichte und Datumsanzeige gegen feste Positivlisten;
- echte Boolean-Werte ohne Interpretation von Strings wie `false`;
- kanonische Gruppen- und Teilnehmerreferenzen;
- Spielnummern von 1 bis 999999;
- echte ISO-Kalendertage sowie inklusive Bereichsrelationen;
- Mindesthöhe von 160 bis 2000 Pixeln, Maximalhöhe von 300 bis 8000 Pixeln und `minHeight <= maxHeight`;
- ansichtsspezifische Filter, sodass Teilnehmer-, Spielnummern- und Datumsfilter nicht in einer Tabellenkonfiguration landen.

`EmbedSelection` trennt eine positive Preset-ID von einer Inline-Konfiguration. Der dokumentierte Block-Standard `presetId: 0` bedeutet Inline-Modus. Eine positive Preset-ID zusammen mit `config` ist ein Fehler und kann später weder durch Block noch Shortcode oder REST-Meta an den Renderer gelangen.

Die ES-Modul-Implementierung `assets/src/embed-config.js` stellt dieselben Defaults, Normalisierungsregeln, drei Bereichsrelationen und die getrennte Preset-Auswahl für den späteren Block-Editor bereit. Die PHP-Prüfung bleibt für gespeicherte Daten und jede Frontendausgabe maßgeblich.

## Referenzen und URL-Vertrauen

`TournamentReference` akzeptiert die vertraglich dokumentierten Eingaben:

- positive numerische IDs mit höchstens 20 Ziffern;
- Slugs mit Kleinbuchstaben, Ziffern und einzelnen Bindestrichen;
- kanonische `trn_…`-Referenzen;
- HTTPS-URLs `https://www.turnierplan.eu/t/{reference}`;
- HTTPS-URLs `https://www.turnierplan.eu/live.php?id={numeric-id}`.

Groß-/Kleinschreibung von Host, Slug und kanonischer Referenz wird kontrolliert normalisiert. HTTP, fremde Hosts, Host-Suffixe, abschließende Hostpunkte, Zugangsdaten, Ports, Fragmente, unbekannte oder doppelte Query-Parameter, kodierte Pfadsegmente, Steuerzeichen, führende Nullen und überlange Werte werden abgewiesen.

`ServiceConfiguration` besitzt ausschließlich den festen Produktions-Origin `https://www.turnierplan.eu`. `EmbedUrlBuilder` erhält keine URL aus Block-, Shortcode- oder Preset-Daten. Er erzeugt Metadaten-, öffentliche Fallback- und Frame-URLs nur aus diesem vertrauenswürdigen Ursprung und normalisierten Werten.

Frame-Parameter erscheinen in fester Reihenfolge. Der Builder unterscheidet weiterhin:

- ausgelassene Sichtbarkeitsliste: alle V1-Standardwerte gelten;
- `show=`: alle optionalen Elemente der Ansicht sind ausdrücklich verborgen;
- vollständige sortierte Positivliste: mindestens ein Sichtbarkeitsschalter weicht ab;
- `date=auto`, `date=show` und `date=hide`;
- `branding=show|hide` und `links=new-tab|same-tab`.

`minHeight` und `maxHeight` bleiben Eigenschaften des WordPress-Wrappers und gelangen nicht in die Frame-URL. UUID und Eltern-Origin werden beim URL-Bau erneut geprüft. Die Eltern-Origin muss ein exakter HTTPS-Origin ohne Pfad, Query, Fragment oder Zugangsdaten sein.

## Shortcode-Abbildung und Defaults

`ShortcodeConfigMapper` verarbeitet ausschließlich die dokumentierten Attribute und entfernt WordPress-Slashes genau einmal an dieser späteren Eingabegrenze. Unbekannte Attribute werden gemäß Vertrag ignoriert. `preset` ist mit allen erkannten Inline-Attributen gegenseitig exklusiv.

Der Mapper erzeugt für Inline-Shortcodes dasselbe `EmbedConfig` wie ein gleichwertiges Blockobjekt. Der kanonische Standard-Shortcode enthält nur `tournament` und `view`. Darstellungsvorgaben erscheinen erst bei einer Abweichung. Sobald ein Sichtbarkeitsschalter abweicht, wird die vollständige geordnete Positivliste der Ansicht geschrieben, damit spätere Änderungen an Einrichtungsstandards vorhandene Shortcodes nicht verändern.

V1-Vertragsdefaults und Einrichtungsstandards sind getrennt. `EmbedConfig::from_array()` verwendet für bestehende und kompakte gespeicherte Werte immer die festen Vertragsdefaults. Nur `EmbedConfig::for_new()` übernimmt ausdrücklich übergebene Einrichtungsstandards für eine neue Konfiguration.

Die einzige vor Version 1 vorhandene Entwicklungsform besaß noch keine `schemaVersion`. Sie wird kontrolliert als V1 gelesen und vollständig normalisiert. Andere Versionsnummern werden abgewiesen. Es existieren noch keine älteren veröffentlichten Plugin-Daten, die eine weitere Migration benötigen.

## Lokale fachliche Hinweise

`CachedMetadataValidator` kann bereits vorhandene Metadaten verwenden, um eine nicht mehr angebotene Ansicht oder entfernte Gruppen- und Teilnehmerfilter zu markieren. Fehlende Cachelisten erzeugen keinen behaupteten Fehler. Die Methode verändert die gespeicherte Konfiguration nicht und startet keinen Remote-Abruf; der aktuelle Frame bleibt für die tatsächliche Zuordnung maßgeblich.

Die Turnierzeitzone ist kein frei konfigurierbares Feld. Sie stammt später ausschließlich aus geprüften Metadaten. Der lokale Validator erkennt bekannte IANA-Zeitzonen, während Datumsgrenzen als lokale `YYYY-MM-DD`-Werte an den Dienst übertragen werden.

## Dateien

- neu: `src/config/class-config-exception.php`
- neu: `src/config/class-tournament-reference.php`
- neu: `src/config/class-embed-config.php`
- neu: `src/config/class-embed-config-map.php`
- neu: `src/config/class-embed-selection.php`
- neu: `src/config/class-service-configuration.php`
- neu: `src/config/class-embed-url-builder.php`
- neu: `src/config/class-shortcode-config-mapper.php`
- neu: `src/config/class-cached-metadata-validator.php`
- neu: `assets/src/embed-config.js`
- erweitert: `assets/src/index.js`
- erweitert: `package.json`
- erweitert: `phpcs.xml.dist`
- neu: fünf PHP-Unit-Testklassen und `tests/embed-config.test.mjs`

## Abnahme

Die Tests decken gültige IDs, Slugs, kanonische Referenzen und beide URL-Formen sowie alle dokumentierten URL-Angriffe ab. Weitere Fälle prüfen Enums, echte Booleans, Farben, echte und umgekehrte Datumsbereiche, Spielnummern, Höhenrelationen, ansichtsfremde Filter, unbekannte Felder und Schema-Versionen.

Paritätstests belegen, dass gleichwertige Block- und Shortcodewerte dasselbe vollständige Konfigurationsobjekt ergeben. PHP und JavaScript erzeugen für dieselbe Konfiguration dieselbe Frame-URL samt sortierter `show`-Liste. Ein komplett standardmäßiger Shortcode bleibt kompakt; eine ausdrücklich leere Sichtbarkeitsliste bleibt als leer erhalten.

Der lokale Metadatentest prüft Warnungen für entfernte Filter, fehlende Cachedaten und IANA-Zeitzonen ohne Netzwerkzugriff. Der gesamte bisherige PHP-, JavaScript-, Vertrags-, Build- und WordPress-Smoke-Testbestand bleibt Teil der abschließenden Prüfung.

Block 8 verändert keine Dateien unter `D:\2025\Turnierplan.eu`, startet keine Remote-Anfrage und fügt keine Laufzeitabhängigkeit hinzu.
