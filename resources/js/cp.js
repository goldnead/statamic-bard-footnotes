/**
 * Bard Footnotes — control panel entry point.
 *
 * Registers the `footnote` inline node (always: a field without the
 * button must still display the footnotes it already carries) and the
 * toolbar button (only in fields whose `buttons` list contains
 * `footnote`, exactly like a core button), plus the source overview
 * under the editor (2.1), shown once a field cites a source.
 */

import FootnoteNodeView from './components/FootnoteNodeView.vue';
import FootnoteSources from './components/FootnoteSources.vue';
import FootnoteToolbarButton from './components/FootnoteToolbarButton.vue';
import {
    citationHighlightPlugin,
    footnoteRenderSpec,
    insertFootnoteAtEnd,
    setCitationHighlight,
    setFootnoteAttrs,
    updateFootnoteSource,
} from './footnotes.js';
import { sourcesPanelView } from './sourcesPanel.js';

Statamic.booting(() => {
    Statamic.$components.register('FootnoteToolbarButton', FootnoteToolbarButton);

    Statamic.$bard.addExtension(({ bard, tiptap }) => {
        const { Extension, Node } = tiptap.core;
        const { Plugin, PluginKey } = tiptap.pm.state;
        const { Decoration, DecorationSet } = tiptap.pm.view;

        // The source overview under the editor frame (see sourcesPanel.js):
        // a plugin view per editor, so it follows Bard's editor through
        // fullscreen and is gone with it. Registered for every Bard, like
        // the node; it renders only once the field cites a source.
        const sourcesOverview = Extension.create({
            name: 'footnoteSourcesOverview',

            addProseMirrorPlugins() {
                const editor = this.editor;

                return [
                    new Plugin({
                        key: new PluginKey('footnoteSourcesOverview'),
                        view: (view) =>
                            sourcesPanelView({
                                view,
                                editor,
                                bard,
                                VueRenderer: tiptap.vue3.VueRenderer,
                                component: FootnoteSources,
                            }),
                    }),
                    citationHighlightPlugin({ Plugin, Decoration, DecorationSet }),
                ];
            },

            addCommands() {
                return { setCitationHighlight };
            },
        });

        const footnote = Node.create({
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
                return footnoteRenderSpec(node.attrs);
            },

            addNodeView() {
                return tiptap.vue3.VueNodeViewRenderer(FootnoteNodeView);
            },

            addCommands() {
                return {
                    // At the END of the selection: selected text stays.
                    insertFootnote: (attrs) => ({ chain }) =>
                        chain().focus().command(insertFootnoteAtEnd(this.type, attrs)).run(),

                    // Editing a source edits every place citing it (one
                    // transaction); pointing one node elsewhere changes
                    // only that node. Both live in footnotes.js, where
                    // they are tested against a real ProseMirror schema.
                    updateFootnoteSource,
                    setFootnoteAttrs,
                };
            },
        });

        return [footnote, sourcesOverview];
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
