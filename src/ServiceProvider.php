<?php

namespace Goldnead\BardFootnotes;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * The parent loads resources/views and resources/fieldsets from these
     * namespaces (bootViews()/bootFieldsets(), available in Statamic 5 and 6),
     * so `bard-footnotes::list` and `bard-footnotes::sources` resolve.
     */
    protected $viewNamespace = 'bard-footnotes';

    protected $fieldsetNamespace = 'bard-footnotes';

    /**
     * The parent would register lang/ under the addon slug. The translations
     * belong to the `bard-footnotes::` namespace, like the views and the
     * fieldset, so they are loaded under that name instead.
     */
    protected $translations = false;

    public function bootAddon()
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bard-footnotes');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/bard-footnotes'),
        ], 'bard-footnotes-views');
    }
}
