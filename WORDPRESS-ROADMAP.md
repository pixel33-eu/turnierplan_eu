# Turnierplan.eu für WordPress – Entwicklungsplan in 15 Blöcken

Stand: 12. September 2026. Grundlage: [WORDPRESS.md](./WORDPRESS.md), die vorhandene Turnierplan.eu-Anwendung und die unten verlinkten offiziellen Vorgaben.

Diese Roadmap zerlegt die Produktspezifikation in prüfbare Arbeitspakete für Version 1.0. Sie dokumentiert außerdem notwendige Präzisierungen. Die ursprüngliche Spezifikation bleibt als Ausgangsbasis erhalten; die Blöcke 1 bis 6 sind umgesetzt. Die folgenden Blöcke sind geplant.

## Ergebnis der Analyse

Das Vorhaben besteht aus zwei Lieferteilen: Turnierplan.eu stellt öffentliche, versionierte Metadaten und schlanke Turnieransichten bereit; WordPress konfiguriert und zeigt diese Inhalte. Die vorhandene Website liefert fachliche Grundlagen, aber noch keinen hinreichend abgesicherten Embed-Vertrag.

Der erste vollständige Release umfasst Tabellen und Spielpläne, Gutenberg, Shortcode, wiederverwendbare Presets, Vorschauen, Filter, deutsche und englische Oberflächen sowie den Betrieb auf Einzelinstallationen und Multisite. Presets sind für Anwender optional, gehören aber zum hier geplanten Lieferumfang. Turnierverwaltung, Ergebniserfassung und Kontoverknüpfung bleiben auf Turnierplan.eu.

### Quellen und Eigenständigkeit

- Plugin-Workspace: `F:\Turnierplan.eu\WordPress Plugin`.
- Bestehende Anwendung: `D:\2025\Turnierplan.eu`; sie wurde für diese Planung ausschließlich gelesen. Ihre `WORDPRESS.md` ist mit der ursprünglichen Workspace-Datei identisch.
- GitHub: [pixel33-eu/turnierplan_eu](https://github.com/pixel33-eu/turnierplan_eu). Der lokale Branch `main` verfolgt `origin/main`; die bestehende Historie wurde übernommen.
- Vom genannten Referenzplugin wurde ausschließlich die `readme.txt` als Funktionsbeschreibung gelesen. Es wurden weder Implementierung noch Verzeichnisarchitektur als Vorlage analysiert oder übernommen. Die beschriebenen allgemeinen Ideen wie Tabellen, Spielpläne und Vorschau sind bereits durch die eigene Spezifikation abgedeckt.
- Code, Dateiorganisation, Bezeichner, Texte, Gestaltung, Beispiele und Tests entstehen aus den eigenen Anforderungen. Keine Dateien, Ausschnitte, Assets oder Übersetzungen des Referenzplugins werden ins Projekt übernommen.

### Befunde, die den Arbeitsplan bestimmen

Dateiangaben in dieser Tabelle beziehen sich auf die vorhandene Turnierplan.eu-Anwendung, sofern nicht ausdrücklich `WORDPRESS.md` genannt ist. Die Befunde beruhen auf lokalem Quelltext, nicht auf einer Prüfung des Produktionssystems. Die heutige Bedeutung von `public`, direkter Linkfreigabe und öffentlicher Listung muss in Block 1 unterschieden werden; sie darf nicht allein aus dem Feldnamen abgeleitet werden.

| Befund | Konsequenz | Zuständiger Block |
| --- | --- | --- |
| `scores.php:16` kann einen Cachetreffer vor `security_check()` ausliefern; die Turnierabfrage in `includes/func.php:1321` prüft `status='active'`, aber keine ausdrückliche öffentliche Freigabe. | Sichtbarkeit muss vor jeder Auslieferung einschließlich Cache/304 geprüft werden; Entzug einer Freigabe braucht eine definierte Cache-Invalidierung. | 1, 3, 4 |
| `widgets/event-frame.php:35` verwendet `eventpage=1`; das ersetzt keine einheitliche Prüfung von Status und Veröffentlichung. | Auch Eventseiten und verknüpfte Turniere müssen dieselbe Freigaberegel anwenden. | 3, 7 |
| `widgets/event-frame.php:4` lädt den allgemeinen Bootstrap; `includes/app.php:2` startet eine Sitzung, `:52` ruft Seitenprotokollierung auf, `widgets/event-frame.php:62` lädt ein CDN-Skript. | Neuer schlanker Einstieg ohne Sitzung, automatische Seitenprotokollierung und allgemeine CDN-Abhängigkeiten. Netzwerkverhalten einschließlich vorhandener Cookies prüfen. | 3, 5, 14 |
| `create.php:177`/`:184` schreibt beziehungsweise berechnet den Slug beim Speichern neu. Teilnehmerkennungen im Score-Payload verwenden veränderbare Positionen (`includes/scores_lib.php:842`, `participants.php:61`). | Dauerhafte Turnier- und Teilnehmerreferenzen sowie Umgang mit alten URLs definieren. | 1, 3 |
| Der Score-Builder verwendet globale Sprache, globale Zustände und Requestparameter. | Adapter mit expliziten Eingaben und isolierten Ergebnissen; sprach- und variantenabhängige Cache-Schlüssel. | 3, 4, 6 |
| `WORDPRESS.md` §§6.2/6.4 beschreiben Datumsfilter; §§8/9.3 modellieren nur Spielnummern. | Datumsfilter vollständig spezifizieren. Planungsvorschlag: in Version 1.0 aufnehmen, einschließlich Turnierzeitzone und inklusiver Grenzen. | 1, 6, 8 |
| Das Metadatenbeispiel enthält keine Liste erlaubter Sprachen und keine vollständigen Fähigkeiten je Ansicht. | Sprachliste, Filter, Spalten, Wertungsarten und unterstützte Optionen verbindlich ergänzen. | 1, 4 |
| Für `showDate=auto`, Linkverhalten, Gruppennavigation und Branding fehlt eine vollständige Übertragung ins Frame-Schema. | Ein Mapping von Konfiguration, Shortcode und Frame-Parametern erstellen; „ausgelassen“, „aus“ und „automatisch“ eindeutig unterscheiden. | 1, 8 |
| Die Spezifikation verlangt aktuelle Filtervalidierung und einen Turniertitel, aber keine WordPress-Remoteabfrage im Frontend. | WordPress prüft lokal Syntax und verfügbare Cachewerte; der Frame prüft aktuelle Gültigkeit. Ohne Titelcache gibt es einen übersetzten Iframe-Titel mit Referenz. | 4, 8, 10 |
| Das bisher geplante Browserprotokoll kennt nur Größenmeldungen. | Bereitschafts- und Statusmeldungen sowie einen außerhalb des Frames nutzbaren Fallback ergänzen. | 7, 10 |
| `maxHeight < minHeight` ist derzeit möglich; sehr lange Listen können die Grenze von 8000 Pixeln überschreiten. | Höhenrelation prüfen, Meldungen begrenzen und vollständigen Inhalt durch zugängliches Scrollen erreichbar halten. | 7, 8 |
| Der Preset-CPT soll REST-Meta verwenden, unterstützt laut §10.3 aber nur `title`. | Für den Standard-REST-Weg `custom-fields` ergänzen und Zugriffe auf Listen/Einzelobjekte ausdrücklich schützen. | 12 |
| Administratorfreigabe, bloße Eingabe und „Turnier verbinden“ sind als Abrufauslöser uneindeutig. | Freigabezustand und bewusste Verbindungsaktion festlegen; einen Verbindungstest nicht von einer erfundenen Demo-ID abhängig machen. | 1, 9 |
| §9.1 fordert aktive öffentliche Turniere, §11.5 nennt beendete öffentliche Turniere. | Veröffentlichung, Spielbetrieb und Archivierung als getrennte Zustände behandeln. | 1, 3, 6 |

WordPress bestätigt die erforderliche `custom-fields`-Unterstützung für REST-Metadaten ausdrücklich. Die nicht öffentliche CPT-Konfiguration ersetzt die gesonderten Zugriffstests nicht. [REST-Metadaten dokumentiert](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/#read-and-write-a-post-meta-field-in-post-responses).

PHP 8.3 oder neuer ist als Arbeitsgrundlage festgelegt; die ursprüngliche PHP-8.1-Angabe wäre für eine Neuentwicklung veraltet. WordPress 6.5 bleibt zunächst ein Kompatibilitätsziel, keine bereits nachgewiesene Kompatibilität. Beide Mindestwerte werden vor dem Release gegen reale Tests und den dann aktuellen Sicherheitsstatus geprüft. [PHP-Supportübersicht](https://www.php.net/supported-versions.php).

## Überblick und Reihenfolge

„Hängt ab von“ bezeichnet benötigte Ergebnisse. Architektur und Testfälle können früher vorbereitet werden; die jeweilige Abnahme benötigt die genannten Vorgänger. Sicherheit, Übersetzbarkeit und zugängliche Bedienung werden in jedem Block mitgebaut und später zusammenhängend geprüft.

| Nr. | Entwicklungsblock | Bereich | Hängt ab von | Prüffähiges Ergebnis |
| --- | --- | --- | --- | --- |
| 01 | Produktentscheidungen und V1-Verträge | Beide | – | Eindeutige Regeln, Schemas und Beispiele |
| 02 | Repository, Entwicklungsumgebung und Plugin-Grundgerüst | WordPress/Entwicklung | 01 | Aktivierbares Grundgerüst und laufende Basisprüfungen |
| 03 | Öffentliche Referenzen, Freigaben und Datenadapter | Turnierplan.eu | 01 | Kontrollierter Zugriff auf öffentliche Turnierdaten |
| 04 | Metadaten-API mit Fehlern und Cache | Turnierplan.eu | 03 | Versionierter Endpunkt für Editor und Tests |
| 05 | Embed-Grundlayout und Turniertabelle | Turnierplan.eu | 03, 04 | Eigenständig nutzbarer Tabellen-Iframe |
| 06 | Spielplan, Filter und Live-Aktualisierung | Turnierplan.eu | 05 | Vollständiger Spiele-Iframe |
| 07 | Browserprotokoll und bestehendes Event-Widget | Beide | 05, 06 | Sichere Größen- und Statuskommunikation |
| 08 | Zentrales Konfigurationsmodell und URL-Bau | WordPress | 01, 02 | Gemeinsame Validierung für alle Eingabewege |
| 09 | Einstellungen, Dienstfreigabe und Metadaten-Proxy | WordPress | 04, 08 | Geschützte, gecachte Editor-Verbindung |
| 10 | Gemeinsamer Renderer und Shortcode | WordPress | 07, 08, 09 | Nutzbare Frontend-Einbettung ohne Editorpflicht |
| 11 | Gutenberg-Block und direkte Vorschau | WordPress | 09, 10 | Tabelle/Spielplan direkt im Block konfigurierbar |
| 12 | Wiederverwendbare Presets und Verwaltungsoberfläche | WordPress | 10, 11 | Sichere zentrale Verwaltung und Wiederverwendung |
| 13 | Bedienqualität, Barrierefreiheit und Sprachen | Beide | 11, 12 | Durchgängig geprüfte deutsche und englische Nutzung |
| 14 | Lebenszyklus, Multisite und Gesamtprüfung | Beide | 12, 13 | Nachgewiesene Sicherheit und Betriebsfähigkeit |
| 15 | Dokumentation, Release und WordPress.org | Veröffentlichung | 14 | Installierbares, geprüftes Veröffentlichungspaket |

Nach Block 1 können die Backend-Arbeiten 3–7 und die WordPress-Grundlagen 2/8 teilweise parallel laufen. Block 9 führt beide Lieferteile zusammen. Ein fertig getesteter Test-Iframe aus 5–7 ist Voraussetzung für die Frontend-Abnahme in Block 10.

## Block 01 – Produktentscheidungen und V1-Verträge

**Ziel:** Alle späteren Komponenten verwenden dieselben fachlichen Regeln. Bezug: Spezifikation §§1–9, 17, 21.

**Status:** Ausgearbeitet am 11. September 2026. Maßgeblich sind das [Entscheidungsprotokoll](docs/block-01/DECISIONS.md) und der [V1-Vertrag](docs/contracts/v1/README.md). Die geschäftlichen Festlegungen werden als dokumentierte Arbeitsentscheidungen geführt und können vor Block 3 kontrolliert geändert werden.

**Arbeitspakete:**

- Öffentlichkeit und Sichtbarkeit definieren: aktiv, beendet, archiviert, verborgen, gelöscht; Verhalten nach Entzug der Freigabe und bei verknüpften Turnieren festhalten.
- Öffentliche Turnier- und Teilnehmerkennungen, akzeptierte URLs, Slug-Aliase, maximale Längen und Normalisierung festlegen.
- Metadaten-, Frame-, Fehler- und Browserprotokoll versionieren; Beispiele ausschließlich mit eigenen fiktiven Daten erstellen.
- Vollständige Feldzuordnung mit Datentypen, Defaults, Grenzwerten, Sprachauflösung und Fähigkeiten je Ansicht erstellen.
- Datumsfilter inklusive Zeitzone, Tagesgrenzen, fehlendem Datum und Zusammenspiel mit Spielnummern spezifizieren.
- Dienstfreigabe, Freigabewiderruf, serverseitige Tarifregeln, Branding und öffentliche Archivierung als ausdrückliche Produktentscheidungen dokumentieren.
- WordPress-/PHP-Mindestversion, Browserziel und Unterstützungsumfang festlegen. Deutsch/Englisch sind vollständig getestete Zielsprachen.

**Lieferung:** Erfüllt durch Vertragstabellen, vier maschinenprüfbare Schemaentwürfe, sieben gültige und drei bewusst ungültige Beispiele sowie das Entscheidungsprotokoll. Namensgebung aus §5.1 bleibt der eigene Ausgangspunkt.

**Abnahme:** Für jedes Feld ist klar, wer es speichert, prüft, überträgt und auswertet. Kein Beispiel verwendet einen nicht spezifizierten Parameter. Offene Geschäftsentscheidungen sind benannt und blockieren nur die davon betroffenen Implementierungen.

## Block 02 – Repository, Entwicklungsumgebung und Plugin-Grundgerüst

**Ziel:** Eine reproduzierbare Grundlage für Entwicklung und Prüfung. Bezug: §§5.1, 10.1–10.2, 12.6, 18.5.

**Status:** Abgeschlossen am 11. September 2026. Das aktivierbare Grundgerüst, die Entwicklungsbefehle, Lockfiles, Qualitätsprüfungen und CI liegen im Repository. Aufbau und Prüfnachweise stehen in [`docs/block-02/IMPLEMENTATION.md`](docs/block-02/IMPLEMENTATION.md).

**Arbeitspakete:**

- Das bereits angebundene GitHub-Repository als Entwicklungsquelle verwenden; überschaubare Änderungen und dokumentierte Prüfungen pro Block vorsehen.
- Eine eigene, schlanke Dateiorganisation aus Verantwortlichkeiten ableiten. Die Zielstruktur in §10.1 ist ein eigener Architekturvorschlag, kein Grund, frühzeitig jede denkbare Klasse anzulegen.
- Plugin-Hauptdatei, Autoloading, Versionsquelle, Header, Textdomain und Mindestversionsprüfung anlegen.
- Aktivierungs- und Deaktivierungshooks registrieren; die Aktivierung erzeugt weder externe Requests noch Beispieldaten.
- Lokale WordPress-Testinstanz, kontrollierte Backend-Testumgebung und fiktive Turnierdaten einrichten; keine Produktionsdaten in Fixtures übernehmen.
- PHP- und JavaScript-Build, Lockfiles, Coding Standards, statische Analyse und Basis-CI aufsetzen. WordPress-eigene Bibliotheken über deren registrierte Abhängigkeiten verwenden.

**Lieferung:** Installierbares Grundgerüst, Entwicklungsanleitung, laufende Basis-CI und dokumentierte Trennung zwischen Plugin- und Website-Änderungen. Die vollständige Website wird nicht ins Plugin-Repository kopiert.

**Abnahme:** Installation und Aktivierung auf einer frischen Testinstanz funktionieren; unzureichende Systemversionen führen zu verständlicher Rückmeldung. Ein frischer Checkout lässt sich nach Anleitung bauen. Abhängigkeiten und Lizenzen sind nachvollziehbar.

## Block 03 – Öffentliche Referenzen, Freigaben und Datenadapter

**Ziel:** Nur ausdrücklich freigegebene Daten gelangen in die neue Embed-Schicht. Bezug: §§4, 9.1, 9.6, 12, 21.

**Status:** Abgeschlossen am 12. September 2026. Die Website besitzt eine getrennte Embed-API-Grundlage, stabile öffentliche Referenzen, die zentrale Freigabepolitik und einen positiv listenden Datenadapter. Umfang, Projektgrenze und Prüfnachweise stehen in [`docs/block-03/IMPLEMENTATION.md`](docs/block-03/IMPLEMENTATION.md). Der öffentliche Metadaten-Endpunkt folgt in Block 4.

**Arbeitspakete:**

- Einen schlanken Backend-Einstieg ohne Login-Automatik, Sitzungsstart, allgemeine Seitennavigation und Seitenaufruf-Tracking schaffen.
- Stabile öffentliche Kennungen einführen beziehungsweise vorhandene stabile IDs fachlich freigeben. Namen und Reihenfolgen dürfen gespeicherte Einbettungen nicht verändern.
- Gemeinsame Freigabeprüfung für Metadaten, Frame, Live-Daten und verknüpfte Turniere implementieren; keine Rückschlüsse auf verborgene Turniere durch unterschiedliche Fehlermeldungen.
- Bestehende Ergebnisberechnung intern über einen Adapter nutzbar machen; globale Sprache und Requestparameter in kontrollierte Eingaben überführen.
- Nur freigegebene Felder ausgeben. Verwaltungs-IDs, interne Hashes, Kontaktdaten und private Notizen gehören nicht in die öffentliche Antwort.
- Cachetreffer und konditionale Antworten in das Freigabekonzept einbeziehen. Für bereits öffentlich gecachte Daten eine begrenzte, dokumentierte Nachlaufzeit beziehungsweise notwendige Cachebereinigung festlegen.

**Lieferung:** Referenzauflösung, Freigabepolitik, öffentlicher Datenadapter und eigene Testdaten für alle Statusfälle.

**Abnahme:** Umbenennen und Umsortieren erhält Referenzen. Verborgene oder gelöschte Turniere werden auch bei warmem Cache nicht neu vom Ursprung ausgeliefert. Öffentliche Felder sind positiv aufgelistet. Bestehende Ergebnisfunktionen werden durch gezielte Regressionstests abgesichert.

## Block 04 – Metadaten-API mit Fehlern und Cache

**Ziel:** Der Editor erhält einen kleinen, stabilen Konfigurationsvertrag. Bezug: §§9.1–9.2, 9.5–9.6, 15.

**Status:** Abgeschlossen am 12. September 2026. Der versionierte Metadaten-Endpunkt, die HTTP-Fehlerabbildung, ETag und Cache-Header, Größen- und Abruflimits sowie die Vertragsprüfungen sind im getrennten Website-Projekt umgesetzt. Umfang und Prüfnachweise stehen in [`docs/block-04/IMPLEMENTATION.md`](docs/block-04/IMPLEMENTATION.md). Die Produktionsmigration und ein Test gegen einen eigens freigegebenen Datensatz bleiben Teil der kontrollierten Inbetriebnahme.

**Arbeitspakete:**

- `GET /api/embed/v1/tournaments/{public-ref}/metadata` implementieren.
- Titel, öffentliche URL, Zustand, Aktualisierungszeit, Turnierzeitzone, Sprachliste, Gruppen und stabile Teilnehmerkennungen liefern.
- Verfügbare Ansichten, Filter und Informationsschalter je Wertungsart melden; keine Tabellenfunktion behaupten, wenn der Modus keine Tabelle besitzt.
- Fehlercodes für ungültige Referenzen, nicht verfügbare Ansichten, fehlende öffentliche Turniere, Limits und Dienstausfall festlegen und umsetzen.
- Antwortgröße begrenzen, ETag/304 und Cache-Control passend zur Freigabepolitik behandeln; Sprachvarianten korrekt trennen.
- Regeln für Rate Limits, `Retry-After`, zeitweilige Störungen und Cache-Invalidierung dokumentieren.

**Lieferung:** Dokumentierter V1-Endpunkt mit Contract-Tests und reproduzierbaren Erfolgs-, Leer- und Fehlerfällen.

**Abnahme:** Antworten erfüllen das Schema und enthalten keine internen Felder. Ein Gruppen-, Teilnehmer- oder Sprachwechsel liefert nachvollziehbar passende Daten. Freigabeentzug wird vor einem 304 oder Cachetreffer geprüft. Unbekannte Parameter folgen einer festgelegten Regel.

## Block 05 – Embed-Grundlayout und Turniertabelle

**Ziel:** Die Tabellenansicht funktioniert eigenständig in einem einfachen Test-Iframe. Bezug: §§7.1, 8.2, 9.3, 12.5, 14.

**Status:** Abgeschlossen am 12. September 2026. Die getrennte Website enthält die eigenständige Tabellenroute, lokale Layout- und Akzent-Assets, Gruppenwahl, wertungsabhängige Spalten, Abschlussplatzierungen, zugängliche Leer- und Fehlerzustände sowie die vereinbarten Cache- und Einbettungsheader. Umfang, Browserprüfung und Testnachweise stehen in [`docs/block-05/IMPLEMENTATION.md`](docs/block-05/IMPLEMENTATION.md). Migration, Deployment und Produktionstest bleiben Teil der kontrollierten Inbetriebnahme.

**Arbeitspakete:**

- `GET /embed/v1/tournaments/{public-ref}` mit der Ansicht `standings` erstellen.
- Eigene Gestaltung mit heller, dunkler und automatischer Farbvariante, zwei Dichten, Akzentfarbe und optionalen Logos entwickeln.
- Gruppenwahl, Tabellenpunkte, Spiele, Siege/Remis/Niederlagen und Ergebnisbilanzen entsprechend den Fähigkeiten darstellen.
- Tore, Sätze und weitere vorhandene Wertungsarten sowie Abschlussplatzierungen fachlich korrekt abbilden.
- Lange Namen, fehlende Logos, leere Tabellen und noch nicht verfügbare Ranglisten behandeln.
- Lokale versionierte Assets, zugängliche Tabellenstruktur, CSP und getestete Einbettungsheader bereitstellen. Die Frame-Routen dürfen nicht durch widersprüchliche globale Frame-Verbote blockiert werden.

**Lieferung:** Vollständige Tabellenansicht mit eigenen Demodaten, Varianten und Fehlerseiten.

**Abnahme:** Nutzbar ab 320 Pixel Breite, auch per Tastatur. Horizontales Scrollen bleibt im Tabellenbereich. Der Frame lädt keine allgemeinen CDN-Bibliotheken, setzt keine Cookies, verwendet weder Local Storage noch Tracking und erzeugt keine neuen Sitzungen. Bereits vorhandene Dienst-Cookies werden zusätzlich in Block 14 geprüft. Unpassende Spaltenoptionen führen zu festgelegtem Verhalten.

## Block 06 – Spielplan, Filter und Live-Aktualisierung

**Ziel:** Spiele und Ergebnisse werden vollständig, fachlich korrekt und sparsam aktualisiert. Bezug: §§6.2/6.4, 8.3, 9.3, 15.

**Arbeitspakete:**

- Ansicht `matches` in derselben Frame-Infrastruktur ergänzen.
- Gruppen-, Teilnehmer-, Spielnummern- und nach Block 1 festgelegte Datumsfilter kombinieren; Filterwechsel und ungültig gewordene Kennungen behandeln.
- Spielnummer, Datum, Uhrzeit, Platz, Gruppe, Runde, Schiedsrichter, Live-Zustand, Verlängerung und Entscheidungsdetails darstellen, soweit vorhanden.
- Gruppenmodus, Liga, direktes K.-o. und Schweizer System sowie eintägige und mehrtägige Turniere prüfen.
- Browserseitige Live-Aktualisierung für Tabellen und Spiele ausschließlich über einen dokumentierten V1-Vertrag bereitstellen: entweder konditionales Nachladen des Frames oder einen eigenen Datenendpunkt. Kein direkter Browserzugriff auf das interne `scores.php`.
- Polling passend zum bestehenden 30-Sekunden-Cache beginnen; `document.hidden`, abgeschlossene Turniere, Wiederaufnahme, ETags, 429 und Störungen mit Backoff berücksichtigen.

**Lieferung:** Spieleansicht mit vollständiger Filterabbildung und dokumentiertem Live-Verhalten.

**Abnahme:** Filtergrenzen liefern erwartete Spiele; ausgeschiedene oder umbenannte Teilnehmer ändern ihre Referenz nicht. Keine unnötigen Abrufe in verborgenen Tabs. Ein Ausfall beendet nicht die übrige WordPress-Seite; der angezeigte Aktualitätszustand bleibt verständlich.

## Block 07 – Browserprotokoll und bestehendes Event-Widget

**Ziel:** Mehrere Iframes kommunizieren sicher und verlässlich mit der einbettenden Seite. Bezug: §§9.4, 10.6/10.9, 12.5, 19.4–19.7, 20 Etappe A.

**Status:** Abgeschlossen am 20. September 2026. Sender, Empfänger, Größen- und Statuskommunikation sowie die kontrollierte Migration des vorhandenen Event-Widgets sind umgesetzt und getestet. Umfang, Übergangsstrategie und Prüfnachweise stehen in [`docs/block-07/IMPLEMENTATION.md`](docs/block-07/IMPLEMENTATION.md).

**Arbeitspakete:**

- Versionierte `resize`-, Bereitschafts- und Statusmeldungen mit eindeutiger Instanzkennung definieren und auf beiden Seiten implementieren.
- Exakte Origin, `event.source`, Typ, Version, Instanz und Werte prüfen. Eine aus Konfiguration bekannte Eltern-Origin bei der Rückantwort verwenden; sie ist keine Zugriffsberechtigung.
- Höhenrelationen und Obergrenzen anwenden; für sehr lange Inhalte erreichbare Scrollbereiche vorsehen. Keine feste Pixelbreite aus Nachrichten übernehmen.
- Größenmessung entprellen, Layoutschleifen verhindern und Listener beim Entfernen der Einbettung aufräumen.
- Lazy Loading und Bereitschaftsfrist abstimmen. Eine fehlende Antwort darf als Ladeproblem angezeigt werden, ohne einen nicht belegten HTTP-Fehler zu behaupten.
- Das bestehende Event-Widget mit kontrollierter Übergangsstrategie auf die sichere Kommunikation umstellen; bestehende Einbettungen und alte Nachrichtentypen gezielt prüfen. Freigabe- und Asset-Anpassungen aus Block 3 mitführen.

**Lieferung:** Dokumentiertes Browserprotokoll, eigene Sender-/Empfängerimplementierung und geprüfte Event-Widget-Migration.

**Abnahme:** Gefälschte Origin, fremdes Fenster, falsche Instanz, NaN und übergroße Werte verändern keinen fremden Frame. Mehrere Turniere funktionieren nebeneinander. Entfernen, Wiedereinfügen und überlange Inhalte erzeugen weder Listener-Lecks noch unzugängliche Inhalte.

## Block 08 – Zentrales Konfigurationsmodell und URL-Bau

**Ziel:** Alle WordPress-Eingabewege erzeugen dieselbe gültige Konfiguration. Bezug: §§5.2, 8, 10.4–10.6, 12.1–12.3.

**Status:** Abgeschlossen am 20. September 2026. Das gemeinsame V1-Modell, die getrennte Preset-Auswahl, Referenznormalisierung, Shortcode-Abbildung, lokale Metadatenhinweise sowie deterministische Service-URLs sind in PHP und für den späteren Editor in JavaScript umgesetzt. Umfang und Prüfnachweise stehen in [`docs/block-08/IMPLEMENTATION.md`](docs/block-08/IMPLEMENTATION.md).

**Arbeitspakete:**

- Versioniertes Konfigurationsobjekt für Inline-Einbettung und eine davon getrennte Preset-Referenz implementieren; Mischzustände ablehnen.
- ID, Slug und erlaubte Turnierplan.eu-URLs normalisieren; fremde Hosts, Zugangsdaten in URLs, Steuerzeichen und unbegrenzte Eingaben ablehnen.
- Enums, Booleans, Farben, Datum/Zeitzone, Spielnummern und Höhenrelationen zentral prüfen. WordPress-Slashes nur an den tatsächlichen Eingabegrenzen entfernen, nicht mehrfach auf bereits normalisierten Daten.
- Die Feldzuordnung aus Block 1 für Frame-URLs und Shortcode-Attribute umsetzen; URLs ausschließlich aus der zentralen Dienstkonfiguration bauen.
- Stabile V1-Defaults von Einrichtungsstandards trennen: globale Einstellungen vorbelegen neue Konfigurationen, dürfen alte kompakte Shortcodes nicht still verändern.
- Ungültige fachliche Filter lokal kennzeichnen; die aktuelle Zuordnung prüft abschließend der Frame. Keine zusätzliche Frontend-Remoteabfrage einführen.

**Lieferung:** Gemeinsamer Validator, normalisierte Konfiguration, deterministischer URL-Bau und dokumentierte Schema-Migration.

**Abnahme:** Gleichwertige Block-, Preset- und Shortcodewerte erzeugen gleiche Ausgabeparameter. Ausgelassen, false und auto bleiben unterscheidbar. Manipulierte Werte erreichen weder HTML/CSS noch beliebige Remoteziele.

## Block 09 – Einstellungen, Dienstfreigabe und Metadaten-Proxy

**Ziel:** Transparente Einrichtung und sichere Kommunikation im WordPress-Backend. Bezug: §§6.1, 10.7–10.8, 11.1, 12.3–12.4, 13.

**Arbeitspakete:**

- Einstellungsseite mit festem Dienst, Standardwerten, Freigabestatus, Verbindungstest und späterer Löschoption erstellen.
- Den in Block 1 festgelegten Ablauf umsetzen: keine Netzaktivität durch Aktivierung oder leere Beispiele; bewusste Verbindung nach sichtbarem Diensthinweis und erforderlicher Administratorfreigabe.
- Widerruf definieren: neue Abrufe und Frameausgabe sperren, gespeicherte Konfigurationen erhalten. Bereits durch Seiten-Caches ausgeliefertes HTML im Betriebskonzept berücksichtigen.
- WordPress HTTP API mit festem HTTPS-Ziel, Hostprüfung bei jedem Redirect, SSL-Prüfung, begrenzter Antwortgröße und Gesamtzeitbudget verwenden. Das Fünf-Sekunden-Ziel darf sich nicht pro Weiterleitung vervielfachen.
- Geschützte Metadaten- und Refresh-Routen mit Nonce, Capability, Benutzerlimit und gegebenenfalls Preset-Bearbeitungsrecht implementieren.
- Erfolgsantworten etwa fünf Minuten, 404 etwa eine Minute cachen; ETags und bis zu 24 Stunden alte Erfolgskopien nur nach festgelegter Fehlerpolitik nutzen. Ein ausdrückliches „nicht öffentlich“ darf nicht durch alten Erfolg überdeckt werden.

**Lieferung:** Settings, Remote-Client, Cache, REST-Proxy, Diagnose und Datenschutz-Textvorschlag.

**Abnahme:** Fremde Domains und private Netzadressen sind unerreichbar. Gast und unberechtigte Benutzer können keine Proxy-/Refresh-Aktionen auslösen. Abbruch, Timeout, ungültiges JSON, 304, 404, 429 und 503 haben verständliche Zustände. Server- und Browserabrufe sind getrennt dokumentiert.

## Block 10 – Gemeinsamer Renderer und Shortcode

**Ziel:** Eine nutzbare Einbettung für Classic Editor und Page Builder. Bezug: §§6.4, 10.5–10.6, 14–15.

**Arbeitspakete:**

- Einen PHP-Renderer für Block, Shortcode, Preset und Vorschau schaffen.
- Iframe-URL, Attribute und Texte kontextgerecht escapen; Instanzkennung, beschreibenden Titel, Lazy Loading, Referrer Policy und getestete Sandbox setzen.
- Frontend-Assets nur bei tatsächlicher Einbettung laden; auch dynamische Nutzung und mehrere Frames prüfen.
- Einen nutzbaren Link zur vollständigen Turnierseite außerhalb des Iframes und verständliche Lade-/Fehlerzustände bereitstellen.
- `[turnierplan]` mit dokumentierter Attribut-Allowlist implementieren; unbekannte Attribute ignorieren. Der Handler gibt einen String zurück.
- Kompakten Generator mit Referenz, Ansicht und abweichenden Darstellungswerten erstellen; die explizite Ansicht bleibt zur Lesbarkeit erhalten. Ohne Referenz oder gültige Preset-Auswahl keinen verwendbaren Shortcode anbieten.

**Lieferung:** Gemeinsames Rendering, Inline-Shortcode, Generator und Frontend-Verhalten. Preset-Auflösung wird in Block 12 ergänzt.

**Abnahme:** Tabelle und Spiele sind ohne Gutenberg nutzbar. Kein ausgehender WordPress-HTTP-Request entsteht beim normalen Frontend-Rendern, auch nicht bei leerem Metadatencache. Ein vollständiger Dienstausfall lässt Fallback und übrige Seite bedienbar. Kein zusätzliches Werbecredit wird automatisch ausgegeben.

## Block 11 – Gutenberg-Block und direkte Vorschau

**Ziel:** Die erste Einbettung gelingt direkt im Block ohne vorheriges Preset. Bezug: §§3.2, 5.5, 6.2, 10.4, 11.3–11.5.

**Arbeitspakete:**

- Einen dynamischen Block `turnierplan-eu/embed` serverseitig über `block.json` registrieren, mit eigenen Variationen für Tabelle und Spielplan.
- Leeren Zustand mit Referenzfeld, „Turnier verbinden“, Diensthinweis und lokalem Beispiel erstellen.
- Titel, Status, Sprachen und verfügbare Filter über den geschützten Metadatenweg anzeigen; nicht unterstützte Einstellungen ausblenden.
- Häufige Filter direkt zugänglich halten; Darstellung und technische Optionen in verständlichen Panels ordnen.
- Dieselbe serverseitige Normalisierung und URL-Logik für Vorschau und Frontend nutzen; konkurrierende Abrufe abbrechen oder veraltete Antworten verwerfen.
- Desktop-, Tablet- und Mobilvorschau, Duplizieren und Ansichtswechsel ergänzen. Vorschaugrößen ändern keine gespeicherte Frontendbreite.

**Lieferung:** Vollständiger Blockablauf mit Leer-, Lade-, Erfolgs-, Veraltet- und Fehlerzuständen.

**Abnahme:** Auf einer eingerichteten Testinstallation führen höchstens drei notwendige Interaktionen zur ersten echten Vorschau. Eine veröffentlichbare Konfiguration gelingt innerhalb von zwei Minuten. Lokale Einstellungsänderungen erscheinen binnen 500 ms in der Vorschau; Remotevorgänge enden innerhalb des festgelegten Zeitbudgets mit Erfolg oder Rückmeldung. Gruppen-/Ansichtswechsel verlieren keine passenden gemeinsamen Werte.

## Block 12 – Wiederverwendbare Presets und Verwaltungsoberfläche

**Ziel:** Häufig benötigte Einbettungen zentral speichern und kontrolliert wiederverwenden. Bezug: §§5.2, 6.3, 10.3, 11.2, 12.4, 16.4.

**Arbeitspakete:**

- Nicht öffentlich abrufbaren CPT `tpeu_embed` mit registriertem, versioniertem Konfigurations-Meta und kontrollierter REST-Anbindung erstellen.
- Bei Verwendung des Standard-REST-Metawegs `title` und `custom-fields` unterstützen; rohe Metaeingaben müssen nicht als zusätzliche Benutzeroberfläche erscheinen.
- Eigene Berechtigungen für Einstellungen, Erstellen, Bearbeiten fremder Presets, Veröffentlichen, Verwenden und Refresh definieren und den Rollen gezielt zuordnen.
- REST-Listen und Einzelabrufe ausdrücklich schützen. Das öffentliche Rendern eines veröffentlichten Presets ist ein anderer Anwendungsfall als dessen REST-Auflistung.
- Eigenen responsiven Editor mit Konfiguration, Vorschau, Speichern, Duplizieren und kopierbarem Shortcode entwickeln; gemeinsame Controls des Blocks wiederverwenden.
- Preset-Auswahl im Block und `[turnierplan preset="87"]` ergänzen. Wechsel zu Inline kopiert Werte einmalig; fehlende, gelöschte oder unveröffentlichte Presets erhalten einen sicheren Fallback.

**Lieferung:** Verwaltung, zentrale Preset-Auflösung, Blockauswahl und dokumentierte Rollenmatrix.

**Abnahme:** Eine Preset-Änderung gilt an allen Verwendungsstellen nach der üblichen Seitencache-Aktualisierung. Gäste erhalten keine Preset-Liste, Entwürfe oder Benutzerinformationen. Autor, Redakteur und Administrator können nur erlaubte Aktionen ausführen. Ein ungültiges Preset wird nicht durch eine willkürlich andere Einbettung ersetzt.

## Block 13 – Bedienqualität, Barrierefreiheit und Sprachen

**Ziel:** Ein konsistentes, verständliches Produkt auf Deutsch und Englisch. Bezug: §§3.2, 5.5, 11, 14, 17, 24.1.

**Arbeitspakete:**

- Alle Plugin-, Editor- und Frame-Texte vollständig übersetzbar machen und eigene deutsche/englische Fassungen prüfen; Datums-, Zeit- und Zahlenformate berücksichtigen.
- Sprache der WordPress-Oberfläche von der Turnierausgabe trennen; `auto` gemäß Vertrag auflösen.
- Tastaturabläufe, Fokus nach Fehlern, sichtbare Labels, Statusansagen und Kopierbestätigung durchgängig prüfen.
- Kontraste einschließlich benutzergewählter Akzentfarbe, hell/dunkel/auto, reduzierte Bewegung und Zoom prüfen.
- Vorschauen und Frontend bei 320/375, 768 und 1440 Pixeln sowie langen Namen, leeren Daten und fehlenden Logos kontrollieren.
- Fiktive Demo-Turniere und eigene Assets für Nutzungstests und spätere Screenshots vorbereiten.

**Lieferung:** Geprüfte Übersetzungen, konsistente Zustände, behobene Bedienprobleme und dokumentierte Accessibility-Prüfung.

**Abnahme:** Alle Kernaufgaben gelingen mit Tastatur. Status ist ohne Farberkennung verständlich. Die WordPress-Seite erhält durch Einbettungen keine zusätzliche horizontale Überbreite. Vollständige Sprachangaben beziehen sich auf tatsächlich geprüfte Oberflächen.

## Block 14 – Lebenszyklus, Multisite und Gesamtprüfung

**Ziel:** Das zusammengesetzte System erfüllt die Sicherheits- und Betriebsanforderungen. Bezug: §§12–19, 23.

**Arbeitspakete:**

- Aktivierung, Deaktivierung, Wiederaktivierung, Versionsmigration und Deinstallation vervollständigen; Deaktivierung löscht keine Nutzerdaten.
- Löschoption standardmäßig deaktivieren. Bei aktivierter Option ausschließlich eigene Presets, bekannte Optionen, eigene Cacheeinträge, gegebenenfalls Rollenrechte und Cron-Ereignisse entfernen.
- Multisite ausdrücklich abdecken: sitebezogene Einstellungen, Netzaktivierung, später angelegte Sites, Cachetrennung und vorher festgelegte Löschregeln pro Site.
- PHP-/JS-Unit-, Contract-, WordPress-Integrations- und E2E-Tests aus den Anforderungen zusammenführen; WPCS, PHPStan, Build-/Lintprüfungen und Plugin Check ausführen.
- Angriffe auf URL-Normalisierung, Redirects, Remote-Titel, Shortcodes, REST, Preset-Zugriffe und Browsernachrichten prüfen.
- Tatsächliche Netzwerkaufrufe in Editor und Frontend messen: frischer Browser, bestehende Turnierplan-Anmeldung, blockierte Drittanbieter-Cookies, mehrere Frames und widerrufene Dienstfreigabe. Dokumentierte Aussagen über Cookies und Tracking müssen dem Ergebnis entsprechen.
- Als eigenes Abnahmetor prüfen: keine durch den Embed gesetzten oder genutzten Cookies, kein Local Storage und kein Tracking. Falls Browser vorhandene Dienst-Cookies mitsenden, reicht eine geänderte Beschreibung nicht aus: technische Lösung oder ausdrücklich dokumentierte Änderung des Vertrags aus Block 1 erforderlich. Bis dahin ist dieses Kriterium nicht bestanden.
- Mindest-/aktuelle unterstützte Versionen, Block- und klassische Themes, Classic Editor, Turniermodi und vollständige Ausfälle anhand der Matrix prüfen.

**Lieferung:** Abnahmeprotokoll, behobene relevante Fehler, geprüfter Lösch-/Migrationsweg und belastbare Kompatibilitätsmatrix.

**Abnahme:** Alle 18 Kriterien aus Spezifikation §19 sind entweder nachgewiesen oder aufgrund einer in Block 1 dokumentierten Präzisierung mit einem gleichwertigen Prüfziel konkretisiert. Keine offenen sicherheits- oder verzeichnisrelevanten Befunde. Restore, Upgrade und Multisite-Verhalten sind nachvollziehbar geprüft.

## Block 15 – Dokumentation, Release und WordPress.org

**Ziel:** Ein vollständiges Plugin-Paket bauen und den vorgesehenen Veröffentlichungsweg vorbereiten. Bezug: §§3.3, 12.6, 18.5, 20 Etappe E, 23–24.

**Arbeitspakete:**

- `readme.txt`, Installation, FAQ, Shortcode-/Blockanleitung, externe Dienste, Datenschutztext, Supportweg und Changelog fertigstellen.
- Lizenzangaben in vorhandener `LICENSE`, Plugin-Header, Readme und Abhängigkeiten konsistent machen. Für neue Dateien GPLv2-or-later ausdrücklich festlegen, sofern diese Projektentscheidung übernommen wird.
- Lesbare Quellen und Build-Anleitung zugänglich machen; Releasequellen einem konkreten Tag zuordnen.
- Aus einem markierten Commit eine reproduzierbare ZIP mit genau einem Plugin-Verzeichnis erzeugen; benötigte Builddateien einschließen und Entwicklungsdaten, Secrets, Testausgaben sowie Referenzmaterial ausschließen.
- Header-Version, Stable Tag, Changelog, Blockversion und ZIP-Version automatisch abgleichen. `schemaVersion` und die WordPress-Block-API-Version sind eigenständige Verträge und werden nicht blind auf die Plugin-Version gesetzt.
- Eigene Screenshots, Banner, Bildunterschriften und verständliche Beschreibung erstellen; Links und Readme validieren.
- Frische Installation aus genau dieser ZIP prüfen; WordPress.org-Einreichung, Konto mit 2FA, Reviewkorrekturen und anschließende SVN-Veröffentlichung vorbereiten. GitHub bleibt der Entwicklungsort.
- Nach einer tatsächlichen Veröffentlichung öffentliche Plugin-Seite, Download, Versionen und Installation erneut kontrollieren. Die vorliegende Planungsaufgabe führt keine Einreichung oder Veröffentlichung aus.

**Lieferung:** Geprüfte Release-ZIP, Quellen-/Buildnachweis, vollständige Verzeichnisunterlagen und dokumentierter Veröffentlichungsablauf.

**Abnahme:** Das ZIP ist auf einer frischen WordPress-Instanz installierbar und besteht die relevanten Prüfungen. Es enthält alle benötigten Laufzeitdateien. Eine externe WordPress.org-Zulassung wird erst als erfolgt vermerkt, wenn sie tatsächlich vorliegt.

## WordPress.org-Vorgaben im Arbeitsplan

Die folgenden Regeln werden über die genannten Blöcke umgesetzt; die Einreichung wird vor dem Release erneut gegen die dann geltenden Vorgaben geprüft.

| Vorgabe | Umsetzung |
| --- | --- |
| GPL-kompatible Lizenz und zugängliche lesbare Quellen | Blöcke 2/15: Lizenz, Abhängigkeiten, Build-Anleitung und Tagzuordnung. |
| Keine künstlichen Sperren eingebauter Plugin-Funktionen | Blöcke 1/9: Etwaige Tarife betreffen echte Dienstleistungen. |
| Externe Dienste transparent dokumentieren | Blöcke 1/9/15: Zweck, Abrufzeitpunkt, Daten und Bedingungen. |
| Plugin-eigene Credits nur nach Opt-in | Blöcke 1/10: Kein automatisch eingebauter Werbecredit. Serverseitiges Service-Branding ist gesondert zulässig. |
| WordPress-eigene Bibliotheken verwenden | Block 2: registrierte WordPress-Abhängigkeiten. |
| Vollständiges Plugin zur Einreichung | Block 15: geprüftes ZIP und wahrheitsgemäße Metadaten. |

Diese Zuordnung folgt den [detaillierten Plugin-Richtlinien](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). `showBranding=true` ist deshalb nicht pauschal regelwidrig: Entscheidend ist, ob der Dienst sein eigenes Ergebnis gestaltet oder das Plugin zusätzliche Credits ausgibt.

Für die WordPress.org-Ausgabe wird §12.6 konkretisiert: kein externer eigener Updater; Updates erfolgen über WordPress.org. Der Dienst darf Inhalte liefern, aber keine WordPress-Plugindateien als ausführbare Updates nachladen. [Offizielle Hinweise zu Update-Checkern](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker).

GitHub-Anbindung allein veröffentlicht das Plugin nicht im WordPress-Verzeichnis. Nach der Prüfung erfolgt dessen Auslieferung über das zugewiesene SVN-Repository. [Offizieller Einreichungsablauf](https://wordpress.org/plugins/developers/).

Readme-Felder, Stable Tag und Beschreibung werden mit der [Readme-Dokumentation](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/) abgeglichen. Plugin Check und Konto-2FA gehören zur Einreichungsvorbereitung. [Offizielle Einreichungsanforderungen](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/).

Die technische Dienstfreigabe ist keine pauschale rechtliche Bewertung einer Besuchereinwilligung. Der Datenschutztext beschreibt die tatsächlich geprüften Verbindungen: Metadaten vom WordPress-Server und Frameinhalte vom Browser. Auch der eigene Dienst muss in den Plugin-Unterlagen erläutert werden. [Externe Dienste dokumentieren](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#undocumented-3rd-party).

## Arbeitsentscheidungen aus Block 1

Das [Entscheidungsprotokoll](docs/block-01/DECISIONS.md) enthält Begründungen und Auswirkungen. Die geschäftlichen Festlegungen lassen sich vor Block 3 kontrolliert ändern; eine Änderung aktualisiert Vertrag, Schema, Beispiele und spätere Tests gemeinsam.

| Entscheidung | Festlegung für Version 1.0 |
| --- | --- |
| Wer darf einbetten? | Alle ausdrücklich öffentlichen Turniere ohne WordPress-Kontoverknüpfung oder gesonderten Einbettungstarif. |
| Welche öffentliche Kennung gilt langfristig? | Zufällige dauerhafte Referenzen für Turnier, Gruppe und Teilnehmer; Bestands-IDs und Slugs nur als Eingabe-Aliase. |
| Bleiben beendete Turniere sichtbar? | Das sportliche Ende stoppt Polling; die Einbettung bleibt bis zum Widerruf der Veröffentlichung sichtbar. |
| Welche Teilnehmerdaten sind öffentlich? | Nur ausdrücklich zur öffentlichen Turnierdarstellung bestimmte Namen und Logos; keine Kontakt- oder Verwaltungsinformationen. |
| Branding und Tarife? | Branding entsteht im Service-Frame; seine Ausblendung darf tarifabhängig sein und wird pro Turnier als `required`, `optional` oder `hidden` gemeldet. |
| Wie erfolgt die Dienstfreigabe? | Administrator gibt den Dienst pro Site frei; Redakteure verbinden danach bewusst ein Turnier. Widerruf stoppt neue Einbettungen und Abrufe. |
| Datumsfilter bereits in 1.0? | Ja; inklusive Grenzen und Auswertung in der IANA-Turnierzeitzone. |
| Mindestversionen? | WordPress 6.5 und PHP 8.3 als Ausgangsbasis, vor Release durch die echte Testmatrix bestätigt. |
| Weitere Sprachen und Domain-Allowlist? | Deutsche und englische Plugin-Oberfläche; weitere gemeldete Frame-Sprachen möglich. Kontobezogene Domain-Allowlist in Phase 2. |

## Meilensteine und Fortschrittsführung

- **Vertrag geklärt:** Block 1 abgeschlossen; eigene Datenbeispiele und Entscheidungsprotokoll vorhanden.
- **Dienst einbettbar:** Blöcke 3–7 abgeschlossen; Tabellen und Spiele funktionieren im einfachen Test-Iframe.
- **WordPress nutzbar:** Blöcke 2 und 8–11 abgeschlossen; direkte Einbettung per Block und Shortcode funktioniert.
- **Version 1.0 funktionsvollständig:** Blöcke 12/13 abgeschlossen; Presets und durchgängige Bedienqualität vorhanden.
- **Release geprüft:** Blöcke 14/15 abgeschlossen; Abnahmeprotokoll und installierbares Paket vorhanden. Verzeichniszulassung separat verfolgen.

Jeder Block erhält später ein Arbeitspaket mit den Zuständen „offen“, „in Arbeit“, „in Prüfung“ und „abgenommen“. Zur Abnahme gehören konkrete geänderte Dateien, ausgeführte Prüfungen und ein nachprüfbares Ergebnis. Ein Block kann intern in mehrere kleine Änderungen zerlegt werden, ohne die 15 fachlichen Blöcke aufzubrechen. Kalendertermine werden erst nach Backend-Vertrag und festgelegter Testumgebung geschätzt.

## Abdeckung der ursprünglichen Spezifikation

| Abschnitte der WORDPRESS.md | Abdeckung |
| --- | --- |
| 1–5 Ziele, Eigenständigkeit, Bestand und Produktentscheidungen | Analyse sowie Blöcke 1–3 |
| 6–8 Abläufe, Umfang und Konfiguration | Blöcke 1, 5–6, 8–13; Phase-2-Liste unten |
| 9 Schnittstellen | Blöcke 1, 3–7 |
| 10 Architektur | Blöcke 2, 8–12 |
| 11 Administration und Vorschauen | Blöcke 9, 11–13 |
| 12 Sicherheit | Alle Implementierungsblöcke, zusammenhängende Prüfung in 14 |
| 13 Datenschutz | Blöcke 1, 3, 9, 14–15 |
| 14–15 Zugänglichkeit und Performance | Blöcke 4–7, 9–11, 13–14 |
| 16 Lebenszyklus | Blöcke 2, 8, 12, 14 |
| 17–19 Kompatibilität, Tests und Abnahme | Blöcke 1–2, jeweilige Blockabnahme, 13–15 |
| 20 bisherige fünf Etappen | Durch die 15 Blöcke konkretisiert |
| 21 offene Produktfragen | Block 1 und Entscheidungstabelle |
| 22–24 Quellen, Lieferqualität und Präsentation | Blöcke 2, 14–15 und offizielle Quellen |

## Ausbau nach Version 1.0

Diese Punkte aus §7.2 bleiben als eigenes Folge-Backlog erhalten und werden nicht unbemerkt in den ersten Release gezogen:

| Erweiterung | Benötigte Grundlage |
| --- | --- |
| Turnierübersicht und K.-o.-Baum | Neue Ansichten und Fähigkeiten im V1-Vertrag beziehungsweise ausdrücklich versionierte Erweiterung |
| Veranstalter-Eventseite | Vereinheitlichte Freigaben, stabile Eventreferenz und sichere Widget-Kommunikation |
| Klassisches `WP_Widget` | Wiederverwendung von Konfiguration und Renderer |
| Block-Patterns, Favoriten und zuletzt verwendete Turniere | Eigene Muster und begrenzte benutzerbezogene Speicherung |
| Geschützte Turniere mit API-Schlüssel | Eigenes Authentifizierungs-, Secret- und Datenschutzkonzept; keine Secrets in öffentlichen Frame-URLs |
| Kontobezogene Domain-Allowlist | Dienstseitige Autorisierung, Verwaltung und geprüfte Einbettungsheader |
| Experimentelle native HTML-Ausgabe | Öffentlicher Datenvertrag, WordPress-Caching, neue XSS-/Theme-/Accessibility-Abnahme |

Die sichere Überarbeitung des bestehenden Event-Widgets in Block 7 ist Backend-Vorarbeit. Die zusätzliche Eventseiten-Ansicht als WordPress-Produktfunktion folgt erst in diesem Ausbau.
