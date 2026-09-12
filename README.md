# Turnierplan.eu für WordPress

Turnierpläne, Tabellen und Ergebnisse von [Turnierplan.eu](https://www.turnierplan.eu/) direkt in WordPress einbetten – ohne Ergebnisse doppelt zu pflegen und ohne eigenes HTML schreiben zu müssen.

> [!IMPORTANT]
> Das WordPress-Plugin besitzt ein aktivierbares und geprüftes Entwicklungsgrundgerüst. Die getrennte Website enthält außerdem die zusätzliche Embed-API samt öffentlichem Metadaten-Endpunkt. Das Embed-Layout und die WordPress-Funktionen folgen in den nächsten Roadmap-Blöcken; ein produktionsreifes Release gibt es noch nicht. Die technische Produktspezifikation liegt in [`WORDPRESS.md`](./WORDPRESS.md).

## Ziel des Projekts

Das Plugin soll Veranstaltern und Vereinen eine einfache, robuste Verbindung zwischen Turnierplan.eu und WordPress bieten. Turniere werden weiterhin zentral auf Turnierplan.eu gepflegt. WordPress speichert lediglich die Konfiguration der Einbettung und zeigt die aktuellen Turnierdaten an.

Geplant sind drei gleichwertige Zugänge:

- ein dynamischer Gutenberg-Block als empfohlener Weg;
- ein kurzer, lesbarer Shortcode für den Classic Editor und Page Builder;
- optionale Presets für Einbettungen, die an mehreren Stellen verwendet werden.

## Geplanter Funktionsumfang für Version 1.0

- Gutenberg-Block mit den Varianten **Turniertabelle** und **Spielplan**
- Eingabe einer öffentlichen Turnier-ID, eines Slugs oder einer Turnierplan.eu-URL
- echte Vorschau direkt im Block-Editor
- Filter nach Gruppe, Teilnehmer und Spielbereich
- Auswahl von Farbschema, Dichte und sichtbaren Informationen
- responsive Ausgabe mit automatischer Höhenanpassung
- wiederverwendbare Einbettungs-Presets
- Shortcode-Generator mit Kopierfunktion
- deutsch- und englischsprachige Oberfläche
- verständliche Fehlerzustände statt leerer oder defekter Einbettungen
- datensparsame Konfiguration ohne lokale Kopie der Turnierergebnisse

## Beispiel

Eine Tabelle soll später mit einem kompakten Shortcode eingebunden werden können:

```text
[turnierplan tournament="12345" view="standings"]
```

Ein gefilterter Spielplan könnte so aussehen:

```text
[turnierplan tournament="12345" view="matches" group="grp_01k4f70bcde2fgh3jkm4npq5rs"]
```

Standardwerte werden bewusst nicht in den generierten Shortcode geschrieben. Block, Shortcode und Preset verwenden intern dasselbe validierte Konfigurationsmodell.

## So soll die Einbettung funktionieren

1. Den Turnierplan-Block in eine WordPress-Seite einfügen.
2. Turnier-ID, Slug oder öffentliche Turnierplan.eu-URL angeben.
3. Ansicht und optionale Filter auswählen.
4. Vorschau prüfen und die Seite veröffentlichen.
5. Änderungen am Turnier erscheinen anschließend automatisch in der Einbettung.

Das Plugin verwaltet keine Turniere, Teams oder Ergebnisse. Turnierplan.eu bleibt die fachliche Datenquelle.

## Technisches Konzept

Für die erste Version ist eine isolierte Iframe-Ausgabe vorgesehen. Dadurch bleiben Styles und Skripte des WordPress-Themes von der Turnierdarstellung getrennt.

```text
WordPress-Block / Shortcode / Preset
                  |
                  v
        gemeinsamer PHP-Renderer
                  |
                  v
      Turnierplan.eu Embed-Endpunkt
```

Die geplante Architektur umfasst:

- einen serverseitig gerenderten Block `turnierplan-eu/embed`;
- den Shortcode `[turnierplan]`;
- einen zentralen Renderer für sämtliches Iframe-Markup;
- eine WordPress-REST-Route als geschützten Metadaten-Proxy für den Editor;
- einen kleinen Cache mit nachvollziehbarem Fehlerverhalten;
- fest definierte Turnierplan.eu-Endpunkte statt frei eingebbarer Remote-URLs;
- ein versioniertes Konfigurationsschema für spätere Migrationen.

Die Produktspezifikation, Sicherheitsregeln, UI-Abläufe und Akzeptanzkriterien stehen in [`WORDPRESS.md`](./WORDPRESS.md). Die verbindlichen Datenverträge für Version 1 liegen unter [`docs/contracts/v1`](./docs/contracts/v1/README.md).

## Geplante Systemanforderungen

- WordPress 6.5 oder neuer
- PHP 8.3 oder neuer
- HTTPS
- Block-Theme oder klassisches Theme
- Classic Editor und viele Page Builder über den Shortcode

Diese Mindestwerte wurden in Block 1 als Arbeitsgrundlage festgelegt. Vor dem ersten Release werden sie gegen die tatsächlich getestete Kompatibilitätsmatrix und den dann aktuellen Sicherheitsstatus geprüft.

## Installation

Der Quellstand lässt sich für die Entwicklung bereits als Plugin-Verzeichnis installieren und aktivieren. Einrichtung, lokale WordPress-Instanz und alle Prüfkommandos sind in [`DEVELOPMENT.md`](./DEVELOPMENT.md) beschrieben. Ein geprüftes Release-ZIP steht noch nicht zur Verfügung. Die spätere Veröffentlichung ist über **GitHub Releases** und nach Zulassung über **WordPress.org** vorgesehen.

Bitte verwende bis dahin keine automatisch aus dem Entwicklungszweig erzeugten Archive auf produktiven WordPress-Websites.

## Roadmap

Die [Entwicklungsroadmap in 15 Blöcken](./WORDPRESS-ROADMAP.md) enthält für jeden Block Aufgaben, Abhängigkeiten, Liefergegenstände und Abnahmekriterien:

- [x] 01 – Produktentscheidungen und V1-Verträge
- [x] 02 – Repository, Entwicklungsumgebung und Plugin-Grundgerüst
- [x] 03 – Öffentliche Referenzen, Freigaben und Datenadapter
- [x] 04 – Metadaten-API mit Fehlern und Cache
- [ ] 05 – Embed-Grundlayout und Turniertabelle
- [ ] 06 – Spielplan, Filter und Live-Aktualisierung
- [ ] 07 – Browserprotokoll und bestehendes Event-Widget
- [ ] 08 – Zentrales Konfigurationsmodell und URL-Bau
- [ ] 09 – Einstellungen, Dienstfreigabe und Metadaten-Proxy
- [ ] 10 – Gemeinsamer Renderer und Shortcode
- [ ] 11 – Gutenberg-Block und direkte Vorschau
- [ ] 12 – Wiederverwendbare Presets und Verwaltungsoberfläche
- [ ] 13 – Bedienqualität, Barrierefreiheit und Sprachen
- [ ] 14 – Lebenszyklus, Multisite und Gesamtprüfung
- [ ] 15 – Dokumentation, Release und WordPress.org

Die ursprüngliche [Produktspezifikation](./WORDPRESS.md) bleibt erhalten. Die Roadmap ergänzt die Bestandsanalyse, notwendige Vertragspräzisierungen, WordPress.org-Vorgaben und das gesonderte Backlog nach Version 1.0.

## Sicherheit und Datenschutz

Das Plugin soll ausschließlich mit fest definierten HTTPS-Endpunkten von Turnierplan.eu kommunizieren. Beliebige Remote-URLs werden nicht akzeptiert. Eingaben werden normalisiert und validiert, Ausgaben kontextbezogen maskiert und schreibende WordPress-Aktionen durch Berechtigungs- und Nonce-Prüfungen geschützt.

Beim Laden einer Vorschau im Editor sowie einer Einbettung im Frontend entsteht eine Verbindung zu Turnierplan.eu. Vor dem ersten Release werden die übertragenen Daten, die Cache-Dauer und ein geeigneter Textbaustein für die WordPress-Datenschutzerklärung vollständig dokumentiert. Es werden keine Datenschutz- oder Tracking-Aussagen veröffentlicht, die nicht durch das ausgelieferte Verhalten belegt sind.

Sicherheitsprobleme bitte nicht als öffentliches Issue mit ausnutzbaren Details melden. Ein vertraulicher Meldeweg wird vor dem ersten öffentlichen Release ergänzt.

## Barrierefreiheit

Die Bedienoberfläche soll vollständig per Tastatur nutzbar sein und WordPress-Komponenten mit verständlichen Beschriftungen verwenden. Die eingebettete Ansicht benötigt eine sinnvolle Iframe-Beschriftung, sichtbare Fokuszustände, ausreichende Kontraste und eine brauchbare Darstellung auf kleinen Bildschirmen.

## Eigene Produktentwicklung

Architektur, Bedienkonzept, Datenverträge, Texte, Tests und Gestaltung entstehen neu für Turnierplan.eu. Das Repository enthält ausschließlich eigene Projektdateien sowie bewusst ausgewählte und lizenzrechtlich geprüfte Abhängigkeiten.

Beiträge dürfen keinen kopierten Code und keine unzulässig verwendeten Assets enthalten. Als technische Grundlagen dienen die offiziellen WordPress-Schnittstellen sowie die von Turnierplan.eu selbst definierten APIs.

## Mitwirken

Die Implementierung läuft blockweise anhand der Roadmap. Hinweise zur Produktspezifikation, reproduzierbare Fehlerberichte und klar abgegrenzte Verbesserungsvorschläge sind willkommen.

Für spätere Pull Requests gelten voraussichtlich folgende Grundsätze:

- WordPress Coding Standards und sichere WordPress-APIs verwenden;
- neue Logik mit passenden PHP-, JavaScript- oder Integrationstests absichern;
- keine Zugangsdaten, personenbezogenen Testdaten oder generierten Build-Artefakte committen;
- sichtbare Texte übersetzbar halten;
- Änderungen an Verhalten oder Datenverträgen dokumentieren.

Die konkreten Entwicklungsbefehle, Laufzeitgrenzen und Prüfungen stehen in [`DEVELOPMENT.md`](./DEVELOPMENT.md).

## Dokumentation

- [Produktspezifikation und Architektur](./WORDPRESS.md)
- [Entwicklungsplan in 15 Blöcken](./WORDPRESS-ROADMAP.md)
- [Entscheidungsprotokoll für Block 1](./docs/block-01/DECISIONS.md)
- [Öffentlicher Embed-Vertrag V1](./docs/contracts/v1/README.md)
- [Entwicklungsanleitung](./DEVELOPMENT.md)
- [Umsetzung und Abnahme von Block 2](./docs/block-02/IMPLEMENTATION.md)
- [Abhängigkeiten und Lizenzen](./docs/block-02/DEPENDENCIES.md)
- [Turnierplan.eu](https://www.turnierplan.eu/)
- [WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

## Lizenz

Die vorhandene Datei [LICENSE](./LICENSE) enthält die GNU General Public License, Version 2. Vor dem ersten Plugin-Release werden die Lizenzangaben in Quelltexten, Plugin-Header, Readme und Abhängigkeiten aufeinander abgestimmt; die genaue Versionsklausel wird dabei ausdrücklich festgelegt.
