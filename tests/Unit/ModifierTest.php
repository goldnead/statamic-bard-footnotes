<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\ServiceProvider;
use Goldnead\BardFootnotes\Tests\Concerns\BuildsBardContent;
use Goldnead\BardFootnotes\Tests\Concerns\RendersAntlers;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Testing\AddonTestCase;

class ModifierTest extends AddonTestCase
{
    use BuildsBardContent, RendersAntlers;

    protected string $addonServiceProvider = ServiceProvider::class;

    private array $sources = [
        ['text' => 'One', 'url' => 'https://example.com/1'],
    ];

    public function test_a_number_parameter_sets_the_count(): void
    {
        $this->assertSame(
            '<p>See <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>.</p>',
            $this->render('{{ content | footnotes:1 }}', ['content' => '<p>See [1].</p>'])
        );
    }

    public function test_the_sources_field_from_the_context_sets_the_count(): void
    {
        $template = '{{ content | footnotes }}';

        $this->assertSame(
            '<p>See <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>.</p>',
            $this->render($template, ['content' => '<p>See [1].</p>', 'sources' => $this->sources])
        );

        // Without matching sources the marker stays.
        $this->assertSame(
            '<p>See [2].</p>',
            $this->render($template, ['content' => '<p>See [2].</p>', 'sources' => $this->sources])
        );
    }

    public function test_the_sources_field_can_be_named_as_a_parameter(): void
    {
        $context = ['content' => '<p>See [1].</p>', 'sources' => $this->sources];

        $this->assertStringContainsString(
            'id="fnref-1"',
            $this->render('{{ content | footnotes:sources }}', $context)
        );
    }

    public function test_the_sources_grid_can_be_passed_as_an_argument(): void
    {
        $context = ['content' => '<p>See [1].</p>', 'sources' => $this->sources];

        $this->assertStringContainsString(
            'id="fnref-1"',
            $this->render('{{ content | footnotes(sources) }}', $context)
        );
    }

    public function test_a_bard_value_is_rendered_to_html_first(): void
    {
        $bard = new Value([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'See [1].']]],
        ], 'content', new Bard);

        $this->assertSame(
            '<p>See <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>.</p>',
            $this->render('{{ content | footnotes:1 }}', ['content' => $bard])
        );
    }

    /**
     * Antlers does accept a modifier's result as the data of a pair loop (the
     * same mechanism `| reverse` uses there), so a Bard field with sets goes
     * through the modifier as a whole: every text set comes back rendered,
     * other sets pass through untouched, and the jump target id lands once
     * across all text sets.
     */
    public function test_bard_sets_render_in_their_text_sets(): void
    {
        $content = $this->bardValue([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro [1].']]],
            ['type' => 'set', 'attrs' => ['id' => 's1', 'values' => ['type' => 'quote', 'quote' => 'Typed words.']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'More [1].']]],
        ]);

        $html = $this->render(
            '{{ content | footnotes }}'.
            '{{ if type == "text" }}{{ text }}{{ /if }}'.
            '{{ if type == "quote" }}<blockquote>{{ quote }}</blockquote>{{ /if }}'.
            '{{ /content }}',
            ['content' => $content, 'sources' => $this->sources],
        );

        $this->assertStringContainsString(
            'Intro <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>.',
            $html,
        );
        $this->assertStringContainsString(
            'More <sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup>.',
            $html,
        );
        $this->assertStringContainsString('<blockquote>Typed words.</blockquote>', $html);
        $this->assertSame(2, substr_count($html, 'href="#fn-1"'));
        $this->assertSame(1, substr_count($html, 'id="fnref-1"'));
    }
}
