import test from 'node:test';
import assert from 'node:assert/strict';

import { collectFootnotes, distinctSources, positionsCiting, sourceKey } from '../../resources/js/footnotes.js';

/*
 * A minimal stand-in for a ProseMirror document: descendants() walking
 * the nodes in document order with positions, like the real one.
 */
function doc(nodes) {
    return {
        descendants(callback) {
            let pos = 0;

            const walk = (children) => {
                for (const child of children) {
                    const open = pos++;

                    if (child.content) {
                        walk(child.content);
                    }

                    callback(child, open);
                }
            };

            walk(nodes);
        },
    };
}

const footnote = (text, url = null) => ({ type: { name: 'footnote' }, attrs: { text, url } });
const text = (t) => ({ type: { name: 'text' }, text: t });
const paragraph = (...content) => ({ type: { name: 'paragraph' }, content });

test('sourceKey prefers the trimmed url over the text', () => {
    assert.equal(sourceKey({ text: 'One', url: 'https://example.com/a ' }), 'https://example.com/a');
    assert.equal(sourceKey({ text: 'One', url: 'https://example.com/a' }), sourceKey({ text: 'Different', url: 'https://example.com/a' }));
});

test('sourceKey collapses whitespace and case in the text', () => {
    assert.equal(sourceKey({ text: 'Example  Source' }), sourceKey({ text: 'example   source' }));
    assert.notEqual(sourceKey({ text: 'One' }), sourceKey({ text: 'Two' }));
});

test('collectFootnotes numbers by first occurrence across the document', () => {
    const collected = collectFootnotes(doc([
        paragraph(text('Three weeks'), footnote('Smith, p. 12'), text('of work'), footnote('Jones')),
        paragraph(footnote('smith, p. 12')),
    ]));

    assert.deepEqual(
        collected.map((f) => [f.number, f.text, f.url]),
        [[1, 'Smith, p. 12', null], [2, 'Jones', null], [1, 'smith, p. 12', null]],
    );
});

test('distinctSources lists each source once, in number order, with its citation count', () => {
    const sources = distinctSources(doc([
        paragraph(footnote('Smith, p. 12'), footnote('Jones')),
        paragraph(footnote('SMITH, p. 12'), footnote('Jones')),
    ]));

    assert.deepEqual(
        sources.map((s) => [s.number, s.text, s.count]),
        [[1, 'Smith, p. 12', 2], [2, 'Jones', 2]],
    );
});

test('positionsCiting returns every node of a source, and none for an unknown one', () => {
    const document = doc([
        paragraph(footnote('Smith, p. 12'), footnote('Jones')),
        paragraph(footnote('smith, p. 12'), footnote('Miller')),
    ]);
    const smithPositions = collectFootnotes(document)
        .filter((f) => f.text.toLowerCase() === 'smith, p. 12')
        .map((f) => f.pos);

    // Editing the reused Smith source must reach both of its nodes —
    // these are the targets the updateFootnoteSource command rewrites.
    assert.deepEqual(positionsCiting(document, sourceKey({ text: 'SMITH, p. 12' })), smithPositions);
    assert.equal(smithPositions.length, 2);

    assert.deepEqual(positionsCiting(document, sourceKey({ text: 'Nobody cites this' })), []);
});
