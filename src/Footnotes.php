<?php

namespace Goldnead\BardFootnotes;

use Closure;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;

/**
 * Footnotes for Bard's inline `footnote` nodes.
 *
 * A footnote stores its source (`text`, optional `url`) and nothing else.
 * Numbers are never saved: they are derived at render time, in order of
 * first occurrence, with the same source keeping the same number. The key
 * for "same source" is the normalized URL, or the normalized text when
 * there is no URL — mirrored 1:1 by sourceKey() in resources/js/footnotes.js,
 * which numbers the nodes live in the editor. Change one, change the other.
 */
final class Footnotes
{
    /**
     * The key that decides whether two footnote nodes are the same source:
     * the trimmed URL when one exists, otherwise the trimmed text with
     * whitespace collapsed, case-insensitive.
     */
    public static function key(?string $text, ?string $url): string
    {
        $url = trim((string) $url);

        if ($url !== '') {
            return $url;
        }

        $text = preg_replace('/[\p{Z}\s]+/u', ' ', trim((string) $text));

        return mb_strtolower($text ?? '');
    }

    /**
     * The distinct sources of a Bard field, in order of first citation:
     * the rows the tag loops and the list view renders. Numbers are the
     * same ones the augment hook writes into the document.
     *
     * @param  mixed  $bardJson  a raw Bard value (array, Value, Collection); anything else has no sources
     * @return list<array{number: int, text: string, url: ?string}>
     */
    public static function sources(mixed $bardJson): array
    {
        $value = self::raw($bardJson);

        if (! is_array($value)) {
            return [];
        }

        $sources = [];
        $keys = [];

        self::walk($value, function (array $attrs) use (&$sources, &$keys): void {
            $key = self::key($attrs['text'] ?? null, $attrs['url'] ?? null);

            if (isset($keys[$key])) {
                return;
            }

            $keys[$key] = true;
            $url = trim((string) ($attrs['url'] ?? ''));

            $sources[] = [
                'number' => count($sources) + 1,
                'text' => trim((string) ($attrs['text'] ?? '')),
                // Nothing from a content field reaches an href unvetted: a
                // link only survives as http(s), anything else is text.
                'url' => $url !== '' && preg_match('#^https?://#i', $url) === 1 ? $url : null,
            ];
        });

        return $sources;
    }

    /**
     * The Bard augment hook: walks the whole ProseMirror document of one
     * field (paragraphs and set markers alike — Augmentor renders them in
     * a single pass) and writes the derived `number` and `first` attrs
     * into every footnote node. The FootnoteNode renders from those.
     *
     * A document without footnote nodes passes through untouched — the
     * walk is a plain array traversal with nothing to write.
     *
     * @param  mixed  $value  whatever the field holds; only arrays are walked
     */
    public static function number(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $numbers = [];

        $walk = function (array &$nodes) use (&$walk, &$numbers): void {
            foreach ($nodes as &$node) {
                if (! is_array($node)) {
                    continue;
                }

                if (($node['type'] ?? null) === 'footnote') {
                    $key = self::key($node['attrs']['text'] ?? null, $node['attrs']['url'] ?? null);
                    $first = ! isset($numbers[$key]);

                    if ($first) {
                        $numbers[$key] = count($numbers) + 1;
                    }

                    $node['attrs'] = [
                        ...($node['attrs'] ?? []),
                        'number' => $numbers[$key],
                        'first' => $first,
                    ];
                }

                // Only `content`: a set's own field values are a separate
                // Bard document, augmented (and numbered) on its own.
                if (isset($node['content']) && is_array($node['content'])) {
                    $walk($node['content']);
                }
            }
        };

        $walk($value);

        return $value;
    }

    /**
     * Visits every footnote node of a document in reading order. Footnotes
     * inside a set's field values do not belong to this document.
     *
     * @param  list<mixed>  $value
     */
    private static function walk(array $value, Closure $visit): void
    {
        foreach ($value as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'footnote') {
                $visit($node['attrs'] ?? []);
            }

            if (isset($node['content']) && is_array($node['content'])) {
                self::walk($node['content'], $visit);
            }
        }
    }

    /**
     * A Bard value to its raw ProseMirror document: Value → raw, Collection
     * → items, array → itself, everything else → null.
     */
    private static function raw(mixed $value): mixed
    {
        if ($value instanceof Value) {
            $value = $value->raw();
        }

        return $value instanceof Collection ? $value->all() : $value;
    }
}
