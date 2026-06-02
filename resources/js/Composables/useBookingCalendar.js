import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

export function useBookingCalendar(props) {
    const showAvailabilityRules = ref(true);
    const showReservations = ref(true);

    const ruleDialogVisible = ref(false);
    const bookingDialogVisible = ref(false);
    const capacityWindowDialogVisible = ref(false);
    const createBookingDialogVisible = ref(false);
    const deleteRuleDialogVisible = ref(false);

    const selectedRuleIndex = ref(null);
    const selectedBooking = ref(null);
    const selectedCapacityWindow = ref(null);
    const selectedRuleOccurrence = ref(null);

    const hiddenStatuses = [
        'cancelled',
        'rejected',
    ];

    const slotModeOptions = [
        {
            label: 'Slot pre jednu službu s viacerými klientmi',
            value: 'single_service_many_clients',
        },
        {
            label: 'Voľný rezervovateľný čas',
            value: 'free_bookable_time',
        },
    ];

    const repeatUnitOptions = [
        {
            label: 'dni',
            value: 'days',
        },
        {
            label: 'týždne',
            value: 'weeks',
        },
        {
            label: 'mesiace',
            value: 'months',
        },
    ];

    const emptyRule = () => ({
        id: null,

        date: null,
        starts_at: '08:00',
        ends_at: '16:00',

        slot_mode: 'free_bookable_time',

        service_id: null,
        service_ids: [],

        bookable_places: 1,

        repeats: false,
        repeat_every: 1,
        repeat_unit: 'weeks',
        repeat_ends_on: null,
        excluded_dates: [],

        is_enabled: true,
    });

    const formatDate = (value) => {
        if (!value) {
            return null;
        }

        return String(value).slice(0, 10);
    };

    const formatTime = (value) => {
        if (!value) {
            return null;
        }

        return String(value).slice(0, 5);
    };

    const formatCalendarTime = (value) => {
        const time = formatTime(value);

        if (!time) {
            return null;
        }

        return `${time}:00`;
    };

    const getDateFromDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const getTimeFromDate = (date) => {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${hours}:${minutes}`;
    };

    const getDateTime = (date, time) => {
        return `${date}T${time}:00`;
    };

    const toLocalDateTimeString = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:00`;
    };

    const addRepeatInterval = (date, rule) => {
        const nextDate = new Date(date);
        const amount = Number(rule.repeat_every || 1);

        if (rule.repeat_unit === 'days') {
            nextDate.setDate(nextDate.getDate() + amount);
        }

        if (rule.repeat_unit === 'weeks') {
            nextDate.setDate(nextDate.getDate() + (amount * 7));
        }

        if (rule.repeat_unit === 'months') {
            nextDate.setMonth(nextDate.getMonth() + amount);
        }

        return nextDate;
    };

    const getRuleOccurrences = (rule) => {
        const occurrences = [];

        if (!rule.date || !rule.starts_at || !rule.ends_at) {
            return occurrences;
        }

        const excludedDates = rule.excluded_dates ?? [];
        const startDate = new Date(`${rule.date}T00:00:00`);

        const calendarStart = new Date();
        calendarStart.setMonth(calendarStart.getMonth() - 1);
        calendarStart.setHours(0, 0, 0, 0);

        const calendarEnd = new Date();
        calendarEnd.setMonth(calendarEnd.getMonth() + 6);
        calendarEnd.setHours(23, 59, 59, 999);

        const repeatEndDate = rule.repeat_ends_on
            ? new Date(`${rule.repeat_ends_on}T23:59:59`)
            : null;

        const maxEndDate = repeatEndDate && repeatEndDate < calendarEnd
            ? repeatEndDate
            : calendarEnd;

        if (!rule.repeats) {
            if (
                startDate >= calendarStart
                && startDate <= maxEndDate
                && !excludedDates.includes(rule.date)
            ) {
                occurrences.push(rule.date);
            }

            return occurrences;
        }

        let occurrenceDate = startDate;

        while (occurrenceDate <= maxEndDate) {
            const occurrenceDateString = getDateFromDate(occurrenceDate);

            if (
                occurrenceDate >= calendarStart
                && !excludedDates.includes(occurrenceDateString)
            ) {
                occurrences.push(occurrenceDateString);
            }

            occurrenceDate = addRepeatInterval(occurrenceDate, rule);
        }

        return occurrences;
    };

    const isHiddenBooking = (booking) => {
        return hiddenStatuses.includes(booking.status);
    };

    const ruleForm = useForm({
        rules: (props.branch.booking_availability_rules ?? []).map((rule) => ({
            id: rule.id,

            date: formatDate(rule.date ?? rule.starts_on ?? rule.start_date),
            starts_at: formatTime(rule.starts_at),
            ends_at: formatTime(rule.ends_at),

            slot_mode: rule.slot_mode ?? rule.booking_mode ?? 'free_bookable_time',

            service_id: rule.service_id
                ?? rule.selected_service_id
                ?? (rule.services?.length === 1 ? rule.services[0].id : null),

            service_ids: (rule.services ?? []).map((service) => service.id),

            bookable_places: rule.bookable_places ?? rule.capacity ?? 1,

            repeats: Boolean(rule.repeats),
            repeat_every: rule.repeat_every ?? rule.repeat_interval ?? 1,
            repeat_unit: rule.repeat_unit ?? 'weeks',
            repeat_ends_on: formatDate(rule.repeat_ends_on),
            excluded_dates: rule.excluded_dates ?? [],

            is_enabled: Boolean(rule.is_enabled),
        })),
    });

    const bookingNotes = ref({});

    const fillBookingNotes = (bookings) => {
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

    const currentRule = computed(() => {
        if (selectedRuleIndex.value === null) {
            return null;
        }

        return ruleForm.rules[selectedRuleIndex.value] ?? null;
    });

    const getRuleServiceIds = (rule) => {
        if (rule.slot_mode === 'single_service_many_clients') {
            return rule.service_id ? [rule.service_id] : [];
        }

        return rule.service_ids ?? [];
    };

    const getServiceNames = (serviceIds) => {
        return props.services
            .filter((service) => serviceIds.includes(service.id))
            .map((service) => service.name)
            .join(', ');
    };

    const getRuleTitle = (rule) => {
        const services = getServiceNames(getRuleServiceIds(rule)) || 'Bez služby';

        if (rule.slot_mode === 'single_service_many_clients') {
            return `${services} · ${rule.bookable_places} miest`;
        }

        return `${services} · voľný čas`;
    };

    const getRepeatLabel = (rule) => {
        if (!rule.repeats) {
            return 'Neopakuje sa';
        }

        const unit = repeatUnitOptions.find((option) => option.value === rule.repeat_unit)?.label ?? '';

        return `Opakovať každých ${rule.repeat_every} ${unit}`;
    };

    const freeTimeRules = computed(() => {
        return ruleForm.rules
            .map((rule, index) => ({
                ...rule,
                ruleIndex: index,
            }))
            .filter((rule) => {
                return rule.is_enabled
                    && rule.slot_mode !== 'single_service_many_clients'
                    && rule.date
                    && rule.starts_at
                    && rule.ends_at;
            });
    });

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

    const getOpeningHours = () => {
        return props.branch.opening_hours ?? props.branch.openingHours ?? [];
    };

    const getOpeningDayValue = (openingDay) => {
        return Number(openingDay.day_of_week);
    };

    const getFullCalendarDayValue = (openingDay) => {
        const day = getOpeningDayValue(openingDay);

        return day === 7 ? 0 : day;
    };

    const getDatabaseDayFromDate = (date) => {
        const day = date.getDay();

        return day === 0 ? 7 : day;
    };

    const getOpeningIntervals = (openingDay) => {
        if (!openingDay || openingDay.is_closed) {
            return [];
        }

        return openingDay.intervals ?? [];
    };

    const getDayOpeningHours = (date) => {
        const databaseDay = getDatabaseDayFromDate(date);

        return getOpeningHours().find((openingDay) => {
            return getOpeningDayValue(openingDay) === databaseDay;
        }) ?? null;
    };

    const getAllOpeningIntervals = () => {
        return getOpeningHours()
            .flatMap((openingDay) => getOpeningIntervals(openingDay));
    };

    const isDateRangeInsideOpeningHours = (start, end) => {
        if (!start || !end) {
            return false;
        }

        if (getDateFromDate(start) !== getDateFromDate(end)) {
            return false;
        }

        const openingDay = getDayOpeningHours(start);
        const intervals = getOpeningIntervals(openingDay);

        if (!intervals.length) {
            return false;
        }

        const startTime = getTimeFromDate(start);
        const endTime = getTimeFromDate(end);

        return intervals.some((interval) => {
            return startTime >= formatTime(interval.opens_at)
                && endTime <= formatTime(interval.closes_at);
        });
    };

    const isSelectionInsideOpeningHours = (selectInfo) => {
        return isDateRangeInsideOpeningHours(selectInfo.start, selectInfo.end);
    };

    const isEventAllowed = (dropInfo, draggedEvent) => {
        const type = draggedEvent?.extendedProps?.type;

        if (type === 'booking') {
            return true;
        }

        if (type === 'capacity_window') {
            return true;
        }

        if (type === 'rule') {
            return isDateRangeInsideOpeningHours(dropInfo.start, dropInfo.end);
        }

        return false;
    };

    const getBusinessHours = () => {
        return getOpeningHours()
            .flatMap((openingDay) => {
                return getOpeningIntervals(openingDay).map((interval) => ({
                    daysOfWeek: [
                        getFullCalendarDayValue(openingDay),
                    ],
                    startTime: formatTime(interval.opens_at),
                    endTime: formatTime(interval.closes_at),
                }));
            });
    };

    const getBranchOpeningHoursForCalendar = () => {
        const intervals = getAllOpeningIntervals();

        if (!intervals.length) {
            return {
                min: '06:00:00',
                max: '20:00:00',
            };
        }

        const min = intervals
            .map((interval) => formatTime(interval.opens_at))
            .filter(Boolean)
            .sort()[0];

        const max = intervals
            .map((interval) => formatTime(interval.closes_at))
            .filter(Boolean)
            .sort()
            .reverse()[0];

        return {
            min: formatCalendarTime(min),
            max: formatCalendarTime(max),
        };
    };

    const openCreateRuleDialog = (selectInfo) => {
        if (!isSelectionInsideOpeningHours(selectInfo)) {
            return;
        }

        ruleForm.rules.push({
            ...emptyRule(),
            date: getDateFromDate(selectInfo.start),
            starts_at: getTimeFromDate(selectInfo.start),
            ends_at: getTimeFromDate(selectInfo.end),
        });

        selectedRuleIndex.value = ruleForm.rules.length - 1;
        selectedRuleOccurrence.value = {
            ruleIndex: selectedRuleIndex.value,
            occurrenceDate: getDateFromDate(selectInfo.start),
            isRepeatedOccurrence: false,
        };

        ruleDialogVisible.value = true;
    };

    const openCreateBookingDialog = () => {
        createBookingDialogVisible.value = true;
    };

    const closeCreateBookingDialog = () => {
        createBookingDialogVisible.value = false;
    };

    const openEventDialog = (clickInfo) => {
        const type = clickInfo.event.extendedProps.type;

        if (type === 'rule') {
            selectedRuleIndex.value = clickInfo.event.extendedProps.ruleIndex;

            selectedRuleOccurrence.value = {
                ruleIndex: clickInfo.event.extendedProps.ruleIndex,
                occurrenceDate: clickInfo.event.extendedProps.occurrenceDate,
                isRepeatedOccurrence: clickInfo.event.extendedProps.isRepeatedOccurrence,
            };

            ruleDialogVisible.value = true;

            return;
        }

        if (type === 'capacity_window') {
            selectedCapacityWindow.value = clickInfo.event.extendedProps.capacityWindow;
            capacityWindowDialogVisible.value = true;

            return;
        }

        if (type === 'booking') {
            selectedBooking.value = clickInfo.event.extendedProps.booking;
            bookingDialogVisible.value = true;
        }
    };

    const updateRuleFromDrop = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type !== 'rule') {
            return;
        }

        if (changeInfo.event.extendedProps.isRepeatedOccurrence) {
            changeInfo.revert();

            return;
        }

        if (!isDateRangeInsideOpeningHours(changeInfo.event.start, changeInfo.event.end)) {
            changeInfo.revert();

            return;
        }

        const index = changeInfo.event.extendedProps.ruleIndex;
        const rule = ruleForm.rules[index];

        if (!rule) {
            return;
        }

        rule.date = getDateFromDate(changeInfo.event.start);
        rule.starts_at = getTimeFromDate(changeInfo.event.start);
        rule.ends_at = getTimeFromDate(changeInfo.event.end);
    };

    const rescheduleBookingByCalendarChange = (changeInfo) => {
        const booking = changeInfo.event.extendedProps.booking;

        if (!booking) {
            changeInfo.revert();

            return;
        }

        router.post(route('branches.booking.bookings.reschedule', [props.branch.id, booking.id]), {
            service_id: booking.service_id,
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: toLocalDateTimeString(changeInfo.event.end),
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: true,
            notification_reason: 'Termín rezervácie bol presunutý.',
        }, {
            preserveScroll: true,
            preserveState: false,
            onError: () => {
                changeInfo.revert();
            },
        });
    };

    const rescheduleCapacityWindowByCalendarChange = (changeInfo) => {
        const capacityWindow = changeInfo.event.extendedProps.capacityWindow;

        if (!capacityWindow) {
            changeInfo.revert();

            return;
        }

        router.post(route('branches.booking.capacity-windows.reschedule', [props.branch.id, capacityWindow.rule_id]), {
            date: capacityWindow.date,
            starts_at: toLocalDateTimeString(changeInfo.event.start),
            ends_at: toLocalDateTimeString(changeInfo.event.end),
            notify_patient: true,
            notification_reason: 'Termín skupinovej rezervácie bol presunutý.',
        }, {
            preserveScroll: true,
            preserveState: false,
            onError: () => {
                changeInfo.revert();
            },
        });
    };

    const handleEventDropOrResize = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type === 'rule') {
            updateRuleFromDrop(changeInfo);

            return;
        }

        if (type === 'booking') {
            rescheduleBookingByCalendarChange(changeInfo);

            return;
        }

        if (type === 'capacity_window') {
            rescheduleCapacityWindowByCalendarChange(changeInfo);
        }
    };

    const deleteCurrentRule = () => {
        if (selectedRuleIndex.value === null) {
            return;
        }

        ruleForm.rules.splice(selectedRuleIndex.value, 1);

        selectedRuleIndex.value = null;
        selectedRuleOccurrence.value = null;
        ruleDialogVisible.value = false;
    };

    const closeRuleDialog = () => {
        selectedRuleIndex.value = null;
        selectedRuleOccurrence.value = null;
        ruleDialogVisible.value = false;
    };

    const saveRules = () => {
        ruleForm
            .transform((data) => ({
                rules: data.rules.map((rule) => ({
                    id: rule.id,

                    date: rule.date,
                    starts_at: rule.starts_at,
                    ends_at: rule.ends_at,

                    slot_mode: rule.slot_mode,

                    service_id: rule.slot_mode === 'single_service_many_clients'
                        ? rule.service_id
                        : null,

                    service_ids: getRuleServiceIds(rule),

                    bookable_places: rule.slot_mode === 'single_service_many_clients'
                        ? rule.bookable_places
                        : 1,

                    repeats: rule.repeats,
                    repeat_every: rule.repeats ? rule.repeat_every : 1,
                    repeat_unit: rule.repeats ? rule.repeat_unit : 'weeks',
                    repeat_ends_on: rule.repeat_ends_on ?? null,
                    excluded_dates: rule.excluded_dates ?? [],

                    is_enabled: rule.is_enabled,
                })),
            }))
            .put(route('branches.booking.rules.update', props.branch.id), {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    closeRuleDialog();
                },
            });
    };

    const availableSlotsForBooking = (booking) => {
        if (!booking) {
            return [];
        }

        return (props.availableRescheduleSlots ?? [])
            .filter((slot) => {
                return Number(slot.service_id) === Number(booking.service_id)
                    && Number(slot.id) !== Number(booking.booking_slot_id);
            });
    };

    const updateBooking = (booking, status, options = {}) => {
        router.put(route('branches.booking.bookings.update', [props.branch.id, booking.id]), {
            status,
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(options.notify_patient ?? false),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                bookingDialogVisible.value = false;
                capacityWindowDialogVisible.value = false;
            },
        });
    };

    const cancelBooking = (booking, options = {}) => {
        router.post(route('branches.booking.bookings.cancel', [props.branch.id, booking.id]), {
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                bookingDialogVisible.value = false;
                capacityWindowDialogVisible.value = false;
            },
        });
    };

    const rescheduleBooking = (booking, data = {}) => {
        router.post(route('branches.booking.bookings.reschedule', [props.branch.id, booking.id]), {
            booking_slot_id: data.booking_slot_id ?? null,
            service_id: data.service_id ?? booking.service_id,
            starts_at: data.starts_at ?? null,
            ends_at: data.ends_at ?? null,
            admin_note: bookingNotes.value[booking.id] ?? '',
            notify_patient: Boolean(data.notify_patient ?? true),
            notification_reason: data.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                bookingDialogVisible.value = false;
                capacityWindowDialogVisible.value = false;
            },
        });
    };

    const cancelCapacityWindow = (capacityWindow, options = {}) => {
        router.post(route('branches.booking.capacity-windows.cancel', [props.branch.id, capacityWindow.rule_id]), {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                capacityWindowDialogVisible.value = false;
                selectedCapacityWindow.value = null;
            },
        });
    };

    const rescheduleCapacityWindow = (capacityWindow, data = {}) => {
        router.post(route('branches.booking.capacity-windows.reschedule', [props.branch.id, capacityWindow.rule_id]), {
            date: data.date ?? capacityWindow.date,
            starts_at: data.starts_at,
            ends_at: data.ends_at,
            notify_patient: Boolean(data.notify_patient ?? true),
            notification_reason: data.notification_reason ?? null,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                capacityWindowDialogVisible.value = false;
                selectedCapacityWindow.value = null;
            },
        });
    };

    const createAdminBooking = (data = {}) => {
        router.post(route('branches.booking.bookings.store', props.branch.id), {
            booking_slot_id: data.booking_slot_id ?? null,
            service_id: data.service_id ?? null,
            starts_at: data.starts_at ?? null,
            ends_at: data.ends_at ?? null,
            patient_name: data.patient_name,
            patient_email: data.patient_email,
            patient_phone: data.patient_phone,
            patient_note: data.patient_note,
            admin_note: data.admin_note,
            notify_patient: Boolean(data.notify_patient ?? true),
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                closeCreateBookingDialog();
            },
        });
    };

    const setEventLayer = (mountInfo) => {
        const type = mountInfo.event.extendedProps.type;
        const harness = mountInfo.el.closest('.fc-timegrid-event-harness');

        if (!harness) {
            return;
        }

        if (type === 'booking') {
            harness.style.zIndex = '30';
            mountInfo.el.style.zIndex = '30';
            mountInfo.el.style.cursor = 'grab';

            return;
        }

        if (type === 'capacity_window') {
            harness.style.zIndex = '20';
            mountInfo.el.style.zIndex = '20';
            mountInfo.el.style.cursor = 'grab';

            return;
        }

        if (type === 'rule') {
            harness.style.zIndex = '1';
            mountInfo.el.style.zIndex = '1';
            mountInfo.el.style.cursor = 'pointer';
        }
    };

    const deleteCurrentRuleOccurrence = () => {
        if (!selectedRuleOccurrence.value) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleOccurrence.value.ruleIndex];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.post(route('branches.booking.rules.exclude-date', [props.branch.id, rule.id]), {
            date: selectedRuleOccurrence.value.occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                deleteRuleDialogVisible.value = false;
                ruleDialogVisible.value = false;
                selectedRuleOccurrence.value = null;
                selectedRuleIndex.value = null;
            },
        });
    };

    const deleteCurrentRuleFromNowOn = () => {
        if (!selectedRuleOccurrence.value) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleOccurrence.value.ruleIndex];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.post(route('branches.booking.rules.end-before-date', [props.branch.id, rule.id]), {
            date: selectedRuleOccurrence.value.occurrenceDate,
        }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                deleteRuleDialogVisible.value = false;
                ruleDialogVisible.value = false;
                selectedRuleOccurrence.value = null;
                selectedRuleIndex.value = null;
            },
        });
    };

    const deleteCurrentRuleEverywhere = () => {
        if (selectedRuleIndex.value === null) {
            return;
        }

        const rule = ruleForm.rules[selectedRuleIndex.value];

        if (!rule?.id) {
            deleteCurrentRule();

            return;
        }

        router.delete(route('branches.booking.rules.destroy', [props.branch.id, rule.id]), {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                deleteRuleDialogVisible.value = false;
                ruleDialogVisible.value = false;
                selectedRuleOccurrence.value = null;
                selectedRuleIndex.value = null;
            },
        });
    };

    const openDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = true;
    };

    const closeDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = false;
    };

    const calendarOptions = computed(() => {
        const branchHours = getBranchOpeningHoursForCalendar();

        return {
            plugins: [
                timeGridPlugin,
                interactionPlugin,
            ],

            initialView: 'timeGridWeek',
            initialDate: new Date().toISOString().slice(0, 10),
            firstDay: 1,

            allDaySlot: false,
            selectable: true,
            editable: true,
            eventResizableFromStart: true,

            slotMinTime: branchHours.min,
            slotMaxTime: branchHours.max,
            slotDuration: '00:30:00',
            snapDuration: '00:15:00',

            businessHours: getBusinessHours(),
            selectConstraint: 'businessHours',
            selectAllow: isSelectionInsideOpeningHours,
            eventAllow: isEventAllowed,

            eventDidMount: setEventLayer,
            eventOrderStrict: true,
            eventOrder: (firstEvent, secondEvent) => {
                const order = {
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

            events: calendarEvents.value,
            select: openCreateRuleDialog,
            eventClick: openEventDialog,
            eventDrop: handleEventDropOrResize,
            eventResize: handleEventDropOrResize,
        };
    });

    const deleteCapacityWindowOccurrence = (capacityWindow, options = {}) => {
    router.post(route('branches.booking.capacity-windows.delete-occurrence', [props.branch.id, capacityWindow.rule_id]), {
        date: options.date ?? capacityWindow.date,
        notify_patient: Boolean(options.notify_patient ?? true),
        notification_reason: options.notification_reason ?? null,
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            capacityWindowDialogVisible.value = false;
            selectedCapacityWindow.value = null;
        },
    });
};

const deleteCapacityWindowFromDate = (capacityWindow, options = {}) => {
    router.post(route('branches.booking.capacity-windows.delete-from-date', [props.branch.id, capacityWindow.rule_id]), {
        date: options.date ?? capacityWindow.date,
        notify_patient: Boolean(options.notify_patient ?? true),
        notification_reason: options.notification_reason ?? null,
    }, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            capacityWindowDialogVisible.value = false;
            selectedCapacityWindow.value = null;
        },
    });
};

const deleteCapacityWindowSeries = (capacityWindow, options = {}) => {
    router.delete(route('branches.booking.capacity-windows.delete-series', [props.branch.id, capacityWindow.rule_id]), {
        data: {
            date: options.date ?? capacityWindow.date,
            notify_patient: Boolean(options.notify_patient ?? true),
            notification_reason: options.notification_reason ?? null,
        },
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            capacityWindowDialogVisible.value = false;
            selectedCapacityWindow.value = null;
        },
    });
};

    return {
        showAvailabilityRules,
        showReservations,

        ruleDialogVisible,
        bookingDialogVisible,
        capacityWindowDialogVisible,
        createBookingDialogVisible,
        deleteRuleDialogVisible,

        selectedBooking,
        selectedCapacityWindow,
        selectedRuleOccurrence,

        ruleForm,
        currentRule,

        slotModeOptions,
        repeatUnitOptions,

        bookingNotes,
        calendarOptions,

        getRuleTitle,
        getRepeatLabel,
        availableSlotsForBooking,

        openCreateBookingDialog,
        closeCreateBookingDialog,

        closeRuleDialog,
        deleteCurrentRule,
        saveRules,

        createAdminBooking,
        updateBooking,
        cancelBooking,
        rescheduleBooking,
        cancelCapacityWindow,
        rescheduleCapacityWindow,

        openDeleteRuleDialog,
        closeDeleteRuleDialog,
        deleteCurrentRuleOccurrence,
        deleteCurrentRuleFromNowOn,
        deleteCurrentRuleEverywhere,

        deleteCapacityWindowOccurrence,
        deleteCapacityWindowFromDate,
        deleteCapacityWindowSeries,
    };
}