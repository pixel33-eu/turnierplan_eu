# Validierung der V1-Verträge

Die vier Schema-Dateien verwenden JSON Schema Draft 2020-12. Seit Block 2 führt `tools/validate-contracts.mjs` die Prüfung plattformunabhängig mit den fest versionierten Entwicklungsabhängigkeiten `ajv` und `ajv-formats` aus. Die Prüfung läuft lokal und in CI.

Die vollständige lokale Prüfung einschließlich semantischer Beziehungen und Markdown-Links startet mit:

```powershell
npm ci
npm run test:contracts
```

Unter Windows bleibt der bisherige Einstieg als dünner Wrapper verfügbar:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File .\docs\contracts\v1\validate-contracts.ps1
```

Der Validator parst alle 14 JSON-Dateien, kompiliert die vier Schemas, akzeptiert sieben Positivbeispiele und verlangt die Ablehnung der drei Negativbeispiele. Diese sichern drei Vertragsgrenzen: gespeicherte externe URLs sind verboten, nicht freigegebene Metadatenfelder werden erkannt und fremde Browsernachrichten werden verworfen.

## Zusätzliche semantische Prüfungen

JSON Schema prüft die Einzelwerte. Folgende Beziehungen werden in den Contract-Tests aus Block 3 beziehungsweise im gemeinsamen Validator aus Block 8 geprüft:

- `matchFrom` ist höchstens `matchTo`;
- `dateFrom` ist höchstens `dateTo` und jedes Datum existiert wirklich;
- `minHeight` ist höchstens `maxHeight`;
- `response_language` und `tournament.default_language` kommen in `supported_languages` vor;
- jede `group_ids`-Referenz zeigt auf eine Gruppe derselben Antwort;
- jede Ansicht kommt höchstens einmal vor und enthält nur die für sie erlaubten Filter und Optionen;
- `refresh_interval_seconds` ist für `completed` und `cancelled` `null`;
- der öffentliche Link enthält dieselbe kanonische Turnierreferenz wie `tournament.ref`;
- `branding.required` hat `default_visible=true`, `branding.hidden` hat `default_visible=false`;
- HTTP-Status und Fehlercode bilden eine erlaubte Kombination aus der Vertragstabelle.
