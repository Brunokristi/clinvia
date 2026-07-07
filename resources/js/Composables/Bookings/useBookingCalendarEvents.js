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

    const normalizeTimeValue = (value, fallback) => {
        if (!value) {
            return String(fallback ?? '').slice(0, 5);
        }

        if (value instanceof Date) {
            return value.toTimeString().slice(0, 5);
        }

        const stringValue = String(value).trim();

        if (stringValue.includes('T')) {
            return stringValue.slice(11, 16);
        }

        if (stringValue.includes(' ') && stringValue.length >= 16) {
            return stringValue.slice(11, 16);
        }

        return stringValue.slice(0, 5);
    };

    const normalizeDateValue = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return value.toISOString().slice(0, 10);
        }

        return String(value).slice(0, 10);
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
            const normalizedOverrides = Array.isArray(rule.occurrence_overrides)
                ? rule.occurrence_overrides
                    .map((override) => ({
                        originalDate: normalizeDateValue(override?.original_date),
                        occurrenceDate: normalizeDateValue(override?.date),
                        startsAt: normalizeTimeValue(override?.starts_at, rule.starts_at),
                        endsAt: normalizeTimeValue(override?.ends_at, rule.ends_at),
                        status: String(override?.status ?? 'confirmed'),
                    }))
                    .filter((override) => override.originalDate && override.occurrenceDate)
                : [];

            const overrideOriginalDates = new Set(normalizedOverrides.map((override) => override.originalDate));
            const visibleOverrides = normalizedOverrides.filter((override) => override.status !== 'cancelled');

            const baseEvents = getRuleOccurrences(rule)
                .filter((occurrenceDate) => !overrideOriginalDates.has(occurrenceDate))
                .map((occurrenceDate) => {
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
                            occurrenceOriginalDate: occurrenceDate,
                            isRepeatedOccurrence: Boolean(rule.repeats),
                            isOverrideOccurrence: false,
                        },
                    };
                })
                .filter(Boolean);

            const overrideEvents = visibleOverrides.map((override) => {
                const isRepeatedOccurrence = Boolean(rule.repeats);
                const eventId = `rule-${rule.id ?? 'new'}-${rule.ruleIndex}-${override.originalDate}-override`;

                if (isHiddenEventId(eventId)) {
                    return null;
                }

                return {
                    id: eventId,
                    title: 'Pravidlo rezervácií',
                    start: getDateTimeValue(override.occurrenceDate, override.startsAt),
                    end: getDateTimeValue(override.occurrenceDate, override.endsAt),
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
                        occurrenceDate: override.occurrenceDate,
                        occurrenceOriginalDate: override.originalDate,
                        isRepeatedOccurrence,
                        isOverrideOccurrence: true,
                    },
                };
            }).filter(Boolean);

            return [
                ...baseEvents,
                ...overrideEvents,
            ];
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

        return (props.calendarCapacityWindows ?? [])
            .filter((capacityWindow) => String(capacityWindow?.status ?? 'confirmed') !== 'cancelled')
            .map((capacityWindow) => {
                const bookingsCount = Number(capacityWindow.bookings_count ?? capacityWindow.bookings?.length ?? 0);
                const capacity = Number(capacityWindow.capacity ?? capacityWindow.bookable_places ?? 0);
                const isFull = capacity > 0 && bookingsCount >= capacity;
                const eventId = capacityWindow.calendar_event_id
                    ?? `capacity-window-${capacityWindow.id}`;

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