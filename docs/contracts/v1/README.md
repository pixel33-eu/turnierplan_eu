# Turnierplan Embed V1 – öffentlicher Vertrag

Stand: 11. September 2026
Status: Vertrag für die Implementierung der Blöcke 3–12

Dieser Vertrag präzisiert die Produktspezifikation. Bei Widersprüchen innerhalb der Planungsdokumente ist für Version 1.0 diese Datei zusammen mit dem [Entscheidungsprotokoll](../../block-01/DECISIONS.md) maßgeblich. Der Vertrag beschreibt öffentliches Verhalten, keine heutige produktive Schnittstelle.

## Grundregeln

- Ursprung aller Produktionsendpunkte ist exakt `https://www.turnierplan.eu`.
- Der Pfad enthält die Hauptversion `v1`; Antwortobjekte tragen zusätzlich `schema_version: 1`.
- Alle Texte und Antworten sind UTF-8.
- Nur ausdrücklich veröffentlichte Turniere werden ausgeliefert. Nicht vorhanden, gelöscht und nicht veröffentlicht sind extern nicht unterscheidbar.
- Anfrageparameter verwenden `snake_case`, WordPress-Konfigurationen `camelCase`.
- Unbekannte Anfrageparameter liefern HTTP 400 `invalid_parameter`. Clients ignorieren unbekannte Antwortfelder derselben Hauptversion.
- Personenbezogene Verwaltungsdaten, interne Benutzer-/Kontokennungen, E-Mail-Adressen, private Notizen und interne Hashes sind ausgeschlossen.
- Die hier enthaltenen Mannschafts-, Personen- und Turniernamen sind erfunden.

## Referenzen und Normalisierung

Kanonische öffentliche Kennungen folgen diesen Mustern:

```text
Turnier:    ^trn_[0-9a-hjkmnp-tv-z]{26}$
Gruppe:     ^grp_[0-9a-hjkmnp-tv-z]{26}$
Teilnehmer: ^ptc_[0-9a-hjkmnp-tv-z]{26}$
```

Der Endpunkt akzeptiert für `{reference}` zusätzlich folgende Bestandsformen:

- eine positive numerische Turnier-ID ohne führendes Pluszeichen und mit höchstens 20 Ziffern;
- einen bestehenden Slug mit 1–100 Zeichen aus Kleinbuchstaben, Ziffern und einzelnen Bindestrichen;
- im WordPress-Eingabefeld eine vollständige HTTPS-URL auf `www.turnierplan.eu` in einer ausdrücklich unterstützten Form, zunächst `/t/{slug-or-ref}` oder `/live.php?id={numeric-id}`.

Die vollständige URL wird im WordPress-Client geparst und vor dem API-Aufruf auf die enthaltene Referenz reduziert. Das Plugin übernimmt keine frei angegebene URL. Hostnamen werden ohne Beachtung der Groß-/Kleinschreibung verglichen; ein abschließender Punkt, Benutzerinformationen, fremde Ports, Fragmente, doppelt kodierte Pfadsegmente und unbekannte Query-Parameter werden abgelehnt. Produktions-URLs müssen HTTPS verwenden.

Die API antwortet nach jeder erfolgreichen Aliasauflösung mit der kanonischen Kennung. Ein Alias ist dauerhaft einem Objekt zugeordnet und wird nie wiederverwendet. Gruppe und Teilnehmer werden in gespeicherten Filtern ausschließlich kanonisch referenziert.

## Metadaten

```http
GET /api/embed/v1/tournaments/{reference}/metadata?lang=de
Accept: application/json
```

`lang` ist optional. Erlaubt sind `auto` oder syntaktisch gültige Sprachcodes. Ein vom Turnier nicht unterstützter, aber syntaktisch gültiger Code fällt auf die in B01-D05 definierte Reihenfolge zurück; `response_language` nennt das Ergebnis. Ein syntaktisch ungültiger Wert liefert HTTP 400.

Eine erfolgreiche Antwort erfüllt [`metadata-response.schema.json`](schemas/metadata-response.schema.json). Sie enthält:

- kanonische Referenz, Titel, sportlichen Zustand, öffentliche URL und Aktualisierungszeit;
- Standard-, unterstützte und tatsächlich verwendete Sprache;
- IANA-Turnierzeitzone und optionale lokale Anfangs-/Enddaten;
- stabile Gruppen und Teilnehmer mit ihren Gruppenzuordnungen;
- verfügbare Ansichten, Filter und Darstellungsoptionen;
- Wertungseinheit, Branding-Richtlinie und empfohlenen Aktualisierungstakt.

Beispiel: [`metadata-success.json`](examples/valid/metadata-success.json).

### Eigentum und Auswertung der Metadatenfelder

| Feld | Erzeuger | Verwender und Regel |
| --- | --- | --- |
| `schema_version` | Dienst | WordPress akzeptiert nur unterstützte Hauptschemas |
| `response_language` | Dienst nach Sprachauflösung | Editor beschriftet Remote-Auswahldaten entsprechend |
| `tournament.ref` | öffentliche Referenzschicht | WordPress ersetzt Eingabe-Alias nach Erfolg durch diesen Speicherwert |
| `tournament.title` | Turnierdatenadapter | Editor/Vorschau; immer als nicht vertrauenswürdiger Text behandeln |
| `tournament.state` | Turnierdatenadapter | Frame steuert Aktualisierung, Editor zeigt Zustand |
| `tournament.default_language` | Turnierdatenadapter | Fallback gemäß B01-D05 |
| `tournament.timezone` | Turnierdatenadapter | Dienst wertet Datumsfilter aus, Editor erklärt lokale Grenzen |
| `tournament.starts_on`, `ends_on` | Turnierdatenadapter | Editor begrenzt Datumsauswahl; `null` bedeutet unbekannt |
| `tournament.public_url` | öffentliche Referenzschicht | Renderer erzeugt Fallback-Link nur nach Host-/Pfadprüfung |
| `tournament.updated_at` | Datenadapter | Editor zeigt Aktualität, Cache nutzt den Wert nicht als Autorisierung |
| `tournament.score_unit` | Datenadapter | Editor benennt Bilanzoption; Frame bestimmt konkrete Spalten |
| `tournament.refresh_interval_seconds` | Dienst | Frame übernimmt Takt; `null` bedeutet kein regelmäßiges Polling |
| `supported_languages` | Dienst | Editor bietet ausschließlich diese Frame-Sprachen an |
| `views[].id` | Dienst | Editor bietet ausschließlich vorhandene Ansichten an |
| `views[].filters`, `options` | Dienst | Editor blendet nicht anwendbare Controls aus; Frame bleibt autoritativ |
| `groups[]` | öffentliche Referenzschicht und Adapter | Editor speichert ID, zeigt escaptes Label; Frame prüft ID aktuell |
| `participants[]` | öffentliche Referenzschicht und Adapter | Editor speichert ID, zeigt escaptes Label; `group_ids` begrenzt Auswahl |
| `branding` | serverseitige Dienst-/Tarifregel | Editor zeigt Schalter nur bei `optional`; Frame setzt Regel durch |

Die unkomprimierte Antwort darf 1 MiB nicht überschreiten. Dienst und WordPress-Client brechen größere Antworten kontrolliert ab; das Plugin verarbeitet keine partielle Antwort.

### Metadaten-Header

```text
Content-Type: application/json; charset=utf-8
Cache-Control: public, max-age=60, stale-while-revalidate=300
ETag: "..."
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
```

Ein ETag bildet mindestens kanonische Referenz, Veröffentlichungsversion, Sprache und Antwortinhalt ab. Die Freigabeprüfung geschieht vor Auswertung von `If-None-Match`; ein widerrufenes Turnier darf daher keine 304-Antwort aus einem früher öffentlichen Zustand erzeugen.

## Frame

```http
GET /embed/v1/tournaments/{reference}?view=standings&lang=de&instance=550e8400-e29b-41d4-a716-446655440000&parent_origin=https%3A%2F%2Fverein.example
Accept: text/html
```

### Parameter

| Parameter | Typ und Grenzen | Vorgabe | Bedeutung |
| --- | --- | --- | --- |
| `view` | `standings` oder `matches` | `standings` | Ansicht |
| `lang` | `auto` oder Sprachcode | `auto` | Sprache nach B01-D05 |
| `group` | kanonische Gruppenkennung | – | Gruppenfilter |
| `participant` | kanonische Teilnehmerkennung | – | Teilnehmerfilter, nur Spiele |
| `match_from` | Integer 1–999999 | – | erste Spielnummer, inklusive |
| `match_to` | Integer 1–999999 | – | letzte Spielnummer, inklusive |
| `date_from` | `YYYY-MM-DD` | – | erstes lokales Turnierdatum, inklusive |
| `date_to` | `YYYY-MM-DD` | – | letztes lokales Turnierdatum, inklusive |
| `theme` | `auto`, `light`, `dark` | `auto` | Farbvariante |
| `density` | `comfortable`, `compact` | `comfortable` | Dichte |
| `accent` | sechs Hex-Zeichen ohne `#` | – | Akzentfarbe |
| `show` | kommagetrennte Allowlist | ansichtsspezifischer Standard | exakt sichtbare boolesche Elemente |
| `date` | `auto`, `show`, `hide` | `auto` | Datumsdarstellung der Spiele |
| `branding` | `show`, `hide` | `show` | Wunsch; Serverrichtlinie hat Vorrang |
| `links` | `new-tab`, `same-tab` | `new-tab` | Ziel öffentlicher Links |
| `instance` | UUID Version 4, Kleinschreibung | erforderlich | Zuordnung der Browsernachrichten |
| `parent_origin` | HTTPS-Origin ohne Pfad | erforderlich | Ziel-Origin für Browsernachrichten |

Das explizite `show=` ist eine leere Liste und blendet alle optionalen booleschen Elemente aus. Ein fehlender `show`-Parameter verwendet die Standardwerte. Leere und fehlende Listen sind daher verschieden.

Erlaubte `show`-Tokens:

| Ansicht | Tokens | Standardmäßig enthalten |
| --- | --- | --- |
| `standings` | `team_logos`, `played`, `wins_draws_losses`, `score_balance`, `points`, `group_navigation` | alle |
| `matches` | `match_number`, `time`, `field`, `group`, `round`, `referee`, `live_state`, `extra_time`, `penalty_result` | alle |

`date` ist separat, weil `auto` neben sichtbar und verborgen einen dritten Zustand besitzt. Tokenreihenfolge und doppelte Tokens verändern die Semantik nicht; der kanonische URL-Builder sortiert gemäß obiger Tabellenfolge und entfernt Duplikate. Ein Token der falschen Ansicht oder ein unbekanntes Token liefert HTTP 400.

### Filterregeln

- `match_from <= match_to` und `date_from <= date_to`; eine verletzte Relation liefert HTTP 400.
- Datum und Uhrzeit werden in `tournament.timezone` ausgewertet. Datumsgrenzen sind inklusive.
- Spiele ohne bestimmbares lokales Datum werden bei aktivem Datumsfilter ausgeschlossen.
- Gruppe, Teilnehmer, Spielnummer und Datum bilden eine Schnittmenge.
- Eine gültige Filterkombination ohne Treffer zeigt einen zugänglichen Leerzustand.
- Ein syntaktisch gültiger, aber nicht mehr vorhandener Gruppen-/Teilnehmerfilter wird ignoriert. Der Frame zeigt einen Hinweis und meldet `filter_ignored` an die Elternseite.
- `participant`, Spielnummern und Datumsfilter sind für `standings` unzulässig und liefern HTTP 400.
- Ist die Ansicht für dieses Turnier nicht verfügbar, folgt HTTP 409 `view_unavailable`.

### Frame-Antwort und Live-Aktualisierung

Der Frame liefert ein vollständiges, eigenständiges HTML-Dokument. Tabelle und Spiele laden das gleiche Dokument bei `live` frühestens nach 30 Sekunden und bei `upcoming` frühestens nach 60 Sekunden konditional neu. In inaktiven Tabs pausiert der Timer. `completed` und `cancelled` pollen nicht regelmäßig.

Das Dokument startet keine Sitzung, setzt oder liest keine Cookies und verwendet weder Web Storage noch Tracking. Alle Laufzeit-Assets stammen versioniert von Turnierplan.eu. Der Frame enthält keine Anmeldung, Verwaltung, Werbung oder globale Seitennavigation.

Erforderliche beziehungsweise sinngemäß gleichwertige Antwortheader:

```text
Content-Type: text/html; charset=utf-8
Cache-Control: public, max-age=30
ETag: "..."
Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; form-action 'none'; object-src 'none'; frame-ancestors https:
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()
Referrer-Policy: strict-origin-when-cross-origin
X-Content-Type-Options: nosniff
```

Bildquellen werden angepasst, falls freigegebene Logos von einem getrennten eigenen Asset-Host ausgeliefert werden. Dieser Host wird dann explizit in Vertrag, CSP und Datenschutzangaben aufgenommen. Der Frame sendet keinen `X-Frame-Options`-Header, der die vorgesehene Einbettung blockiert. Lokale HTTP-Entwicklung verwendet eine ausdrücklich begrenzte Entwicklungsheader-Konfiguration; Produktion bleibt bei HTTPS.

## Fehlervertrag

JSON-Endpunkte antworten bei Fehlern mit [`error-response.schema.json`](schemas/error-response.schema.json). Der Frame zeigt bei einem erreichten Serverfehler eine kleine, übersetzte HTML-Fehleransicht und verwendet denselben stabilen Fehlercode intern für seine Statusmeldung.

| HTTP | Code | Verwendung |
| --- | --- | --- |
| 400 | `invalid_reference` | Referenz ist syntaktisch ungültig |
| 400 | `invalid_parameter` | Querywert, Kombination oder unbekannter Parameter ist ungültig |
| 404 | `tournament_not_found` | fehlt, gelöscht oder nicht veröffentlicht |
| 409 | `view_unavailable` | gültige Ansicht ist für das Turnier nicht vorhanden |
| 429 | `rate_limited` | Abruflimit erreicht; `Retry-After` mitsenden |
| 503 | `temporarily_unavailable` | temporäre Dienststörung; optional `Retry-After` |

Antworten enthalten keine Exceptiontexte, SQL-Informationen, Pfade oder Unterscheidungsmerkmale verborgener Turniere. `request_id` ist eine zufällige Supportkennung ohne eingebettete Benutzer-, Turnier- oder Serverdaten. WordPress zeigt eigene übersetzte Texte anhand des Codes und übernimmt `message` nicht ungeprüft.

## Browserprotokoll

Frame und Elternseite verwenden den Umschlag aus [`embed-message.schema.json`](schemas/embed-message.schema.json):

```json
{
  "type": "turnierplan.eu/embed",
  "version": 1,
  "instance": "550e8400-e29b-41d4-a716-446655440000",
  "event": "resize",
  "payload": {
    "height": 684
  }
}
```

Der Empfänger akzeptiert eine Nachricht nur, wenn:

1. `event.origin` exakt `https://www.turnierplan.eu` ist;
2. `event.source` exakt dem `contentWindow` des zugeordneten Iframes entspricht;
3. Typ, Version und Instanz exakt übereinstimmen;
4. die Nutzlast zum Ereignis passt;
5. eine Höhe eine endliche Ganzzahl von 160 bis 8000 ist.

Der Frame sendet ausschließlich an die geprüfte `parent_origin`, niemals absichtlich an `*`. `parent_origin` ist nur ein Nachrichten-Ziel und gewährt keinen Zugriff. Pro Frame wird ein Listener verwaltet und beim Entfernen bereinigt.

Ereignisse:

- `ready`: Das Frame-Skript und ein zugänglicher Inhaltszustand sind bereit. Enthält Ansicht, aufgelöste Sprache, erste Höhe und eventuelle Warnungen.
- `resize`: Der Inhalt benötigt eine andere Höhe.
- `status`: Meldet `loading`, `ready`, `empty`, `stale` oder `error` sowie gegebenenfalls einen stabilen Code. Die Elternseite lokalisiert den Text selbst.

| Nachrichtenfeld | Erzeuger | Empfängerregel |
| --- | --- | --- |
| `type` | Frame, Konstante | muss exakt `turnierplan.eu/embed` sein |
| `version` | Frame | Elternseite akzeptiert ausschließlich `1` |
| `instance` | ursprünglich WordPress, vom Frame zurückgegeben | muss zum konkreten Iframe gehören |
| `event` | Frame | bestimmt das erlaubte Schema von `payload` |
| `payload.height` | Frame-Messung | ganzzahlig begrenzen; nur zugeordneten Frame ändern |
| `payload.view` | Frame | muss der angeforderten Ansicht entsprechen |
| `payload.resolved_language` | Frame | Statusanzeige, keine ungeprüfte HTML-Ausgabe |
| `payload.warnings` | Frame | Elternseite lokalisiert bekannte Codes und ignoriert unbekannte |
| `payload.state`, `code` | Frame | Elternseite lokalisiert bekannte Zustände; kein Remote-Text enthalten |

Der Elterncode startet seine Bereitschaftsfrist erst, wenn der lazy geladene Frame tatsächlich angefordert wird. Fehlt `ready`, zeigt er einen vorsichtigen Ladehinweis und den immer vorhandenen öffentlichen Fallback-Link; er behauptet keinen bestimmten HTTP-Fehler, den er wegen der Cross-Origin-Grenze nicht feststellen kann.

## WordPress-Konfiguration

Eine Inline-Konfiguration erfüllt [`embed-config.schema.json`](schemas/embed-config.schema.json). Die JSON-Schema-Datei beschreibt Feldtypen und Einzelgrenzen. Diese drei Relationen werden zusätzlich zentral in PHP und JavaScript geprüft:

- `matchFrom <= matchTo`;
- `dateFrom <= dateTo`;
- `minHeight <= maxHeight`.

Beim Frontend-Rendern validiert WordPress Syntax und gespeicherte Cacheinformationen, löst aber keine synchrone Remote-Anfrage aus. Der Frame prüft Gruppen- und Teilnehmerexistenz gegen aktuelle Daten. Fehlt ein gecachter Turniertitel, lautet der lokalisierte Iframe-Titel sinngemäß „Turniertabelle für {Referenz}“ oder „Spielplan für {Referenz}“.

### Feldzuordnung

`–` bedeutet, dass der Wert in dieser Schicht nicht vorkommt. Der Shortcode `preset` ist mit allen Inline-Attributen gegenseitig exklusiv.

| Konfiguration | Typ / Standard | Shortcode | Frame | Gültigkeit und Auswertung |
| --- | --- | --- | --- | --- |
| `schemaVersion` | Integer / `1` | – | – | WordPress speichert und migriert |
| `tournamentRef` | String / erforderlich | `tournament` | Pfad `{reference}` | WP normalisiert; Dienst löst Alias und liefert kanonische Referenz |
| `view` | Enum / `standings` | `view` | `view` | WP und Dienst; im kanonischen Shortcode immer enthalten |
| `language` | String / `auto` | `lang` | `lang` | WP löst wenn Metadaten vorhanden, Dienst abschließend |
| `group` | String/null / `null` | `group` | `group` | nur gemeldete ID; Dienst prüft Existenz aktuell |
| `participant` | String/null / `null` | `participant` | `participant` | nur `matches`; Dienst prüft Existenz aktuell |
| `matchFrom` | Integer/null / `null` | `match_from` | `match_from` | nur `matches`, inklusive |
| `matchTo` | Integer/null / `null` | `match_to` | `match_to` | nur `matches`, inklusive |
| `dateFrom` | Datum/null / `null` | `date_from` | `date_from` | nur `matches`, Turnierzeitzone, inklusive |
| `dateTo` | Datum/null / `null` | `date_to` | `date_to` | nur `matches`, Turnierzeitzone, inklusive |
| `theme` | Enum / `auto` | `theme` | `theme` | WP Allowlist, Frame wertet aus |
| `density` | Enum / `comfortable` | `density` | `density` | WP Allowlist, Frame wertet aus |
| `accentColor` | Hex/null / `null` | `accent` | `accent` ohne `#` | WP validiert/normalisiert, Frame als kontrollierte CSS-Variable |
| `showBranding` | Boolean / `true` | `branding` | `branding` | Dienstrichtlinie hat Vorrang |
| `openLinksInNewTab` | Boolean / `true` | `links` | `links` | Frame setzt Linkziel und sichere `rel`-Werte |
| `minHeight` | Integer 160–2000 / `240` | `min_height` | – | ausschließlich WordPress-Wrapper |
| `maxHeight` | Integer 300–8000 / `4000` | `max_height` | – | ausschließlich WordPress-Wrapper; darüber interner Scrollbereich |
| `showTeamLogos` | Boolean / `true` | `show:team_logos` | `show:team_logos` | nur `standings`, falls gemeldet |
| `showPlayed` | Boolean / `true` | `show:played` | `show:played` | nur `standings`, falls gemeldet |
| `showWinsDrawsLosses` | Boolean / `true` | `show:wins_draws_losses` | `show:wins_draws_losses` | nur `standings`, falls gemeldet |
| `showScoreBalance` | Boolean / `true` | `show:score_balance` | `show:score_balance` | nur `standings`, falls gemeldet |
| `showPoints` | Boolean / `true` | `show:points` | `show:points` | nur `standings`, falls gemeldet |
| `enableGroupNavigation` | Boolean / `true` | `show:group_navigation` | `show:group_navigation` | nur `standings`, falls mehrere Gruppen gemeldet |
| `showMatchNumber` | Boolean / `true` | `show:match_number` | `show:match_number` | nur `matches` |
| `showDate` | Enum / `auto` | `date` | `date` | nur `matches`; `auto` zeigt Datum bei mehreren lokalen Tagen |
| `showTime` | Boolean / `true` | `show:time` | `show:time` | nur `matches` |
| `showField` | Boolean / `true` | `show:field` | `show:field` | nur `matches`, falls gemeldet |
| `showGroup` | Boolean / `true` | `show:group` | `show:group` | nur `matches`, falls gemeldet |
| `showRound` | Boolean / `true` | `show:round` | `show:round` | nur `matches`, falls gemeldet |
| `showReferee` | Boolean / `true` | `show:referee` | `show:referee` | nur `matches`, falls gemeldet |
| `showLiveState` | Boolean / `true` | `show:live_state` | `show:live_state` | nur `matches`, falls gemeldet |
| `showExtraTime` | Boolean / `true` | `show:extra_time` | `show:extra_time` | nur `matches`, falls relevant |
| `showPenaltyResult` | Boolean / `true` | `show:penalty_result` | `show:penalty_result` | nur `matches`, falls relevant |

Der Shortcode fasst die mit `show:` bezeichneten Werte in einem einzigen kommagetrennten Attribut `show` zusammen. Ist jeder boolesche Wert auf seinem Standard, wird `show` ausgelassen. Weicht einer ab, schreibt der Generator die vollständige Liste der aktivierten Tokens dieser Ansicht. Dadurch bleiben explizit deaktivierte Werte nach späteren Defaultänderungen stabil.

Globale Plugin-Einstellungen sind Vorlagen für neu angelegte Konfigurationen. Sie verändern keine bereits gespeicherten Blöcke, Presets oder Shortcodes. Vertragliche Defaults bleiben innerhalb der Haupt-/Konfigurationsversion stabil.

### Konfigurationsmigration

Die vor dem ersten Plugin-Release verwendete kompakte, noch unversionierte Entwicklungsform wird einmalig als Schema 1 gelesen und um die festen V1-Vertragsdefaults ergänzt. Bereits vorhandene Werte haben Vorrang. Gespeichert und weitergegeben wird anschließend immer das vollständige V1-Objekt mit `schemaVersion: 1`.

Andere vorhandene Versionsnummern werden abgelehnt, statt ihre Bedeutung still umzudeuten. Zukünftige Migrationen erhalten deshalb einen ausdrücklich getesteten Schritt von der jeweiligen Ausgangsversion. Einrichtungsstandards werden ausschließlich beim bewussten Erzeugen einer neuen Konfiguration angewendet und sind kein Teil einer Migration.

## Beispiele

Gültige Beispiele unter [`examples/valid`](examples/valid):

- Metadaten-Erfolg und Fehlerantwort;
- Tabellen- und Spielekonfiguration;
- `ready`-, `resize`- und `status`-Nachrichten.

Bewusst ungültige Beispiele unter [`examples/invalid`](examples/invalid) sichern zentrale Negativfälle:

- ein nicht erlaubtes privates Metadatenfeld;
- eine unnormalisierte externe URL als gespeicherte Turnierreferenz;
- eine Browsernachricht mit einem unbekannten Typ.

Die Validierungsbefehle und erwarteten Ergebnisse stehen in [`VALIDATION.md`](VALIDATION.md).

## Kompatibilität und Änderungen

Schemas verwenden JSON Schema Draft 2020-12 für die eigenständigen öffentlichen Verträge. WordPress REST-Endpunktschemas verwenden später den von WordPress unterstützten JSON-Schema-Umfang und werden daraus bewusst abgeleitet; die Dateien werden nicht ungeprüft an `register_rest_route()` oder `register_post_meta()` übergeben.

Eine Änderung ist rückwärtskompatibel, wenn bestehende V1-Clients unverändert korrekt arbeiten, etwa bei einem neuen optionalen Antwortfeld oder einer neu angekündigten Fähigkeit. Neue Pflichtfelder, veränderte Bedeutung, entfernte Enumwerte und inkompatible Pfade benötigen V2. Jede Änderung ergänzt Contract-Tests und Beispiele vor der Server- oder Plugin-Implementierung.
