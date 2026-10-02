<?php

/**
 * The `footnotes` modifier: turns the typed [1] markers in a Bard field into
 * superscript links.
 */

namespace Goldnead\BardFootnotes\Modifiers;

use Goldnead\BardFootnotes\Footnotes as Footnote;
use Statamic\Modifiers\Modifier;

class Footnotes extends Modifier
{
    /**
     * @param  mixed  $value  The value to be modified: an HTML string or a Bard value
     * @param  array  $params  Any parameters used in the modifier
     * @param  array  $context  Contextual values
     */
    public function index($value, $params = [], $context = [])
    {
        return Footnote::render((string) $value, $this->count($params, $context));
    }

    /**
     * How many sources the text may reference: the first parameter, the
     * `sources` field from the context, or a plain number.
     */
    private function count(array $params, array $context): int
    {
        $param = $params[0] ?? null;

        if ($param === null) {
            return count(Footnote::sources($context['sources'] ?? null));
        }

        if (is_numeric($param)) {
            return (int) $param;
        }

        // A field name: `{{ content | footnotes:sources }}` hands over the
        // bare word when no context variable of that name resolves.
        if (is_string($param)) {
            $param = $context[$param] ?? $param;
        }

        return count(Footnote::sources($param));
    }
}
