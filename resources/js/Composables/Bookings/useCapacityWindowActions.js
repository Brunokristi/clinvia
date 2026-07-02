import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

export function useCapacityWindowActions({
    props,
    dateTime,
    dialogs,
    hideCalendarEventId,
    restoreCalendarEventId,
    reloadCalendarData,
}) {
    const toast = useToast();
    const { toLocalDateTimeString } = dateTime;

    const {
        groupEventDialogVisible,
        groupEventOccurrenceDialogVisible,
        capacityWindowRescheduleScopeDialogVisible,
        selectedCapacityWindow,
        selectedGroupEvent,
        openCreateBookingWithPrefill,
        suppressNextEventClick,
        suppressEventClicksFor,
    } = dialogs;

    const pendingCapacityWindowReschedule = ref(null);

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

    const reloadCalendarDataInternal = reloadCalendarData ?? (() => {
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
    });

    const closeCapacityWindowDialog = () => {
        groupEventOccurrenceDialogVisible.value = false;
        selectedCapacityWindow.value = null;
    };

    const closeGroupEventDialog = () => {
        groupEventDialogVisible.value = false;
        selectedGroupEvent.value = null;
    };

    const getCapacityWindowId = (capacityWindow) => {
        return capacityWindow?.capacity_window_id
            ?? capacityWindow?.window_id
            ?? capacityWindow?.id
            ?? null;
    };

    const isCapacityWindowRepeatable = (capacityWindow) => {
        return Boolean(
            capacityWindow?.series_uuid
            || capacityWindow?.repeats
            || capacityWindow?.is_recurring,
        );
    };

    const getExistingStart = (capacityWindow) => {
        return capacityWindow?.starts_datetime
            ?? capacityWindow?.start
            ?? capacityWindow?.starts_at
            ?? null;
    };

    const getExistingEnd = (capacityWindow) => {
        return capacityWindow?.ends_datetime
            ?? capacityWindow?.end
            ?? capacityWindow?.ends_at
            ?? null;
    };

    const getDateOnly = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            const year = value.getFullYear();
            const month = String(value.getMonth() + 1).padStart(2, '0');
            const day = String(value.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        return String(value).slice(0, 10);
    };

    const getTimeOnly = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            const hours = String(value.getHours()).padStart(2, '0');
            const minutes = String(value.getMinutes()).padStart(2, '0');

            return `${hours}:${minutes}`;
        }

        const stringValue = String(value);

        if (stringValue.includes('T') || stringValue.includes(' ')) {
            return stringValue.slice(11, 16);
        }

        return stringValue.slice(0, 5);
    };

    const toDateTimeString = (date, time) => {
        if (!date || !time) {
            return null;
        }

        const dateString = getDateOnly(date);
        const timeString = getTimeOnly(time);

        if (!dateString || !timeString) {
            return null;
        }

        return `${dateString} ${timeString}:00`;
    };

    const getSeriesWindows = (capacityWindow) => {
        if (!capacityWindow?.series_uuid) {
            return [];
        }

        return (props.calendarCapacityWindows ?? [])
            .filter((window) => window?.series_uuid === capacityWindow.series_uuid)
            .sort((first, second) => {
                const firstStart = new Date(getExistingStart(first) ?? 0).getTime();
                const secondStart = new Date(getExistingStart(second) ?? 0).getTime();

                return firstStart - secondStart;
            });
    };

    const inferRecurrenceFromSeries = (capacityWindow) => {
        const seriesWindows = getSeriesWindows(capacityWindow);

        if (seriesWindows.length < 2) {
            return null;
        }

        const firstStart = new Date(getExistingStart(seriesWindows[0]));
        const secondStart = new Date(getExistingStart(seriesWindows[1]));

        if (Number.isNaN(firstStart.getTime()) || Number.isNaN(secondStart.getTime())) {
            return null;
        }

        const diffDays = Math.max(1, Math.round((secondStart.getTime() - firstStart.getTime()) / (24 * 60 * 60 * 1000)));
        let frequency = 'weekly';
        let interval = 1;

        if (diffDays === 1) {
            frequency = 'daily';
            interval = 1;
        } else if (diffDays % 7 === 0) {
            frequency = 'weekly';
            interval = Math.max(1, diffDays / 7);
        } else {
            const monthDiff = (secondStart.getFullYear() - firstStart.getFullYear()) * 12
                + (secondStart.getMonth() - firstStart.getMonth());

            if (monthDiff >= 1 && firstStart.getDate() === secondStart.getDate()) {
                frequency = 'monthly';
                interval = monthDiff;
            }
        }

        const lastWindow = seriesWindows[seriesWindows.length - 1];
        const until = getDateOnly(lastWindow?.date ?? getExistingStart(lastWindow));

        return {
            frequency,
            interval,
            weekdays: [],
            ends: {
                type: until ? 'on' : 'never',
                count: null,
                until: until ?? null,
            },
        };
    };

    const normalizeCapacityWindowPayload = (groupEvent) => {
        const rawPatients = Array.isArray(groupEvent.group_patients)
            ? groupEvent.group_patients
            : (Array.isArray(groupEvent.patients) ? groupEvent.patients : []);

        return {
            service_id: groupEvent.service_id,
            starts_at: toDateTimeString(groupEvent.date, groupEvent.starts_at),
            ends_at: toDateTimeString(groupEvent.date, groupEvent.ends_at),
            capacity: groupEvent.capacity ?? groupEvent.bookable_places ?? 1,
            public_booking_type: groupEvent.public_booking_type ?? 'immediate_booking',
            repeats: Boolean(groupEvent.repeats),
            repeat_every: groupEvent.repeats ? groupEvent.repeat_every : 1,
            repeat_unit: groupEvent.repeats ? groupEvent.repeat_unit : 'weeks',
            repeat_ends_on: groupEvent.repeats ? getDateOnly(groupEvent.repeat_ends_on) : null,
            patients: rawPatients
                .map((patient) => ({
                    patient_name: String(patient?.patient_name ?? '').trim(),
                    patient_email: patient?.patient_email ?? null,
                    patient_phone: patient?.patient_phone ?? null,
                }))
                .filter((patient) => patient.patient_name.length > 0),
        };
    };

    const createCapacityWindow = (groupEvent) => {
        router.post(route('branches.booking.capacity-windows.store', {
            branch: props.branch.id,
        }), normalizeCapacityWindowPayload(groupEvent), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeGroupEventDialog();

                if (typeof suppressNextEventClick === 'function') {
                    suppressNextEventClick();
                }

                if (typeof suppressEventClicksFor === 'function') {
                    suppressEventClicksFor(2000);
                }

                showSuccess('Skupinový termín bol vytvorený.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                showError('Skupinový termín sa nepodarilo vytvoriť.', errors);
            },
        });
    };

    const duplicateCapacityWindow = (groupEvent) => {
        if (!groupEvent) {
            return;
        }

        const date = getDateOnly(groupEvent.date ?? getExistingStart(groupEvent));
        const startsAt = getExistingStart(groupEvent) ?? toDateTimeString(groupEvent.date, groupEvent.starts_at);
        const endsAt = getExistingEnd(groupEvent) ?? toDateTimeString(groupEvent.date, groupEvent.ends_at);

        openCreateBookingWithPrefill({
            create_type: 'group_event',
            date,
            starts_at: startsAt,
            ends_at: endsAt,
            service_ids: [groupEvent.service_id ?? groupEvent.service?.id].filter(Boolean),
            service_id: groupEvent.service_id ?? groupEvent.service?.id ?? null,
            capacity: groupEvent.capacity ?? groupEvent.bookable_places ?? 1,
            public_booking_type: groupEvent.service?.public_booking_type ?? 'immediate_booking',
            recurrence: groupEvent.repeats
                ? {
                    frequency: groupEvent.repeat_unit === 'months' ? 'monthly' : 'weekly',
                    interval: Number(groupEvent.repeat_every ?? 1),
                    weekdays: [],
                    ends: {
                        type: groupEvent.repeat_ends_on ? 'on' : 'never',
                        count: null,
                        until: groupEvent.repeat_ends_on ?? null,
                    },
                }
                : (inferRecurrenceFromSeries(groupEvent) ?? null),
        });

        closeCapacityWindowDialog();
        closeGroupEventDialog();
    };

    const updateCapacityWindow = (capacityWindow, groupEvent) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);

        if (!capacityWindowId) {
            console.error('Missing capacity window id for update', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        const nextStartsAt = toDateTimeString(groupEvent.date, groupEvent.starts_at);
        const nextEndsAt = toDateTimeString(groupEvent.date, groupEvent.ends_at);
        const previousStartsAt = String(groupEvent.original_starts_at ?? getExistingStart(groupEvent) ?? '').replace('T', ' ');
        const previousEndsAt = String(groupEvent.original_ends_at ?? getExistingEnd(groupEvent) ?? '').replace('T', ' ');

        const shouldReschedule = nextStartsAt
            && nextEndsAt
            && !groupEvent.repeats
            && (
                !previousStartsAt.startsWith(nextStartsAt.slice(0, 16))
                || !previousEndsAt.startsWith(nextEndsAt.slice(0, 16))
            );

        const finishUpdate = (message = 'Skupinový termín bol upravený.') => {
            closeGroupEventDialog();
            showSuccess(message);
            reloadCalendarDataInternal();
        };

        const rescheduleAfterUpdate = () => {
            router.post(route('branches.booking.capacity-windows.reschedule', {
                branch: props.branch.id,
                capacityWindow: capacityWindowId,
            }), {
                starts_at: nextStartsAt,
                ends_at: nextEndsAt,
                reschedule_scope: groupEvent.update_scope ?? 'occurrence',
                from_date: groupEvent.date ?? getDateOnly(getExistingStart(groupEvent)),
                notify_patient: true,
            }, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    finishUpdate('Skupinový termín bol upravený a presunutý.');
                },
                onError: (errors) => {
                    showError('Skupinový termín sa nepodarilo presunúť.', errors);
                },
            });
        };

        router.put(route('branches.booking.capacity-windows.update', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            service_id: groupEvent.service_id,
            capacity: groupEvent.capacity ?? groupEvent.bookable_places ?? 1,
            public_booking_type: groupEvent.public_booking_type ?? 'immediate_booking',
            update_scope: groupEvent.update_scope ?? 'occurrence',
            from_date: groupEvent.date ?? getDateOnly(getExistingStart(groupEvent)),
            starts_at: nextStartsAt,
            ends_at: nextEndsAt,
            recurrence: groupEvent.repeats
                ? {
                    frequency: groupEvent.repeat_unit === 'days'
                        ? 'daily'
                        : (groupEvent.repeat_unit === 'months' ? 'monthly' : 'weekly'),
                    interval: Number(groupEvent.repeat_every ?? 1),
                    weekdays: [],
                    ends: {
                        type: groupEvent.repeat_ends_on ? 'on' : 'never',
                        count: null,
                        until: groupEvent.repeat_ends_on ?? null,
                    },
                }
                : null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (shouldReschedule) {
                    rescheduleAfterUpdate();

                    return;
                }

                finishUpdate();
            },
            onError: (errors) => {
                showError('Skupinový termín sa nepodarilo upraviť.', errors);
            },
        });
    };

    const saveCapacityWindow = (groupEvent) => {
        if (!groupEvent) {
            return;
        }

        if (getCapacityWindowId(groupEvent)) {
            updateCapacityWindow(groupEvent, groupEvent);

            return;
        }

        createCapacityWindow(groupEvent);
    };

    const openCapacityWindowEditor = (capacityWindow) => {
        if (!capacityWindow) {
            return;
        }

        const date = getDateOnly(capacityWindow.date ?? getExistingStart(capacityWindow));
        const startsAt = getExistingStart(capacityWindow);
        const endsAt = getExistingEnd(capacityWindow);

        openCreateBookingWithPrefill({
            create_type: 'group_event',
            edit_mode: true,
            target_type: 'group_event',
            target_id: getCapacityWindowId(capacityWindow),
            date,
            starts_at: startsAt,
            ends_at: endsAt,
            original_starts_at: startsAt,
            original_ends_at: endsAt,
            service_ids: [capacityWindow.service_id ?? capacityWindow.service?.id].filter(Boolean),
            service_id: capacityWindow.service_id ?? capacityWindow.service?.id ?? null,
            capacity: capacityWindow.capacity ?? capacityWindow.bookable_places ?? 1,
            public_booking_type: capacityWindow.service?.public_booking_type ?? 'immediate_booking',
            recurrence: inferRecurrenceFromSeries(capacityWindow),
        });

        groupEventOccurrenceDialogVisible.value = false;
        selectedCapacityWindow.value = null;
        groupEventDialogVisible.value = false;
    };

    const cancelCapacityWindow = (capacityWindow, options = {}) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);

        if (!capacityWindowId) {
            console.error('Missing capacity window id for cancel', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        router.post(route('branches.booking.capacity-windows.cancel', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeCapacityWindowDialog();
                showSuccess('Skupinový termín bol zrušený.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                showError('Skupinový termín sa nepodarilo zrušiť.', errors);
            },
        });
    };

    const rescheduleCapacityWindow = (capacityWindow, data = {}) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);

        if (!capacityWindowId) {
            console.error('Missing capacity window id for reschedule', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        router.post(route('branches.booking.capacity-windows.reschedule', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            starts_at: data.starts_at,
            ends_at: data.ends_at,
            reschedule_scope: data.reschedule_scope ?? 'occurrence',
            from_date: data.date ?? capacityWindow.date ?? getDateOnly(getExistingStart(capacityWindow)),
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeCapacityWindowDialog();
                showSuccess('Skupinový termín bol presunutý.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                showError('Skupinový termín sa nepodarilo presunúť.', errors);
            },
        });
    };

    const rescheduleCapacityWindowByCalendarChange = (changeInfo) => {
        const capacityWindow = changeInfo.event.extendedProps.capacityWindow;

        if (!capacityWindow) {
            changeInfo.revert();

            return;
        }

        const capacityWindowId = getCapacityWindowId(capacityWindow);

        if (!capacityWindowId) {
            console.error('Missing capacity window id for calendar reschedule', capacityWindow);
            showError('Chýba ID skupinového termínu.');
            changeInfo.revert();

            return;
        }

        const pendingReschedule = {
            date: getDateOnly(changeInfo.event.start),
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: toLocalDateTimeString(changeInfo.event.end),
            notify_patient: true,
        };

        if (isCapacityWindowRepeatable(capacityWindow)) {
            changeInfo.revert();

            pendingCapacityWindowReschedule.value = {
                capacityWindow,
                payload: pendingReschedule,
            };

            capacityWindowRescheduleScopeDialogVisible.value = true;

            return;
        }

        router.post(route('branches.booking.capacity-windows.reschedule', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            starts_at: pendingReschedule.starts_at,
            ends_at: pendingReschedule.ends_at,
            reschedule_scope: 'occurrence',
            from_date: capacityWindow.date ?? getDateOnly(getExistingStart(capacityWindow)),
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showSuccess('Skupinový termín bol presunutý.');
                reloadCalendarData();
            },
            onError: (errors) => {
                changeInfo.revert();
                showError('Skupinový termín sa nepodarilo presunúť.', errors);
            },
        });
    };

    const submitPendingCapacityWindowRescheduleScope = (scope) => {
        if (!pendingCapacityWindowReschedule.value) {
            return;
        }

        const { capacityWindow, payload } = pendingCapacityWindowReschedule.value;

        rescheduleCapacityWindow(capacityWindow, {
            ...payload,
            reschedule_scope: scope,
        });

        pendingCapacityWindowReschedule.value = null;
        capacityWindowRescheduleScopeDialogVisible.value = false;
    };

    const cancelPendingCapacityWindowReschedule = () => {
        pendingCapacityWindowReschedule.value = null;
        capacityWindowRescheduleScopeDialogVisible.value = false;
    };

    const deleteCapacityWindowOccurrence = (capacityWindow, options = {}) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);
        const eventId = `capacity-window-${capacityWindowId}`;

        if (!capacityWindowId) {
            console.error('Missing capacity window id for occurrence delete', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        hideCalendarEventId?.(eventId);

        router.delete(route('branches.booking.capacity-windows.destroy', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            data: {
                delete_scope: 'occurrence',
                notify_patient: true,
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeCapacityWindowDialog();
                showSuccess('Tento skupinový termín bol vymazaný.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Tento skupinový termín sa nepodarilo vymazať.', errors);
            },
        });
    };

    const deleteCapacityWindowFromDate = (capacityWindow, options = {}) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);
        const eventId = `capacity-window-${capacityWindowId}`;

        if (!capacityWindowId) {
            console.error('Missing capacity window id for future delete', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        hideCalendarEventId?.(eventId);

        router.delete(route('branches.booking.capacity-windows.destroy-series', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            data: {
                delete_scope: 'from_date',
                from_date: options.date ?? capacityWindow.date ?? null,
                notify_patient: true,
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeCapacityWindowDialog();
                showSuccess('Skupinové termíny od vybraného dátumu boli vymazané.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Skupinové termíny od vybraného dátumu sa nepodarilo vymazať.', errors);
            },
        });
    };

    const deleteCapacityWindowSeries = (capacityWindow, options = {}) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);
        const eventId = `capacity-window-${capacityWindowId}`;

        if (!capacityWindowId) {
            console.error('Missing capacity window id for series delete', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        hideCalendarEventId?.(eventId);

        router.delete(route('branches.booking.capacity-windows.destroy-series', {
            branch: props.branch.id,
            capacityWindow: capacityWindowId,
        }), {
            data: {
                delete_scope: 'series',
                notify_patient: true,
            },
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeCapacityWindowDialog();
                showSuccess('Celá séria skupinových termínov bola vymazaná.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Celú sériu skupinových termínov sa nepodarilo vymazať.', errors);
            },
        });
    };

    const findUpdatedCapacityWindow = (capacityWindow) => {
        const capacityWindows = props.calendarCapacityWindows ?? [];

        return capacityWindows.find((window) => {
            return Number(getCapacityWindowId(window)) === Number(getCapacityWindowId(capacityWindow));
        }) ?? null;
    };

    const refreshSelectedCapacityWindow = (capacityWindow) => {
        const updatedCapacityWindow = findUpdatedCapacityWindow(capacityWindow);

        if (!updatedCapacityWindow) {
            return;
        }

        selectedCapacityWindow.value = updatedCapacityWindow;
    };

    const addPatientToCapacityWindow = (capacityWindow, payload) => {
        const capacityWindowId = getCapacityWindowId(capacityWindow);

        if (!capacityWindowId) {
            console.error('Missing capacity window id for capacity window booking store', capacityWindow);
            showError('Chýba ID skupinového termínu.');

            return;
        }

        router.post(
            route('branches.booking.capacity-windows.bookings.store', {
                branch: props.branch.id,
                capacityWindow: capacityWindowId,
            }),
            {
                patient_name: payload.patient_name,
                patient_email: payload.patient_email,
                patient_phone: payload.patient_phone,
                notify_patient: true,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: [
                    'calendarCapacityWindows',
                    'calendarBookings',
                    'todayBookingsCount',
                ],
                onSuccess: () => {
                    refreshSelectedCapacityWindow(capacityWindow);
                    showSuccess('Pacient bol pridaný do skupinového termínu.');
                    reloadCalendarDataInternal();
                },
                onError: (errors) => {
                    showError('Pacienta sa nepodarilo pridať do skupinového termínu.', errors);
                },
            },
        );
    };

    return {
        cancelCapacityWindow,
        createCapacityWindow,
        duplicateCapacityWindow,
        deleteCapacityWindowFromDate,
        deleteCapacityWindowOccurrence,
        deleteCapacityWindowSeries,
        rescheduleCapacityWindow,
        rescheduleCapacityWindowByCalendarChange,
        submitPendingCapacityWindowRescheduleScope,
        cancelPendingCapacityWindowReschedule,
        addPatientToCapacityWindow,
        openCapacityWindowEditor,
        saveCapacityWindow,
    };
}