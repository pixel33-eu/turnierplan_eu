# Block 15: Dokumentation, Release und WordPress.org

Stand: 10. Oktober 2026

## Status

Block 15 ist umgesetzt und abgenommen. Version 1.0.1 besitzt ein vollständiges WordPress.org-Readme, einen privaten Sicherheitskontakt, eine dokumentierte Buildkette, ein reproduzierbares Release-ZIP, einen frischen Installationstest aus genau diesem ZIP und einen automatisierten GitHub-Releaseweg. Die externe Einreichung und Freigabe bei WordPress.org wird separat verfolgt und erst nach ihrer tatsächlichen Durchführung als erfolgt bezeichnet.

Der in Block 14 dokumentierte manuelle Browsernachweis für fehlende ausgehende Cookie-Anfrageheader und ausdrücklich blockierte Drittanbieter-Cookies wurde am 10. Oktober 2026 abgeschlossen.

## Release-Paket

npm run release:build erzeugt:

- dist/turnierplan-eu-1.0.1.zip;
- die SHA-256-Datei dist/turnierplan-eu-1.0.1.zip.sha256;
- ein Manifest mit Version, Git-Commit, Zeitquelle, Prüfsumme und vollständiger Dateiliste.

Das ZIP enthält genau das Stammverzeichnis turnierplan-eu/. Die Paketliste ist positiv definiert und enthält nur die Laufzeitdateien aus assets/css, blocks, build, languages, src sowie LICENSE, readme.txt, turnierplan-eu.php und uninstall.php. Tests, Werkzeuge, Entwicklungsabhängigkeiten, Dokumentationsquellen, Umgebungsdateien und Referenzmaterial gelangen nicht in das Plugin-Paket.

Alle Dateinamen werden stabil sortiert. Zeitstempel stammen aus SOURCE_DATE_EPOCH oder aus dem Commitzeitpunkt. Kompression, Dateirechte und ZIP-Metadaten sind festgelegt. npm run release:verify baut den Datenstrom zweimal und verlangt Bytegleichheit.

Für ein öffentliches Release prüft der zusätzliche Aufruf

    node tools/build-release.mjs --require-tag

einen sauberen Arbeitsbaum und den exakten Git-Tag v1.0.1. Das ordnet das Paket eindeutig dem lesbaren Quellstand und den eingecheckten Buildwerkzeugen zu.

Ein eigener Tag-Workflow führt vor jeder GitHub-Veröffentlichung `composer check`, `npm run check`, den strikten Tag-Build und die SHA-256-Prüfung aus. Erst danach erstellt er mit dem GitHub-eigenen Token das Release und hängt ZIP, Prüfsumme und Manifest an. Die öffentlichen Release-Notizen liegen versioniert unter `docs/block-15/RELEASE-NOTES-1.0.1.md`.

Die nachgelagerte Kontrolle des ersten GitHub-Artefakts deckte unterschiedliche Zeilenenden in `LICENSE` und dem deutschen PO-Katalog zwischen Windows und Linux auf. Version 1.0.1 normalisiert alle textuellen Paketeingaben vor Kompression. Dadurch erzeugen beide Betriebssysteme aus demselben Commit dieselben Archivbytes; Binärdateien bleiben unverändert.

## Versionsvertrag

VERSION ist die maßgebliche Releaseversion. npm run check:versions gleicht damit ab:

- Version im Plugin-Header;
- TPEU_VERSION;
- package.json;
- blocks/embed/block.json;
- Stable tag und aktuellen Changelog-Abschnitt in readme.txt.

Die Datenvertragsnummer schemaVersion und apiVersion des WordPress-Blocks bleiben davon unabhängig. Beide kennzeichnen eigene technische Verträge und werden nicht auf die Plugin-Version gesetzt.

## Installationstest

npm run test:wordpress:release erstellt das Paket, entpackt es in ein leeres temporäres Verzeichnis und bindet ausschließlich dieses Verzeichnis in eine frische WordPress-6.5-/PHP-8.3-Playground-Instanz ein. Der Test prüft:

- Aktivierung und gemeldete Version 1.0.1;
- gespeicherte Installationsversion;
- Registrierung von Gutenberg-Block und Shortcode;
- vorhandene kompilierte Editor-Dateien;
- standardmäßig deaktivierten externen Dienst;
- keine Iframe-Ausgabe vor der Dienstfreigabe;
- erreichbare Einstellungsseite ohne PHP-Fehler.

Damit prüft der Lauf die tatsächlich verpackten Dateien und nicht den Entwicklungsordner.

## Dokumentation und Lizenz

readme.txt dokumentiert Installation, Block, Shortcode, Presets, externe Verbindungen, übertragene technische Daten, Cachezeiten, Datenschutz, Deinstallation, Support und Changelog. Lesbare Quellen und Buildwerkzeuge sind über den öffentlichen GitHub-Link erreichbar.

Der offizielle WordPress.org-Readme-Validator meldete am 10. Oktober 2026 keine Fehler. Seine beiden Hinweise betreffen die für die erste Version optionalen Abschnitte „Upgrade Notice“ und „Donate link“. Alle im Readme verwendeten Turnierplan.eu-, GitHub- und WordPress.org-Links antworteten beim Releasecheck erfolgreich.

Plugin-Header, Readme, Composer, npm und LICENSE verwenden GPL-2.0-or-later beziehungsweise die gleichbedeutende Formulierung „GPLv2 or later“. Das Abhängigkeitsinventar aus Block 2 bleibt Bestandteil der Freigabeprüfung.

Die Umsetzung richtet sich nach:

- [WordPress.org Developer Information](https://wordpress.org/plugins/developers/);
- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/);
- [Plugin Readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/);
- [How Your Plugin Assets Work](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).

## Verzeichnisunterlagen

docs/block-15/WORDPRESS-ORG.md enthält den Ablauf für Konto, 2FA, Einreichung, Review, SVN und die Kontrollen nach einer tatsächlichen Veröffentlichung. wordpress-org-assets/ enthält die eigenen Banner in 772×250 und 1544×500 Pixeln, Icons in 128×128 und 256×256 Pixeln sowie vier 1200×900 Pixel große Funktionsabbildungen mit synthetischen Daten. Bildunterschriften und Alternativtexte sind dokumentiert. Die Verzeichnisbilder liegen bewusst außerhalb des Plugin-ZIPs und werden später im obersten SVN-Verzeichnis assets/ abgelegt.

npm run assets:directory erzeugt die Bilder lokal ohne fremde oder nachgeladene Bestandteile. npm run check:directory-assets validiert Dateinamen, PNG-Signatur und Maße.

## Abnahme

Die lokale Abnahme umfasst:

    composer check
    npm run check
    npm run test:responsive-browser

GitHub Actions prüft PHP 8.3/8.5, die Node-Kette und den offiziellen WordPress Plugin Check gegen das aus dem Release-ZIP entpackte Pluginverzeichnis. Eine WordPress.org-Zulassung, ein SVN-Upload und eine öffentliche Downloadprüfung werden erst nach ihrem tatsächlichen Abschluss als bestanden vermerkt.
