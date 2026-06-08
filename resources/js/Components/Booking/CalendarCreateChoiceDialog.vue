<script setup>
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

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

const form = reactive({
    date: null,
    starts_at: null,
    ends_at: null,
    create_type: null,
});

const createTypeOptions = [
    {
        label: 'Nová rezervácia',
        value: 'booking',
    },
    {
        label: 'Pravidlo online rezercácií',
        value: 'rule',
    },
    {
        label: 'Skupinový termín',
        value: 'group_event',
    },
];

const canContinue = computed(() => {
    return Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at)
        && Boolean(form.create_type);
});

const formatDateForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatTimeForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const createDateFromDateAndTime = (dateValue, timeValue) => {
    if (!dateValue || !timeValue) {
        return null;
    }

    const date = dateValue instanceof Date
        ? new Date(dateValue)
        : new Date(dateValue);

    if (timeValue instanceof Date) {
        date.setHours(timeValue.getHours(), timeValue.getMinutes(), 0, 0);

        return date;
    }

    const [hours, minutes] = String(timeValue).split(':');

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const createDefaultStartDate = () => {
    const now = new Date();

    now.setSeconds(0, 0);

    const minutes = now.getMinutes();
    const roundedMinutes = Math.ceil(minutes / 15) * 15;

    now.setMinutes(roundedMinutes);

    if (roundedMinutes === 60) {
        now.setHours(now.getHours() + 1, 0, 0, 0);
    }

    return now;
};

const createDefaultEndDate = (start) => {
    const end = new Date(start);

    end.setMinutes(end.getMinutes() + 60);

    return end;
};

const resetFormFromSelection = (selection) => {
    if (!selection?.start || !selection?.end) {
        const start = createDefaultStartDate();
        const end = createDefaultEndDate(start);

        form.date = start;
        form.starts_at = start;
        form.ends_at = end;
        form.create_type = null;

        return;
    }

    const start = selection.start instanceof Date
        ? selection.start
        : new Date(selection.start);

    const end = selection.end instanceof Date
        ? selection.end
        : new Date(selection.end);

    form.date = selection.date
        ? new Date(`${selection.date}T00:00:00`)
        : start;

    form.starts_at = selection.starts_at
        ? createDateFromDateAndTime(form.date, selection.starts_at)
        : start;

    form.ends_at = selection.ends_at
        ? createDateFromDateAndTime(form.date, selection.ends_at)
        : end;

    form.create_type = null;
};

watch(() => props.selection, (selection) => {
    if (props.visible) {
        resetFormFromSelection(selection);
    }
});

watch(() => props.visible, (visible) => {
    if (visible) {
        resetFormFromSelection(props.selection);
    }
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
        date: formatDateForBackend(form.date),
        starts_at: formatTimeForBackend(form.starts_at),
        ends_at: formatTimeForBackend(form.ends_at),
        create_type: form.create_type,
    });
};
</script>

<template>
    <EventDialog
        :visible="visible"
        v-model:date="form.date"
        v-model:starts-at="form.starts_at"
        v-model:ends-at="form.ends_at"
        width="max-w-xl"
        date-id="create_choice_date"
        starts-at-id="create_choice_starts_at"
        ends-at-id="create_choice_ends_at"
        starts-at-placeholder="Vyberte čas"
        ends-at-placeholder="Vyberte čas"
        save-label="Pokračovať"
        :save-disabled="!canContinue"
        :show-delete="false"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @save="submit"
        title="Vytvoriť udalosť"
    >
        <FormPage
            :loading="false"
            :show-submit="false"
        >
            <FormSection title="Vytvorte udalosť" columns="md:grid-cols-1">
                <FormField label="Typ" for="create_choice_type" required>
                    <Select
                        id="create_choice_type"
                        v-model="form.create_type"
                        :options="createTypeOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte čo chcete vytvoriť"
                        class="w-full"
                    />
                </FormField>
            </FormSection>
        </FormPage>
    </EventDialog>
</template>
