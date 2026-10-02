<script>
import { Button, Description, Input, Select, Stack, StackContent, StackFooter } from '@statamic/cms/ui';

/**
 * The footnote form, in the same slide-out Stack Bard's own link button
 * uses. Pure presentational: the parents own the editor. Emits
 * `apply({ text, url })` and `remove`, and closes itself.
 */
export default {
    components: { Button, Description, Input, Select, Stack, StackContent, StackFooter },

    props: {
        open: { type: Boolean, default: false },
        text: { type: String, default: '' },
        url: { type: String, default: '' },
        existingSources: { type: Array, default: () => [] },
        showRemove: { type: Boolean, default: false },
    },

    emits: ['update:open', 'apply', 'remove'],

    data() {
        return { source: 'new', sourceText: this.text, sourceUrl: this.url };
    },

    computed: {
        options() {
            return [
                { value: 'new', label: __('bard-footnotes::messages.new_source') },
                ...this.existingSources.map((source) => ({
                    value: source.key,
                    label: `${source.number}. ${source.text || source.url}`,
                })),
            ];
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
        // Fresh values every time it opens; picking an existing source
        // fills the fields, which stay editable.
        open(isOpen) {
            if (!isOpen) {
                return;
            }

            this.sourceText = this.text;
            this.sourceUrl = this.url;
            this.source = 'new';
        },
        source(key) {
            const existing = this.existingSources.find((source) => source.key === key);

            if (existing) {
                this.sourceText = existing.text;
                this.sourceUrl = existing.url ?? '';
            }
        },
    },

    methods: {
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
            <Select
                v-if="existingSources.length"
                v-model="source"
                :options="options"
            />
            <Input
                v-model="sourceText"
                type="text"
                autofocus
                :placeholder="__('bard-footnotes::messages.source')"
                :prepend="__('bard-footnotes::messages.source')"
                @keydown.enter.prevent="apply"
            />
            <Input
                v-model="sourceUrl"
                type="text"
                placeholder="https://"
                :prepend="__('bard-footnotes::messages.link')"
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
