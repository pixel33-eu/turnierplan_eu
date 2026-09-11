# Block 01 – Entscheidungsprotokoll

Stand: 11. September 2026
Status: Verbindliche Arbeitsgrundlage für Version 1.0

Dieses Protokoll schließt die offenen Punkte der Produktspezifikation für die Implementierung. Änderungen sind möglich, werden aber als neue Entscheidung mit Auswirkung auf Vertrag, Migration und Tests dokumentiert. Die technischen Entscheidungen gelten ab sofort. Die geschäftlichen Festlegungen zu öffentlicher Nutzung, Archivsichtbarkeit und Branding entsprechen der Roadmap-Empfehlung und können vor Beginn von Block 3 noch geändert werden.

## B01-D01 – Produktidentität

**Entscheidung:** Version 1.0 verwendet folgende Identität:

| Element | Wert |
| --- | --- |
| Anzeigename | Turnierplan.eu – Turniere einbetten |
| Plugin-Slug | `turnierplan-eu` |
| PHP-Namespace | `TurnierplanEU\WordPress` |
| Funktions-/Hook-Präfix | `tpeu_` |
| Textdomain | `turnierplan-eu` |
| Block-Namespace | `turnierplan-eu` |
| Preset-CPT | `tpeu_embed` |

**Folge:** Der Name wird vor der WordPress.org-Einreichung noch gegen Verfügbarkeit und Markenregeln geprüft. Eine notwendige Änderung des öffentlichen Namens ändert nicht automatisch Namespace oder gespeicherte Bezeichner.

## B01-D02 – Umfang von Version 1.0

**Entscheidung:** Version 1.0 umfasst `standings` und `matches`, den dynamischen Gutenberg-Block, den Shortcode, wiederverwendbare Presets, deutsche und englische Plugin-Oberflächen sowie Datums- und Spielnummernfilter für Spiele.

**Nicht enthalten:** Ergebniserfassung, Kontoverknüpfung, geschützte Turniere, frei eingebbare Dienst-Domains, klassische Widgets, native WordPress-Ausgabe, K.-o.-Baum, Turnierübersicht und Veranstalter-Eventseite als auswählbare WordPress-Ansicht.

## B01-D03 – Veröffentlichung und Turnierzustand

**Entscheidung:** Veröffentlichung und sportlicher Zustand sind getrennte Größen.

- Der öffentliche Vertrag wird nur für ausdrücklich veröffentlichte Turniere ausgeliefert.
- Die sportlichen Zustände lauten `upcoming`, `live`, `completed` und `cancelled`.
- `completed` beendet regelmäßiges Live-Polling, entzieht aber nicht die Veröffentlichung.
- Ein veröffentlichtes, beendetes oder abgesagtes Turnier bleibt erreichbar, bis der Veranstalter die Veröffentlichung widerruft oder das Turnier löscht.
- Nicht vorhandene, gelöschte und nicht veröffentlichte Turniere liefern nach außen denselben Fehler `tournament_not_found` mit HTTP 404.
- Die Regeln gelten auch für Daten, die über verknüpfte Turniere aufgelöst werden. Eine öffentliche Einbettung darf keine Daten aus einer nicht veröffentlichten Quelle offenlegen.

**Folge:** Das heutige Feld `status='active'` ist kein ausreichender öffentlicher Vertrag. Block 3 führt eine zentrale, vor jedem Cachetreffer ausgeführte Freigabeprüfung ein.

## B01-D04 – Öffentliche Kennungen und Aliase

**Entscheidung:** Erfolgreich verbundene Blöcke und Presets verwenden unveränderliche, zufällige öffentliche Kennungen:

| Objekt | Kanonisches Format | Beispiel |
| --- | --- | --- |
| Turnier | `trn_` plus 26 Zeichen Crockford Base32 in Kleinschreibung | `trn_01k4f6y7m8n9p0q1r2s3t4v5wx` |
| Gruppe | `grp_` plus 26 Zeichen Crockford Base32 in Kleinschreibung | `grp_01k4f70bcde2fgh3jkm4npq5rs` |
| Teilnehmer | `ptc_` plus 26 Zeichen Crockford Base32 in Kleinschreibung | `ptc_01k4f71bcde2fgh3jkm4npq5rs` |

Der Zeichenvorrat ist `0-9a-hjkmnp-tv-z`; die leicht verwechselbaren Zeichen `i`, `l`, `o` und `u` werden nicht erzeugt. Kennungen ändern sich weder bei Umbenennung noch bei neuer Reihenfolge oder Gruppe.

Numerische Bestands-IDs und bestehende Slugs bleiben Eingabe-Aliase. Nach erfolgreicher Auflösung liefert die API immer `tournament.ref` im kanonischen Format; WordPress speichert diesen Wert bei Blöcken und Presets. Ein direkt geschriebener Shortcode darf einen Alias enthalten, den der Dienst beim Frame-Aufruf auflöst. Alte Slugs werden nach einer Umbenennung weiterhin als Alias aufgelöst oder kontrolliert migriert. Ein Alias wird nie später für ein anderes Turnier wiederverwendet.

Akzeptierte vollständige Eingabe-URLs sind ausschließlich HTTPS-URLs auf `www.turnierplan.eu` mit einem dokumentierten Turnierpfad. Benutzername, Passwort, fremder Port, Fragment und unbekannte Query-Parameter werden abgelehnt. Die genaue Normalisierung steht im [V1-Vertrag](../contracts/v1/README.md#referenzen-und-normalisierung).

## B01-D05 – Sprache und Zeitzone

**Entscheidung:** Die Plugin-Oberfläche wird in Version 1.0 vollständig auf Deutsch und Englisch ausgeliefert. Die Frame-Ausgabe darf weitere Sprachen anbieten, wenn der Metadaten-Endpunkt sie in `supported_languages` meldet.

`language=auto` wird in dieser Reihenfolge aufgelöst:

1. primärer Sprachcode der WordPress-Site, sofern vom Turnier unterstützt;
2. `tournament.default_language`, sofern unterstützt;
3. erster Wert aus `supported_languages`.

Die API gibt eine gültige IANA-Zeitzone aus. Datumsfilter beziehen sich auf das lokale Kalenderdatum in dieser Turnierzeitzone. Der WordPress-Server und der Besucherbrowser dürfen durch ihre eigene Zeitzone keine andere Spielauswahl erzeugen.

## B01-D06 – Datums- und Spielfilter

**Entscheidung:** `dateFrom` und `dateTo` gehören zu Version 1.0. Beide Grenzen sind inklusive; ein fehlender Rand bedeutet offen. Ein Spiel ohne bestimmbares lokales Datum wird bei aktivem Datumsfilter ausgeschlossen.

`matchFrom` und `matchTo` beziehen sich auf die sichtbare positive Spielnummer und sind ebenfalls inklusive. Werden Datum und Spielnummer kombiniert, gilt die Schnittmenge. Für `matches` können Gruppe und Teilnehmer ebenfalls kombiniert werden. Eine gültige Kombination ohne Treffer ist ein leerer Zustand und kein Fehler.

Nicht mehr vorhandene, aber syntaktisch gültige Gruppen- oder Teilnehmerkennungen werden vom Frame ignoriert und als Warnung `filter_ignored` gemeldet. Syntaktisch ungültige Werte führen zu HTTP 400 `invalid_parameter`. Die Tabellenansicht akzeptiert nur den Gruppenfilter.

## B01-D07 – Dienstfreigabe in WordPress

**Entscheidung:** Ein Administrator aktiviert den externen Dienst pro WordPress-Site. Vorher entstehen keine Remote-Abfragen und keine Iframes; vorhandene Einbettungen zeigen einen lokalen Hinweis mit Fallback-Link, soweit aus der gespeicherten Konfiguration sicher ableitbar.

Nach der Freigabe löst erst die bewusste Aktion „Turnier verbinden“ einen Metadatenabruf aus. Das Einfügen eines leeren Blocks und lokale Beispieldaten bleiben offline. Ein Verbindungstest verwendet eine vom Benutzer eingegebene Turnierreferenz; Version 1.0 setzt keinen künstlichen Test-Endpunkt und keine feste Demo-ID voraus.

Der Widerruf stoppt neue Metadatenabrufe und die Ausgabe neuer Frames. Gespeicherte Konfigurationen und Presets bleiben erhalten. Auswirkungen vorhandener Seiten- und Full-Page-Caches werden in der Administration erklärt und in Block 9 soweit möglich invalidiert.

## B01-D08 – Tarife und Branding

**Entscheidung:** Alle ausdrücklich öffentlichen Turniere können ohne gesonderten Einbettungstarif angezeigt werden. Das Plugin enthält keine eigene Lizenzprüfung, Zeitbegrenzung oder lokale Funktionssperre. Das Ausblenden von Branding darf eine echte serverseitige Tarifleistung am Veranstalterkonto beziehungsweise Turnier sein.

Der Dienst meldet eine Branding-Richtlinie:

- `required`: Branding wird im Service-Frame angezeigt und kann clientseitig nicht entfernt werden;
- `optional`: der Benutzer kann `showBranding` wählen;
- `hidden`: der Dienst zeigt kein Branding und der Schalter bleibt verborgen.

Die endgültige Entscheidung trifft stets der Server. Das WordPress-Plugin fügt auf öffentlichen Seiten keine zusätzlichen „Powered by“-Links oder Werbecredits ein.

## B01-D09 – Datenschutz- und Speicherziel

**Entscheidung:** Der Embed-Frame setzt und liest keine Cookies, verwendet weder Local Storage noch Session Storage und enthält kein Tracking oder Werbung. Sein Server-Bootstrap startet keine PHP-Sitzung, verarbeitet keine Auto-Login-Cookies und führt keine allgemeine Seitenaufrufprotokollierung aus.

Vor Release wird dies in einem frischen Browser und bei bestehender Anmeldung auf Turnierplan.eu geprüft. Falls der Browser vorhandene Cookies an den Embed-Endpunkt sendet, ist das Ziel nicht erfüllt; eine technische Trennung, etwa über eine cookielose Auslieferungsgrenze, ist erforderlich. Eine bloße Änderung des Datenschutzhinweises gilt nicht als Abnahme.

Der WordPress-Metadatenproxy übermittelt die Server-IP der WordPress-Installation; der Frame-Abruf kommt aus dem Besucherbrowser und übermittelt dessen technisch übliche HTTP-Daten. Diese Vorgänge werden getrennt dokumentiert.

## B01-D10 – Caching und Freigabewiderruf

**Entscheidung:** Autorisierung und öffentliche Freigabe werden vor Cacheauslieferung und vor einer 304-Antwort geprüft. Ein bestätigter Freigabewiderruf, eine Löschung oder ein bestätigtes 404 darf nicht durch veraltete Erfolgsdaten überdeckt werden.

Ausgangswerte:

| Inhalt | Browser-/Dienstcache | WordPress-Metadatencache |
| --- | --- | --- |
| Live-Inhalt | 30 Sekunden, ETag | nicht im Frontend gespiegelt |
| Metadaten erfolgreich | 60 Sekunden am Dienst | 5 Minuten |
| Nicht gefunden/nicht öffentlich | höchstens 60 Sekunden | 1 Minute |
| Temporärer Fehler | kein dauerhafter Fehlercache | letzter Erfolg höchstens 24 Stunden, nur im Editor klar als veraltet |
| Abgeschlossenes Turnier | längere Cachezeit nach Servervorgabe | 1 Stunde, manuell aktualisierbar |

Freigabeänderungen invalidieren zugehörige Servercaches aktiv. Der WordPress-Fallback wird nur bei Transportfehlern oder 503 verwendet, nie nach einem autoritativen 404.

## B01-D11 – Live-Aktualisierung

**Entscheidung:** Tabelle und Spiele aktualisieren sich innerhalb des Frames. Der Frame lädt dafür sein eigenes versioniertes Dokument `/embed/v1/tournaments/{reference}` konditional neu. Das Plugin und sein JavaScript kennen weder `scores.php` noch ein internes Ergebnisformat.

Der Ausgangstakt beträgt bei `live` 30 Sekunden und bei `upcoming` 60 Sekunden. In inaktiven Tabs pausiert der Timer. Beim erneuten Sichtbarwerden wird nur dann zeitnah aktualisiert, wenn das letzte Laden älter als der jeweilige Takt ist. `completed` und `cancelled` werden nicht regelmäßig aktualisiert. 429/503 führen zu begrenztem Backoff.

**Folge:** Falls Block 6 zeigt, dass vollständiges Dokument-Neuladen sichtbare oder barrierebezogene Nachteile verursacht, benötigt ein alternativer Snapshot-Endpunkt vor der Implementierung eine eigene versionierte Schemaentscheidung.

## B01-D12 – Frame-Kommunikation

**Entscheidung:** Das Browserprotokoll verwendet einen gemeinsamen Nachrichtenumschlag mit Version 1 und den Ereignissen `ready`, `resize` und `status`. Der Empfänger prüft immer exakte Origin, `event.source`, Instanz, Typ, Version und Nutzlast.

Der WordPress-Renderer übergibt `instance` sowie seine kanonische `parent_origin` an den Frame. Diese Origin begrenzt das `postMessage`-Ziel und ist keine Autorisierung oder Domain-Allowlist. Der öffentliche Zugriff bleibt von der Freigaberegel des Turniers abhängig.

## B01-D13 – API- und Konfigurationsversionen

**Entscheidung:** Folgende Versionen entwickeln sich unabhängig:

- `/api/embed/v1` und `/embed/v1` bezeichnen die Hauptversion der öffentlichen Dienstverträge;
- `schema_version: 1` bezeichnet die Antwortstruktur des jeweiligen V1-Vertrags;
- `schemaVersion: 1` bezeichnet die gespeicherte WordPress-Konfiguration;
- die Block-API-Version und Plugin-Version folgen den jeweiligen WordPress-/Release-Regeln.

Unbekannte Antwortfelder einer bestehenden Hauptversion werden von Clients ignoriert. Unbekannte Anfrageparameter werden mit HTTP 400 abgewiesen. Entfernte oder semantisch geänderte Felder benötigen eine neue Hauptversion. Neue optionale Antwortfelder dürfen in V1 ergänzt werden, wenn bestehende Clients korrekt weiterarbeiten.

## B01-D14 – Systemziele

**Entscheidung:** Ausgangsbasis der Neuentwicklung:

| Komponente | Ziel |
| --- | --- |
| WordPress | 6.5 oder neuer |
| PHP | 8.3 oder neuer |
| Browser | aktuelle zwei Hauptversionen von Chrome, Edge, Firefox und Safari; funktionale Basis mit ES2019 |
| Installationen | Single Site und Multisite |
| Themes | Block-Themes und klassische Themes |
| Classic Editor/Page Builder | Shortcode |

„Tested up to“ und tatsächlich unterstützte neuere PHP-/WordPress-Versionen werden erst nach erfolgreicher Testmatrix genannt. Die Mindestwerte werden spätestens vor Block 15 noch einmal anhand Reichweite, Sicherheitsstatus und realer Tests überprüft.

## B01-D15 – Lizenz und Veröffentlichung

**Entscheidung:** Eigener Plugin-Code wird unter `GPL-2.0-or-later` veröffentlicht. Abhängigkeiten und Assets müssen GPL-kompatibel sein und werden einzeln dokumentiert. Lesbare Quellen und eine reproduzierbare Build-Anleitung gehören zum Release.

GitHub ist die Entwicklungsquelle. Nach Zulassung wird die WordPress.org-Ausgabe über das zugewiesene SVN-Repository veröffentlicht und ausschließlich über WordPress.org aktualisiert; sie enthält keinen externen Plugin-Updater.

## Auswirkung auf nachfolgende Blöcke

- Block 2 übernimmt Identität, Mindestversionen, Lizenz und getrennte Versionsquellen.
- Blöcke 3–7 implementieren Veröffentlichung, Kennungen, Cache, Frame und Browserprotokoll.
- Blöcke 8–12 verwenden ausschließlich das kanonische Konfigurationsschema und die Feldzuordnung.
- Blöcke 13–15 prüfen Sprache, Datenschutz, Kompatibilität und Veröffentlichung gegen diese Entscheidungen.

Die maschinenprüfbaren Verträge und alle Feldzuordnungen stehen unter [`docs/contracts/v1`](../contracts/v1/README.md).
