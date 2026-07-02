import { computed, ref, watch } from 'vue';

import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import { router } from '@inertiajs/vue3';

import { useBookingActions } from './useBookingActions';
import { useBookingCalendarDialogs } from './useBookingCalendarDialogs';
import { useBookingCalendarEvents } from './useBookingCalendarEvents';
import { useBookingDateTime } from './useBookingDateTime';
import { useBookingOpeningHours } from './useBookingOpeningHours';
import { useBookingRules } from './useBookingRules';
import { useCapacityWindowActions } from './useCapacityWindowActions';

export function useBookingCalendar(props, options = {}) {
    const isDateDisabled = options.isDateDisabled ?? (() => false);
    const isDateClosedByOpeningHours = options.isDateClosedByOpeningHours ?? (() => false);
    const toggleDisabledDayByDate = options.toggleDisabledDayByDate ?? (() => { });
    const optimisticDisabledDays = ref({});
    const hiddenEventIds = ref(new Set());

    const hideCalendarEventId = (eventId) => {
        if (!eventId) {
            return;
        }

        const next = new Set(hiddenEventIds.value);
        next.add(eventId);
        hiddenEventIds.value = next;
    };

    const restoreCalendarEventId = (eventId) => {
        if (!eventId || !hiddenEventIds.value.has(eventId)) {
            return;
        }

        const next = new Set(hiddenEventIds.value);
        next.delete(eventId);
        hiddenEventIds.value = next;
    };

    watch(
        () => [
            props.calendarBookings,
            props.availabilityRules,
            props.calendarCapacityWindows,
            props.disabledDays,
        ],
        () => {
            if (hiddenEventIds.value.size === 0) {
                return;
            }

            // Server payload is now the source of truth; drop stale optimistic hide state.
            hiddenEventIds.value = new Set();
        },
    );

    let eventLayoutFrame = null;
    let liveEventLayoutTimer = null;
    const calendarRootElement = ref(null);

    const formatDateOnly = (date) => {
        const normalizedDate = date instanceof Date ? date : new Date(date);

        if (Number.isNaN(normalizedDate.getTime())) {
            return null;
        }

        const year = normalizedDate.getFullYear();
        const month = String(normalizedDate.getMonth() + 1).padStart(2, '0');
        const day = String(normalizedDate.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const getSourceDisabledStateByDateOnly = (dateOnly) => {
        return Boolean(isDateDisabled(`${dateOnly}T00:00:00`));
    };

    const clearOptimisticDisabledState = (dateOnly) => {
        if (!(dateOnly in optimisticDisabledDays.value)) {
            return;
        }

        const { [dateOnly]: _ignored, ...remaining } = optimisticDisabledDays.value;

        optimisticDisabledDays.value = remaining;
    };

    const syncOptimisticDisabledStateIfPersisted = (dateOnly) => {
        if (!(dateOnly in optimisticDisabledDays.value)) {
            return;
        }

        const sourceState = getSourceDisabledStateByDateOnly(dateOnly);

        if (sourceState === optimisticDisabledDays.value[dateOnly]) {
            clearOptimisticDisabledState(dateOnly);
        }
    };

    const getEffectiveDisabledState = (date) => {
        const dateOnly = formatDateOnly(date);

        if (!dateOnly) {
            return false;
        }

        syncOptimisticDisabledStateIfPersisted(dateOnly);

        if (dateOnly in optimisticDisabledDays.value) {
            return Boolean(optimisticDisabledDays.value[dateOnly]);
        }

        return Boolean(isDateDisabled(date));
    };

    const showAvailabilityRules = ref(true);
    const showReservations = ref(true);
    const showGroupEvents = ref(true);

    const currentCalendarDate = ref(new Date().toISOString().slice(0, 10));
    const currentCalendarView = ref('timeGridWeek');
    const currentCalendarRange = ref({
        start: new Date(Date.now() - (31 * 24 * 60 * 60 * 1000)),
        end: new Date(Date.now() + (6 * 30 * 24 * 60 * 60 * 1000)),
    });

    const getBufferedReloadRange = (range = null) => {
        const sourceRange = range ?? currentCalendarRange.value;

        const visibleStart = sourceRange?.start
            ? new Date(sourceRange.start)
            : new Date();
        const visibleEnd = sourceRange?.end
            ? new Date(sourceRange.end)
            : new Date();

        visibleStart.setMonth(visibleStart.getMonth() - 2);
        visibleEnd.setFullYear(visibleEnd.getFullYear() + 2);

        return {
            start: Number.isNaN(visibleStart.getTime()) ? null : visibleStart,
            end: Number.isNaN(visibleEnd.getTime()) ? null : visibleEnd,
        };
    };

    const loadedCalendarRange = ref(getBufferedReloadRange(currentCalendarRange.value));

    const isRangeLoaded = (start, end) => {
        if (!(start instanceof Date) || !(end instanceof Date)) {
            return false;
        }

        const loadedStart = loadedCalendarRange.value?.start;
        const loadedEnd = loadedCalendarRange.value?.end;

        if (!(loadedStart instanceof Date) || !(loadedEnd instanceof Date)) {
            return false;
        }

        return start >= loadedStart && end <= loadedEnd;
    };

    const reloadCalendarData = (range = null) => {
        const bufferedRange = getBufferedReloadRange(range);

        loadedCalendarRange.value = bufferedRange;

        router.reload({
            data: {
                start: bufferedRange.start?.toISOString?.(),
                end: bufferedRange.end?.toISOString?.(),
            },
            only: [
                'availabilityRules',
                'calendarBookings',
                'calendarCapacityWindows',
                'disabledDays',
                'pendingAppointmentRequests',
                'todayBookingsCount',
                'unreadMessagesCount',
            ],
            preserveState: true,
            preserveScroll: true,
        });
    };

    const dateTime = useBookingDateTime();

    const openingHours = useBookingOpeningHours({
        props,
        dateTime,
    });

    const dialogs = useBookingCalendarDialogs({
        dateTime,
        isSelectionInsideOpeningHours: openingHours.isSelectionInsideOpeningHours,
    });

    const rules = useBookingRules({
        props,
        dateTime,
        dialogs,
        isDateRangeInsideOpeningHours: openingHours.isDateRangeInsideOpeningHours,
        hideCalendarEventId,
        restoreCalendarEventId,
        reloadCalendarData,
    });

    const bookingActions = useBookingActions({
        props,
        dateTime,
        dialogs,
        hideCalendarEventId,
        restoreCalendarEventId,
        reloadCalendarData,
    });

    const capacityWindowActions = useCapacityWindowActions({
        props,
        dateTime,
        dialogs,
        hideCalendarEventId,
        restoreCalendarEventId,
        reloadCalendarData,
    });

    const getCapacityWindowId = (capacityWindow) => {
        return capacityWindow?.capacity_window_id
            ?? capacityWindow?.window_id
            ?? capacityWindow?.id
            ?? null;
    };

    watch(
        () => props.calendarCapacityWindows,
        (capacityWindows) => {
            const selectedCapacityWindow = dialogs.selectedCapacityWindow.value;

            if (!selectedCapacityWindow) {
                return;
            }

            const selectedId = Number(getCapacityWindowId(selectedCapacityWindow));

            if (!selectedId) {
                return;
            }

            const updatedCapacityWindow = (capacityWindows ?? []).find((window) => {
                return Number(getCapacityWindowId(window)) === selectedId;
            });

            if (!updatedCapacityWindow) {
                return;
            }

            dialogs.selectedCapacityWindow.value = updatedCapacityWindow;
        },
    );

    const events = useBookingCalendarEvents({
        props,
        showAvailabilityRules,
        showReservations,
        showGroupEvents,
        hiddenEventIds,
        freeTimeRules: rules.freeTimeRules,
        getDateTime: dateTime.getDateTime,
        getRuleOccurrences: rules.getRuleOccurrences,
        getRuleTitle: rules.getRuleTitle,
    });

    const createGroupEventDraft = (data) => ({
        id: null,
        capacity_window_id: null,

        date: data.date,
        starts_at: data.starts_at,
        ends_at: data.ends_at,

        service_id: data.service_id ?? data.service_ids?.[0] ?? null,
        public_booking_type: data.public_booking_type ?? 'immediate_booking',
        capacity: Number(data.capacity ?? 5),
        bookable_places: Number(data.capacity ?? 5),
        admin_note: null,

        repeats: Boolean(data.repeats),
        repeat_every: data.repeat_every ?? 1,
        repeat_unit: data.repeat_unit ?? 'weeks',
        repeat_weekdays: data.recurrence?.frequency === 'weekly'
            ? [...(data.recurrence?.weekdays ?? [])]
            : [],
        repeat_ends_on: data.recurrence?.ends?.type === 'on'
            ? (data.recurrence?.ends?.until ?? null)
            : null,
        recurrence: data.recurrence ?? null,
        apply_to_series: false,
        is_enabled: true,
        group_patients: Array.isArray(data.group_patients)
            ? data.group_patients.map((patient) => ({
                patient_name: patient?.patient_name ?? '',
                patient_email: patient?.patient_email ?? null,
                patient_phone: patient?.patient_phone ?? null,
            }))
            : [],
    });

    const recurrenceFrequencyToRepeatSettings = (recurrence = null) => {
        const frequency = recurrence?.frequency ?? 'weekly';
        const baseInterval = Math.max(1, Number(recurrence?.interval ?? 1));

        if (frequency === 'daily') {
            return {
                repeat_every: baseInterval,
                repeat_unit: 'days',
            };
        }

        if (frequency === 'monthly') {
            return {
                repeat_every: baseInterval,
                repeat_unit: 'months',
            };
        }

        if (frequency === 'yearly') {
            return {
                repeat_every: baseInterval * 12,
                repeat_unit: 'months',
            };
        }

        return {
            repeat_every: baseInterval,
            repeat_unit: 'weeks',
        };
    };

    const addRepeatIntervalToDate = (dateValue, repeatUnit, repeatEvery) => {
        const next = new Date(dateValue);

        if (repeatUnit === 'days') {
            next.setDate(next.getDate() + repeatEvery);

            return next;
        }

        if (repeatUnit === 'months') {
            next.setMonth(next.getMonth() + repeatEvery);

            return next;
        }

        next.setDate(next.getDate() + (7 * repeatEvery));

        return next;
    };

    const resolveRepeatEndsOn = (date, recurrence = null) => {
        if (!recurrence || !date) {
            return null;
        }

        if (recurrence?.ends?.type === 'on' && recurrence?.ends?.until) {
            return recurrence.ends.until;
        }

        const startDate = new Date(`${String(date).slice(0, 10)}T00:00:00`);

        if (Number.isNaN(startDate.getTime())) {
            return null;
        }

        const { repeat_every, repeat_unit } = recurrenceFrequencyToRepeatSettings(recurrence);

        if (recurrence?.ends?.type === 'after') {
            const count = Math.max(1, Number(recurrence?.ends?.count ?? 1));
            let cursor = new Date(startDate);

            for (let index = 1; index < count; index += 1) {
                cursor = addRepeatIntervalToDate(cursor, repeat_unit, repeat_every);
            }

            return formatDateOnly(cursor);
        }

        const fallback = new Date(startDate);
        fallback.setFullYear(fallback.getFullYear() + 2);

        return formatDateOnly(fallback);
    };

    const normalizeRuleRepeatForCompare = (rule) => {
        return {
            repeats: Boolean(rule?.repeats),
            repeat_every: Number(rule?.repeat_every ?? 1),
            repeat_unit: rule?.repeat_unit ?? 'weeks',
            repeat_weekdays: [...(rule?.repeat_weekdays ?? [])].sort(),
            repeat_ends_on: rule?.repeat_ends_on ?? null,
        };
    };

    const normalizeRecurrenceForCompare = (recurrence = null) => {
        if (!recurrence) {
            return null;
        }

        return {
            frequency: recurrence.frequency ?? null,
            interval: Math.max(1, Number(recurrence.interval ?? 1)),
            weekdays: [...(recurrence.weekdays ?? [])].sort(),
            ends: {
                type: recurrence.ends?.type ?? 'never',
                count: recurrence.ends?.count ?? null,
                until: recurrence.ends?.until ?? null,
            },
        };
    };

    const hasRecurringRuleChanged = (previousRecurrence = null, nextRecurrence = null) => {
        return JSON.stringify(normalizeRecurrenceForCompare(previousRecurrence))
            !== JSON.stringify(normalizeRecurrenceForCompare(nextRecurrence));
    };

    const continueFromCreateChoice = (data) => {
        const selectionInfo = dialogs.getSelectionFromCreateChoiceData(data);

        if (!selectionInfo) {
            return;
        }

        if (!openingHours.isSelectionInsideOpeningHours(selectionInfo)) {
            return;
        }

        dialogs.pendingCalendarSelection.value = {
            ...selectionInfo,
            date: selectionInfo.date,
            starts_at: selectionInfo.starts_at,
            ends_at: selectionInfo.ends_at,
        };

        if (data.edit_mode) {
            dialogs.createBookingDialogVisible.value = false;

            if (data.target_type === 'booking' && data.target_id) {
                const booking = (props.calendarBookings ?? []).find((item) => {
                    if (data.target_calendar_event_id) {
                        return item.calendar_event_id === data.target_calendar_event_id;
                    }

                    if (data.target_occurrence_date) {
                        return Number(item.id) === Number(data.target_id)
                            && item.occurrence_date === data.target_occurrence_date;
                    }

                    return Number(item.id) === Number(data.target_id);
                }) ?? {
                    id: data.target_id,
                    booking_id: data.target_id,
                    service_id: data.service_id,
                    service_ids: data.service_ids ?? [],
                    occurrence_date: data.target_occurrence_date ?? data.date ?? null,
                    recurrence: data.target_is_recurring ? (data.recurrence ?? null) : null,
                };

                const recurrenceChanged = hasRecurringRuleChanged(
                    data.target_original_recurrence ?? booking.recurrence ?? null,
                    data.recurrence ?? null,
                );
                const requestedScope = data.save_scope ?? (data.target_is_recurring ? 'series' : null);
                const resolvedScope = data.target_is_recurring && recurrenceChanged && requestedScope === 'occurrence'
                    ? 'series'
                    : requestedScope;

                bookingActions.rescheduleBooking(booking, {
                    service_id: data.service_id,
                    service_ids: data.service_ids ?? [],
                    starts_at: data.starts_at,
                    ends_at: data.ends_at,
                    patient_name: data.patient_name,
                    patient_email: data.patient_email,
                    patient_phone: data.patient_phone,
                    recurrence: data.recurrence ?? null,
                    reschedule_scope: resolvedScope,
                    date: data.target_occurrence_date ?? data.date ?? null,
                    notify_patient: true,
                });

                return;
            }

            if (data.target_type === 'rule' && data.target_id) {
                const targetRuleIndex = rules.ruleForm.rules.findIndex((rule) => {
                    return Number(rule.id) === Number(data.target_id);
                });

                if (targetRuleIndex === -1) {
                    return;
                }

                const targetRule = rules.ruleForm.rules[targetRuleIndex];
                const previousRepeatSignature = normalizeRuleRepeatForCompare(targetRule);

                const repeatSettings = recurrenceFrequencyToRepeatSettings(data.recurrence ?? null);

                const nextRule = {
                    ...targetRule,
                    date: selectionInfo.date,
                    starts_at: selectionInfo.starts_at,
                    ends_at: selectionInfo.ends_at,
                    service_ids: data.service_ids ?? [],
                    public_booking_type: data.public_booking_type ?? targetRule.public_booking_type,
                    repeats: Boolean(data.repeats),
                    repeat_every: Number(repeatSettings.repeat_every ?? data.repeat_every ?? 1),
                    repeat_unit: repeatSettings.repeat_unit ?? data.repeat_unit ?? 'weeks',
                };

                nextRule.repeat_weekdays = nextRule.repeat_unit === 'weeks'
                    ? [...(data.recurrence?.weekdays ?? [])]
                    : [];

                nextRule.repeat_ends_on = data.repeats
                    ? resolveRepeatEndsOn(selectionInfo.date, data.recurrence ?? null)
                    : null;

                nextRule.is_enabled = Boolean(data.is_enabled ?? true);

                const nextRules = [...rules.ruleForm.rules];
                nextRules[targetRuleIndex] = nextRule;
                rules.ruleForm.rules = nextRules;

                const nextRepeatSignature = normalizeRuleRepeatForCompare(nextRule);

                if (JSON.stringify(previousRepeatSignature) !== JSON.stringify(nextRepeatSignature)) {
                    nextRule.excluded_dates = [];
                }

                dialogs.selectedRuleIndex.value = targetRuleIndex;
                rules.saveRules();

                return;
            }

            if (data.target_type === 'group_event' && data.target_id) {
                const repeatSettings = recurrenceFrequencyToRepeatSettings(data.recurrence ?? null);
                const requestedScope = data.save_scope ?? 'occurrence';
                const resolvedScope = data.target_is_recurring
                    && data.save_scope === 'occurrence'
                    && JSON.stringify(normalizeRecurrenceForCompare(data.target_original_recurrence ?? null)) !== JSON.stringify(normalizeRecurrenceForCompare(data.recurrence ?? null))
                    ? 'series'
                    : requestedScope;

                capacityWindowActions.saveCapacityWindow({
                    id: Number(data.target_id),
                    capacity_window_id: Number(data.target_id),
                    service_id: data.service_id ?? data.service_ids?.[0] ?? null,
                    capacity: Number(data.capacity ?? 5),
                    bookable_places: Number(data.capacity ?? 5),
                    public_booking_type: data.public_booking_type ?? 'immediate_booking',
                    repeats: Boolean(data.recurrence),
                    repeat_every: Number(repeatSettings.repeat_every ?? 1),
                    repeat_unit: repeatSettings.repeat_unit ?? 'weeks',
                    repeat_ends_on: data.recurrence
                        ? resolveRepeatEndsOn(selectionInfo.date, data.recurrence ?? null)
                        : null,
                    recurrence: data.recurrence ?? null,
                    target_original_recurrence: data.target_original_recurrence ?? null,
                    date: selectionInfo.date,
                    starts_at: selectionInfo.starts_at,
                    ends_at: selectionInfo.ends_at,
                    original_starts_at: data.original_starts_at ?? data.starts_at,
                    original_ends_at: data.original_ends_at ?? data.ends_at,
                    group_patients: Array.isArray(data.group_patients)
                        ? data.group_patients.map((patient) => ({
                            patient_name: patient?.patient_name ?? '',
                            patient_email: patient?.patient_email ?? null,
                            patient_phone: patient?.patient_phone ?? null,
                        }))
                        : [],
                    update_scope: resolvedScope,
                });
            }

            return;
        }

        if (data.create_type === 'booking') {
            bookingActions.createAdminBooking(data);

            return;
        }

        dialogs.createBookingDialogVisible.value = false;

        if (data.create_type === 'rule') {
            rules.ruleForm.rules.push({
                ...rules.emptyRule(),
                date: selectionInfo.date,
                starts_at: selectionInfo.starts_at,
                ends_at: selectionInfo.ends_at,
                service_ids: data.service_ids ?? [],
                public_booking_type: data.public_booking_type ?? 'immediate_booking',
                repeats: Boolean(data.repeats),
                ...recurrenceFrequencyToRepeatSettings(data.recurrence ?? null),
                repeat_weekdays: data.recurrence?.frequency === 'weekly'
                    ? [...(data.recurrence?.weekdays ?? [])]
                    : [],
                repeat_ends_on: data.repeats
                    ? resolveRepeatEndsOn(selectionInfo.date, data.recurrence ?? null)
                    : null,
                is_enabled: Boolean(data.is_enabled ?? true),
            });

            dialogs.selectedRuleIndex.value = rules.ruleForm.rules.length - 1;
            rules.saveRules();

            return;
        }

        if (data.create_type === 'group_event') {
            capacityWindowActions.saveCapacityWindow(createGroupEventDraft({
                ...data,
                date: selectionInfo.date,
                starts_at: selectionInfo.starts_at,
                ends_at: selectionInfo.ends_at,
            }));

            return;
        }
    };

    const cloneRuleForRestore = (rule) => {
        if (!rule) {
            return null;
        }

        return {
            ...rule,
            service_ids: [...(rule.service_ids ?? [])],
            excluded_dates: [...(rule.excluded_dates ?? [])],
        };
    };

    const getBookingServiceIds = (booking) => {
        if (Array.isArray(booking.service_ids) && booking.service_ids.length) {
            return booking.service_ids;
        }

        if (Array.isArray(booking.services) && booking.services.length) {
            return booking.services.map((service) => service.id);
        }

        return booking.service_id ? [booking.service_id] : [];
    };

    const getBookingRecordId = (booking) => {
        return booking?.booking_id ?? booking?.record_id ?? booking?.id ?? null;
    };

    const openBookingInUnifiedEditor = (booking) => {
        if (!booking) {
            return;
        }

        const serviceIds = getBookingServiceIds(booking);

        dialogs.bookingDialogVisible.value = false;

        dialogs.openCreateBookingWithPrefill({
            create_type: 'booking',
            edit_mode: true,
            target_type: 'booking',
            target_id: getBookingRecordId(booking),
            target_calendar_event_id: booking.calendar_event_id ?? null,
            target_occurrence_date: booking.occurrence_date ?? String(booking.starts_at ?? '').slice(0, 10),
            target_is_recurring: Boolean(booking.recurrence),
            target_original_recurrence: booking.recurrence ?? null,
            date: booking.occurrence_date ?? String(booking.starts_at ?? '').slice(0, 10),
            starts_at: booking.starts_at,
            ends_at: booking.ends_at,
            service_ids: serviceIds,
            service_id: serviceIds[0] ?? booking.service_id,
            patient_name: booking.patient_name ?? '',
            patient_email: booking.patient_email ?? '',
            patient_phone: booking.patient_phone ?? '',
            recurrence: booking.recurrence ?? null,
        });
    };

    const openRuleInUnifiedEditor = (payload) => {
        const rule = payload?.rule ?? null;

        if (!rule?.id) {
            return;
        }

        const occurrenceDate = payload?.selectedRuleOccurrence?.occurrenceDate ?? rule.date;
        const recurrenceFrequency = rule.repeat_unit === 'days'
            ? 'daily'
            : (rule.repeat_unit === 'months' ? 'monthly' : 'weekly');

        dialogs.availabilityRuleDialogVisible.value = false;

        dialogs.openCreateBookingWithPrefill({
            create_type: 'rule',
            edit_mode: true,
            target_type: 'rule',
            target_id: rule.id,
            date: occurrenceDate,
            starts_at: `${occurrenceDate} ${rule.starts_at}:00`,
            ends_at: `${occurrenceDate} ${rule.ends_at}:00`,
            service_ids: [...(rule.service_ids ?? [])],
            service_id: rule.service_ids?.[0] ?? null,
            public_booking_type: rule.public_booking_type ?? 'immediate_booking',
            recurrence: rule.repeats
                ? {
                    frequency: recurrenceFrequency,
                    interval: Number(rule.repeat_every ?? 1),
                    weekdays: recurrenceFrequency === 'weekly'
                        ? [...(rule.repeat_weekdays ?? [])]
                        : [],
                    ends: {
                        type: rule.repeat_ends_on ? 'on' : 'never',
                        count: null,
                        until: rule.repeat_ends_on ?? null,
                    },
                }
                : null,
            is_enabled: Boolean(rule.is_enabled ?? true),
        });
    };

    const openEventDialog = (clickInfo) => {
        if (dialogs.createBookingDialogVisible.value) {
            return;
        }

        if (typeof dialogs.consumeSuppressedEventClick === 'function' && dialogs.consumeSuppressedEventClick()) {
            return;
        }

        if (typeof dialogs.isEventClickSuppressed === 'function' && dialogs.isEventClickSuppressed()) {
            return;
        }

        const type = clickInfo.event.extendedProps.type;

        if (type === 'disabled_day') {
            return;
        }

        if (type === 'rule') {
            const ruleIndex = clickInfo.event.extendedProps.ruleIndex;
            const rule = rules.ruleForm.rules[ruleIndex];

            dialogs.selectedRuleIndex.value = ruleIndex;
            dialogs.selectedRuleOccurrence.value = {
                ruleIndex,
                occurrenceDate: clickInfo.event.extendedProps.occurrenceDate,
                isRepeatedOccurrence: clickInfo.event.extendedProps.isRepeatedOccurrence,
                originalRule: cloneRuleForRestore(rule),
            };

            dialogs.availabilityRuleDialogVisible.value = true;

            return;
        }

        if (type === 'capacity_window') {
            dialogs.selectedCapacityWindow.value = clickInfo.event.extendedProps.capacityWindow;
            dialogs.groupEventOccurrenceDialogVisible.value = true;

            return;
        }

        if (type === 'booking') {
            dialogs.selectedBooking.value = clickInfo.event.extendedProps.booking;
            dialogs.bookingDialogVisible.value = true;
        }
    };

    const getHarnessEventType = (harness) => {
        return harness.dataset.bookingCalendarEventType ?? 'unknown';
    };

    const getEventHarnesses = () => {
        if (!calendarRootElement.value) {
            return [];
        }

        return Array.from(calendarRootElement.value.querySelectorAll('.fc-timegrid-event-harness'))
            .filter((harness) => {
                return harness.offsetParent !== null
                    && harness.querySelector('.fc-timegrid-event, .fc-v-event');
            });
    };

    const getHarnessBounds = (harness) => {
        return {
            harness,
            top: harness.offsetTop,
            bottom: harness.offsetTop + harness.offsetHeight,
            height: harness.offsetHeight,
        };
    };

    const resetHarnessLayout = (harness) => {
        harness.style.left = '0px';
        harness.style.right = '0px';
        harness.style.width = 'auto';
        harness.style.maxWidth = 'none';
    };

    const applyHarnessLayout = (harness, stackIndex) => {
        const stackOffset = 10;
        const maxOffset = 30;
        const offset = Math.min(stackIndex * stackOffset, maxOffset);
        const zIndex = 20 + stackIndex;

        harness.style.left = `${offset}px`;
        harness.style.right = '0px';
        harness.style.width = 'auto';
        harness.style.maxWidth = 'none';
        harness.style.zIndex = String(zIndex);

        const eventElement = harness.querySelector('.fc-timegrid-event, .fc-v-event');

        if (eventElement) {
            eventElement.style.zIndex = String(zIndex);
            eventElement.style.cursor = 'grab';
        }
    };

    const layoutOneDayColumn = (harnesses) => {
        const items = harnesses
            .map(getHarnessBounds)
            .sort((first, second) => {
                if (first.top !== second.top) {
                    return first.top - second.top;
                }

                return second.height - first.height;
            });

        const clusters = [];

        items.forEach((item) => {
            const currentCluster = clusters[clusters.length - 1];

            if (!currentCluster || item.top >= currentCluster.bottom) {
                clusters.push({
                    bottom: item.bottom,
                    items: [item],
                });

                return;
            }

            currentCluster.items.push(item);
            currentCluster.bottom = Math.max(currentCluster.bottom, item.bottom);
        });

        clusters.forEach((cluster) => {
            const layers = [];

            cluster.items.forEach((item) => {
                let layerIndex = layers.findIndex((layerBottom) => {
                    return layerBottom <= item.top;
                });

                if (layerIndex === -1) {
                    layerIndex = layers.length;
                    layers.push(item.bottom);
                } else {
                    layers[layerIndex] = item.bottom;
                }

                applyHarnessLayout(item.harness, layerIndex);
            });
        });
    };

    const layoutCalendarEvents = () => {
        const harnesses = getEventHarnesses();

        harnesses.forEach(resetHarnessLayout);

        const harnessesByColumn = new Map();

        harnesses.forEach((harness) => {
            if (getHarnessEventType(harness) === 'disabled_day') {
                return;
            }

            const column = harness.closest('.fc-timegrid-col') ?? harness.parentElement;

            if (!column) {
                return;
            }

            if (!harnessesByColumn.has(column)) {
                harnessesByColumn.set(column, []);
            }

            harnessesByColumn.get(column).push(harness);
        });

        harnessesByColumn.forEach((columnHarnesses) => {
            layoutOneDayColumn(columnHarnesses);
        });
    };

    const scheduleCalendarEventLayout = () => {
        if (eventLayoutFrame !== null) {
            cancelAnimationFrame(eventLayoutFrame);
        }

        eventLayoutFrame = requestAnimationFrame(() => {
            eventLayoutFrame = null;
            layoutCalendarEvents();
        });
    };

    const startLiveEventLayout = () => {
        if (liveEventLayoutTimer !== null) {
            return;
        }

        liveEventLayoutTimer = window.setInterval(() => {
            scheduleCalendarEventLayout();
        }, 50);
    };

    const stopLiveEventLayout = () => {
        if (liveEventLayoutTimer === null) {
            return;
        }

        window.clearInterval(liveEventLayoutTimer);
        liveEventLayoutTimer = null;

        scheduleCalendarEventLayout();
    };

    const setEventLayer = (mountInfo) => {
        const type = mountInfo.event.extendedProps.type;
        const harness = mountInfo.el.closest('.fc-timegrid-event-harness');

        calendarRootElement.value = mountInfo.el.closest('.fc');

        if (!harness) {
            return;
        }

        harness.dataset.bookingCalendarEventType = type;

        if (type === 'disabled_day') {
            harness.style.zIndex = '0';
            mountInfo.el.style.zIndex = '0';

            return;
        }

        mountInfo.el.style.cursor = 'grab';

        scheduleCalendarEventLayout();
    };

    const handleEventDropOrResize = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type === 'rule') {
            rules.updateRuleFromDrop(changeInfo);

            return;
        }

        if (type === 'booking') {
            bookingActions.rescheduleBookingByCalendarChange(changeInfo);

            return;
        }

        if (type === 'capacity_window') {
            capacityWindowActions.rescheduleCapacityWindowByCalendarChange(changeInfo);
        }
    };

    const rememberCalendarPosition = (dateInfo) => {
        currentCalendarDate.value = dateInfo.startStr.slice(0, 10);
        currentCalendarView.value = dateInfo.view.type;
        currentCalendarRange.value = {
            start: dateInfo.start,
            end: dateInfo.end,
        };

        if (!isRangeLoaded(dateInfo.start, dateInfo.end)) {
            reloadCalendarData({
                start: dateInfo.start,
                end: dateInfo.end,
            });
        }
    };

    const calendarOptions = computed(() => {
        const branchHours = openingHours.getBranchOpeningHoursForCalendar();

        const getResponsiveStatusLabel = ({ isClosed, containerWidth }) => {
            if (containerWidth <= 72) {
                return isClosed ? 'Z' : 'O';
            }

            if (containerWidth <= 112) {
                return isClosed ? 'Zatv.' : 'Otv.';
            }

            return isClosed ? 'Zatvorene' : 'Otvorene';
        };

        const applyStatusTagState = (tag, options) => {
            const containerWidth = options.containerWidth
                ?? tag.parentElement?.parentElement?.clientWidth
                ?? 140;

            tag.textContent = getResponsiveStatusLabel({
                isClosed: options.isClosed,
                containerWidth,
            });
            tag.title = options.title;
            tag.disabled = options.isLocked;
            tag.setAttribute('aria-disabled', options.isLocked ? 'true' : 'false');
            tag.classList.toggle('is-closed', options.isClosed);
            tag.classList.toggle('is-open', !options.isClosed);
            tag.classList.toggle('is-locked', options.isLocked);
        };

        const renderDayHeaderStatusTag = (arg) => {
            if (!arg?.el || !(arg.date instanceof Date)) {
                return;
            }

            const dateOnly = formatDateOnly(arg.date);
            const isClosedByOpeningHours = Boolean(isDateClosedByOpeningHours(arg.date));
            const existingTag = arg.el.querySelector(`[data-disabled-day-date="${dateOnly}"]`);
            const isClosed = isClosedByOpeningHours || getEffectiveDisabledState(arg.date);
            const title = isClosedByOpeningHours
                ? 'Tento deň je zatvorený podľa otváracích hodín.'
                : 'Kliknutím prepnete dostupnosť dňa.';

            if (existingTag) {
                applyStatusTagState(existingTag, {
                    isClosed,
                    isLocked: isClosedByOpeningHours,
                    title,
                });

                return;
            }

            const wrapper = document.createElement('span');
            wrapper.className = 'fc-disabled-day-toggle';
            wrapper.style.display = 'block';
            wrapper.style.width = '100%';
            wrapper.style.marginTop = '4px';

            const tag = document.createElement('button');
            tag.type = 'button';
            tag.className = 'fc-disabled-day-tag';
            tag.dataset.disabledDayDate = dateOnly;
            applyStatusTagState(tag, {
                isClosed,
                isLocked: isClosedByOpeningHours,
                title,
                containerWidth: arg.el.clientWidth,
            });

            tag.addEventListener('click', () => {
                if (isClosedByOpeningHours) {
                    return;
                }

                const previousState = getEffectiveDisabledState(arg.date);
                const nextChecked = !previousState;

                optimisticDisabledDays.value = {
                    ...optimisticDisabledDays.value,
                    [dateOnly]: nextChecked,
                };

                applyStatusTagState(tag, {
                    isClosed: nextChecked,
                    isLocked: false,
                    title,
                });

                toggleDisabledDayByDate(arg.date, nextChecked, {
                    onError: () => {
                        optimisticDisabledDays.value = {
                            ...optimisticDisabledDays.value,
                            [dateOnly]: previousState,
                        };

                        applyStatusTagState(tag, {
                            isClosed: previousState,
                            isLocked: false,
                            title,
                            containerWidth: arg.el.clientWidth,
                        });
                    },
                });
            });

            const headerResizeObserver = new ResizeObserver(() => {
                applyStatusTagState(tag, {
                    isClosed: isClosedByOpeningHours || getEffectiveDisabledState(arg.date),
                    isLocked: isClosedByOpeningHours,
                    title,
                    containerWidth: arg.el.clientWidth,
                });
            });

            headerResizeObserver.observe(arg.el);

            wrapper.appendChild(tag);
            arg.el.appendChild(wrapper);
        };

        return {
            plugins: [
                timeGridPlugin,
                interactionPlugin,
            ],

            initialView: currentCalendarView.value,
            initialDate: currentCalendarDate.value,
            firstDay: 1,

            allDaySlot: false,
            selectable: true,
            editable: true,
            droppable: true,
            eventResizableFromStart: true,

            slotEventOverlap: true,
            eventOverlap: true,
            selectOverlap: true,
            eventMaxStack: 4,

            slotMinTime: branchHours.min,
            slotMaxTime: branchHours.max,
            slotDuration: '00:30:00',
            snapDuration: '00:05:00',

            businessHours: openingHours.getBusinessHours(),
            selectConstraint: 'businessHours',
            selectAllow: openingHours.isSelectionInsideOpeningHours,
            eventAllow: (dropInfo, draggedEvent) => {
                const startsAt = dropInfo.start;

                if (isDateClosedByOpeningHours(startsAt)) {
                    return false;
                }

                if (getEffectiveDisabledState(startsAt)) {
                    return false;
                }

                return true;
            },

            eventDidMount: setEventLayer,
            eventsSet: () => {
                scheduleCalendarEventLayout();
            },
            eventDragStart: () => {
                startLiveEventLayout();
            },
            eventDragStop: () => {
                stopLiveEventLayout();
            },
            eventResizeStart: () => {
                startLiveEventLayout();
            },
            eventResizeStop: () => {
                stopLiveEventLayout();
            },
            eventOrderStrict: false,
            eventOrder: (firstEvent, secondEvent) => {
                const order = {
                    disabled_day: 0,
                    rule: 1,
                    capacity_window: 2,
                    booking: 3,
                };

                const firstOrder = order[firstEvent.extendedProps.type] ?? 0;
                const secondOrder = order[secondEvent.extendedProps.type] ?? 0;

                return firstOrder - secondOrder;
            },

            height: 'auto',
            locale: 'sk',
            nowIndicator: true,

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay',
            },

            buttonText: {
                today: 'Dnes',
                week: 'Týždeň',
                day: 'Deň',
            },

            events: events.calendarEvents.value,

            datesSet: (dateInfo) => {
                rememberCalendarPosition(dateInfo);
                scheduleCalendarEventLayout();
            },
            dayHeaderDidMount: renderDayHeaderStatusTag,
            dayCellClassNames: (dayInfo) => {
                if (!(dayInfo.date instanceof Date)) {
                    return [];
                }

                if (!getEffectiveDisabledState(dayInfo.date)) {
                    return [];
                }

                return [
                    'booking-disabled-day-column',
                ];
            },
            dayHeaderClassNames: (dayInfo) => {
                if (!(dayInfo.date instanceof Date)) {
                    return [];
                }

                return getEffectiveDisabledState(dayInfo.date)
                    ? ['booking-disabled-day-header']
                    : [];
            },

            select: dialogs.openCreateChoiceDialog,
            dateClick: (clickInfo) => {
                dialogs.openCreateChoiceDialog(dialogs.getSelectionFromDateClick(clickInfo));
            },
            eventClick: openEventDialog,
            eventDrop: (changeInfo) => {
                handleEventDropOrResize(changeInfo);
                scheduleCalendarEventLayout();
            },
            eventResize: (changeInfo) => {
                handleEventDropOrResize(changeInfo);
                scheduleCalendarEventLayout();
            },
            eventReceive: (receiveInfo) => {
                bookingActions.convertAppointmentRequest(receiveInfo);
                scheduleCalendarEventLayout();
            },
        };
    });

    return {
        showAvailabilityRules,
        showReservations,
        showGroupEvents,

        createBookingDialogVisible: dialogs.createBookingDialogVisible,
        bookingDialogVisible: dialogs.bookingDialogVisible,

        availabilityRuleDialogVisible: dialogs.availabilityRuleDialogVisible,
        ruleRescheduleScopeDialogVisible: dialogs.ruleRescheduleScopeDialogVisible,
        bookingRescheduleScopeDialogVisible: dialogs.bookingRescheduleScopeDialogVisible,
        capacityWindowRescheduleScopeDialogVisible: dialogs.capacityWindowRescheduleScopeDialogVisible,
        groupEventDialogVisible: dialogs.groupEventDialogVisible,
        groupEventOccurrenceDialogVisible: dialogs.groupEventOccurrenceDialogVisible,

        selectedBooking: dialogs.selectedBooking,
        selectedCapacityWindow: dialogs.selectedCapacityWindow,
        selectedGroupEvent: dialogs.selectedGroupEvent,
        selectedRuleOccurrence: dialogs.selectedRuleOccurrence,
        pendingCalendarSelection: dialogs.pendingCalendarSelection,
        createBookingPrefill: dialogs.createBookingPrefill,

        ruleForm: rules.ruleForm,
        currentRule: rules.currentRule,

        repeatUnitOptions: rules.repeatUnitOptions,

        bookingNotes: bookingActions.bookingNotes,
        calendarOptions,
        currentCalendarRange,

        getRuleTitle: rules.getRuleTitle,
        getRepeatLabel: rules.getRepeatLabel,
        availableSlotsForBooking: bookingActions.availableSlotsForBooking,

        openCreateChoiceFromButton: dialogs.openCreateChoiceFromButton,
        closeCreateBookingDialog: dialogs.closeCreateBookingDialog,
        continueFromCreateChoice,
        openBookingInUnifiedEditor,
        openRuleInUnifiedEditor,

        closeRuleDialog: dialogs.closeRuleDialog,
        closeRuleDialogSafely: rules.closeRuleDialogSafely,
        closeGroupEventDialog: dialogs.closeGroupEventDialog,
        deleteCurrentRule: rules.deleteCurrentRule,
        deleteCurrentRuleByScope: rules.deleteCurrentRuleByScope,
        duplicateCurrentRule: rules.duplicateCurrentRule,
        saveRules: rules.saveRules,

        createAdminBooking: bookingActions.createAdminBooking,
        duplicateBooking: bookingActions.duplicateBooking,
        updateBooking: bookingActions.updateBooking,
        cancelBooking: bookingActions.cancelBooking,
        rescheduleBooking: bookingActions.rescheduleBooking,
        submitPendingBookingRescheduleScope: bookingActions.submitPendingBookingRescheduleScope,
        cancelPendingBookingReschedule: bookingActions.cancelPendingBookingReschedule,

        cancelCapacityWindow: capacityWindowActions.cancelCapacityWindow,
        rescheduleCapacityWindow: capacityWindowActions.rescheduleCapacityWindow,
        saveCapacityWindow: capacityWindowActions.saveCapacityWindow,
        duplicateCapacityWindow: capacityWindowActions.duplicateCapacityWindow,
        submitPendingCapacityWindowRescheduleScope: capacityWindowActions.submitPendingCapacityWindowRescheduleScope,
        cancelPendingCapacityWindowReschedule: capacityWindowActions.cancelPendingCapacityWindowReschedule,

        deleteCurrentRuleOccurrence: rules.deleteCurrentRuleOccurrence,
        deleteCurrentRuleFromNowOn: rules.deleteCurrentRuleFromNowOn,
        deleteCurrentRuleEverywhere: rules.deleteCurrentRuleEverywhere,

        deleteCapacityWindowOccurrence: capacityWindowActions.deleteCapacityWindowOccurrence,
        deleteCapacityWindowFromDate: capacityWindowActions.deleteCapacityWindowFromDate,
        deleteCapacityWindowSeries: capacityWindowActions.deleteCapacityWindowSeries,
        addPatientToCapacityWindow: capacityWindowActions.addPatientToCapacityWindow,

        openCapacityWindowEditor: capacityWindowActions.openCapacityWindowEditor,
    };
}