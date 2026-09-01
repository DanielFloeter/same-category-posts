# Plan: Block-Optionen auf Widget-Parität bringen

Ziel: Der Gutenberg-Block `tiptip/same-posts-block` soll dieselben Einstellungen
anbieten — und dieselbe Ausgabe erzeugen — wie das klassische Widget
`samePosts\Widget` (`same-category-posts.php`).

Stand: 2026-09-01 · Branch `master`

---

## 1. Ausgangslage

### 1.1 Was der Block heute hat

`src/edit.js` rendert vier Panels:

| Panel | Controls | Bewertung |
|---|---|---|
| Title | `hideTitle`, `title`, `titleLink` | ✅ entspricht dem Widget |
| Filter | `groupBy`, `displayAsDropdown`, `showPostCounts`, `QueryControls` (`order`, `orderBy`, `categories`) | ❌ Reste aus dem Core-Block `core/archives` / `core/latest-posts` — keine Entsprechung im Widget |
| Post details | *(leer)* | ❌ |
| General | 3× `ToggleControl` | ❌ alle drei auf dasselbe Attribut verdrahtet |
| Footer | *(leer)* | — |

Es gibt kein Thumbnails-Panel.

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

## 2. Blocker: Die Optionen würden noch nichts bewirken

Bevor Controls ergänzt werden, muss die PHP-Seite tragfähig sein. Vier Punkte:

### 2.1 `render_same_posts_block()` ist ein Archives-Torso

`same-posts-block.php` stammt erkennbar aus `core/archives`: `wp_get_archives()`,
Dropdown-Zweig, „No archives to show." Am Ende baut es ein `$instance` aus genau
zwei Werten (`order` → `asc_sort_order`, `title`) und ruft **einmal**
`$widget->itemHTML()` auf — außerhalb jeder Loop. Ergebnis: ein einzelnes `<li>`
für den gerade globalen `$post`, keine Liste, kein Titel, kein `<ul>`.

Der halbe Rest der Funktion ist auskommentiert und referenziert eine Methode
`get_elements_HTML()`, die es in der Klasse nicht gibt.

→ Muss ersetzt werden, nicht erweitert.

### 2.2 `Widget::widget()` gibt aus, statt zurückzugeben

`widget($args, $instance)` (Zeile 414–750) mischt Query-Aufbau, Loop und
Ausgabe und schreibt per `echo`. Für den Block wird eine Funktion gebraucht,
die HTML **zurückgibt**.

→ Query-Aufbau + Loop in eine Methode `render_html($instance, $current_post_id)`
extrahieren, die einen String liefert. `widget()` wird zu einem dünnen Wrapper
(`echo $before_widget . $this->render_html(...) . $after_widget`), der
Block-Render-Callback nutzt dieselbe Methode. Ohne diesen Schritt entsteht
zwangsläufig doppelte, auseinanderdriftende Logik.

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

### 2.4 Editor-Vorschau hat keinen Post-Kontext

`widget()` steigt sofort aus, wenn nicht `is_single()` oder `is_archive()`.
Der `ServerSideRender`-Request aus dem Editor läuft aber gegen
`/wp/v2/block-renderer/...` ohne diesen Kontext — die Vorschau bliebe leer.

→ Im Block `usesContext: [ "postId" ]` deklarieren bzw. die Post-ID aus dem
Editor (`useSelect` auf `core/editor` → `getCurrentPostId()`) als Attribut
mitschicken, und in `render_html()` die ID als Parameter akzeptieren statt sie
aus dem Loop zu ziehen.

### 2.5 Kleinkram

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

---

## 3. Vorgehen in Phasen

Jede Phase endet mit `npm run build` + manuellem Test in WordPress
(Widget-Ausgabe unverändert, Block-Ausgabe wie erwartet) und einem eigenen Commit.

### Phase 0 — Aufräumen (kein Funktionszuwachs)

- Archives-Reste aus `src/edit.js` entfernen: `groupBy`, `displayAsDropdown`,
  `showPostCounts`, `QueryControls`.
- General-Panel: `disableCSS` und `disableFontStyles` an ihre eigenen Attribute binden.
- `block.json`: alle Attribute mit `type` und `default` versehen; tote Attribute
  (`categorySuggestions`, `selectCategories`, `groupBy`, `displayAsDropdown`,
  `showPostCounts`) entfernen; `renderCallback`-Zeile löschen.
- `same-posts-block.php`: auskommentierten Altcode entfernen.
- `titleLevel`-Rest in `edit.js` entfernen.

**Prüfen:** Block lässt sich einfügen, Editor-Konsole ohne Fehler, Widget unberührt.

### Phase 1 — Gemeinsamer Render-Kern (PHP)

- `Widget::render_html( array $instance, $current_post_id ): string` aus
  `widget()` extrahieren (Query-Aufbau, Loop, `separate_categories`-Zweig,
  Titel-Platzhalter `%cat%` / `%cat-all%`).
- `widget()` auf den Wrapper reduzieren.
- `block_attributes_to_instance()` in `same-posts-block.php` anlegen (siehe 2.3).
- `render_same_posts_block()` neu schreiben: Attribute → Instance →
  `render_html()` → `get_block_wrapper_attributes()`.
- Post-ID-Kontext für die Editor-Vorschau (siehe 2.4).

**Prüfen:** Widget-Ausgabe byte-gleich zu vorher (Seitenquelltext vergleichen);
Block zeigt mit den drei Title-Optionen bereits eine echte Liste, im Editor wie
im Frontend.

### Phase 2 — Panel „Filter"

Controls in `src/edit.js`, Attribute in `block.json`, Mapping in
`block_attributes_to_instance()`:

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
den einfachen Filter-Controls angehen.

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
| `same-category-posts.php` | `render_html()` extrahieren, `widget()` als Wrapper |
| `readme.txt` | Changelog |

---

## 5. Offene Fragen

1. **Widget-Zukunft:** Bleibt das klassische Widget dauerhaft gleichwertig, oder
   ist der Block sein Nachfolger? Das entscheidet, wie viel Aufwand die
   Rückwärtskompatibilität von `widget()` rechtfertigt.
2. **`include_tax` / `exclude_terms` im Editor:** REST-API mit mehreren Requests
   oder ein eigener Endpoint, der die fertige Struktur liefert?
3. **`isset()` vs. `! empty()`:** Mapping-Funktion (kleiner Eingriff, Widget
   bleibt unberührt) oder die Prüfungen im Kern begradigen (sauberer, testet
   sich aber auf dem Widget mit)?
4. **`separate_categories` im Block:** Der Zweig erzeugt mehrere Titel-Blöcke
   mit `$before_title` / `$after_title` — im Block gibt es diese Widget-Args
   nicht. Ersatz festlegen (z. B. feste `<h*>`-Ebene als Attribut).
