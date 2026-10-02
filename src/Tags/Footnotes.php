<?php

/**
 * The `footnotes` tag.
 */

namespace Goldnead\BardFootnotes\Tags;

use Goldnead\BardFootnotes\Footnotes as Footnote;
use Statamic\Tags\Tags;

class Footnotes extends Tags
{
    protected static $handle = 'footnotes';

    /**
     * As a pair, loops over the sources; on its own, renders the
     * `bard-footnotes::list` view.
     *
     * @return string|array
     */
    public function index()
    {
        $sources = Footnote::sources($this->params->get('sources') ?? $this->context->get('sources'));

        if ($sources === []) {
            // An empty array rather than null: Antlers turns it into
            // no_results for the pair, and the single tag renders nothing.
            return $this->isPair ? [] : '';
        }

        $sources = $this->withCited($sources);

        if (! $this->isPair) {
            // The addon's view namespace is registered at boot and invisible
            // to the analyzer's view path check.
            return (string) view('bard-footnotes::list', ['sources' => $sources]); // @phpstan-ignore argument.type
        }

        return $sources;
    }

    /**
     * Whether each source is actually cited. Only decidable when the content
     * comes along — as HTML or as Bard sets, every text set counts; without
     * it every source stays `cited => false`.
     */
    private function withCited(array $sources): array
    {
        $content = $this->params->get('content');

        if ($content === null) {
            return array_map(fn (array $source): array => [...$source, 'cited' => false], $sources);
        }

        $rendered = Footnote::html($content, count($sources));

        return array_map(
            fn (array $source): array => [...$source, 'cited' => Footnote::cited($rendered, $source['number'])],
            $sources
        );
    }
}
