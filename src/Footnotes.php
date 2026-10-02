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
     * exist), never inside links or headings.
     *
     * Anything without work to do returns the HTML unchanged, without a DOM
     * round-trip.
     */
    public static function render(string $html, int $count): string
    {
        if ($count < 1 || ! str_contains($html, '[')) {
            return $html;
        }

        [$doc, $root] = self::load($html);
        $nodes = (new DOMXPath($doc))->query('.//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4)]', $root);
        $placed = [];
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
