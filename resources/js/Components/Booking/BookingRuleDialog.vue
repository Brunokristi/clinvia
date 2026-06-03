<script setup>
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
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
    slotModeOptions: {
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
    get: () => createTimeDate(props.currentRule?.starts_at),
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => createTimeDate(props.currentRule?.ends_at),
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.ends_at = formatTimeForBackend(value);
    },
});

const dialogTitle = computed(() => {
    if (!props.currentRule) {
        return 'Pravidlo rezervácií';
    }

    return props.getRuleTitle(props.currentRule) || 'Pravidlo rezervácií';
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
        :save-disabled="loading"
        show-delete
        :is-repeatable="Boolean(currentRule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate"
        @close="closeDialog"
        @save="emit('save')"
        @delete-occurrence="deleteCurrentRuleOccurrence"
        @delete-from-now-on="deleteCurrentRuleFromNowOn"
        @delete-all="deleteCurrentRuleEverywhere"
    >
        <div
            v-if="currentRule"
            class="space-y-6"
        >
            <FormSection
                title="Typ rezervovania"
                description="Vyberte, ako sa má tento čas v kalendári správať."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Ako chcete spravovať tento čas?"
                    for="slot_mode"
                >
                    <Select
                        id="slot_mode"
                        v-model="currentRule.slot_mode"
                        :options="slotModeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <FormSection
                v-if="currentRule.slot_mode === 'single_service_many_clients'"
                title="Skupinová služba"
                description="Tento režim zobrazí v kalendári jedno časové okno. Počet prihlásených klientov sa bude počítať v celom intervale."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Služba"
                    for="single_service_id"
                    required
                >
                    <Select
                        id="single_service_id"
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
                    for="bookable_places"
                    required
                >
                    <InputNumber
                        id="bookable_places"
                        v-model="currentRule.bookable_places"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                    />
                </FormField>
            </FormSection>

            <FormSection
                v-if="currentRule.slot_mode === 'free_bookable_time'"
                title="Voľný rezervovateľný čas"
                description="Klient si vyberie jednu z povolených služieb a systém obsadí potrebný čas podľa trvania služby."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Rezervovateľné služby"
                    for="service_ids"
                    required
                >
                    <MultiSelect
                        id="service_ids"
                        v-model="currentRule.service_ids"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        display="chip"
                        placeholder="Vyberte služby"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                :model="currentRule"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Nastavte, či sa pravidlo má opakovať."
                enabled-id="rule_is_enabled"
                repeats-id="rule_repeats"
                repeat-every-id="repeat_every"
                repeat-unit-id="repeat_unit"
                enabled-label="Pravidlo je aktívne"
                repeats-label="Opakovať pravidlo"
            />

            <div class="rounded-md bg-soft p-4 text-sm leading-6 text-accent">
                <strong>Ukážka:</strong>
                {{ currentRule.date }},
                {{ currentRule.starts_at }} – {{ currentRule.ends_at }}.
                {{ getRuleTitle(currentRule) }}.
                {{ getRepeatLabel(currentRule) }}.

                <span v-if="selectedRuleOccurrence?.occurrenceDate">
                    Vybraný výskyt:
                    {{ selectedRuleOccurrence.occurrenceDate }}.
                </span>
            </div>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Pravidlo sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>