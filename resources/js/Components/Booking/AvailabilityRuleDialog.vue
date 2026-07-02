<script setup>
import { computed } from 'vue';

import EventDetailDialog from '@/Components/Booking/Common/EventDetailDialog.vue';
import EventOccurrenceActions from '@/Components/Booking/Common/EventOccurrenceActions.vue';

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
    'delete',
    'duplicate',
    'edit-in-unified-form',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const dialogTitle = computed(() => {
    if (!props.rule) {
        return 'Pravidlo rezervácií';
    }

    return props.rule.id
        ? 'Pravidlo rezervácií'
        : 'Nové pravidlo rezervácií';
});

const createDateFromRule = (timeValue = null) => {
    if (!props.rule?.date) {
        return null;
    }

    const datePart = String(props.rule.date).slice(0, 10);
    const timePart = timeValue
        ? String(timeValue).slice(0, 5)
        : '00:00';

    const parsed = new Date(`${datePart}T${timePart}:00`);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatSlovakDate = (value) => {
    if (!value) {
        return '—';
    }

    const datePart = String(value).slice(0, 10);
    const parsed = new Date(`${datePart}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString('sk-SK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatSlovakTime = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleTimeString('sk-SK', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const ruleDateModel = computed(() => {
    return createDateFromRule();
});

const ruleStartsAtModel = computed(() => {
    return createDateFromRule(props.rule?.starts_at ?? null);
});

const ruleEndsAtModel = computed(() => {
    return createDateFromRule(props.rule?.ends_at ?? null);
});

const selectedServices = computed(() => {
    if (!props.rule?.service_ids?.length) {
        return [];
    }

    return props.services.filter((service) => {
        return props.rule.service_ids.includes(service.id);
    });
});

const repetitionLabel = computed(() => {
    if (!props.rule?.repeats) {
        return 'Neopakuje sa';
    }

    return 'Opakuje sa';
});

const occurrenceLabel = computed(() => {
    const occurrenceDate = props.selectedRuleOccurrence?.occurrenceDate
        ?? props.rule?.date
        ?? null;

    return formatSlovakDate(occurrenceDate);
});

const parseDateOnly = (value) => {
    if (!value) {
        return null;
    }

    const parsed = new Date(`${String(value).slice(0, 10)}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const addRuleInterval = (date, unit, every) => {
    const next = new Date(date);

    if (unit === 'days') {
        next.setDate(next.getDate() + every);

        return next;
    }

    if (unit === 'months') {
        next.setMonth(next.getMonth() + every);

        return next;
    }

    next.setDate(next.getDate() + (7 * every));

    return next;
};

const countRuleOccurrencesBetween = (startDate, endDate, rule) => {
    if (!startDate || !endDate || endDate < startDate) {
        return null;
    }

    const unit = ['days', 'weeks', 'months'].includes(rule?.repeat_unit)
        ? rule.repeat_unit
        : 'weeks';
    const every = Math.max(1, Number(rule?.repeat_every ?? 1));

    let count = 0;
    let cursor = new Date(startDate);

    while (cursor <= endDate && count < 1000) {
        count += 1;
        cursor = addRuleInterval(cursor, unit, every);
    }

    return count;
};

const deleteCountOccurrence = computed(() => 1);

const deleteCountSeries = computed(() => {
    if (!props.rule?.repeats) {
        return 1;
    }

    const start = parseDateOnly(props.rule?.date);
    const end = parseDateOnly(props.rule?.repeat_ends_on);

    return countRuleOccurrencesBetween(start, end, props.rule);
});

const deleteCountFromDate = computed(() => {
    if (!props.rule?.repeats) {
        return 1;
    }

    const fromDate = parseDateOnly(
        props.selectedRuleOccurrence?.occurrenceDate
        ?? props.rule?.date,
    );
    const end = parseDateOnly(props.rule?.repeat_ends_on);

    return countRuleOccurrencesBetween(fromDate, end, props.rule);
});

const ruleInfoItems = computed(() => {
    if (!props.rule) {
        return [];
    }

    return [
        {
            key: 'occurrence',
            icon: 'pi pi-calendar',
            label: 'Dátum',
            value: occurrenceLabel.value,
        },
        {
            key: 'duration',
            icon: 'pi pi-clock',
            label: 'Trvanie',
            value: ruleStartsAtModel.value && ruleEndsAtModel.value
                ? `${formatSlovakTime(ruleStartsAtModel.value)} – ${formatSlovakTime(ruleEndsAtModel.value)}`
                : '—',
        },
        {
            key: 'services',
            icon: 'pi pi-briefcase',
            label: 'Služby',
            value: selectedServices.value.length ? selectedServices.value : '—',
            type: 'services',
        },
        {
            key: 'repetition',
            icon: 'pi pi-refresh',
            label: 'Opakovanie',
            value: repetitionLabel.value,
        },
    ];
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const openUnifiedEditor = () => {
    if (!props.rule) {
        return;
    }

    emit('edit-in-unified-form', {
        rule: props.rule,
        selectedRuleOccurrence: props.selectedRuleOccurrence,
    });
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

const duplicateRule = () => {
    emit('duplicate');
};
</script>

<template>
    <EventDetailDialog
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        width="max-w-3xl"
        :date="ruleDateModel"
        :starts-at="ruleStartsAtModel"
        :ends-at="ruleEndsAtModel"
        :loading="loading"
        show-delete
        :delete-disabled="!rule"
        :is-repeatable="Boolean(rule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate ?? rule?.date"
        :delete-count-occurrence="deleteCountOccurrence"
        :delete-count-from-date="deleteCountFromDate"
        :delete-count-series="deleteCountSeries"
        :show-duplicate="false"
        :show-date-time-fields="false"
        scope-mode="update"
        scope-subject-label="voľný čas"
        @close="closeDialog"
        @delete-occurrence="deleteOccurrence"
        @delete-from-now-on="deleteFromNowOn"
        @delete-all="deleteAll"
        @duplicate="duplicateRule"
    >
        <template #footer-start>
            <EventOccurrenceActions
                v-if="rule"
                @duplicate="duplicateRule"
                @edit="openUnifiedEditor"
            />
        </template>

        <div
            v-if="rule"
            class="space-y-4"
        >
            <div class="md:col-span-2">
                <div class="space-y-4">
                    <div
                        v-for="item in ruleInfoItems"
                        :key="item.key"
                        class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3"
                    >
                        <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-soft text-accent">
                            <i
                                :class="item.icon"
                                class="text-base"
                            />
                        </div>

                        <div class="flex min-w-0 items-center">
                            <div
                                v-if="item.type === 'services' && Array.isArray(item.value)"
                                class="w-full space-y-1"
                            >
                                <div
                                    v-for="service in item.value"
                                    :key="service.id"
                                    class="flex items-center rounded-md bg-white text-sm font-medium text-dark"
                                >
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ service.name }}
                                    </span>
                                </div>
                            </div>

                            <p
                                v-else
                                class="break-words text-sm font-medium text-dark"
                            >
                                {{ item.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Pravidlo sa nepodarilo načítať.
        </div>
    </EventDetailDialog>
</template>