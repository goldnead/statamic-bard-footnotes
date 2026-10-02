<?php

namespace Goldnead\BardFootnotes;

use Goldnead\BardFootnotes\Bard\FootnoteNode;
use Statamic\Facades\Icon;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Bard\Augmentor;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * The parent loads resources/views from this namespace (bootViews(),
     * available in Statamic 5 and 6), so `bard-footnotes::list` resolves.
     */
    protected $viewNamespace = 'bard-footnotes';

    /**
     * The parent would register lang/ under the addon slug. The translations
     * belong to the `bard-footnotes::` namespace, like the views, so they
     * are loaded under that name instead.
     */
    protected $translations = false;

    /**
     * The control panel bundle: the footnote toolbar button and node view.
     * `dist` is committed, so consumers never need a Node toolchain — they
     * publish it once (`vendor:publish --tag=statamic-bard-footnotes`).
     */
    protected $vite = [
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
        'publicDirectory' => 'dist',
    ];

    public function bootAddon()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bard-footnotes');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/bard-footnotes'),
        ], 'bard-footnotes-views');

        // The PHP render side: the node for the Augmentor, and the hook that
        // numbers every footnote of a field before the HTML is built.
        Augmentor::addExtension('footnote', fn () => new FootnoteNode);
        Bard::hook('augment', fn ($value, $next) => $next(Footnotes::number($value)));

        Icon::register('bard-footnotes', __DIR__.'/../resources/svg');
    }
}
