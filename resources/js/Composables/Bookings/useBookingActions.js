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
    } = dialogs;

    const bookingNotes = ref({});

    const reloadCalendarData = () => {
        router.reload({
            only: [
                'calendarBookings',
                'calendarCapacityWindows',
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
            patient_note: data.patient_note,
            admin_note: data.admin_note,
            notify_patient: Boolean(data.notify_patient ?? true),
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
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
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(options.notify_patient ?? false),
            notification_reason: options.notification_reason ?? null,
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
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
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
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(data.notify_patient ?? false),
            notification_reason: data.notification_reason ?? null,
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
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: true,
            notification_reason: 'Termín rezervácie bol presunutý.',
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
        rescheduleBooking,
        rescheduleBookingByCalendarChange,
        updateBooking,
        convertAppointmentRequest,
    };
}