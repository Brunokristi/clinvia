<script setup>
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import { computed, ref } from 'vue';

import AppDialog from '@/Components/Dialogs/FormDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';

const props = defineProps({
    visible: {
        type: Boolean,
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
    showDate: {
        type: Boolean,
        default: true,
    },
    showStartsAt: {
        type: Boolean,
        default: true,
    },
    showEndsAt: {
        type: Boolean,
        default: true,
    },
    showDateTime: {
        type: Boolean,
        default: false,
    },
    endsAtShowDateTime: {
        type: Boolean,
        default: false,
    },
    readonlyEndsAt: {
        type: Boolean,
        default: false,
    },
    disabledEndsAt: {
        type: Boolean,
        default: false,
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
    dateLabel: {
        type: String,
        default: 'Dátum',
    },
    startsAtLabel: {
        type: String,
        default: 'Začiatok',
    },
    endsAtLabel: {
        type: String,
        default: 'Koniec',
    },
    datePlaceholder: {
        type: String,
        default: 'Vyberte dátum',
    },
    startsAtPlaceholder: {
        type: String,
        default: '08:00',
    },
    endsAtPlaceholder: {
        type: String,
        default: '09:00',
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
    deleteDialogTitle: {
        type: String,
        default: 'Odstrániť termín',
    },
    deleteDialogDescription: {
        type: String,
        default: 'Vyberte, ako chcete tento termín odstrániť.',
    },
    deleteOneLabel: {
        type: String,
        default: 'Odstrániť iba tento termín',
    },
    deleteFutureLabel: {
        type: String,
        default: 'Odstrániť tento a všetky budúce termíny',
    },
    deleteAllLabel: {
        type: String,
        default: 'Odstrániť celú sériu',
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
        title=""
        :width="width"
        show-footer
        :close-label="cancelLabel"
        @close="closeDialog"
    >
        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-soft bg-white p-4 md:grid-cols-3">
                <FormField
                    v-if="showDate"
                    :label="dateLabel"
                    :for="dateId"
                    required
                >
                    <DatePicker
                        :input-id="dateId"
                        v-model="dateModel"
                        date-format="dd.mm.yy"
                        class="w-full"
                        input-class="w-full"
                        :placeholder="datePlaceholder"
                    />
                </FormField>

                <FormField
                    v-if="showStartsAt"
                    :label="startsAtLabel"
                    :for="startsAtId"
                    required
                >
                    <DatePicker
                        :input-id="startsAtId"
                        v-model="startsAtModel"
                        :show-time="showDateTime"
                        :time-only="!showDateTime"
                        hour-format="24"
                        icon-display="input"
                        date-format="dd.mm.yy"
                        class="w-full"
                        input-class="w-full"
                        :placeholder="startsAtPlaceholder"
                    />
                </FormField>

                <FormField
                    v-if="showEndsAt"
                    :label="endsAtLabel"
                    :for="endsAtId"
                    required
                >
                    <DatePicker
                        :input-id="endsAtId"
                        v-model="endsAtModel"
                        :show-time="endsAtShowDateTime"
                        :time-only="!endsAtShowDateTime"
                        hour-format="24"
                        icon-display="input"
                        date-format="dd.mm.yy"
                        class="w-full"
                        input-class="w-full"
                        :placeholder="endsAtPlaceholder"
                        :readonly="readonlyEndsAt"
                        :disabled="disabledEndsAt"
                    />
                </FormField>
            </div>

            <slot />
        </div>

        <template #footer>
            <div class="flex w-full flex-col-reverse gap-3 border-t border-soft pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Button
                        v-if="showDelete"
                        type="button"
                        :label="deleteLabel"
                        icon="pi pi-trash"
                        severity="danger"
                        outlined
                        :disabled="deleteDisabled"
                        @click="openDeleteDialog"
                    />
                </div>

                <div class="flex justify-end gap-2">
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
        </template>
    </AppDialog>

    <AppDialog
        v-model:visible="deleteDialogVisible"
        :title="deleteDialogTitle"
        width="max-w-xl"
        @close="closeDeleteDialog"
    >
        <div class="space-y-5">
            <p class="text-sm text-dark">
                {{ deleteDialogDescription }}
            </p>

            <div
                v-if="occurrenceDate"
                class="rounded-xl border border-warning/30 bg-warning/5 p-3 text-sm flex items-center gap-3"
            >
                <i class="pi pi-exclamation-triangle text-amber-500 text-lg"></i>

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
                    :label="deleteOneLabel"
                    icon="pi pi-calendar-times"
                    severity="warn"
                    outlined
                    class="justify-start text-left"
                    @click="deleteOccurrence"
                />

                <template v-if="isRepeatable">
                    <Button
                        type="button"
                        :label="deleteFutureLabel"
                        icon="pi pi-forward"
                        severity="danger"
                        outlined
                        class="justify-start text-left"
                        @click="deleteFromNowOn"
                    />

                    <Button
                        type="button"
                        :label="deleteAllLabel"
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
