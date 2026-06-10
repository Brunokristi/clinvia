import { computed } from 'vue';

export function useBookingCalendarEvents({
    props,
    showAvailabilityRules,
    showReservations,
    showGroupEvents,
    freeTimeRules,
    getDateTime,
    getRuleOccurrences,
    getRuleTitle,
}) {
    const getDateTimeValue = (date, time) => {
        if (typeof getDateTime === 'function') {
            return getDateTime(date, time);
        }

        return `${date}T${String(time).slice(0, 5)}:00`;
    };

    const normalizeCalendarDateTime = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return value;
        }

        return String(value)
            .trim()
            .replace(' ', 'T')
            .replace(/Z$/, '')
            .replace(/([+-]\d{2}:?\d{2})$/, '')
            .slice(0, 19);
    };

    const ruleEvents = computed(() => {
        if (!showAvailabilityRules.value) {
            return [];
        }

        return (freeTimeRules.value ?? []).flatMap((rule) => {
            return getRuleOccurrences(rule).map((occurrenceDate) => {
                const isRepeatedOccurrence = Boolean(rule.repeats);

                return {
                    id: `rule-${rule.id ?? 'new'}-${rule.ruleIndex}-${occurrenceDate}`,
                    title: getRuleTitle(rule),
                    start: getDateTimeValue(occurrenceDate, rule.starts_at),
                    end: getDateTimeValue(occurrenceDate, rule.ends_at),
                    editable: true,
                    durationEditable: true,
                    startEditable: true,
                    classNames: [
                        'booking-rule-free-time',
                    ],
                    extendedProps: {
                        type: 'rule',
                        rule,
                        ruleIndex: rule.ruleIndex,
                        occurrenceDate,
                        isRepeatedOccurrence,
                    },
                };
            });
        });
    });

    const bookingEvents = computed(() => {
        if (!showReservations.value) {
            return [];
        }

        return (props.calendarBookings ?? []).map((booking) => {
            return {
                id: `booking-${booking.id}`,
                title: booking.patient_name ?? 'Rezervácia',
                start: normalizeCalendarDateTime(booking.starts_datetime ?? booking.starts_at),
                end: normalizeCalendarDateTime(booking.ends_datetime ?? booking.ends_at),
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
            };
        });
    });

    const capacityWindowEvents = computed(() => {
        if (showGroupEvents && !showGroupEvents.value) {
            return [];
        }

        return (props.calendarCapacityWindows ?? []).map((capacityWindow) => {
            const bookingsCount = Number(capacityWindow.bookings_count ?? capacityWindow.bookings?.length ?? 0);
            const capacity = Number(capacityWindow.capacity ?? capacityWindow.bookable_places ?? 0);
            const isFull = capacity > 0 && bookingsCount >= capacity;

            return {
                id: `capacity-window-${capacityWindow.id}`,
                title: capacityWindow.title
                    ?? capacityWindow.service?.name
                    ?? capacityWindow.service_name
                    ?? 'Skupinový termín',
                start: normalizeCalendarDateTime(capacityWindow.starts_datetime ?? capacityWindow.starts_at),
                end: normalizeCalendarDateTime(capacityWindow.ends_datetime ?? capacityWindow.ends_at),
                editable: true,
                durationEditable: true,
                startEditable: true,
                classNames: [
                    'booking-capacity-window-event',
                    ...(isFull ? ['booking-capacity-window-full'] : []),
                ],
                extendedProps: {
                    type: 'capacity_window',
                    capacityWindow,
                },
            };
        });
    });

    const calendarEvents = computed(() => {
        return [
            ...ruleEvents.value,
            ...capacityWindowEvents.value,
            ...bookingEvents.value,
        ];
    });

    return {
        calendarEvents,
    };
}
