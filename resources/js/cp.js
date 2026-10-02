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
