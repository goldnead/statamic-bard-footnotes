<script>
import FootnotePopover from './FootnotePopover.vue';
import { collectFootnotes, distinctSources, resolveApply, sourceKey } from '../footnotes.js';

/**
 * A footnote in the text: a superscript with its live number — computed
 * from the document on every update, the same numbering the PHP side
 * renders — and the source as its tooltip. Clicking opens the popover
 * to change or remove it.
 *
 * `NodeViewWrapper` is globally registered by Bard itself for exactly
 * this kind of third-party node view; no import needed (importing
 * @tiptap/vue-3 would inline a second TipTap into the bundle).
 */
export default {
    components: { FootnotePopover },

    props: {
        editor: { type: Object, required: true },
        node: { type: Object, required: true },
        selected: { type: Boolean, default: false },
        getPos: { type: Function, default: null },
        updateAttributes: { type: Function, default: null },
        deleteNode: { type: Function, required: true },
    },

    data() {
        return { number: null, sources: [], showing: false };
    },

    computed: {
        title() {
            const { text, url } = this.node.attrs;

            return url ? `${text} (${url})` : text;
        },
        ownKey() {
            return sourceKey(this.node.attrs) || null;
        },
    },

    watch: {
        showing(isOpen) {
            if (isOpen) {
                this.sources = distinctSources(this.editor.state.doc);
            }
        },
    },

    mounted() {
        this.update();
        this.editor.on('update', this.update);
    },

    beforeUnmount() {
        this.editor.off('update', this.update);
    },

    methods: {
        update() {
            const pos = typeof this.getPos === 'function' ? this.getPos() : null;
            const mine = collectFootnotes(this.editor.state.doc).find((f) => f.pos === pos);

            this.number = mine ? mine.number : null;
        },
        apply({ source, ...attrs }) {
            // Editing the footnote's own source edits the source: every
            // node citing the old key changes, in one transaction. Picking
            // another source (or a new one) re-points this node only, as
            // does Remove (deleteNode, below).
            const decision = resolveApply({ node: this.node.attrs, selectedKey: source, attrs });

            if (decision.mode === 'source') {
                this.editor.commands.updateFootnoteSource(decision.key, decision.attrs);
            } else {
                this.editor.commands.setFootnoteAttrs(this.getPos(), decision.attrs);
            }
        },
    },
};
</script>

<template>
    <NodeViewWrapper
        as="span"
        class="footnote-node"
    >
        <sup
            class="footnote-ref"
            :class="{ 'is-selected': selected }"
            v-tooltip="title"
            @click.stop="showing = true"
        >{{ number }}</sup>
        <FootnotePopover
            v-model:open="showing"
            :text="node.attrs.text"
            :url="node.attrs.url"
            :existing-sources="sources"
            :own-key="ownKey"
            show-remove
            @apply="apply"
            @remove="deleteNode"
        />
    </NodeViewWrapper>
</template>
