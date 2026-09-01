# Plan: Block-Optionen auf Widget-Parität bringen

Ziel: Der Gutenberg-Block `tiptip/same-posts-block` soll dieselben Einstellungen
anbieten — und dieselbe Ausgabe erzeugen — wie das klassische Widget
`samePosts\Widget` (`same-category-posts.php`).

Stand: 2026-09-01 · Branch `master`

**Fortschritt:** Phase 0 (Aufräumen), Phase 1 (gemeinsamer Render-Kern) und
die einfachen Controls aus Phase 2 sind erledigt. Als nächstes Phase 2b
(`include_tax` / `exclude_terms`) oder Phase 3 (Panel „Post details").

---

## 1. Ausgangslage

### 1.1 Was der Block hatte (Stand vor Phase 0)

`src/edit.js` rendert(e) vier Panels:

| Panel | Controls | Bewertung |
|---|---|---|
| Title | `hideTitle`, `title`, `titleLink` | ✅ entspricht dem Widget |
| Filter | `groupBy`, `displayAsDropdown`, `showPostCounts`, `QueryControls` (`order`, `orderBy`, `categories`) | ❌ Reste aus dem Core-Block `core/archives` / `core/latest-posts` — keine Entsprechung im Widget |
| Post details | *(leer)* | ❌ |
| General | 3× `ToggleControl` | ❌ alle drei auf dasselbe Attribut verdrahtet |
| Footer | *(leer)* | — |

Es gibt kein Thumbnails-Panel.

Nach Phase 0 sind davon nur das Title-Panel und ein korrekt verdrahtetes
General-Panel übrig; die leeren Panels sind entfernt und kommen in den Phasen
2 bis 4 mit Inhalt zurück.

### 1.2 Fehlende Optionen gegenüber `Widget::form()`

**Filter**

- `include_tax` — Taxonomie je Post-Type (Radio-Gruppe, dynamisch aus `get_taxonomies()`)
- `exclude_terms` — Mehrfachauswahl auszuschließender Terms je Taxonomie
- `exclude_no_children` — „Perform the exclusion without children"
- `exclude_children` — „Exclude children"
- `sort_by` — `date` | `title` | `comment_count` | `rand`
- `asc_sort_order` — „Reverse sort order (ascending)"
- `separate_categories` — „Separate terms (If more than one assigned)"
- `num_per_cate` — Max. Beiträge je getrennter Kategorie
- `num` — Anzahl Beiträge gesamt
- `exclude_current_post`
- `exclude_sticky_posts`

**Thumbnails** (Panel fehlt vollständig)

- `thumb` — „Show post thumbnail"
- `thumbTop` — Thumbnail über dem Titel
- `thumb_w`, `thumb_h` — Maße in Pixeln
- `use_css_cropping` — „CSS crop to requested size"

**Post details** (Panel leer)

- `excerpt`, `excerpt_length`, `excerpt_more_text`
- `comment_num`
- `date`, `use_wp_date_format`, `date_format`, `date_link`
- `author`

---

## 2. Blocker: Die Optionen würden noch nichts bewirken — **erledigt**

Bevor Controls ergänzt werden, muss die PHP-Seite tragfähig sein. Vier Punkte,
alle in Phase 1 abgearbeitet:

### 2.1 `render_same_posts_block()` ist ein Archives-Torso

`same-posts-block.php` stammt erkennbar aus `core/archives`: `wp_get_archives()`,
Dropdown-Zweig, „No archives to show." Am Ende baut es ein `$instance` aus genau
zwei Werten (`order` → `asc_sort_order`, `title`) und ruft **einmal**
`$widget->itemHTML()` auf — außerhalb jeder Loop. Ergebnis: ein einzelnes `<li>`
für den gerade globalen `$post`, keine Liste, kein Titel, kein `<ul>`.

Der halbe Rest der Funktion ist auskommentiert und referenziert eine Methode
`get_elements_HTML()`, die es in der Klasse nicht gibt.

→ Muss ersetzt werden, nicht erweitert. **Erledigt:** neu geschrieben, ruft
`Widget::render_html()` und setzt `get_block_wrapper_attributes()`.

### 2.2 `Widget::widget()` gibt aus, statt zurückzugeben

`widget($args, $instance)` (Zeile 414–750) mischt Query-Aufbau, Loop und
Ausgabe und schreibt per `echo`. Für den Block wird eine Funktion gebraucht,
die HTML **zurückgibt**.

→ Query-Aufbau + Loop in eine Methode `render_html($instance, $current_post_id)`
extrahieren, die einen String liefert. `widget()` wird zu einem dünnen Wrapper
(`echo $before_widget . $this->render_html(...) . $after_widget`), der
Block-Render-Callback nutzt dieselbe Methode. Ohne diesen Schritt entsteht
zwangsläufig doppelte, auseinanderdriftende Logik.

**Erledigt:** `render_html( $instance, $current_post_id, $before_title,
$after_title )` liefert den String, `build_html()` trägt den ausgelagerten
Rumpf und darf frei früh aussteigen, `render_html()` stellt danach immer den
globalen `$post` wieder her und hängt die Excerpt-Filter ab. `widget()` ist
der Wrapper und übergibt seine Sidebar-Args als Titel-Klammer.

### 2.3 `isset()`-Semantik: `false` ist nicht „aus"

`itemHTML()` und `show_thumb()` prüfen durchweg `isset($instance['excerpt'])`,
nicht den Wahrheitswert. Das Widget liefert für nicht angehakte Checkboxen
schlicht *keinen* Key.

Ein Block-Attribut vom Typ `boolean` ist aber immer gesetzt — `false` würde als
„eingeschaltet" gelesen. Die Attribut→Instance-Abbildung muss falsche Werte
daher **entfernen**, nicht durchreichen.

→ Zentrale Funktion `block_attributes_to_instance( array $attributes ): array`,
die camelCase→snake_case übersetzt und leere/false-Werte auslässt. Alternativ
alle `isset()`-Prüfungen auf `! empty()` umstellen — sauberer, aber ein
größerer Eingriff mit Regressionsrisiko fürs Widget.

**Erledigt:** die Mapping-Funktion wird jetzt vom Render-Callback benutzt; die
`isset()`-Prüfungen im Kern bleiben unangetastet.

### 2.4 Editor-Vorschau hat keinen Post-Kontext

`widget()` steigt sofort aus, wenn nicht `is_single()` oder `is_archive()`.
Der `ServerSideRender`-Request aus dem Editor läuft aber gegen
`/wp/v2/block-renderer/...` ohne diesen Kontext — die Vorschau bliebe leer.

→ Im Block `usesContext: [ "postId" ]` deklarieren bzw. die Post-ID aus dem
Editor (`useSelect` auf `core/editor` → `getCurrentPostId()`) als Attribut
mitschicken, und in `render_html()` die ID als Parameter akzeptieren statt sie
aus dem Loop zu ziehen.

**Erledigt:** `usesContext: [ "postId" ]` steht in `block.json`, und
`samePosts\current_post_id()` bedient die drei Fälle Archivseite (0),
Post-Template (`$block->context['postId']`) und Einzelseite bzw.
Editor-Vorschau (`get_the_ID()`). Der Editor braucht kein eigenes Attribut:
`ServerSideRender` schickt `post_id` mit, und die Block-Renderer-Route richtet
daraus den globalen `$post` ein.

### 2.5 Kleinkram

Die ersten vier Punkte sind in Phase 0 erledigt.

- `block.json`: die meisten Attribute haben weder `type` noch `default`
  (`"hideTitle": {}`). WordPress kann sie so nicht serialisieren/validieren.
- `block.json`: `"renderCallback": "samePosts\render_same_posts_block"` ist
  wirkungslos (kein gültiger Key; `\r` ist zudem ein Escape) — die Registrierung
  läuft korrekt über `register_block_type( __DIR__, [...] )` in `same-posts-block.php`.
  Zeile entfernen.
- `src/edit.js` liest `titleLevel` aus den Attributen; das Attribut existiert
  nicht → `titleTagName` ist `"hundefined"` (aktuell ungenutzt).
- `post_thumbnail_html()` liest `$this->instance['default_thunmbnail']` (Typo
  im Original) — Key existiert in `form()` nicht. Beim Refactoring stehen
  lassen oder bewusst mitziehen, nicht stillschweigend umbenennen.
  Steht noch (Phase 4).
- Noch offen, in Phase 1 nur festgehalten: `build_html()` liest auf
  Archivseiten `get_post_type($term->slag)` — `slag` statt `slug`, also immer
  `null`. Praktisch harmlos, weil `get_post_type(null)` auf den globalen Post
  zurückfällt; beim Aufräumen bewusst entscheiden, nicht nebenbei ändern.
- `src/save.js` ist toter Code (in `index.js` auskommentiert) und
  destrukturiert die in Phase 0 entfernten Attribute. Der Block wird
  serverseitig gerendert, braucht also kein `save`. Löschen oder behalten?
- Der `example`-Block in `src/index.js` setzt ein Attribut `values`, das es
  nicht gibt — die Vorschau in der Inserter-Liste bleibt daher leer.

---

## 3. Vorgehen in Phasen

Jede Phase endet mit `npm run build` + manuellem Test in WordPress
(Widget-Ausgabe unverändert, Block-Ausgabe wie erwartet) und einem eigenen Commit.

### Phase 0 — Aufräumen (kein Funktionszuwachs) — **erledigt**

- Archives-Reste aus `src/edit.js` entfernen: `groupBy`, `displayAsDropdown`,
  `showPostCounts`, `QueryControls`.
- General-Panel: `disableCSS` und `disableFontStyles` an ihre eigenen Attribute binden.
- `block.json`: alle Attribute mit `type` und `default` versehen; tote Attribute
  (`categorySuggestions`, `selectCategories`, `groupBy`, `displayAsDropdown`,
  `showPostCounts`) entfernen; `renderCallback`-Zeile löschen.
- `same-posts-block.php`: auskommentierten Altcode entfernen.
- `titleLevel`-Rest in `edit.js` entfernen.

**Prüfen:** Block lässt sich einfügen, Editor-Konsole ohne Fehler, Widget unberührt.

### Phase 1 — Gemeinsamer Render-Kern (PHP) — **erledigt**

- `Widget::render_html( array $instance, $current_post_id ): string` aus
  `widget()` extrahieren (Query-Aufbau, Loop, `separate_categories`-Zweig,
  Titel-Platzhalter `%cat%` / `%cat-all%`).
- `widget()` auf den Wrapper reduzieren.
- `block_attributes_to_instance()` — **erledigt**, liegt in
  `includes/block-attributes.php` samt Unit-Tests (siehe 2.3 und Abschnitt 5).
  In `same-posts-block.php` nur noch einbinden und aufrufen.
- `render_same_posts_block()` neu schreiben: Attribute → Instance →
  `render_html()` → `get_block_wrapper_attributes()`.
- Post-ID-Kontext für die Editor-Vorschau (siehe 2.4).

**Geprüft:** Die Widget-Ausgabe ist byte-gleich zu vorher. Nachgewiesen mit
einem Charakterisierungslauf gegen WordPress-Attrappen (46 Fälle: 23
Optionskombinationen × Einzel- und Archivseite) — alte und neue Fassung liefern
identisches HTML. Der Block liefert in allen fünf Kontexten (Einzelseite,
Archiv, Editor-Vorschau, Post-Template, kein Post) das erwartete Ergebnis.

Zwei Verhaltenskorrekturen sind dabei bewusst mitgegangen:

- `remove_filter( 'excerpt_length', … )` wurde ohne Priorität aufgerufen,
  `add_filter` aber mit `9999` — der Filter blieb also für den Rest des
  Requests hängen und hat auch fremde Excerpts gekürzt. Jetzt mit `9999`
  entfernt.
- Der globale `$post` wird auf allen Wegen wiederhergestellt, auch bei den
  frühen Ausstiegen.

Der Test in WordPress selbst (Widget in der Sidebar, Block im Editor) steht
noch aus.

### Phase 2 — Panel „Filter" — **einfache Controls erledigt**

Controls in `src/edit.js`, Attribute in `block.json`, Mapping in
`block_attributes_to_instance()`. Die ersten neun Zeilen der Tabelle stehen,
die letzten zwei sind Phase 2b:

| Attribut (Block) | Instance-Key | Control |
|---|---|---|
| `sortBy` | `sort_by` | `SelectControl` (Date / Title / Number of comments / Random) |
| `ascSortOrder` | `asc_sort_order` | `ToggleControl` |
| `num` | `num` | `NumberControl`, min 0 |
| `separateCategories` | `separate_categories` | `ToggleControl` |
| `numPerCate` | `num_per_cate` | `NumberControl`, nur sichtbar wenn `separateCategories` |
| `excludeCurrentPost` | `exclude_current_post` | `ToggleControl` |
| `excludeStickyPosts` | `exclude_sticky_posts` | `ToggleControl` |
| `excludeChildren` | `exclude_children` | `ToggleControl` |
| `excludeNoChildren` | `exclude_no_children` | `ToggleControl` |
| `includeTax` | `include_tax` | `RadioControl` je Post-Type |
| `excludeTerms` | `exclude_terms` | `FormTokenField` je Taxonomie |

`includeTax` / `excludeTerms` sind der aufwändigste Teil: Das Widget baut die
Liste serverseitig aus `get_taxonomies()` + `get_terms()`. Im Editor braucht es
dieselben Daten über die REST-API (`/wp/v2/taxonomies`, `/wp/v2/<taxonomy>`)
oder einen eigenen Endpoint. **Empfehlung:** in einem separaten Schritt nach
den einfachen Filter-Controls angehen — das ist Phase 2b und noch offen, siehe
Frage 2.

**Geprüft (einfache Controls):** Zahlenfelder sind `TextControl type="number"`
statt `NumberControl`, weil letzteres in `@wordpress/components` noch
experimentell ist; leeres Feld und Unsinn werden zu `0`, was im Widget „ohne
Limit" heißt. Jeder Schalter wurde gegen die Attrappen durchgerechnet: `num`
begrenzt, `exclude_current_post` und `exclude_sticky_posts` entfernen die
richtigen Beiträge, `num_per_cate` greift nur mit `separate_categories`,
`sort_by` nimmt nur Werte aus der Whitelist, und `asc_sort_order` dreht die
Reihenfolge — letzteres allerdings nur, weil `sortBy` per Default `date`
mitschickt: ohne `sort_by` im Instance erzwingt der Render-Kern `date`/`DESC`
und ignoriert die Richtung. Das ist Widget-Verhalten und bleibt so.

Ein Hinweis zu `excludeNoChildren`: Die Option wirkt nur auf ausgeschlossene
Terms, also erst mit Phase 2b. Sie ist trotzdem schon im Panel, mit
entsprechendem Hilfetext.

### Phase 3 — Panel „Post details"

`excerpt`, `excerpt_length`, `excerpt_more_text`, `comment_num`, `date`,
`use_wp_date_format`, `date_format`, `date_link`, `author`.

Abhängigkeiten wie im Widget nachbilden: `excerpt_length` / `excerpt_more_text`
nur bei aktivem `excerpt`; `date_format` nur wenn `use_wp_date_format` aus ist.

Bedient wird ausschließlich `itemHTML()` — sobald Phase 1 steht, ist das reine
UI-Arbeit plus Mapping.

### Phase 4 — Panel „Thumbnails"

`thumb`, `thumbTop`, `thumb_w`, `thumb_h`, `use_css_cropping`.
Breite/Höhe als Zahlenfelder-Paar, nur sichtbar bei aktivem `thumb`.

### Phase 5 — Abschluss

- Parität durchgehen: jede Option aus `form()` gegen den Block prüfen.
- `readme.txt` und Changelog ergänzen.
- Entscheiden, ob das Widget als deprecated markiert wird oder gleichwertig bleibt.

---

## 4. Betroffene Dateien

| Datei | Änderung |
|---|---|
| `block.json` | Attribute mit `type`/`default`, tote Attribute raus, `usesContext` |
| `src/edit.js` | Panels neu aufbauen (Hauptarbeit) |
| `same-posts-block.php` | Render-Callback neu, Attribut→Instance-Mapping |
| `same-category-posts.php` | `render_html()` + `build_html()` extrahiert, `widget()` ist Wrapper |
| `includes/block-attributes.php` | Attribut→Instance-Abbildung (WP-frei, vorhanden) |
| `tests/unit/` | Unit-Suite ohne WordPress (vorhanden) |
| `phpunit.xml.dist`, `composer.json` | Testkonfiguration (vorhanden) |
| `readme.txt` | Changelog |

---

## 5. Tests

### 5.1 Eine Suite

| Verzeichnis | Art | Braucht | Status |
|---|---|---|---|
| `tests/unit/` | Unit-Tests ohne WordPress | nur PHP + PHPUnit | lauffähig |

Die alten WordPress-Integrationstests sind gelöscht (Phase 2). Sie waren nicht
mehr gültig: `test-main.php` instanziierte `SameCategoryPosts`, die Klasse heißt
heute `samePosts\Widget`; `tests/bootstrap.php` verwies auf
`../../../../../tests/phpunit/`, was in einer normalen Installation nicht
existiert.

Damit gibt es für alles, was WordPress braucht — und dazu gehört der
Render-Kern —, kein automatisches Netz mehr. Die Byte-Gleichheit in Phase 1
wurde mit einem Lauf gegen WordPress-Attrappen nachgewiesen, der außerhalb des
Repos liegt. Offen: ob dieser Harness als `tests/characterization/`
aufgenommen wird oder eine echte Integrationssuite neu aufgesetzt wird.

### 5.2 Was ohne WordPress testbar ist — und was nicht

Nur Code, der keine WordPress-Funktionen aufruft. Daraus folgt eine
Arbeitsregel für die Phasen: **reine Logik wandert nach `includes/`**, alles mit
`get_posts()`, `get_the_date()` usw. bleibt in der Widget-Klasse und lässt sich
nur mit WordPress oder gegen Attrappen prüfen.

Der extrahierte Render-Kern `render_html()` aus Phase 1 ist damit ausdrücklich
*kein* Kandidat für die Unit-Suite — er lebt von WP-Query und Loop.

### 5.3 Vorhanden: `includes/block-attributes.php`

Erste ausgelagerte Einheit, entspricht Punkt 2.3 dieses Plans:
`samePosts\block_attributes_to_instance()` übersetzt Block-Attribute in ein
Widget-Instance-Array. Abgedeckt von `tests/unit/BlockAttributesTest.php`
(23 Tests, 119 Assertions):

- **`false`-Booleans werden ausgelassen** — der Kern der `isset()`-Falle.
  Der Test prüft für jedes Boolean-Attribut, dass `isset()` es hinterher als
  „aus" meldet. Gegenprobe durchgeführt: Werden Booleans wieder durchgereicht,
  schlagen genau dieser Test und der Vollkonfigurations-Test fehl.
- Unbekannte Attribute (`className`, Archives-Reste, Fremdes) werden verworfen
  und landen nicht im Instance.
- `thumbTop` behält seinen historischen camelCase-Key — `itemHTML()` liest genau
  diesen; ein „Aufräumen" nach `thumb_top` würde die Option still abschalten.
- Zahlenoptionen: `0` und negative Werte entfallen (`num` = 0 bedeutet im Widget
  ohnehin „ohne Limit"), Strings werden zu `int` gecastet, `true` wird abgewiesen.
- `sort_by` nur aus der Whitelist `date|title|comment_count|rand`.
- `include_tax` / `exclude_terms` werden strukturell bereinigt: leere Einträge
  raus, Term-IDs als positive Integer, dedupliziert.
- Die Style-Attribute des General-Panels tauchen nicht im Instance auf — sie
  steuern das Laden von Stylesheets, nicht die Query.

### 5.4 Ausführen

```
cd wp-content/plugins/same-category-posts
composer install
vendor/bin/phpunit
```

Ohne Composer genügt `phpunit.phar` im Plugin-Ordner: `php phpunit.phar`.
Die Konfiguration liegt in `phpunit.xml.dist` und umfasst nur `tests/unit`.

XAMPP-PHP liegt unter `/Applications/XAMPP/xamppfiles/bin/php`, falls das
System-PHP eine andere Version ist.

### 5.5 Kandidaten für die weiteren Phasen

- `same_category_posts_get_image_size()` ist bereits eine reine Funktion
  (Seitenverhältnis-Berechnung fürs CSS-Cropping) und ohne WordPress testbar,
  sobald sie aus `same-category-posts.php` in `includes/` gezogen ist — relevant
  für Phase 4.
- Die Datumsformat-Auswahl aus `itemHTML()` (`date_format` vs.
  `use_wp_date_format` vs. Default `j M Y`) ist reine Verzweigungslogik — Phase 3.
- Die Titel-Platzhalter `%cat%` / `%cat-all%` lassen sich als String-Ersetzung
  isolieren, wenn das Zusammenbauen der Kategorie-Links davon getrennt wird —
  Phase 1.

---

## 6. Offene Fragen

**Erledigt (Phase 1):** Frage 4 ist entschieden — der Block bringt mit
`samePosts\BLOCK_BEFORE_TITLE` / `BLOCK_AFTER_TITLE` ein festes
`<h2 class="widget-title">` mit. `render_html()` nimmt die Titel-Klammer als
Parameter, damit das Widget weiter seine Sidebar-Args durchgibt. Eine wählbare
Überschriftenebene kann später als Attribut nachgezogen werden.

1. **Widget-Zukunft:** Bleibt das klassische Widget dauerhaft gleichwertig, oder
   ist der Block sein Nachfolger? Das entscheidet, wie viel Aufwand die
   Rückwärtskompatibilität von `widget()` rechtfertigt.
2. **`include_tax` / `exclude_terms` im Editor:** REST-API mit mehreren Requests
   oder ein eigener Endpoint, der die fertige Struktur liefert?
3. **`isset()` vs. `! empty()`:** Mapping-Funktion (kleiner Eingriff, Widget
   bleibt unberührt) oder die Prüfungen im Kern begradigen (sauberer, testet
   sich aber auf dem Widget mit)?
4. ~~**`separate_categories` im Block:**~~ entschieden, siehe oben.
