<script setup>
import Button from 'primevue/button';
import { computed, ref } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import OccurrenceScopeDialog from '@/Components/Booking/OccurrenceScopeDialog.vue';

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
    dateId: {
        type: String,
        default: 'event_date',
    },
    startsAtId: {
        type: String,
        default: 'event_starts_at',
    },
    endsAtId: {
        type: String,
        default: 'event_ends_at',
    },
    startsAtPlaceholder: {
        type: String,
        default: '',
    },
    endsAtPlaceholder: {
        type: String,
        default: '',
    },
    dateTimeDisabled: {
        type: Boolean,
        default: false,
    },
    showSave: {
        type: Boolean,
        default: true,
    },
    showCancel: {
        type: Boolean,
        default: true,
    },
    showDelete: {
        type: Boolean,
        default: false,
    },
    saveLabel: {
        type: String,
        default: 'Uložiť',
    },
    cancelLabel: {
        type: String,
        default: 'Zrušiť',
    },
    deleteLabel: {
        type: String,
        default: 'Odstrániť',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    saveDisabled: {
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
        default: 'reschedule',
        validator: (value) => ['reschedule', 'delete', 'update'].includes(value),
    },
    scopeSubjectLabel: {
        type: String,
        default: 'termín',
    },
    scopeOnSave: {
        type: Boolean,
        default: true,
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
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
]);

const scopeDialogVisible = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const dateModel = computed({
    get: () => props.date,
    set: (value) => emit('update:date', value),
});

const startsAtModel = computed({
    get: () => props.startsAt,
    set: (value) => emit('update:startsAt', value),
});

const endsAtModel = computed({
    get: () => props.endsAt,
    set: (value) => emit('update:endsAt', value),
});

const closeDialog = () => {
    scopeDialogVisible.value = false;
    emit('update:visible', false);
    emit('close');
};

const handleSave = () => {
    if (props.isRepeatable && props.scopeOnSave) {
        scopeDialogVisible.value = true;

        return;
    }

    emit('save');
};

const submitScope = (scope) => {
    scopeDialogVisible.value = false;
    emit('save-scope', scope);
};

const cancelScope = () => {
    scopeDialogVisible.value = false;
};

const duplicate = () => {
    emit('duplicate');
};
</script>

<template>
    <EventDialog
        v-model:visible="dialogVisible"
        :title="title"
        :width="width"
        v-model:date="dateModel"
        v-model:starts-at="startsAtModel"
        v-model:ends-at="endsAtModel"
        :date-id="dateId"
        :starts-at-id="startsAtId"
        :ends-at-id="endsAtId"
        :starts-at-placeholder="startsAtPlaceholder"
        :ends-at-placeholder="endsAtPlaceholder"
        :date-time-disabled="dateTimeDisabled"
        :show-save="showSave"
        :show-cancel="showCancel"
        :show-delete="showDelete"
        :save-label="saveLabel"
        :cancel-label="cancelLabel"
        :delete-label="deleteLabel"
        :loading="loading"
        :save-disabled="saveDisabled"
        :delete-disabled="deleteDisabled"
        :is-repeatable="isRepeatable"
        :occurrence-date="occurrenceDate"
        @close="closeDialog"
        @save="handleSave"
        @delete-occurrence="emit('delete-occurrence')"
        @delete-from-now-on="emit('delete-from-now-on')"
        @delete-all="emit('delete-all')"
    >
        <template #footer-start>
            <Button
                v-if="showDuplicate"
                type="button"
                :label="duplicateLabel"
                outlined
                @click="duplicate"
            />

            <slot name="footer-start" />
        </template>

        <template #footer-actions>
            <slot name="footer-actions" />
        </template>

        <slot />
    </EventDialog>

    <OccurrenceScopeDialog
        v-model:visible="scopeDialogVisible"
        :mode="scopeMode"
        :subject-label="scopeSubjectLabel"
        @select="submitScope"
        @cancel="cancelScope"
    />
</template>