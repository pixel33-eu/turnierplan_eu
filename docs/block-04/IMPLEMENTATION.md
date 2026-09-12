# Block 4 – Umsetzung und Abnahme

Stand: 12. September 2026. Status: abgeschlossen.

## Metadaten-Endpunkt

Die getrennte Turnierplan.eu-Website stellt folgenden versionierten Quellstand bereit:

```http
GET /api/embed/v1/tournaments/{reference}/metadata?lang=de
```

Der Endpunkt akzeptiert kanonische Turnierreferenzen, positive numerische Bestands-IDs und gespeicherte Slug-Aliase. Eine Erfolgsantwort verwendet unabhängig von der Eingabe immer die kanonische Referenz. Ausschließlich `lang` ist als Query-Parameter erlaubt. `auto`, unterstützte Sprachcodes und der dokumentierte Rückfall für gültige, aber nicht unterstützte Sprachen werden getrennt behandelt.

Die Implementierung liegt vollständig unter `api/embed/v1` im Website-Projekt:

- `.htaccess` bildet die lesbare Route auf `metadata.php` ab und sperrt direkte Zugriffe auf interne `_*.php`-Dateien.
- `metadata.php` verbindet Requestverarbeitung, Datenadapter und Cache.
- `_http.php` enthält die von Datenbankzugriff unabhängige HTTP- und Vertragslogik.
- `_bootstrap.php`, `_public.php` und `_adapter.php` bleiben die in Block 3 geschaffene isolierte Grundlage.

Die Root-Rewrite-Regeln, Turniererstellung, Kopier- und Löschabläufe, Statuswechsel, Navigation und Backoffice wurden für Block 4 nicht geändert.

## Antwortvertrag

Vor jeder Auslieferung prüft die Referenzschicht erneut `public = 1` und `status <> 'deleted'`. Diese Prüfung geschieht vor dem Ursprungscache und vor `If-None-Match`. Ein widerrufenes oder gelöschtes Turnier kann deshalb weder einen alten Erfolg noch eine `304`-Antwort vom Ursprung erhalten.

Erfolgsantworten werden vor der Ausgabe gegen die wesentlichen syntaktischen und semantischen Regeln des V1-Metadatenschemas geprüft. Dazu gehören exakte Feldlisten, Referenzformate, Sprachen, Zustände, Datumsbereich, Ansichten, Fähigkeiten, Gruppenbezüge, Brandingregeln und Höchstzahlen. Die JSON-Ausgabe ist unkomprimiert auf 1 MiB begrenzt.

Der Datenadapter meldet `standings` nur für Turniermodi mit Gruppenphase und aktivierter Rangliste. `matches` bleibt auch für direkte K.-o.-Turniere verfügbar. Gruppen- und Teilnehmerfilter sowie Gruppennavigation werden nur gemeldet, wenn die benötigten Daten vorhanden sind.

Erfolgsantworten verwenden:

```text
Content-Type: application/json; charset=utf-8
Cache-Control: public, max-age=60, stale-while-revalidate=300
ETag: "…"
Content-Language: …
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
```

Der ETag wird aus kanonischer Referenz, aufgelöster Sprache und vollständigem Antwortinhalt gebildet. Bei Übereinstimmung folgt `304` ohne Body, allerdings erst nach erneuter Veröffentlichungskontrolle.

## Fehler und Limits

Fehler erfüllen das V1-Fehlerschema und enthalten eine zufällige `req_…`-Kennung. Die Implementierung deckt `invalid_reference`, `invalid_parameter`, `tournament_not_found`, `rate_limited` und `temporarily_unavailable` ab. Andere Methoden als `GET` erhalten Status 405 und `Allow: GET`. Interne Ausnahmen, SQL-Details und Stacktraces erscheinen nicht in der Antwort.

Das lokale Rate Limit erlaubt 120 Abrufe je `REMOTE_ADDR` in 60 Sekunden. Der Cache speichert nur einen SHA-256-Schlüssel, Fensterbeginn und Zähler. Status 429 enthält `Retry-After`. Fehler beim lokalen Zählerspeicher blockieren den Dienst nicht und können durch Webserver- oder Infrastruktur-Limits ergänzt werden.

## Abnahme

Die neuen und bestehenden Website-Tests prüfen:

- Erfolg mit leerem Gruppen- und Teilnehmerzustand;
- kanonische Referenz in der Erfolgsantwort;
- Sprachübergabe sowie regionale und nicht unterstützte Sprachcodes;
- unbekannte und als Array übergebene Parameter;
- ungültige Referenzen und unzulässige HTTP-Methoden;
- einheitliche 404-Antworten für nicht öffentliche Turniere;
- Freigabeprüfung vor einer konditionalen `304`-Antwort;
- ETag-, Cache-, Sprach- und Sicherheitsheader;
- Antwortkörper ohne Score-Rohdaten;
- vollständige Feld-Positivliste und Metadatenvalidierung;
- fehlende Tabellenansicht bei direktem K.-o.-Modus;
- 1-MiB-Grenze, 429 und `Retry-After`;
- festes Rate-Limit-Fenster;
- Route und Schutz interner API-Dateien;
- bestehende Ergebnis-, Zeitzonen- und Schachregressionen.

Die Datenbankmigration aus Block 3 wurde nicht gegen die Produktionsdatenbank ausgeführt. Vor der produktiven Aktivierung muss `database/migrations/20260912_embed_public_references.sql` kontrolliert eingespielt und der Endpunkt anschließend mit einem eigens freigegebenen Testturnier geprüft werden.
