/**
 * Bard Footnotes — control panel entry point.
 *
 * Registers the `footnote` inline node (always: a field without the
 * button must still display the footnotes it already carries) and the
 * toolbar button (only in fields whose `buttons` list contains
 * `footnote`, exactly like a core button).
 */

import FootnoteNodeView from './components/FootnoteNodeView.vue';
import FootnoteToolbarButton from './components/FootnoteToolbarButton.vue';
import { positionsCiting } from './footnotes.js';

Statamic.booting(() => {
    Statamic.$components.register('FootnoteToolbarButton', FootnoteToolbarButton);

    Statamic.$bard.addExtension(({ tiptap }) => {
        const { Node } = tiptap.core;

        return Node.create({
            name: 'footnote',
            inline: true,
            group: 'inline',
            atom: true,

            addAttributes() {
                return {
                    text: { default: '' },
                    url: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('data-url') || null,
                    },
                };
            },

            parseHTML() {
                return [
                    {
                        tag: 'sup[data-footnote]',
                        getAttrs: (element) => ({
                            text: element.getAttribute('data-text') || '',
                            url: element.getAttribute('data-url') || null,
                        }),
                    },
                ];
            },

            renderHTML({ node }) {
                return [
                    'sup',
                    {
                        'data-footnote': '',
                        'data-text': node.attrs.text,
                        'data-url': node.attrs.url,
                        class: 'footnote-ref',
                    },
                ];
            },

            addNodeView() {
                return tiptap.vue3.VueNodeViewRenderer(FootnoteNodeView);
            },

            addCommands() {
                return {
                    insertFootnote: (attrs) => ({ chain }) =>
                        chain().focus().insertContent({ type: this.name, attrs }).run(),

                    // Editing a source edits every place citing it: one
                    // transaction over every node with the old key (attrs
                    // never change a node's size, so the positions stay
                    // valid while the loop runs).
                    updateFootnoteSource: (key, attrs) => ({ state, dispatch }) => {
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
                    },
                };
            },
        });
    });

    Statamic.$bard.buttons((buttons) => {
        // The configured name arrives as a plain string (there is no core
        // button called footnote); it takes this button's place, so the
        // toolbar keeps the order the blueprint configured.
        const index = buttons.findIndex((button) =>
            typeof button === 'string' ? button === 'footnote' : button?.name === 'footnote',
        );

        if (index === -1) {
            return;
        }

        buttons[index] = {
            name: 'footnote',
            text: __('bard-footnotes::messages.button'),
            component: 'FootnoteToolbarButton',
            command: (editor) => editor.commands.insertFootnote(),
        };
    });
});
