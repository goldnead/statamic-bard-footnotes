# Bard Footnotes

Footnotes for Statamic's Bard field: type `[1]` in the text, keep the sources in a grid, get
superscript links and a source list.

Bard has no superscript with an anchor — but plain text survives every save, in the CP and in
inline editing alike. So the marker of choice is a typed `[1]`, and everything else happens on
output:

- A `[1]` in the text becomes `<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>`
- The source list below the article carries the jump targets `id="fn-1"`, `id="fn-2"` … and,
  for cited sources, a back link `#fnref-n`.
- Markers without a matching source (`[0]`, `[4]` when there are three sources) stay as text.
  So does everything inside links, headings, `pre` and `code` — `arr[1]` in a code block stays code.

## Installation

```bash
composer require goldnead/statamic-bard-footnotes
```

## The field

Add the shipped fieldset to your blueprint:

```yaml
fields:
  -
    import: bard-footnotes::sources
```

You get a grid named `sources` with the columns **Source** (text) and **Link** (url). One source
per row; reference it in the text with `[1]`, `[2]` … in the order of this list. Rows without
text drop out on output, and the numbering follows the remaining rows.

A link only reaches the output when it starts with `http://` or `https://` — anything else a
redactor types into that column (`javascript:…`, a relative path) becomes a plain-text source.
That is deliberate: nothing from a content field should end up in an `href` unvetted.

## In Antlers

Prepare the article with the modifier, then render the list with the tag:

```antlers
{{ article | footnotes(sources) }}

{{ footnotes :sources="sources" :content="article" }}
```

The modifier accepts the count three ways:

- `{{ article | footnotes(sources) }}` — the grid field (or `{{ article | footnotes:sources }}`)
- `{{ article | footnotes:3 }}` — a plain number
- `{{ article | footnotes }}` — reads the field `sources` from the context

**Bard with sets** works as a pair loop: the modifier takes the whole set list and
returns it with every text set's `text` rendered — other sets pass through untouched,
and the jump target `id="fnref-n"` is placed only once across all text sets:

```antlers
{{ content | footnotes }}
    {{ if type == 'text' }}{{ text }}{{ /if }}
    {{ if type == 'quote' }}<blockquote>{{ quote }}</blockquote>{{ /if }}
{{ /content }}
```

(Antlers accepts a modifier's result as the data of a pair loop, the same mechanism
`{{ list | reverse }}` uses.) The `sources` count comes from the surrounding context.

The tag works two ways:

**Single tag** — renders the shipped `bard-footnotes::list` view, with the "Sources" heading,
an ordered list, external links with `target="_blank" rel="noopener noreferrer"` and a `↩` back
link for every cited source. Text and url are always escaped. No sources, no output.

**Tag pair** — loop over the sources yourself. The single tag escapes text and url itself;
in the pair that is your template's job, hence `| entities`:

```antlers
{{ footnotes :sources="sources" :content="article" }}
    <li id="fn-{{ number }}">{{ text | entities }}{{ if url }} — {{ url | entities }}{{ /if }}{{ if cited }} <a href="#fnref-{{ number }}">↩</a>{{ /if }}</li>
{{ /footnotes }}
```

Each source carries `number`, `text`, `url`, `cited`. `cited` is true when the rendered content
actually contains the marker `[n]`; without `:content` it is always false. `no_results` and
`total_results` behave like in Statamic's collection tags.

Publish the view to make it your own:

```bash
php artisan vendor:publish --tag=bard-footnotes-views
```

## In PHP

For Blade, Inertia or anywhere outside Antlers, the static methods do all of it:

```php
use Goldnead\BardFootnotes\Footnotes;

$sources = Footnotes::sources($entry->sources); // list of ['number', 'text', 'url']

// Whatever the article field is: a plain Bard field comes back as rendered HTML,
// a field with sets comes back as the same set list with every text set rendered.
$content = Footnotes::renderValue($entry->article, count($sources));

// For Inertia:
return Inertia::render('Article', ['content' => $content, 'sources' => $sources]);

// For Blade, a plain Bard field:
{!! $content !!}
```

`renderValue()` takes whatever the field hands you — the HTML string of a plain Bard field,
or its set list — and returns the same shape with the markers linked (`render()` for a bare
HTML string, `renderSets()` for a set list). `sources()` accepts the raw grid value, a
Statamic `Value` or a collection.

## CSS

No assets are published; two small rules cover the essentials:

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

The ids `fn-n` and `fnref-n` are fixed: one footnote list per page. Two `footnotes` tags on the
same page produce the same ids. Translations ship for English and German
(`lang/en`, `lang/de`); the strings live under `bard-footnotes::messages`.

## License

MIT — see [LICENSE](LICENSE).
