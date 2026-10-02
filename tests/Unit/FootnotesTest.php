<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\Footnotes;
use Goldnead\BardFootnotes\ServiceProvider;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Testing\AddonTestCase;

/**
 * Footnotes::key(), sources() and number(): the shared source-key and the
 * two document walks — one reading the sources for the list, one writing
 * the numbers the FootnoteNode renders.
 */
class FootnotesTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    /*
     * key(): what makes two footnote nodes the same source.
     */

    public function test_the_same_url_is_the_same_source(): void
    {
        $this->assertSame(
            Footnotes::key('One', 'https://example.com/a'),
            Footnotes::key('Completely different text', 'https://example.com/a'),
        );
    }

    public function test_the_same_text_differently_written_is_the_same_source(): void
    {
        $this->assertSame(
            Footnotes::key('Example  Source', null),
            Footnotes::key('example   source', null),
        );
    }

    public function test_different_texts_are_different_sources(): void
    {
        $this->assertNotSame(Footnotes::key('One', null), Footnotes::key('Two', null));
    }

    /*
     * sources(): the list, in order of first citation.
     */

    public function test_sources_collects_footnotes_in_reading_order(): void
    {
        $sources = Footnotes::sources($this->doc());

        $this->assertSame([
            ['number' => 1, 'text' => 'Smith, p. 12', 'url' => null],
            ['number' => 2, 'text' => 'Jones', 'url' => null],
            ['number' => 3, 'text' => 'Ebd.', 'url' => 'https://example.com/smith'],
        ], $sources);
    }

    public function test_a_repeated_source_is_listed_once(): void
    {
        $this->assertCount(3, Footnotes::sources($this->doc()));
    }

    public function test_a_url_that_is_not_http_is_dropped_but_counted(): void
    {
        $sources = Footnotes::sources([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'Nope', 'url' => 'javascript:alert(1)']],
            ]],
        ]);

        $this->assertSame([['number' => 1, 'text' => 'Nope', 'url' => null]], $sources);
    }

    public function test_sources_accepts_a_bard_value_and_a_collection(): void
    {
        $value = new Value($this->doc(), 'content', new Bard);

        $this->assertSame(Footnotes::sources($this->doc()), Footnotes::sources($value));
        $this->assertSame(Footnotes::sources($this->doc()), Footnotes::sources(new Collection($this->doc())));
    }

    public function test_anything_without_footnotes_has_no_sources(): void
    {
        $this->assertSame([], Footnotes::sources([['type' => 'paragraph']]));
        $this->assertSame([], Footnotes::sources('rendered html has no nodes to read'));
        $this->assertSame([], Footnotes::sources(null));
    }

    /*
     * number(): the augment hook's walk.
     */

    public function test_number_writes_first_occurrence_numbers(): void
    {
        $numbered = Footnotes::number($this->doc());

        $attrs = array_map(
            fn (array $footnote): array => $footnote['attrs'],
            $this->footnotesOf($numbered),
        );

        $this->assertSame([
            ['text' => 'Smith, p. 12', 'url' => null, 'number' => 1, 'first' => true],
            ['text' => 'Jones', 'url' => null, 'number' => 2, 'first' => true],
            // The same source, differently written: same number, no second
            // jump target.
            ['text' => 'smith, p. 12', 'url' => null, 'number' => 1, 'first' => false],
            ['text' => 'Ebd.', 'url' => 'https://example.com/smith', 'number' => 3, 'first' => true],
            ['text' => 'See the site', 'url' => 'https://example.com/smith', 'number' => 3, 'first' => false],
        ], $attrs);
    }

    public function test_number_runs_across_set_markers(): void
    {
        $numbered = Footnotes::number([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'A', 'url' => null]],
            ]],
            ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => ['type' => 'quote', 'quote' => 'q']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'B', 'url' => null]],
            ]],
        ]);

        $this->assertSame(1, $this->footnotesOf($numbered)[0]['attrs']['number']);
        $this->assertSame(2, $this->footnotesOf($numbered)[1]['attrs']['number']);
    }

    public function test_number_does_not_descend_into_set_values(): void
    {
        // A bard field nested inside a set is its own document, augmented
        // (and numbered) on its own — the outer walk must not pre-number it.
        $numbered = Footnotes::number([
            ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => [
                'type' => 'quote',
                'nested' => [['type' => 'paragraph', 'content' => [
                    ['type' => 'footnote', 'attrs' => ['text' => 'inner', 'url' => null]],
                ]]],
            ]]],
        ]);

        $this->assertSame([], $this->footnotesOf($numbered));
        $this->assertSame('inner', $numbered[0]['attrs']['values']['nested'][0]['content'][0]['attrs']['text']);
    }

    public function test_a_document_without_footnotes_passes_through_unchanged(): void
    {
        $doc = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'plain']]]];

        $this->assertSame($doc, Footnotes::number($doc));
        $this->assertSame('unchanged', Footnotes::number('unchanged'));
    }

    /**
     * Two paragraphs with five footnote nodes: a source repeated by text
     * (differently written), a second source, and one repeated by URL.
     *
     * @return list<array<string, mixed>>
     */
    private function doc(): array
    {
        return [
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Three weeks'],
                ['type' => 'footnote', 'attrs' => ['text' => 'Smith, p. 12', 'url' => null]],
                ['type' => 'text', 'text' => 'of work'],
                ['type' => 'footnote', 'attrs' => ['text' => 'Jones', 'url' => null]],
            ]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'smith, p. 12', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'Ebd.', 'url' => 'https://example.com/smith']],
                ['type' => 'footnote', 'attrs' => ['text' => 'See the site', 'url' => 'https://example.com/smith']],
            ]],
        ];
    }

    /**
     * @param  list<mixed>  $doc
     * @return list<array<string, mixed>>
     */
    private function footnotesOf(array $doc): array
    {
        $found = [];

        $walk = function (array $nodes) use (&$walk, &$found): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                if (($node['type'] ?? null) === 'footnote') {
                    $found[] = $node;
                }

                if (isset($node['content']) && is_array($node['content'])) {
                    $walk($node['content']);
                }
            }
        };

        $walk($doc);

        return $found;
    }
}
