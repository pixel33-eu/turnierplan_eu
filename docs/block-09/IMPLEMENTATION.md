# Block 9 – Umsetzung und Abnahme

Stand: 21. September 2026. Status: abgeschlossen.

## Dienstfreigabe und Einstellungen

Unter **Einstellungen → Turnierplan.eu** steht eine eigene, mit der WordPress Settings API registrierte Seite bereit. Die Verbindung ist nach Installation und Aktivierung standardmäßig ausgeschaltet. Die Plugin-Aktivierung, das Öffnen der Einstellungsseite und leere spätere Einbettungen starten keinen Remote-Abruf.

Ein Administrator kann dort:

- die Nutzung des externen Dienstes für die jeweilige WordPress-Installation ausdrücklich freigeben oder widerrufen;
- den unveränderlichen Dienst `https://www.turnierplan.eu` sehen;
- Sprache, Farbschema und Dichte für neu erzeugte Einbettungen festlegen;
- die spätere Bereinigung bei einer Deinstallation vormerken;
- mit einer selbst eingegebenen Turnierreferenz bewusst einen Verbindungstest starten.

Der Verbindungstest ist durch `manage_options` und einen eigenen Nonce geschützt. Er verwendet höchstens zehn Sekunden Gesamtzeit. Ein Widerruf löscht alle vom Plugin exakt erfassten Metadaten-Transients. Gespeicherte Einbettungskonfigurationen bleiben erhalten. Der Renderer aus Block 10 muss denselben Freigabezustand vor jeder neuen Frameausgabe prüfen. Bereits in einem Seiten-, Reverse-Proxy- oder CDN-Cache gespeichertes HTML kann WordPress nicht allgemein entfernen; die Einstellungsseite weist deshalb ausdrücklich auf das zusätzliche Leeren dieser Caches hin.

## Fester Remote-Client

Der Metadaten-Client baut sein Ziel ausschließlich mit `ServiceConfiguration` und `EmbedUrlBuilder`. Weder Einstellungen noch REST-Parameter können Origin oder API-Pfad ersetzen. Anfangsziel und jede Weiterleitung müssen folgende Regeln erfüllen:

- HTTPS ohne Zugangsdaten, Port oder Fragment;
- exakter Host `www.turnierplan.eu`;
- ausschließlich der Pfad `/api/embed/v1/tournaments/{reference}/metadata`;
- ausschließlich der normalisierte Parameter `lang`;
- höchstens zwei manuell verfolgte Weiterleitungen.

Jeder einzelne Abruf verwendet `wp_safe_remote_get()` mit aktivierter WordPress-SSRF-Prüfung, aktiver TLS-Zertifikatsprüfung, deaktivierter automatischer Weiterleitung, leerer Cookie-Liste, `Accept: application/json`, einem unkritischen Plugin-User-Agent und einem Größenlimit von einem MiB. Alle Weiterleitungen teilen sich ein einziges Zeitbudget von fünf Sekunden im Editor beziehungsweise höchstens zehn Sekunden beim Verbindungstest.

Der Client prüft Status, Content-Type, Größe, JSON-Syntax und die erforderlichen Felder des V1-Metadatenschemas. Unbekannte zusätzliche Antwortfelder derselben Hauptversion bleiben gemäß V1-Vertrag kompatibel. ETags werden nur in begrenzter Länge und ohne Zeilenumbrüche übernommen. Transportfehler, Timeout, zu viele oder unsichere Weiterleitungen, ungültiges JSON, ungültiges Schema, falscher Content-Type sowie 304, 404, 429 und 503 werden in stabile interne Zustände übersetzt; fremde Fehlermeldungen gelangen nicht ungeprüft in die Oberfläche.

## Cache- und Fehlerpolitik

Cache-Schlüssel bestehen aus einem SHA-256-Ausschnitt über API-Version, normalisierte Referenz und Sprache. Freie Eingaben erscheinen nicht in Transient-Namen.

- Ein erfolgreicher Abruf liegt fünf Minuten als frischer Treffer vor.
- Die letzte geprüfte Erfolgsantwort bleibt bis zu 24 Stunden als Rückfall erhalten.
- Ein bestätigtes `404 tournament_not_found` liegt eine Minute negativ im Cache.
- 429, 503 und Transportfehler werden nicht dauerhaft als Fehler gespeichert.
- Ein ETag ermöglicht einen konditionalen Abruf; 304 erneuert die vorhandene geprüfte Antwort.
- Nur Timeout, Transportfehler und 503 dürfen im Editor auf die ausdrücklich als `stale` markierte letzte Erfolgsantwort zurückfallen.
- Ein bestätigtes 404 löscht frische und alte Erfolgsantworten sofort. Ein nicht mehr öffentliches Turnier wird daher nie durch alte Metadaten überdeckt.

Der Cache führt eine Liste seiner exakten Transient-Namen. Dadurch kann der Freigabewiderruf die eigenen Einträge entfernen, ohne fremde Transients über Präfixsuchen oder unbeschränkte Datenbankabfragen anzutasten.

## Geschützte REST-Routen

Die Editor-Verbindung registriert ausschließlich diese authentifizierten Routen:

```text
GET  /wp-json/turnierplan-eu/v1/metadata/{reference}
POST /wp-json/turnierplan-eu/v1/cache/refresh
```

Beide Routen verlangen `edit_posts`, einen gültigen `wp_rest`-Nonce und ein kurzes Limit pro angemeldetem Benutzer. Der allgemeine Metadatenabruf erlaubt 30, der manuelle Refresh 10 Anfragen pro Minute. Enthält ein Refresh eine positive `preset_id`, wird zusätzlich `edit_post` für genau diesen Beitrag geprüft. Es gibt keine `nopriv`-Route.

Referenz und Sprache durchlaufen vor Cache oder Remote-Aufruf das zentrale Modell aus Block 8. Erfolgsantworten enthalten geprüfte Metadaten sowie `remote`, `cache` oder `stale` als Quelle. Fehler werden als stabile `WP_Error`-Codes mit verständlichem lokalem Text und passendem HTTP-Status ausgegeben.

## Datenschutz

Das Plugin schlägt über `wp_add_privacy_policy_content()` einen Text für die Datenschutzerklärung der Website vor. Die Beschreibung trennt zwei technisch verschiedene Datenwege:

1. Im Backend ruft der WordPress-Server kleine Metadatenantworten ab. Turnierplan.eu sieht dabei insbesondere die Server-IP und übliche HTTP-Daten, jedoch keine WordPress-Anmeldedaten oder Cookies.
2. Auf einer veröffentlichten Seite lädt der Browser des Besuchers den späteren Frame direkt von Turnierplan.eu. Dabei fallen insbesondere Besucher-IP, Browser-, Referrer- und Anfragedaten beim externen Dienst an.

Die Einstellungsseite verlinkt die Datenschutzbestimmungen und Nutzungsbedingungen von Turnierplan.eu.

## Dateien

- neu: `src/cache/` mit Store-Adapter und Metadaten-Cache
- neu: `src/remote/` mit HTTP-Adapter, Zielrichtlinie, Schema-Validator, Client und Gateway
- neu: `src/rest/` mit geschütztem Controller und Benutzerlimit
- neu: `src/settings/` mit Option, Einstellungsseite, Freigabevertrag und Datenschutztext
- erweitert: `src/class-plugin.php` für die Laufzeitregistrierung
- erweitert: `tools/smoke-wordpress.mjs` für Einstellungsseite und Zugriffsschutz
- neu: drei Unit-Testdateien für Client, Cache/Gateway und Einstellungen

Block 9 verändert keine Datei unter `D:\2025\Turnierplan.eu`. Der Website-Endpunkt aus Block 4 wird ausschließlich über seinen bestehenden öffentlichen Vertrag verwendet.

## Abnahme

Die isolierten Tests prüfen unter anderem feste Ziel-URLs, sichere Request-Argumente, fremde und private Redirects, gültige Redirects, ETags, Größenlimit, JSON- und Schemaprüfung, 304, 404, 429, 503, Transportfehler, Freigabe vor dem ersten Request, Cachetreffer, den begrenzten veralteten Rückfall und das Entfernen alter Erfolge nach einem 404.

Der WordPress-6.5-/PHP-8.3-Smoke-Test aktiviert das Plugin, rendert die Einstellungsseite und weist sowohl einen Gast als auch einen angemeldeten Administrator ohne REST-Nonce am Metadaten-Proxy ab. Die vollständigen PHP-, JavaScript-, Vertrags-, Build-, Lizenz- und WordPress-Prüfungen bleiben Teil der Abnahme.
