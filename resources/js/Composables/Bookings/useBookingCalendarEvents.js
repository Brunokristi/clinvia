import { computed } from 'vue';

export function useBookingCalendarEvents({
    props,
    showAvailabilityRules,
    showReservations,
    showGroupEvents,
    hiddenEventIds,
    freeTimeRules,
    getDateTime,
    getRuleOccurrences,
    getRuleTitle,
}) {
    const isHiddenEventId = (eventId) => {
        return Boolean(eventId) && hiddenEventIds?.value?.has(eventId);
    };

    const normalizeDateOnly = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return value.toISOString().slice(0, 10);
        }

        return String(value).slice(0, 10);
    };

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

    const getBookingTitle = (booking) => {
        return booking.patient_name
            ?? booking.patientName
            ?? booking.patient?.name
            ?? 'Rezervácia';
    };

    const ruleEvents = computed(() => {
        if (!showAvailabilityRules.value) {
            return [];
        }

        return (freeTimeRules.value ?? []).flatMap((rule) => {
            return getRuleOccurrences(rule).map((occurrenceDate) => {
                const isRepeatedOccurrence = Boolean(rule.repeats);
                const eventId = `rule-${rule.id ?? 'new'}-${rule.ruleIndex}-${occurrenceDate}`;

                if (isHiddenEventId(eventId)) {
                    return null;
                }

                return {
                    id: eventId,
                    title: 'Pravidlo rezervácií',
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
            }).filter(Boolean);
        });
    });

    const bookingEvents = computed(() => {
        if (!showReservations.value) {
            return [];
        }

        return (props.calendarBookings ?? []).map((booking) => {
            const eventId = booking.calendar_event_id ?? `booking-${booking.id}`;

            if (isHiddenEventId(eventId)) {
                return null;
            }

            return {
                id: eventId,
                title: getBookingTitle(booking),
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
        }).filter(Boolean);
    });

    const capacityWindowEvents = computed(() => {
        if (showGroupEvents && !showGroupEvents.value) {
            return [];
        }

        return (props.calendarCapacityWindows ?? []).map((capacityWindow) => {
            const bookingsCount = Number(capacityWindow.bookings_count ?? capacityWindow.bookings?.length ?? 0);
            const capacity = Number(capacityWindow.capacity ?? capacityWindow.bookable_places ?? 0);
            const isFull = capacity > 0 && bookingsCount >= capacity;
            const eventId = `capacity-window-${capacityWindow.id}`;

            if (isHiddenEventId(eventId)) {
                return null;
            }

            return {
                id: eventId,
                title: 'Skupinový termín',
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
        }).filter(Boolean);
    });

    const disabledDayEvents = computed(() => {
        return (props.disabledDays ?? []).map((disabledDay) => {
            const date = normalizeDateOnly(disabledDay.date);

            if (!date) {
                return null;
            }

            const end = new Date(`${date}T00:00:00`);
            end.setDate(end.getDate() + 1);

            return {
                id: `disabled-day-${disabledDay.id}`,
                title: disabledDay.title ?? 'Zakázaný deň',
                start: `${date}T00:00:00`,
                end: `${end.toISOString().slice(0, 10)}T00:00:00`,
                display: 'background',
                editable: false,
                overlap: false,
                classNames: [
                    'booking-disabled-day-event',
                ],
                extendedProps: {
                    type: 'disabled_day',
                    disabledDay,
                },
            };
        }).filter(Boolean);
    });

    const calendarEvents = computed(() => {
        return [
            ...disabledDayEvents.value,
            ...ruleEvents.value,
            ...capacityWindowEvents.value,
            ...bookingEvents.value,
        ];
    });

    return {
        calendarEvents,
    };
}