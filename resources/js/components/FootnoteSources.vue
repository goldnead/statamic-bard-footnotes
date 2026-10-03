<script>
import { Button, Icon } from '@statamic/cms/ui';
import FootnotePopover from './FootnotePopover.vue';
import { distinctSources, isHttpUrl, nextCitation } from '../footnotes.js';

/**
 * The source overview under the editor: every source of this field, in
 * number order, with how often it is cited. Edit opens the same stack the
 * footnote itself opens, but for the SOURCE — Apply changes every place
 * citing it. Jump selects the first citation in the text; clicking again
 * moves on to the next one.
 *
 * Rendered by sourcesPanel.js through TipTap's VueRenderer, so it shares
 * the app context and the provides of Bard's EditorContent (the injected
 * `bard`, the portals the stack needs) exactly like a node view.
 *
 * There is deliberately no "remove" here: like every Bard content, a
 * footnote is removed where it stands in the text.
 */
export default {
    components: { Button, Icon, FootnotePopover },

    inject: { bard: { default: null } },

    props: {
        editor: { type: Object, required: true },
    },

    data() {
        return { sources: [], editable: true, editing: null, showing: false };
    },

    computed: {
        readOnly() {
            return this.bard ? this.bard.isReadOnly : !this.editable;
        },
    },

    mounted() {
        this.refresh();
        this.editor.on('update', this.refresh);
    },

    beforeUnmount() {
        this.editor.off('update', this.refresh);
    },

    methods: {
        // The ProseMirror document is not reactive: read on every update.
        refresh() {
            this.sources = distinctSources(this.editor.state.doc);
            this.editable = this.editor.isEditable;
        },
        label(source) {
            return source.text || source.url;
        },
        link(source) {
            return isHttpUrl(source.url) ? source.url.trim() : null;
        },
        edit(source) {
            this.editing = source;
            this.showing = true;
        },
        apply({ text, url }) {
            // Whatever the select shows: the whole source changes. Picking
            // another source therefore merges this one into it.
            if (this.editing) {
                this.editor.commands.updateFootnoteSource(this.editing.key, { text, url });
            }
        },
        jump(source) {
            const { state, view } = this.editor;
            const pos = nextCitation(state.doc, source.key, state.selection.from);

            if (pos === null) {
                return;
            }

            const chain = this.editor.chain().setNodeSelection(pos);

            if (!this.readOnly) {
                chain.focus(null, { scrollIntoView: false });
            }

            chain.run();

            // Centered rather than ProseMirror's edge-scroll: the CP's
            // sticky header would cover a footnote scrolled to the top.
            view.nodeDOM(pos)?.scrollIntoView?.({ block: 'center', inline: 'nearest' });
        },
    },
};
</script>

<template>
    <div
        v-if="sources.length"
        class="bard-footnotes-sources"
    >
        <div class="mb-1 font-medium text-gray-900 dark:text-gray-300">
            {{ __('bard-footnotes::messages.sources_heading', { count: sources.length }) }}
        </div>
        <ol>
            <li
                v-for="source in sources"
                :key="source.key"
                class="flex min-h-7 items-center gap-2"
            >
                <span class="w-5 shrink-0 text-end tabular-nums">{{ source.number }}.</span>
                <span class="flex min-w-0 flex-1 items-center gap-1">
                    <span
                        class="truncate text-gray-900 dark:text-gray-100"
                        :title="label(source)"
                    >{{ label(source) }}</span>
                    <a
                        v-if="link(source)"
                        :href="link(source)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="shrink-0 text-gray-500 hover:text-gray-900 dark:hover:text-gray-100"
                        :aria-label="__('bard-footnotes::messages.open_link')"
                        v-tooltip="link(source)"
                    >
                        <Icon
                            name="arrow-up-right"
                            class="size-3"
                        />
                    </a>
                </span>
                <span
                    class="shrink-0 tabular-nums"
                    :title="__('bard-footnotes::messages.cited_times', { count: source.count })"
                >{{ __('bard-footnotes::messages.cited_count', { count: source.count }) }}</span>
                <Button
                    variant="ghost"
                    size="xs"
                    :text="__('bard-footnotes::messages.jump')"
                    v-tooltip="source.count > 1 ? __('bard-footnotes::messages.jump_next') : null"
                    @click="jump(source)"
                />
                <Button
                    v-if="!readOnly"
                    variant="ghost"
                    size="xs"
                    :text="__('bard-footnotes::messages.edit')"
                    @click="edit(source)"
                />
            </li>
        </ol>
        <FootnotePopover
            v-if="!readOnly"
            v-model:open="showing"
            :title="__('bard-footnotes::messages.edit_source')"
            :text="editing?.text ?? ''"
            :url="editing?.url ?? null"
            :existing-sources="sources"
            :own-key="editing?.key ?? null"
            whole-source
            @apply="apply"
        />
    </div>
</template>
