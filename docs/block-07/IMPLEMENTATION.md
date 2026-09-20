# Block 7 – Umsetzung und Abnahme

Stand: 20. September 2026. Status: abgeschlossen.

## Versioniertes Browserprotokoll

Der Embed-Frame unter `D:\2025\Turnierplan.eu` und die künftige WordPress-Frontendseite verwenden jetzt den in Block 1 festgelegten Nachrichtenumschlag `turnierplan.eu/embed` in Version 1. Jede Einbettung besitzt eine kleingeschriebene UUID v4. Die drei Ereignisse sind:

- `ready` mit Ansicht, aufgelöster Sprache, erster Höhe und bekannten Filterwarnungen;
- `resize` mit einer ganzzahligen Höhe zwischen 160 und 8000 Pixeln;
- `status` mit `loading`, `ready`, `empty`, `stale` oder `error` und gegebenenfalls einem stabilen Fehlercode.

Der Frame verwendet ausschließlich die bereits beim Request streng validierte `parent_origin` als konkretes `postMessage`-Ziel. Der WordPress-Empfänger prüft vor jeder Änderung exakte Dienst-Origin, `event.source`, Typ, Version, Instanz, Ereignis und die vollständige Nutzlast. Zusätzliche Felder, `NaN`, falsche UUID-Schreibweisen und Höhen außerhalb der Vertragsgrenzen werden verworfen. Nachrichten verändern nie die Breite.

Die Elternbibliothek begrenzt die gültige Frame-Höhe zusätzlich auf die konfigurierte Mindest- und Maximalhöhe. Der Frame misst Dokument und Body, entprellt `ResizeObserver`-Signale und sendet nur bei einer tatsächlichen Höhenänderung. Der natürliche Scrollbereich im Iframe bleibt erhalten, falls der Inhalt länger als die konfigurierte Elternhöhe ist.

## Bereitschaft, Status und Lebenszyklus

Der Controller startet die Bereitschaftsfrist über `markRequested()` erst in dem Moment, in dem ein lazy geladenes Frame tatsächlich angefordert wird. Ein späteres `load` dient als zusätzliche Absicherung. `ready` beendet die Frist. Bei Ablauf ruft der Controller `onReadyTimeout` mit einem neutralen Ladezustand auf; er behauptet keinen von der Elternseite nicht beobachtbaren HTTP-Fehler. Der gemeinsame Renderer aus Block 10 bindet daran seinen immer vorhandenen öffentlichen Fallback-Link und den übersetzten Ladehinweis an.

Aktualisierungen senden `loading` vor dem Abruf, danach wieder `ready` oder `empty`. Ein begrenzter Abruf meldet `stale/rate_limited`, sonstige vorübergehende Fehler `stale/refresh_failed`. Der bisher sichtbare Inhalt bleibt dabei erhalten. Beendete und abgesagte Turniere laden das kleine Protokollmodul ebenfalls, starten weiterhin aber keinen Aktualisierungstimer.

Jeder Elterncontroller besitzt eigene Listener und eine eigene Instanzprüfung. Ein `MutationObserver` beendet ihn, sobald sein Iframe aus dem Dokument entfernt wurde. `destroy()` ist wiederholbar und entfernt Timer, Nachrichtenlistener sowie Observer. Auf der Frame-Seite werden Resize-Observer, Timer und Fensterlistener bei `pagehide` entfernt.

## Bestehendes Event-Widget

Das vorhandene Event-Widget wurde nur in seinem abgegrenzten `widgets`-Ordner und in der gemeinsamen Embed-Schicht angepasst. Turnierkopien, Turnierverwaltung, Ergebnisabläufe und sonstige Website-Seiten wurden nicht verändert.

Der bisherige allgemeine App-Bootstrap wurde durch den sitzungslosen Embed-Bootstrap ersetzt. Die Abfrage führt die Regeln aus Block 3 mit: Ein Turnier erscheint nur bei aktiver Event-Freigabe, `public = 1` und einem Zustand ungleich `deleted`. Der externe Tailwind-CDN-Aufruf und das Inline-Skript wurden durch eigene lokale Dateien ersetzt.

Der neue Sender übermittelt ausschließlich eine versionierte `resize`-Nachricht an die validierte Eltern-Origin. Der neue Empfänger prüft Origin, Fenster, Instanz und Höhe. Mehrere Widgets besitzen getrennte Instanzen. Ein gemeinsamer Dokument-Observer entfernt verwaiste Einträge und initialisiert später eingefügte oder nach einer Entfernung wieder eingesetzte Widgets neu.

Für zwischengespeicherte alte Event-Frames akzeptiert der neue Empfänger vorübergehend `turnierplan_widget_height`. Auch dieser Pfad verlangt die exakte Turnierplan.eu-Origin, das zugehörige `contentWindow`, eine exakt geformte Nachricht und eine Höhe innerhalb der Grenzen. Nach der ersten V1-Nachricht wird der alte Typ für die Instanz ignoriert. Der neue Frame sendet keine unsichere Alt-Nachricht und verwendet nie einen Wildcard-Origin. Ein alter Empfänger behält bis zum Laden des neuen Skripts seine feste Anfangshöhe; der Inhalt bleibt als normales Iframe-Dokument erreichbar.

## Dateien

Im Plugin-Repository:

- neu: `assets/src/embed-parent.js`
- erweitert: `assets/src/index.js`
- erweitert: `package.json`
- neu: `tests/embed-parent.test.mjs`

Im getrennten Website-Projekt:

- erweitert: `embed/v1/_frame.php`
- erweitert: `embed/v1/assets/embed-v1.js`
- erweitert: `embed/v1/README.md`
- überarbeitet: `widgets/event-frame.php`
- überarbeitet: `widgets/event.js`
- neu: `widgets/event-frame.js`
- neu: `widgets/event.css`
- neu: `widgets/.htaccess`
- neu: `widgets/README.md`
- aktualisiert: `tests/embed_matches_test.php`
- erweitert: `tests/embed_refresh_test.mjs`
- neu: `tests/event_widget_migration_test.php`
- neu: `tests/event_widget_protocol_test.mjs`
- neu: `tests/event_widget_parent_test.mjs`

## Abnahme

Die automatisierten Tests weisen nach:

- gefälschte Origins, fremde Fenster und falsche Instanzen verändern keine Einbettung;
- falscher Typ, falsche Version, zusätzliche Felder, `NaN` und Höhen über 8000 Pixeln werden abgewiesen;
- mehrere Frames bleiben durch `contentWindow` und Instanz voneinander getrennt;
- gültige Höhen werden an die konfigurierte Obergrenze gebunden, ohne Breiten zu übernehmen;
- der Bereitschaftstimer beginnt erst mit der tatsächlichen Anforderung und meldet neutralen Timeout;
- Entfernen und wiederholtes Zerstören räumen Listener, Timer und Observer auf;
- das Event-Widget unterstützt kontrolliert den alten Nachrichtentyp und initialisiert wieder eingesetzte Widgets neu;
- der Event-Frame verwendet keine Sitzung, kein Tracking, keine externen Assets und keine Wildcard-Nachricht;
- alle Embed-Regressionen aus den Blöcken 3 bis 6 bleiben grün.

Die Produktionsmigration aus Block 3 wurde weiterhin nicht ausgeführt. Die neuen Routen und Assets wurden nicht deployt. Eine vollständige reale Browser-, Tastatur- und WordPress-Matrix bleibt Bestandteil der Gesamtprüfung in Block 14.
