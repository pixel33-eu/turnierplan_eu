# Block 3 – Umsetzung und Abnahme

Stand: 12. September 2026. Status: abgeschlossen.

## Ergebnis

Die Website besitzt jetzt die interne Grundlage einer eigenständigen Embed-API. Dieser Block veröffentlicht noch keinen HTTP-Endpunkt. `GET /api/embed/v1/tournaments/{public-ref}/metadata` folgt in Block 4.

Die neue Logik liegt im getrennten Website-Projekt unter `api/embed/v1`. Der Bootstrap verzichtet auf den allgemeinen Anwendungsstart und damit auf automatische Anmeldung, Sitzungsstart, Seitennavigation und Seitenaufruf-Tracking.

## Öffentliche Datenregeln

- Ein Turnier ist nur bei `public = 1` und einem von `deleted` verschiedenen Status verfügbar.
- Fehlende, private und gelöschte Turniere werden nach außen gleich behandelt.
- Verknüpfte Turniere verwenden dieselbe Freigabeprüfung und können keine Daten aus einer privaten Quelle ergänzen.
- Die Freigabeprüfung läuft vor einem Ursprungscache-Treffer. Ein Widerruf kann deshalb nicht durch eine warme Serverdatei überdeckt werden.
- Der spätere HTTP-Cache darf gemäß V1-Vertrag bis zu 60 Sekunden frisch und bis zu 300 Sekunden während einer erneuten Prüfung nachlaufen. Die konkrete Header- und `304`-Umsetzung gehört zu Block 4.
- Der Datenadapter baut die öffentliche Antwort ausschließlich aus erlaubten Feldern auf. Verwaltungs-IDs, interne Hashes, E-Mail-Adressen und private Notizen werden nicht weitergereicht.

## Stabile Referenzen

Die Migration `database/migrations/20260912_embed_public_references.sql` im Website-Projekt ergänzt dauerhafte Referenzen für Turniere, Gruppen und Teilnehmer sowie gespeicherte Slug-Aliase. Kanonische Referenzen verwenden die in Block 1 vereinbarten Präfixe und 26 zufällige Crockford-Base32-Zeichen.

Numerische Turnier-IDs und vorhandene Slugs bleiben Eingabe-Aliase. Antworten verwenden die kanonische Referenz. Teilnehmerreferenzen beruhen auf der bestehenden Teilnehmerzeile und bleiben deshalb bei Umbenennen oder Umsortieren gleich. Gruppenreferenzen beruhen auf der internen Gruppenkennung und bleiben bei einer Umbenennung gleich.

Die Migration wurde nicht gegen eine Produktionsdatenbank ausgeführt. Sie muss vor der ersten Route aus Block 4 kontrolliert eingespielt werden.

## Begrenzte Integration in die bestehende Website

Bestehende Abläufe für Erstellen, Kopieren, Löschen, Wiederherstellen, Statuswechsel und Backoffice bleiben unverändert. Zwei vorhandene Funktionen besitzen kleine optionale Adapterargumente:

- `scores_build_response()` kann für den API-Aufruf einen geprüften Resolver für verknüpfte Turniere und den Mini-Modus als Wert erhalten.
- `resolveExternalParticipantLocal()` reicht diesen Resolver rekursiv weiter und umgeht nur in diesem kontrollierten API-Aufruf die sitzungsbasierte Seitenprüfung.

Alle bisherigen Aufrufe ohne diese Argumente behalten ihr bisheriges Verhalten. Der bestehende Ergebnisalgorithmus wurde nicht kopiert und nicht durch eine zweite Berechnung ersetzt.

## Abnahme

Die Website-Tests decken folgende Anforderungen ab:

- kanonische Kennungen sowie numerische und Slug-Aliase;
- stabile Turnier-, Gruppen- und Teilnehmerreferenzen nach Umbenennen und Umsortieren;
- öffentliche, private, gelöschte, kommende, laufende, abgeschlossene und abgesagte Zustände;
- identische Nichtverfügbarkeitsmeldung für fehlende, private und gelöschte Turniere;
- Freigabeprüfung vor einem warmen Cache;
- Positivliste ohne interne und personenbezogene Felder;
- verknüpfte private Turniere ohne Datenbankabfrage oder Sitzungsstart im kontrollierten Resolver;
- Signaturkompatibilität und bestehende Zeit-/Schach-Ergebnisregressionen.

Ausgeführte Prüfungen:

```text
embed_bootstrap_test: OK
embed_publication_test: OK
embed_scores_regression_test: OK
scores_timezone_test: OK
chess scoring tests passed
```

Der Quelltext und die Dateistruktur des genannten fremden Plugins wurden weder übernommen noch als Vorlage kopiert.
