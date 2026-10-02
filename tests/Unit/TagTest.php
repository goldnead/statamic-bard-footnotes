<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\ServiceProvider;
use Goldnead\BardFootnotes\Tests\Concerns\RendersAntlers;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Testing\AddonTestCase;

/**
 * The `footnotes` tag: the source list of a Bard field's inline
 * footnotes, read from the raw field value, numbered like the text.
 */
class TagTest extends AddonTestCase
{
    use RendersAntlers;

    protected string $addonServiceProvider = ServiceProvider::class;

    public function test_the_pair_tag_loops_the_sources(): void
    {
        $out = $this->render(
            '{{ footnotes field="content" }}[{{ number }}|{{ text }}|{{ url }}]{{ /footnotes }}',
            ['content' => $this->content()],
        );

        $this->assertSame(
            '[1|Smith, p. 12|https://example.com/smith][2|Jones|][3|<script>alert(1)</script>|]',
            $out,
        );
    }

    public function test_the_pair_tag_reports_no_results(): void
    {
        $plain = new Value([['type' => 'paragraph']], 'content', new Bard);

        $this->assertSame(
            'none',
            $this->render('{{ footnotes field="content" }}{{ if no_results }}none{{ /if }}{{ /footnotes }}', ['content' => $plain]),
        );
    }

    public function test_the_single_tag_renders_the_source_list(): void
    {
        $out = $this->render('{{ footnotes field="content" }}', ['content' => $this->content()]);

        $this->assertStringContainsString('<section class="footnotes" aria-labelledby="footnotes-title">', $out);
        $this->assertStringContainsString('<h2 id="footnotes-title">Sources</h2>', $out);
        $this->assertStringContainsString('<li id="fn-1">', $out);
        $this->assertStringContainsString('<a href="https://example.com/smith" target="_blank" rel="noopener noreferrer">Smith, p. 12</a>', $out);
        $this->assertStringContainsString('<li id="fn-2">', $out);
        $this->assertStringContainsString('Jones', $out);
        $this->assertStringContainsString('<a href="#fnref-1" class="footnote-back" aria-label="Back to text 1">↩</a>', $out);

        // A footnote without a citable link stays plain text, escaped;
        // a javascript: url never becomes an href.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $out);
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('href="javascript:', $out);
    }

    public function test_the_single_tag_without_footnotes_renders_nothing(): void
    {
        $plain = new Value([['type' => 'paragraph']], 'content', new Bard);

        $this->assertSame('', $this->render('{{ footnotes field="content" }}', ['content' => $plain]));
    }

    public function test_an_antlers_page_renders_text_and_list_with_matching_anchors(): void
    {
        // A plain text flow the way `{{ content }}` renders it in a page.
        $content = new Value([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Three weeks'],
                ['type' => 'footnote', 'attrs' => ['text' => 'Smith, p. 12', 'url' => 'https://example.com/smith']],
            ]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'Jones', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'Ebd.', 'url' => 'https://example.com/smith']],
            ]],
        ], 'content', new Bard);

        $out = $this->render('{{ content }}{{ footnotes field="content" }}', ['content' => $content]);

        // The reference and its jump target agree; the repeated source
        // gets its number but no second jump target.
        $this->assertStringContainsString('<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>', $out);
        $this->assertStringContainsString('<li id="fn-1">', $out);
        $this->assertStringContainsString('<li id="fn-2">', $out);
        $this->assertSame(2, substr_count($out, 'class="footnote-back"'));
    }

    /**
     * A Bard value the way an entry holds it: a repeated source (same key),
     * a second source, and one with hostile text — plus a set in between,
     * to prove the numbering carries across the whole field.
     */
    private function content(): Value
    {
        $bard = (new Bard)->setField(new Field('content', [
            'sets' => [
                'quote' => ['fields' => [
                    ['handle' => 'quote', 'field' => ['type' => 'text']],
                ]],
            ],
        ]));

        return new Value([
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Three weeks'],
                ['type' => 'footnote', 'attrs' => ['text' => 'Smith, p. 12', 'url' => 'https://example.com/smith']],
            ]],
            ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => ['type' => 'quote', 'quote' => 'Typed words.']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'footnote', 'attrs' => ['text' => 'Jones', 'url' => 'javascript:alert(1)']],
                ['type' => 'footnote', 'attrs' => ['text' => '<script>alert(1)</script>', 'url' => null]],
                ['type' => 'footnote', 'attrs' => ['text' => 'Ebd.', 'url' => 'https://example.com/smith']],
            ]],
        ], 'content', $bard);
    }
}
