<script setup>
import { computed, watch } from 'vue';

import EventDetailDialog from '@/Components/Booking/Common/EventDetailDialog.vue';
import EventOccurrenceActions from '@/Components/Booking/Common/EventOccurrenceActions.vue';
import { useRecurringImpactPreview } from '@/Composables/Bookings/useRecurringImpactPreview';

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

const selectedOccurrenceDate = computed(() => {
    return props.selectedRuleOccurrence?.occurrenceOriginalDate
        ?? props.selectedRuleOccurrence?.occurrenceDate
        ?? props.rule?.date
        ?? null;
});

const buildOccurrenceDateTime = (date, time) => {
    const datePart = String(date ?? '').slice(0, 10);
    const timePart = String(time ?? '').slice(0, 8);

    if (!datePart || !timePart) {
        return null;
    }

    return `${datePart}T${timePart}`;
};

const availabilityRuleBranchId = computed(() => Number(props.rule?.branch_id ?? props.rule?.branch?.id ?? 0) || null);

const {
    impactPreview: ruleDeleteImpactPreview,
    fetchImpactPreview: fetchRuleDeleteImpactPreview,
    clearImpactPreview: clearRuleDeleteImpactPreview,
} = useRecurringImpactPreview(availabilityRuleBranchId);

const ruleDeleteSelectedOccurrence = computed(() => {
    if (!props.rule?.id) {
        return null;
    }

    const startsAt = buildOccurrenceDateTime(selectedOccurrenceDate.value, props.rule?.starts_at);
    const endsAt = buildOccurrenceDateTime(selectedOccurrenceDate.value, props.rule?.ends_at);

    return {
        rule_id: props.rule.id,
        event_id: props.rule.id,
        root_event_id: props.rule.root_event_id ?? null,
        occurrence_starts_at: startsAt,
        occurrence_ends_at: endsAt,
        occurrence_original_starts_at: startsAt,
        starts_at: startsAt,
        ends_at: endsAt,
        display_key: props.selectedRuleOccurrence?.displayKey ?? null,
    };
});

watch(
    () => [props.visible, props.rule?.id, selectedOccurrenceDate.value],
    async ([visible]) => {
        if (!visible || !props.rule || !props.rule.repeats) {
            clearRuleDeleteImpactPreview();

            return;
        }

        await fetchRuleDeleteImpactPreview({
            action: 'delete',
            selectedOccurrence: ruleDeleteSelectedOccurrence.value,
        });
    },
    { immediate: true },
);

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

const getRecurrenceFrequency = (recurrence, rule) => {
    const frequency = recurrence?.frequency
        ?? recurrence?.repeat_unit
        ?? recurrence?.unit
        ?? (rule?.repeat_unit === 'days' ? 'daily' : (rule?.repeat_unit === 'months' ? 'monthly' : 'weekly'));

    if (['daily', 'weekly', 'monthly', 'yearly'].includes(frequency)) {
        return frequency;
    }

    if (frequency === 'days') {
        return 'daily';
    }

    if (frequency === 'months') {
        return 'monthly';
    }

    return 'weekly';
};

const addRecurrenceInterval = (date, frequency, interval) => {
    const next = new Date(date);

    if (frequency === 'daily') {
        next.setDate(next.getDate() + interval);

        return next;
    }

    if (frequency === 'monthly') {
        next.setMonth(next.getMonth() + interval);

        return next;
    }

    if (frequency === 'yearly') {
        next.setFullYear(next.getFullYear() + interval);

        return next;
    }

    next.setDate(next.getDate() + (7 * interval));

    return next;
};

const toIsoWeekday = (date) => {
    const weekday = date.getDay();

    return weekday === 0 ? 7 : weekday;
};

const getWeekStartMonday = (date) => {
    const monday = new Date(date);
    const isoWeekday = toIsoWeekday(monday);

    monday.setDate(monday.getDate() - (isoWeekday - 1));
    monday.setHours(0, 0, 0, 0);

    return monday;
};

const weekdayCodeFromDate = (date) => {
    return ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'][date.getDay()];
};

const getRuleWeekdayCodes = (rule) => {
    const recurrenceWeekdays = Array.isArray(rule?.recurrence?.weekdays)
        ? rule.recurrence.weekdays
        : [];
    const legacyWeekdays = Array.isArray(rule?.repeat_weekdays)
        ? rule.repeat_weekdays
        : [];

    const weekdays = recurrenceWeekdays.length ? recurrenceWeekdays : legacyWeekdays;

    return [...new Set(
        weekdays
            .map((weekday) => String(weekday).toUpperCase())
            .filter((weekday) => ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'].includes(weekday)),
    )];
};

const getRuleOccurrences = (rule) => {
    const startDate = parseDateOnly(rule?.date);

    if (!startDate) {
        return [];
    }

    const excludedDates = new Set(
        Array.isArray(rule?.excluded_dates)
            ? rule.excluded_dates.map((value) => String(value).slice(0, 10))
            : [],
    );

    if (!rule?.repeats) {
        const startDateString = startDate.toISOString().slice(0, 10);

        return excludedDates.has(startDateString) ? [] : [startDateString];
    }

    const recurrence = rule?.recurrence ?? null;
    const frequency = getRecurrenceFrequency(recurrence, rule);
    const interval = Math.max(1, Number(recurrence?.interval ?? rule?.repeat_every ?? 1));
    const untilDate = parseDateOnly(recurrence?.ends?.until ?? rule?.repeat_ends_on ?? null);
    const maxOccurrences = Math.max(0, Number(recurrence?.ends?.count ?? 0));
    const maxIterations = 2000;
    const occurrences = [];

    if (!untilDate && maxOccurrences <= 0) {
        return [];
    }

    if (frequency === 'weekly') {
        const weekdayCodes = getRuleWeekdayCodes(rule);
        const startWeekdayCode = weekdayCodeFromDate(startDate);
        const activeWeekdays = weekdayCodes.length ? weekdayCodes : [startWeekdayCode];
        const seriesWeekStart = getWeekStartMonday(startDate);
        let candidate = new Date(startDate);
        let iterations = 0;

        while (iterations < maxIterations) {
            iterations += 1;

            if (untilDate && candidate > untilDate) {
                break;
            }

            const candidateDateString = candidate.toISOString().slice(0, 10);
            const candidateWeekStart = getWeekStartMonday(candidate);
            const weekDiff = Math.floor((candidateWeekStart.getTime() - seriesWeekStart.getTime()) / (7 * 24 * 60 * 60 * 1000));
            const weekdayMatches = activeWeekdays.includes(weekdayCodeFromDate(candidate));
            const intervalMatches = weekDiff >= 0 && weekDiff % interval === 0;

            if (weekdayMatches && intervalMatches && !excludedDates.has(candidateDateString)) {
                occurrences.push(candidateDateString);

                if (maxOccurrences > 0 && occurrences.length >= maxOccurrences) {
                    break;
                }
            }

            candidate.setDate(candidate.getDate() + 1);
        }

        return occurrences;
    }

    let candidate = new Date(startDate);
    let iterations = 0;

    while (iterations < maxIterations) {
        iterations += 1;

        if (untilDate && candidate > untilDate) {
            break;
        }

        const candidateDateString = candidate.toISOString().slice(0, 10);

        if (!excludedDates.has(candidateDateString)) {
            occurrences.push(candidateDateString);

            if (maxOccurrences > 0 && occurrences.length >= maxOccurrences) {
                break;
            }
        }

        candidate = addRecurrenceInterval(candidate, frequency, interval);
    }

    return occurrences;
};

const deleteCountOccurrence = computed(() => ruleDeleteImpactPreview.value?.occurrence?.count ?? 1);

const deleteCountSeries = computed(() => ruleDeleteImpactPreview.value?.series?.count ?? null);

const deleteCountFromDate = computed(() => ruleDeleteImpactPreview.value?.from_date?.count ?? null);
const deleteMessageOccurrence = computed(() => ruleDeleteImpactPreview.value?.occurrence?.message ?? null);
const deleteMessageFromDate = computed(() => ruleDeleteImpactPreview.value?.from_date?.message ?? null);
const deleteMessageSeries = computed(() => ruleDeleteImpactPreview.value?.series?.message ?? null);

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
        :delete-message-occurrence="deleteMessageOccurrence"
        :delete-message-from-date="deleteMessageFromDate"
        :delete-message-series="deleteMessageSeries"
        :show-duplicate="false"
        :show-date-time-fields="false"
        scope-mode="update"
        scope-subject-label="pravidlo rezervácií"
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