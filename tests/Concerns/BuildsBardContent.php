<?php

namespace Goldnead\BardFootnotes\Tests\Concerns;

use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;

/**
 * Builds a Bard value the way a real entry holds it: the stored ProseMirror
 * document plus a fieldtype whose config declares the sets. Augmenting that
 * value (Value::value()) produces exactly what Antlers hands to a modifier
 * or a tag parameter: a list of Values, text sets carrying `type => text`
 * and their HTML in `text`.
 */
trait BuildsBardContent
{
    /**
     * @param  list<array<string, mixed>>  $doc
     */
    private function bardValue(array $doc): Value
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
}
