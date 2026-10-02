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

Select some text (or just place the cursor) and click **Footnote**. Fill in the source and,
optionally, a link; Apply inserts the footnote at the cursor. The popover's select at the top
offers the sources already cited in this field — picking one fills the fields with its text and
link. The editor shows the live superscript number; hovering it reveals the source.

Numbers are never stored. They are derived from the document: order of first occurrence, with the
same source keeping the same number. "Same source" means the same link (trimmed), or — without a
link — the same text (whitespace collapsed, case-insensitive).

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
binding would arrive as already-rendered HTML, too late to read the footnotes from. Each source
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
- **Removing the addon empties the field.** A Bard document containing footnote nodes needs this
  addon's node registered — for rendering *and* in the CP. With the addon uninstalled or disabled,
  a Bard field holding footnotes loads **empty** in the CP, and the next save destroys the value.
  Migrate the content away first (remove or convert the footnotes), then remove the addon.
- Translations ship for English and German (`lang/en`, `lang/de`); the strings live under
  `bard-footnotes::messages`.

## License

MIT — see [LICENSE](LICENSE).
