import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useRecurringImpactPreview } from './useRecurringImpactPreview';
import { scopeSuccessMessage, useCalendarActionFeedback } from './useCalendarActionFeedback';

export function useBookingRules({ props, dateTime, dialogs, isDateRangeInsideOpeningHours, hideCalendarEventId, restoreCalendarEventId, reloadCalendarData }) {
    const feedback = useCalendarActionFeedback();

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
        reloadRuleState();
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

    const pendingRuleReschedule = ref(null);
    const pendingRuleScopeSubmit = ref(false);
    const {
        impactPreview: ruleRescheduleImpactPreview,
        fetchImpactPreview: fetchRuleRescheduleImpactPreview,
        clearImpactPreview: clearRuleRescheduleImpactPreview,
    } = useRecurringImpactPreview(props.branch.id);

    const emptyRule = () => ({
        id: null,

        date: null,
        starts_at: '08:00',
        ends_at: '16:00',

        service_ids: [],
        public_booking_type: 'immediate_booking',

        repeats: false,
        repeat_every: 1,
        repeat_unit: 'weeks',
        repeat_weekdays: [],
        repeat_ends_on: null,
        recurrence: null,
        excluded_dates: [],
        occurrence_overrides: [],

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
        series_uuid: rule.series_uuid ?? null,

        date: formatDate(rule.date ?? rule.starts_on ?? rule.start_date),
        starts_at: formatTime(rule.starts_at),
        ends_at: formatTime(rule.ends_at),

        service_ids: getRuleServiceIds(rule),
        public_booking_type: rule.public_booking_type ?? 'immediate_booking',

        repeats: Boolean(rule.repeats),
        repeat_every: Number(rule.repeat_every ?? rule.repeat_interval ?? 1),
        repeat_unit: rule.repeat_unit ?? 'weeks',
        repeat_weekdays: Array.isArray(rule.repeat_weekdays) ? [...rule.repeat_weekdays] : [],
        repeat_ends_on: formatDate(rule.repeat_ends_on),
        recurrence: rule.recurrence ?? rule.recurrence_rule ?? null,
        excluded_dates: rule.excluded_dates ?? [],
        occurrence_overrides: Array.isArray(rule.occurrence_overrides)
            ? rule.occurrence_overrides
                .map((override) => ({
                    original_date: String(override?.original_date ?? '').slice(0, 10),
                    date: String(override?.date ?? '').slice(0, 10),
                    starts_at: override?.starts_at ? String(override.starts_at).slice(0, 5) : null,
                    ends_at: override?.ends_at ? String(override.ends_at).slice(0, 5) : null,
                    status: override?.status ?? null,
                }))
                .filter((override) => override.original_date && override.date)
            : [],

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

    watch(
        () => [selectedRuleIndex.value, ruleForm.rules.length],
        () => {
            if (selectedRuleIndex.value === null) {
                return;
            }

            if (ruleForm.rules[selectedRuleIndex.value]) {
                return;
            }

            closeRuleDeletes();
        },
    );

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

        return selectedRuleOccurrence.value?.occurrenceOriginalDate
            ?? selectedRuleOccurrence.value?.occurrenceDate
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

    const getSelectedRuleEventIds = () => {
        const baseId = getSelectedRuleEventId();

        if (!baseId) {
            return [];
        }

        return [baseId, `${baseId}-override`];
    };

    const getServiceNames = (serviceIds) => {
        return props.services
            .filter((service) => serviceIds.includes(service.id))
            .map((service) => service.name)
            .join(', ');
    };

    const getRuleTitle = (rule) => {
        const services = getServiceNames(rule.service_ids ?? []) || 'Bez služby';

        return `${services} · pravidlo rezervácií`;
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

    const isRuleOccurrenceInsideOpeningHours = (dateString, rule) => {
        if (typeof isDateRangeInsideOpeningHours !== 'function') {
            return true;
        }

        if (!dateString || !rule?.starts_at || !rule?.ends_at) {
            return false;
        }

        const start = new Date(`${dateString}T${rule.starts_at}:00`);
        const end = new Date(`${dateString}T${rule.ends_at}:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
            return false;
        }

        return isDateRangeInsideOpeningHours(start, end);
    };

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
        const maxOccurrences = Math.max(0, Number(rule?.recurrence?.ends?.count ?? 0));
        let producedOccurrences = 0;
        let candidate = new Date(startDate);

        candidate.setHours(0, 0, 0, 0);

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
                && isRuleOccurrenceInsideOpeningHours(candidateDateString, rule)
            ) {
                producedOccurrences += 1;

                if (maxOccurrences > 0 && producedOccurrences > maxOccurrences) {
                    break;
                }

                if (candidate >= calendarStart) {
                    occurrences.push(candidateDateString);
                }
            }

            if (maxOccurrences > 0 && producedOccurrences >= maxOccurrences) {
                break;
            }

            candidate = new Date(candidate);
            candidate.setDate(candidate.getDate() + 1);
        }

        return occurrences;
    };

    const getCountLimitedMaxEndDate = (startDate, rule) => {
        const maxOccurrences = Math.max(0, Number(rule?.recurrence?.ends?.count ?? 0));

        if (maxOccurrences <= 0) {
            return null;
        }

        const frequency = rule?.recurrence?.frequency ?? (rule?.repeat_unit === 'days' ? 'daily' : (rule?.repeat_unit === 'months' ? 'monthly' : 'weekly'));
        const interval = Math.max(1, Number(rule?.recurrence?.interval ?? rule?.repeat_every ?? 1));
        let cursor = new Date(startDate);

        for (let index = 1; index < maxOccurrences; index += 1) {
            cursor = addRecurrenceInterval(cursor, frequency, interval);
        }

        cursor.setHours(23, 59, 59, 999);

        return cursor;
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
        const countLimitedEndDate = getCountLimitedMaxEndDate(startDate, rule);

        const maxEndDateCandidates = [calendarEnd, repeatEndDate, countLimitedEndDate]
            .filter(Boolean)
            .sort((first, second) => first.getTime() - second.getTime());

        const maxEndDate = maxEndDateCandidates[0] ?? calendarEnd;

        if (!rule.repeats) {
            if (
                startDate >= calendarStart
                && startDate <= maxEndDate
                && !excludedDates.includes(rule.date)
                && !isBlockedDate(rule.date)
                && isRuleOccurrenceInsideOpeningHours(rule.date, rule)
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
        const maxOccurrences = Math.max(0, Number(rule?.recurrence?.ends?.count ?? 0));
        let producedOccurrences = 0;

        while (occurrenceDate <= maxEndDate) {
            const occurrenceDateString = getDateFromDate(occurrenceDate);

            if (
                !excludedDates.includes(occurrenceDateString)
                && !isBlockedDate(occurrenceDateString)
                && isRuleOccurrenceInsideOpeningHours(occurrenceDateString, rule)
            ) {
                producedOccurrences += 1;

                if (maxOccurrences > 0 && producedOccurrences > maxOccurrences) {
                    break;
                }

                if (occurrenceDate >= calendarStart) {
                    occurrences.push(occurrenceDateString);
                }
            }

            if (maxOccurrences > 0 && producedOccurrences >= maxOccurrences) {
                break;
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
            recurrence: rule.repeats ? (rule.recurrence ?? null) : null,
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
        repeat_weekdays: [...(rule.repeat_weekdays ?? [])],
        recurrence: rule.recurrence ? JSON.parse(JSON.stringify(rule.recurrence)) : null,
        excluded_dates: [...(rule.excluded_dates ?? [])],
        occurrence_overrides: (rule.occurrence_overrides ?? []).map((override) => ({ ...override })),
    });

    const normalizeDateOnly = (value) => {
        const parsed = new Date(`${String(value ?? '').slice(0, 10)}T00:00:00`);

        if (Number.isNaN(parsed.getTime())) {
            return null;
        }

        return getDateFromDate(parsed);
    };

    const subtractOneDay = (dateString) => {
        const normalized = normalizeDateOnly(dateString);

        if (!normalized) {
            return null;
        }

        const date = new Date(`${normalized}T00:00:00`);

        date.setDate(date.getDate() - 1);

        return getDateFromDate(date);
    };

    const uniqueDateList = (values = []) => {
        return [...new Set(values.filter(Boolean))].sort();
    };

    const buildScopedRuleUpdate = ({
        scope,
        rule,
        ruleIndex,
        occurrenceDate,
        originalRule,
    }) => {
        const normalizedOccurrenceDate = normalizeDateOnly(occurrenceDate);

        if (!normalizedOccurrenceDate || !rule || ruleIndex === null || ruleIndex < 0) {
            return null;
        }

        if (scope === 'series' || !originalRule?.repeats) {
            return [...ruleForm.rules];
        }

        if (scope === 'occurrence') {
            const nextRules = [...ruleForm.rules];
            const restoredSeriesRule = cloneRule(originalRule);

            restoredSeriesRule.excluded_dates = uniqueDateList([
                ...(restoredSeriesRule.excluded_dates ?? []),
                normalizedOccurrenceDate,
            ]);

            nextRules[ruleIndex] = restoredSeriesRule;
            nextRules.push({
                ...cloneRule(rule),
                id: null,
                date: normalizedOccurrenceDate,
                repeats: false,
                repeat_every: 1,
                repeat_unit: 'weeks',
                repeat_weekdays: [],
                repeat_ends_on: null,
                excluded_dates: [],
            });

            return nextRules;
        }

        if (scope === 'from_date') {
            const normalizedOriginalDate = normalizeDateOnly(originalRule?.date ?? rule.date);

            if (!normalizedOriginalDate || normalizedOccurrenceDate <= normalizedOriginalDate) {
                return [...ruleForm.rules];
            }

            const previousSeriesRule = cloneRule(originalRule);
            const currentSeriesUuid = rule.series_uuid ?? originalRule?.series_uuid ?? null;

            previousSeriesRule.repeat_ends_on = subtractOneDay(normalizedOccurrenceDate);
            previousSeriesRule.excluded_dates = (previousSeriesRule.excluded_dates ?? [])
                .filter((date) => String(date).slice(0, 10) < normalizedOccurrenceDate);

            const followingSeriesRule = {
                ...cloneRule(rule),
                id: null,
                series_uuid: currentSeriesUuid,
                excluded_dates: (rule.excluded_dates ?? [])
                    .filter((date) => String(date).slice(0, 10) >= normalizedOccurrenceDate),
            };

            const nextRules = ruleForm.rules.filter((entry, entryIndex) => {
                if (entryIndex === ruleIndex) {
                    return false;
                }

                if (!currentSeriesUuid || entry.series_uuid !== currentSeriesUuid) {
                    return true;
                }

                return normalizeDateOnly(entry.date) < normalizedOccurrenceDate;
            });

            nextRules.push(previousSeriesRule, followingSeriesRule);

            return nextRules;
        }

        return [...ruleForm.rules];
    };

    const restorePendingRuleReschedule = () => {
        pendingRuleReschedule.value = null;

        if (selectedRuleOccurrence.value?.originalRule && currentRule.value) {
            Object.assign(currentRule.value, cloneRule(selectedRuleOccurrence.value.originalRule));
        }

        ruleRescheduleScopeDialogVisible.value = false;
        clearRuleRescheduleImpactPreview();
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
        const normalizedOptions = typeof options === 'string'
            ? { reschedule_scope: options }
            : (options ?? {});

        const rescheduleScope = normalizedOptions.reschedule_scope ?? null;
        const updateScope = normalizedOptions.update_scope ?? null;

        const rule = currentRule.value;
        const occurrenceDate = normalizedOptions.occurrence_date ?? getSelectedOccurrenceDate();

        if (rescheduleScope && pendingRuleReschedule.value?.ruleId) {
            const pending = pendingRuleReschedule.value;
            pendingRuleScopeSubmit.value = true;

            router.post(route('branches.booking.rules.reschedule', [
                props.branch.id,
                pending.ruleId,
            ]), {
                occurrence_date: pending.occurrenceDate,
                date: pending.nextRule.date,
                starts_at: pending.nextRule.starts_at,
                ends_at: pending.nextRule.ends_at,
                reschedule_scope: rescheduleScope,
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    pendingRuleReschedule.value = null;
                    ruleRescheduleScopeDialogVisible.value = false;
                    feedback.success(scopeSuccessMessage({ action: 'reschedule', scope: rescheduleScope }));
                    reloadRuleStateSoon();
                },
                onError: (errors) => {
                    pendingRuleReschedule.value = null;
                    ruleRescheduleScopeDialogVisible.value = false;
                    feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo presunúť.');
                },
                onFinish: () => {
                    pendingRuleScopeSubmit.value = false;
                },
            });

            return;
        }

        if (updateScope && rule?.id && occurrenceDate) {
            const previousRules = ruleForm.rules.map((entry) => cloneRule(entry));
            const nextRules = buildScopedRuleUpdate({
                scope: updateScope,
                rule,
                ruleIndex: selectedRuleIndex.value,
                occurrenceDate,
                originalRule: cloneRule(normalizedOptions.original_rule ?? selectedRuleOccurrence.value?.originalRule ?? rule),
            });

            if (!nextRules) {
                return;
            }

            ruleForm.rules = nextRules;

            submitRules({
                onSuccess: () => {
                    closeRuleAfterSave();
                    feedback.success(scopeSuccessMessage({ action: 'update', scope: updateScope }));
                    reloadRuleStateSoon();
                },
                onError: (errors) => {
                    ruleForm.rules = previousRules;
                    feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo uložiť.');
                },
            });

            return;
        }

        if (rescheduleScope && rule?.id && occurrenceDate) {
            pendingRuleScopeSubmit.value = true;

            router.post(route('branches.booking.rules.reschedule', [
                props.branch.id,
                rule.id,
            ]), {
                occurrence_date: occurrenceDate,
                date: rule.date,
                starts_at: rule.starts_at,
                ends_at: rule.ends_at,
                reschedule_scope: rescheduleScope,
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    closeRuleAfterSave();
                    feedback.success(scopeSuccessMessage({ action: 'reschedule', scope: rescheduleScope }));
                    reloadRuleStateSoon();
                },
                onError: (errors) => {
                    restorePendingRuleReschedule();
                    feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo presunúť.');
                },
                onFinish: () => {
                    pendingRuleScopeSubmit.value = false;
                },
            });

            return;
        }

        submitRules({
            onSuccess: () => {
                closeRuleAfterSave();
                feedback.success('Dostupnosť bola upravená.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo uložiť.');
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

            const occurrenceDate = changeInfo.event.extendedProps.occurrenceOriginalDate
                ?? changeInfo.event.extendedProps.occurrenceDate
                ?? previousRule.date;

            selectedRuleIndex.value = index;
            selectedRuleOccurrence.value = {
                ruleIndex: index,
                occurrenceDate,
                occurrenceOriginalDate: occurrenceDate,
                isRepeatedOccurrence: true,
                originalRule: cloneRule(previousRule),
            };

            pendingRuleReschedule.value = {
                ruleId: rule.id,
                occurrenceDate,
                nextRule,
            };

            fetchRuleRescheduleImpactPreview({
                action: 'reschedule',
                selectedOccurrence: {
                    event_id: rule.id,
                    root_event_id: rule.root_event_id ?? null,
                    occurrence_starts_at: `${occurrenceDate}T${String(previousRule.starts_at ?? '00:00').slice(0, 5)}:00`,
                    occurrence_original_starts_at: `${occurrenceDate}T${String(previousRule.starts_at ?? '00:00').slice(0, 5)}:00`,
                    starts_at: `${occurrenceDate}T${String(previousRule.starts_at ?? '00:00').slice(0, 5)}:00`,
                    ends_at: `${occurrenceDate}T${String(previousRule.ends_at ?? '00:00').slice(0, 5)}:00`,
                    display_key: null,
                },
                changes: {
                    starts_at: `${nextRule.date}T${String(nextRule.starts_at ?? '00:00').slice(0, 5)}:00`,
                    ends_at: `${nextRule.date}T${String(nextRule.ends_at ?? '00:00').slice(0, 5)}:00`,
                },
            }).catch(() => {
                clearRuleRescheduleImpactPreview();
            });

            ruleRescheduleScopeDialogVisible.value = true;

            return;
        }

        Object.assign(rule, nextRule);

        submitRules({
            onError: (errors) => {
                Object.assign(rule, previousRule);
                changeInfo.revert();
                feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo presunúť.');
            },
            onSuccess: () => {
                feedback.success('Pravidlo rezervácií bolo presunuté.');
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
        const eventIds = getSelectedRuleEventIds();

        if (!rule || !occurrenceDate) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        eventIds.forEach((id) => hideCalendarEventId?.(id));

        router.post(route('branches.booking.rules.exclude-date', [props.branch.id, rule.id]), {
            date: occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                feedback.success(scopeSuccessMessage({ action: 'delete', scope: 'occurrence' }));
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                eventIds.forEach((id) => restoreCalendarEventId?.(id));
                feedback.error(errors, 'Výskyt voľného času sa nepodarilo vymazať.');
            },
        });
    };

    const deleteCurrentRuleFromNowOn = () => {
        const rule = getSelectedRule();
        const occurrenceDate = getSelectedOccurrenceDate();
        const eventIds = getSelectedRuleEventIds();

        if (!rule || !occurrenceDate) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        eventIds.forEach((id) => hideCalendarEventId?.(id));

        router.post(route('branches.booking.rules.end-before-date', [props.branch.id, rule.id]), {
            date: occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                feedback.success(scopeSuccessMessage({ action: 'delete', scope: 'from_date' }));
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                eventIds.forEach((id) => restoreCalendarEventId?.(id));
                feedback.error(errors, 'Výskyty voľného času sa nepodarilo vymazať.');
            },
        });
    };

    const deleteCurrentRuleEverywhere = () => {
        const rule = getSelectedRule();
        const eventIds = getSelectedRuleEventIds();

        if (!rule) {
            return;
        }

        if (!rule.id) {
            deleteCurrentRule();

            return;
        }

        eventIds.forEach((id) => hideCalendarEventId?.(id));

        router.delete(route('branches.booking.rules.destroy', [props.branch.id, rule.id]), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeRuleDeletes();
                feedback.success(scopeSuccessMessage({ action: 'delete', scope: 'series' }));
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                eventIds.forEach((id) => restoreCalendarEventId?.(id));
                feedback.error(errors, 'Pravidlo rezervácií sa nepodarilo vymazať.');
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

    const cancelPendingRuleReschedule = () => {
        if (pendingRuleScopeSubmit.value) {
            return;
        }

        pendingRuleReschedule.value = null;
        ruleRescheduleScopeDialogVisible.value = false;
        clearRuleRescheduleImpactPreview();
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
        cancelPendingRuleReschedule,
        ruleRescheduleImpactPreview,
        pendingRuleScopeSubmit,
        saveRules,
        updateRuleFromDrop,
    };
}