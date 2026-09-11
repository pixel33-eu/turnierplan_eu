# Abhängigkeiten in Block 2

Stand: 11. September 2026.

Das aktivierbare Plugin hat in Block 2 keine externe Laufzeitabhängigkeit. Es lädt nur eigene Klassen aus `src/`. Die folgenden Pakete werden ausschließlich lokal oder in CI für Entwicklung und Prüfung installiert und nicht mit dem späteren WordPress.org-Paket ausgeliefert.

| Direkte Abhängigkeit | Zweck | Lizenz laut Paketmetadaten | Auslieferung |
|---|---|---|---|
| `@wordpress/env` | reproduzierbare WordPress-Testinstanz | GPL-2.0-or-later | nur Entwicklung |
| `@wordpress/eslint-plugin` | WordPress-Regeln für JavaScript | GPL-2.0-or-later | nur Entwicklung |
| `@wp-playground/cli` | isolierter Aktivierungs-Smoke-Test | GPL-2.0-or-later | nur Entwicklung |
| `ajv` | Prüfung der JSON-Schemas aus Block 1 | MIT | nur Entwicklung |
| `ajv-formats` | standardisierte Formatprüfung für Ajv | MIT | nur Entwicklung |
| `esbuild` | kleiner JavaScript-Produktionsbuild | MIT | nur Entwicklung |
| `eslint` | statische JavaScript-Prüfung | MIT | nur Entwicklung |
| `prettier` | Formatregeln des WordPress-ESLint-Pakets | MIT | nur Entwicklung |
| `typescript` | Peer-Abhängigkeit des WordPress-ESLint-Pakets | Apache-2.0 | nur Entwicklung |
| `dealerdirect/phpcodesniffer-composer-installer` | Registrierung der PHPCS-Regelwerke | MIT | nur Entwicklung |
| `phpstan/phpstan` | statische PHP-Analyse | MIT | nur Entwicklung |
| `phpunit/phpunit` | PHP-Unit-Tests | BSD-3-Clause | nur Entwicklung |
| `szepeviktor/phpstan-wordpress` | WordPress-Typinformationen für PHPStan | MIT | nur Entwicklung |
| `wp-coding-standards/wpcs` | WordPress Coding Standards für PHPCS | MIT | nur Entwicklung |

Die vollständigen transitiven Versionen stehen in `composer.lock` und `package-lock.json`. `composer licenses` zeigt die Composer-Lizenzmetadaten. `npm run check:licenses` kontrolliert die Lizenzen aller direkten npm-Entwicklungsabhängigkeiten und bricht ab, sobald eine npm-Laufzeitabhängigkeit ohne gesonderte Release-Prüfung eingetragen wird.

`package.json` überschreibt die indirekte Playground-Abhängigkeit `qs` mit Version 6.16.0. Die von `@wp-playground/cli` aufgelöste ältere Version besitzt bekannte Denial-of-Service-Probleme; 6.16.0 schließt diese Lücke bei unveränderter öffentlicher Schnittstelle. React und React DOM werden für die reine Werkzeugkette einheitlich auf 18.3.1 aufgelöst, weil ein indirektes WordPress-Paket noch keine React-19-kompatible Peer-Abhängigkeit seines Memo-Helfers besitzt.

GitHub-Actions werden nur in der Build-Infrastruktur ausgeführt und nicht in das Plugin übernommen. Dependabot überwacht Composer-, npm- und Actions-Versionen wöchentlich.

Die Node-Untergrenze folgt den tatsächlich aufgelösten WordPress-Werkzeugen: einzelne transitive Pakete benötigen mindestens Node 20.19 beziehungsweise Node 22.13. Dadurch reicht eine ältere Node-20-Installation trotz der weiter gefassten Metadaten einzelner direkter Pakete nicht für einen reproduzierbaren Build.

Das Sammelpaket `@wordpress/scripts` bleibt in Block 2 außen vor. Der vollständige npm-Audit seiner zum Prüfzeitpunkt aktuellen Version zog über in diesem Projekt ungenutzte Werkzeuge mehrere High-Severity-Befunde ein. Die hier benötigten offiziellen WordPress-Regeln werden direkt über `@wordpress/eslint-plugin` eingebunden; Build und Vertragsprüfung bleiben kleine, gesondert fixierte Werkzeuge.
