import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

import { isRecurringEntity } from './recurrencePolicy';

export function useBookingActions({ props, dateTime, dialogs, hideCalendarEventId, restoreCalendarEventId, reloadCalendarData }) {
    const toast = useToast();
    const { toLocalDateTimeString } = dateTime;

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

    const {
        bookingDialogVisible,
        bookingRescheduleScopeDialogVisible,
        createBookingDialogVisible,
        groupEventOccurrenceDialogVisible,
        pendingCalendarSelection,
        openCreateBookingWithPrefill,
        selectedBooking,
        suppressNextEventClick,
        suppressEventClicksFor,
    } = dialogs;

    const bookingNotes = ref({});
    const pendingBookingReschedule = ref(null);

    const reloadCalendarDataInternal = reloadCalendarData ?? (() => {
        router.reload({
            only: [
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

    const fillBookingNotes = (bookings = []) => {
        bookings.forEach((booking) => {
            if (bookingNotes.value[booking.id] === undefined) {
                bookingNotes.value[booking.id] = booking.admin_note ?? '';
            }
        });
    };

    fillBookingNotes(props.calendarBookings ?? []);

    (props.calendarCapacityWindows ?? []).forEach((capacityWindow) => {
        fillBookingNotes(capacityWindow.bookings ?? []);
    });

    const closeBookingDialogs = () => {
        bookingDialogVisible.value = false;
        groupEventOccurrenceDialogVisible.value = false;
        selectedBooking.value = null;
    };

    const getBookingRecordId = (booking) => {
        return booking?.booking_id ?? booking?.record_id ?? booking?.id ?? null;
    };

    const isBookingRepeatable = (booking) => {
        return isRecurringEntity(booking);
    };

    const availableSlotsForBooking = () => {
        return [];
    };

    const getSelectionStartsAt = () => {
        if (!pendingCalendarSelection.value?.start) {
            return null;
        }

        return toLocalDateTimeString(pendingCalendarSelection.value.start);
    };

    const getSelectionEndsAt = () => {
        if (!pendingCalendarSelection.value?.end) {
            return null;
        }

        return toLocalDateTimeString(pendingCalendarSelection.value.end);
    };

    const createAdminBooking = (data = {}) => {
        router.post(route('branches.booking.bookings.store', props.branch.id), {
            service_id: data.service_id ?? null,
            service_ids: data.service_ids ?? (data.service_id ? [data.service_id] : []),
            starts_at: data.starts_at ?? getSelectionStartsAt(),
            ends_at: data.ends_at ?? getSelectionEndsAt(),
            patient_name: data.patient_name,
            patient_email: data.patient_email,
            patient_phone: data.patient_phone,
            patient_birth_number: data.patient_birth_number,
            notify_patient: true,
            recurrence: data.recurrence ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                bookingDialogVisible.value = false;
                selectedBooking.value = null;

                if (typeof suppressNextEventClick === 'function') {
                    suppressNextEventClick();
                }

                if (typeof suppressEventClicksFor === 'function') {
                    suppressEventClicksFor(2000);
                }

                createBookingDialogVisible.value = false;
                pendingCalendarSelection.value = null;
                showSuccess('Rezervácia bola vytvorená.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo vytvoriť.', errors);
            },
        });
    };

    const updateBooking = (booking, status, options = {}) => {
        router.put(route('branches.booking.bookings.update', [
            props.branch.id,
            getBookingRecordId(booking),
        ]), {
            status,
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeBookingDialogs();
                showSuccess('Rezervácia bola upravená.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo upraviť.', errors);
            },
        });
    };

    const cancelBooking = (booking, options = {}) => {
        const eventId = booking?.calendar_event_id ?? `booking-${getBookingRecordId(booking)}`;
        const capacityWindowId = booking?.capacity_window_id ?? null;

        hideCalendarEventId?.(eventId);

        if (capacityWindowId) {
            router.delete(route('branches.booking.capacity-windows.bookings.destroy', {
                branch: props.branch.id,
                capacityWindow: capacityWindowId,
                booking: getBookingRecordId(booking),
            }), {
                data: {
                    notify_patient: true,
                    date: options.date ?? booking.occurrence_date ?? booking.starts_at ?? null,
                },
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    closeBookingDialogs();
                    showSuccess('Pacient bol odstránený zo skupinového termínu.');
                    reloadCalendarDataInternal();
                },
                onError: (errors) => {
                    restoreCalendarEventId?.(eventId);
                    showError('Pacienta sa nepodarilo odstrániť zo skupinového termínu.', errors);
                },
            });

            return;
        }

        router.post(route('branches.booking.bookings.cancel', [
            props.branch.id,
            getBookingRecordId(booking),
        ]), {
            notify_patient: true,
            delete_scope: options.delete_scope ?? null,
            date: options.date ?? booking.occurrence_date ?? booking.starts_at ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeBookingDialogs();
                showSuccess('Rezervácia bola zrušená.');
                reloadCalendarDataInternal();
            },
            onError: (errors) => {
                restoreCalendarEventId?.(eventId);
                showError('Rezerváciu sa nepodarilo zrušiť.', errors);
            },
        });
    };

    const getBookingServiceIds = (booking, data = {}) => {
        if (data.service_ids?.length) {
            return data.service_ids;
        }

        if (booking.service_ids?.length) {
            return booking.service_ids;
        }

        if (booking.services?.length) {
            return booking.services.map((service) => service.id);
        }

        if (data.service_id) {
            return [data.service_id];
        }

        if (booking.service_id) {
            return [booking.service_id];
        }

        return [];
    };

    const rescheduleBooking = (booking, data = {}, options = {}) => {
        const serviceIds = getBookingServiceIds(booking, data);
        const inferredRecurringScope = isBookingRepeatable(booking)
            ? (data.date || booking.occurrence_original_date || booking.occurrence_date ? 'occurrence' : 'series')
            : null;
        const requestedScope = data.reschedule_scope ?? inferredRecurringScope;
        const shouldSendRecurrence = requestedScope !== 'occurrence';

        router.post(route('branches.booking.bookings.reschedule', [
            props.branch.id,
            getBookingRecordId(booking),
        ]), {
            service_id: serviceIds[0] ?? data.service_id ?? booking.service_id,
            service_ids: serviceIds,
            starts_at: data.starts_at ?? null,
            ends_at: data.ends_at ?? null,
            patient_name: data.patient_name ?? booking.patient_name ?? null,
            patient_email: data.patient_email ?? booking.patient_email ?? null,
            patient_phone: data.patient_phone ?? booking.patient_phone ?? null,
            patient_birth_number: data.patient_birth_number ?? booking.patient_birth_number ?? null,
            ...(shouldSendRecurrence
                ? { recurrence: data.recurrence ?? booking.recurrence ?? null }
                : {}),
            notify_patient: true,
            reschedule_scope: requestedScope,
            date: data.date
                ?? booking.occurrence_original_date
                ?? booking.occurrence_date
                ?? booking.starts_at
                ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeBookingDialogs();
                showSuccess('Rezervácia bola presunutá.');
                reloadCalendarDataInternal();
                options.onSuccess?.({
                    booking,
                    data,
                    requestedScope,
                });
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo presunúť.', errors);
                options.onError?.(errors);
            },
        });
    };

    const duplicateBooking = (booking) => {
        const serviceIds = getBookingServiceIds(booking);

        openCreateBookingWithPrefill({
            create_type: 'booking',
            date: booking.occurrence_date ?? String(booking.starts_at ?? '').slice(0, 10),
            starts_at: booking.starts_at,
            ends_at: booking.ends_at,
            service_ids: serviceIds,
            service_id: serviceIds[0] ?? booking.service_id,
            patient_name: booking.patient_name ?? '',
            patient_email: booking.patient_email ?? '',
            patient_phone: booking.patient_phone ?? '',
            patient_birth_number: booking.patient_birth_number ?? '',
            public_booking_type: booking.service?.public_booking_type ?? 'immediate_booking',
            recurrence: booking.recurrence ?? null,
        });

        closeBookingDialogs();
    };

    const rescheduleBookingByCalendarChange = (changeInfo, options = {}) => {
        const booking = changeInfo.event.extendedProps.booking;

        if (!booking) {
            changeInfo.revert();

            return;
        }

        const serviceIds = getBookingServiceIds(booking);
        const previousStartsAt = toLocalDateTimeString(changeInfo.oldEvent?.start ?? booking.starts_datetime ?? booking.starts_at ?? null);
        const previousEndsAt = changeInfo.oldEvent?.end
            ? toLocalDateTimeString(changeInfo.oldEvent.end)
            : (booking.ends_datetime ?? booking.ends_at ?? null);

        const payload = {
            service_id: serviceIds[0] ?? booking.service_id,
            service_ids: serviceIds,
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: changeInfo.event.end
                ? toLocalDateTimeString(changeInfo.event.end)
                : booking.ends_datetime ?? booking.ends_at,
            date: booking.occurrence_original_date
                ?? booking.occurrence_date
                ?? String(booking.starts_at ?? '').slice(0, 10),
            notify_patient: true,
        };

        if (isBookingRepeatable(booking)) {
            changeInfo.revert();

            pendingBookingReschedule.value = {
                booking,
                payload,
            };

            bookingRescheduleScopeDialogVisible.value = true;

            return;
        }

        router.post(route('branches.booking.bookings.reschedule', [
            props.branch.id,
            getBookingRecordId(booking),
        ]), {
            service_id: payload.service_id,
            service_ids: payload.service_ids,
            starts_at: payload.starts_at,
            ends_at: payload.ends_at,
            notify_patient: true,
            reschedule_scope: 'occurrence',
            date: payload.date,
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                changeInfo.revert();
                showError('Rezerváciu sa nepodarilo presunúť.', errors);
                options.onError?.(errors);
            },
            onSuccess: () => {
                changeInfo.event.setExtendedProp('booking', {
                    ...booking,
                    starts_at: payload.starts_at,
                    starts_datetime: payload.starts_at,
                    ends_at: payload.ends_at,
                    ends_datetime: payload.ends_at,
                    occurrence_date: payload.date,
                });
                showSuccess('Rezervácia bola presunutá.');
                options.onSuccess?.({
                    booking,
                    payload,
                    previous: {
                        starts_at: previousStartsAt,
                        ends_at: previousEndsAt,
                        date: payload.date,
                    },
                });
            },
        });
    };

    const submitPendingBookingRescheduleScope = (scope) => {
        if (!pendingBookingReschedule.value) {
            return;
        }

        const { booking, payload } = pendingBookingReschedule.value;

        rescheduleBooking(booking, {
            ...payload,
            reschedule_scope: scope,
        });

        pendingBookingReschedule.value = null;
        bookingRescheduleScopeDialogVisible.value = false;
    };

    const cancelPendingBookingReschedule = () => {
        pendingBookingReschedule.value = null;
        bookingRescheduleScopeDialogVisible.value = false;
    };

    const convertAppointmentRequest = (receiveInfo) => {
        const appointmentRequest = receiveInfo.event.extendedProps.appointmentRequest;

        if (!appointmentRequest?.id) {
            receiveInfo.revert();

            return;
        }

        router.post(route('branches.booking.appointment-requests.convert', [
            props.branch.id,
            appointmentRequest.id,
        ]), {
            starts_at: toLocalDateTimeString(receiveInfo.event.start),
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                receiveInfo.revert();
                showError('Žiadosť sa nepodarilo premeniť na rezerváciu.', errors);
            },
            onSuccess: () => {
                showSuccess('Žiadosť bola premenená na rezerváciu.');
                reloadCalendarDataInternal();
            },
            onFinish: () => {
                receiveInfo.event.remove();
            },
        });
    };

    return {
        availableSlotsForBooking,
        bookingNotes,
        cancelBooking,
        createAdminBooking,
        duplicateBooking,
        rescheduleBooking,
        rescheduleBookingByCalendarChange,
        submitPendingBookingRescheduleScope,
        cancelPendingBookingReschedule,
        updateBooking,
        convertAppointmentRequest,
    };
}