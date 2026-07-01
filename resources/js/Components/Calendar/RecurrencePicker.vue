<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

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
    { label: 'Neopakovať sa', value: 'never' },
    { label: 'Každý deň', value: 'daily' },
    { label: 'Každý týždeň', value: 'weekly' },
    { label: 'Každé 2 týždne', value: 'biweekly' },
    { label: 'Každý mesiac', value: 'monthly' },
    { label: 'Každý rok', value: 'yearly' },
    { label: 'Vlastné opakovanie...', value: 'custom' },
];

const unitOptions = [
    { label: 'Dni', value: 'daily' },
    { label: 'Týždne', value: 'weekly' },
    { label: 'Mesiace', value: 'monthly' },
    { label: 'Roky', value: 'yearly' },
];

const weekdayOptions = [
    { label: 'M', value: 'MO' },
    { label: 'T', value: 'TU' },
    { label: 'W', value: 'WE' },
    { label: 'T', value: 'TH' },
    { label: 'F', value: 'FR' },
    { label: 'S', value: 'SA' },
    { label: 'S', value: 'SU' },
];

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

    return props.date instanceof Date
        ? props.date
        : new Date(props.date);
});

const weekdayFromDate = computed(() => {
    const date = normalizedDate.value;

    if (!date) {
        return 'MO';
    }

    return weekdayOptions[date.getDay()]?.value ?? 'MO';
});

const presetLabel = computed(() => {
    return presetOptions.find((option) => option.value === recurrenceMode.value)?.label ?? 'Neopakovať sa';
});

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
            count: customDraft.endsType === 'after' ? Math.max(1, Number(customDraft.endsCount || 1)) : null,
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
        return 'Denne';
    }

    if (recurrenceMode.value === 'weekly') {
        return `Každý týždeň (${weekdayFromDate.value})`;
    }

    if (recurrenceMode.value === 'biweekly') {
        return `Každé 2 týždne (${weekdayFromDate.value})`;
    }

    if (recurrenceMode.value === 'monthly') {
        return 'Každý mesiac';
    }

    if (recurrenceMode.value === 'yearly') {
        return 'Každý rok';
    }

    if (recurrenceMode.value === 'custom') {
        const interval = Math.max(1, Number(customDraft.interval || 1));
        const unitLabel = {
            daily: interval === 1 ? 'deň' : 'dni',
            weekly: interval === 1 ? 'týždeň' : 'týždne',
            monthly: interval === 1 ? 'mesiac' : 'mesiace',
            yearly: interval === 1 ? 'rok' : 'roky',
        }[customDraft.frequency] ?? 'interval';

        let description = `Každý ${interval}. ${unitLabel}`;

        if (customDraft.frequency === 'weekly' && customDraft.weekdays.length) {
            description += ` (${customDraft.weekdays.join(', ')})`;
        }

        if (customDraft.endsType === 'on' && customDraft.endsUntil) {
            description += `, do ${formatDateForBackend(customDraft.endsUntil)}`;
        }

        if (customDraft.endsType === 'after') {
            const count = Math.max(1, Number(customDraft.endsCount || 1));
            description += `, ${count} výskytov`;
        }

        return description;
    }

    return presetLabel.value;
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

const syncFromValue = (value) => {
    if (!value) {
        recurrenceMode.value = 'never';
        return;
    }

    const mode = value.mode ?? 'custom';

    recurrenceMode.value = ['never', 'weekly', 'biweekly', 'monthly', 'custom'].includes(mode)
        || mode === 'daily'
        || mode === 'yearly'
        ? mode
        : 'custom';

    if (recurrenceMode.value === 'custom') {
        customDraft.frequency = value.frequency ?? 'weekly';
        customDraft.interval = value.interval ?? 1;
        customDraft.weekdays = [...(value.weekdays ?? [])];
        customDraft.endsType = value.ends?.type ?? 'never';
        customDraft.endsCount = value.ends?.count ?? 1;
        customDraft.endsUntil = value.ends?.until ?? null;
    }
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
    recurrenceMode.value = 'custom';
    emit('update:modelValue', recurrenceValue.value);
    customVisible.value = false;
};

const cancelCustom = () => {
    customVisible.value = false;
};
</script>

<template>
    <FormSection
        :title="label"
        columns="md:grid-cols-2"
    >
        <FormField
            label="Opakovanie"
            for="recurrence_mode"
            span="md:col-span-2"
        >
            <Select
                id="recurrence_mode"
                v-model="recurrenceMode"
                :options="presetOptions"
                option-label="label"
                option-value="value"
                class="w-full"
            />
        </FormField>

        <div class="md:col-span-2 flex items-center justify-between gap-3 rounded-xl border border-soft bg-white px-4 py-3">
            <span class="text-sm text-accent">
                {{ recurrenceDescription }}
            </span>

            <Button
                type="button"
                label="Vlastné..."
                severity="secondary"
                outlined
                @click="openCustomDialog"
            />
        </div>
    </FormSection>

    <Dialog
        v-model:visible="customVisible"
        modal
        header="Vlastné opakovanie"
        :style="{ width: '32rem' }"
        :closable="true"
    >
        <div class="flex flex-col gap-6">
            <div>
                <p class="mb-3 text-sm font-medium text-dark">
                    Repeat every
                </p>

                <div class="flex items-center gap-3">
                    <InputNumber
                        v-model="customDraft.interval"
                        :min="1"
                        class="w-24"
                        input-class="w-full"
                    />

                    <Select
                        v-model="customDraft.frequency"
                        :options="unitOptions"
                        option-label="label"
                        option-value="value"
                        class="w-44"
                    />
                </div>
            </div>

            <div v-if="customDraft.frequency === 'weekly'">
                <p class="mb-3 text-sm font-medium text-dark">
                    Repeat on
                </p>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="day in weekdayOptions"
                        :key="day.value"
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-medium transition"
                        :class="customDraft.weekdays.includes(day.value)
                            ? 'bg-primary/20 text-primary'
                            : 'bg-soft text-accent hover:bg-soft/80'"
                        @click="toggleWeekday(day.value)"
                    >
                        {{ day.label }}
                    </button>
                </div>
            </div>

            <div>
                <p class="mb-3 text-sm font-medium text-dark">
                    Ends
                </p>

                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-3">
                        <input
                            v-model="customDraft.endsType"
                            type="radio"
                            value="never"
                        >
                        <span>Never</span>
                    </label>

                    <label class="flex items-center gap-3">
                        <input
                            v-model="customDraft.endsType"
                            type="radio"
                            value="on"
                        >
                        <span>On</span>

                        <input
                            v-if="customDraft.endsType === 'on'"
                            v-model="customDraft.endsUntil"
                            type="date"
                            class="ml-auto w-44 rounded-lg border border-soft bg-white px-3 py-2 text-sm"
                        >
                    </label>

                    <label class="flex items-center gap-3">
                        <input
                            v-model="customDraft.endsType"
                            type="radio"
                            value="after"
                        >
                        <span>After</span>

                        <InputNumber
                            v-if="customDraft.endsType === 'after'"
                            v-model="customDraft.endsCount"
                            :min="1"
                            class="ml-auto w-44"
                            input-class="w-full"
                        />
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-soft pt-4">
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="cancelCustom"
                />

                <Button
                    type="button"
                    label="Done"
                    @click="saveCustom"
                />
            </div>
        </div>
    </Dialog>
</template>
