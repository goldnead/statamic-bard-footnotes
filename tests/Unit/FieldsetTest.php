<?php

namespace Goldnead\BardFootnotes\Tests\Unit;

use Goldnead\BardFootnotes\ServiceProvider;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Testing\AddonTestCase;

class FieldsetTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    public function test_the_fieldset_is_findable_under_its_namespace(): void
    {
        $fieldset = Fieldset::find('bard-footnotes::sources');

        $this->assertNotNull($fieldset);
        $this->assertSame('bard-footnotes::sources', $fieldset->handle());
        $this->assertArrayHasKey('sources', $fieldset->fields()->all());
    }

    public function test_the_fieldset_can_be_imported_into_a_blueprint(): void
    {
        $blueprint = Blueprint::make()->setContents([
            'fields' => [
                ['import' => 'bard-footnotes::sources'],
            ],
        ]);

        $field = $blueprint->fields()->get('sources');

        $this->assertNotNull($field);
        $this->assertSame('grid', $field->type());
        $this->assertSame('One source per row. Reference it in the text with [1], [2] … in the order of this list.', $field->instructions());
    }
}
