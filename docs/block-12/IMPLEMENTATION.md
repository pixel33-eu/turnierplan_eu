# Block 12 – Umsetzung und Abnahme

Stand: 7. Oktober 2026. Status: abgeschlossen.

## Presets und zentrale Auflösung

Wiederverwendbare Einbettungen werden im nicht öffentlich abfragbaren Custom Post Type `tpeu_embed` gespeichert. Die vollständige V1-Konfiguration liegt als einzelnes, versioniertes Objekt in `_tpeu_config`. `EmbedConfig` normalisiert und validiert jeden Schreib- und Leseweg. Der öffentliche Resolver akzeptiert ausschließlich die exakt angeforderte ID, den erwarteten Post Type, Status `publish` und eine gültige Konfiguration. Er sucht niemals nach einem Ersatz-Preset.

Shortcode und dynamischer Block verwenden denselben Resolver und anschließend denselben `EmbedRenderer` wie Inline-Einbettungen:

```text
[turnierplan preset="87"]
```

Eine Änderung am veröffentlichten Preset wirkt dadurch bei jeder neuen Seitenausgabe an allen Verwendungsstellen. Fehlende, gelöschte, unveröffentlichte oder ungültige Presets zeigen den lokalen sicheren Fehlerzustand und laden weder Iframe noch Frontend-Assets.

## Rollen und Rechte

Die Rechte sind eigene WordPress-Capabilities und werden versioniert installiert:

| Aktion | Autor | Redakteur | Administrator |
|---|---:|---:|---:|
| Presets verwenden und eigene erstellen | ja | ja | ja |
| Eigene Presets veröffentlichen/aktualisieren | ja | ja | ja |
| Presets anderer Benutzer bearbeiten | nein | ja | ja |
| Metadaten für ein bearbeitbares Preset aktualisieren | ja | ja | ja |
| Plugin-Einstellungen ändern | nein | nein | ja |

Aktivierung und das kleine Laufzeit-Upgrade prüfen die Capability-Version idempotent. Einstellungen verwenden `tpeu_manage_settings`; das Bearbeiten fremder Presets bleibt von den objektspezifischen WordPress-Prüfungen abhängig.

## REST-Schutz

Der Block-Selector verwendet ausschließlich:

```text
GET /wp-json/turnierplan-eu/v1/presets
GET /wp-json/turnierplan-eu/v1/presets/{id}
```

Beide Routen verlangen `tpeu_use_embeds` und einen gültigen `X-WP-Nonce`. Sie liefern nur veröffentlichte, validierte Presets sowie ID, Titel, Konfiguration und Shortcode. Autoren- oder andere Benutzerdaten fehlen bewusst. Auch die von `show_in_rest` erzeugten Core-Routen `/wp/v2/turnierplan-embeds` sind über einen vorgeschalteten Capability- und Nonce-Check geschützt; dadurch macht ein veröffentlichter Status die Sammlung nicht öffentlich.

Die Metadaten-Aktualisierung eines Presets verlangt zusätzlich `tpeu_refresh_embeds` und `edit_post` für genau dieses Objekt. Das Konfigurations-Meta besitzt eine vollständige REST-Schema-Allowlist und eine objektspezifische Schreibberechtigung.

## Verwaltungsoberfläche

Der eigene Preset-Editor bietet:

- Turnierreferenz, Ansicht, Sprache, Theme, Dichte, Akzentfarbe und Höhen;
- die wichtigsten Sichtbarkeits- und Linkoptionen;
- die echte serverseitige Vorschau über den dynamischen Block;
- validiertes Speichern mit Rückstufung auf Entwurf bei ungültiger Konfiguration;
- einen kopierbaren kurzen Preset-Shortcode;
- eine Nonce- und Capability-geschützte Duplikation als neuer Entwurf.

Die Ansicht verwendet zwei Spalten und fällt unter 960 Pixel auf eine Spalte zurück. Rohes Custom-Meta bleibt aus der Oberfläche entfernt, obwohl `custom-fields` für die registrierte REST-Metadatenunterstützung aktiviert ist.

## Gutenberg-Block

Das Inspector-Panel lädt die geschützte Preset-Liste. Im Preset-Modus speichert der Block die Preset-ID und rendert diese serverseitig. Beim Wechsel zurück auf Inline werden die aktuellen Werte des ausgewählten Presets genau einmal in das Blockattribut kopiert; spätere Preset-Änderungen beeinflussen diese Inline-Kopie nicht mehr. Eine nicht mehr vorhandene Auswahl bleibt als exakte ID erhalten und wird nicht auf das erste verfügbare Preset umgebogen.

## Abnahme

Der WordPress-6.5-/PHP-8.3-Smoke-Test prüft:

- Gastablehnung für eigene und Core-REST-Listen;
- Nonce-Pflicht für einen Administrator;
- die Rollenmatrix von Autor, Redakteur und Administrator;
- eine Datenantwort ohne Benutzerinformationen;
- identische Preset-Auflösung in Shortcode und Block;
- die zentrale Wirkung einer Konfigurationsänderung;
- Ausschluss von Entwürfen aus der Liste;
- sicheren Fallback für Entwurf und unbekannte ID.

Zusätzlich laufen WPCS, PHPStan, 81 PHP-Unit-Tests, JavaScript-Lint, Vertrags- und Frontendtests, Build und Lizenzprüfung. Die visuelle Tastatur-, Zoom- und Mehrsprachenabnahme folgt gesammelt in Block 13.

Block 12 verändert keine Datei unter `D:\2025\Turnierplan.eu`.

## Offizielle Grundlagen

- [`register_post_type()` und eigene Capabilities](https://developer.wordpress.org/reference/functions/register_post_type/)
- [`register_meta()` und REST-Schema](https://developer.wordpress.org/reference/functions/register_meta/)
- [REST-Unterstützung für eigene Inhaltstypen](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-rest-api-support-for-custom-content-types/)
- [Berechtigungsprüfung eigener REST-Routen](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
