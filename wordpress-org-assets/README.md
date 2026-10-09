# WordPress.org-Verzeichnisbilder

Alle Verzeichnisbilder werden von Pixel33 Software für Turnierplan.eu erstellt und unter GPL-2.0-or-later bereitgestellt. Sie liegen außerhalb des Plugin-Pakets und werden nach einer WordPress.org-Zulassung in das oberste SVN-Verzeichnis /assets/ kopiert.

Vorhandene Dateien:

- banner-772x250.png und banner-1544x500.png: neutrale Turnierübersicht mit dem Namen Turnierplan.eu, ohne fremde Marken oder nicht belegte Aussagen.
- icon-128x128.png und icon-256x256.png: eigenes Turnierplan.eu-Symbol mit klarer Form bei kleinen Größen.
- screenshot-1.png: Gutenberg-Block mit Turniertabelle.
- screenshot-2.png: Gutenberg-Block mit Spielplan und Filtern.
- screenshot-3.png: Einstellungen mit ausgeschalteter Dienstfreigabe und den Hinweisen zum externen Dienst.
- screenshot-4.png: wiederverwendbares Preset und kopierbarer Shortcode.

Bildunterschriften aus dem Abschnitt == Screenshots == in readme.txt:

1. Configure and preview public tournament standings directly in the Gutenberg editor.
2. Display a responsive schedule and limit it with group, match, participant, or date filters.
3. Review the external-service information before an administrator enables Turnierplan.eu.
4. Reuse a validated preset or copy its canonical shortcode.

Alternativtexte:

1. “WordPress block editor showing a Turnierplan.eu standings preview and configuration controls.”
2. “Responsive Turnierplan.eu match schedule with filter controls in the WordPress block editor.”
3. “Turnierplan.eu settings page with the external service disabled.”
4. “Turnierplan.eu reusable preset editor with generated shortcode.”

Die acht PNG-Dateien werden mit npm run assets:directory vollständig lokal aus eigenen Formen, Plugin-Farben, WordPress-nahen Bedienelementen und klar erkennbaren synthetischen Turnierdaten erzeugt. Sie enthalten keine fremden Assets, echten Zugangsdaten, privaten Turniere, personenbezogenen Daten oder Produktions-Administrationsansichten. npm run check:directory-assets prüft Dateinamen, PNG-Signatur und exakte Maße.
