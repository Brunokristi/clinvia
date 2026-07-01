<script setup>
import ScopedEventDialog from '@/Components/Calendar/ScopedEventDialog.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    width: {
        type: String,
        default: 'max-w-3xl',
    },
    date: {
        type: [Date, String],
        default: null,
    },
    startsAt: {
        type: [Date, String],
        default: null,
    },
    endsAt: {
        type: [Date, String],
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    saveLabel: {
        type: String,
        default: 'Uložiť',
    },
    saveDisabled: {
        type: Boolean,
        default: false,
    },
    showSave: {
        type: Boolean,
        default: true,
    },
    isRepeatable: {
        type: Boolean,
        default: false,
    },
    scopeMode: {
        type: String,
        default: 'update',
    },
    scopeSubjectLabel: {
        type: String,
        default: 'termín',
    },
    showDuplicate: {
        type: Boolean,
        default: false,
    },
    duplicateLabel: {
        type: String,
        default: 'Duplikovať',
    },
});

const emit = defineEmits([
    'update:visible',
    'update:date',
    'update:startsAt',
    'update:endsAt',
    'close',
    'save',
    'save-scope',
    'duplicate',
]);
</script>

<template>
    <ScopedEventDialog
        :visible="visible"
        :title="title"
        :width="width"
        :date="date"
        :starts-at="startsAt"
        :ends-at="endsAt"
        :loading="loading"
        :save-label="saveLabel"
        :save-disabled="saveDisabled"
        :show-save="showSave"
        :is-repeatable="isRepeatable"
        :scope-mode="scopeMode"
        :scope-subject-label="scopeSubjectLabel"
        :show-duplicate="showDuplicate"
        :duplicate-label="duplicateLabel"
        @update:visible="emit('update:visible', $event)"
        @update:date="emit('update:date', $event)"
        @update:starts-at="emit('update:startsAt', $event)"
        @update:ends-at="emit('update:endsAt', $event)"
        @close="emit('close')"
        @save="emit('save')"
        @save-scope="emit('save-scope', $event)"
        @duplicate="emit('duplicate')"
    >
        <template #footer-start>
            <slot name="footer-start" />
        </template>

        <template #footer-actions>
            <slot name="footer-actions" />
        </template>

        <slot />
    </ScopedEventDialog>
</template>
