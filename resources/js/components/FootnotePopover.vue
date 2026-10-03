<script>
import { Button, Description, Field, Input, Select, Stack, StackContent, StackFooter } from '@statamic/cms/ui';
import { reusedCount, sourceKey } from '../footnotes.js';

/**
 * The footnote form, in the same slide-out Stack Bard's own link button
 * uses. Pure presentational: the parents own the editor. Emits
 * `apply({ text, url, source })` — `source` is the key of the source
 * picked in the select, null for "New source" — and `remove`, and closes
 * itself. `ownKey` is the source of the footnote being edited (null for
 * the toolbar, which inserts a new one).
 */
export default {
    components: { Button, Description, Field, Input, Select, Stack, StackContent, StackFooter },

    props: {
        open: { type: Boolean, default: false },
        text: { type: String, default: '' },
        url: { type: String, default: null },
        existingSources: { type: Array, default: () => [] },
        ownKey: { type: String, default: null },
        showRemove: { type: Boolean, default: false },
        // Opened from the source overview: the stack edits one source,
        // every place citing it (the parent applies it so), and says so.
        // No select then: nothing can be re-pointed or merged from here.
        wholeSource: { type: Boolean, default: false },
        title: { type: String, default: null },
    },

    emits: ['update:open', 'apply', 'remove'],

    data() {
        return { source: 'new', sourceText: this.text, sourceUrl: this.url ?? '' };
    },

    computed: {
        options() {
            return [
                { value: 'new', label: __('bard-footnotes::messages.new_source') },
                ...this.existingSources.map((source) => ({
                    value: this.optionValue(source.key),
                    label: `${source.number}. ${source.text || source.url}`,
                })),
            ];
        },
        // The select's value is prefixed so no source key — any text is a
        // possible one — can collide with the "new" sentinel.
        selectedSource() {
            return this.existingSources.find((source) => this.optionValue(source.key) === this.source) ?? null;
        },
        // "Used N times": the opened footnote's own source, only while
        // that one is selected (0 = no hint).
        reused() {
            return reusedCount({
                ownKey: this.ownKey,
                selectedKey: this.selectedSource?.key ?? null,
                sources: this.existingSources,
                wholeSource: this.wholeSource,
            });
        },
        validUrl() {
            const url = this.sourceUrl.trim();

            return url === '' || /^https?:\/\//i.test(url);
        },
        canApply() {
            return this.sourceText.trim() !== '' && this.validUrl;
        },
    },

    watch: {
        // Fresh values every time it opens, the select on the source the
        // footnote already cites — "New source" only without a match.
        // Picking an existing source fills the fields, which stay editable.
        open(isOpen) {
            if (!isOpen) {
                return;
            }

            this.sourceText = this.text;
            this.sourceUrl = this.url ?? '';

            const key = sourceKey({ text: this.text, url: this.url });
            const cited = this.existingSources.find((source) => source.key === key);

            this.source = cited ? this.optionValue(cited.key) : 'new';
            this.focusSourceText();
        },
        source(value) {
            const existing = this.existingSources.find((source) => this.optionValue(source.key) === value);

            if (existing) {
                this.sourceText = existing.text;
                this.sourceUrl = existing.url ?? '';
            }
        },
    },

    methods: {
        optionValue(key) {
            return `source:${key}`;
        },
        // Core's LinkToolbar pattern: the Stack portal needs a tick plus
        // a beat before the input can take the focus.
        focusSourceText() {
            this.$nextTick(() => {
                setTimeout(() => this.$refs.sourceText?.focus(), 50);
            });
        },
        apply() {
            if (!this.canApply) {
                return;
            }

            const url = this.sourceUrl.trim();

            this.$emit('apply', {
                text: this.sourceText.trim(),
                url: url === '' ? null : url,
                source: this.selectedSource?.key ?? null,
            });
            this.close();
        },
        remove() {
            this.$emit('remove');
            this.close();
        },
        close() {
            this.$emit('update:open', false);
        },
    },
};
</script>

<template>
    <Stack
        :open="open"
        :title="title || __('bard-footnotes::messages.button')"
        size="narrow"
        inset
        :wrap-slot="false"
        @update:open="$emit('update:open', $event)"
    >
        <template #trigger>
            <slot name="trigger" />
        </template>

        <StackContent class="space-y-5">
            <!-- Editing a whole source (from the overview): no select — this
                 panel edits exactly that source, it never re-points or
                 merges. The hint is its only line above the fields. -->
            <Description
                v-if="wholeSource && reused > 0"
                :text="__('bard-footnotes::messages.reused_source', { count: reused })"
            />
            <Field
                v-if="!wholeSource && existingSources.length"
                :label="__('bard-footnotes::messages.source')"
            >
                <Select
                    v-model="source"
                    :options="options"
                />
                <!-- Editing the reused source of THIS footnote changes every
                     place citing it; picking another source or "Remove
                     Footnote" below only touches this one. -->
                <Description
                    v-if="reused > 0"
                    class="mt-2"
                    :text="__('bard-footnotes::messages.reused_source', { count: reused })"
                />
            </Field>
            <Input
                ref="sourceText"
                v-model="sourceText"
                type="text"
                autofocus
                :placeholder="__('bard-footnotes::messages.source_placeholder')"
                @keydown.enter.prevent="apply"
            />
            <Input
                v-model="sourceUrl"
                type="text"
                :placeholder="__('bard-footnotes::messages.link_placeholder')"
                @keydown.enter.prevent="apply"
            />
            <Description
                v-if="!validUrl"
                :text="__('bard-footnotes::messages.url_hint')"
            />
        </StackContent>

        <StackFooter>
            <template #end>
                <Button
                    variant="ghost"
                    :text="__('Cancel')"
                    @click="close"
                />
                <Button
                    v-if="showRemove"
                    :text="__('bard-footnotes::messages.remove_footnote')"
                    @click="remove"
                />
                <Button
                    variant="primary"
                    :text="wholeSource ? __('bard-footnotes::messages.apply_source') : __('bard-footnotes::messages.apply_footnote')"
                    :disabled="!canApply"
                    @click="apply"
                />
            </template>
        </StackFooter>
    </Stack>
</template>
