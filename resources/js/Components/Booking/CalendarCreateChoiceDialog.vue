<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    selection: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'continue',
]);

const isContinuing = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const form = reactive({
    date: '',
    starts_at: '',
    ends_at: '',
    create_type: null,
});

const createTypeOptions = [
    {
        label: 'Rezervácia',
        value: 'booking',
    },
    {
        label: 'Pravidlo rezervácií',
        value: 'rule',
    },
    {
        label: 'Skupinová rezervácia',
        value: 'group_event',
    },
];

const canContinue = computed(() => {
    return Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at)
        && Boolean(form.create_type);
});

const getDateFromDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const getTimeFromDate = (date) => {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const resetFormFromSelection = (selection) => {
    if (!selection?.start || !selection?.end) {
        form.date = '';
        form.starts_at = '';
        form.ends_at = '';
        form.create_type = null;

        return;
    }

    form.date = selection.date ?? getDateFromDate(selection.start);
    form.starts_at = selection.starts_at ?? getTimeFromDate(selection.start);
    form.ends_at = selection.ends_at ?? getTimeFromDate(selection.end);
    form.create_type = null;
};

watch(() => props.selection, (selection) => {
    resetFormFromSelection(selection);
}, {
    immediate: true,
});

const closeDialog = () => {
    if (isContinuing.value) {
        isContinuing.value = false;

        return;
    }

    emit('update:visible', false);
    emit('close');
};

const submit = () => {
    if (!canContinue.value) {
        return;
    }

    isContinuing.value = true;

    emit('continue', {
        date: form.date,
        starts_at: form.starts_at,
        ends_at: form.ends_at,
        create_type: form.create_type,
    });
};
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Čo chcete vytvoriť?"
        :style="{ width: '560px', maxWidth: '95vw' }"
        @hide="closeDialog"
    >
        <div class="space-y-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Dátum
                    </label>

                    <InputText
                        v-model="form.date"
                        type="date"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Od
                    </label>

                    <InputText
                        v-model="form.starts_at"
                        type="time"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Do
                    </label>

                    <InputText
                        v-model="form.ends_at"
                        type="time"
                        class="w-full"
                    />
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-dark">
                    Typ vytvorenia
                </label>

                <Select
                    v-model="form.create_type"
                    :options="createTypeOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Vyberte čo chcete vytvoriť"
                    class="w-full"
                />
            </div>

            <div class="flex justify-end gap-2 border-t border-soft pt-5">
                <Button
                    type="button"
                    label="Zrušiť"
                    severity="secondary"
                    outlined
                    @click="closeDialog"
                />

                <Button
                    type="button"
                    label="Pokračovať"
                    icon="pi pi-arrow-right"
                    :disabled="!canContinue"
                    @click="submit"
                />
            </div>
        </div>
    </Dialog>
</template>