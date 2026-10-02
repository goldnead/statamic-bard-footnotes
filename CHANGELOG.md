# Changelog

All notable changes to `statamic-bard-footnotes` will be documented in this file.

## 1.0.0 - unreleased

### First release

- `Footnotes::render()`: `[n]` markers (1–2 digits) in rendered Bard HTML become superscript
  links `<sup class="footnote-ref"><a href="#fn-n" id="fnref-n">`; only 1 to the source count,
  never inside links or headings, jump target only on the first occurrence.
- `Footnotes::sources()`: normalizes the `sources` grid to numbered source rows. Empty rows
  drop out and the numbering closes the gaps; urls survive only with `http(s)://`.
- `{{ | footnotes }}` modifier: count from the sources field (passed, named, or from the
  context) or from a plain number. Bard values render to HTML first.
- `{{ footnotes }}` tag: as a pair, loops the sources with `number`, `text`, `url`, `cited`
  (plus `no_results`/`total_results`); on its own, renders the publishable
  `bard-footnotes::list` view with the source list and back links.
- Fieldset `bard-footnotes::sources` for the blueprint import.
- Translations for English and German.
