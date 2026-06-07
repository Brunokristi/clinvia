<script setup>
import Button from 'primevue/button';
import { computed, ref } from 'vue';

import AppDialog from '@/Components/Dialogs/FormDialog.vue';
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

const openDeleteDialog = () => {
    deleteDialogVisible.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogVisible.value = false;
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
                v-model:date="dateModel"
                v-model:starts-at="startsAtModel"
                v-model:ends-at="endsAtModel"
                :date-id="dateId"
                :starts-at-id="startsAtId"
                :ends-at-id="endsAtId"
                :starts-at-placeholder="startsAtPlaceholder"
                :ends-at-placeholder="endsAtPlaceholder"
            />

            <slot />

            <div class="flex w-full flex-col-reverse gap-3 border-t border-accent pt-4 sm:flex-row sm:items-center sm:justify-between">
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
        title="Odstrániť termín"
        width="max-w-xl"
        @close="closeDeleteDialog"
    >
        <div class="space-y-5">
            <p class="text-sm text-dark">
                Vyberte, ako chcete tento termín odstrániť.
            </p>

            <div
                v-if="occurrenceDate"
                class="flex items-center gap-3 rounded-xl border border-warning/30 bg-warning/5 p-3 text-sm"
            >
                <i class="pi pi-exclamation-triangle text-lg text-amber-500"></i>

                <div>
                    <span class="block text-xs font-medium text-accent">
                        Vybraný termín
                    </span>

                    <strong class="text-dark">
                        {{ occurrenceDate }}
                    </strong>
                </div>
            </div>

            <div class="flex flex-col gap-2.5 pt-2">
                <Button
                    type="button"
                    label="Odstrániť iba tento termín"
                    icon="pi pi-calendar-times"
                    severity="warn"
                    outlined
                    class="justify-start text-left"
                    @click="deleteOccurrence"
                />

                <template v-if="isRepeatable">
                    <Button
                        type="button"
                        label="Odstrániť tento a všetky budúce termíny"
                        icon="pi pi-forward"
                        severity="danger"
                        outlined
                        class="justify-start text-left"
                        @click="deleteFromNowOn"
                    />

                    <Button
                        type="button"
                        label="Odstrániť celú sériu"
                        icon="pi pi-trash"
                        severity="danger"
                        class="justify-start text-left"
                        @click="deleteAll"
                    />
                </template>
            </div>

            <div class="flex justify-end border-t border-soft pt-3">
                <Button
                    type="button"
                    label="Späť"
                    severity="secondary"
                    outlined
                    @click="closeDeleteDialog"
                />
            </div>
        </div>
    </AppDialog>
</template>