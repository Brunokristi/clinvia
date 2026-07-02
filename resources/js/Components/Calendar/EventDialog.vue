<script setup>
import Button from 'primevue/button';
import { computed, ref } from 'vue';

import AppDialog from '@/Components/Dialogs/FormDialog.vue';
import OccurrenceScopeDialog from '@/Components/Booking/Common/OccurrenceScopeDialog.vue';
import EventDateTimeFields from '@/Components/Calendar/EventDateTime.vue';

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
    dateTimeDisabled: {
        type: Boolean,
        default: false,
    },
    deleteDialogTitle: {
        type: String,
        default: 'Odstrániť termín',
    },
    deleteDialogDescription: {
        type: String,
        default: '',
    },
    deleteDialogImpactMessage: {
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
    showDateTimeFields: {
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
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
]);

const deleteDialogVisible = ref(false);
const deleteScopeDialogVisible = ref(false);
const selectedDeleteScope = ref('occurrence');

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
    emit('update:visible', false);
    emit('close');
};

const closeDeleteDialog = () => {
    deleteDialogVisible.value = false;
};

const closeDeleteScopeDialog = () => {
    deleteScopeDialogVisible.value = false;
};

const openDeleteDialog = () => {
    selectedDeleteScope.value = 'occurrence';

    if (props.isRepeatable) {
        deleteScopeDialogVisible.value = true;
        return;
    }

    deleteDialogVisible.value = true;
};

const getDeleteCountByScope = (scope) => {
    if (scope === 'from_date') {
        return props.deleteCountFromDate;
    }

    if (scope === 'series') {
        return props.deleteCountSeries;
    }

    return props.deleteCountOccurrence;
};

const getOccurrenceWord = (count) => {
    if (count === 1) {
        return 'výskyt';
    }

    if (count >= 2 && count <= 4) {
        return 'výskyty';
    }

    return 'výskytov';
};

const formatCountLabel = (count) => {
    const number = Number(count);

    if (!Number.isFinite(number) || number <= 0) {
        return null;
    }

    return `${number} ${getOccurrenceWord(number)}`;
};

const selectedDeleteCountLabel = computed(() => {
    return formatCountLabel(getDeleteCountByScope(selectedDeleteScope.value));
});

const deleteScopeLabel = computed(() => {
    if (selectedDeleteScope.value === 'from_date') {
        return 'tento a nasledujúce výskyty';
    }

    if (selectedDeleteScope.value === 'series') {
        return 'celú sériu';
    }

    return 'iba tento výskyt';
});

const deleteConfirmMessage = computed(() => {
    if (selectedDeleteCountLabel.value) {
        return `Naozaj chcete odstrániť ${selectedDeleteCountLabel.value}?`;
    }

    return 'Naozaj chcete odstrániť tento termín?';
});

const deleteSubMessage = computed(() => {
    if (props.isRepeatable) {
        return `Odstráni sa ${deleteScopeLabel.value}.`;
    }

    return 'Tento krok nie je možné vrátiť späť.';
});

const chooseDeleteScope = (scope) => {
    selectedDeleteScope.value = scope;
    deleteScopeDialogVisible.value = false;
    deleteDialogVisible.value = true;
};

const deleteOccurrence = () => {
    emit('delete-occurrence');
    closeDeleteDialog();
};

const deleteFromNowOn = () => {
    emit('delete-from-now-on');
    closeDeleteDialog();
};

const deleteAll = () => {
    emit('delete-all');
    closeDeleteDialog();
};

const confirmDelete = () => {
    if (selectedDeleteScope.value === 'from_date') {
        deleteFromNowOn();
        return;
    }

    if (selectedDeleteScope.value === 'series') {
        deleteAll();
        return;
    }

    deleteOccurrence();
};
</script>

<template>
    <AppDialog
        v-model:visible="dialogVisible"
        :title="title"
        :width="width"
        @close="closeDialog"
    >
        <div class="space-y-6">
            <EventDateTimeFields
                v-if="showDateTimeFields"
                v-model:date="dateModel"
                v-model:starts-at="startsAtModel"
                v-model:ends-at="endsAtModel"
                :date-id="dateId"
                :starts-at-id="startsAtId"
                :ends-at-id="endsAtId"
                :starts-at-placeholder="startsAtPlaceholder"
                :ends-at-placeholder="endsAtPlaceholder"
                :disabled="dateTimeDisabled"
            />

            <slot />

            <div class="flex w-full flex-col-reverse gap-3 border-t border-soft pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="showDelete"
                        type="button"
                        :label="deleteLabel"
                        severity="danger"
                        outlined
                        :disabled="deleteDisabled"
                        @click="openDeleteDialog"
                    />

                    <slot name="footer-start" />
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <slot name="footer-actions" />

                    <Button
                        v-if="showCancel"
                        type="button"
                        :label="cancelLabel"
                        severity="secondary"
                        outlined
                        @click="closeDialog"
                    />

                    <Button
                        v-if="showSave"
                        type="button"
                        :label="saveLabel"
                        :loading="loading"
                        :disabled="saveDisabled"
                        @click="emit('save')"
                    />
                </div>
            </div>
        </div>
    </AppDialog>

    <AppDialog
        v-model:visible="deleteDialogVisible"
        :title="deleteDialogTitle"
        width="max-w-md"
        :show-footer="true"
        close-label="Späť"
        @close="closeDeleteDialog"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 rounded-md bg-soft p-3 text-sm text-red-600">
                <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-white text-dark">
                    <i class="pi pi-trash text-base" />
                </div>

                <div class="min-w-0">
                    <p class="font-semibold text-dark">
                        {{ deleteConfirmMessage }}
                    </p>

                    <p class="mt-1 text-xs leading-5 text-dark">
                        {{ deleteSubMessage }}
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Odstrániť"
                severity="danger"
                @click="confirmDelete"
            />
        </template>
    </AppDialog>

    <OccurrenceScopeDialog
        v-model:visible="deleteScopeDialogVisible"
        mode="delete"
        subject-label="termín"
        :count-occurrence="deleteCountOccurrence"
        :count-from-date="deleteCountFromDate"
        :count-series="deleteCountSeries"
        @select="chooseDeleteScope"
        @cancel="closeDeleteScopeDialog"
    />
</template>