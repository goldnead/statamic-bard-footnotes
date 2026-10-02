<?php

namespace Goldnead\BardFootnotes\Bard;

use Tiptap\Core\Node;

/**
 * The PHP twin of the CP's `footnote` TipTap node, registered on the
 * Augmentor so Bard's HTML output carries the superscript references.
 *
 * It renders from the transient `number` and `first` attrs that the
 * augment hook (Footnotes::number()) writes into the document — a node
 * without a number (no hook ran) has nothing to show and renders empty.
 */
class FootnoteNode extends Node
{
    public static $name = 'footnote';

    public function addAttributes()
    {
        return [
            'text' => [
                'default' => '',
                'parseHTML' => fn ($element) => $element->getAttribute('data-text') ?? '',
            ],
            'url' => [
                'default' => null,
                'parseHTML' => fn ($element) => $element->getAttribute('data-url') ?: null,
            ],
        ];
    }

    public function parseHTML()
    {
        return [
            [
                'tag' => 'sup[data-footnote]',
            ],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        $number = $node->attrs->number ?? null;

        if (! $number) {
            return ['content' => ''];
        }

        $ref = ($node->attrs->first ?? false) ? sprintf(' id="fnref-%d"', $number) : '';

        return ['content' => sprintf(
            '<sup class="footnote-ref"><a href="#fn-%1$d"%2$s aria-label="%3$s">%1$d</a></sup>',
            $number,
            $ref,
            e(__('bard-footnotes::messages.footnote', ['number' => $number])),
        )];
    }
}
