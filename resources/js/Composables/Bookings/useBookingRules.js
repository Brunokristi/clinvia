import { router, useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed, watch } from 'vue';

export function useBookingRules({ props, dateTime, dialogs, isDateRangeInsideOpeningHours, hideCalendarEventId, restoreCalendarEventId, reloadCalendarData }) {
    const toast = useToast();

    // Success notifications are shown centrally from flash messages in AdminLayout.
    const showSuccess = () => { };

    const showError = (fallback, errors = {}) => {
        const firstError = Object.values(errors ?? {})?.[0];

        toast.add({
            severity: 'error',
            summary: 'Chyba',
            detail: Array.isArray(firstError) ? firstError[0] : firstError || fallback,
            life: 5000,
        });
    };

    const reloadRuleState = () => {
        if (typeof reloadCalendarData === 'function') {
            reloadCalendarData();

            return;
        }

        router.reload({
            only: [
                'availabilityRules',
                'calendarBookings',
                'calendarCapacityWindows',
                'disabledDays',
                'pendingAppointmentRequests',
                'todayBookingsCount',
                'unreadMessagesCount',
            ],
            preserveScroll: true,
            preserveState: true,
        });
    };

    const reloadRuleStateSoon = () => {
        window.setTimeout(() => {
            reloadRuleState();
        }, 250);
    };

    const {
        formatDate,
        formatTime,
        getDateFromDate,
        getTimeFromDate,
    } = dateTime;

    const {
        availabilityRuleDialogVisible,
        deleteRuleDialogVisible,
        ruleRescheduleScopeDialogVisible,
        pendingCalendarSelection,
        openCreateBookingWithPrefill,
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

        service_ids: [],

        repeats: false,
        repeat_every: 1,
        repeat_unit: 'weeks',
        repeat_weekdays: [],
        repeat_ends_on: null,
        excluded_dates: [],

        is_enabled: true,
    });

    const getRuleServiceIds = (rule) => {
        if (Array.isArray(rule.service_ids) && rule.service_ids.length) {
            return rule.service_ids
                .map((id) => Number(id))
                .filter((id) => id > 0);
        }

        return (rule.services ?? [])
            .map((service) => Number(service.id))
            .filter((id) => id > 0);
    };

    const getRuleId = (rule) => {
        const id = Number(rule.id);

        return Number.isInteger(id) && id > 0
            ? id
            : null;
    };

    const mapRuleFromBackend = (rule) => ({
        id: getRuleId(rule),

        date: formatDate(rule.date ?? rule.starts_on ?? rule.start_date),
        starts_at: formatTime(rule.starts_at),
        ends_at: formatTime(rule.ends_at),

        service_ids: getRuleServiceIds(rule),

        repeats: Boolean(rule.repeats),
        repeat_every: Number(rule.repeat_every ?? rule.repeat_interval ?? 1),
        repeat_unit: rule.repeat_unit ?? 'weeks',
        repeat_weekdays: Array.isArray(rule.repeat_weekdays) ? [...rule.repeat_weekdays] : [],
        repeat_ends_on: formatDate(rule.repeat_ends_on),
        excluded_dates: rule.excluded_dates ?? [],

        is_enabled: Boolean(rule.is_enabled),
    });

    const getInitialRules = () => {
        if (Array.isArray(props.availabilityRules)) {
            return props.availabilityRules;
        }

        return props.branch.booking_availability_rules ?? [];
    };

    const ruleForm = useForm({
        rules: getInitialRules().map(mapRuleFromBackend),
    });

    const refreshRulesFromProps = () => {
        if (ruleForm.processing) {
            return;
        }

        ruleForm.rules = getInitialRules().map(mapRuleFromBackend);
    };

    watch(
        () => props.availabilityRules,
        () => {
            refreshRulesFromProps();
        },
        {
            deep: true,
        },
    );

    const currentRule = computed(() => {
        if (selectedRuleIndex.value === null) {
            return null;
        }

        return ruleForm.rules[selectedRuleIndex.value] ?? null;
    });

    const getSelectedRule = () => {
        if (selectedRuleIndex.value === null) {
            return null;
        }

        return ruleForm.rules[selectedRuleIndex.value] ?? null;
    };

    const duplicateCurrentRule = () => {
        const rule = getSelectedRule();

        if (!rule) {
            return;
        }

        const date = selectedRuleOccurrence.value?.occurrenceDate ?? rule.date;
        const repeatUnit = rule.repeat_unit ?? 'weeks';
        const recurrenceFrequency = repeatUnit === 'days'
            ? 'daily'
            : (repeatUnit === 'months' ? 'monthly' : 'weekly');
        const repeatWeekdays = Array.isArray(rule.repeat_weekdays)
            ? [...rule.repeat_weekdays]
            : [];

        openCreateBookingWithPrefill({
            create_type: 'rule',
            date,
            starts_at: `${date} ${rule.starts_at}:00`,
            ends_at: `${date} ${rule.ends_at}:00`,
            service_ids: [...(rule.service_ids ?? [])],
            service_id: rule.service_ids?.[0] ?? null,
            public_booking_type: rule.public_booking_type ?? 'immediate_booking',
            recurrence: rule.repeats
                ? {
                    frequency: recurrenceFrequency,
                    interval: Number(rule.repeat_every ?? 1),
                    weekdays: recurrenceFrequency === 'weekly' ? repeatWeekdays : [],
                    ends: {
                        type: rule.repeat_ends_on ? 'on' : 'never',
                        count: null,
                        until: rule.repeat_ends_on ?? null,
                    },
                }
                : null,
            is_enabled: Boolean(rule.is_enabled ?? true),
        });

        closeRuleDialogSafely();
    };

    const getSelectedOccurrenceDate = () => {
        const rule = getSelectedRule();

        return selectedRuleOccurrence.value?.occurrenceDate
            ?? rule?.date
            ?? null;
    };

    const getSelectedRuleEventId = () => {
        const rule = getSelectedRule();
        const occurrenceDate = getSelectedOccurrenceDate();

        if (!rule || !occurrenceDate) {
            return null;
        }

        return `rule-${rule.id ?? 'new'}-${selectedRuleIndex.value}-${occurrenceDate}`;
    };

    const getServiceNames = (serviceIds) => {
        return props.services
            .filter((service) => serviceIds.includes(service.id))
            .map((service) => service.name)
            .join(', ');
    };

    const getRuleTitle = (rule) => {
        const services = getServiceNames(rule.service_ids ?? []) || 'Bez služby';

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

    const blockedDateSet = computed(() => {
        return new Set(
            (props.disabledDays ?? [])
                .map((disabledDay) => String(disabledDay?.date ?? '').slice(0, 10))
                .filter(Boolean),
        );
    });

    const isBlockedDate = (dateString) => blockedDateSet.value.has(dateString);

    const getRuleWeekdayCodes = (rule) => {
        if (!Array.isArray(rule?.repeat_weekdays)) {
            return [];
        }

        return [...new Set(rule.repeat_weekdays
            .map((weekday) => String(weekday).toUpperCase())
            .filter((weekday) => ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'].includes(weekday)))];
    };

    const getWeeklyRuleOccurrences = ({
        startDate,
        maxEndDate,
        calendarStart,
        excludedDates,
        rule,
    }) => {
        const occurrences = [];
        const intervalWeeks = Math.max(1, Number(rule.repeat_every || 1));
        const weekdayCodes = getRuleWeekdayCodes(rule);
        const seriesWeekStart = getWeekStartMonday(startDate);
        const firstCandidate = new Date(Math.max(startDate.getTime(), calendarStart.getTime()));

        firstCandidate.setHours(0, 0, 0, 0);

        let candidate = firstCandidate;

        while (candidate <= maxEndDate) {
            const candidateDateString = getDateFromDate(candidate);
            const candidateWeekdayCode = weekdayCodeFromDate(candidate);
            const candidateWeekStart = getWeekStartMonday(candidate);
            const weekDiff = Math.floor((candidateWeekStart.getTime() - seriesWeekStart.getTime()) / (7 * 24 * 60 * 60 * 1000));
            const weekdayMatches = weekdayCodes.length
                ? weekdayCodes.includes(candidateWeekdayCode)
                : candidateWeekdayCode === weekdayCodeFromDate(startDate);
            const intervalMatches = weekDiff >= 0 && weekDiff % intervalWeeks === 0;

            if (
                weekdayMatches
                && intervalMatches
                && !excludedDates.includes(candidateDateString)
                && !isBlockedDate(candidateDateString)
            ) {
                occurrences.push(candidateDateString);
            }

            candidate = new Date(candidate);
            candidate.setDate(candidate.getDate() + 1);
        }

        return occurrences;
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
                && !isBlockedDate(rule.date)
            ) {
                occurrences.push(rule.date);
            }

            return occurrences;
        }

        if (rule.repeat_unit === 'weeks') {
            return getWeeklyRuleOccurrences({
                startDate,
                maxEndDate,
                calendarStart,
                excludedDates,
                rule,
            });
        }

        let occurrenceDate = startDate;

        while (occurrenceDate <= maxEndDate) {
            const occurrenceDateString = getDateFromDate(occurrenceDate);

            if (
                occurrenceDate >= calendarStart
                && !excludedDates.includes(occurrenceDateString)
                && !isBlockedDate(occurrenceDateString)
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

            service_ids: rule.service_ids ?? [],
            public_booking_type: rule.public_booking_type ?? null,

            repeats: Boolean(rule.repeats),
            repeat_every: rule.repeats ? Number(rule.repeat_every || 1) : 1,
            repeat_unit: rule.repeats ? (rule.repeat_unit || 'weeks') : 'weeks',
            repeat_weekdays: rule.repeats ? (rule.repeat_weekdays ?? []) : [],
            repeat_ends_on: rule.repeats ? rule.repeat_ends_on : null,
            excluded_dates: rule.excluded_dates ?? [],

            is_enabled: Boolean(rule.is_enabled),
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

    const cloneRule = (rule) => ({
        ...rule,
        service_ids: [...(rule.service_ids ?? [])],
        excluded_dates: [...(rule.excluded_dates ?? [])],
    });

    const restorePendingRuleReschedule = () => {
        if (selectedRuleOccurrence.value?.originalRule && currentRule.value) {
            Object.assign(currentRule.value, cloneRule(selectedRuleOccurrence.value.originalRule));
        }

        ruleRescheduleScopeDialogVisible.value = false;
    };

    const closeRuleDialogSafely = () => {
        restorePendingRuleReschedule();
        closeRuleDialog();
        pendingCalendarSelection.value = null;
    };

    const closeRuleAfterSave = () => {
        ruleRescheduleScopeDialogVisible.value = false;
        closeRuleDialog();
        pendingCalendarSelection.value = null;
    };

    const saveRules = (options = {}) => {
        const scope = typeof options === 'string'
            ? options
            : options.reschedule_scope ?? null;

        const rule = currentRule.value;
        const occurrenceDate = getSelectedOccurrenceDate();

        if (scope && rule?.id && occurrenceDate) {
            router.post(route('branches.booking.rules.reschedule', [
                props.branch.id,
                rule.id,
            ]), {
                occurrence_date: occurrenceDate,
                date: rule.date,
                starts_at: rule.starts_at,
                ends_at: rule.ends_at,
                reschedule_scope: scope,
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    closeRuleAfterSave();
                    showSuccess('Voľný čas bol presunutý.');
                    reloadRuleStateSoon();
                },
                onError: (errors) => {
                    restorePendingRuleReschedule();
                    showError('Voľný čas sa nepodarilo presunúť.', errors);
                },
            });

            return;
        }

        submitRules({
            onSuccess: () => {
                closeRuleAfterSave();
                showSuccess('Voľný čas bol uložený.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                showError('Voľný čas sa nepodarilo uložiť.', errors);
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
    };

    const updateRuleFromDrop = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type !== 'rule') {
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

        const previousRule = cloneRule(rule);

        const nextRule = {
            date: getDateFromDate(changeInfo.event.start),
            starts_at: getTimeFromDate(changeInfo.event.start),
            ends_at: getTimeFromDate(changeInfo.event.end),
        };

        if (rule.repeats && rule.id) {
            changeInfo.revert();

            Object.assign(rule, nextRule);

            selectedRuleIndex.value = index;
            selectedRuleOccurrence.value = {
                ruleIndex: index,
                occurrenceDate: changeInfo.event.extendedProps.occurrenceDate ?? previousRule.date,
                isRepeatedOccurrence: true,
                originalRule: previousRule,
                pendingReschedule: nextRule,
                source: 'calendar_drag',
            };

            ruleRescheduleScopeDialogVisible.value = true;

            return;
        }

        Object.assign(rule, nextRule);

        submitRules({
            onError: (errors) => {
                Object.assign(rule, previousRule);
                changeInfo.revert();
                showError('Voľný čas sa nepodarilo presunúť.', errors);
            },
            onSuccess: () => {
                showSuccess('Voľný čas bol presunutý.');
                reloadRuleStateSoon();
            },
        });
    };

    const closeRuleDeletes = () => {
        deleteRuleDialogVisible.value = false;
        availabilityRuleDialogVisible.value = false;
        selectedRuleOccurrence.value = null;
        selectedRuleIndex.value = null;
    };

    const deleteCurrentRuleOccurrence = () => {
        const rule = getSelectedRule();
        const occurrenceDate = getSelectedOccurrenceDate();
        const eventId = getSelectedRuleEventId();

        if (!rule || !occurrenceDate) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        hideCalendarEventId?.(eventId);

        router.post(route('branches.booking.rules.exclude-date', [props.branch.id, rule.id]), {
            date: occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Výskyt voľného času bol vymazaný.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Výskyt voľného času sa nepodarilo vymazať.', errors);
            },
        });
    };

    const deleteCurrentRuleFromNowOn = () => {
        const rule = getSelectedRule();
        const occurrenceDate = getSelectedOccurrenceDate();
        const eventId = getSelectedRuleEventId();

        if (!rule || !occurrenceDate) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        hideCalendarEventId?.(eventId);

        router.post(route('branches.booking.rules.end-before-date', [props.branch.id, rule.id]), {
            date: occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Tento a nasledujúce výskyty boli vymazané.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Výskyty voľného času sa nepodarilo vymazať.', errors);
            },
        });
    };

    const deleteCurrentRuleEverywhere = () => {
        const rule = getSelectedRule();
        const eventId = getSelectedRuleEventId();

        if (!rule) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        hideCalendarEventId?.(eventId);

        router.delete(route('branches.booking.rules.destroy', [props.branch.id, rule.id]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Voľný čas bol vymazaný.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Voľný čas sa nepodarilo vymazať.', errors);
            },
        });
    };

    const deleteCurrentRuleByScope = (scope = 'series') => {
        if (scope === 'occurrence') {
            deleteCurrentRuleOccurrence();

            return;
        }

        if (scope === 'from_date') {
            deleteCurrentRuleFromNowOn();

            return;
        }

        deleteCurrentRuleEverywhere();
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

        closeRuleDialogSafely,
        deleteCurrentRule,
        deleteCurrentRuleByScope,
        deleteCurrentRuleEverywhere,
        deleteCurrentRuleFromNowOn,
        deleteCurrentRuleOccurrence,
        duplicateCurrentRule,
        saveRules,
        updateRuleFromDrop,
    };
}