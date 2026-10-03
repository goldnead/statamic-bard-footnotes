import test from 'node:test';
import assert from 'node:assert/strict';

import { Schema } from 'prosemirror-model';
import { EditorState, NodeSelection, Plugin, TextSelection } from 'prosemirror-state';
import { Decoration, DecorationSet } from 'prosemirror-view';

import {
    citationHighlightPlugin,
    collectFootnotes,
    positionsCiting,
    setCitationHighlight,
    distinctSources,
    hasFootnotes,
    isHttpUrl,
    nextCitation,
    reusedCount,
    sourceKey,
    updateFootnoteSource,
} from '../../resources/js/footnotes.js';

/*
 * The source overview under the editor (2.1): what it lists, where
 * "Jump" goes, what "Edit" changes — against a REAL ProseMirror schema.
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

const stateOf = (...paragraphs) => EditorState.create({ schema, doc: schema.nodes.doc.create(null, paragraphs) });

const select = (state, pos) => state.apply(state.tr.setSelection(NodeSelection.create(state.doc, pos)));

test('hasFootnotes: only a footnote with a source counts', () => {
    assert.equal(hasFootnotes(stateOf(p(t('plain'))).doc), false);
    assert.equal(hasFootnotes(stateOf(p(t('a'), fn(''), fn(' ', ''))).doc), false);
    assert.equal(hasFootnotes(stateOf(p(t('a'), fn('')), p(fn('Smith'))).doc), true);
    assert.equal(hasFootnotes(stateOf(p(fn('', 'https://example.com'))).doc), true);
});

test('the overview rows: one per source, in number order, with count and the first citation\'s text', () => {
    const state = stateOf(
        p(t('a'), fn('Jones'), t('b'), fn('Smith', 'https://example.com/s')),
        p(fn('JONES '), fn('Other text, same link', 'https://example.com/s'), fn('Miller'), fn('jones')),
    );

    assert.deepEqual(
        distinctSources(state.doc).map((s) => [s.number, s.text, s.url, s.count]),
        [
            [1, 'Jones', null, 3],
            [2, 'Smith', 'https://example.com/s', 2],
            [3, 'Miller', null, 1],
        ],
    );
});

test('nextCitation: first citation from anywhere, then the next on each click, wrapping round', () => {
    let state = stateOf(p(t('a'), fn('Smith'), t('b'), fn('Jones')), p(fn('smith'), t('c'), fn('Smith ')));
    const key = sourceKey({ text: 'Smith' });
    const smith = collectFootnotes(state.doc).filter((f) => f.key === key).map((f) => f.pos);

    assert.equal(smith.length, 3);

    // a text cursor somewhere else: the first place
    state = state.apply(state.tr.setSelection(TextSelection.create(state.doc, 1)));
    assert.equal(nextCitation(state.doc, key, state.selection.from), smith[0]);

    // the selection on a citation of this source: the next one, then wrap
    const visited = [];
    let pos = nextCitation(state.doc, key, state.selection.from);

    for (let i = 0; i < 4; i++) {
        visited.push(pos);
        state = select(state, pos);
        assert.equal(state.doc.nodeAt(state.selection.from).attrs.text.trim().toLowerCase(), 'smith');
        pos = nextCitation(state.doc, key, state.selection.from);
    }

    assert.deepEqual(visited, [smith[0], smith[1], smith[2], smith[0]]);
});

test('nextCitation: selected on ANOTHER source\'s footnote starts at this source\'s first', () => {
    let state = stateOf(p(fn('Jones'), fn('Smith'), fn('Smith')));
    const [jones, smith] = collectFootnotes(state.doc);

    state = select(state, jones.pos);

    assert.equal(nextCitation(state.doc, smith.key, state.selection.from), smith.pos);
});

test('nextCitation: a source no longer cited has no target', () => {
    assert.equal(nextCitation(stateOf(p(fn('Smith'))).doc, 'nobody', 0), null);
});

test('Edit Source from the overview changes every place of that source, and only those', () => {
    const state = stateOf(p(fn('Smith'), fn('Jones')), p(fn('smith')));
    const smithKey = sourceKey({ text: 'Smith' });

    const transactions = [];
    updateFootnoteSource(smithKey, { text: 'Smith, p. 13', url: null })({ state, dispatch: (tr) => transactions.push(tr) });

    assert.equal(transactions.length, 1);
    const next = state.apply(transactions[0]);

    assert.deepEqual(collectFootnotes(next.doc).map((f) => f.text), ['Smith, p. 13', 'Jones', 'Smith, p. 13']);
    assert.deepEqual(distinctSources(next.doc).map((s) => [s.number, s.text, s.count]), [[1, 'Smith, p. 13', 2], [2, 'Jones', 1]]);
});

test('reusedCount with wholeSource: the hint stays while another source is picked', () => {
    const smithKey = sourceKey({ text: 'Smith' });
    const jonesKey = sourceKey({ text: 'Jones' });
    const sources = [
        { key: smithKey, count: 2 },
        { key: jonesKey, count: 1 },
    ];

    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: jonesKey, sources, wholeSource: true }), 2);
    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: null, sources, wholeSource: true }), 2);
    assert.equal(reusedCount({ ownKey: jonesKey, selectedKey: jonesKey, sources, wholeSource: true }), 0);
    // without the flag, unchanged
    assert.equal(reusedCount({ ownKey: smithKey, selectedKey: jonesKey, sources }), 0);
});

/* The Edit Source panel marks every place of the source while it is open. */

const highlightPlugin = () => citationHighlightPlugin({ Plugin, Decoration, DecorationSet });

const highlighted = (state) =>
    citationHighlightPlugin.decorationsOf(state)
        .find()
        .map((d) => [d.from, d.type.attrs.class]);

test('citation highlight: off by default, marks every citation of the key, off again with null', () => {
    let state = EditorState.create({
        schema,
        doc: schema.nodes.doc.create(null, [p(t('a'), fn('Smith'), fn('Jones')), p(fn('smith'))]),
        plugins: [highlightPlugin()],
    });
    const smithKey = sourceKey({ text: 'Smith' });
    const smith = positionsCiting(state.doc, smithKey);

    assert.deepEqual(highlighted(state), []);

    const on = state.tr;
    setCitationHighlight(smithKey)({ tr: on });
    state = state.apply(on);

    assert.deepEqual(highlighted(state), smith.map((pos) => [pos, 'footnote-cited']));
    // a highlight is not an edit: nothing for undo
    assert.equal(on.getMeta('addToHistory'), false);

    // follows the document: a footnote added for the same source lights up too
    state = state.apply(state.tr.insert(1, fn('SMITH')));
    assert.equal(highlighted(state).length, 3);

    const off = state.tr;
    setCitationHighlight(null)({ tr: off });
    state = state.apply(off);

    assert.deepEqual(highlighted(state), []);
});

test('isHttpUrl: only http(s) links are clickable', () => {
    assert.equal(isHttpUrl('https://example.com'), true);
    assert.equal(isHttpUrl(' HTTP://example.com '), true);
    assert.equal(isHttpUrl('javascript:alert(1)'), false);
    assert.equal(isHttpUrl('example.com'), false);
    assert.equal(isHttpUrl(null), false);
});
