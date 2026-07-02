<script setup>
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: null,
    },
    date: {
        type: [Date, String],
        default: null,
    },
    label: {
        type: String,
        default: 'Opakovanie',
    },
});

const emit = defineEmits([
    'update:modelValue',
]);

const presetOptions = [
    { label: 'Neopakovať', value: 'never' },
    { label: 'Každý deň', value: 'daily' },
    { label: 'Každý týždeň', value: 'weekly' },
    { label: 'Každé 2 týždne', value: 'biweekly' },
    { label: 'Každý mesiac', value: 'monthly' },
    { label: 'Každý rok', value: 'yearly' },
    { label: 'Vlastné opakovanie', value: 'custom' },
];

const unitOptions = [
    { label: 'deň', pluralLabel: 'dni', value: 'daily' },
    { label: 'týždeň', pluralLabel: 'týždne', value: 'weekly' },
    { label: 'mesiac', pluralLabel: 'mesiace', value: 'monthly' },
    { label: 'rok', pluralLabel: 'roky', value: 'yearly' },
];

const weekdayOptions = [
    { label: 'Po', value: 'MO' },
    { label: 'Ut', value: 'TU' },
    { label: 'St', value: 'WE' },
    { label: 'Št', value: 'TH' },
    { label: 'Pi', value: 'FR' },
    { label: 'So', value: 'SA' },
    { label: 'Ne', value: 'SU' },
];

const endsTypeOptions = [
    { label: 'Nikdy', value: 'never' },
    { label: 'V konkrétny deň', value: 'on' },
    { label: 'Po určitom počte výskytov', value: 'after' },
];

const weekdayLabels = {
    MO: 'pondelok',
    TU: 'utorok',
    WE: 'streda',
    TH: 'štvrtok',
    FR: 'piatok',
    SA: 'sobota',
    SU: 'nedeľa',
};

const recurrenceMode = ref('never');
const customVisible = ref(false);

const customDraft = reactive({
    frequency: 'weekly',
    interval: 1,
    weekdays: [],
    endsType: 'never',
    endsCount: 1,
    endsUntil: null,
});

const normalizedDate = computed(() => {
    if (!props.date) {
        return null;
    }

    const parsed = props.date instanceof Date
        ? props.date
        : new Date(props.date);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
});

const weekdayFromDate = computed(() => {
    const date = normalizedDate.value;

    if (!date) {
        return 'MO';
    }

    const weekdayMap = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];

    return weekdayMap[date.getDay()] ?? 'MO';
});

const selectedWeekdayLabel = computed(() => {
    return weekdayLabels[weekdayFromDate.value] ?? 'vybraný deň';
});

const selectedWeekdaysLabel = computed(() => {
    if (!customDraft.weekdays.length) {
        return null;
    }

    return customDraft.weekdays
        .map((weekday) => weekdayLabels[weekday] ?? weekday)
        .join(', ');
});

const customUnitLabel = computed(() => {
    const interval = Math.max(1, Number(customDraft.interval || 1));
    const option = unitOptions.find((unit) => unit.value === customDraft.frequency);

    if (!option) {
        return '';
    }

    return interval === 1 ? option.label : option.pluralLabel;
});

const recurrenceHint = computed(() => {
    if (recurrenceMode.value === 'never') {
        return 'Termín sa vytvorí iba raz a nebude sa opakovať.';
    }

    if (recurrenceMode.value === 'daily') {
        return 'Termín sa bude opakovať každý deň.';
    }

    if (recurrenceMode.value === 'weekly') {
        return `Termín sa bude opakovať každý týždeň v rovnaký deň, teda v ${selectedWeekdayLabel.value}.`;
    }

    if (recurrenceMode.value === 'biweekly') {
        return `Termín sa bude opakovať každé 2 týždne v rovnaký deň, teda v ${selectedWeekdayLabel.value}.`;
    }

    if (recurrenceMode.value === 'monthly') {
        return 'Termín sa bude opakovať každý mesiac v rovnaký deň v mesiaci.';
    }

    if (recurrenceMode.value === 'yearly') {
        return 'Termín sa bude opakovať každý rok v rovnaký deň.';
    }

    if (recurrenceMode.value === 'custom') {
        return 'Nastavujete vlastné opakovanie. Môžete určiť interval, dni v týždni aj ukončenie opakovania.';
    }

    return null;
});

const isSameWeekdaySelection = (weekdays = [], weekday) => {
    return weekdays.length === 1 && weekdays[0] === weekday;
};

const recurrenceValue = computed(() => {
    if (recurrenceMode.value === 'never') {
        return null;
    }

    if (recurrenceMode.value === 'daily') {
        return {
            mode: 'daily',
            frequency: 'daily',
            interval: 1,
            weekdays: [],
            ends: {
                type: 'never',
                count: null,
                until: null,
            },
        };
    }

    if (recurrenceMode.value === 'weekly') {
        return {
            mode: 'weekly',
            frequency: 'weekly',
            interval: 1,
            weekdays: [weekdayFromDate.value],
            ends: {
                type: 'never',
                count: null,
                until: null,
            },
        };
    }

    if (recurrenceMode.value === 'biweekly') {
        return {
            mode: 'biweekly',
            frequency: 'weekly',
            interval: 2,
            weekdays: [weekdayFromDate.value],
            ends: {
                type: 'never',
                count: null,
                until: null,
            },
        };
    }

    if (recurrenceMode.value === 'monthly') {
        return {
            mode: 'monthly',
            frequency: 'monthly',
            interval: 1,
            weekdays: [],
            ends: {
                type: 'never',
                count: null,
                until: null,
            },
        };
    }

    if (recurrenceMode.value === 'yearly') {
        return {
            mode: 'yearly',
            frequency: 'yearly',
            interval: 1,
            weekdays: [],
            ends: {
                type: 'never',
                count: null,
                until: null,
            },
        };
    }

    return {
        mode: 'custom',
        frequency: customDraft.frequency,
        interval: Math.max(1, Number(customDraft.interval || 1)),
        weekdays: [...customDraft.weekdays],
        ends: {
            type: customDraft.endsType,
            count: customDraft.endsType === 'after'
                ? Math.max(1, Number(customDraft.endsCount || 1))
                : null,
            until: customDraft.endsType === 'on' && customDraft.endsUntil
                ? formatDateForBackend(customDraft.endsUntil)
                : null,
        },
    };
});

const recurrenceDescription = computed(() => {
    if (recurrenceMode.value === 'never') {
        return 'Neopakuje sa';
    }

    if (recurrenceMode.value === 'daily') {
        return 'Opakuje sa každý deň';
    }

    if (recurrenceMode.value === 'weekly') {
        return `Opakuje sa každý týždeň v ${selectedWeekdayLabel.value}`;
    }

    if (recurrenceMode.value === 'biweekly') {
        return `Opakuje sa každé 2 týždne v ${selectedWeekdayLabel.value}`;
    }

    if (recurrenceMode.value === 'monthly') {
        return 'Opakuje sa každý mesiac';
    }

    if (recurrenceMode.value === 'yearly') {
        return 'Opakuje sa každý rok';
    }

    if (recurrenceMode.value === 'custom') {
        const interval = Math.max(1, Number(customDraft.interval || 1));
        let description = `Opakuje sa každé ${interval} ${customUnitLabel.value}`;

        if (customDraft.frequency === 'weekly' && selectedWeekdaysLabel.value) {
            description += ` v dňoch: ${selectedWeekdaysLabel.value}`;
        }

        if (customDraft.endsType === 'on' && customDraft.endsUntil) {
            description += `, do ${formatDateForDisplay(customDraft.endsUntil)}`;
        }

        if (customDraft.endsType === 'after') {
            const count = Math.max(1, Number(customDraft.endsCount || 1));
            description += `, ukončí sa po ${count} výskytoch`;
        }

        return description;
    }

    return 'Neopakuje sa';
});

function formatDateForBackend(value) {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatDateForDisplay(value) {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleDateString('sk-SK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

const resetCustomDraft = () => {
    customDraft.frequency = 'weekly';
    customDraft.interval = 1;
    customDraft.weekdays = [];
    customDraft.endsType = 'never';
    customDraft.endsCount = 1;
    customDraft.endsUntil = null;
};

const syncFromValue = (value) => {
    if (!value) {
        recurrenceMode.value = 'never';
        resetCustomDraft();

        return;
    }

    customDraft.frequency = value.frequency ?? 'weekly';
    customDraft.interval = value.interval ?? 1;
    customDraft.weekdays = [...(value.weekdays ?? [])];
    customDraft.endsType = value.ends?.type ?? 'never';
    customDraft.endsCount = value.ends?.count ?? 1;
    customDraft.endsUntil = value.ends?.until
        ? new Date(`${value.ends.until}T00:00:00`)
        : null;

    const hasCustomEnds = customDraft.endsType !== 'never';
    const interval = Math.max(1, Number(value.interval ?? 1));
    const frequency = value.frequency ?? value.mode ?? 'weekly';
    const weekdays = value.weekdays ?? [];

    if (!hasCustomEnds && frequency === 'daily' && interval === 1) {
        recurrenceMode.value = 'daily';

        return;
    }

    if (!hasCustomEnds && frequency === 'weekly' && interval === 1 && isSameWeekdaySelection(weekdays, weekdayFromDate.value)) {
        recurrenceMode.value = 'weekly';

        return;
    }

    if (!hasCustomEnds && frequency === 'weekly' && interval === 2 && isSameWeekdaySelection(weekdays, weekdayFromDate.value)) {
        recurrenceMode.value = 'biweekly';

        return;
    }

    if (!hasCustomEnds && frequency === 'monthly' && interval === 1) {
        recurrenceMode.value = 'monthly';

        return;
    }

    if (!hasCustomEnds && frequency === 'yearly' && interval === 1) {
        recurrenceMode.value = 'yearly';

        return;
    }

    recurrenceMode.value = 'custom';
};

watch(() => props.modelValue, (value) => {
    syncFromValue(value);
}, { immediate: true, deep: true });

watch(recurrenceMode, (mode) => {
    if (mode === 'custom') {
        customVisible.value = true;
        return;
    }

    emit('update:modelValue', recurrenceValue.value);
});

const openCustomDialog = () => {
    syncFromValue(props.modelValue);
    recurrenceMode.value = 'custom';
    customVisible.value = true;
};

const toggleWeekday = (weekday) => {
    const index = customDraft.weekdays.indexOf(weekday);

    if (index === -1) {
        customDraft.weekdays.push(weekday);
        return;
    }

    customDraft.weekdays.splice(index, 1);
};

const saveCustom = () => {
    if (customDraft.frequency === 'weekly' && !customDraft.weekdays.length) {
        customDraft.weekdays = [weekdayFromDate.value];
    }

    recurrenceMode.value = 'custom';
    emit('update:modelValue', recurrenceValue.value);
    customVisible.value = false;
};

const cancelCustom = () => {
    syncFromValue(props.modelValue);
    customVisible.value = false;
};
</script>

<template>
    <FormField
        :label="label"
        for="recurrence_mode"
        span="md:col-span-2"
        class="space-y-1"
    >
        <Select
            id="recurrence_mode"
            v-model="recurrenceMode"
            :options="presetOptions"
            option-label="label"
            option-value="value"
            class="w-full"
        />

        <div
            v-if="recurrenceHint"
            class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 text-xs text-accent"
        >
            <div class="flex h-full min-h-8 items-center justify-center rounded-md bg-soft text-dark">
                <i class="pi pi-info-circle text-base" />
            </div>

            <p class="flex min-w-0 items-center font-medium leading-relaxed">
                {{ recurrenceHint }}
            </p>
        </div>

        <div
            v-if="recurrenceMode === 'custom' && modelValue"
            class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 rounded-md bg-soft p-3 text-sm text-accent"
        >
            <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-white text-accent">
                <i class="pi pi-refresh text-base" />
            </div>

            <div class="flex min-w-0 items-center justify-between gap-3">
                <p class="min-w-0 font-medium text-dark">
                    {{ recurrenceDescription }}
                </p>

                <Button
                    type="button"
                    label="Upraviť"
                    text
                    size="small"
                    @click="openCustomDialog"
                />
            </div>
        </div>
    </FormField>

    <FormDialog
        v-model:visible="customVisible"
        title="Vlastné opakovanie"
        description="Nastavte, ako často sa má termín opakovať a kedy sa má opakovanie skončiť."
        width="max-w-2xl"
        :show-footer="true"
        close-label="Zrušiť"
        @close="cancelCustom"
    >
        <div class="space-y-6">
            <FormSection
                title="Interval opakovania"
                description="Nastavte, ako často sa má termín opakovať."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Opakovať každých"
                    for="custom_interval"
                    required
                >
                    <InputNumber
                        id="custom_interval"
                        v-model="customDraft.interval"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                    />
                </FormField>

                <FormField
                    label="Jednotka"
                    for="custom_frequency"
                    required
                >
                    <Select
                        id="custom_frequency"
                        v-model="customDraft.frequency"
                        :options="unitOptions"
                        option-label="pluralLabel"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <FormSection
                v-if="customDraft.frequency === 'weekly'"
                title="Dni v týždni"
                description="Vyberte dni, v ktorých sa má termín opakovať."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Opakovať v dňoch"
                    for="custom_weekdays"
                    span="md:col-span-1"
                >
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="day in weekdayOptions"
                            :key="day.value"
                            type="button"
                            class="flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold transition"
                            :class="customDraft.weekdays.includes(day.value)
                                ? 'bg-dark text-white'
                                : 'bg-soft text-accent hover:bg-soft/80'"
                            @click="toggleWeekday(day.value)"
                        >
                            {{ day.label }}
                        </button>
                    </div>
                </FormField>
            </FormSection>

            <FormSection
                title="Ukončenie opakovania"
                description="Vyberte, kedy sa má opakovanie skončiť."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Koniec opakovania"
                    for="custom_ends_type"
                    required
                    span="md:col-span-2"
                >
                    <Select
                        id="custom_ends_type"
                        v-model="customDraft.endsType"
                        :options="endsTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>

                <FormField
                    v-if="customDraft.endsType === 'on'"
                    label="Dátum ukončenia"
                    for="custom_ends_until"
                    required
                    span="md:col-span-2"
                >
                    <DatePicker
                        id="custom_ends_until"
                        v-model="customDraft.endsUntil"
                        date-format="dd.mm.yy"
                        show-icon
                        class="w-full"
                    />
                </FormField>

                <FormField
                    v-if="customDraft.endsType === 'after'"
                    label="Počet výskytov"
                    for="custom_ends_count"
                    required
                    span="md:col-span-2"
                >
                    <InputNumber
                        id="custom_ends_count"
                        v-model="customDraft.endsCount"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                        suffix=" výskytov"
                    />
                </FormField>
            </FormSection>

            <div class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 rounded-md bg-soft p-3 text-sm text-accent">
                <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-white text-accent">
                    <i class="pi pi-refresh text-base" />
                </div>

                <p class="flex min-w-0 items-center font-medium leading-relaxed text-dark">
                    {{ recurrenceDescription }}
                </p>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Uložiť opakovanie"
                @click="saveCustom"
            />
        </template>
    </FormDialog>
</template>