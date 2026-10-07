# Block 11 – Umsetzung und Abnahme

Stand: 7. Oktober 2026. Status: abgeschlossen.

## Dynamischer Gutenberg-Block

Der Block `turnierplan-eu/embed` ist mit einer kanonischen [`block.json`](../../blocks/embed/block.json) beschrieben und wird auf dem WordPress-`init`-Hook serverseitig über `register_block_type()` registriert. Dies entspricht der offiziellen WordPress-Empfehlung für einen einzelnen Block bei einer Mindestversion vor WordPress 6.7. Die öffentliche Ausgabe besitzt keine eigene JavaScript-Renderlogik: Der PHP-Callback validiert die gespeicherte Konfiguration erneut und übergibt sie an denselben `EmbedRenderer`, den bereits der Shortcode verwendet.

Der Inserter bietet zwei Variationen desselben Blocks:

- **Turniertabelle** mit `view: standings`;
- **Spielplan** mit `view: matches`.

Beide Varianten speichern ausschließlich `presetId`, den Initialisierungsmarker und das vollständige versionierte Konfigurationsobjekt. Ein leerer Block erzeugt im Frontend keine Ausgabe und lädt keine Assets. Preset-Auflösung folgt wie geplant in Block 12.

## Erster Ablauf und lokale Vorschau

Der leere Block zeigt direkt in der Arbeitsfläche:

- ein beschriftetes gemeinsames Feld für ID, Slug oder vollständige Turnierplan.eu-URL;
- die primäre Aktion **Turnier verbinden**;
- den Diensthinweis vor dem ersten Remote-Abruf;
- **Beispiel ansehen** mit einer rein lokalen Mustertabelle, die keinen Netzwerkaufruf auslöst;
- einen verständlichen Hinweis, falls der Administrator den Dienst noch nicht freigegeben hat.

Neue Blöcke übernehmen einmalig Sprache, Theme und Dichte aus den Einstellungen von Block 9. Eine gespeicherte Konfiguration mit nicht unterstützter Schemaversion wird nicht stillschweigend verwendet; der Editor zeigt eine konkrete Meldung und bietet ein bewusstes Zurücksetzen an.

## Geschützte Metadatenverbindung

Der bestehende authentifizierte Proxy besitzt zusätzlich:

```text
POST /wp-json/turnierplan-eu/v1/metadata/resolve
```

Die Route verlangt weiterhin `edit_posts`, einen gültigen REST-Nonce und das Benutzerlimit aus Block 9. Der Body akzeptiert eine Referenz oder dokumentierte Turnierplan.eu-URL. Die zentrale PHP-Klasse `TournamentReference` normalisiert sie, bevor Cache oder Remote-Client sie verwenden. Fremde Hosts und unsichere URL-Formen werden bereits als ungültiger REST-Parameter abgewiesen.

Der Editor verwendet `wp.apiFetch`. Vor einem neuen Abruf wird ein noch laufender Abruf über `AbortController` beendet; zusätzlich verwirft eine Sequenzkennung verspätete Antworten. Damit kann ein älteres Ergebnis eine neuere Referenz oder Sprache nicht überschreiben. Während einer Aktualisierung bleiben vorhandene Metadaten sichtbar. Ein veralteter Cachetreffer und ein Fehler nach vorherigem Erfolg werden klar gekennzeichnet.

## Metadatenabhängige Bedienung

Nach erfolgreicher Verbindung zeigt der Block Turniertitel, sportlichen Status, letzte Aktualisierung und den öffentlichen Link. Die vom Endpunkt gelieferten Fähigkeiten bestimmen die sichtbaren Felder:

- nur gemeldete Ansichten, Sprachen, Filter und Informationsschalter erscheinen;
- Gruppe und Teilnehmer sind direkt oberhalb der Vorschau erreichbar;
- Teilnehmer werden auf die ausgewählte Gruppe eingeschränkt;
- Spielnummern- und Datumsbereiche erscheinen nur für passende Spielplanfähigkeiten;
- Theme, Dichte und Akzentfarbe liegen im Panel **Darstellung**;
- Spalten- und Informationsschalter liegen in **Angezeigte Informationen**;
- Fallback-Höhen, Linkziel und optionales Branding liegen in **Erweitert**.

Beim Ansichtswechsel bleiben gemeinsame, weiterhin gültige Werte wie die Gruppe erhalten. Unpassende Spielplanfilter werden entfernt, bevor die serverseitige Vorschau sie validiert. Nicht mehr vorhandene Ansichten, Gruppen und Teilnehmer lösen jeweils eine eigene verständliche Warnung aus. Die Branding-Richtlinie des Metadatenvertrags überschreibt widersprüchliche lokale Werte deterministisch.

## Direkte Vorschau

Die echte Vorschau verwendet `ServerSideRender` und damit denselben PHP-Callback, dieselbe Normalisierung, denselben URL-Builder und denselben Renderer wie das veröffentlichte Frontend. Lokale Änderungen werden nach 250 Millisekunden an die Vorschau weitergereicht. Die drei Umschalter **Desktop**, **Tablet** und **Mobil** ändern nur die maximale Breite des Editor-Containers; sie speichern keine Frontendbreite.

WordPress stellt das Duplizieren über das Standardmenü des Blocks bereit. Da Referenz und gemeinsame Werte im Konfigurationsobjekt liegen, kann eine Kopie unmittelbar von Tabelle auf Spielplan umgeschaltet werden, ohne das Turnier erneut einzugeben.

## Fehler- und Statuszustände

Die Oberfläche unterscheidet:

- leere oder ungültige Referenz;
- fehlende Dienstfreigabe;
- nicht gefundenes oder nicht öffentliches Turnier;
- vorübergehend nicht erreichbaren Dienst, Timeout oder blockiertes HTTPS;
- veralteten Cachetreffer;
- beendetes, weiterhin öffentliches Turnier;
- nicht verfügbare Ansicht, Gruppe oder Teilnehmerkennung;
- nicht unterstützte Konfigurationsversion.

REST-Fehler verwenden nur die stabilen, lokal formulierten Meldungen aus Block 9. Remote-Inhalte werden von React als Text ausgegeben; die öffentliche Vorschau bleibt vollständig in der geprüften PHP-Ausgabe.

## Assets und Dateien

- neu: `blocks/embed/block.json` mit vollständigen Defaults und zwei Variationen
- neu: `src/block/class-embed-block.php` für Registrierung, Editor-Assets und PHP-Rendering
- neu: `assets/src/block-editor.jsx` für den vollständigen Editorablauf
- neu: `assets/src/block-editor-state.js` für Zustände, Abgleich und Abbruchsteuerung
- neu: `assets/css/block-editor.css` für responsive Editor- und Vorschauflächen
- neu: `tests/block-editor-state.test.mjs`
- erweitert: Build um ein eigenes Editor-Bundle und WordPress-Abhängigkeitsmanifest
- erweitert: geschützter Metadaten-Proxy um die serverseitige URL-Auflösung
- erweitert: WordPress-Smoke-Test um Block, Variationen, Resolver und dynamisches Rendering
- stabilisiert: Smoke-Test-Start unter Windows mit genau einem Wiederholungsversuch für den bekannten Playground-Dateifehler; fachliche Fehler werden nicht wiederholt

Editor-Skript und Editor-Styles hängen ausschließlich an den in `block.json` eingetragenen WordPress-Handles. Das Frontend lädt weiterhin nur die Assets aus Block 10 und nur dann, wenn der Renderer tatsächlich ein Embed ausgibt.

Block 11 verändert keine Datei unter `D:\2025\Turnierplan.eu`.

## Abnahme

Die JavaScript-Tests prüfen Leer-, Lade-, Erfolgs-, Veraltet-, Aktualisierungs- und Fehlerzustände, den Erhalt gemeinsamer Werte beim Ansichtswechsel, das Zurücksetzen unpassender Filter, Metadatenabgleich sowie Abbruch und Ignorieren konkurrierender Abrufe.

Der WordPress-6.5-/PHP-8.3-Smoke-Test prüft die serverseitige Registrierung aus `block.json`, beide Variationen, Editor-Handles, die geschützte POST-Auflösung für eine vollständige Turnierplan.eu-URL, die Ablehnung eines fremden Hosts sowie die dynamische Blockausgabe mit demselben Renderer. Ein leerer Block bleibt im Frontend leer. Die vollständigen PHP-, JavaScript-, Vertrags-, Build- und Lizenzprüfungen bleiben Bestandteil der Abnahme.

## Offizielle Grundlagen

- [Blockregistrierung und `block.json`](https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/)
- [`register_block_type()`](https://developer.wordpress.org/reference/functions/register_block_type/)
- [Dynamische Blöcke und `ServerSideRender`](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/)
- [`@wordpress/api-fetch` und Abbruch laufender Requests](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/)
