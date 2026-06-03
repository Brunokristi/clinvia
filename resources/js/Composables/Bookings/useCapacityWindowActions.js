import { router } from '@inertiajs/vue3';

export function useCapacityWindowActions({ props, dateTime, dialogs }) {
    const { toLocalDateTimeString } = dateTime;
    const {
        groupEventOccurrenceDialogVisible,
        selectedCapacityWindow,
    } = dialogs;

    const closeCapacityWindowDialog = () => {
        groupEventOccurrenceDialogVisible.value = false;
        selectedCapacityWindow.value = null;
    };

    const cancelCapacityWindow = (capacityWindow, options = {}) => {
        router.post(route('branches.booking.capacity-windows.cancel', [props.branch.id, capacityWindow.rule_id]), {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: closeCapacityWindowDialog,
        });
    };

    const rescheduleCapacityWindow = (capacityWindow, data = {}) => {
        router.post(route('branches.booking.capacity-windows.reschedule', [props.branch.id, capacityWindow.rule_id]), {
            date: data.date ?? capacityWindow.date,
            starts_at: data.starts_at,
            ends_at: data.ends_at,
            notify_patient: Boolean(data.notify_patient ?? true),
            notification_reason: data.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
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
            preserveState: false,
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
            preserveState: false,
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
            preserveState: false,
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
            preserveState: false,
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
    };
}
