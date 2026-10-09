# Block 14: Lebenszyklus, Multisite und Gesamtprüfung

Stand: 9. Oktober 2026

## Status

Die lokale Implementierung und die reproduzierbaren Einzelinstallations-, Upgrade-, Deaktivierungs-, Deinstallations-, Kompatibilitäts- und Multisite-Prüfungen sind abgeschlossen. Zwei externe Abnahmetore bleiben offen:

1. Die reale Browser-Netzwerkmessung mit einer bestehenden Turnierplan-Anmeldung ist noch nicht durchgeführt. In dieser Sitzung war keine Browser-Sitzung verfügbar. Kriterium 9 aus §19 ist deshalb ausdrücklich **nicht bestanden**.
2. Der offizielle Plugin Check ist als eigener GitHub-Actions-Job eingerichtet. Sein Ergebnis gilt erst nach einem erfolgreichen Lauf auf GitHub als bestanden.

Die Release-ZIP und der automatische Versionsgleichlauf aus den Kriterien 14 und 18 gehören entsprechend der Roadmap zu Block 15. Der Roadmap-Haken für Block 14 bleibt bis zu den beiden externen Toren offen.

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
| Aktuelle Zielumgebung | WordPress 7.0.7, PHP 8.5, klassisches Twenty Twenty-One: bestanden. |
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
| 9 | offen | Reale DevTools-Netzwerkmessung mit bestehender Anmeldung, blockierten Drittanbieter-Cookies, mehreren Frames und widerrufener Freigabe erforderlich. |
| 10 | bestanden | 222 englische Originale, vollständige deutsche PHP-/JS-Kataloge und echter MO-Ladetest. |
| 11 | bestanden | Getrennter Einzelinstallations- und Multisite-Lebenszykluslauf. |
| 12 | lokal bestanden | PHP-, JS-, Contract-, Browser-, Build- und WordPress-Läufe grün; reales Netzwerktor bleibt unter Nr. 9 offen. |
| 13 | ausstehender CI-Nachweis | Offizieller `wordpress/plugin-check-action@v1`-Job ist eingerichtet. |
| 14 | Block 15 | Wird am tatsächlichen Release-ZIP geprüft. |
| 15 | bestanden | Shortcode-Generator und Unit-Test lassen Darstellungsdefaults weg. |
| 16 | bestanden | Direkter Inline-Vorschaustatus ohne Preset ist getestet. |
| 17 | bestanden | Editor-Viewportzustände und unveränderte Frontend-Konfiguration sind getestet. |
| 18 | Block 15 | Versions- und ZIP-Gleichlauf wird im Releaseprozess fertiggestellt. |

## Restore und Upgrade

Das Plugin verwendet keine eigenen Datenbanktabellen. Für eine Wiederherstellung reichen die WordPress-Datenbankinhalte der Site: Beiträge vom Typ `tpeu_embed` mit `_tpeu_config`, `tpeu_settings` sowie die WordPress-Rollenoptionen. Eine Deaktivierung kann jederzeit rückgängig gemacht werden. Eine Reaktivierung oder ein Plugin-Update stellt fehlende Versionsmarker und eigene Rollenrechte idempotent wieder her.

Wer die Löschoption aktiviert und das Plugin anschließend über WordPress löscht, entscheidet sich ausdrücklich gegen eine spätere Wiederherstellung der Plugin-Daten aus der laufenden Datenbank. Ein Restore ist dann nur aus einem vorherigen WordPress-Datenbankbackup möglich.

## Reproduzierbare Prüfungen

```powershell
composer check
npm run check
npm run test:responsive-browser
```

`npm run check` umfasst Übersetzungen, Verträge, Lebenszyklusvertrag, JavaScript-Tests, Build, Lizenzen, WordPress 6.5/PHP 8.3 mit Block-Theme, WordPress 7.0.7/PHP 8.5 mit klassischem Theme, Einzel-Lebenszyklus und Multisite. GitHub führt zusätzlich den offiziellen [Plugin Check Action](https://github.com/WordPress/plugin-check-action) aus.
