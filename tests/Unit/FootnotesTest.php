<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\Footnotes;
use Goldnead\BardFootnotes\ServiceProvider;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Grid;
use Statamic\Testing\AddonTestCase;
use stdClass;

/**
 * Footnotes::render() and sources(): an editor types [1] as plain text into
 * Bard, the sources live in a grid field, and only the rendered output
 * carries links. These tests pin that transformation.
 */
class FootnotesTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    /*
     * render(): ported from the production code on adriangoldner.com.
     */

    public function test_a_marker_becomes_a_superscript_link(): void
    {
        $html = Footnotes::render('<p>Three weeks.[1] More.</p>', 1);

        $this->assertSame(
            '<p>Three weeks.<sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup> More.</p>',
            $html,
        );
    }

    public function test_a_marker_without_a_matching_source_stays(): void
    {
        $this->assertSame('<p>See [2] and [0].</p>', Footnotes::render('<p>See [2] and [0].</p>', 1));
        $this->assertSame('<p>No sources [1].</p>', Footnotes::render('<p>No sources [1].</p>', 0));
    }

    public function test_links_and_headings_keep_their_text(): void
    {
        $html = '<h2>Part [1]</h2><p><a href="/x">Link [1]</a></p><h4>Deep [1]</h4>';

        $this->assertSame($html, Footnotes::render($html, 1));
    }

    public function test_every_heading_level_keeps_its_text(): void
    {
        $html = '<h1>H [1]</h1><h2>H [1]</h2><h3>H [1]</h3><h4>H [1]</h4><h5>H [1]</h5><h6>H [1]</h6>';

        $this->assertSame($html, Footnotes::render($html, 1));
    }

    public function test_pre_and_code_keep_their_markers(): void
    {
        $html = Footnotes::render('<pre>arr[1]</pre><p>Use <code>arr[1]</code> but [1] stands.</p>', 1);

        $this->assertStringContainsString('<pre>arr[1]</pre>', $html);
        $this->assertStringContainsString('<code>arr[1]</code>', $html);
        $this->assertSame(1, substr_count($html, 'href="#fn-1"'));
    }

    public function test_a_source_cited_twice_sets_its_jump_target_once(): void
    {
        $html = Footnotes::render('<p>a[1] b[2]</p><ul><li>c [1]</li></ul>', 2);

        $this->assertSame(1, substr_count($html, 'id="fnref-1"'));
        $this->assertSame(2, substr_count($html, 'href="#fn-1"'));
        $this->assertStringContainsString(
            '<li>c <sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup></li>',
            $html,
        );
    }

    public function test_two_digit_markers_resolve(): void
    {
        $html = Footnotes::render('<p>See [12], also [9], not [13].</p>', 12);

        $this->assertStringContainsString('<a href="#fn-12" id="fnref-12" aria-label="Footnote 12">12</a>', $html);
        $this->assertStringContainsString('<a href="#fn-9" id="fnref-9" aria-label="Footnote 9">9</a>', $html);
        $this->assertStringContainsString('[13]', $html);
    }

    public function test_umlaute_survive_the_dom_round_trip(): void
    {
        $html = Footnotes::render('<p>Schöne Grüße[1] aus München.</p>', 1);

        $this->assertStringContainsString('Schöne Grüße<sup class="footnote-ref">', $html);
        $this->assertStringContainsString(' aus München.</p>', $html);
    }

    /*
     * renderSets(): a Bard field with sets, one jump target across all of it.
     */

    public function test_render_sets_renders_every_text_set_and_shares_the_jump_target(): void
    {
        $rendered = Footnotes::renderSets([
            ['type' => 'text', 'text' => '<p>Intro [1].</p>'],
            ['type' => 'quote', 'quote' => 'Typed words.'],
            ['type' => 'text', 'text' => '<p>More [1].</p>'],
        ], 1);

        $this->assertSame(
            '<p>Intro <sup class="footnote-ref"><a href="#fn-1" id="fnref-1" aria-label="Footnote 1">1</a></sup>.</p>',
            $rendered[0]['text'],
        );
        $this->assertSame(['type' => 'quote', 'quote' => 'Typed words.'], $rendered[1]);
        $this->assertSame(
            '<p>More <sup class="footnote-ref"><a href="#fn-1" aria-label="Footnote 1">1</a></sup>.</p>',
            $rendered[2]['text'],
        );
    }

    /*
     * renderValue(): whatever a Bard field hands over, without a cast.
     */

    public function test_render_value_takes_unknown_values_without_a_warning(): void
    {
        $this->assertSame('', Footnotes::renderValue(null, 1));
        $this->assertSame('42', Footnotes::renderValue(42, 1));
        $this->assertSame('', Footnotes::renderValue(new stdClass, 1));
        $this->assertSame('<p>[1]</p>', Footnotes::renderValue('<p>[1]</p>', 0));
    }

    public function test_render_value_unwraps_collections_of_sets(): void
    {
        $rendered = Footnotes::renderValue(new Collection([
            ['type' => 'text', 'text' => '<p>Intro [1].</p>'],
        ]), 0);

        $this->assertSame('<p>Intro [1].</p>', $rendered[0]['text']);
    }

    /*
     * sources().
     */

    public function test_sources_drop_empty_rows_renumber_and_clean_urls(): void
    {
        $this->assertSame([
            ['number' => 1, 'text' => 'First', 'url' => 'https://example.com/a'],
            ['number' => 2, 'text' => 'Second', 'url' => null],
        ], Footnotes::sources([
            ['text' => '   ', 'url' => 'https://example.com'],
            ['text' => ' First ', 'url' => 'https://example.com/a'],
            ['text' => 'Second', 'url' => 'javascript:alert(1)'],
        ]));
    }

    public function test_sources_keep_an_uppercase_https_url(): void
    {
        $sources = Footnotes::sources([['text' => 'A', 'url' => 'HTTPS://EXAMPLE.COM/a']]);

        $this->assertSame('HTTPS://EXAMPLE.COM/a', $sources[0]['url']);
    }

    public function test_sources_accept_values_and_collections(): void
    {
        $value = new Value([['text' => 'Via value', 'url' => null]], 'sources', new Grid);

        $this->assertSame('Via value', Footnotes::sources($value)[0]['text']);

        $collection = new Collection([['text' => 'Via collection', 'url' => null]]);

        $this->assertSame('Via collection', Footnotes::sources($collection)[0]['text']);
    }

    public function test_sources_reject_everything_that_is_not_a_row_list(): void
    {
        $this->assertSame([], Footnotes::sources(null));
        $this->assertSame([], Footnotes::sources('nope'));
        $this->assertSame([], Footnotes::sources('3'));
    }

    /*
     * cited().
     */

    public function test_cited_reads_the_rendered_html(): void
    {
        $html = Footnotes::render('<p>a [1]</p>', 1);

        $this->assertTrue(Footnotes::cited($html, 1));
        $this->assertFalse(Footnotes::cited($html, 2));
        $this->assertFalse(Footnotes::cited('<p>a [1]</p>', 1));
    }
}
