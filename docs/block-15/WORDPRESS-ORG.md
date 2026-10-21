# WordPress.org-Einreichung und Veröffentlichung

Diese Unterlage dokumentiert die Einreichung, Prüfung und erste Veröffentlichung auf WordPress.org.

## Aktueller Stand

Am 10. Oktober 2026 wurde `turnierplan-eu-1.0.1.zip` über das Konto mit der Kontaktadresse `info@pixel33.eu` eingereicht. Das Reviewteam bestätigte die Zulassung am 19. Oktober 2026. Am 21. Oktober 2026 wurde Version `1.0.1` als SVN-Revision `3757664` unter dem endgültigen Slug `turnierplan-eu` veröffentlicht.

Die öffentliche Seite ist unter [wordpress.org/plugins/turnierplan-eu/](https://wordpress.org/plugins/turnierplan-eu/) erreichbar. WordPress.org kündigt nach dem ersten Commit eine ungefähr sechsstündige Sicherheitsverzögerung an, bevor die Version an WordPress-Installationen ausgeliefert wird.

## Vor der Einreichung

1. WordPress.org-Konto mit regelmäßig gelesener Firmenadresse verwenden und Zwei-Faktor-Authentifizierung aktivieren.
2. plugins@wordpress.org im Mailfilter zulassen.
3. Block 14 vollständig abnehmen, einschließlich des dokumentierten DevTools-Nachweises.
4. CI auf dem vorgesehenen Releasecommit vollständig grün prüfen.
5. Sauberen Tag v1.0.1 erstellen und node tools/build-release.mjs --require-tag ausführen.
6. SHA-256-Prüfsumme mit dist/turnierplan-eu-1.0.1.zip.sha256 vergleichen.
7. Das ZIP nochmals über Plugins > Installieren > Plugin hochladen in einer frischen WordPress-Installation testen.
8. readme.txt mit dem offiziellen WordPress-Readme-Validator prüfen.
9. Lizenzinventar und alle Links nochmals kontrollieren.

## Einreichung

Unter [wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/) wurde die vollständige Datei `turnierplan-eu-1.0.1.zip` eingereicht. Die mitgesendete Zusatzinformation beschreibt das konkrete Verhalten:

> Embeds public Turnierplan.eu standings, schedules, and results in WordPress through a Gutenberg block, shortcode, or reusable preset. Administrators explicitly enable the external service before any connection is made.

Reviewfragen und Korrekturen werden im GitHub-Quellstand nachvollziehbar umgesetzt. Eine geänderte Releaseversion erhält einen neuen Tag und ein neu geprüftes Paket; das bereits eingereichte ZIP wird nicht stillschweigend durch einen anderen Quellstand ersetzt.

## SVN-Veröffentlichung

GitHub bleibt das Entwicklungsrepository. Das WordPress.org-SVN-Repository enthält nur freigegebene Ausgaben:

    /
      assets/
      tags/
        1.0.1/
      trunk/

1. Der Inhalt des geprüften ZIP wurde ohne dessen äußeren Ordner nach `trunk/` kopiert.
2. Derselbe Inhalt wurde nach `tags/1.0.1/` kopiert.
3. Die acht Verzeichnisbilder aus `wordpress-org-assets/` wurden nach `/assets/` kopiert; sie liegen weder in `trunk/assets` noch im Plugin-ZIP.
4. Für alle PNG-Dateien ist `svn:mime-type` auf `image/png` gesetzt.
5. Version, Stable Tag, Dateiliste und identische Dateihashes von `trunk` und `tags/1.0.1` wurden vor dem Commit geprüft.
6. Der Release wurde als Revision `3757664` mit dem Committext `Release Turnierplan.eu Embed tournaments 1.0.1` veröffentlicht.

SVN-Repository: [plugins.svn.wordpress.org/turnierplan-eu](https://plugins.svn.wordpress.org/turnierplan-eu/)

## Kontrolle nach Veröffentlichung

Am 21. Oktober 2026 wurden folgende Punkte kontrolliert:

- Die öffentliche Pluginseite zeigt Name, Version `1.0.1`, Beschreibung, Banner, Icon und vier Screenshots.
- Der öffentliche Download enthält dieselben 74 Laufzeitdateien mit identischen Dateihashes wie das freigegebene Release.
- Installation und Aktivierung aus dem öffentlichen WordPress.org-Download bestehen den Smoke-Test mit WordPress 6.5 und PHP 8.3.
- Einstellungen werden geladen und die REST-Berechtigungen bestehen den Test.
- `Stable tag` und Plugin-Header stehen auf `1.0.1`.
- Das SVN-Arbeitsverzeichnis ist nach Revision `3757664` sauber; `trunk` und `tags/1.0.1` enthalten jeweils 74 Dateien, `/assets` enthält acht PNG-Dateien.
- WordPress.org zeigt den automatischen Sicherheits-Rollout von ungefähr sechs Stunden an.

Damit ist die erste externe Veröffentlichung technisch nachgewiesen. Die tatsächliche Update-Auslieferung an WordPress-Installationen folgt nach Ablauf der von WordPress.org angezeigten Sicherheitsverzögerung.
