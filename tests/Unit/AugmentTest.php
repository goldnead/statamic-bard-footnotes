<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\Footnotes;
use Goldnead\BardFootnotes\ServiceProvider;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Bard\Augmentor;
use Statamic\Testing\AddonTestCase;

/**
 * The rendered output: the augment hook numbers a field's footnotes and
 * the FootnoteNode turns them into superscript links — across paragraphs,
 * across sets, once per source for the jump target.
 */
class AugmentTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    public function test_footnotes_render_as_superscript_links(): void
    {
        $this->assertSame(
            '<p>Three weeks<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>of work<sup class="footnote-ref"><a href="#fn-2" id="fnref-2" aria-label="Footnote 2">2</a></sup></p>'
            .'<p><sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup></p>',
            $this->augment($this->doc()),
        );
    }

    public function test_a_repeated_source_gets_its_number_and_no_second_jump_target(): void
    {
        $html = $this->augment($this->doc());

        $this->assertSame(1, substr_count($html, 'id="fnref-1"'));
        $this->assertSame(2, substr_count($html, 'href="#fn-1"'));
    }

    public function test_the_footnote_text_never_reaches_the_body(): void
    {
        $html = $this->augment([['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'a'],
            ['type' => 'footnote', 'attrs' => ['text' => '<script>alert(1)</script>', 'url' => 'javascript:alert(1)']],
        ]]]);

        $this->assertStringContainsString('<a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_a_footnote_without_text_and_url_renders_nothing_and_takes_no_number(): void
    {
        $html = $this->augment([['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'a'],
            ['type' => 'footnote', 'attrs' => ['text' => '', 'url' => null]],
            ['type' => 'footnote', 'attrs' => ['text' => "\u{00A0}", 'url' => '']],
            ['type' => 'footnote', 'attrs' => ['text' => 'Real', 'url' => null]],
        ]]]);

        $this->assertSame(
            '<p>a<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup></p>',
            $html,
        );
    }

    public function test_bard_without_footnotes_renders_unchanged(): void
    {
        $this->assertSame(
            '<p>Plain text</p>',
            $this->augment([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Plain text']]]]),
        );
    }

    public function test_numbering_runs_across_sets(): void
    {
        $sets = $this->bardWithSets([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Intro '],
                ['type' => 'footnote', 'attrs' => ['text' => 'A', 'url' => null]],
            ]],
            ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => ['type' => 'quote', 'quote' => 'Typed words.']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'More'],
                ['type' => 'footnote', 'attrs' => ['text' => 'B', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'a ', 'url' => null]],
            ]],
        ]);

        $rows = $sets->value();

        $this->assertSame(
            '<p>Intro <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup></p>',
            (string) $rows[0]['text'],
        );
        $this->assertSame('Typed words.', $rows[1]['quote']);
        $this->assertSame(
            '<p>More<sup class="footnote-ref"><a href="#fn-2" id="fnref-2" aria-label="Footnote 2">2</a></sup>'
            .'<sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup></p>',
            (string) $rows[2]['text'],
        );
    }

    /*
     * Rendering in parts: an app numbers the whole document once, splits it
     * (e.g. around sets) and renders each part through the Augmentor. The
     * hook runs again per part and must keep the field-wide numbers — and
     * the jump target only on the first occurrence in the WHOLE document.
     */
    public function test_a_numbered_document_rendered_in_parts_keeps_its_numbers(): void
    {
        $numbered = Footnotes::number([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'One'],
                ['type' => 'footnote', 'attrs' => ['text' => 'A', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'B', 'url' => null]],
            ]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Two'],
                ['type' => 'footnote', 'attrs' => ['text' => 'a', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'C', 'url' => null]],
            ]],
        ]);

        [$first, $second] = [array_slice($numbered, 0, 1), array_slice($numbered, 1)];

        $this->assertSame(
            '<p>One<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>'
            .'<sup class="footnote-ref"><a href="#fn-2" id="fnref-2" aria-label="Footnote 2">2</a></sup></p>',
            $this->convertToHtml($first),
        );
        $this->assertSame(
            '<p>Two<sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup>'
            .'<sup class="footnote-ref"><a href="#fn-3" id="fnref-3" aria-label="Footnote 3">3</a></sup></p>',
            $this->convertToHtml($second),
        );
    }

    public function test_an_unnumbered_part_is_numbered_on_its_own_as_before(): void
    {
        $this->assertSame(
            '<p>Two<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>'
            .'<sup class="footnote-ref"><a href="#fn-2" id="fnref-2" aria-label="Footnote 2">2</a></sup></p>',
            $this->convertToHtml([['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Two'],
                ['type' => 'footnote', 'attrs' => ['text' => 'A', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'C', 'url' => null]],
            ]]]),
        );
    }

    public function test_number_returns_an_already_numbered_document_unchanged(): void
    {
        $numbered = Footnotes::number($this->doc());

        $this->assertSame($numbered, Footnotes::number($numbered));

        // Stale numbers from elsewhere survive too: numbered means numbered.
        $foreign = $numbered;
        $foreign[0]['content'][1]['attrs']['number'] = 7;
        $this->assertSame($foreign, Footnotes::number($foreign));
    }

    public function test_a_partly_numbered_document_is_numbered_again(): void
    {
        $doc = $this->doc();
        $doc[0]['content'][1]['attrs'] += ['number' => 5, 'first' => true];

        $this->assertSame(
            [1, 2, 1],
            array_map(fn ($node) => $node['attrs']['number'], [
                Footnotes::number($doc)[0]['content'][1],
                Footnotes::number($doc)[0]['content'][3],
                Footnotes::number($doc)[1]['content'][0],
            ]),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $part
     */
    private function convertToHtml(array $part): string
    {
        return (new Augmentor((new Bard)->setField(new Field('content', []))))->convertToHtml($part);
    }

    /**
     * @param  list<array<string, mixed>>  $doc
     */
    private function augment(array $doc): string
    {
        return (string) (new Value($doc, 'content', (new Bard)->setField(new Field('content', []))))->value();
    }

    /**
     * @param  list<array<string, mixed>>  $doc
     */
    private function bardWithSets(array $doc): Value
    {
        $bard = (new Bard)->setField(new Field('content', [
            'sets' => [
                'quote' => ['fields' => [
                    ['handle' => 'quote', 'field' => ['type' => 'text']],
                ]],
            ],
        ]));

        return new Value($doc, 'content', $bard);
    }

    /**
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
            ]],
        ];
    }
}
