# Block 2 – Umsetzung und Abnahme

Stand: 11. September 2026. Status: abgeschlossen.

## Gelieferte Grundlage

- `turnierplan-eu.php` enthält den vollständigen WordPress-Plugin-Header, die Version `0.1.0`, die Textdomain sowie die Mindestwerte WordPress 6.5 und PHP 8.3.
- Der kleine eigene Autoloader lädt ausschließlich Klassen im Namespace `TurnierplanEU\WordPress` aus `src/`. Das Plugin benötigt zur Laufzeit weder Composer noch npm.
- Bootstrap, Versionsprüfung und Lebenszyklus sind nach Verantwortung getrennt. Die Aktivierung prüft die Laufzeit und speichert nur `tpeu_plugin_version`; sie erzeugt keine Beispieldaten und führt keinen Netzwerkzugriff aus.
- `VERSION` ist die maßgebliche Versionsquelle. Eine automatisierte Prüfung hält Plugin-Header, PHP-Konstante, `package.json` und `readme.txt` synchron.
- `composer.lock` und `package-lock.json` fixieren die Entwicklungswerkzeuge. Zweck, Lizenz und Auslieferungsstatus der direkten Abhängigkeiten stehen in [`DEPENDENCIES.md`](./DEPENDENCIES.md).
- `.wp-env.json` stellt eine schnelle lokale Playground-Instanz bereit. `.wp-env.minimum.json` beschreibt die dauerhafte Docker-Instanz an der Mindestgrenze. Die automatisierte Playground-Smoke-Prüfung verwendet ebenfalls WordPress 6.5 und PHP 8.3.
- Die GitHub-Actions-CI prüft PHP 8.3 und 8.5 sowie die Node- und WordPress-Strecke. Dependabot überwacht Composer, npm und Actions.

## Abnahme

Die vollständige lokale Prüfung besteht aus:

```powershell
composer validate --strict
composer check
npm run check
```

Ergebnis am 11. September 2026:

- WordPress Coding Standards: 8 PHP-Dateien ohne Befund;
- PHPStan: Stufe 8, 8 Dateien ohne Befund;
- PHPUnit: 4 Tests und 4 Assertions erfolgreich;
- ESLint und `package.json`-Prüfung erfolgreich;
- Versionsabgleich über 5 Quellen erfolgreich;
- V1-Verträge: 14 JSON-Dateien, 7 gültige und 3 absichtlich ungültige Beispiele sowie semantische Beziehungen und lokale Links erfolgreich geprüft;
- JavaScript-Produktionsbuild erfolgreich;
- Lizenzmetadaten aller 9 direkten npm-Entwicklungsabhängigkeiten geprüft; keine npm-Laufzeitabhängigkeit vorhanden;
- Composer-Audit und vollständiger npm-Audit ohne bekannte Sicherheitslücke;
- WordPress-Smoke-Test: WordPress 6.5, PHP 8.3, Plugin aktiv, Versionsoption `0.1.0` gespeichert und kein PHP-Fatal-Error.

Die Mindestversionslogik besitzt eigene Grenztests für exakte, neuere und unzureichende Versionen. WordPress kann die statischen Header zusätzlich vor der Aktivierung auswerten und eine nicht erfüllte Anforderung in der Plugin-Verwaltung anzeigen.

## Projektgrenze

Dieses Repository enthält keinen Quelltext der Turnierplan.eu-Webanwendung und keine Datei des genannten Referenz-Plugins. Das Website-Projekt bleibt getrennt. Die einzigen fiktiven Testdaten dieses Blocks liegen ausdrücklich markiert unter `tests/fixtures`.

## Verwendete WordPress-Grundlagen

- [Header Requirements](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [Activation and Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
- [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [`@wordpress/eslint-plugin`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-eslint-plugin/)
- [WordPress Playground CLI](https://wordpress.github.io/wordpress-playground/developers/local-development/wp-playground-cli/)

Das umfassende Paket `@wordpress/scripts` wurde nach einem vollständigen Audit bewusst nicht aufgenommen: Seine für diesen Block ungenutzten Markdown-, Lighthouse- und Webpack-Zweige brachten zum Prüfzeitpunkt mehrere nicht auflösbare High-Severity-Befunde mit. Build, WordPress-ESLint-Regeln, Vertragsprüfung und Playground werden deshalb als einzeln fixierte Werkzeuge installiert. Das hält die Grundlage kleiner und der vollständige npm-Audit endet ohne bekannten Befund.
