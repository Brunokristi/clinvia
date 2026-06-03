<script setup>
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { computed } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    currentRule: {
        type: Object,
        default: null,
    },
    selectedRuleOccurrence: {
        type: Object,
        default: null,
    },
    services: {
        type: Array,
        default: () => [],
    },
    repeatUnitOptions: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    getRuleTitle: {
        type: Function,
        required: true,
    },
    getRepeatLabel: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'save',
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const createTimeDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const [hours, minutes] = String(value).slice(0, 5).split(':');
    const date = new Date();

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

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

const datePickerModel = computed({
    get: () => {
        if (!props.currentRule?.date) {
            return null;
        }

        if (props.currentRule.date instanceof Date) {
            return props.currentRule.date;
        }

        return new Date(`${props.currentRule.date}T00:00:00`);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.date = formatDateForBackend(value);
    },
});

const startsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.currentRule?.starts_at);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.currentRule?.ends_at);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.ends_at = formatTimeForBackend(value);
    },
});

const dialogTitle = computed(() => {
    if (!props.currentRule) {
        return 'Skupinová rezervácia';
    }

    return props.getRuleTitle(props.currentRule) || 'Skupinová rezervácia';
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const deleteCurrentRuleOccurrence = () => {
    if (props.currentRule?.repeats) {
        emit('delete-occurrence');

        return;
    }

    emit('delete-all');
};

const deleteCurrentRuleFromNowOn = () => {
    emit('delete-from-now-on');
};

const deleteCurrentRuleEverywhere = () => {
    emit('delete-all');
};
</script>

<template>
    <EventDialog
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        v-model:date="datePickerModel"
        v-model:starts-at="startsAtPickerModel"
        v-model:ends-at="endsAtPickerModel"
        width="max-w-3xl"
        save-label="Uložiť"
        :loading="loading"
        :save-disabled="loading || !currentRule"
        :show-save="Boolean(currentRule)"
        show-delete
        :is-repeatable="Boolean(currentRule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate"
        @close="closeDialog"
        @save="emit('save')"
        @delete-occurrence="deleteCurrentRuleOccurrence"
        @delete-from-now-on="deleteCurrentRuleFromNowOn"
        @delete-all="deleteCurrentRuleEverywhere"
    >
        <FormPage
            v-if="currentRule"
            submit-label="Uložiť"
            :loading="loading"
            :show-submit="false"
        >
            <FormSection
                title="Kapacita a služba"
                description="Pacienti sa budú prihlasovať do rovnakého času až do naplnenia kapacity."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Služba"
                    for="group_service_id"
                    required
                >
                    <Select
                        id="group_service_id"
                        v-model="currentRule.service_id"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                </FormField>

                <FormField
                    label="Počet rezervovateľných miest"
                    for="group_bookable_places"
                    required
                >
                    <InputNumber
                        id="group_bookable_places"
                        v-model="currentRule.bookable_places"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                        placeholder="Napr. 10"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                :model="currentRule"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Nastavte platnosť, prípadnú periodickú opakovateľnosť skupinovej rezervácie."
                enabled-id="group_rule_is_enabled"
                repeats-id="group_rule_repeats"
                repeat-every-id="group_repeat_every"
                repeat-unit-id="group_repeat_unit"
                enabled-label="Skupinová rezervácia je aktívna a viditeľná pre pacientov"
                repeats-label="Opakovať túto skupinovú rezerváciu periodicky"
            />
        </FormPage>

        <div
            v-else
            class="rounded-xl border border-soft bg-white p-6 text-center text-sm text-accent"
        >
            <i class="pi pi-exclamation-circle mb-2 block text-2xl text-red-400"></i>
            Skupinovú rezerváciu sa nepodarilo úspešne načítať.
        </div>
    </EventDialog>
</template>