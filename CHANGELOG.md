# Changelog

All notable changes to `statamic-bard-footnotes` will be documented in this file.

## 2.0.0 - unreleased

### Breaking: footnotes move into the text

- Requires Statamic 6 and PHP 8.2. For Statamic 5, stay on the `1.x` branch — see
  [UPGRADE.md](UPGRADE.md) for the manual migration.
- Removed the `sources` grid fieldset and typed `[n]` markers: no more `Footnotes::render()`,
  `renderSets()`, `renderValue()`, the `{{ | footnotes }}` modifier, or the fieldset import.
  Footnotes are now created with a **Footnote** toolbar button in Bard — an inline node storing
  its source (`text`, optional `url`), rendered as a superscript link.
- Numbers are derived, never stored: order of first occurrence, same source = same number
  (same trimmed URL, else same text — whitespace collapsed, case-insensitive). The editor shows
  the live number; clicking a footnote reopens its popover.
- Numbering runs across the whole field, Bard sets included; a Bard field nested inside a set
  remains its own document.
- Markup: `<sup class="footnote-ref"><a href="#fn-n" id="fnref-n" aria-label="Footnote n">n</a></sup>`,
  jump target on first occurrence only. The CP bundle (button, popover, node view) is committed
  under `dist/` and published with `vendor:publish --tag=statamic-bard-footnotes`.
- The `{{ footnotes }}` tag reads the field from the template context by handle
  (`field="content"` — a `:content` binding arrives as rendered HTML). `cited` is gone: every
  listed source is cited by definition. The pair loop now yields `number`, `text`, `url`.
- `Footnotes::sources()` returns `list<{number, text, url}>` for a raw Bard value.

## 1.0.0 - 2026-10-02

### First release

- `Footnotes::render()`: `[n]` markers (1–2 digits) in rendered Bard HTML become superscript
  links `<sup class="footnote-ref"><a href="#fn-n" id="fnref-n">`; only 1 to the source count,
  never inside links, headings (h1–h6), `pre` or `code`, jump target only on the first occurrence.
- Bard fields with sets: every text set is rendered, other sets pass through untouched, and the
  jump target is placed once across all text sets (`Footnotes::renderSets()`,
  `Footnotes::renderValue()`).
- `Footnotes::sources()`: normalizes the `sources` grid to numbered source rows. Empty rows
  drop out and the numbering closes the gaps; urls survive only with `http(s)://`.
- `{{ | footnotes }}` modifier: count from the sources field (passed, named, or from the
  context) or from a plain number. Bard values render to HTML first.
- `{{ footnotes }}` tag: as a pair, loops the sources with `number`, `text`, `url`, `cited`
  (plus `no_results`/`total_results`); on its own, renders the publishable
  `bard-footnotes::list` view with the source list and back links.
- Fieldset `bard-footnotes::sources` for the blueprint import.
- Translations for English and German.
