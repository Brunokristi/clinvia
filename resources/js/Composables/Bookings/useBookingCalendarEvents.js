import { computed } from 'vue';

export function useBookingCalendarEvents({
    props,
    showAvailabilityRules,
    showReservations,
    freeTimeRules,
    getDateTime,
    getRuleOccurrences,
    getRuleTitle,
}) {
    const hiddenStatuses = [
        'cancelled',
        'rejected',
    ];

    const isHiddenBooking = (booking) => {
        return hiddenStatuses.includes(booking.status);
    };

    const availabilityEvents = computed(() => {
        if (!showAvailabilityRules.value) {
            return [];
        }

        return freeTimeRules.value.flatMap((rule) => {
            return getRuleOccurrences(rule).map((date, occurrenceIndex) => ({
                id: `rule-${rule.ruleIndex}-${date}`,
                title: getRuleTitle(rule),
                start: getDateTime(date, rule.starts_at),
                end: getDateTime(date, rule.ends_at),
                editable: occurrenceIndex === 0,
                classNames: [
                    'booking-rule-event booking-rule-free-time',
                ],
                extendedProps: {
                    type: 'rule',
                    ruleIndex: rule.ruleIndex,
                    occurrenceDate: date,
                    isRepeatedOccurrence: rule.repeats && date !== rule.date,
                },
            }));
        });
    });

    const capacityWindowEvents = computed(() => {
        if (!showReservations.value) {
            return [];
        }

        return (props.calendarCapacityWindows ?? []).map((capacityWindow) => {
            const visibleBookings = (capacityWindow.bookings ?? [])
                .filter((booking) => !isHiddenBooking(booking));

            return {
                id: `capacity-window-${capacityWindow.id}`,
                title: `${capacityWindow.service_name} · ${visibleBookings.length}/${capacityWindow.capacity} obsadené`,
                start: capacityWindow.starts_at,
                end: capacityWindow.ends_at,
                editable: true,
                durationEditable: true,
                startEditable: true,
                classNames: [
                    visibleBookings.length >= Number(capacityWindow.capacity)
                        ? 'booking-capacity-window-event booking-capacity-window-full'
                        : 'booking-capacity-window-event',
                ],
                extendedProps: {
                    type: 'capacity_window',
                    capacityWindow: {
                        ...capacityWindow,
                        bookings: visibleBookings,
                    },
                },
            };
        });
    });

    const individualReservationEvents = computed(() => {
        if (!showReservations.value) {
            return [];
        }

        return (props.calendarBookings ?? [])
            .filter((booking) => !isHiddenBooking(booking))
            .map((booking) => ({
                id: `booking-${booking.id}`,
                title: `${booking.service_name} · ${booking.patient_name}`,
                start: booking.starts_at,
                end: booking.ends_at,
                editable: true,
                durationEditable: true,
                startEditable: true,
                classNames: [
                    'booking-reservation-event',
                ],
                extendedProps: {
                    type: 'booking',
                    booking,
                },
            }));
    });

    const calendarEvents = computed(() => [
        ...availabilityEvents.value,
        ...capacityWindowEvents.value,
        ...individualReservationEvents.value,
    ]);

    return {
        calendarEvents,
    };
}
