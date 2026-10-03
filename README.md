# Bard Footnotes

Footnotes for Statamic's Bard field: a toolbar button, inline superscript references, and an
automatic source list. No second field, no typed `[1]` markers — the footnote lives in the text
itself, as a node.

- A **Footnote** button in Bard's toolbar opens a popover: a source (required) and a link
  (optional, `http`/`https` only) — or a pick of the sources already cited in the same field.
- The footnote renders inline as `<sup class="footnote-ref"><a href="#fn-1" id="fnref-1"
  aria-label="Footnote 1">1</a></sup>`, numbered by order of first occurrence.
- Citing the same source again reuses its number; the jump target `id="fnref-n"` is written once.
- The source list below the article carries the targets `id="fn-1"`, `id="fn-2"` … and a `↩` back
  link per source.
- Clicking a footnote in the CP opens the popover again — edit it or remove it.

Requires Statamic 6. On Statamic 5, use the [1.x branch](https://github.com/goldnead/statamic-bard-footnotes/tree/1.x)
(see [UPGRADE.md](UPGRADE.md)).

## Installation

```bash
composer require goldnead/statamic-bard-footnotes
php artisan vendor:publish --tag=statamic-bard-footnotes
```

The publish step copies the compiled control panel bundle into `public/vendor/statamic-bard-footnotes`.
No Node toolchain is needed — `dist/` ships with the package.

## The button

The button appears in a Bard field when its blueprint config lists it, like every core button:

```yaml
fields:
  -
    handle: content
    field:
      type: bard
      buttons:
        - h2
        - bold
        - footnote
```

Footnotes stay readable (and editable) in fields whose config does not list the button — only the
toolbar button is opt-in, the node itself always renders.

## In the CP

Place the cursor where the reference belongs and click **Footnote**. Fill in the source and,
optionally, a link; Apply inserts the footnote at the cursor. With text selected, the footnote is
inserted at the **end of the selection** and the selected text stays as it is (it is not used as
the source). The popover's select at the top offers the sources already cited in this field —
picking one fills the fields with its text and link. The editor shows the live superscript number;
hovering it reveals the source.

Clicking a footnote reopens the popover. Editing the source it already cites changes **every**
place citing it (the popover says "Used N times"). Picking a different source, or "New source",
re-points only this one footnote. A footnote with neither text nor link has no source: it gets no
number, no list entry and renders nothing.

### Sources under the field

As soon as a field cites a source, a compact list **Sources (N)** appears under the editor, inside
the field. It is where the sources of the text are kept in order:

- one row per source, in number order, with the same live number the text shows,
- the source text (a link source shows a small ↗ that opens it in a new tab),
- how often it is cited (`2×`),
- **Edit** (pencil) opens the panel "Edit Source n" for exactly this source: text and link,
  and Apply Source changes **every** place citing it. While the panel is open, those places are
  highlighted in the text. There is no source select here — the panel never re-points or merges.
- **Go to citation** (target) selects the first place in the text that cites the source and
  scrolls to it; with several places the button reads "Next citation" and each click moves on to
  the next one.

The list updates with every change in the editor and disappears when the last footnote goes. It
shows in every Bard field that holds footnotes, with or without the toolbar button. A read-only
field lists its sources without Edit. Footnotes are **removed in the text**, where they stand
(click the footnote, Remove Footnote) — there is no "remove" in the list, so nothing leaves the
text without the sentence around it in view.

Numbers are never stored. They are derived from the document: order of first occurrence, with the
same source keeping the same number. "Same source" means the same link (trimmed), or — without a
link — the same text (whitespace trimmed and collapsed, Unicode spaces such as NBSP included,
case-insensitive). PHP and the editor apply exactly the same rule.

## In Antlers

Render the article, then the source list:

```antlers
{{ content }}

{{ footnotes field="content" }}
```

The single tag renders the shipped `bard-footnotes::list` view: a "Sources" heading, an ordered
list, external links with `target="_blank" rel="noopener noreferrer"`, and a `↩` back link per
source. Text and url are always escaped. No footnotes, no output.

**Bard with sets** numbers across the whole field — a footnote before a set and one after it are
numbered in reading order. (A Bard field *nested inside* a set is its own document and numbers
separately, the same way Statamic augments it.)

**Tag pair** — loop the sources yourself:

```antlers
{{ footnotes field="content" }}
    <li id="fn-{{ number }}">{{ text | entities }}{{ if url }} — <a href="{{ url | entities }}">{{ url | entities }}</a>{{ /if }} <a href="#fnref-{{ number }}">↩</a></li>
{{ /footnotes }}
```

The tag reads the field's raw value from the template context, by handle — a `:content="content"`
binding would arrive as already-rendered HTML, too late to read the footnotes from. `field` is
therefore the handle of a Bard variable available where the tag is used (an entry's `content`, a
loop variable, …). If it is missing or not a Bard value, the tag renders nothing; with
`APP_DEBUG=true` it also writes one warning to the log naming the field. Each source
carries `number`, `text`, `url` (`null` unless `http(s)`). `no_results` and `total_results` behave
like in Statamic's collection tags.

Publish the view to make it your own:

```bash
php artisan vendor:publish --tag=bard-footnotes-views
```

## In PHP

For Blade, Inertia or anywhere outside Antlers:

```php
use Goldnead\BardFootnotes\Footnotes;

// list of ['number' => 1, 'text' => 'Smith, p. 12', 'url' => 'https://…']
$sources = Footnotes::sources($entry->content);

return Inertia::render('Article', [
    'content' => $entry->content, // rendered HTML: the augment pass numbers and links
    'sources' => $sources,
]);
```

`sources()` accepts the raw field value, a Statamic `Value` or a collection. The rendered HTML
needs nothing from you — the augment hook numbers every footnote of the field and the node turns
each into the superscript link.

### Rendering a Bard field in parts

The augment hook numbers whatever document it is given. If your app renders one Bard field in
parts — say one `Augmentor::convertToHtml()` call per stretch of text between two sets — the hook
runs once per part and every part would start again at 1. Number the whole document first, then
split it:

```php
use Goldnead\BardFootnotes\Footnotes;
use Statamic\Fieldtypes\Bard\Augmentor;

$numbered = Footnotes::number($entry->content->raw()); // the whole field, once

foreach ($stretchesBetweenSets($numbered) as $part) {
    $html .= (new Augmentor($bardFieldtype))->convertToHtml($part);
}
```

`Footnotes::number()` is idempotent: a document in which every footnote with a source already
carries a number is returned unchanged, so the hook keeps the field-wide numbers in each part,
and `id="fnref-n"` stays on the first occurrence in the whole field. A partly numbered document is
numbered anew.

## CSS

No frontend assets are published; two small rules cover the essentials:

```css
sup.footnote-ref {
    font-size: 0.75em;
    line-height: 0;
}

.footnotes ol {
    font-size: 0.875rem;
}

.footnotes li {
    scroll-margin-top: 6rem; /* keep the jump target clear of a sticky header */
}
```

## Limits

- The ids `fn-n` and `fnref-n` are fixed: one footnote list per page. Two `footnotes` tags on the
  same page produce the same ids.
- **Requires `save_html: false`, the default.** The footnotes are numbered from the saved JSON. A
  Bard field saving HTML (`save_html: true`) stores the superscript without a number and has no
  source list: the footnote reads `[source]` in the saved markup and nothing more.
- **A Bard field nested in a set** numbers its footnotes on its own, from 1. Its `#fn-1` and
  `#fnref-1` collide with the same ids of the outer field's list when both appear on one page.
- **Removing the addon empties the field.** A Bard document containing footnote nodes needs this
  addon's node registered — for rendering *and* in the CP. With the addon uninstalled or disabled,
  a Bard field holding footnotes loads **empty** in the CP, and the next save destroys the value.
  Migrate the content away first (remove or convert the footnotes), then remove the addon.
- Translations ship for English and German (`lang/en`, `lang/de`); the strings live under
  `bard-footnotes::messages`.

## License

MIT — see [LICENSE](LICENSE).
