<?php

/**
 * The `footnotes` tag: the source list for a Bard field's inline footnotes.
 */

namespace Goldnead\BardFootnotes\Tags;

use Goldnead\BardFootnotes\Footnotes as Footnote;
use Illuminate\Support\Facades\Log;
use Statamic\Tags\Tags;

class Footnotes extends Tags
{
    protected static $handle = 'footnotes';

    /**
     * Collects the field's (`field="..."`) footnote nodes in document
     * order — the same order the augment hook numbers them by — and
     * either loops them (pair) or renders the `bard-footnotes::list`
     * view (single tag).
     *
     * @return string|array
     */
    public function index()
    {
        $sources = Footnote::sources($this->rawContent());

        if ($sources === []) {
            // An empty array rather than null: Antlers turns it into
            // no_results for the pair, and the single tag renders nothing.
            return $this->isPair ? [] : '';
        }

        if (! $this->isPair) {
            // The addon's view namespace is registered at boot and invisible
            // to the analyzer's view path check.
            return (string) view('bard-footnotes::list', ['sources' => $sources]); // @phpstan-ignore argument.type
        }

        return $sources;
    }

    /**
     * The field's raw Bard document. A `:content` binding can't carry it:
     * by the time the tag runs, binding a Bard value yields its augmented
     * output (HTML, or the augmented rows for a sets field) — so the raw
     * JSON is read from the template context, by field handle.
     */
    private function rawContent(): mixed
    {
        $field = $this->params->get('field');
        $raw = $field === null ? null : Footnote::raw($this->context->get($field));

        // A silent empty list is the right output for a page, a puzzle for
        // the developer: in debug mode one line says what was not found.
        if (! is_array($raw) && config('app.debug')) {
            Log::warning(sprintf(
                'bard-footnotes: {{ footnotes field="%s" }} found no Bard value in the current context. '
                .'`field` is the handle of a Bard variable available where the tag is used.',
                is_string($field) ? $field : '',
            ));
        }

        return $raw;
    }
}
