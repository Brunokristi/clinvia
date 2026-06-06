import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useBookingRules({ props, dateTime, dialogs, isDateRangeInsideOpeningHours }) {
    const {
        formatDate,
        formatTime,
        getDateFromDate,
        getTimeFromDate,
    } = dateTime;

    const {
        availabilityRuleDialogVisible,
        deleteRuleDialogVisible,
        groupEventDialogVisible,
        pendingCalendarSelection,
        selectedRuleIndex,
        selectedRuleOccurrence,
        closeRuleDialog,
    } = dialogs;

    const repeatUnitOptions = [
        {
            label: 'dni',
            value: 'days',
        },
        {
            label: 'týždne',
            value: 'weeks',
        },
        {
            label: 'mesiace',
            value: 'months',
        },
    ];

    const emptyRule = () => ({
        id: null,

        date: null,
        starts_at: '08:00',
        ends_at: '16:00',

        slot_mode: 'free_bookable_time',

        service_id: null,
        service_ids: [],

        bookable_places: 1,

        repeats: false,
        repeat_every: 1,
        repeat_unit: 'weeks',
        repeat_ends_on: null,
        excluded_dates: [],

        is_enabled: true,
    });

    const ruleForm = useForm({
        rules: (props.branch.booking_availability_rules ?? []).map((rule) => ({
            id: rule.id,

            date: formatDate(rule.date ?? rule.starts_on ?? rule.start_date),
            starts_at: formatTime(rule.starts_at),
            ends_at: formatTime(rule.ends_at),

            slot_mode: rule.slot_mode ?? rule.booking_mode ?? 'free_bookable_time',

            service_id: rule.service_id
                ?? rule.selected_service_id
                ?? (rule.services?.length === 1 ? rule.services[0].id : null),

            service_ids: (rule.services ?? []).map((service) => service.id),

            bookable_places: rule.bookable_places ?? rule.capacity ?? 1,

            repeats: Boolean(rule.repeats),
            repeat_every: rule.repeat_every ?? rule.repeat_interval ?? 1,
            repeat_unit: rule.repeat_unit ?? 'weeks',
            repeat_ends_on: formatDate(rule.repeat_ends_on),
            excluded_dates: rule.excluded_dates ?? [],

            is_enabled: Boolean(rule.is_enabled),
        })),
    });

    const currentRule = computed(() => {
        if (selectedRuleIndex.value === null) {
            return null;
        }

        return ruleForm.rules[selectedRuleIndex.value] ?? null;
    });

    const getRuleServiceIds = (rule) => {
        if (rule.slot_mode === 'single_service_many_clients') {
            return rule.service_id ? [rule.service_id] : [];
        }

        return rule.service_ids ?? [];
    };

    const getServiceNames = (serviceIds) => {
        return props.services
            .filter((service) => serviceIds.includes(service.id))
            .map((service) => service.name)
            .join(', ');
    };

    const getRuleTitle = (rule) => {
        const services = getServiceNames(getRuleServiceIds(rule)) || 'Bez služby';

        if (rule.slot_mode === 'single_service_many_clients') {
            return `${services} · ${rule.bookable_places} miest`;
        }

        return `${services} · voľný čas`;
    };

    const getRepeatLabel = (rule) => {
        if (!rule.repeats) {
            return 'Neopakuje sa';
        }

        const unit = repeatUnitOptions.find((option) => option.value === rule.repeat_unit)?.label ?? '';

        return `Opakovať každých ${rule.repeat_every} ${unit}`;
    };

    const addRepeatInterval = (date, rule) => {
        const nextDate = new Date(date);
        const amount = Number(rule.repeat_every || 1);

        if (rule.repeat_unit === 'days') {
            nextDate.setDate(nextDate.getDate() + amount);
        }

        if (rule.repeat_unit === 'weeks') {
            nextDate.setDate(nextDate.getDate() + (amount * 7));
        }

        if (rule.repeat_unit === 'months') {
            nextDate.setMonth(nextDate.getMonth() + amount);
        }

        return nextDate;
    };

    const getRuleOccurrences = (rule) => {
        const occurrences = [];

        if (!rule.date || !rule.starts_at || !rule.ends_at) {
            return occurrences;
        }

        const excludedDates = rule.excluded_dates ?? [];
        const startDate = new Date(`${rule.date}T00:00:00`);

        const calendarStart = new Date();
        calendarStart.setMonth(calendarStart.getMonth() - 1);
        calendarStart.setHours(0, 0, 0, 0);

        const calendarEnd = new Date();
        calendarEnd.setMonth(calendarEnd.getMonth() + 6);
        calendarEnd.setHours(23, 59, 59, 999);

        const repeatEndDate = rule.repeat_ends_on
            ? new Date(`${rule.repeat_ends_on}T23:59:59`)
            : null;

        const maxEndDate = repeatEndDate && repeatEndDate < calendarEnd
            ? repeatEndDate
            : calendarEnd;

        if (!rule.repeats) {
            if (
                startDate >= calendarStart
                && startDate <= maxEndDate
                && !excludedDates.includes(rule.date)
            ) {
                occurrences.push(rule.date);
            }

            return occurrences;
        }

        let occurrenceDate = startDate;

        while (occurrenceDate <= maxEndDate) {
            const occurrenceDateString = getDateFromDate(occurrenceDate);

            if (
                occurrenceDate >= calendarStart
                && !excludedDates.includes(occurrenceDateString)
            ) {
                occurrences.push(occurrenceDateString);
            }

            occurrenceDate = addRepeatInterval(occurrenceDate, rule);
        }

        return occurrences;
    };

    const freeTimeRules = computed(() => {
        return ruleForm.rules
            .map((rule, index) => ({
                ...rule,
                ruleIndex: index,
            }))
            .filter((rule) => {
                return rule.is_enabled
                    && rule.slot_mode !== 'single_service_many_clients'
                    && rule.date
                    && rule.starts_at
                    && rule.ends_at;
            });
    });

    const getRulesPayload = () => ({
        rules: ruleForm.rules.map((rule) => ({
            id: rule.id,

            date: rule.date,
            starts_at: rule.starts_at,
            ends_at: rule.ends_at,

            slot_mode: rule.slot_mode,

            service_id: rule.slot_mode === 'single_service_many_clients'
                ? rule.service_id
                : null,

            service_ids: getRuleServiceIds(rule),

            bookable_places: rule.slot_mode === 'single_service_many_clients'
                ? rule.bookable_places
                : 1,

            repeats: rule.repeats,
            repeat_every: rule.repeats ? rule.repeat_every : 1,
            repeat_unit: rule.repeats ? rule.repeat_unit : 'weeks',
            repeat_ends_on: rule.repeat_ends_on ?? null,
            excluded_dates: rule.excluded_dates ?? [],

            is_enabled: rule.is_enabled,
        })),
    });

    const submitRules = (options = {}) => {
        ruleForm
            .transform(() => getRulesPayload())
            .put(route('branches.booking.rules.update', props.branch.id), {
                preserveScroll: true,
                preserveState: true,
                ...options,
            });
    };

    const saveRules = () => {
        submitRules({
            onSuccess: () => {
                closeRuleDialog();
                pendingCalendarSelection.value = null;
            },
        });
    };

    const deleteCurrentRule = () => {
        if (selectedRuleIndex.value === null) {
            return;
        }

        ruleForm.rules.splice(selectedRuleIndex.value, 1);

        selectedRuleIndex.value = null;
        selectedRuleOccurrence.value = null;
        availabilityRuleDialogVisible.value = false;
        groupEventDialogVisible.value = false;
    };

    const updateRuleFromDrop = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type !== 'rule') {
            return;
        }

        if (changeInfo.event.extendedProps.isRepeatedOccurrence) {
            changeInfo.revert();

            return;
        }

        if (!isDateRangeInsideOpeningHours(changeInfo.event.start, changeInfo.event.end)) {
            changeInfo.revert();

            return;
        }

        const index = changeInfo.event.extendedProps.ruleIndex;
        const rule = ruleForm.rules[index];

        if (!rule) {
            changeInfo.revert();

            return;
        }

        const previousRule = {
            ...rule,
            service_ids: [...(rule.service_ids ?? [])],
            excluded_dates: [...(rule.excluded_dates ?? [])],
        };

        rule.date = getDateFromDate(changeInfo.event.start);
        rule.starts_at = getTimeFromDate(changeInfo.event.start);
        rule.ends_at = getTimeFromDate(changeInfo.event.end);

        submitRules({
            onError: () => {
                Object.assign(rule, previousRule);
                changeInfo.revert();
            },
        });
    };

    const closeRuleDeletes = () => {
        deleteRuleDialogVisible.value = false;
        availabilityRuleDialogVisible.value = false;
        groupEventDialogVisible.value = false;
        selectedRuleOccurrence.value = null;
        selectedRuleIndex.value = null;
    };

    const deleteCurrentRuleOccurrence = () => {
        if (!selectedRuleOccurrence.value) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleOccurrence.value.ruleIndex];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.post(route('branches.booking.rules.exclude-date', [props.branch.id, rule.id]), {
            date: selectedRuleOccurrence.value.occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeRuleDeletes,
        });
    };

    const deleteCurrentRuleFromNowOn = () => {
        if (!selectedRuleOccurrence.value) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleOccurrence.value.ruleIndex];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.post(route('branches.booking.rules.end-before-date', [props.branch.id, rule.id]), {
            date: selectedRuleOccurrence.value.occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeRuleDeletes,
        });
    };

    const deleteCurrentRuleEverywhere = () => {
        if (selectedRuleIndex.value === null) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleIndex.value];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.delete(route('branches.booking.rules.destroy', [props.branch.id, rule.id]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeRuleDeletes,
        });
    };

    return {
        currentRule,
        emptyRule,
        freeTimeRules,
        getRepeatLabel,
        getRuleOccurrences,
        getRuleTitle,
        repeatUnitOptions,
        ruleForm,

        deleteCurrentRule,
        deleteCurrentRuleEverywhere,
        deleteCurrentRuleFromNowOn,
        deleteCurrentRuleOccurrence,
        saveRules,
        updateRuleFromDrop,
    };
}
