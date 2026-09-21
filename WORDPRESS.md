# Turnierplan.eu für WordPress – Produktspezifikation

Stand: 12. September 2026

Status: Verbindliche Planungsgrundlage; die Umsetzung läuft entlang der Roadmap.

> **Verbindliche Präzisierung für Version 1.0:** Block 1 der Roadmap ist im [Entscheidungsprotokoll](./docs/block-01/DECISIONS.md) und im [öffentlichen V1-Vertrag](./docs/contracts/v1/README.md) ausgearbeitet. Diese Dokumente schließen unter anderem die offenen Punkte zu Kennungen, Veröffentlichung, Datumsfiltern, Sprache, Branding, Browsernachrichten und Feldzuordnung. Bei einem Widerspruch gilt für die Implementierung der dort dokumentierte V1-Vertrag.

> **Umsetzungsstand:** Die Blöcke 1 bis 9 sind abgeschlossen. Block 2 stellt das aktivierbare Plugin-Grundgerüst und die Basis-CI bereit. Die Blöcke 3 bis 7 liefern im getrennten Website-Projekt die öffentliche Embed-API, Tabellen- und Spielplanansichten sowie das Browserprotokoll. Die Blöcke 8 und 9 ergänzen im WordPress-Plugin das gemeinsame Konfigurationsmodell, die ausdrückliche Dienstfreigabe, den festen Remote-Client, den Metadaten-Cache und geschützte Editor-Routen. Die Nachweise stehen in den jeweiligen Verzeichnissen unter [`docs`](./docs/).

## 1. Ziel

Wir bauen ein eigenständiges WordPress-Plugin, mit dem öffentliche Turniere von Turnierplan.eu in WordPress-Seiten eingebettet werden können. Die erste Version stellt mindestens folgende Ansichten bereit:

- Tabelle beziehungsweise Rangliste
- Spiele, Spielplan und Ergebnisse
- optional als nächste Ausbaustufe: Turnierübersicht, K.-o.-Baum und Veranstalter-Eventseite

Redakteure sollen die Einbettung ohne HTML-Kenntnisse über den Block-Editor konfigurieren können. Für Classic Editor und Page Builder gibt es zusätzlich einen Shortcode. Wiederverwendbare Konfigurationen können als „Einbettungen“ gespeichert werden.

Das Plugin verwaltet keine Turniere und keine Ergebnisse. Turnierplan.eu bleibt die einzige Datenquelle. WordPress speichert nur die Konfiguration der Einbettung.

## 2. Quellenbasis und Projektgrenzen

Die Spezifikation basiert ausschließlich auf:

- den fachlichen Anforderungen von Turnierplan.eu;
- den vorhandenen und neu zu entwickelnden Turnierplan.eu-Schnittstellen;
- den offiziellen WordPress-APIs und der offiziellen WordPress-Dokumentation;
- eigenen Entwürfen für Architektur, Bedienung, Texte, Gestaltung und Tests.

Für die Umsetzung gelten verbindlich folgende Regeln:

1. Das Plugin erhält ausschließlich eigene technische Bezeichner, Produkttexte und Gestaltungselemente.
2. Nicht dokumentierte Schnittstellen Dritter werden weder vorausgesetzt noch angesprochen.
3. Externe Bibliotheken dürfen nur bewusst ausgewählt, lizenzrechtlich geprüft und als Abhängigkeit dokumentiert werden.
4. Abnahmetests werden unmittelbar aus dieser Spezifikation und den eigenen API-Verträgen abgeleitet.
5. Produkttexte und Bedienoberfläche werden für Deutsch und Englisch neu formuliert.
6. In Repository und Release-Paket befinden sich nur eigene Dateien sowie ausdrücklich freigegebene Abhängigkeiten und Assets.

## 3. Produktgrundsätze

### 3.1 Kernanwendungsfälle

Das Plugin soll die folgenden Aufgaben abdecken:

- eine Rangliste oder Tabelle eines öffentlichen Turniers anzeigen;
- Spiele, Spielplan und Ergebnisse anzeigen;
- Einbettungen direkt im Gutenberg-Editor konfigurieren;
- Einbettungen über einen lesbaren Shortcode in Classic Editor und Page Buildern verwenden;
- häufig genutzte Konfigurationen als optionale Presets wiederverwenden;
- von Turnierplan.eu gemeldete Gruppen, Teilnehmer und weitere Filter anbieten;
- die Ausgabe responsiv und vom WordPress-Theme isoliert darstellen.

Die Anforderungen werden von den Nutzungsszenarien der eigenen Plattform und dem Ziel abgeleitet, bereits veröffentlichte Turnierdaten ohne doppelte Datenpflege in WordPress sichtbar zu machen.

### 3.2 Bedienprinzipien

- Für die erste Vorschau genügt eine Turnierreferenz; alle weiteren Angaben sind optional.
- Gültigkeit, Ladezustand und Ergebnis einer Verbindung sind jederzeit eindeutig erkennbar.
- Filter werden nur angeboten, wenn der Turnierplan.eu-Endpunkt die jeweilige Fähigkeit meldet.
- Konfiguration und Vorschau bleiben im Editor gleichzeitig zugänglich.
- Ein generierter Shortcode lässt sich mit einem Klick kopieren und enthält nur vom Standard abweichende Werte.
- Leere, ladende, erfolgreiche und fehlerhafte Zustände besitzen verständliche Texte und zugängliches Statusfeedback.
- Eine erste Einbettung erfordert kein zuvor angelegtes oder veröffentlichtes Preset.
- Häufig benötigte Einstellungen stehen im Vordergrund; technische Detailoptionen werden schrittweise eingeblendet.
- Tabellen- und Spieleansicht nutzen dasselbe Konfigurationsmodell und dieselbe Render-Pipeline.

### 3.3 Release-Konsistenz

- Plugin-Header, `readme.txt`, Stable Tag, Blockversionen, Changelog und Release-ZIP werden aus einer gemeinsamen Versionsquelle erzeugt oder in CI gegeneinander geprüft.
- Dienst-Domains stehen genau einmal in einer zentralen Konfiguration; Dokumentation und Code pflegen keine unabhängigen Kopien dieser Zuordnung.
- Sprach-, Datenschutz- und Kompatibilitätsangaben werden vor jedem Release gegen das tatsächlich ausgelieferte Verhalten geprüft.
- Die öffentliche Plugin-Seite und die Installation des veröffentlichten ZIP-Pakets werden nach jedem Release kontrolliert.

## 4. Ausgangslage bei Turnierplan.eu

Im bestehenden Projekt sind bereits zwei verwertbare Bausteine vorhanden:

- `scores.php?id={ID}` liefert für aktive Turniere eine JSON-Darstellung mit Spielen, Gruppen, Tabellen, Abschlussplatzierungen, Wertungstyp und Schweizer-System-Status. Die Antwort wird aktuell 30 Sekunden gecacht und unterstützt ETags.
- `widgets/event.js` zusammen mit `widgets/event-frame.php` bettet derzeit die freigegebenen Turniere einer Eventseite als Karten ein und meldet die Höhe per `postMessage` an die Elternseite.

Diese Bausteine reichen für ein belastbares WordPress-Plugin noch nicht aus:

- `scores.php` ist eine interne Laufzeitschnittstelle mit kompakten Feldnamen und ohne ausdrücklich versionierten öffentlichen Vertrag.
- Das bestehende Widget zeigt Veranstalter-Turniere, aber keine einzelne Rangliste oder Spieleliste.
- `live.php` ist eine vollständige öffentliche Seite und deshalb zu schwer und zu instabil als Embed-Vertrag.
- Die derzeitige Resize-Kommunikation prüft auf der Empfängerseite die Nachrichtenherkunft noch nicht.
- Eine kompakte API für Turnier-Metadaten, verfügbare Gruppen, Teilnehmer und Ansichten fehlt.

Darum besteht das Vorhaben aus zwei Lieferteilen:

1. stabile Embed- und Metadaten-Endpunkte auf Turnierplan.eu;
2. das eigentliche WordPress-Plugin.

Das Plugin darf nicht direkt von der internen Struktur von `scores.php` abhängig werden. Eine serverseitige Adapterklasse auf Turnierplan.eu darf `scores_build_response()` intern zunächst weiterverwenden, muss nach außen aber das nachfolgend definierte V1-Schema stabil halten.

## 5. Produktentscheidungen

### 5.1 Eigene Identität

Für die Implementierung festgelegte technische Identität:

| Element | Wert |
| --- | --- |
| Anzeigename | Turnierplan.eu – Turniere einbetten |
| Plugin-Slug | `turnierplan-eu` |
| PHP-Namespace | `TurnierplanEU\WordPress` |
| Funktionspräfix, falls nötig | `tpeu_` |
| Textdomain | `turnierplan-eu` |
| Block-Namespace | `turnierplan-eu` |
| Custom Post Type | `tpeu_embed` |
| Option-Präfix | `tpeu_` |
| Transient-Präfix | `tpeu_cache_` |

Der endgültige öffentliche Name ist vor Einreichung markenrechtlich und mit den WordPress.org-Namensregeln abzugleichen.

### 5.2 Ein gemeinsames Modell statt doppelter Inhaltstypen

Es gibt genau einen internen Inhaltstyp „Einbettung“. Das Feld `view` bestimmt die Ansicht:

- `standings`
- `matches`
- später `overview`, `bracket` oder `event-list`

Dadurch werden Validierung, Ausgabe, Caching, Vorschau und Migration nur einmal implementiert. Der Block-Editor zeigt anwenderfreundliche Block-Varianten wie „Turniertabelle“ und „Spielplan“, intern verwenden sie denselben dynamischen Block.

### 5.3 Iframe als MVP-Ausgabemodell

Die öffentliche Ausgabe erfolgt zunächst als Iframe von `https://www.turnierplan.eu`. Vorteile:

- Live-Daten müssen nicht durch den WordPress-Server gespiegelt werden.
- Darstellung und Datenformat können serverseitig gemeinsam weiterentwickelt werden.
- CSS fremder Themes beeinflusst die Turnieransicht nicht.
- WordPress speichert keine Teilnehmer- oder Ergebnisdaten.

Eine spätere native HTML-Ausgabe über die API ist denkbar, aber nicht Teil des MVP. Sie hätte mehr SEO- und Theme-Integrationsvorteile, würde jedoch Caching, Barrierefreiheit, Schema-Kompatibilität und XSS-Abwehr im Plugin erheblich aufwendiger machen.

### 5.4 Kein klassisches Widget im MVP

Der dynamische Block funktioniert bereits in blockbasierten Widget-Bereichen. Ein `WP_Widget` für alte Installationen ist nur Phase 2. Der Shortcode deckt Classic Editor und viele Page Builder ab.

### 5.5 Erfolgskennzahlen für die Bedienung

Die einfache Einrichtung wird messbar gemacht:

- Ein neuer Nutzer erreicht mit Turnierreferenz und einer Ansicht in höchstens zwei Minuten eine veröffentlichbare Einbettung.
- Bis zur ersten Vorschau sind höchstens drei notwendige Interaktionen erforderlich.
- Lokale Einstellungsänderungen erscheinen innerhalb von 500 Millisekunden in der Vorschau; ein neuer Remote-Abruf erhält nach spätestens fünf Sekunden einen Erfolgs- oder Fehlerzustand.
- Der kanonische Shortcode enthält nur Referenz, Ansicht und Werte, die vom Standard abweichen.
- Ein Nutzer kann Tabelle und Spiele desselben Turniers duplizieren, ohne die Turnierreferenz erneut einzugeben.
- Ein ungültiger gespeicherter Filter blockiert die Ausgabe nicht, sondern fällt nachvollziehbar auf „alle“ zurück und zeigt im Editor einen Hinweis.

## 6. Nutzerabläufe

### 6.1 Ersteinrichtung

1. Administrator installiert und aktiviert das Plugin.
2. Unter „Einstellungen → Turnierplan.eu“ wird erklärt, dass öffentliche Inhalte von Turnierplan.eu geladen werden.
3. Der Administrator bestätigt die Nutzung des externen Dienstes und kann optional Standardwerte festlegen.
4. Nach der Freigabe kann der Administrator mit einer selbst eingegebenen Turnierreferenz einen Verbindungstest ausführen.
5. Es werden keine Turnierplan.eu-Zugangsdaten benötigt, solange ausschließlich öffentliche Turniere eingebettet werden.

Die Aktivierung allein startet keinen unaufgeforderten Netzaufruf. Vor der Administratorfreigabe entstehen weder Remote-Abfragen noch Iframes. Danach erfolgt der erste Abruf erst durch „Turnier verbinden“ beziehungsweise den ausdrücklichen Verbindungstest, nicht bereits durch das Einfügen eines leeren Blocks.

### 6.2 Direkte Block-Konfiguration

1. Redakteur fügt „Turniertabelle“ oder „Spielplan“ ein.
2. Er trägt eine Turnier-ID, öffentliche Kennung oder vollständige Turnierplan.eu-URL ein.
3. Das Plugin normalisiert die Eingabe auf eine öffentliche Turnierreferenz.
4. Der Editor lädt Titel, Status und verfügbare Filter.
5. Redakteur wählt Gruppe, Teilnehmer, Zeitraum beziehungsweise Spielnummern und Darstellung.
6. Eine Vorschau erscheint im Editor.
7. Beim Speichern bleiben nur Blockattribute in WordPress erhalten.

Der Block ist der empfohlene und kürzeste Weg. Presets sind eine Erweiterung für wiederholte Nutzung, keine Voraussetzung für die erste Einbettung.

### 6.3 Wiederverwendbare Einbettung

1. Unter „Turnierplan → Einbettungen“ wird eine Konfiguration angelegt.
2. Die Konfiguration erhält einen sprechenden WordPress-Titel, zum Beispiel „Herren – aktuelle Tabelle“.
3. Der Block kann statt eigener Werte diese Einbettung auswählen.
4. Änderungen am Preset gelten auf allen Seiten, die darauf verweisen.

Ein Block verwendet entweder `presetId` oder eigene Inline-Attribute. Mischzustände sind nicht erlaubt. Beim Wechsel auf Inline-Konfiguration können Preset-Werte einmalig kopiert werden.

### 6.4 Shortcode

Der kanonische Shortcode ist lesbar und ohne kryptische Ein-Buchstaben-Parameter:

```text
[turnierplan tournament="12345" view="standings"]
```

Weitere Beispiele:

```text
[turnierplan preset="87"]
[turnierplan tournament="sommer-cup-2026" view="matches" group="grp_01k4f70bcde2fgh3jkm4npq5rs"]
[turnierplan tournament="12345" view="matches" participant="ptc_01k4f71bcde2fgh3jkm4npq5rs" date_from="2026-09-09" date_to="2026-09-10"]
```

Erlaubte Shortcode-Attribute entsprechen einem bewusst begrenzten Teil des Konfigurationsschemas. Unbekannte Attribute werden ignoriert. Der Shortcode darf keine beliebige Remote-URL akzeptieren.

Standardwerte werden nicht in den generierten Shortcode geschrieben. Ein einfacher Tabellen-Shortcode bleibt daher auch nach mehreren Plugin-Updates kurz und lesbar. Der Generator bietet zusätzlich „In die Zwischenablage kopieren“ und ein zugängliches Statusfeedback; er erzeugt niemals automatisch einen Shortcode mit einer noch leeren Turnierreferenz.

## 7. Funktionsumfang

### 7.1 MVP

- dynamischer Gutenberg-Block mit Varianten „Turniertabelle“ und „Spielplan“
- Shortcode `[turnierplan]`
- optional wiederverwendbare Einbettungen über einen nicht öffentlichen Custom Post Type
- Eingabe von numerischer Turnier-ID, öffentlicher Kennung oder Turnierplan.eu-URL
- automatische Ermittlung von Turniertitel, Sprache, Status, Gruppen und Teilnehmern
- Filter nach Gruppe
- Spiele zusätzlich filterbar nach Teilnehmer und Spielnummernbereich
- helle, dunkle und automatische Farbvariante
- kompakte oder komfortable Dichte
- Akzentfarbe innerhalb sicherer Grenzen
- Spalten- und Informationsschalter mit sprechenden Namen
- responsive Breite und automatische Höhe
- Editor- und Adminvorschau
- verständliche Fehler- und Leerzustände
- deutsch- und englischsprachige Plugin-Oberfläche
- externe-Dienste-Hinweis und Textvorschlag für die WordPress-Datenschutzerklärung
- Deinstallationsoption „Daten beim Löschen entfernen“, standardmäßig aus
- Duplizieren einer bestehenden Konfiguration mit Wechsel der Ansicht
- umschaltbare Vorschaugrößen für Desktop, Tablet und Mobilgerät
- kompakter Shortcode-Generator, der Standardwerte auslässt

### 7.2 Phase 2

- Turnierübersicht als dritte Blockvariante
- K.-o.-Baum
- Veranstalter-Eventseite über den bereits vorhandenen Eventpage-Slug
- klassisches Widget für Legacy-Installationen
- Muster/Patterns, zum Beispiel Tabelle und nächste Spiele nebeneinander
- Favoriten beziehungsweise zuletzt verwendete Turniere im Editor
- optionaler API-Schlüssel für nicht öffentliche oder besonders geschützte Einbettungen
- Domain-Allowlist pro Turnier beziehungsweise Konto
- native HTML-Ausgabe als experimenteller Modus

### 7.3 Bewusst nicht im MVP

- Bearbeiten von Ergebnissen aus WordPress
- Synchronisieren oder Kopieren aller Turnierdaten in die WordPress-Datenbank
- WordPress-Benutzer mit Turnierplan.eu-Konten verbinden
- Tracking, Werbung oder Telemetrie
- Laden von JavaScript- oder CSS-Bibliotheken von allgemeinen Drittanbieter-CDNs
- frei eingebbare Iframe- oder API-Domains

## 8. Konfigurationsmodell

Die interne Konfiguration verwendet sprechende, englische Schlüssel, damit PHP, JavaScript und API dieselben Begriffe nutzen können.

### 8.1 Kernfelder

| Feld | Typ | Vorgabe | Regeln |
| --- | --- | --- | --- |
| `schemaVersion` | Integer | `1` | nur unterstützte Versionen |
| `tournamentRef` | String | leer | 1–100 Zeichen; ID, Slug oder serverseitig normalisierte öffentliche Kennung |
| `view` | Enum | `standings` | `standings`, `matches`; später erweiterbar |
| `language` | String | `auto` | `auto` oder vom API-Endpunkt angekündigter Sprachcode |
| `group` | String/null | `null` | ausschließlich Wert aus den Metadaten |
| `participant` | String/null | `null` | ausschließlich öffentliche Teilnehmerkennung aus den Metadaten |
| `matchFrom` | Integer/null | `null` | mindestens 1 |
| `matchTo` | Integer/null | `null` | mindestens `matchFrom` |
| `dateFrom` | String/null | `null` | `YYYY-MM-DD`, inklusive, in der Turnierzeitzone |
| `dateTo` | String/null | `null` | `YYYY-MM-DD`, inklusive und nicht vor `dateFrom` |
| `theme` | Enum | `auto` | `auto`, `light`, `dark` |
| `density` | Enum | `comfortable` | `compact`, `comfortable` |
| `accentColor` | String/null | `null` | gültige 6-stellige Hex-Farbe mit `#` oder `null` |
| `showBranding` | Boolean | `true` | serverseitige Tarifregeln haben Vorrang |
| `openLinksInNewTab` | Boolean | `true` | steuert Linkverhalten im Frame |
| `minHeight` | Integer | `240` | 160–2000 Pixel; nur Fallback |
| `maxHeight` | Integer | `4000` | 300–8000 Pixel |

### 8.2 Tabellenoptionen

| Feld | Vorgabe | Bedeutung |
| --- | --- | --- |
| `showTeamLogos` | `true` | Mannschaftslogos anzeigen, falls verfügbar |
| `showPlayed` | `true` | Anzahl Spiele anzeigen |
| `showWinsDrawsLosses` | `true` | Sieg/Remis/Niederlage-Spalten anzeigen, sofern Wertungsart passend |
| `showScoreBalance` | `true` | Tore, Sätze oder Punktebilanz anzeigen |
| `showPoints` | `true` | Tabellenpunkte anzeigen |
| `enableGroupNavigation` | `true` | Gruppenwechsel innerhalb des Frames erlauben |

Nicht jede Wertungsart besitzt dieselben Spalten. Der Embed-Endpunkt liefert `capabilities` und ignoriert nicht anwendbare Schalter deterministisch.

### 8.3 Spieleoptionen

| Feld | Vorgabe | Bedeutung |
| --- | --- | --- |
| `showMatchNumber` | `true` | Spielnummer anzeigen |
| `showDate` | `auto` | Datum bei mehrtägigen Turnieren automatisch anzeigen |
| `showTime` | `true` | Uhrzeit anzeigen |
| `showField` | `true` | Feld/Platz anzeigen |
| `showGroup` | `true` | Gruppe beziehungsweise Runde anzeigen |
| `showRound` | `true` | Rundenname anzeigen |
| `showReferee` | `true` | Schiedsrichter anzeigen, falls vorhanden |
| `showLiveState` | `true` | Live-Status und laufende Zeit anzeigen |
| `showExtraTime` | `true` | Verlängerung anzeigen, sofern relevant |
| `showPenaltyResult` | `true` | Entscheidungsdetails anzeigen, sofern relevant |

### 8.4 Stilregeln

Das MVP bietet semantische Presets statt einer großen Zahl niedrigstufiger Rahmen- und Padding-Regler. Das sorgt für konsistente mobile Darstellung und eine eigenständige Gestaltung.

Optional können später unter „Erweitert“ kontrollierte CSS-Variablen ergänzt werden. Freies CSS und ungeprüfte Style-Strings sind ausgeschlossen.

## 9. Turnierplan.eu-Schnittstellen

### 9.1 Grundregeln

- Basisdomain ist fest `https://www.turnierplan.eu`.
- Jede Schnittstelle trägt eine explizite Hauptversion im Pfad.
- Nur ausdrücklich öffentlich freigegebene Turniere dürfen ohne Authentifizierung ausgeliefert werden. Der sportliche Zustand `upcoming`, `live`, `completed` oder `cancelled` wird davon getrennt behandelt.
- Alle Antworten sind UTF-8.
- Fehler verwenden passende HTTP-Statuscodes und stabile maschinenlesbare Fehlercodes.
- Antworten unterstützen ETag und sinnvolle `Cache-Control`-Header.
- Unbekannte Query-Parameter werden mit `400` abgewiesen und dürfen niemals direkt in HTML/CSS gelangen.
- API und Frame dürfen weder E-Mail-Adressen noch interne Hashes, Benutzer-IDs, private Notizen oder andere Verwaltungsdaten ausgeben.

### 9.2 Metadaten-Endpunkt

Vertrag:

```text
GET /api/embed/v1/tournaments/{public-ref}/metadata?lang=de
```

Zweck: Block-Editor, Preset-Editor und Verbindungstest. Dieser Endpunkt liefert kleine, für die Konfiguration geeignete Daten und kein vollständiges Turnier.

Das vollständige Zielschema liegt in [`metadata-response.schema.json`](./docs/contracts/v1/schemas/metadata-response.schema.json), ein geprüftes Beispiel in [`metadata-success.json`](./docs/contracts/v1/examples/valid/metadata-success.json). Es enthält kanonische Kennungen, sportlichen Zustand, IANA-Zeitzone, unterstützte Sprachen, Ansichten mit ihren Filtern und Optionen sowie die serverseitige Branding-Richtlinie.

Öffentliche Turnier-, Gruppen- und Teilnehmerkennungen sind dauerhaft stabil und unabhängig von Name, Position und Gruppeneinteilung. Numerische IDs und bestehende Slugs sind ausschließlich Eingabe-Aliase; die API antwortet mit der kanonischen Kennung.

Empfohlene Antwortheader:

```text
Content-Type: application/json; charset=utf-8
Cache-Control: public, max-age=60, stale-while-revalidate=300
ETag: "..."
X-Content-Type-Options: nosniff
```

### 9.3 Frame-Endpunkt

Vertrag:

```text
GET /embed/v1/tournaments/{public-ref}
```

Unterstützte Query-Parameter sind eine explizite Allowlist:

| Parameter | Beispiel | Bedeutung |
| --- | --- | --- |
| `view` | `matches` | Ansicht |
| `lang` | `de` | Sprache |
| `group` | `grp_…` | stabile Gruppenkennung |
| `participant` | `ptc_…` | stabile Teilnehmerkennung |
| `match_from` | `4` | erste Spielnummer |
| `match_to` | `12` | letzte Spielnummer |
| `date_from` | `2026-09-09` | erstes lokales Turnierdatum, inklusive |
| `date_to` | `2026-09-10` | letztes lokales Turnierdatum, inklusive |
| `theme` | `dark` | Farbvariante |
| `density` | `compact` | Darstellungsdichte |
| `accent` | `16a34a` | Akzentfarbe ohne `#` |
| `show` | `time,field,group` | positive Allowlist sichtbarer Elemente |
| `date` | `auto` | Datum automatisch, immer oder nie anzeigen |
| `branding` | `show` | Wunsch; Serverrichtlinie hat Vorrang |
| `links` | `new-tab` | Linkziel |
| `instance` | UUID v4 | Zuordnung der Browsernachricht |
| `parent_origin` | `https://verein.example` | konkretes Nachrichtenziel |

Boolean-Schalter werden möglichst als positive, lesbare Liste übertragen. Das vermeidet schwer verständliche Negativoptionen.

Der Frame:

- verwendet ausschließlich lokale, versionierte Assets von Turnierplan.eu;
- enthält keine globale Seitennavigation, Werbung oder unnötigen Footer;
- setzt keine Cookies und verwendet weder Local Storage noch Tracking;
- ist tastaturbedienbar und besitzt eine sinnvolle Fokusreihenfolge;
- reagiert ab 320 Pixel Breite ohne abgeschnittene Namen;
- aktualisiert Live-Daten sparsam und stoppt Polling in inaktiven Tabs;
- zeigt einen Link zur vollständigen Turnierseite;
- meldet seine Höhe nach Laden und Größenänderungen.

### 9.4 Browserprotokoll

Eigener Nachrichtentyp:

```json
{
  "type": "turnierplan.eu/embed",
  "version": 1,
  "instance": "550e8400-e29b-41d4-a716-446655440000",
  "event": "resize",
  "payload": {
    "height": 684
  }
}
```

Regeln im WordPress-Empfänger:

1. `event.origin` muss exakt `https://www.turnierplan.eu` sein.
2. `event.source` muss dem `contentWindow` des zugehörigen Iframes entsprechen.
3. `type`, `version`, `event` und `instance` müssen passen.
4. Die Nutzlast muss dem Ereignis entsprechen; `height` ist eine endliche Ganzzahl innerhalb 160–8000.
5. Breite wird nicht per Nachricht in feste Pixel umgestellt; der Iframe bleibt `width: 100%`.
6. Pro Iframe wird nur ein Listener verwaltet und beim Entfernen aufgeräumt.

Der Renderer übergibt `parent_origin`; der Frame validiert sie als Origin und verwendet sie als konkretes `postMessage`-Ziel statt `*`. Neben `resize` sind `ready` und `status` verbindlich. Das vollständige Schema steht in [`embed-message.schema.json`](./docs/contracts/v1/schemas/embed-message.schema.json).

### 9.5 Fehlervertrag

Beispiele:

| HTTP | Code | Bedeutung |
| --- | --- | --- |
| 400 | `invalid_reference` | Kennung syntaktisch ungültig |
| 404 | `tournament_not_found` | nicht vorhanden oder nicht öffentlich |
| 409 | `view_unavailable` | Ansicht passt nicht zum Turniermodus |
| 429 | `rate_limited` | Abruflimit erreicht |
| 503 | `temporarily_unavailable` | Dienst vorübergehend nicht verfügbar |

Der Frame liefert bei Fehlern eine kleine, übersetzte HTML-Ansicht. Der Metadaten-Endpunkt liefert JSON. Interne Fehlermeldungen, SQL-Details und Stacktraces verlassen den Server nicht.

### 9.6 Übergang von `scores.php`

1. Neue Adapterfunktion liest die bestehende Ergebnisstruktur serverintern.
2. Adapter übersetzt Kurzfelder in das stabile V1-Schema.
3. Contract-Tests frieren das externe Schema ein.
4. Frame-Endpunkt nutzt ausschließlich den Adapter.
5. WordPress-Plugin kennt `scores.php` nicht.
6. Nach erfolgreichem Betrieb kann die interne Quelle ohne Plugin-Update ersetzt werden.

## 10. WordPress-Architektur

### 10.1 Zielstruktur

```text
turnierplan-eu/
├── turnierplan-eu.php
├── readme.txt
├── uninstall.php
├── composer.json
├── package.json
├── languages/
├── src/
│   ├── Plugin.php
│   ├── Domain/
│   │   ├── EmbedConfig.php
│   │   ├── EmbedView.php
│   │   └── ValidationResult.php
│   ├── Infrastructure/
│   │   ├── TurnierplanClient.php
│   │   ├── MetadataCache.php
│   │   └── UrlBuilder.php
│   ├── WordPress/
│   │   ├── Assets.php
│   │   ├── BlockRegistration.php
│   │   ├── EmbedPostType.php
│   │   ├── SettingsPage.php
│   │   ├── Shortcode.php
│   │   ├── Privacy.php
│   │   └── Uninstall.php
│   └── Presentation/
│       ├── EmbedRenderer.php
│       └── ErrorRenderer.php
├── blocks/
│   └── embed/
│       ├── block.json
│       ├── edit.js
│       ├── editor.scss
│       └── view.js
├── assets/
│   ├── css/
│   └── js/
├── templates/
└── tests/
    ├── Unit/
    ├── Integration/
    └── E2E/
```

Die endgültige Distributions-ZIP enthält Build-Dateien, aber keine unnötigen Entwicklungsartefakte. Minifizierte Dateien dürfen nur zusammen mit nachvollziehbarem Quellcode und Build-Anleitung veröffentlicht werden.

### 10.2 Bootstrap

Die Hauptdatei tut nur Folgendes:

- direkten Aufruf verhindern;
- unveränderliche Pfad- und Versionswerte definieren;
- Autoloader laden;
- Mindestanforderungen prüfen;
- Aktivierungs-/Deaktivierungshooks registrieren;
- `Plugin` booten.

Es gibt keinen Singleton-Zwang. Abhängigkeiten werden im Bootstrap erzeugt und an Konstruktoren übergeben. Geschäftslogik ruft WordPress-Funktionen nur über klar begrenzte Adapter auf, soweit dies ohne unnötige Komplexität möglich ist.

### 10.3 Custom Post Type

`tpeu_embed` ist ein UI-fähiger, aber nicht öffentlich abrufbarer Custom Post Type:

- `public: false`
- `publicly_queryable: false`
- `show_ui: true`
- `show_in_rest: true`
- `supports: ["title"]`
- eigene Capability-Zuordnung oder mindestens `edit_posts`/`publish_posts` korrekt prüfen

Alle Metafelder werden mit `register_post_meta()` inklusive Typ, Default, REST-Schema, Sanitizer und `auth_callback` registriert. Ein einziges JSON-Metafeld ist nur akzeptabel, wenn es strikt gegen ein versioniertes Schema validiert wird. Bevorzugt wird ein strukturiertes Konfigurationsobjekt mit zentraler PHP-Validierung.

### 10.4 Block

Ein dynamischer Block `turnierplan-eu/embed` wird serverseitig aus `block.json` registriert. „Turniertabelle“ und „Spielplan“ sind Variationen desselben Blocks.

Wesentliche Attribute:

```json
{
  "presetId": 0,
  "config": {
    "schemaVersion": 1,
    "tournamentRef": "",
    "view": "standings",
    "language": "auto",
    "theme": "auto"
  }
}
```

Die tatsächliche `block.json` definiert vollständige Typen und Defaults. Die öffentliche Ausgabe entsteht ausschließlich in PHP, damit Validierung und URL-Bau nicht umgangen werden können. Im Editor darf JavaScript eine Vorschau rendern, aber gespeicherte Attribute werden beim Frontend-Render erneut geprüft.

Der Editor verwendet WordPress-Komponenten und die WordPress REST API; kein öffentliches `admin-ajax.php`-Listing von Presets ist nötig. Presets werden nur an Benutzer ausgeliefert, die sie bearbeiten oder verwenden dürfen.

Der leere Block enthält direkt in der Arbeitsfläche:

- ein kombiniertes Feld für ID, Slug oder Turnierplan.eu-URL;
- die primäre Aktion „Turnier verbinden“;
- einen Link „Beispiel ansehen“, der nur lokale Beispieldaten zeigt und keinen Remote-Aufruf startet;
- einen kurzen Hinweis, dass beim Verbinden Daten von Turnierplan.eu abgerufen werden.

Nach erfolgreicher Verbindung zeigt der Block die echte Vorschau. Die häufigsten Einstellungen – Ansicht, Gruppe und Teilnehmer – stehen oberhalb oder in der Block-Werkzeugleiste. Theme, Dichte, sichtbare Informationen und technische Optionen liegen in geordneten Inspector-Panels.

### 10.5 Shortcode

Der Shortcode-Handler:

1. nimmt nur dokumentierte Attribute an;
2. entfernt WordPress-Slashes;
3. normalisiert und validiert mit demselben `EmbedConfig` wie Block und Preset;
4. prüft bei `preset`, ob ein veröffentlichter beziehungsweise berechtigt lesbarer Preset-Datensatz vorliegt;
5. übergibt ausschließlich eine validierte Konfiguration an `EmbedRenderer`;
6. gibt immer einen String zurück und schreibt nicht direkt in den Output.

### 10.6 Renderer

`EmbedRenderer` ist die einzige Stelle, die Iframe-Markup erzeugt. Anforderungen:

- eindeutige UUID je Instanz
- `src` ausschließlich aus festem HTTPS-Ursprung und eigener URL-Builder-Klasse
- URL-Parameter mit `add_query_arg()` oder gleichwertiger sicherer Kodierung
- `title` mit Turnier- und Ansichtsnamen
- `loading="lazy"`
- `width="100%"` und rein responsives CSS
- anfängliche Höhe aus validiertem Fallback
- `referrerpolicy="strict-origin-when-cross-origin"`
- restriktives `sandbox`; voraussichtlich `allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox`
- keine Berechtigungen für Kamera, Mikrofon, Standort oder Zwischenablage
- Fallback-Link zur öffentlichen Turnierseite
- Frontend-Skript nur laden, wenn mindestens ein Embed ausgegeben wird

Die konkrete Sandbox-Liste wird gegen alle Funktionen des Frames getestet und danach nicht unnötig erweitert.

### 10.7 REST-Endpunkte im Plugin

Eigene WordPress-REST-Routen werden nur für Editoraktionen benötigt, zum Beispiel:

```text
GET  /wp-json/turnierplan-eu/v1/metadata/{reference}
POST /wp-json/turnierplan-eu/v1/cache/refresh
```

Sie dienen als kontrollierter Proxy zum Metadaten-Endpunkt, nicht zum vollständigen Live-Datenstrom.

- `permission_callback` prüft mindestens `edit_posts`; Refresh eines Presets zusätzlich die Bearbeitungsberechtigung dieses Posts.
- Referenz, Sprache und alle Filter werden vor dem Remote-Aufruf validiert.
- Die Remote-Basis-URL ist nicht vom Request beeinflussbar.
- Fehler werden in `WP_Error` mit stabilen Codes übersetzt.
- Keine entsprechenden `nopriv`-Routen.

### 10.8 Remote-Client und Cache

Der Client nutzt die WordPress HTTP API und für die feste externe HTTPS-URL bevorzugt `wp_safe_remote_get()`.

Vorgaben:

- Timeout 5 Sekunden im Editor, höchstens 10 Sekunden beim manuellen Verbindungstest
- maximal zwei Redirects
- `Accept: application/json`
- eigener, unkritischer User-Agent mit Plugin-Version
- Statuscode und Content-Type prüfen
- Antwortgröße begrenzen
- JSON-Fehler und unvollständige Schemas ablehnen
- keine Cookies oder WordPress-Anmeldedaten mitsenden
- SSL-Prüfung niemals deaktivieren

Cache-Schlüssel enthalten API-Version, normalisierte Referenz und Sprache, aber keine frei eingegebenen langen Strings. Empfohlene TTL:

- erfolgreiche Metadaten: 5 Minuten
- `404`: 1 Minute
- temporäre Fehler: nicht dauerhaft cachen
- letzte erfolgreiche Antwort zusätzlich bis zu 24 Stunden als Fallback vorhalten

Bei einem Fehler darf die letzte erfolgreiche Antwort mit deutlich sichtbarem Hinweis im Editor weiterverwendet werden. Im Frontend ist kein WordPress-Remote-Abruf nötig, weil der Browser den Frame direkt lädt.

### 10.9 Assets

- Editor-Assets nur laden, wenn der Block-Editor den Block verwenden kann.
- Admin-Assets nur auf den Plugin-Seiten laden.
- Frontend-JavaScript nur laden, wenn der Renderer mindestens ein Embed ausgegeben hat.
- Abhängigkeiten ausschließlich über WordPress-Handles deklarieren.
- Keine globalen Variablen außer einem eindeutig benannten, gekapselten Namespace, falls unvermeidbar.
- Mehrere Einbettungen pro Seite müssen ohne doppelte Listener oder ID-Kollision funktionieren.
- Mutation Observer nur einsetzen, wenn dynamisch nachgeladene Blöcke ihn tatsächlich benötigen; bevorzugt explizite Initialisierung pro Element.

## 11. Adminoberfläche

### 11.1 Einstellungen

Einstellungen → Turnierplan.eu enthält:

- Status der Verbindung
- fest angezeigte Dienst-URL
- Standard-Sprache (`auto` empfohlen)
- Standard-Theme
- Standard-Dichte
- Option „Plugin-Daten beim Löschen entfernen“, standardmäßig aus
- Erklärung zum externen Dienst mit Links zu Datenschutzerklärung und Nutzungsbedingungen
- Diagnoseinformationen ohne personenbezogene Daten oder Secrets

Die Seite akzeptiert keine alternative API-Domain. Entwicklungs- und Staging-URLs werden ausschließlich über eine dokumentierte PHP-Konstante oder einen Filter gesetzt, nicht über frei editierbare Produktionsoptionen.

### 11.2 Preset-Editor

Abschnitte:

1. Turnier: Referenz, erkannter Titel, Status, öffentliche URL
2. Inhalt: Ansicht, Sprache, Gruppe, Teilnehmer, Spielbereich
3. Darstellung: Theme, Dichte, Akzent, anwendbare Informationsschalter
4. Vorschau
5. Verwendung: kopierbarer Shortcode und Hinweis zum Block

Gruppen und Teilnehmer werden erst nach gültiger Turnierreferenz geladen. Ein manueller Aktualisieren-Button umgeht den normalen Cache nur nach Nonce- und Capability-Prüfung.

Der Preset-Editor verwendet eine eigenständige, responsive Zweispaltenansicht statt die klassische WordPress-Publish-Metabox als primäre Interaktion:

```text
┌──────────────────────────────┬──────────────────────────────────┐
│ Turnier und Inhalt           │ Vorschau                         │
│ [ID, Slug oder URL] [Prüfen] │ [Desktop] [Tablet] [Mobil]       │
│ [Ansicht] [Sprache]          │                                  │
│ [Gruppe] [Teilnehmer]        │        eingebetteter Frame       │
│                              │                                  │
│ Darstellung                  │                                  │
│ [Theme] [Dichte] [Akzent]    │                                  │
│ [Weitere Optionen …]         │                                  │
├──────────────────────────────┴──────────────────────────────────┤
│ Verwendung: [kurzer Shortcode] [Kopieren]        [Speichern]    │
└─────────────────────────────────────────────────────────────────┘
```

Ab einer schmalen Adminbreite steht die Vorschau unter den Einstellungen. Auf großen Bildschirmen darf sie beim Scrollen innerhalb des verfügbaren Bereichs haften bleiben. Die Größenumschaltung verändert nur den Vorschau-Viewport und nie die gespeicherte Frontendbreite.

### 11.3 Progressive Offenlegung

Die sichtbare Oberfläche beginnt mit den wenigen Feldern, die für fast alle Nutzer relevant sind:

1. Turnier
2. Ansicht
3. fachliche Filter
4. Theme und Dichte

Alle Spaltenschalter liegen in „Angezeigte Informationen“. Technische Fallback-Höhen und seltene Optionen liegen in „Erweitert“. Nicht anwendbare Optionen werden ausgeblendet und nicht lediglich deaktiviert. Dadurch bleibt die Maske auch dann verständlich, wenn später weitere Ansichten hinzukommen.

### 11.4 Vorschauverhalten

- Vor einer gültigen Referenz erscheint ein eigener, neutraler Leerzustand; es wird kein scheinbar funktionsfähiger Shortcode angeboten.
- Während des Metadatenabrufs bleiben bereits geladene Werte sichtbar und werden als „wird aktualisiert“ gekennzeichnet.
- Gruppen- und Teilnehmerauswahl zeigen den Zeitpunkt der letzten erfolgreichen Aktualisierung.
- Ein manueller Refresh sitzt direkt am betroffenen Feld und besitzt Text beziehungsweise Tooltip, nicht nur ein unbeschriftetes Symbol.
- Sprachwechsel aktualisiert sowohl Frame als auch verfügbare Bezeichnungen, ohne das Preset vorher speichern zu müssen.
- Die Vorschau verwendet dieselbe Renderer- und URL-Builder-Logik wie das Frontend.
- Ein sichtbarer Link öffnet die vollständige öffentliche Turnierseite in einem neuen Tab.
- Sehr lange Spielelisten erhalten im Adminbereich eine begrenzte Vorschauhöhe; das tatsächliche Frontend bleibt automatisch höhenangepasst.

### 11.5 Fehlerzustände

Mindestens folgende Meldungen werden unterschieden:

- Referenz fehlt oder ist ungültig
- Turnier existiert nicht oder ist nicht öffentlich
- Turnier ist beendet, aber weiterhin öffentlich
- gewählte Ansicht ist nicht verfügbar
- Turnierplan.eu ist vorübergehend nicht erreichbar
- WordPress-Server blockiert ausgehende HTTPS-Verbindungen
- gespeicherte Gruppe oder Teilnehmerkennung existiert nicht mehr
- veraltete Konfigurationsversion muss migriert werden

Fehlertexte enthalten eine konkrete nächste Aktion, aber keine internen Serverdetails.

## 12. Sicherheit

### 12.1 WordPress-Eingaben

- Eingaben zuerst mit `wp_unslash()` normalisieren.
- IDs als Integer beziehungsweise streng begrenzte öffentliche Referenz validieren.
- Enums ausschließlich per Allowlist akzeptieren.
- Farben ausschließlich als sechsstelligen Hex-Wert akzeptieren.
- Arrays elementweise validieren und unbekannte Schlüssel entfernen.
- URLs nicht allgemein übernehmen; nur Turnierplan.eu-URLs parsen und anschließend in eine Referenz umwandeln.
- Beim Speichern Nonce und Benutzerberechtigung getrennt prüfen. Nonces ersetzen keine Autorisierung.

### 12.2 Ausgabe

- Attribute mit `esc_attr()`, URLs mit `esc_url()`, Text mit `esc_html()` ausgeben.
- Remote-Titel und Gruppenbezeichnungen sind nicht vertrauenswürdig und werden immer escaped.
- Keine Remote-Antwort wird als unbereinigtes HTML in WordPress eingesetzt.
- JSON für JavaScript über WordPress-Helfer beziehungsweise korrektes JSON-Encoding ausgeben.

### 12.3 SSRF und Remote Requests

- Host, Schema und Pfadbasis sind im Code festgelegt.
- Benutzer beeinflussen nur validierte Pfadsegmente und Allowlist-Query-Parameter.
- Keine URL aus einer Remote-Antwort ungeprüft erneut abrufen.
- Redirectziel muss weiterhin HTTPS und ein erlaubter Turnierplan.eu-Host sein.
- Private IP-Bereiche, Loopback und lokale Hostnamen dürfen nie erreichbar werden.

### 12.4 REST, AJAX und Berechtigungen

- Keine anonymen Endpunkte zum Auflisten lokaler Presets oder WordPress-Beiträge.
- Jede schreibende Aktion braucht Capability und Nonce beziehungsweise REST-Nonce.
- Vorschau- und Refresh-Aktionen erhalten Rate Limits pro Benutzer.
- Fehlermeldungen verraten nicht, ob nicht öffentliche lokale Presets existieren.

### 12.5 Iframe und Browsergrenzen

- `postMessage` auf Origin, Quelle, Instanz und Datentyp prüfen.
- Iframe mit Sandbox und restriktiver Referrer Policy ausgeben.
- Turnierplan.eu setzt eine passende Content Security Policy. Für das öffentliche MVP ist mindestens `frame-ancestors https:` denkbar; bevorzugt wird später eine konfigurierte Domain-Allowlist.
- Keine sensiblen Daten in Frame-URLs, da Query-Strings in Logs und Referrern auftauchen können.
- Frame-Antworten setzen `X-Content-Type-Options: nosniff` und eine enge CSP für Scripts, Styles, Bilder und Verbindungen.

### 12.6 Updates und Lieferkette

- Updates erfolgen über WordPress.org oder einen später klar dokumentierten, sicheren Kanal; das Plugin lädt keinen ausführbaren Code zur Laufzeit nach.
- Alle Laufzeit-JavaScript- und CSS-Dateien des Plugins werden mitgeliefert.
- Composer- und npm-Abhängigkeiten werden minimiert, versioniert und vor Releases geprüft.
- Release-ZIP wird reproduzierbar aus einem markierten Commit gebaut.

## 13. Datenschutz und externe Dienste

Das Plugin muss in `readme.txt` klar erklären:

- dass es öffentliche Turnierinhalte von `https://www.turnierplan.eu` einbettet;
- wann eine Verbindung entsteht: im Editor nach Administratorfreigabe und der bewussten Aktion „Turnier verbinden“ sowie im Frontend beim Laden einer freigegebenen Seite mit Embed;
- welche Parameter übertragen werden: öffentliche Turnierreferenz, Anzeigeoptionen, Sprache, die für Browsernachrichten benötigte WordPress-Origin und technisch übliche HTTP-Daten wie IP-Adresse, User-Agent, Referrer und Zeitpunkt;
- dass das Plugin selbst keine Trackingdaten erhebt und keine Telemetrie sendet;
- dass der Frame gemäß V1-Vertrag keine Cookies oder Browser-Speichermechanismen nutzt;
- wo Datenschutzerklärung und Nutzungsbedingungen von Turnierplan.eu zu finden sind.

Das Plugin ergänzt über `wp_add_privacy_policy_content()` einen sachlichen Textvorschlag für Websitebetreiber. Es behauptet nicht, dass durch die technische Gestaltung automatisch keine Einwilligung erforderlich sei; die rechtliche Bewertung hängt vom Einsatz und der Website ab.

Vor dem Release müssen die tatsächlichen Netzwerkaufrufe mit Browser-Devtools verifiziert und die Dokumentation exakt daran angepasst werden.

## 14. Barrierefreiheit und responsive Darstellung

- Alle Bedienelemente des Editors besitzen sichtbare Labels.
- Statusänderungen der Vorschau werden über eine angemessene Live Region angekündigt.
- Farbe ist nie der einzige Informationsträger.
- Kontrast orientiert sich mindestens an WCAG 2.2 AA.
- Fokusindikatoren bleiben sichtbar.
- Tabellen besitzen korrekte Überschriften und zugängliche Namen.
- Auf kleinen Bildschirmen dürfen Tabellen horizontal scrollen; die Seite selbst darf dadurch nicht breiter werden.
- Lange Team- und Rundennamen werden sinnvoll umgebrochen.
- `prefers-reduced-motion` wird respektiert.
- Der Iframe besitzt einen beschreibenden `title` und einen nutzbaren Fallback-Link.
- Resize darf beim Laden keine unendliche Layout-Schleife auslösen.

## 15. Performance und Verfügbarkeit

- Kein WordPress-Server-Request auf normalen Frontend-Seiten.
- Iframe standardmäßig lazy laden; im Editor darf die Vorschau erst nach gültiger Eingabe entstehen.
- Metadaten werden gecacht und konditionale Requests nutzen ETags.
- Der Frame bündelt kleine lokale Assets und lädt keine allgemeinen CDN-Bibliotheken.
- Live-Polling verwendet zunächst 30 Sekunden als Obergrenze des vorhandenen Daten-Caches; eine kürzere Frequenz bringt ohne Serveränderung keinen Nutzen.
- Bei `document.hidden` wird Polling pausiert oder stark reduziert.
- Mehrere Frames dürfen ihre Abrufe serverseitig über denselben Turniercache bedienen.
- Störungen des externen Dienstes dürfen die übrige WordPress-Seite nicht blockieren.
- Ein fester Fallback verhindert Layoutsprünge, bis die echte Höhe eintrifft.

## 16. Lebenszyklus und Datenhaltung

### 16.1 Aktivierung

- Mindestversionen prüfen.
- CPT registrieren und nur falls erforderlich Rewrite-Regeln einmalig aktualisieren.
- Versionsoption `tpeu_db_version` setzen.
- keine Remote-Anfrage und keine Beispielinhalte erzeugen.

### 16.2 Deaktivierung

- keine Nutzerdaten löschen.
- geplante Cronjobs entfernen, falls spätere Versionen welche einführen.
- Rewrite-Regeln nur bei tatsächlichem Bedarf aktualisieren.

### 16.3 Deinstallation

Standardmäßig bleiben Presets und Einstellungen erhalten. Nur wenn der Administrator vorher „Daten beim Löschen entfernen“ aktiviert hat, entfernt `uninstall.php`:

- Beiträge vom Typ `tpeu_embed` einschließlich zugehöriger Metadaten;
- Optionen mit exakt bekannten Namen;
- Transients mit exakt unserem Präfix;
- gegebenenfalls eigene Cron-Ereignisse.

Keine breiten SQL-LIKE-Löschungen ohne zusätzliche Präfixprüfung. In Multisite wird die Entscheidung ausdrücklich getestet und dokumentiert.

### 16.4 Migrationen

Konfigurationen tragen `schemaVersion`. Migrationen sind idempotent, klein und getestet. Blockattribute werden möglichst beim Rendern kompatibel gelesen; massenhafte Änderungen an `post_content` sind nur letzter Ausweg.

## 17. Kompatibilitätsziel

Festgelegte Ausgangsbasis:

- WordPress 6.5 oder neuer
- PHP 8.3 oder neuer
- aktuelle zwei Hauptversionen von Chrome, Edge, Firefox und Safari; funktionale Basis mit ES2019
- Einzelinstallation und Multisite
- Block-Themes und klassische Themes
- Classic Editor über Shortcode

Vor jeder Veröffentlichung werden „Requires at least“, „Requires PHP“ und „Tested up to“ gegen die tatsächlich geprüfte Matrix gesetzt. Nicht getestete zukünftige WordPress-Versionen werden nicht vorab behauptet.

Da WordPress aktuell `block.json` als kanonische Blockbeschreibung empfiehlt, wird diese verwendet. Wenn die Mindestversion später auf WordPress 6.8 oder neuer steigt, kann die Registrierung mehrerer Blocks über eine Metadaten-Collection erfolgen; für den einzelnen MVP-Block reicht die Registrierung seines `block.json`-Verzeichnisses.

## 18. Tests

### 18.1 PHP-Unit-Tests

- Normalisierung numerischer IDs, Slugs und Turnierplan.eu-URLs
- Ablehnung fremder Hosts, HTTP-URLs, zu langer Werte und Steuerzeichen
- Enum-, Farb-, Bereichs- und Boolean-Validierung
- deterministischer URL-Bau
- Filterkombinationen für Tabelle und Spiele
- Migration alter eigener Schema-Versionen
- Mapping aller erwarteten API-Fehler auf `WP_Error`
- Cache-Key ohne Kollisionen und ohne sensible Inhalte
- Escaping des erzeugten Iframe-Markups

### 18.2 WordPress-Integrationstests

- Aktivierung/Deaktivierung
- CPT- und Meta-Registrierung
- Capability-Grenzen für Administrator, Redakteur, Autor und Gast
- REST-Routen einschließlich Nonce und Fehlercodes
- Shortcode mit Inline-Konfiguration und Preset
- dynamischer Block auf Frontend und im REST-Kontext
- Deinstallation mit aktivierter und deaktivierter Löschoption
- Multisite-Verhalten

### 18.3 JavaScript-Tests

- Editorzustände: leer, lädt, erfolgreich, veraltet, Fehler
- Umschalten der Blockvariante erhält gemeinsame Werte und entfernt unpassende Filter
- Resize akzeptiert nur korrekte Origin, Quelle, Instanz und Höhe
- zwei und mehr Frames auf einer Seite
- Listener werden beim Entfernen eines Frames aufgeräumt
- keine Größenänderung bei gefälschter Nachricht

### 18.4 End-to-End-Matrix

- WordPress-Mindestversion und aktuelle stabile Version
- PHP 8.3 und aktuelle unterstützte PHP-Version
- Block-Theme und klassisches Theme
- Desktop 1440 px, Tablet 768 px, Mobil 320/375 px
- helles und dunkles Theme
- Gruppenmodus, Liga, direktes K.-o.-Turnier und Schweizer System
- Wertung nach Toren sowie mindestens eine alternative Wertungsart
- Turnier ohne Logos, ohne Schiedsrichter und ohne fertige Ergebnisse
- eintägiges und mehrtägiges Turnier
- inaktives, gelöschtes und nicht erreichbares Turnier

### 18.5 Qualitätswerkzeuge

- WordPress Coding Standards über PHP_CodeSniffer
- PHPStan auf sinnvoll hohem Level
- PHPUnit
- `@wordpress/eslint-plugin` für WordPress-JavaScript-Regeln und eine schlanke, auditierbare Build-Kette; weitere `@wordpress/*`-Pakete nur nach tatsächlichem Bedarf
- Playwright für Editor- und Frontend-E2E
- Plugin Check vor jedem WordPress.org-Release
- Accessibility-Prüfung automatisiert plus manuelle Tastaturkontrolle

## 19. Akzeptanzkriterien für Version 1.0

Version 1.0 ist fertig, wenn:

1. Ein Redakteur eine Turnierreferenz in einen Block eingeben und ohne HTML eine Tabelle oder Spieleliste veröffentlichen kann.
2. Gruppen- und Teilnehmerfilter aus dem neuen Metadaten-Endpunkt geladen werden.
3. Derselbe Renderer Block, Shortcode und Preset zuverlässig bedient.
4. Zwei verschiedene Turniere und zwei Ansichten auf derselben Seite korrekt funktionieren.
5. Die Einbettung bei 320 Pixel Breite bedienbar ist und die WordPress-Seite nicht horizontal aufzieht.
6. Resize-Nachrichten fremder Origins oder fremder Frames keine Wirkung haben.
7. Bei Ausfall von Turnierplan.eu die WordPress-Seite normal lädt und ein verständlicher Fallback erscheint.
8. Kein anonymer Plugin-Endpunkt lokale Presets, Entwürfe oder WordPress-Benutzerdaten offenlegt.
9. Netzwerkprüfung bestätigt: nur dokumentierte Anfragen an Turnierplan.eu, keine Cookies und kein Tracking.
10. Alle Texte übersetzbar sind und Deutsch sowie Englisch vollständig vorliegen.
11. Deaktivierung keine Daten löscht und Deinstallation die gewählte Löschoption respektiert.
12. PHP-, JavaScript-, Integrations- und E2E-Tests grün sind.
13. Plugin Check keine ungeklärten sicherheits- oder verzeichnisrelevanten Fehler meldet.
14. Die Release-ZIP ausschließlich freigegebene Projektdateien und dokumentierte Abhängigkeiten enthält.
15. Ein vollständig standardmäßiger Shortcode enthält keine ausgeschriebenen Darstellungsdefaults.
16. Die erste echte Vorschau kann direkt im Block ohne vorher angelegtes oder veröffentlichtes Preset erreicht werden.
17. Desktop-, Tablet- und Mobilvorschau verändern nur den Editor-Viewport und nicht unbeabsichtigt die Frontendausgabe.
18. Plugin-Header, Stable Tag, Changelog, Blockversion und ZIP-Version werden im Releaseprozess automatisch auf Gleichstand geprüft.

## 20. Umsetzungsetappen

### Etappe A – Serververtrag

- Format für die öffentliche Turnierkennung festlegen
- `/api/embed/v1/.../metadata` implementieren
- Adapter vor `scores_build_response()` beziehungsweise der späteren Datenquelle bauen
- `/embed/v1/...` mit Rangliste und Spielen implementieren
- CSP, Cache, ETag, Fehlervertrag und Resize-Protokoll testen
- vorhandenes Event-Widget auf dieselbe sichere Resize-Bibliothek migrieren

Ergebnis: Einbettung funktioniert per handgeschriebenem Test-Iframe, bevor WordPress-Code entsteht.

### Etappe B – Plugin-Grundgerüst

- neue Repository-/Plugin-Struktur
- Bootstrap, Autoloading, Anforderungen, i18n
- Settings, Datenschutztext und Remote-Client
- zentrale Konfigurationsvalidierung und URL-Builder
- PHPUnit-Tests

### Etappe C – Block und Shortcode

- dynamischer Block samt zwei Variationen
- Inspector Controls und Metadatenabfrage
- PHP-Renderer und Resize-Skript
- Shortcode
- Editor- und Frontend-Tests

### Etappe D – Presets

- interner CPT und registrierte Metadaten
- Preset-Editor mit Vorschau
- Auswahl im Block
- Capability- und Migrationstests

### Etappe E – Release

- Übersetzungen und `readme.txt`
- Datenschutz- und externe-Dienste-Dokumentation
- Build-Automation und reproduzierbare ZIP
- Plugin Check, Security Review, Accessibility Review
- Test auf frischer WordPress-Installation und Multisite

## 21. Produktentscheidungen für Version 1.0

Die früher offenen Punkte sind in Block 1 der Roadmap als überprüfbare Arbeitsentscheidungen geschlossen. Maßgeblich ist das vollständige [`DECISIONS.md`](./docs/block-01/DECISIONS.md):

- Einbettungen verwenden dauerhafte zufällige öffentliche Kennungen; numerische IDs und bestehende Slugs bleiben Eingabe-Aliase.
- Veröffentlichung und sportlicher Zustand sind getrennt. Ein abgeschlossenes Turnier bleibt erreichbar, solange es ausdrücklich veröffentlicht ist.
- Alle ausdrücklich öffentlichen Turniere können ohne gesonderten Einbettungstarif angezeigt werden. Eine tarifabhängige Branding-Ausblendung wird vom Dienst am Turnier durchgesetzt. Das Plugin enthält keine eigene Lizenzsperre und fügt keine Werbecredits ein.
- Deutsch und Englisch sind vollständig getestete Plugin-Sprachen. Weitere Frame-Sprachen werden nur angeboten, wenn die Metadaten sie melden.
- Die kontobezogene Domain-Allowlist bleibt Phase 2; `parent_origin` begrenzt bereits in Version 1 nur das Nachrichtenziel.
- Öffentliche Antworten enthalten ausschließlich für die Turnierdarstellung freigegebene Namen und Logos, keine Kontakt- oder Verwaltungsdaten.

## 22. Technische Leitplanken aus der WordPress-Dokumentation

- Block-Metadaten und serverseitige Registrierung: <https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/>
- Dynamische Blockregistrierung: <https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/>
- WordPress HTTP API und Transients: <https://developer.wordpress.org/plugins/http-api/>
- sichere externe GET-Anfragen: <https://developer.wordpress.org/reference/functions/wp_safe_remote_get/>
- Nonces: <https://developer.wordpress.org/plugins/security/nonces/>
- Sanitizing: <https://developer.wordpress.org/apis/security/sanitizing/>
- Datenschutztext für Plugins: <https://developer.wordpress.org/plugins/privacy/suggesting-text-for-the-site-privacy-policy/>
- WordPress.org Plugin-Richtlinien: <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>

## 23. Definition der Lieferqualität

Die Neuentwicklung ist veröffentlichungsbereit, wenn sie:

- konsistente eigene technische Bezeichner verwendet;
- ein gemeinsames Embed-Modell und klar benannte Blockvariationen nutzt;
- nur dokumentierte Turnierplan.eu-V1-Endpunkte anspricht;
- eine semantische, versionierte Konfiguration und zugängliche Gestaltung besitzt;
- nur freigegebene Dateien, Assets, Übersetzungen und Textpassagen enthält;
- durch eigene Tests aus dieser Spezifikation belegt wird;
- als reproduzierbares Release-Artefakt ohne Entwicklungs- oder Zugangsdaten gebaut werden kann.

Das Ergebnis ist eine auf Turnierplan.eu zugeschnittene WordPress-Integration für die Darstellung öffentlicher Turnierdaten.

## 24. Eigene WordPress.org-Präsentation

Die öffentliche Plugin-Seite ist Teil des Produkts und bekommt vor dem ersten Release neu erstellte Inhalte, Demodaten und Abbildungen im eigenen Turnierplan.eu-Erscheinungsbild.

### 24.1 Screenshot-Konzept

Empfohlene eigene Screenshots im einheitlichen 16:9-Format:

1. Block-Inserter und leerer Turnierplan-Block mit dem Feld für ID, Slug oder URL
2. verbundene Turniertabelle mit einfacher Gruppenwahl und Desktop-Vorschau
3. dieselbe Tabelle in der mobilen Vorschau mit sauberem horizontalem Scrollverhalten
4. Spieleliste mit Gruppen-, Teilnehmer- und Spielbereichsfilter
5. wiederverwendbares Preset mit kurzem Shortcode und Kopierbestätigung
6. Einstellungsseite mit Verbindungsstatus und transparenter Erklärung des externen Dienstes

Für die Bilder wird ein eigenes Demo-Turnier mit erfundenen Mannschaftsnamen und selbst erstellten oder eindeutig lizenzierten Logos verwendet. Persönliche Daten, interne IDs, lokale Domains, Debug-Leisten und irrelevante Plugins sind nicht sichtbar. Jeder Screenshot erhält einen präzisen Alternativtext und zeigt genau eine Hauptaussage.

### 24.2 Beschreibung und FAQ

Die eigene Beschreibung folgt dieser Reihenfolge:

1. ein Satz zum Nutzen;
2. drei Schritte bis zur ersten Einbettung;
3. unterstützte Ansichten und Filter;
4. Block-, Shortcode- und Preset-Verwendung;
5. externe Dienste und Datenschutz;
6. technische Anforderungen;
7. Support und bekannte Grenzen.

Die FAQ beantwortet mindestens:

- Wo finde ich die Turnierreferenz?
- Muss ich ein Turnierplan.eu-Konto in WordPress verbinden?
- Wie zeige ich nur eine Gruppe oder eine Mannschaft?
- Wie aktuell sind Ergebnisse?
- Was passiert, wenn Turnierplan.eu nicht erreichbar ist?
- Werden Cookies oder Tracking eingesetzt?
- Kann ich mehrere Turniere auf einer Seite anzeigen?
- Wie entferne ich beim Deinstallieren alle Presets?

### 24.3 Veröffentlichungsprüfung

Vor und nach dem Upload wird automatisiert beziehungsweise manuell geprüft:

- Plugin-Version, Stable Tag und Download-ZIP stimmen überein.
- „Requires at least“, „Requires PHP“ und „Tested up to“ entsprechen der echten Testmatrix.
- Die Liste der Blöcke entspricht den ausgelieferten `block.json`-Dateien.
- Screenshots, Nummerierung, Bildunterschriften und Alternativtexte stimmen überein.
- Alle genannten Endpunkte, Datenschutz- und Nutzungsbedingungslinks funktionieren.
- Behauptete Sprachen sind tatsächlich vollständig verfügbar.
- Beschreibung und Datenschutzhinweise enthalten keine widersprüchlichen Verneinungen oder veralteten Domains.
- Installation aus der öffentlichen ZIP funktioniert auf einer frischen WordPress-Instanz.
