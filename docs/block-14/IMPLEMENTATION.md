# Block 14: Lebenszyklus, Multisite und Gesamtprüfung

Stand: 10. Oktober 2026

## Status

Die lokale Implementierung und die reproduzierbaren Einzelinstallations-, Upgrade-, Deaktivierungs-, Deinstallations-, Kompatibilitäts- und Multisite-Prüfungen sind abgeschlossen. Der offizielle Plugin Check sowie die PHP- und Node-Matrix sind im [GitHub-Actions-Lauf 38003128472](https://github.com/pixel33-eu/turnierplan_eu/actions/runs/38003128472) vollständig bestanden. Das externe Abnahmetor wurde am 10. Oktober 2026 in einem echten Chrome-Browser abgeschlossen. Alle 18 Kriterien aus §19 sind damit bestanden.

Die Release-ZIP und der automatische Versionsgleichlauf aus den Kriterien 14 und 18 sind in Block 15 umgesetzt. Block 14 ist vollständig abgenommen.

## Reale HTTPS-Browserprüfung

Der Browserlauf verwendete WordPress 7.1.3 im offiziellen WordPress Playground unter HTTPS und eine parallel nachgewiesene, bestehende Anmeldung auf `www.turnierplan.eu`. Das Plugin wurde aus einem bereinigten Testarchiv installiert und aktiviert. Der Verbindungstest für Turnier `400` war erfolgreich.

Eine Entwurfsvorschau lud gleichzeitig Tabelle und Spielplan. Beide Frames zeigten reale Daten und meldeten den geladenen Zustand. Sichtbar geladene Ressourcen beschränkten sich auf die beiden Frame-Dokumente, das versionierte lokale Embed-JavaScript, das lokale Embed-Stylesheet und Turnierlogos unter demselben Turnierplan.eu-Ursprung. Beide Frame-Dokumente meldeten eine leere `document.cookie`-Zeichenkette. Direkte GET-Prüfungen der beiden Frame-Dokumente sowie der ausgelieferten JavaScript- und CSS-Datei enthielten keinen `Set-Cookie`-Header.

Die abschließende manuelle Chrome-DevTools-Prüfung bestätigte für beide `embed/v1/tournaments/400`-Dokumentanfragen, dass kein `Cookie`-Anfrageheader übertragen wurde. Nach dem ausdrücklichen Blockieren von Drittanbieter-Cookies und erneutem Laden blieben sowohl Tabelle als auch Spielplan funktionsfähig. Damit ist das gesonderte Cookie- und Speicher-Abnahmetor erfüllt.

Nach Widerruf der Dienstfreigabe und erneutem Laden enthielt die WordPress-Vorschau null Turnierplan-Iframes und null externe Turnierplan-Ressourcen. Stattdessen erschienen ausschließlich die lokalen Hinweise und Fallback-Links. Anschließend wurde die Freigabe wiederhergestellt.

Der Lauf deckte außerdem drei Darstellungsfehler auf. Numerische Referenzen wurden als nicht unterstütztes `/t/{id}` ausgegeben. `EmbedUrlBuilder` verwendet dafür nun `/live.php?id={id}`; Slugs und kanonische Referenzen bleiben unter `/t/{reference}`. Sehr lange Spielpläne erreichten die bisherige Maximalhöhe von 4000 Pixeln und erzeugten dadurch einen Scrollbereich, der deutlich höher als der Bildschirm war. Die sichtbare Frame-Höhe ist nun zusätzlich auf 80 Prozent der Viewport-Höhe begrenzt. Außerdem folgte der lokale Fallback-Link der WordPress-Oberflächensprache, obwohl der Frame ausdrücklich mit `lang="de"` konfiguriert war. Jetzt folgen alle lokalen Titel-, Status-, Fehler- und Linktexte derselben aufgelösten Ausgabesprache wie der Frame; ein Playground-Test prüft dies auf einer englischen WordPress-Installation.

## Lebenszyklus

`Lifecycle` verwaltet Plugin- und Datenbankschema-Version getrennt. Aktivierung und gewöhnliche Updates führen dieselbe idempotente Site-Installation aus. Dabei entstehen keine Beispieldaten und keine Remote-Anfragen.

| Vorgang | Nachgewiesenes Verhalten |
| --- | --- |
| Aktivierung | Prüft WordPress/PHP, setzt Plugin- und Datenbankversion und installiert nur die eigenen Capabilities. |
| Update/Reaktivierung | Erkennt alte Versionsoptionen, stellt Versionen und Capabilities wieder her und verändert vorhandene Presets nicht. |
| Deaktivierung | Entfernt nur bekannte eigene Cron-Ereignisse. Einstellungen und Presets bleiben erhalten. |
| Deinstallation ohne Löschoption | Behält Einstellungen, Presets, Versionen und Capabilities. Das ist der Standard. |
| Deinstallation mit Löschoption | Entfernt eigene Presets samt Meta, exakt bekannte Optionen, exakt registrierte Transients, eigene Capabilities und eigene Cron-Ereignisse. Fremde Optionen, Transients, Beiträge und Cron-Ereignisse bleiben erhalten. |

`uninstall.php` schützt direkten Zugriff über `WP_UNINSTALL_PLUGIN` und delegiert an den sitebezogenen Uninstaller. Alle Transient-Schreibvorgänge laufen über `TransientRegistry`. Dadurch funktioniert die exakte Bereinigung auch mit persistentem Object Cache und benötigt weder Tabellenwildcards noch direkte SQL-Abfragen.

Die Umsetzung folgt den offiziellen WordPress-Hinweisen zu [Aktivierungs- und Deaktivierungshooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/) und zur [Deinstallation](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/).

## Multisite

Bei Netzaktivierung wird jede bestehende Site einzeln initialisiert. `wp_initialize_site` initialisiert später angelegte Sites, sofern das Plugin weiterhin netzwerkweit aktiv ist. Jede Site besitzt eigene Einstellungen, Versionsoptionen, Rollenrechte, Presets, Transient-Register und Cachewerte.

Der Multisite-Playground-Test erstellt nach der Netzaktivierung eine zweite Site und prüft:

- automatische Initialisierung der neuen Site;
- keine Übernahme der Einstellungen der Haupt-Site;
- keine Cachewert-Übernahme zwischen Sites;
- getrennte Löschentscheidung: Haupt-Site behält ihre Daten, zweite Site löscht nur ihre eigenen Plugin-Daten;
- Erhalt einer fremden Option auf der zweiten Site;
- Wiederherstellung des ursprünglichen Blog-Kontexts nach Sitewechsel und Deinstallation.

Damit ist das Verhalten für bestehende und später angelegte Sites reproduzierbar. Grundlage sind `wp_initialize_site` und die [Multisite-Netzwerkverwaltung](https://developer.wordpress.org/advanced-administration/multisite/administration/).

## Sicherheits- und Angriffsmatrix

| Angriffsfläche | Automatischer Nachweis |
| --- | --- |
| Turnierreferenz/URL | PHPUnit lehnt fremde Hosts, HTTP, Steuerzeichen, unbekannte Felder und ungültige Referenzen ab. |
| Redirects | `MetadataClientTest` erlaubt nur das feste HTTPS-Ziel, begrenzt Redirects und lehnt unsichere Ziele ab. |
| Remote-Titel | Validierung begrenzt Datentyp und Länge; gemeinsamer Renderer maskiert Titel und Attribute kontextbezogen. |
| Shortcode | Unbekannte Attribute werden nicht in die Frame-URL übernommen; ungültige und fehlende Konfiguration erzeugt keinen Iframe. |
| REST | Gast- und Nonce-lose Anfragen werden abgewiesen; Core-CPT-Routen sind ebenfalls geschützt; Antworten enthalten keine Autorendaten. |
| Presets | Nur exakt angeforderte, veröffentlichte und valide Presets werden öffentlich aufgelöst; Entwürfe und fehlende IDs liefern keinen Iframe. |
| Browsernachrichten | Origin, Quelle, Instanz-ID, Nachrichtentyp und Höhenbereich müssen gemeinsam passen; entfernte Frames verlieren ihre Listener. |
| Frontend-Verfügbarkeit | Normales Rendern erzeugt keinen WordPress-HTTP-Aufruf; ungültige, deaktivierte oder nicht verfügbare Zustände blockieren die WordPress-Seite nicht. |

## Kompatibilitätsmatrix

| Bereich | Ergebnis |
| --- | --- |
| Mindestumgebung | WordPress 6.5.13, PHP 8.3, Standard-Block-Theme: bestanden. |
| Aktuelle Zielumgebung | WordPress 7.1.3, PHP 8.5, klassisches Twenty Twenty-One: bestanden. |
| Classic Editor | Shortcode wird im WordPress-Integrationstest ohne Block-Editor gerendert: bestanden. |
| Multisite | Netzaktivierung, neue Site, Cachetrennung und Löschregeln: bestanden. |
| Viewports | Echter Chromium bei 320, 375, 768 und 1440 Pixeln: bestanden in Block 13. |
| Helles/dunkles Theme | Konfigurations-, URL- und Frame-Tests für `light`, `dark` und `auto`: bestanden. |
| Mehrere Frames | Eindeutige Instanzen, getrennte Nachrichtenquellen und Listener-Bereinigung: bestanden. |
| Turniermodi und Wertungen | Lokale synthetische Frame-Fixtures der getrennten Turnierplan-Seite: bestanden in Block 13; keine Produktionsaussage. |
| Ausfälle | Nicht erreichbar, ungültig, inaktiv, leer und veraltet sind durch Unit-, Frontend- und Playground-Tests abgedeckt. |

Die aktuelle WordPress-Version wurde am Testtag gegen die offizielle WordPress.org-Angabe des Plugin-Check-Projekts geprüft. Eine künftig veröffentlichte WordPress-Version wird erst nach einem neuen Lauf als unterstützt bezeichnet.

## Kriterien aus §19

| Nr. | Status | Nachweis oder nächstes Tor |
| ---: | --- | --- |
| 1 | bestanden | Gutenberg-Zustandstest und echter WordPress-Block-/REST-Lauf. |
| 2 | bestanden | Metadaten-, Gateway-, REST- und Editorzustandstests. |
| 3 | bestanden | Gemeinsamer Renderer für Block, Shortcode und Preset im Playground. |
| 4 | bestanden | Mehrinstanz-Frontend- und Browserprotokolltests. |
| 5 | bestanden | Chromium-Prüfung bei 320 Pixeln ohne Seitenüberlauf. |
| 6 | bestanden | Gefälschte Origin, Quelle, Instanz und Höhe bleiben wirkungslos. |
| 7 | bestanden | Kein WordPress-HTTP beim Rendern; sichere Fehler- und Fallbackzustände. |
| 8 | bestanden | Eigene und Core-REST-Routen schützen Presets, Entwürfe und Benutzerdaten. |
| 9 | bestanden | HTTPS-Lauf mit bestehender Anmeldung, zwei realen Frames, leerem `document.cookie`, fehlenden `Set-Cookie`-Antwortheadern, fehlenden `Cookie`-Anfrageheadern, blockierten Drittanbieter-Cookies und widerrufener Dienstfreigabe. |
| 10 | bestanden | 222 englische Originale, vollständige deutsche PHP-/JS-Kataloge und echter MO-Ladetest. |
| 11 | bestanden | Getrennter Einzelinstallations- und Multisite-Lebenszykluslauf. |
| 12 | bestanden | PHP-, JS-, Contract-, Browser-, Build- und WordPress-Läufe lokal und in GitHub Actions grün; das reale Netzwerktor unter Nr. 9 ist ebenfalls abgeschlossen. |
| 13 | bestanden | Offizieller `wordpress/plugin-check-action@v1`-Job im [GitHub-Actions-Lauf 38003128472](https://github.com/pixel33-eu/turnierplan_eu/actions/runs/38003128472) grün. |
| 14 | bestanden | Das reproduzierbare Paket enthält genau ein Plugin-Verzeichnis und besteht den frischen WordPress-Installationstest. |
| 15 | bestanden | Shortcode-Generator und Unit-Test lassen Darstellungsdefaults weg. |
| 16 | bestanden | Direkter Inline-Vorschaustatus ohne Preset ist getestet. |
| 17 | bestanden | Editor-Viewportzustände und unveränderte Frontend-Konfiguration sind getestet. |
| 18 | bestanden | VERSION, Plugin-Header, Konstante, Stable Tag, Changelog, Paket- und Blockversion sowie ZIP-Dateiname werden automatisch abgeglichen. |

## Restore und Upgrade

Das Plugin verwendet keine eigenen Datenbanktabellen. Für eine Wiederherstellung reichen die WordPress-Datenbankinhalte der Site: Beiträge vom Typ `tpeu_embed` mit `_tpeu_config`, `tpeu_settings` sowie die WordPress-Rollenoptionen. Eine Deaktivierung kann jederzeit rückgängig gemacht werden. Eine Reaktivierung oder ein Plugin-Update stellt fehlende Versionsmarker und eigene Rollenrechte idempotent wieder her.

Wer die Löschoption aktiviert und das Plugin anschließend über WordPress löscht, entscheidet sich ausdrücklich gegen eine spätere Wiederherstellung der Plugin-Daten aus der laufenden Datenbank. Ein Restore ist dann nur aus einem vorherigen WordPress-Datenbankbackup möglich.

## Reproduzierbare Prüfungen

```powershell
composer check
npm run check
npm run test:responsive-browser
```

`npm run check` umfasst Übersetzungen, Verträge, Lebenszyklusvertrag, JavaScript-Tests, Build, Lizenzen, reproduzierbares Release-Paket, WordPress 6.5/PHP 8.3 mit Block-Theme, WordPress 7.1.3/PHP 8.5 mit klassischem Theme, Einzel-Lebenszyklus, Multisite und frische Installation aus der ZIP. GitHub führt zusätzlich den offiziellen [Plugin Check Action](https://github.com/WordPress/plugin-check-action) gegen das aus derselben Release-ZIP entpackte Verzeichnis aus.
