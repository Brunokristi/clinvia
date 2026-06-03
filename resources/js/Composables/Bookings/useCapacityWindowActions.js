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
    };
}
