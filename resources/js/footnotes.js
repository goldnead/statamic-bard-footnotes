/**
 * The footnote numbering, as the editor shows it live.
 *
 * This is the JavaScript twin of Goldnead\BardFootnotes\Footnotes (PHP):
 * sourceKey() mirrors Footnotes::key(), collectFootnotes() walks the
 * document in reading order and numbers by first occurrence — the same
 * numbers the PHP augment hook writes before rendering. The two must
 * stay in step; change one, change the other.
 */

/**
 * The key that decides whether two footnote nodes are the same source:
 * the trimmed URL when one exists, otherwise the trimmed text with
 * whitespace collapsed, case-insensitive.
 */
export function sourceKey({ text = '', url = null } = {}) {
    const link = String(url ?? '').trim();

    if (link !== '') {
        return link;
    }

    return String(text ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
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
 * the "reuse a source" select.
 */
export function distinctSources(doc) {
    const seen = new Map();

    for (const footnote of collectFootnotes(doc)) {
        if (!seen.has(footnote.key)) {
            seen.set(footnote.key, footnote);
        }
    }

    return [...seen.values()];
}
