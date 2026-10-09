# WordPress.org-Einreichung und Veröffentlichung

Diese Unterlage bereitet die Veröffentlichung vor. Sie ist kein Nachweis, dass WordPress.org das Plugin bereits geprüft oder zugelassen hat.

## Vor der Einreichung

1. WordPress.org-Konto mit regelmäßig gelesener Firmenadresse verwenden und Zwei-Faktor-Authentifizierung aktivieren.
2. plugins@wordpress.org im Mailfilter zulassen.
3. Block 14 vollständig abnehmen, einschließlich des dokumentierten DevTools-Nachweises.
4. CI auf dem vorgesehenen Releasecommit vollständig grün prüfen.
5. Sauberen Tag v1.0.0 erstellen und node tools/build-release.mjs --require-tag ausführen.
6. SHA-256-Prüfsumme mit dist/turnierplan-eu-1.0.0.zip.sha256 vergleichen.
7. Das ZIP nochmals über Plugins > Installieren > Plugin hochladen in einer frischen WordPress-Installation testen.
8. readme.txt mit dem offiziellen WordPress-Readme-Validator prüfen.
9. Lizenzinventar und alle Links nochmals kontrollieren.

## Einreichung

Unter [wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/) wird die vollständige Datei turnierplan-eu-1.0.0.zip eingereicht. Die Kurzbeschreibung soll das konkrete Verhalten nennen:

> Embeds public Turnierplan.eu standings, schedules, and results in WordPress through a Gutenberg block, shortcode, or reusable preset. Administrators explicitly enable the external service before any connection is made.

Reviewfragen und Korrekturen werden im GitHub-Quellstand nachvollziehbar umgesetzt. Eine geänderte Releaseversion erhält einen neuen Tag und ein neu geprüftes Paket; das bereits eingereichte ZIP wird nicht stillschweigend durch einen anderen Quellstand ersetzt.

## Nach der Zulassung

WordPress.org stellt ein SVN-Repository bereit. GitHub bleibt das Entwicklungsrepository. SVN enthält nur freigegebene Ausgaben:

    /
      assets/
      tags/
        1.0.0/
      trunk/

1. Inhalt des geprüften ZIP ohne dessen äußeren Ordner nach trunk/ kopieren.
2. Denselben Inhalt nach tags/1.0.0/ kopieren.
3. Verzeichnisbilder aus wordpress-org-assets/ nach /assets/ kopieren; sie gehören weder in trunk/assets noch in das Plugin-ZIP.
4. Für PNG-Dateien svn:mime-type image/png setzen.
5. Vor dem Commit svn status, Version, Stable Tag und Dateiliste prüfen.
6. Einen einzelnen beschreibenden Releasecommit ausführen.

Die echten SVN-Befehle werden erst verwendet, wenn WordPress.org den endgültigen Slug und das Repository zugewiesen hat.

## Kontrolle nach Veröffentlichung

Nach der tatsächlichen Veröffentlichung werden erst dann als erledigt markiert:

- öffentliche Pluginseite zeigt Name, Version 1.0.0, Beschreibung und Screenshots korrekt;
- Download liefert dieselbe Laufzeitdateiliste und die erwartete Version;
- Installation und Aktivierung aus dem öffentlichen WordPress.org-Download funktionieren;
- Block, Shortcode, Preset, Einstellungen und Übersetzungen sind vorhanden;
- Updateanzeige und Stable Tag stimmen überein;
- Supportforum und Sicherheitskontakt sind erreichbar;
- Links zu Turnierplan.eu, Datenschutz, Bedingungen und Quellcode funktionieren.

Erst diese Kontrolle belegt die externe Veröffentlichung.
