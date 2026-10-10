# Entwicklung

Dieses Repository enthält ausschließlich das eigenständig entwickelte WordPress-Plugin für Turnierplan.eu. Die Anwendung unter `D:\2025\Turnierplan.eu` bleibt ein getrenntes Projekt. Website-Code, Produktionsdaten und Dateien anderer Plugins gehören nicht in dieses Repository.

## Voraussetzungen

- PHP 8.3 oder neuer
- Composer 2
- Node.js 20.19 oder neuer beziehungsweise Node.js 22.13 oder neuer
- npm 10.8 oder neuer
- für die Docker-Laufzeit von `wp-env`: Docker Desktop

Die standardmäßige lokale Laufzeit verwendet WordPress Playground und benötigt kein Docker. Die automatisierte Matrix prüft die festgelegte Untergrenze WordPress 6.5 mit PHP 8.3 sowie WordPress 7.1.3 mit PHP 8.5.

## Ersteinrichtung

```powershell
composer install
npm ci
```

Composer- und npm-Pakete sind reine Entwicklungsabhängigkeiten. Das Plugin lädt seine eigenen Klassen selbst und lässt sich deshalb auch ohne `vendor` und `node_modules` aktivieren.

## Lokale WordPress-Instanz

Die portable Testinstanz starten:

```powershell
npm run env:start
```

Danach ist WordPress unter `http://localhost:8888` erreichbar. Die von `wp-env` angelegte Anmeldung lautet standardmäßig `admin` / `password`. Das Plugin-Verzeichnis wird eingebunden und das Plugin automatisch aktiviert.

Wenn Docker Desktop vorhanden ist, kann alternativ eine dauerhafte Instanz mit WordPress 6.5 und PHP 8.3 gestartet werden:

```powershell
npm run env:start:docker
```

Die jeweils zuletzt gestartete Umgebung wird so beendet:

```powershell
npm run env:stop
```

Fixtures müssen künstlich erzeugt und als solche erkennbar sein. Ein neutraler Ausgangsdatensatz liegt in `tests/fixtures/synthetic-tournament.json`. Zugangsdaten, Exporte und personenbezogene Produktionsdaten dürfen nicht verwendet werden.

## Prüfungen und Build

Alle PHP-Prüfungen ausführen:

```powershell
composer check
```

Das umfasst WordPress Coding Standards, statische Analyse auf Stufe 8 und PHPUnit. Alle JavaScript-, Paket-, Versions-, Vertrags-, Build- und Lizenzprüfungen ausführen:

```powershell
npm run check
```

Die Node-Prüfkette startet kurzzeitig isolierte WordPress-Playground-Instanzen. `npm run test:wordpress` kontrolliert WordPress 6.5, PHP 8.3 und ein Block-Theme. `npm run test:wordpress:current` wiederholt den Lauf mit WordPress 7.1.3, PHP 8.5 und einem klassischen Theme. `npm run test:wordpress:lifecycle` prüft Aktivierung, Upgrade, Deaktivierung und beide Deinstallationsentscheidungen. `npm run test:wordpress:multisite` prüft Netzaktivierung, später angelegte Sites, Cachetrennung und sitebezogene Löschung. `npm run test:wordpress:release` installiert ausschließlich die aus der Release-ZIP entpackten Laufzeitdateien. Die Instanzen werden nach jeder Prüfung beendet.

Der offizielle Plugin Check läuft in GitHub Actions über `wordpress/plugin-check-action@v1`. Der lokale WordPress-Playground-CLI-Prozess 3.1.53 konnte Plugin Check 2.1.0 unter Windows nicht zuverlässig beenden; deshalb wird ein lokaler WASM-Abbruch nicht als Plugin-Check-Ergebnis gewertet.

Der Build landet in `build/` und wird nicht eingecheckt. `VERSION` ist die maßgebliche Versionsdatei; `npm run check:versions` prüft die notwendigen statischen Angaben im Plugin-Header, in `readme.txt`, in `package.json` und in der PHP-Konstante.

## Release-Paket

Nach einem erfolgreichen Build erzeugt dieser Befehl ZIP, Prüfsumme und Manifest:

```powershell
npm run release:build
```

`npm run release:verify` baut den ZIP-Datenstrom zweimal und prüft Bytegleichheit. Das Paket enthält genau ein Verzeichnis `turnierplan-eu/` und nur die festgelegten Laufzeitdateien. Der Installationstest läuft mit:

```powershell
npm run test:wordpress:release
```

Ein öffentliches Release wird von einem sauberen Git-Tag erzeugt:

```powershell
git tag -a v1.0.1 -m "Turnierplan.eu WordPress plugin 1.0.1"
node tools/build-release.mjs --require-tag
```

Nach dem Push eines annotierten Tags `vX.Y.Z` führt `.github/workflows/release.yml` die PHP- und Node-Prüfkette erneut aus, erzeugt die exakt getaggten Artefakte und veröffentlicht ZIP, SHA-256-Datei und Manifest als GitHub Release. Das lokale Erstellen und Pushen des Tags sowie die WordPress.org-Einreichung bleiben bewusste externe Schritte. Der Ablauf steht in [`docs/block-15/WORDPRESS-ORG.md`](./docs/block-15/WORDPRESS-ORG.md).

Die eigenen WordPress.org-Banner, Icons und Funktionsabbildungen werden mit `npm run assets:directory` erzeugt. `npm run check:directory-assets` prüft die acht PNG-Dateien und ihre vorgeschriebenen Maße. Diese Dateien liegen unter `wordpress-org-assets/` und gehören nicht in das Plugin-ZIP.

Die Vertragsprüfung kann einzeln und plattformunabhängig gestartet werden:

```powershell
npm run test:contracts
```

## Abhängigkeiten

`composer.lock` und `package-lock.json` halten die Entwicklungsumgebung reproduzierbar. Direkte Abhängigkeiten, Zweck, Lizenz und Auslieferungsstatus sind in `docs/block-02/DEPENDENCIES.md` dokumentiert. Neue Laufzeitabhängigkeiten benötigen vor ihrer Aufnahme eine Prüfung auf Zweck, Wartungszustand, Sicherheit und GPL-Kompatibilität.

## Branch- und Commit-Ablauf

`main` bildet den geprüften Entwicklungsstand. Änderungen werden pro Roadmap-Block klein gehalten, lokal geprüft und mit einer aussagekräftigen Commit-Nachricht übertragen. Die Basis-CI wiederholt die PHP- und Node-Prüfungen bei Pushes und Pull Requests.
