import { router, useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { computed } from 'vue';

export function useBookingRules({ props, dateTime, dialogs, isDateRangeInsideOpeningHours }) {
    const toast = useToast();

    const showSuccess = (message) => {
        toast.add({
            severity: 'success',
            summary: 'Hotovo',
            detail: message,
            life: 3500,
        });
    };

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
        router.reload({
            preserveScroll: true,
            preserveState: false,
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

        service_ids: [],

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

            service_ids: (rule.services ?? []).map((service) => service.id),

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

    const restorePendingRuleReschedule = () => {
        if (selectedRuleOccurrence.value?.originalRule && currentRule.value) {
            Object.assign(currentRule.value, {
                ...selectedRuleOccurrence.value.originalRule,
                service_ids: [...(selectedRuleOccurrence.value.originalRule.service_ids ?? [])],
                excluded_dates: [...(selectedRuleOccurrence.value.originalRule.excluded_dates ?? [])],
            });
        }
    };

    const closeRuleDialogSafely = () => {
        restorePendingRuleReschedule();
        closeRuleDialog();
        pendingCalendarSelection.value = null;
    };

    const closeRuleAfterSave = () => {
        closeRuleDialog();
        pendingCalendarSelection.value = null;
    };

    const getRuleRescheduleTargetDate = (rule, scope) => {
        if (scope === 'series') {
            return rule.date;
        }

        const occurrence = selectedRuleOccurrence.value;

        if (!occurrence?.occurrenceDate) {
            return rule.date;
        }

        if (!occurrence.originalRule) {
            return rule.date === occurrence.occurrenceDate
                ? rule.date
                : occurrence.occurrenceDate;
        }

        return rule.date === occurrence.originalRule.date
            ? occurrence.occurrenceDate
            : rule.date;
    };

    const saveRules = (options = {}) => {
        const scope = typeof options === 'string'
            ? options
            : options.reschedule_scope ?? null;

        const rule = currentRule.value;

        if (scope && rule?.id && selectedRuleOccurrence.value?.occurrenceDate) {
            router.post(route('branches.booking.rules.reschedule', [
                props.branch.id,
                rule.id,
            ]), {
                occurrence_date: selectedRuleOccurrence.value.occurrenceDate,
                date: getRuleRescheduleTargetDate(rule, scope),
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

        const previousRule = {
            ...rule,
            service_ids: [...(rule.service_ids ?? [])],
            excluded_dates: [...(rule.excluded_dates ?? [])],
        };

        const nextRule = {
            date: getDateFromDate(changeInfo.event.start),
            starts_at: getTimeFromDate(changeInfo.event.start),
            ends_at: getTimeFromDate(changeInfo.event.end),
        };

        if (rule.repeats) {
            changeInfo.revert();

            Object.assign(rule, nextRule);

            selectedRuleIndex.value = index;
            selectedRuleOccurrence.value = {
                ruleIndex: index,
                occurrenceDate: changeInfo.event.extendedProps.occurrenceDate,
                isRepeatedOccurrence: true,
                originalRule: previousRule,
            };

            availabilityRuleDialogVisible.value = true;

            toast.add({
                severity: 'info',
                summary: 'Opakovaná dostupnosť',
                detail: 'Vyberte, či chcete presunúť iba tento výskyt, nasledujúce výskyty alebo celú sériu.',
                life: 4500,
            });

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
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Výskyt voľného času bol vymazaný.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                showError('Výskyt voľného času sa nepodarilo vymazať.', errors);
            },
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
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Opakovanie voľného času bolo ukončené.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                showError('Opakovanie voľného času sa nepodarilo ukončiť.', errors);
            },
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
            onSuccess: () => {
                closeRuleDeletes();
                showSuccess('Voľný čas bol vymazaný.');
                reloadRuleStateSoon();
            },
            onError: (errors) => {
                showError('Voľný čas sa nepodarilo vymazať.', errors);
            },
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

        closeRuleDialogSafely,
        deleteCurrentRule,
        deleteCurrentRuleEverywhere,
        deleteCurrentRuleFromNowOn,
        deleteCurrentRuleOccurrence,
        saveRules,
        updateRuleFromDrop,
    };
}
