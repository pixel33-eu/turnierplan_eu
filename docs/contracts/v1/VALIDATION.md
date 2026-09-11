# Validierung der V1-Verträge

Die vier Schema-Dateien verwenden JSON Schema Draft 2020-12. Die Befehle wurden am 11. September 2026 mit Node.js und `ajv-cli` 5 ausgeführt. Block 2 übernimmt den Validator als fest versionierte Entwicklungsabhängigkeit und automatisiert diese Prüfungen in CI.

Die vollständige lokale Prüfung einschließlich semantischer Beziehungen und Markdown-Links startet mit:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass `
  -File .\docs\contracts\v1\validate-contracts.ps1
```

`ExecutionPolicy Bypass` gilt nur für diesen Prozess und verändert keine Systemeinstellung. Das Skript lädt `ajv-cli` bei Bedarf über `npx`; es fügt dem Repository keine Laufzeitabhängigkeit hinzu. Die folgenden Einzelbefehle dokumentieren die darin enthaltenen Schema-Prüfungen.

## JSON-Syntax

In PowerShell aus dem Repository-Stamm:

```powershell
$jsonFiles = rg --files docs/contracts/v1 -g "*.json"
node -e "const fs=require('fs'); for (const f of process.argv.slice(1)) JSON.parse(fs.readFileSync(f,'utf8')); console.log('JSON syntax OK:', process.argv.length-1, 'files');" $jsonFiles
```

Erwartung: Alle Dateien lassen sich als JSON parsen.

## Gültige Beispiele

```powershell
npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/embed-config.schema.json `
  -d "docs/contracts/v1/examples/valid/embed-config-*.json"

npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/metadata-response.schema.json `
  -d docs/contracts/v1/examples/valid/metadata-success.json

npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/error-response.schema.json `
  -d docs/contracts/v1/examples/valid/error-tournament-not-found.json

npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/embed-message.schema.json `
  -d "docs/contracts/v1/examples/valid/message-*.json"
```

Erwartung: Jeder Befehl endet mit Exitcode 0 und meldet alle Beispiele als `valid`.

## Ungültige Beispiele

Jeder folgende Befehl muss mit einem von 0 verschiedenen Exitcode enden und `invalid` melden:

```powershell
npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/embed-config.schema.json `
  -d docs/contracts/v1/examples/invalid/embed-config-external-url.json

npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/metadata-response.schema.json `
  -d docs/contracts/v1/examples/invalid/metadata-private-field.json

npx --yes ajv-cli@5 validate --spec=draft2020 `
  -s docs/contracts/v1/schemas/embed-message.schema.json `
  -d docs/contracts/v1/examples/invalid/message-wrong-type.json
```

Die Negativbeispiele sichern drei Vertragsgrenzen: gespeicherte externe URLs sind verboten, nicht freigegebene Metadatenfelder werden erkannt und fremde Browsernachrichten werden verworfen.

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
