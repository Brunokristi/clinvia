<script setup>
import InputNumber from 'primevue/inputnumber';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, ref, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import OccurrenceScopeDialog from '@/Components/Booking/OccurrenceScopeDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    rule: {
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
});

const emit = defineEmits([
    'update:visible',
    'close',
    'save',
    'delete',
]);

const rescheduleChoiceVisible = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const stripTimezoneFromDateTime = (value) => {
    if (!value) {
        return null;
    }

    return String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);
};

const createTimeDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const stringValue = stripTimezoneFromDateTime(value);

    if (!stringValue) {
        return null;
    }

    if (stringValue.includes('T')) {
        return new Date(stringValue);
    }

    const [hours, minutes] = stringValue.slice(0, 5).split(':');
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

    if (Number.isNaN(date.getTime())) {
        return '';
    }

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

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const addYearsToDate = (dateValue, years = 2) => {
    if (!dateValue) {
        return null;
    }

    const stringValue = dateValue instanceof Date
        ? formatDateForBackend(dateValue)
        : String(dateValue).slice(0, 10);

    const date = new Date(`${stringValue}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    date.setFullYear(date.getFullYear() + years);

    return formatDateForBackend(date);
};

const normalizeRepeatDefaults = () => {
    if (!props.rule) {
        return;
    }

    props.rule.repeat_every = Number(props.rule.repeat_every || 1);
    props.rule.repeat_unit = props.rule.repeat_unit || 'weeks';

    if (props.rule.repeats && !props.rule.repeat_ends_on) {
        props.rule.repeat_ends_on = addYearsToDate(props.rule.date, 2);
    }
};

watch(() => props.visible, (visible) => {
    if (visible) {
        normalizeRepeatDefaults();
    }
});

watch(() => props.rule?.repeats, () => {
    normalizeRepeatDefaults();
});

watch(() => props.rule?.date, () => {
    if (props.rule?.repeats && !props.rule.repeat_ends_on) {
        props.rule.repeat_ends_on = addYearsToDate(props.rule.date, 5);
    }
});

const datePickerModel = computed({
    get: () => {
        if (!props.rule?.date) {
            return null;
        }

        if (props.rule.date instanceof Date) {
            return props.rule.date;
        }

        return new Date(`${String(props.rule.date).slice(0, 10)}T00:00:00`);
    },
    set: (value) => {
        if (!props.rule) {
            return;
        }

        props.rule.date = formatDateForBackend(value);

        if (props.rule.repeats && !props.rule.repeat_ends_on) {
            props.rule.repeat_ends_on = addYearsToDate(props.rule.date, 5);
        }
    },
});

const startsAtPickerModel = computed({
    get: () => createTimeDate(props.rule?.starts_at),
    set: (value) => {
        if (!props.rule) {
            return;
        }

        props.rule.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => createTimeDate(props.rule?.ends_at),
    set: (value) => {
        if (!props.rule) {
            return;
        }

        props.rule.ends_at = formatTimeForBackend(value);
    },
});

const dialogTitle = computed(() => {
    if (!props.rule) {
        return 'Voľný čas';
    }

    return props.rule.id
        ? 'Upraviť voľný čas'
        : 'Nový voľný čas';
});

const hasValidRepeatSettings = computed(() => {
    if (!props.rule?.repeats) {
        return true;
    }

    return Boolean(props.rule.repeat_ends_on)
        && Number(props.rule.repeat_every ?? 0) >= 1
        && ['days', 'weeks', 'months'].includes(props.rule.repeat_unit);
});

const canSave = computed(() => {
    return Boolean(props.rule)
        && Boolean(props.rule.date)
        && Boolean(props.rule.starts_at)
        && Boolean(props.rule.ends_at)
        && (props.rule.service_ids ?? []).length > 0
        && hasValidRepeatSettings.value;
});

const isExistingRepeatedRule = computed(() => {
    return Boolean(props.rule?.id && props.rule?.repeats);
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const saveRule = () => {
    if (!canSave.value) {
        return;
    }

    if (isExistingRepeatedRule.value) {
        rescheduleChoiceVisible.value = true;

        return;
    }

    emit('save');
};

const submitRescheduleScope = (scope) => {
    rescheduleChoiceVisible.value = false;
    emit('save', scope);
};

const deleteOccurrence = () => {
    emit('delete', 'occurrence');
};

const deleteFromNowOn = () => {
    emit('delete', 'from_date');
};

const deleteAll = () => {
    emit('delete', 'series');
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
        :save-disabled="loading || !canSave"
        :show-save="Boolean(rule)"
        show-delete
        :delete-disabled="!rule"
        :is-repeatable="Boolean(rule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate ?? rule?.date"
        @close="closeDialog"
        @save="saveRule"
        @delete-occurrence="deleteOccurrence"
        @delete-from-now-on="deleteFromNowOn"
        @delete-all="deleteAll"
    >
        <FormPage
            v-if="rule"
            submit-label="Uložiť"
            :loading="loading"
            :show-submit="false"
        >
            <FormSection
                title="Služby"
                description="Toto je voľný čas pre vybrané rezervovateľné služby. Nevytvára skupinový termín."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Priradené rezervovateľné služby"
                    for="availability_service_ids"
                    required
                >
                    <MultiSelect
                        id="availability_service_ids"
                        v-model="rule.service_ids"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        display="chip"
                        placeholder="Vyberte jednu alebo viac služieb"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                :model="rule"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Nastavte, či sa má dostupnosť opakovať."
                enabled-id="availability_rule_is_enabled"
                repeats-id="availability_rule_repeats"
                repeat-every-id="availability_repeat_every"
                repeat-unit-id="availability_repeat_unit"
                enabled-label="Pravidlo dostupnosti je aktívne"
                repeats-label="Toto pravidlo sa opakuje periodicky"
            />

            <div
                v-if="rule.repeats && !hasValidRepeatSettings"
                class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600"
            >
                Pri opakovaní musí byť vyplnený dátum ukončenia opakovania, interval a jednotka opakovania.
            </div>
        </FormPage>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Pravidlo sa nepodarilo načítať.
        </div>
    </EventDialog>

    <OccurrenceScopeDialog
        v-model:visible="rescheduleChoiceVisible"
        mode="reschedule"
        subject-label="voľný čas"
        @select="submitRescheduleScope"
    />
</template>