import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';

export function useBookingActions({ props, dateTime, dialogs }) {
    const toast = useToast();
    const { toLocalDateTimeString } = dateTime;

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

    const {
        bookingDialogVisible,
        createBookingDialogVisible,
        groupEventOccurrenceDialogVisible,
        pendingCalendarSelection,
        openCreateBookingWithPrefill,
        selectedBooking,
        suppressNextEventClick,
        suppressEventClicksFor,
    } = dialogs;

    const bookingNotes = ref({});

    const reloadCalendarData = () => {
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
    };

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
                reloadCalendarData();
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo vytvoriť.', errors);
            },
        });
    };

    const updateBooking = (booking, status, options = {}) => {
        router.put(route('branches.booking.bookings.update', [
            props.branch.id,
            booking.id,
        ]), {
            status,
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeBookingDialogs();
                showSuccess('Rezervácia bola upravená.');
                reloadCalendarData();
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo upraviť.', errors);
            },
        });
    };

    const cancelBooking = (booking, options = {}) => {
        router.post(route('branches.booking.bookings.cancel', [
            props.branch.id,
            booking.id,
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
                reloadCalendarData();
            },
            onError: (errors) => {
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

    const rescheduleBooking = (booking, data = {}) => {
        const serviceIds = getBookingServiceIds(booking, data);

        router.post(route('branches.booking.bookings.reschedule', [
            props.branch.id,
            booking.id,
        ]), {
            service_id: serviceIds[0] ?? data.service_id ?? booking.service_id,
            service_ids: serviceIds,
            starts_at: data.starts_at ?? null,
            ends_at: data.ends_at ?? null,
            notify_patient: true,
            reschedule_scope: data.reschedule_scope ?? null,
            date: data.date ?? booking.occurrence_date ?? booking.starts_at ?? null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeBookingDialogs();
                showSuccess('Rezervácia bola presunutá.');
                reloadCalendarData();
            },
            onError: (errors) => {
                showError('Rezerváciu sa nepodarilo presunúť.', errors);
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
            public_booking_type: booking.service?.public_booking_type ?? 'immediate_booking',
            recurrence: booking.recurrence ?? null,
        });

        closeBookingDialogs();
    };

    const rescheduleBookingByCalendarChange = (changeInfo) => {
        const booking = changeInfo.event.extendedProps.booking;

        if (!booking) {
            changeInfo.revert();

            return;
        }

        const serviceIds = getBookingServiceIds(booking);

        router.post(route('branches.booking.bookings.reschedule', [
            props.branch.id,
            booking.id,
        ]), {
            service_id: serviceIds[0] ?? booking.service_id,
            service_ids: serviceIds,
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: changeInfo.event.end
                ? toLocalDateTimeString(changeInfo.event.end)
                : booking.ends_datetime ?? booking.ends_at,
            notify_patient: true,
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                changeInfo.revert();
                showError('Rezerváciu sa nepodarilo presunúť.', errors);
            },
            onSuccess: () => {
                showSuccess('Rezervácia bola presunutá.');
                reloadCalendarData();
            },
        });
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
                reloadCalendarData();
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
        updateBooking,
        convertAppointmentRequest,
    };
}