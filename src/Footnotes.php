<?php

namespace Goldnead\BardFootnotes;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Footnotes for rendered Bard HTML.
 *
 * Everything here works on the rendered HTML, never on the stored value: the
 * entry keeps exactly what the editor typed, and Bard keeps opening the real
 * text. A typed `[1]` survives every save, in the CP and in inline editing
 * alike — Bard has neither superscript nor anchor ids to lose.
 */
final class Footnotes
{
    /**
     * `[n]` in the running text becomes a superscript link pointing at
     * `#fn-n` in the source list. Only for 1 to $count (that many sources
     * exist), never inside links, headings or code.
     *
     * $placed carries which jump targets already exist, so several calls
     * over one document (renderSets) still place each `fnref-n` only once.
     *
     * Anything without work to do returns the HTML unchanged, without a DOM
     * round-trip.
     */
    public static function render(string $html, int $count, array &$placed = []): string
    {
        if ($count < 1 || ! str_contains($html, '[')) {
            return $html;
        }

        [$doc, $root] = self::load($html);
        $nodes = (new DOMXPath($doc))->query('.//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6) and not(ancestor::pre) and not(ancestor::code)]', $root);
        $changed = false;

        foreach (iterator_to_array($nodes ?: []) as $text) {
            $value = $text->nodeValue ?? '';
            $parts = preg_split('/\[(\d{1,2})\]/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($parts === false || count($parts) === 1) {
                continue;
            }

            $rebuilt = [];
            foreach ($parts as $i => $part) {
                $n = (int) $part;
                if ($i % 2 === 0 || $n < 1 || $n > $count) {
                    $rebuilt[] = $doc->createTextNode($i % 2 === 0 ? $part : '['.$part.']');

                    continue;
                }

                $link = $doc->createElement('a');
                $link->setAttribute('href', '#fn-'.$n);
                if (! isset($placed[$n])) {
                    $link->setAttribute('id', 'fnref-'.$n);
                    $placed[$n] = true;
                }
                $link->setAttribute('aria-label', __('bard-footnotes::messages.footnote', ['number' => $n]));
                $link->appendChild($doc->createTextNode((string) $n));
                $sup = $doc->createElement('sup');
                $sup->setAttribute('class', 'footnote-ref');
                $sup->appendChild($link);
                $rebuilt[] = $sup;
                $changed = true;
            }

            foreach ($rebuilt as $partNode) {
                $text->parentNode?->insertBefore($partNode, $text);
            }
            $text->parentNode?->removeChild($text);
        }

        return $changed ? self::save($doc, $root) : $html;
    }

    /**
     * A Bard field with sets: the same set list back, every text set with
     * its `text` rendered, every other set untouched. The jump targets are
     * shared across the whole list, so `fnref-n` exists exactly once.
     *
     * @param  list<mixed>  $sets
     * @return list<mixed>
     */
    public static function renderSets(array $sets, int $count): array
    {
        $placed = [];

        return array_map(function (mixed $set) use ($count, &$placed): mixed {
            if (! self::isTextSet($set)) {
                return $set;
            }

            return self::withText($set, self::render(self::text($set['text'] ?? null), $count, $placed));
        }, $sets);
    }

    /**
     * Whatever a Bard field hands over, with every marker rendered: an HTML
     * string stays a string, a set list (array, Collection, Value) comes
     * back as sets. Nothing is ever cast from array to string — unknown
     * values become an empty string.
     */
    public static function renderValue(mixed $value, int $count): mixed
    {
        $value = self::resolve($value);

        if (is_array($value)) {
            return self::renderSets($value, $count);
        }

        return self::render(self::text($value), $count);
    }

    /**
     * The rendered HTML of a content value, whatever shape it has: the
     * string itself, or all text sets joined — what cited() reads.
     */
    public static function html(mixed $value, int $count): string
    {
        $value = self::resolve($value);

        if (is_array($value)) {
            $html = '';
            foreach (self::renderSets($value, $count) as $set) {
                if (self::isTextSet($set)) {
                    $html .= self::text($set['text'] ?? null);
                }
            }

            return $html;
        }

        return self::render(self::text($value), $count);
    }

    /**
     * The grid value as a clean source list. Order = number of its `[n]`
     * marker. A row without text drops out; a link without http(s) drops out
     * too, so no `javascript:` from the field ever reaches an href.
     *
     * @return list<array{number: int, text: string, url: ?string}>
     */
    public static function sources(mixed $rows): array
    {
        if ($rows instanceof Value) {
            $rows = $rows->raw();
        }

        if ($rows instanceof Collection) {
            $rows = $rows->all();
        }

        if (! is_array($rows)) {
            return [];
        }

        $sources = [];

        foreach ($rows as $row) {
            if ($row instanceof Values) {
                $row = $row->all();
            }

            if (! is_array($row)) {
                continue;
            }

            $text = self::string($row['text'] ?? null);

            if ($text === '') {
                continue;
            }

            $url = self::string($row['url'] ?? null);

            $sources[] = [
                'number' => count($sources) + 1,
                'text' => $text,
                'url' => $url !== '' && preg_match('#^https?://#i', $url) === 1 ? $url : null,
            ];
        }

        return $sources;
    }

    /**
     * Whether the rendered HTML carries the jump target for a source —
     * i.e. the text actually cites it.
     */
    public static function cited(string $renderedHtml, int $number): bool
    {
        return str_contains($renderedHtml, 'id="fnref-'.$number.'"');
    }

    /**
     * A grid cell as a string: Value and null both arrive in normal Antlers
     * context, and everything that is not scalar has no business in a source.
     */
    private static function string(mixed $value): string
    {
        if ($value instanceof Value) {
            $value = $value->raw();
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * The one normalization both the modifier and the tag go through: a
     * Value to its augmented value (the set list of a Bard field with sets,
     * the HTML of one without), a Collection to its items.
     */
    private static function resolve(mixed $value): mixed
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return $value instanceof Collection ? $value->all() : $value;
    }

    /**
     * Content as HTML: an augmented Value (a text set's `text`) to its
     * string, scalars to their string, everything else to '' — never a
     * warning.
     */
    private static function text(mixed $value): string
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function isTextSet(mixed $set): bool
    {
        if ($set instanceof Values) {
            return $set->raw('type') === 'text';
        }

        return is_array($set) && ($set['type'] ?? null) === 'text';
    }

    /**
     * The same set shape with a rendered `text`: Values stay Values (they
     * cannot be mutated), plain arrays stay arrays.
     */
    private static function withText(mixed $set, string $html): mixed
    {
        $fields = $set instanceof Values ? $set->all() : $set;
        $fields = [...$fields, 'text' => $html];

        return $set instanceof Values ? new Values($fields) : $fields;
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private static function load(string $html): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><html><body><div data-fn-root="1">'.$html.'</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($doc))->query('//div[@data-fn-root]')->item(0);

        return [$doc, $root instanceof DOMElement ? $root : $doc->createElement('div')];
    }

    private static function save(DOMDocument $doc, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return $html;
    }
}
