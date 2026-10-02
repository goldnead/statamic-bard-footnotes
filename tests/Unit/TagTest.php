<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\ServiceProvider;
use Goldnead\BardFootnotes\Tests\Concerns\RendersAntlers;
use Statamic\Testing\AddonTestCase;

class TagTest extends AddonTestCase
{
    use RendersAntlers;

    protected string $addonServiceProvider = ServiceProvider::class;

    private array $sources = [
        ['text' => 'One', 'url' => 'https://example.com/1'],
        ['text' => 'Two, no link', 'url' => null],
    ];

    public function test_the_pair_tag_loops_the_sources_with_cited(): void
    {
        $out = $this->render(
            '{{ footnotes :sources="sources" :content="content" }}[{{ number }}|{{ text }}|{{ url }}|{{ if cited }}cited{{ /if }}]{{ /footnotes }}',
            ['sources' => $this->sources, 'content' => '<p>a [1].</p>'],
        );

        $this->assertSame('[1|One|https://example.com/1|cited][2|Two, no link||]', $out);
    }

    public function test_the_pair_tag_without_content_has_no_cited_source(): void
    {
        $out = $this->render(
            '{{ footnotes :sources="sources" }}[{{ number }}|{{ if cited }}cited{{ /if }}]{{ /footnotes }}',
            ['sources' => $this->sources],
        );

        $this->assertSame('[1|][2|]', $out);
    }

    public function test_the_pair_tag_reports_no_results(): void
    {
        $this->assertSame(
            'none',
            $this->render('{{ footnotes :sources="sources" }}{{ if no_results }}none{{ /if }}{{ /footnotes }}', ['sources' => []]),
        );
    }

    public function test_the_single_tag_renders_the_source_list(): void
    {
        $out = $this->render(
            '{{ footnotes :sources="sources" :content="content" }}',
            [
                'sources' => [
                    ['text' => 'One', 'url' => 'https://example.com/1'],
                    ['text' => '<script>alert(1)</script> Two', 'url' => null],
                ],
                'content' => '<p>a [1].</p>',
            ],
        );

        $this->assertStringContainsString('<section class="footnotes" aria-labelledby="footnotes-title">', $out);
        $this->assertStringContainsString('<h2 id="footnotes-title">Sources</h2>', $out);
        $this->assertStringContainsString('<li id="fn-1">', $out);
        $this->assertStringContainsString('<li id="fn-2">', $out);
        $this->assertStringContainsString('<a href="https://example.com/1" target="_blank" rel="noopener noreferrer">One</a>', $out);
        $this->assertStringContainsString('<a href="#fnref-1" class="footnote-back" aria-label="Back to text 1">↩</a>', $out);

        // Only the cited source gets a back link; text is always escaped.
        $this->assertStringNotContainsString('#fnref-2', $out);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Two', $out);
        $this->assertStringNotContainsString('<script>', $out);
    }

    public function test_the_single_tag_without_sources_renders_nothing(): void
    {
        $this->assertSame('', $this->render('{{ footnotes :sources="sources" }}', ['sources' => []]));
    }
}
