import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

// No Vue test harness in this repo (no @vue/test-utils, no jsdom), so this
// pins the template contract: the source field is a multi-line Textarea,
// and Enter applies while Shift+Enter keeps its line break.
const sfc = readFileSync(
    new URL('../../resources/js/components/FootnotePopover.vue', import.meta.url),
    'utf8',
);
const template = sfc.slice(sfc.indexOf('<template>'));
const field = template.match(/<(\w+)\s[^>]*ref="sourceText"[^>]*>/s);

test('the source field is a multi-line Textarea', () => {
    assert.ok(field, 'no element with ref="sourceText" in the template');
    assert.equal(field[1], 'Textarea');
});

test('Enter applies the footnote, Shift+Enter is left to the textarea', () => {
    assert.ok(field, 'no element with ref="sourceText" in the template');
    assert.match(field[0], /@keydown\.enter\.exact\.prevent="apply"/);
});
