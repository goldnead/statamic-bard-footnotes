/**
 * The footnote numbering, as the editor shows it live.
 *
 * This is the JavaScript twin of Goldnead\BardFootnotes\Footnotes (PHP):
 * sourceKey() mirrors Footnotes::key(), collectFootnotes() walks the
 * document in reading order and numbers by first occurrence — the same
 * numbers the PHP augment hook writes before rendering. The two must
 * stay in step; change one, change the other.
 */

// "Whitespace" for keys: \s, every Unicode separator (\p{Z}, NBSP included),
// the BOM (U+FEFF) and NEL (U+0085). The same set as Footnotes::SPACE in PHP;
// tests/fixtures/keys.json pins both to the same answers.
const SPACE = '[\\s\\p{Z}\\uFEFF\\u0085]';
const SPACES = new RegExp(`${SPACE}+`, 'gu');
const EDGE_SPACES = new RegExp(`^${SPACE}+|${SPACE}+$`, 'gu');

const trimSpace = (value) => value.replace(EDGE_SPACES, '');

/**
 * The key that decides whether two footnote nodes are the same source:
 * the trimmed URL when one exists, otherwise the text with whitespace
 * trimmed at the edges and collapsed inside, case-insensitive. An empty
 * key means the footnote has no source at all: it is not numbered.
 */
export function sourceKey({ text = '', url = null } = {}) {
    const link = trimSpace(String(url ?? ''));

    if (link !== '') {
        return link;
    }

    return trimSpace(String(text ?? '').replace(SPACES, ' ')).toLowerCase();
}

/**
 * Every footnote node of a ProseMirror document, in reading order, each
 * with the number it renders as. Same source (same key), same number.
 *
 * @param {import("@tiptap/core").Editor} doc - editor.state.doc
 * @returns {{key: string, number: number, text: string, url: ?string, pos: number}[]}
 */
export function collectFootnotes(doc) {
    const numbers = new Map();
    const found = [];

    doc.descendants((node, pos) => {
        if (node.type.name !== 'footnote') {
            return true;
        }

        const attrs = { text: node.attrs.text ?? '', url: node.attrs.url ?? null };
        const key = sourceKey(attrs);

        // No text and no url: no source, no number (the PHP side renders
        // nothing for it either).
        if (key === '') {
            return false;
        }

        if (!numbers.has(key)) {
            numbers.set(key, numbers.size + 1);
        }

        found.push({ ...attrs, key, number: numbers.get(key), pos });

        return false;
    });

    return found;
}

/**
 * The number of the footnote node at a document position, or null.
 */
export function footnoteNumberAt(doc, pos) {
    const footnote = collectFootnotes(doc).find((f) => f.pos === pos);

    return footnote ? footnote.number : null;
}

/**
 * The distinct sources of a document, in number order — the options for
 * the "reuse a source" select — each with how often it is cited.
 */
export function distinctSources(doc) {
    const seen = new Map();

    for (const footnote of collectFootnotes(doc)) {
        const source = seen.get(footnote.key);

        if (source) {
            source.count++;
        } else {
            seen.set(footnote.key, { ...footnote, count: 1 });
        }
    }

    return [...seen.values()];
}

/**
 * The positions of every footnote node citing a source. Editing a reused
 * source changes all of them — one transaction, the old key picks the
 * targets (see the `updateFootnoteSource` command in cp.js).
 */
export function positionsCiting(doc, key) {
    return collectFootnotes(doc)
        .filter((footnote) => footnote.key === key)
        .map((footnote) => footnote.pos);
}

/**
 * The popover's Apply, as a decision: which nodes does it change?
 *
 * - another existing source selected, or "New source" (`selectedKey`
 *   null): only THIS node is re-pointed (`mode: 'node'`);
 * - the node's own source stays selected: the edit is an edit of the
 *   source itself, so every node citing the old key changes
 *   (`mode: 'source'`, `key` = the old key).
 *
 * @param {{node: {text: string, url: ?string}, selectedKey: ?string, attrs: {text: string, url: ?string}}} input
 */
export function resolveApply({ node, selectedKey, attrs }) {
    const ownKey = sourceKey(node);

    if (ownKey !== '' && selectedKey === ownKey) {
        return { mode: 'source', key: ownKey, attrs };
    }

    return { mode: 'node', attrs };
}

/**
 * How many places the hint "Used N times. Changes apply to every place."
 * speaks of: the citations of the OPENED node's own source, but only
 * while that source is the selected one, and only when it is reused.
 * Otherwise 0 (no hint).
 */
export function reusedCount({ ownKey, selectedKey, sources }) {
    if (!ownKey || selectedKey !== ownKey) {
        return 0;
    }

    const own = sources.find((source) => source.key === ownKey);

    return own && own.count > 1 ? own.count : 0;
}

/**
 * The editor command inserting a footnote at the END of the selection:
 * selected text stays where it is (a plain cursor is its own end).
 * Runs inside a TipTap chain: it only touches `tr`.
 */
export const insertFootnoteAtEnd = (nodeType, attrs) => ({ tr }) => {
    const node = nodeType.create(attrs);
    const at = tr.selection.to;

    tr.insert(at, node);
    // The cursor continues after the footnote, like after typed text.
    // `near` is inherited from ProseMirror's Selection by every selection class.
    tr.setSelection(tr.selection.constructor.near(tr.doc.resolve(at + node.nodeSize)));

    return true;
};

/**
 * The editor command behind "edit a source": every node of the old key
 * gets the new attrs, in ONE transaction (attrs never change a node's
 * size, so the positions stay valid while the loop runs).
 */
export const updateFootnoteSource = (key, attrs) => ({ state, dispatch }) => {
    const positions = positionsCiting(state.doc, key);

    if (positions.length === 0) {
        return false;
    }

    if (dispatch) {
        const tr = state.tr;

        for (const pos of positions) {
            const node = tr.doc.nodeAt(pos);
            tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...attrs });
        }

        dispatch(tr);
    }

    return true;
};

/**
 * The editor command re-pointing exactly one footnote node.
 */
export const setFootnoteAttrs = (pos, attrs) => ({ state, dispatch }) => {
    const node = state.doc.nodeAt(pos);

    if (!node || node.type.name !== 'footnote') {
        return false;
    }

    if (dispatch) {
        dispatch(state.tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...attrs }));
    }

    return true;
};

/**
 * The node's HTML spec for TipTap's renderHTML. The addon renders from the
 * JSON, so this only matters for fields saving HTML (`save_html: true`,
 * unsupported): the source stays readable instead of an empty <sup>.
 */
export function footnoteRenderSpec({ text, url }) {
    return [
        'sup',
        {
            'data-footnote': '',
            'data-text': text,
            'data-url': url,
            class: 'footnote-ref',
        },
        `[${text}]`,
    ];
}
