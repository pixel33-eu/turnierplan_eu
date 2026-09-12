# Block 6 – Umsetzung und Abnahme

Stand: 12. September 2026. Status: abgeschlossen.

## Spieleansicht

Das getrennte Website-Projekt unter `D:\2025\Turnierplan.eu` stellt jetzt neben der Tabelle auch den vollständigen Spiele-Frame bereit:

```http
GET /embed/v1/tournaments/{reference}?view=matches&instance=550e8400-e29b-41d4-a716-446655440000&parent_origin=https%3A%2F%2Fverein.example
Accept: text/html
```

Die Ansicht nutzt denselben schlanken Bootstrap, dieselbe zentrale Freigabeprüfung, denselben 30-Sekunden-Ursprungscache und dieselben Sicherheitsheader wie die Tabelle. Bestehende Turnierseiten, `scores.php`, Navigation, Backoffice, Turnierverwaltung und Ergebnis-Schreibvorgänge wurden in Block 6 nicht geändert.

Der öffentliche Adapter ergänzt pro Spiel ausschließlich die benötigten Angaben:

- stabile öffentliche Referenzen für Gruppe und bereits feststehende Teilnehmer;
- sichere Bezeichnungen für noch offene K.-o.-Plätze;
- Spielnummer, lokales Turnierdatum und Uhrzeit, Feld und Runde;
- Ergebnis, Gewinner sowie geplant, live, pausiert oder beendet als Zustand;
- die vorhandenen Kennzeichen `nv` für Verlängerung und `ne` für Entscheidung im Elfmeterschießen.

Datum und Uhrzeit stammen aus der bestehenden Match-Tabelle. Diese Zeitstempel speichern die Turnier-Uhrzeit als UTC-Zahlenwert; der Adapter formatiert sie deshalb mit `gmdate`, damit Serverzeitzone und Sommerzeit die geplante Uhrzeit nicht verschieben. Eine Schiedsrichterzuordnung ist in der aktuellen fachlichen Match-Struktur nicht vorhanden. Die Fähigkeit `referee` wird daher nicht gemeldet und der Frame erfindet keinen Wert.

## Filter und Turnierarten

`group`, `participant`, `match_from`, `match_to`, `date_from` und `date_to` lassen sich beliebig kombinieren und bilden eine Schnittmenge. Spielnummern von 1 bis 999999 sowie echte ISO-Daten werden validiert; beide Bereichsenden sind inklusive. Spiele ohne lokales Datum fallen bei aktivem Datumsfilter heraus. Eine gültige Kombination ohne Treffer zeigt einen zugänglichen Leerzustand.

Gruppen- und Teilnehmerfilter verwenden ausschließlich kanonische `grp_…`- und `ptc_…`-Referenzen. Eine syntaktisch gültige, inzwischen entfernte Referenz wird verworfen und im Frame erklärt. Teilnehmerreferenzen bleiben an die unveränderliche Teilnehmerzeile gebunden; Umbenennen und Umsortieren verändern sie nicht.

Die gemeinsame Spieleliste wurde mit Gruppenmodus, Liga, direktem K.-o. und Schweizer System geprüft. Direkte K.-o.-Turniere veröffentlichen keine technische Gruppe als Filter oder Anzeigeoption. Noch offene Paarungen zeigen die vorhandene Herkunft wie „Sieger Spiel 10“. `date=auto` zeigt das Datum bei mehreren enthaltenen Turniertagen, während `show` die gemeldeten Detailfelder steuert.

## Sparsame Aktualisierung

Das lokale Modul `embed/v1/assets/embed-v1.js` lädt ausschließlich die aktuelle Frame-URL neu. Ein direkter Browserzugriff auf das interne `scores.php` findet nicht statt.

- `live` verwendet mindestens 30 Sekunden, passend zum Frame-Cache.
- `upcoming` verwendet mindestens 60 Sekunden.
- `completed` und `cancelled` laden den Aktualisierungscontroller nicht.
- `document.hidden` beendet den Timer und bricht einen laufenden Abruf ab; beim erneuten Sichtbarwerden wird kontrolliert fortgesetzt.
- Nach dem ersten Erfolg wird `If-None-Match` verwendet; `304` benötigt keinen neuen Dokumentinhalt.
- `429` beachtet `Retry-After`. Netz- und Dienstfehler verwenden einen wachsenden Backoff bis fünf Minuten.
- Bei Fehlern bleibt der letzte Frame-Inhalt sichtbar und eine `aria-live`-Statuszeile erklärt die verzögerte Aktualisierung.
- Abrufe verwenden keine Cookies, keine Sitzung, keinen Web Storage und keine externen Skripte.

## Dateien im Website-Projekt

- erweitert: `api/embed/v1/_adapter.php`
- erweitert: `api/embed/v1/README.md`
- erweitert: `embed/v1/_frame.php`
- erweitert: `embed/v1/assets/embed-v1.css`
- neu: `embed/v1/assets/embed-v1.js`
- erweitert: `embed/v1/README.md`
- aktualisiert: `tests/embed_publication_test.php`
- aktualisiert: `tests/embed_standings_test.php`
- neu: `tests/embed_matches_test.php`
- neu: `tests/embed_refresh_test.mjs`

Es wurden keine Datenbankmigration und keine neue Abhängigkeit hinzugefügt.

## Abnahme

Die PHP-Abnahme verwendet ausschließlich eigene fiktive Turnier-, Gruppen-, Teilnehmer- und Spieldaten. Sie prüft unter anderem:

- vollständige und leere Spielelisten sowie noch offene K.-o.-Paarungen;
- mehrtägige Datumsanzeige und unveränderte lokale Turnier-Uhrzeiten;
- kombinierte Gruppen-, Teilnehmer-, Nummern- und Datumsfilter;
- inklusive Grenzen, ungültige Werte und gültige Kombinationen ohne Treffer;
- Hinweise für entfernte stabile Gruppen- und Teilnehmerreferenzen;
- Gruppenmodus, Liga, direkte K.-o.-Turniere und Schweizer System;
- Felder, Runden, Ergebnisse, Gewinner, Live-/Pausenzustand, Verlängerung und Elfmeterschießen;
- dynamische Metadatenfähigkeiten ohne vorgetäuschte Schiedsrichterdaten;
- lokales Aktualisierungsmodul nur für aktive Turnierzustände.

Der JavaScript-Test prüft die Mindestintervalle, den vollständigen Polling-Stopp für versteckte Tabs und abgeschlossene/abgesagte Turniere, `Retry-After`, den Backoff sowie das Verbot von `scores.php`, Cookies und Web Storage. Alle bisherigen Embed-, Ergebnis-, Zeitzonen- und Schachregressionen laufen weiterhin ohne Fehler.

Eine interaktive Browserinstanz war für die erneute Sichtprüfung in dieser Sitzung nicht verfügbar. Das Kartenlayout verwendet die bereits in Block 5 bei 320 und 1024 Pixel geprüfte Frame-Hülle und besitzt zusätzliche Regeln für 420 Pixel und schmaler. Eine erneute reale Browsermatrix bleibt in Block 14 Teil der Gesamtprüfung.

Die Produktionsmigration aus Block 3 wurde weiterhin nicht ausgeführt. Die Route wurde nicht deployt und ist daher auf der produktiven Website noch nicht erreichbar.
