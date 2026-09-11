# Turnierplan.eu für WordPress

Turnierpläne, Tabellen und Ergebnisse von [Turnierplan.eu](https://www.turnierplan.eu/) direkt in WordPress einbetten – ohne Ergebnisse doppelt zu pflegen und ohne eigenes HTML schreiben zu müssen.

> [!IMPORTANT]
> Das WordPress-Plugin befindet sich derzeit in der Konzept- und Entwicklungsphase. Es gibt noch kein produktionsreifes Release. Die technische Produktspezifikation liegt in [`WORDPRESS.md`](./WORDPRESS.md).

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
[turnierplan tournament="12345" view="matches" group="gruppe-a"]
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

Die vollständigen Datenverträge, Sicherheitsregeln, UI-Abläufe und Akzeptanzkriterien stehen in [`WORDPRESS.md`](./WORDPRESS.md).

## Geplante Systemanforderungen

- WordPress 6.5 oder neuer
- PHP 8.1 oder neuer
- HTTPS
- Block-Theme oder klassisches Theme
- Classic Editor und viele Page Builder über den Shortcode

Die endgültigen Mindestversionen werden vor dem ersten Release gegen die tatsächlich getestete Kompatibilitätsmatrix geprüft.

## Installation

Derzeit steht noch kein installierbares Plugin-Paket zur Verfügung. Sobald ein erstes Release veröffentlicht ist, wird es als geprüftes ZIP-Paket über **GitHub Releases** bereitgestellt. Die Installations- und Aktualisierungsschritte werden dann hier dokumentiert.

Bitte verwende bis dahin keine automatisch aus dem Entwicklungszweig erzeugten Archive auf produktiven WordPress-Websites.

## Roadmap

- [ ] stabile Metadaten- und Embed-Schnittstellen auf Turnierplan.eu
- [ ] WordPress-Plugin-Grundgerüst und zentrale Konfigurationsvalidierung
- [ ] dynamischer Block mit Tabellen- und Spielplanvariante
- [ ] Shortcode und gemeinsamer Renderer
- [ ] Editor-Vorschau, Filter und Fehlerzustände
- [ ] wiederverwendbare Presets
- [ ] Datenschutz-, Barrierefreiheits- und Sicherheitstests
- [ ] automatisierter Release-Prozess und erstes öffentliches ZIP

Die Reihenfolge und die detaillierten Abnahmekriterien sind in der [Produktspezifikation](./WORDPRESS.md#20-umsetzungsetappen) beschrieben.

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

Das Projekt befindet sich noch vor der Implementierungsphase. Hinweise zur Produktspezifikation, reproduzierbare Fehlerberichte und klar abgegrenzte Verbesserungsvorschläge sind willkommen.

Für spätere Pull Requests gelten voraussichtlich folgende Grundsätze:

- WordPress Coding Standards und sichere WordPress-APIs verwenden;
- neue Logik mit passenden PHP-, JavaScript- oder Integrationstests absichern;
- keine Zugangsdaten, personenbezogenen Testdaten oder generierten Build-Artefakte committen;
- sichtbare Texte übersetzbar halten;
- Änderungen an Verhalten oder Datenverträgen dokumentieren.

Konkrete Entwicklungsbefehle und Contribution-Richtlinien werden ergänzt, sobald das Plugin-Grundgerüst vorhanden ist.

## Dokumentation

- [Produktspezifikation und Architektur](./WORDPRESS.md)
- [Turnierplan.eu](https://www.turnierplan.eu/)
- [WordPress Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

## Lizenz

Für dieses Repository ist derzeit noch keine Lizenzdatei hinterlegt. Vor einer öffentlichen Code-Veröffentlichung wird eine WordPress-kompatible Open-Source-Lizenz festgelegt und als `LICENSE` ergänzt. Bis dahin werden durch die öffentliche Bereitstellung keine zusätzlichen Nutzungsrechte eingeräumt.

---

**Kurz gesagt:** Turniere einmal auf Turnierplan.eu pflegen und anschließend aktuell, responsiv und komfortabel in WordPress anzeigen.
