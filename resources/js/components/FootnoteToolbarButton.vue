<script>
import { Button, Icon } from '@statamic/cms/ui';
import { ToolbarButtonMixin } from '@statamic/cms/bard';
import FootnotePopover from './FootnotePopover.vue';
import { distinctSources } from '../footnotes.js';

/**
 * The toolbar button: opens the popover, and on apply inserts a footnote
 * at the current selection. Mirrors Bard's own link button (the mixin
 * provides the button/active/editor props; mousedown is prevented so the
 * editor keeps its selection while the popover has the focus).
 */
export default {
    mixins: [ToolbarButtonMixin],

    components: { Button, Icon, FootnotePopover },

    data() {
        return { showing: false, sources: [] };
    },

    watch: {
        showing(isOpen) {
            if (isOpen) {
                // The ProseMirror document is not reactive: read it when
                // the popover opens, not in a computed.
                this.sources = distinctSources(this.editor.state.doc);
            } else {
                this.editor.commands.focus();
            }
        },
    },

    methods: {
        // Inserts after the selection (selected text stays). Whatever the
        // select picked, the toolbar only ever inserts: the `source` key
        // is for the node view's edit decision.
        apply({ text, url }) {
            this.editor.chain().focus().insertFootnote({ text, url }).run();
        },
    },
};
</script>

<template>
    <FootnotePopover
        v-model:open="showing"
        :existing-sources="sources"
        @apply="apply"
    >
        <template #trigger>
            <Button
                class="px-2!"
                :class="{ active }"
                variant="ghost"
                size="sm"
                :aria-label="button.text"
                @mousedown.prevent
            >
                <Icon
                    name="bard-footnotes::footnote"
                    class="size-4"
                />
            </Button>
        </template>
    </FootnotePopover>
</template>
