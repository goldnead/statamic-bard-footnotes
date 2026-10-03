import test from 'node:test';
import assert from 'node:assert/strict';

import { Schema } from 'prosemirror-model';
import { EditorState, TextSelection } from 'prosemirror-state';

import {
    footnoteRenderSpec,
    insertFootnoteAtEnd,
    resolveApply,
    reusedCount,
    setFootnoteAttrs,
    sourceKey,
    updateFootnoteSource,
    distinctSources,
    collectFootnotes,
} from '../../resources/js/footnotes.js';

/*
 * The commands against a REAL ProseMirror schema: positions, one
 * transaction, only the nodes that are meant.
 */
const schema = new Schema({
    nodes: {
        doc: { content: 'block+' },
        paragraph: { group: 'block', content: 'inline*' },
        text: { group: 'inline' },
        footnote: {
            inline: true,
            group: 'inline',
            atom: true,
            attrs: { text: { default: '' }, url: { default: null } },
        },
    },
});

const fn = (text, url = null) => schema.nodes.footnote.create({ text, url });
const p = (...content) => schema.nodes.paragraph.create(null, content);
const t = (s) => schema.text(s);

function stateOf(...paragraphs) {
    return EditorState.create({ schema, doc: schema.nodes.doc.create(null, paragraphs) });
}

/** Runs a command the way TipTap does: state + dispatch collecting transactions. */
function run(command, state) {
    const transactions = [];
    const tr = state.tr;
    const result = command({ state, tr, dispatch: (t) => transactions.push(t) });

    // TipTap dispatches the shared `tr` itself when a command only edits it.
    if (transactions.length === 0 && tr.docChanged) {
        transactions.push(tr);
    }

    return { result, transactions, next: transactions.length ? state.apply(transactions[0]) : state };
}

const attrsOf = (state) => collectFootnotes(state.doc).map((f) => [f.text, f.url]);

/* Befund 1: insert at the END of the selection, selected text stays. */

test('insertFootnoteAtEnd keeps the selected text and inserts after it', () => {
    const base = stateOf(p(t('Three weeks of work')));
    // select "weeks" (positions 7..12 inside the paragraph that starts at 0)
    const state = base.apply(base.tr.setSelection(TextSelection.create(base.doc, 7, 12)));

    const { result, transactions, next } = run(insertFootnoteAtEnd(schema.nodes.footnote, { text: 'Smith', url: null }), state);

    assert.equal(result, true);
    assert.equal(transactions.length, 1);
    assert.equal(next.doc.textContent, 'Three weeks of work');
    assert.deepEqual(next.doc.firstChild.content.toJSON().map((n) => n.type + (n.text ? `:${n.text}` : '')), [
        'text:Three weeks',
        'footnote',
        'text: of work',
    ]);
    assert.equal(attrsOf(next)[0][0], 'Smith');
    // the cursor continues right after the footnote (12 + node size 1)
    assert.equal(next.selection.empty, true);
    assert.equal(next.selection.from, 13);
});

test('insertFootnoteAtEnd at a plain cursor inserts at the cursor', () => {
    const base = stateOf(p(t('abc')));
    const state = base.apply(base.tr.setSelection(TextSelection.create(base.doc, 2)));

    const { next } = run(insertFootnoteAtEnd(schema.nodes.footnote, { text: 'X', url: null }), state);

    assert.equal(next.doc.firstChild.child(0).text, 'a');
    assert.equal(next.doc.firstChild.child(1).type.name, 'footnote');
    assert.equal(next.doc.firstChild.child(2).text, 'bc');
});

/* Befund 2: updateFootnoteSource / setFootnoteAttrs. */

test('updateFootnoteSource rewrites every node of the old key in ONE transaction, and only those', () => {
    const state = stateOf(
        p(t('a'), fn('Smith, p. 12'), t('b'), fn('Jones')),
        p(fn('SMITH,  p. 12'), t('c'), fn('Miller')),
    );

    const { result, transactions, next } = run(
        updateFootnoteSource(sourceKey({ text: 'smith, p. 12' }), { text: 'Smith, p. 13', url: null }),
        state,
    );

    assert.equal(result, true);
    assert.equal(transactions.length, 1);
    assert.deepEqual(attrsOf(next), [
        ['Smith, p. 13', null],
        ['Jones', null],
        ['Smith, p. 13', null],
        ['Miller', null],
    ]);
    // text between the nodes is untouched, the document keeps its size
    assert.equal(next.doc.textContent, 'abc');
    assert.equal(next.doc.content.size, state.doc.content.size);
});

test('updateFootnoteSource returns false for an unknown key and dispatches nothing', () => {
    const state = stateOf(p(fn('Jones')));
    const { result, transactions } = run(updateFootnoteSource('nobody', { text: 'x', url: null }), state);

    assert.equal(result, false);
    assert.equal(transactions.length, 0);
});

test('setFootnoteAttrs changes exactly the node at the position', () => {
    const state = stateOf(p(fn('Smith'), t('x'), fn('Smith')));
    const [first, second] = collectFootnotes(state.doc);

    const { result, transactions, next } = run(setFootnoteAttrs(second.pos, { text: 'Jones', url: 'https://example.com' }), state);

    assert.equal(result, true);
    assert.equal(transactions.length, 1);
    assert.equal(first.pos < second.pos, true);
    assert.deepEqual(attrsOf(next), [['Smith', null], ['Jones', 'https://example.com']]);
});

test('setFootnoteAttrs refuses a position that is not a footnote', () => {
    const state = stateOf(p(t('abc')));
    const { result, transactions } = run(setFootnoteAttrs(0, { text: 'x', url: null }), state);

    assert.equal(result, false);
    assert.equal(transactions.length, 0);
});

test('a real document: empty footnotes are not collected, positions are real', () => {
    const state = stateOf(p(t('a'), fn(''), fn('Smith'), fn(' ', '')));

    const collected = collectFootnotes(state.doc);

    assert.deepEqual(collected.map((f) => [f.number, f.text]), [[1, 'Smith']]);
    assert.equal(state.doc.nodeAt(collected[0].pos).attrs.text, 'Smith');
    assert.deepEqual(distinctSources(state.doc).map((s) => s.count), [1]);
});

/* Befund 2: which target does Apply hit? */

const smith = { text: 'Smith', url: null };
const smithKey = sourceKey(smith);
const jonesKey = sourceKey({ text: 'Jones' });

test('resolveApply: another existing source re-points only this node', () => {
    assert.deepEqual(
        resolveApply({ node: smith, selectedKey: jonesKey, attrs: { text: 'Jones', url: null } }),
        { mode: 'node', attrs: { text: 'Jones', url: null } },
    );
});

test('resolveApply: "New source" re-points only this node', () => {
    assert.deepEqual(
        resolveApply({ node: smith, selectedKey: null, attrs: { text: 'Brand new', url: null } }),
        { mode: 'node', attrs: { text: 'Brand new', url: null } },
    );
});

test('resolveApply: the own source stays selected, edited: every node of the old key', () => {
    assert.deepEqual(
        resolveApply({ node: smith, selectedKey: smithKey, attrs: { text: 'Smith, p. 13', url: null } }),
        { mode: 'source', key: smithKey, attrs: { text: 'Smith, p. 13', url: null } },
    );
});

test('resolveApply: a node without a source of its own has only the node-level path', () => {
    assert.equal(
        resolveApply({ node: { text: '', url: null }, selectedKey: jonesKey, attrs: { text: 'Jones', url: null } }).mode,
        'node',
    );
});

/* Befund 2: the hint. */

const sources = [
    { key: smithKey, number: 1, count: 3 },
    { key: jonesKey, number: 2, count: 1 },
];

test('reusedCount counts the OPENED node\'s source, only while it is selected, only when reused', () => {
    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: smithKey, sources }), 3);
    // another source chosen: no hint, even though that one could be reused
    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: jonesKey, sources }), 0);
    // own source is cited once: no hint
    assert.equal(reusedCount({ ownKey: jonesKey, selectedKey: jonesKey, sources }), 0);
    // new source selected / toolbar (no own key): no hint
    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: null, sources }), 0);
    assert.equal(reusedCount({ ownKey: null, selectedKey: smithKey, sources }), 0);
});

/* Befund 5: save_html output is readable. */

test('footnoteRenderSpec renders the source readably, not an empty sup', () => {
    const [tag, attrs, content] = footnoteRenderSpec({ text: 'Smith, p. 12', url: 'https://example.com/s' });

    assert.equal(tag, 'sup');
    assert.equal(attrs['data-footnote'], '');
    assert.equal(attrs['data-text'], 'Smith, p. 12');
    assert.equal(attrs['data-url'], 'https://example.com/s');
    assert.equal(content, '[Smith, p. 12]');
});
