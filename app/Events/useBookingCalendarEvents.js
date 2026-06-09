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

    const toLocalCalendarDateTime = (value) => {
        if (!value) {
            return null;
        }

        const stringValue = String(value).trim();

        if (stringValue.includes('T')) {
            return stringValue
                .replace(' ', 'T')
                .replace(/Z$/, '')
                .replace(/([+-]\d{2}:?\d{2})$/, '')
                .slice(0, 19);
        }

        if (stringValue.includes(' ') && stringValue.length >= 16) {
            return stringValue.replace(' ', 'T').slice(0, 19);
        }

        return stringValue;
    };

    const toFullCalendarDateTime = (date, value) => {
        if (!value) {
            return null;
        }

        const localDateTime = toLocalCalendarDateTime(value);

        if (!localDateTime) {
            return null;
        }

        if (localDateTime.includes('T')) {
            return localDateTime;
        }

        if (!date) {
            return null;
        }

        return getDateTime(date, localDateTime.slice(0, 5));
    };

    const availabilityEvents = computed(() => {
        if (!showAvailabilityRules.value) {
            return [];
        }

        return (freeTimeRules.value ?? []).flatMap((rule) => {
            return getRuleOccurrences(rule).map((date, occurrenceIndex) => ({
                id: `rule-${rule.ruleIndex}-${date}`,
                title: getRuleTitle(rule),
                start: getDateTime(date, rule.starts_at),
                end: getDateTime(date, rule.ends_at),
                editable: occurrenceIndex === 0,
                classNames: [
                    'booking-rule-free-time',
                ],
                extendedProps: {
                    type: 'rule',
                    ruleIndex: rule.ruleIndex,
                    occurrenceDate: date,
                    isRepeatedOccurrence: occurrenceIndex > 0,
                },
            }));
        });
    });

    const bookingEvents = computed(() => {
        if (!showReservations.value) {
            return [];
        }

        return (props.calendarBookings ?? [])
            .filter((booking) => !isHiddenBooking(booking))
            .map((booking) => {
                const date = booking.date
                    ?? booking.starts_at?.slice?.(0, 10)
                    ?? booking.starts_datetime?.slice?.(0, 10);

                const start = toFullCalendarDateTime(
                    date,
                    booking.starts_datetime ?? booking.starts_at,
                );

                const end = toFullCalendarDateTime(
                    date,
                    booking.ends_datetime ?? booking.ends_at,
                );

                return {
                    id: `booking-${booking.id}`,
                    title: booking.service_name
                        ? `${booking.patient_name} · ${booking.service_name}`
                        : booking.patient_name,
                    start,
                    end,
                    editable: true,
                    classNames: [
                        'booking-reservation-event',
                    ],
                    extendedProps: {
                        type: 'booking',
                        booking,
                    },
                };
            })
            .filter((event) => event.start && event.end);
    });

    const capacityWindowEvents = computed(() => {
        return (props.calendarCapacityWindows ?? [])
            .map((capacityWindow) => {
                const date = capacityWindow.date
                    ?? capacityWindow.starts_datetime?.slice?.(0, 10)
                    ?? capacityWindow.starts_at?.slice?.(0, 10)
                    ?? capacityWindow.start?.slice?.(0, 10);

                const start = toFullCalendarDateTime(
                    date,
                    capacityWindow.starts_datetime
                        ?? capacityWindow.start
                        ?? capacityWindow.starts_at,
                );

                const end = toFullCalendarDateTime(
                    date,
                    capacityWindow.ends_datetime
                        ?? capacityWindow.end
                        ?? capacityWindow.ends_at,
                );

                const bookedCount = Number(
                    capacityWindow.booked_count
                        ?? capacityWindow.bookings?.length
                        ?? 0,
                );

                const capacity = Number(
                    capacityWindow.capacity
                        ?? 1,
                );

                const availableCount = Number(
                    capacityWindow.available_count
                        ?? Math.max(0, capacity - bookedCount),
                );

                const normalizedCapacityWindow = {
                    ...capacityWindow,

                    id: capacityWindow.id,
                    capacity_window_id: capacityWindow.capacity_window_id ?? capacityWindow.id,

                    date,
                    starts_datetime: start,
                    ends_datetime: end,
                    starts_at: capacityWindow.starts_at ?? start,
                    ends_at: capacityWindow.ends_at ?? end,

                    booked_count: bookedCount,
                    capacity,
                    available_count: availableCount,
                };

                return {
                    id: `capacity-window-${normalizedCapacityWindow.capacity_window_id}`,
                    title: `${normalizedCapacityWindow.service_name ?? 'Skupinový termín'} · ${bookedCount}/${capacity}`,
                    start,
                    end,
                    editable: true,
                    classNames: [
                        'booking-capacity-window-event',
                        availableCount <= 0 ? 'booking-capacity-window-full' : null,
                    ].filter(Boolean),
                    extendedProps: {
                        type: 'capacity_window',
                        capacityWindow: normalizedCapacityWindow,
                    },
                };
            })
            .filter((event) => event.start && event.end);
    });

    const calendarEvents = computed(() => {
        return [
            ...availabilityEvents.value,
            ...capacityWindowEvents.value,
            ...bookingEvents.value,
        ];
    });

    return {
        calendarEvents,
    };
}
