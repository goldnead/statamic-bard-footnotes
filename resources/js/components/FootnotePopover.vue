<script>
import { Button, Description, Field, Input, Select, Stack, StackContent, StackFooter } from '@statamic/cms/ui';
import { sourceKey } from '../footnotes.js';

/**
 * The footnote form, in the same slide-out Stack Bard's own link button
 * uses. Pure presentational: the parents own the editor. Emits
 * `apply({ text, url })` and `remove`, and closes itself.
 */
export default {
    components: { Button, Description, Field, Input, Select, Stack, StackContent, StackFooter },

    props: {
        open: { type: Boolean, default: false },
        text: { type: String, default: '' },
        url: { type: String, default: null },
        existingSources: { type: Array, default: () => [] },
        showRemove: { type: Boolean, default: false },
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

            this.$emit('apply', { text: this.sourceText.trim(), url: url === '' ? null : url });
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
        :title="__('bard-footnotes::messages.button')"
        size="narrow"
        inset
        :wrap-slot="false"
        @update:open="$emit('update:open', $event)"
    >
        <template #trigger>
            <slot name="trigger" />
        </template>

        <StackContent class="space-y-5">
            <Field
                v-if="existingSources.length"
                :label="__('bard-footnotes::messages.source')"
            >
                <Select
                    v-model="source"
                    :options="options"
                />
                <!-- Editing a reused source changes every place citing it;
                     "Remove Footnote" below only removes this one. -->
                <Description
                    v-if="selectedSource?.count > 1"
                    class="mt-2"
                    :text="__('bard-footnotes::messages.reused_source', { count: selectedSource.count })"
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
                    :text="__('bard-footnotes::messages.apply_footnote')"
                    :disabled="!canApply"
                    @click="apply"
                />
            </template>
        </StackFooter>
    </Stack>
</template>
