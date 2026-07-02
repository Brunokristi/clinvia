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
    showDelete: {
        type: Boolean,
        default: false,
    },
    deleteDisabled: {
        type: Boolean,
        default: false,
    },
    isRepeatable: {
        type: Boolean,
        default: false,
    },
    occurrenceDate: {
        type: String,
        default: '',
    },
    deleteCountOccurrence: {
        type: Number,
        default: 1,
    },
    deleteCountFromDate: {
        type: Number,
        default: null,
    },
    deleteCountSeries: {
        type: Number,
        default: null,
    },
    showDuplicate: {
        type: Boolean,
        default: false,
    },
    duplicateLabel: {
        type: String,
        default: 'Duplikovať',
    },
    scopeMode: {
        type: String,
        default: 'update',
    },
    scopeSubjectLabel: {
        type: String,
        default: 'termín',
    },
    showDateTimeFields: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'duplicate',
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
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
        :date-time-disabled="true"
        :loading="loading"
        :show-save="false"
        :show-delete="showDelete"
        :delete-disabled="deleteDisabled"
        :is-repeatable="isRepeatable"
        :occurrence-date="occurrenceDate"
        :delete-count-occurrence="deleteCountOccurrence"
        :delete-count-from-date="deleteCountFromDate"
        :delete-count-series="deleteCountSeries"
        :show-duplicate="showDuplicate"
        :duplicate-label="duplicateLabel"
        :scope-mode="scopeMode"
        :scope-subject-label="scopeSubjectLabel"
        :show-date-time-fields="showDateTimeFields"
        @update:visible="emit('update:visible', $event)"
        @close="emit('close')"
        @duplicate="emit('duplicate')"
        @delete-occurrence="emit('delete-occurrence')"
        @delete-from-now-on="emit('delete-from-now-on')"
        @delete-all="emit('delete-all')"
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
