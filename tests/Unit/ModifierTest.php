<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\ServiceProvider;
use Goldnead\BardFootnotes\Tests\Concerns\RendersAntlers;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Testing\AddonTestCase;

class ModifierTest extends AddonTestCase
{
    use RendersAntlers;

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
}
