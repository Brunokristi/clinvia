import { router } from '@inertiajs/vue3';

export function useCapacityWindowActions({
    props,
    dateTime,
    dialogs,
    ruleForm,
}) {
    const { toLocalDateTimeString } = dateTime;
    const {
        groupEventDialogVisible,
        groupEventOccurrenceDialogVisible,
        selectedCapacityWindow,
        selectedRuleOccurrence,
        selectedRuleIndex,
    } = dialogs;

    const closeCapacityWindowDialog = () => {
        groupEventOccurrenceDialogVisible.value = false;
        selectedCapacityWindow.value = null;
    };

    const getDateOnly = (value) => {
        if (!value) {
            return null;
        }

        return String(value).slice(0, 10);
    };

    const getTimeOnly = (value) => {
        if (!value) {
            return null;
        }

        const stringValue = String(value);

        if (stringValue.includes('T') || stringValue.includes(' ')) {
            return stringValue.slice(11, 16);
        }

        return stringValue.slice(0, 5);
    };

    const openCapacityWindowRuleEditor = (capacityWindow) => {
        if (!capacityWindow || !ruleForm?.rules) {
            return;
        }

        const ruleId = capacityWindow.rule_id ?? capacityWindow.id;
        const occurrenceDate = getDateOnly(capacityWindow.date ?? capacityWindow.starts_at);

        const editableRule = {
            id: ruleId,
            rule_id: ruleId,
            type: 'capacity',
            slot_mode: 'single_service_many_clients',

            service_id: capacityWindow.service_id ?? capacityWindow.service?.id ?? null,
            bookable_places: capacityWindow.bookable_places ?? capacityWindow.capacity ?? 1,

            date: occurrenceDate,

            starts_at: getTimeOnly(capacityWindow.starts_at),
            ends_at: getTimeOnly(capacityWindow.ends_at),

            is_enabled: capacityWindow.is_enabled ?? true,

            repeats: capacityWindow.repeats ?? capacityWindow.is_recurring ?? false,
            repeat_every: capacityWindow.repeat_every ?? 1,
            repeat_unit: capacityWindow.repeat_unit ?? 'week',
            repeat_until: capacityWindow.repeat_until ?? null,
        };

        const existingRuleIndex = ruleForm.rules.findIndex((rule) => {
            return Number(rule.id ?? rule.rule_id) === Number(ruleId);
        });

        if (existingRuleIndex >= 0) {
            ruleForm.rules[existingRuleIndex] = {
                ...ruleForm.rules[existingRuleIndex],
                ...editableRule,
            };

            selectedRuleIndex.value = existingRuleIndex;
        } else {
            ruleForm.rules.push(editableRule);
            selectedRuleIndex.value = ruleForm.rules.length - 1;
        }

        selectedRuleOccurrence.value = {
            ruleIndex: selectedRuleIndex.value,
            occurrenceDate,
            isRepeatedOccurrence: Boolean(capacityWindow.repeats ?? capacityWindow.is_recurring),
        };

        groupEventOccurrenceDialogVisible.value = false;
        selectedCapacityWindow.value = null;
        groupEventDialogVisible.value = true;
    };

    const cancelCapacityWindow = (capacityWindow, options = {}) => {
        router.post(route('branches.booking.capacity-windows.cancel', [props.branch.id, capacityWindow.rule_id]), {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    const rescheduleCapacityWindow = (capacityWindow, data = {}) => {
        router.post(route('branches.booking.capacity-windows.reschedule', [props.branch.id, capacityWindow.rule_id]), {
            date: capacityWindow.date,
            starts_at: data.starts_at,
            ends_at: data.ends_at,
            notify_patient: Boolean(data.notify_patient ?? true),
            notification_reason: data.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    const rescheduleCapacityWindowByCalendarChange = (changeInfo) => {
        const capacityWindow = changeInfo.event.extendedProps.capacityWindow;

        if (!capacityWindow) {
            changeInfo.revert();

            return;
        }

        router.post(route('branches.booking.capacity-windows.reschedule', [props.branch.id, capacityWindow.rule_id]), {
            date: capacityWindow.date,
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: toLocalDateTimeString(changeInfo.event.end),
            notify_patient: true,
            notification_reason: 'Termín skupinovej rezervácie bol presunutý.',
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                changeInfo.revert();
            },
        });
    };

    const deleteCapacityWindowOccurrence = (capacityWindow, options = {}) => {
        router.post(route('branches.booking.capacity-windows.delete-occurrence', [props.branch.id, capacityWindow.rule_id]), {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    const findUpdatedCapacityWindow = (capacityWindow) => {
        const capacityWindows = props.calendarCapacityWindows ?? [];

        return capacityWindows.find((window) => {
            const sameId = Number(window.id) === Number(capacityWindow.id);
            const sameRuleId = Number(window.rule_id) === Number(capacityWindow.rule_id);
            const sameDate = String(window.date ?? window.starts_at).slice(0, 10)
                === String(capacityWindow.date ?? capacityWindow.starts_at).slice(0, 10);

            return sameId || (sameRuleId && sameDate);
        }) ?? null;
    };

    const refreshSelectedCapacityWindow = (capacityWindow) => {
        const updatedCapacityWindow = findUpdatedCapacityWindow(capacityWindow);

        if (!updatedCapacityWindow) {
            return;
        }

        dialogs.selectedCapacityWindow.value = updatedCapacityWindow;
    };

    const addPatientToCapacityWindow = (capacityWindow, payload) => {
        if (!capacityWindow?.rule_id && !capacityWindow?.id) {
            return;
        }

        router.post(
            route('branches.booking.capacity-windows.bookings.store', {
                branch: props.branch.id,
                rule: capacityWindow.rule_id ?? capacityWindow.id,
            }),
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                only: [
                    'calendarCapacityWindows',
                    'calendarBookings',
                ],
                onSuccess: () => {
                    refreshSelectedCapacityWindow(capacityWindow);
                },
            },
        );
    };

    const deleteCapacityWindowFromDate = (capacityWindow, options = {}) => {
        router.post(route('branches.booking.capacity-windows.delete-from-date', [props.branch.id, capacityWindow.rule_id]), {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    const deleteCapacityWindowSeries = (capacityWindow, options = {}) => {
        router.delete(route('branches.booking.capacity-windows.delete-series', [props.branch.id, capacityWindow.rule_id]), {
            data: {
                date: options.date ?? capacityWindow.date,
                notify_patient: Boolean(options.notify_patient ?? true),
                notification_reason: options.notification_reason ?? null,
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    return {
        cancelCapacityWindow,
        deleteCapacityWindowFromDate,
        deleteCapacityWindowOccurrence,
        deleteCapacityWindowSeries,
        rescheduleCapacityWindow,
        rescheduleCapacityWindowByCalendarChange,
        addPatientToCapacityWindow,
        openCapacityWindowRuleEditor,
    };
}