<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

import BookingDialog from '@/Components/Booking/BookingDialog.vue';
import EventCreateEditHubDialog from '@/Components/Booking/EventCreateEditHubDialog.vue';
import AvailabilityRuleDialog from '@/Components/Booking/AvailabilityRuleDialog.vue';
import GroupEventDialog from '@/Components/Booking/GroupEventDialog.vue';
import ConfirmDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import OccurrenceScopeDialog from '@/Components/Booking/Common/OccurrenceScopeDialog.vue';

import { useBookingCalendar } from '@/Composables/Bookings/useBookingCalendar';

import FullCalendar from '@fullcalendar/vue3';
import { Draggable } from '@fullcalendar/interaction';

import { router } from '@inertiajs/vue3';
import { useBranchBroadcasting } from '@/Composables/useBranchBroadcasting';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';

import { computed, nextTick, onBeforeUnmount, onMounted, ref, unref } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    services: {
        type: Array,
        default: () => [],
    },
    patients: {
        type: Array,
        default: () => [],
    },
    availabilityRules: {
        type: Array,
        default: () => [],
    },
    availableRescheduleSlots: {
        type: Array,
        default: () => [],
    },
    calendarBookings: {
        type: Array,
        default: () => [],
    },
    calendarCapacityWindows: {
        type: Array,
        default: () => [],
    },
    disabledDays: {
        type: Array,
        default: () => [],
    },
    pendingAppointmentRequests: {
        type: Array,
        default: () => [],
    },
    todayBookingsCount: {
        type: Number,
        default: 0,
    },
    unreadMessagesCount: {
        type: Number,
        default: 0,
    },
});

const formatDateOnly = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const disabledDayByDate = computed(() => {
    return new Map((props.disabledDays ?? []).map((disabledDay) => [
        String(disabledDay.date).slice(0, 10),
        disabledDay,
    ]));
});

const isDateDisabled = (date) => {
    const dateOnly = formatDateOnly(date);

    if (!dateOnly) {
        return false;
    }

    return disabledDayByDate.value.has(dateOnly);
};

const getBranchOpeningHours = () => {
    return props.branch?.opening_hours ?? props.branch?.openingHours ?? [];
};

const getDatabaseDayFromDate = (date) => {
    const day = date.getDay();

    return day === 0 ? 7 : day;
};

const isDateClosedByOpeningHours = (date) => {
    const normalizedDate = date instanceof Date ? date : new Date(date);

    if (Number.isNaN(normalizedDate.getTime())) {
        return false;
    }

    const databaseDay = getDatabaseDayFromDate(normalizedDate);
    const openingDay = getBranchOpeningHours().find((day) => {
        return Number(day.day_of_week) === databaseDay;
    });

    if (!openingDay || openingDay.is_closed) {
        return true;
    }

    return !(openingDay.intervals ?? []).length;
};

const toggleDisabledDayByDate = (date, checked, callbacks = {}) => {
    if (isDateClosedByOpeningHours(date)) {
        return;
    }

    const dateOnly = formatDateOnly(date);

    if (!dateOnly) {
        return;
    }

    const existing = disabledDayByDate.value.get(dateOnly);

    if (checked) {
        if (existing) {
            return;
        }

        router.post(route('branches.booking.disabled-days.store', props.branch.id), {
            date: dateOnly,
            title: 'Zatvorený deň',
            type: 'closed',
            reason: null,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                callbacks.onSuccess?.();
                reloadCalendarData();
            },
            onError: () => {
                callbacks.onError?.();
            },
        });

        return;
    }

    if (!existing?.id) {
        return;
    }

    router.delete(route('branches.booking.disabled-days.destroy', [
        props.branch.id,
        existing.id,
    ]), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            callbacks.onSuccess?.();
            reloadCalendarData();
        },
        onError: () => {
            callbacks.onError?.();
        },
    });
};

const {
    showAvailabilityRules,
    showReservations,
    showGroupEvents,

    createBookingDialogVisible,
    bookingDialogVisible,

    availabilityRuleDialogVisible,
    ruleRescheduleScopeDialogVisible,
    bookingRescheduleScopeDialogVisible,
    capacityWindowRescheduleScopeDialogVisible,
    groupEventDialogVisible,
    groupEventOccurrenceDialogVisible,

    selectedBooking,
    selectedCapacityWindow,
    selectedGroupEvent,
    selectedRuleOccurrence,
    pendingCalendarSelection,
    createBookingPrefill,

    ruleForm,
    currentRule,

    repeatUnitOptions,

    bookingNotes,
    calendarOptions,
    currentCalendarRange,

    availableSlotsForBooking,

    openCreateChoiceFromButton,
    closeCreateBookingDialog,

    continueFromCreateChoice,
    openBookingInUnifiedEditor,
    openRuleInUnifiedEditor,

    closeRuleDialogSafely,
    saveRules,
    deleteCurrentRuleByScope,
    duplicateCurrentRule,

    closeGroupEventDialog,
    saveCapacityWindow,

    cancelBooking,
    duplicateBooking,
    submitPendingBookingRescheduleScope,
    cancelPendingBookingReschedule,

    cancelCapacityWindow,
    rescheduleCapacityWindow,
    submitPendingCapacityWindowRescheduleScope,
    cancelPendingCapacityWindowReschedule,
    duplicateCapacityWindow,

    deleteCapacityWindowOccurrence,
    deleteCapacityWindowFromDate,
    deleteCapacityWindowSeries,
    addPatientToCapacityWindow,

    openCapacityWindowEditor,
} = useBookingCalendar(props, {
    isDateDisabled,
    isDateClosedByOpeningHours,
    toggleDisabledDayByDate,
});

const bookingCalendar = ref(null);
const fullCalendar = ref(null);
const requestSidebar = ref(null);
const requestSidebarHeight = ref(null);

let pendingCalendarResizeFrame = null;
let requestDraggable = null;
let calendarResizeObserver = null;

const sidePanelMode = ref('requests');
const reservationSearch = ref('');

const highlightedReservationEventId = ref(null);
let reservationHighlightCleanupTimer = null;
let reservationHighlightRetryTimer = null;
let highlightedReservationCalendarEvent = null;
let highlightedReservationOriginalClassNames = [];
let requestSearchHighlightCleanupTimer = null;

const sidePanelModes = [
    {
        key: 'requests',
        label: 'Žiadosti',
        icon: 'pi pi-inbox',
    },
    {
        key: 'reservations',
        label: 'Rezervácie',
        icon: 'pi pi-search',
    },
];

const periodLabels = {
    morning: 'Ráno',
    forenoon: 'Dopoludnia',
    afternoon: 'Popoludní',
    evening: 'Večer',
    rano: 'Ráno',
    dopoludnia: 'Dopoludnia',
    popoludni: 'Popoludní',
    vecer: 'Večer',
};

const pendingRequests = computed(() => props.pendingAppointmentRequests ?? []);

const getBufferedReloadRange = () => {
    const visibleStart = currentCalendarRange.value?.start
        ? new Date(currentCalendarRange.value.start)
        : new Date();
    const visibleEnd = currentCalendarRange.value?.end
        ? new Date(currentCalendarRange.value.end)
        : new Date();

    visibleStart.setDate(visibleStart.getDate() - 31);
    visibleEnd.setMonth(visibleEnd.getMonth() + 6);

    return {
        start: Number.isNaN(visibleStart.getTime()) ? null : visibleStart,
        end: Number.isNaN(visibleEnd.getTime()) ? null : visibleEnd,
    };
};

const reloadCalendarData = () => {
    const range = getBufferedReloadRange();

    router.reload({
        data: {
            start: range.start?.toISOString?.(),
            end: range.end?.toISOString?.(),
        },
        only: [
            'availabilityRules',
            'calendarBookings',
            'calendarCapacityWindows',
            'disabledDays',
            'pendingAppointmentRequests',
            'todayBookingsCount',
            'unreadMessagesCount',
            'patients',
        ],
        preserveState: true,
        preserveScroll: true,
    });
};

useBranchBroadcasting(props.branch.id, reloadCalendarData);

const requestToCancel = ref(null);

const normalizeSearchValue = (value) => {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
};

const getBookingStartValue = (booking) => {
    return booking?.starts_datetime
        ?? booking?.starts_at
        ?? booking?.start
        ?? null;
};

const getBookingEndValue = (booking) => {
    return booking?.ends_datetime
        ?? booking?.ends_at
        ?? booking?.end
        ?? null;
};

const getBookingServiceLabel = (booking) => {
    const services = booking?.services ?? [];

    if (services.length) {
        return services
            .map((service) => service?.name)
            .filter(Boolean)
            .join(', ');
    }

    return booking?.service_name
        ?? booking?.service?.name
        ?? 'Bez služby';
};

const getCapacityWindowEventId = (capacityWindow) => {
    const capacityWindowId = capacityWindow?.id
        ?? capacityWindow?.capacity_window_id
        ?? capacityWindow?.window_id
        ?? null;

    if (!capacityWindowId) {
        return null;
    }

    return `capacity-window-${capacityWindowId}`;
};

const getCapacityWindowEventIdCandidates = (capacityWindow) => {
    const capacityWindowId = capacityWindow?.id
        ?? capacityWindow?.capacity_window_id
        ?? capacityWindow?.window_id
        ?? null;
    const capacityWindowEventId = getCapacityWindowEventId(capacityWindow);

    return [
        capacityWindowEventId,
        capacityWindowId,
    ].filter(Boolean).map(String);
};

const getBookingContactLabel = (booking) => {
    return booking?.patient_phone
        ?? booking?.patient_email
        ?? '';
};

const getBookingEventIdCandidates = (booking) => {
    return [
        booking?.calendar_event_id,
        booking?.event_id,
        booking?.fullcalendar_id,
        booking?.uuid,
        booking?.id ? `booking-${booking.id}` : null,
        booking?.id ? String(booking.id) : null,
    ].filter(Boolean);
};

const hasReservationSearch = computed(() => {
    return normalizeSearchValue(reservationSearch.value).length > 0;
});

const reservationResults = computed(() => {
    const query = normalizeSearchValue(reservationSearch.value);

    if (!query) {
        return [];
    }

    return [...(props.calendarBookings ?? [])]
        .sort((first, second) => {
            const firstStart = new Date(getBookingStartValue(first) ?? 0).getTime();
            const secondStart = new Date(getBookingStartValue(second) ?? 0).getTime();

            if (Number.isNaN(firstStart) && Number.isNaN(secondStart)) {
                return 0;
            }

            if (Number.isNaN(firstStart)) {
                return 1;
            }

            if (Number.isNaN(secondStart)) {
                return -1;
            }

            return firstStart - secondStart;
        })
        .filter((booking) => {
            const searchable = [
                booking?.patient_name,
                booking?.patient_email,
                booking?.patient_phone,
                booking?.service_name,
                booking?.service?.name,
                getBookingServiceLabel(booking),
                formatDateTime(getBookingStartValue(booking)),
            ].map(normalizeSearchValue);

            return searchable.some((value) => value.includes(query));
        })
        .slice(0, 30);
});

const requestSearchResults = computed(() => {
    const query = normalizeSearchValue(reservationSearch.value);

    if (!query) {
        return [];
    }

    return pendingRequests.value
        .filter((request) => {
            const searchable = [
                request?.patient_name,
                request?.patient_email,
                request?.patient_phone,
                getRequestServicesLabel(request),
                formatDate(getRequestPreferredDate(request)),
            ].map(normalizeSearchValue);

            return searchable.some((value) => value.includes(query));
        })
        .slice(0, 30)
        .map((request) => {
            return {
                key: `request-${request.id}`,
                type: 'request',
                tagLabel: 'Žiadosť',
                patientName: request.patient_name ?? 'Žiadosť',
                contactLabel: request.patient_phone ?? request.patient_email ?? '',
                serviceLabel: getRequestServicesLabel(request),
                dateLabel: `${formatDate(getRequestPreferredDate(request))} · ${getRequestPeriodLabel(request)}`,
                request,
                startsAt: getRequestPreferredDate(request),
            };
        });
});

const groupReservationResults = computed(() => {
    const query = normalizeSearchValue(reservationSearch.value);

    if (!query) {
        return [];
    }

    return (props.calendarCapacityWindows ?? [])
        .flatMap((capacityWindow) => {
            const bookings = Array.isArray(capacityWindow?.bookings)
                ? capacityWindow.bookings
                : [];

            return bookings.map((booking) => {
                return {
                    booking: {
                        ...booking,
                        starts_at: booking?.starts_at ?? capacityWindow?.starts_at,
                        starts_datetime: booking?.starts_datetime ?? capacityWindow?.starts_datetime,
                        ends_at: booking?.ends_at ?? capacityWindow?.ends_at,
                        ends_datetime: booking?.ends_datetime ?? capacityWindow?.ends_datetime,
                        service_name: booking?.service_name ?? capacityWindow?.service_name ?? capacityWindow?.service?.name,
                    },
                    capacityWindow,
                };
            });
        })
        .filter(({ booking }) => {
            const searchable = [
                booking?.patient_name,
                booking?.patient_email,
                booking?.patient_phone,
                booking?.service_name,
                formatDateTime(getBookingStartValue(booking)),
            ].map(normalizeSearchValue);

            return searchable.some((value) => value.includes(query));
        })
        .slice(0, 30)
        .map(({ booking, capacityWindow }) => {
            return {
                key: `group-booking-${booking?.id ?? `${capacityWindow?.id}-unknown`}`,
                type: 'group_reservation',
                tagLabel: 'Skupinová rezervácia',
                patientName: booking?.patient_name ?? 'Skupinová rezervácia',
                contactLabel: getBookingContactLabel(booking),
                serviceLabel: getBookingServiceLabel(booking),
                dateLabel: formatDateTime(getBookingStartValue(booking)),
                timeLabel: formatTimeRange(getBookingStartValue(booking), getBookingEndValue(booking)),
                startsAt: getBookingStartValue(booking),
                eventIdCandidates: [
                    ...getBookingEventIdCandidates(booking),
                    ...getCapacityWindowEventIdCandidates(capacityWindow),
                ],
                booking,
                capacityWindow,
            };
        });
});

const directReservationResults = computed(() => {
    return reservationResults.value
        .filter((booking) => !booking?.capacity_window_id)
        .map((booking) => {
            return {
                key: `booking-${booking.id}`,
                type: 'reservation',
                tagLabel: 'Rezervácia',
                patientName: booking.patient_name ?? 'Rezervácia',
                contactLabel: getBookingContactLabel(booking),
                serviceLabel: getBookingServiceLabel(booking),
                dateLabel: formatDateTime(getBookingStartValue(booking)),
                timeLabel: formatTimeRange(getBookingStartValue(booking), getBookingEndValue(booking)),
                startsAt: getBookingStartValue(booking),
                eventIdCandidates: getBookingEventIdCandidates(booking),
                booking,
            };
        });
});

const mixedSearchResults = computed(() => {
    if (!hasReservationSearch.value) {
        return [];
    }

    return [
        ...directReservationResults.value,
        ...groupReservationResults.value,
        ...requestSearchResults.value,
    ]
        .sort((first, second) => {
            const firstStart = new Date(first.startsAt ?? 0).getTime();
            const secondStart = new Date(second.startsAt ?? 0).getTime();

            if (Number.isNaN(firstStart) && Number.isNaN(secondStart)) {
                return 0;
            }

            if (Number.isNaN(firstStart)) {
                return 1;
            }

            if (Number.isNaN(secondStart)) {
                return -1;
            }

            return firstStart - secondStart;
        })
        .slice(0, 50);
});

const getSearchResultTagClass = (type) => {
    if (type === 'group_reservation') {
        return 'bg-amber-100 text-amber-800';
    }

    if (type === 'request') {
        return 'bg-blue-100 text-blue-800';
    }

    return 'bg-rose-100 text-rose-800';
};

const highlightRequestCard = (requestId) => {
    if (!requestSidebar.value || !requestId) {
        return;
    }

    const target = requestSidebar.value.querySelector(`[data-request-id="${requestId}"]`);

    if (!target) {
        return;
    }

    target.classList.add('request-search-spotlight');
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });

    if (requestSearchHighlightCleanupTimer) {
        clearTimeout(requestSearchHighlightCleanupTimer);
    }

    requestSearchHighlightCleanupTimer = setTimeout(() => {
        target.classList.remove('request-search-spotlight');
        requestSearchHighlightCleanupTimer = null;
    }, 2500);
};

const clearReservationHighlight = () => {
    if (reservationHighlightRetryTimer) {
        clearTimeout(reservationHighlightRetryTimer);
        reservationHighlightRetryTimer = null;
    }

    if (bookingCalendar.value) {
        const highlightedElements = bookingCalendar.value.querySelectorAll('.booking-reservation-spotlight');

        highlightedElements.forEach((element) => {
            element.classList.remove('booking-reservation-spotlight');
        });
    }

    if (highlightedReservationCalendarEvent) {
        highlightedReservationCalendarEvent.setProp('classNames', highlightedReservationOriginalClassNames);
    }

    highlightedReservationCalendarEvent = null;
    highlightedReservationOriginalClassNames = [];
    highlightedReservationEventId.value = null;
};

const getCalendarEventByCandidates = (eventIdCandidates) => {
    const calendarApi = fullCalendar.value?.getApi?.();

    if (!calendarApi) {
        return null;
    }

    return eventIdCandidates
        .map((eventId) => calendarApi.getEventById(String(eventId)))
        .find(Boolean) ?? null;
};

const getEventElementsByEventIdCandidates = (eventIdCandidates) => {
    if (!bookingCalendar.value || !eventIdCandidates.length) {
        return [];
    }

    const normalizedCandidates = eventIdCandidates.map(String);
    const allEventElements = Array.from(bookingCalendar.value.querySelectorAll('.fc-event[data-event-id]'));

    return allEventElements.filter((element) => {
        return normalizedCandidates.includes(String(element.getAttribute('data-event-id')));
    });
};

const scrollHighlightedReservationIntoView = (eventIdCandidates = []) => {
    if (!bookingCalendar.value) {
        return;
    }

    const eventElements = [
        ...Array.from(bookingCalendar.value.querySelectorAll('.booking-reservation-spotlight')),
        ...getEventElementsByEventIdCandidates(eventIdCandidates),
    ];

    const uniqueElements = [...new Set(eventElements)];

    uniqueElements.forEach((element) => {
        element.scrollIntoView({
            block: 'center',
            inline: 'center',
            behavior: 'smooth',
        });
    });
};

const applyReservationHighlight = (eventIdCandidates, retriesLeft = 10) => {
    const normalizedCandidates = eventIdCandidates.map(String).filter(Boolean);

    if (!normalizedCandidates.length) {
        return;
    }

    const calendarEvent = getCalendarEventByCandidates(normalizedCandidates);
    const eventElements = getEventElementsByEventIdCandidates(normalizedCandidates);

    if (!calendarEvent && !eventElements.length) {
        if (retriesLeft <= 0) {
            return;
        }

        reservationHighlightRetryTimer = setTimeout(() => {
            applyReservationHighlight(normalizedCandidates, retriesLeft - 1);
        }, 140);

        return;
    }

    clearReservationHighlight();

    if (calendarEvent) {
        highlightedReservationCalendarEvent = calendarEvent;
        highlightedReservationOriginalClassNames = [...(calendarEvent.classNames ?? [])];

        calendarEvent.setProp('classNames', [
            ...new Set([
                ...highlightedReservationOriginalClassNames,
                'booking-reservation-spotlight',
            ]),
        ]);

        highlightedReservationEventId.value = calendarEvent.id;
    }

    eventElements.forEach((element) => {
        element.classList.add('booking-reservation-spotlight');
    });

    nextTick(() => {
        requestAnimationFrame(() => {
            scrollHighlightedReservationIntoView(normalizedCandidates);
        });
    });

    if (reservationHighlightCleanupTimer) {
        clearTimeout(reservationHighlightCleanupTimer);
    }

    reservationHighlightCleanupTimer = setTimeout(() => {
        clearReservationHighlight();
        reservationHighlightCleanupTimer = null;
    }, 4500);
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Bez termínu';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Bez termínu';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}.${month}.${year} ${hours}:${minutes}`;
};

const formatTimeRange = (startsAt, endsAt) => {
    const startDate = startsAt ? new Date(startsAt) : null;
    const endDate = endsAt ? new Date(endsAt) : null;

    if (!startDate || Number.isNaN(startDate.getTime())) {
        return 'Bez času';
    }

    const startHours = String(startDate.getHours()).padStart(2, '0');
    const startMinutes = String(startDate.getMinutes()).padStart(2, '0');

    if (!endDate || Number.isNaN(endDate.getTime())) {
        return `${startHours}:${startMinutes}`;
    }

    const endHours = String(endDate.getHours()).padStart(2, '0');
    const endMinutes = String(endDate.getMinutes()).padStart(2, '0');

    return `${startHours}:${startMinutes} – ${endHours}:${endMinutes}`;
};

const openReservationFromSearch = (booking, preferredEventIdCandidates = null) => {
    if (!booking) {
        return;
    }

    const calendarApi = fullCalendar.value?.getApi?.();
    const bookingStart = getBookingStartValue(booking);
    const bookingStartDate = bookingStart ? new Date(bookingStart) : null;
    const eventIdCandidates = Array.isArray(preferredEventIdCandidates) && preferredEventIdCandidates.length
        ? preferredEventIdCandidates
        : getBookingEventIdCandidates(booking);

    showReservations.value = true;

    if (calendarApi && bookingStartDate && !Number.isNaN(bookingStartDate.getTime())) {
        calendarApi.changeView('timeGridWeek', bookingStartDate);
    }

    nextTick(() => {
        requestAnimationFrame(() => {
            applyReservationHighlight(eventIdCandidates);
        });
    });
};

const openSearchResult = (result) => {
    if (!result) {
        return;
    }

    if (result.type === 'request') {
        sidePanelMode.value = 'requests';

        nextTick(() => {
            requestAnimationFrame(() => {
                highlightRequestCard(result.request?.id);
            });
        });

        return;
    }

    if (result.booking) {
        openReservationFromSearch(result.booking, result.eventIdCandidates ?? null);

        return;
    }

    const calendarApi = fullCalendar.value?.getApi?.();
    const startDate = result.startsAt ? new Date(result.startsAt) : null;

    showReservations.value = true;

    if (calendarApi && startDate && !Number.isNaN(startDate.getTime())) {
        calendarApi.changeView('timeGridWeek', startDate);
    }

    if (result.eventIdCandidates?.length) {
        nextTick(() => {
            requestAnimationFrame(() => {
                applyReservationHighlight(result.eventIdCandidates);
            });
        });
    }
};

const requestCancelDialogVisible = computed(() => {
    return Boolean(requestToCancel.value);
});

const requestCancelDialogMessage = computed(() => {
    if (!requestToCancel.value) {
        return '';
    }

    return `Naozaj chcete zrušiť žiadosť pacienta ${requestToCancel.value.patient_name}?`;
});

const updateRequestSidebarHeight = () => {
    if (!bookingCalendar.value) {
        return;
    }

    const calendarElement = bookingCalendar.value;

    requestSidebarHeight.value = Math.round(calendarElement.getBoundingClientRect().height);
};

const updateCalendarWidth = () => {
    if (pendingCalendarResizeFrame !== null) {
        cancelAnimationFrame(pendingCalendarResizeFrame);
    }

    pendingCalendarResizeFrame = requestAnimationFrame(() => {
        pendingCalendarResizeFrame = null;
        fullCalendar.value?.getApi?.()?.updateSize?.();
    });
};

const openCancelAppointmentRequestDialog = (request) => {
    requestToCancel.value = request;
};

const closeCancelAppointmentRequestDialog = () => {
    requestToCancel.value = null;
};

const confirmCancelAppointmentRequest = () => {
    if (!requestToCancel.value) {
        return;
    }

    router.delete(route('branches.booking.appointment-requests.destroy', [
        props.branch.id,
        requestToCancel.value.id,
    ]), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            closeCancelAppointmentRequestDialog();
        },
    });
};

const formatDate = (value) => {
    if (!value) {
        return 'Bez dátumu';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Bez dátumu';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
};

const getRequestPreferredDate = (request) => {
    return request.preferred_date
        ?? request.preferredDate
        ?? request.requested_date
        ?? request.date
        ?? request.preferred_dates?.[0]?.date
        ?? null;
};

const getRequestPeriodLabel = (request) => {
    return periodLabels[request.preferred_period] ?? request.preferred_period ?? 'Bez časti dňa';
};

const getRequestServicesLabel = (request) => {
    const services = request.services ?? [];

    if (!services.length) {
        return 'Bez služby';
    }

    return services
        .map((service) => service.name)
        .join(', ');
};

const calendarOptionsWithReservationHighlight = computed(() => {
    const resolvedCalendarOptions = unref(calendarOptions) ?? {};
    const originalEventDidMount = resolvedCalendarOptions.eventDidMount;

    return {
        ...resolvedCalendarOptions,
        eventDidMount: (info) => {
            info.el.dataset.eventId = info.event.id;

            if (info.event.extendedProps?.booking?.id) {
                info.el.dataset.bookingId = String(info.event.extendedProps.booking.id);
            }

            originalEventDidMount?.(info);
        },
    };
});

onMounted(() => {
    if (requestSidebar.value) {
        requestDraggable = new Draggable(requestSidebar.value, {
            itemSelector: '.appointment-request-card',
            eventData: (eventElement) => {
                const requestId = Number(eventElement.dataset.requestId);

                const appointmentRequest = pendingRequests.value.find((request) => {
                    return Number(request.id) === requestId;
                });

                if (!appointmentRequest) {
                    return null;
                }

                return {
                    id: `appointment-request-${appointmentRequest.id}`,
                    title: `${appointmentRequest.patient_name} · ${appointmentRequest.total_duration_minutes} min`,
                    duration: {
                        minutes: Number(appointmentRequest.total_duration_minutes || 30),
                    },
                    classNames: [
                        'booking-request-preview-event',
                    ],
                    extendedProps: {
                        type: 'appointment_request',
                        appointmentRequest,
                    },
                };
            },
        });
    }

    nextTick(() => {
        updateRequestSidebarHeight();
        updateCalendarWidth();

        if (bookingCalendar.value) {
            calendarResizeObserver = new ResizeObserver(() => {
                updateRequestSidebarHeight();
                updateCalendarWidth();
            });

            calendarResizeObserver.observe(bookingCalendar.value);
        }
    });

    window.addEventListener('resize', updateRequestSidebarHeight);
    window.addEventListener('resize', updateCalendarWidth);
});

onBeforeUnmount(() => {
    requestDraggable?.destroy();
    calendarResizeObserver?.disconnect();

    if (reservationHighlightRetryTimer) {
        clearTimeout(reservationHighlightRetryTimer);
        reservationHighlightRetryTimer = null;
    }

    if (reservationHighlightCleanupTimer) {
        clearTimeout(reservationHighlightCleanupTimer);
        reservationHighlightCleanupTimer = null;
    }

    if (requestSearchHighlightCleanupTimer) {
        clearTimeout(requestSearchHighlightCleanupTimer);
        requestSearchHighlightCleanupTimer = null;
    }

    clearReservationHighlight();

    window.removeEventListener('resize', updateRequestSidebarHeight);
    window.removeEventListener('resize', updateCalendarWidth);

    if (pendingCalendarResizeFrame !== null) {
        cancelAnimationFrame(pendingCalendarResizeFrame);
        pendingCalendarResizeFrame = null;
    }
});
</script>

<template>
    <AdminLayout>
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div
                    ref="bookingCalendar"
                    class="booking-calendar flex min-w-0 flex-col gap-4"
                >
                    <FullCalendar
                        ref="fullCalendar"
                        :options="calendarOptionsWithReservationHighlight"
                    />

                    <div class="flex flex-wrap gap-4">
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm text-dark">
                                <ToggleSwitch v-model="showAvailabilityRules" />
                                Pravidlá rezervácií
                            </label>

                            <label class="flex items-center gap-2 text-sm text-dark">
                                <ToggleSwitch v-model="showReservations" />
                                Rezervácie
                            </label>

                            <label class="flex items-center gap-2 text-sm text-dark">
                                <ToggleSwitch v-model="showGroupEvents" />
                                Skupinové termíny
                            </label>
                        </div>
                    </div>
                </div>

                <aside
                    ref="requestSidebar"
                    class="dynamic-booking-panel flex min-h-0 flex-col rounded-md border border-white/70 bg-white/85 p-3"
                    :style="requestSidebarHeight ? { height: `${requestSidebarHeight}px` } : null"
                >
                    <div class="rounded-md bg-soft p-1">
                        <div class="grid grid-cols-2 gap-1">
                            <button
                                v-for="mode in sidePanelModes"
                                :key="mode.key"
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-md px-3 py-1 text-normal transition"
                                :class="sidePanelMode === mode.key
                                    ? 'bg-accent text-white shadow-sm'
                                    : 'text-accent hover:text-dark'"
                                @click="sidePanelMode = mode.key"
                            >
                                <i :class="mode.icon" class="text-xs" />
                                {{ mode.label }}

                                <span
                                    v-if="mode.key === 'requests' && pendingRequests.length"
                                    class="rounded-full bg-white/90 px-1.5 py-0.5 text-[10px] font-bold text-accent"
                                >
                                    {{ pendingRequests.length }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden pt-4">
                        <section
                            v-if="sidePanelMode === 'requests'"
                            class="flex h-full min-h-0 flex-col"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3 px-1">
                                <div>
                                    <h2 class="text-sm font-bold text-dark">
                                        Čakajúce žiadosti
                                    </h2>

                                    <p class="text-xs text-accent">
                                        Potiahnite žiadosť priamo do kalendára.
                                    </p>
                                </div>

                                <span class="rounded-md bg-soft px-2 py-1 text-xs font-semibold text-accent">
                                    {{ pendingRequests.length }}
                                </span>
                            </div>

                            <div
                                v-if="pendingRequests.length"
                                class="min-h-0 flex-1 overflow-y-auto pr-1"
                            >
                                <div class="space-y-3">
                                    <article
                                        v-for="request in pendingRequests"
                                        :key="request.id"
                                        :data-request-id="request.id"
                                        class="appointment-request-card cursor-grab rounded-md bg-accent p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:cursor-grabbing"
                                    >
                                        <div class="space-y-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <h3 class="truncate font-semibold text-soft">
                                                        {{ request.patient_name }}
                                                    </h3>

                                                    <div class="mt-1 space-y-1 text-xs text-soft/90">
                                                        <p
                                                            v-if="request.patient_phone"
                                                            class="truncate"
                                                        >
                                                            {{ request.patient_phone }}
                                                        </p>

                                                        <p
                                                            v-if="request.patient_email"
                                                            class="truncate"
                                                        >
                                                            {{ request.patient_email }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="rounded-md bg-white/90 px-2 py-1 text-xs font-semibold text-dark transition hover:bg-red-50"
                                                    @mousedown.stop
                                                    @click.stop="openCancelAppointmentRequestDialog(request)"
                                                >
                                                    Zrušiť
                                                </button>
                                            </div>

                                            <div class="grid gap-2 text-normal text-soft">
                                                <div class="request-card-soft-box rounded-md bg-soft px-3 py-2 text-accent">
                                                    <span class="block text-xs font-semibold text-dark">
                                                        Služba
                                                    </span>

                                                    <span class="block text-xs leading-5">
                                                        {{ getRequestServicesLabel(request) }}
                                                    </span>
                                                </div>

                                                <div class="request-card-soft-box rounded-md bg-soft px-3 py-2 text-accent">
                                                    <span class="block text-xs font-semibold text-dark">
                                                        Preferovaný termín
                                                    </span>

                                                    <span class="block text-xs leading-5">
                                                        {{ formatDate(getRequestPreferredDate(request)) }} · {{ getRequestPeriodLabel(request) }}
                                                    </span>
                                                </div>

                                                <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-white/15 px-1 text-soft">
                                                    <span class="font-medium">
                                                        Trvanie
                                                    </span>

                                                    <span>
                                                        {{ request.total_duration_minutes }} min
                                                    </span>
                                                </div>
                                            </div>

                                            <p
                                                v-if="request.patient_note"
                                                class="request-card-soft-box rounded-md bg-white/60 p-3 text-xs leading-5 text-accent"
                                            >
                                                {{ request.patient_note }}
                                            </p>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex min-h-0 flex-1 items-center justify-center p-6 text-center text-sm text-accent"
                            >
                                Žiadne čakajúce žiadosti.
                            </div>
                        </section>

                        <section
                            v-else
                            class="flex h-full min-h-0 flex-col"
                        >
                            <div class="mb-3 space-y-3">
                                <span class="relative block">
                                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-accent" />

                                    <InputText
                                        v-model="reservationSearch"
                                        class="w-full !rounded-md !pl-9"
                                        placeholder="Meno, telefón, e-mail alebo služba"
                                    />
                                </span>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                                <div
                                    v-if="hasReservationSearch"
                                    class="space-y-3"
                                >
                                    <button
                                        v-for="result in mixedSearchResults"
                                        :key="result.key"
                                        type="button"
                                        class="w-full rounded-md border border-soft bg-soft p-3 text-left transition hover:-translate-y-0.5 hover:border-accent hover:bg-white"
                                        :class="result.eventIdCandidates?.includes(highlightedReservationEventId)
                                            ? 'border-accent bg-white'
                                            : ''"
                                        @click="openSearchResult(result)"
                                    >
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                :class="getSearchResultTagClass(result.type)"
                                            >
                                                {{ result.tagLabel }}
                                            </span>

                                            <span
                                                v-if="result.timeLabel"
                                                class="shrink-0 rounded-md bg-white px-2 py-1 text-xs font-semibold text-accent"
                                            >
                                                {{ result.timeLabel }}
                                            </span>
                                        </div>

                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-dark">
                                                    {{ result.patientName }}
                                                </p>

                                                <p
                                                    v-if="result.contactLabel"
                                                    class="mt-1 truncate text-xs text-accent"
                                                >
                                                    {{ result.contactLabel }}
                                                </p>

                                                <p
                                                    v-if="result.serviceLabel"
                                                    class="mt-1 truncate text-xs text-muted"
                                                >
                                                    {{ result.serviceLabel }}
                                                </p>

                                                <p
                                                    v-if="result.dateLabel"
                                                    class="mt-1 truncate text-xs text-muted"
                                                >
                                                    {{ result.dateLabel }}
                                                </p>
                                            </div>
                                        </div>
                                    </button>

                                    <p
                                        v-if="!mixedSearchResults.length"
                                        class="rounded-[22px] border border-dashed border-soft bg-soft/40 p-6 text-center text-sm text-accent"
                                    >
                                        Pre zadané hľadanie neexistujú žiadne rezervácie ani žiadosti.
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="flex h-full min-h-[220px] items-center justify-center rounded-[22px] border border-dashed border-soft bg-soft/40 p-6 text-center text-sm text-accent"
                                >
                                    Začnite písať meno pacienta, kontakt alebo názov služby.
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </div>

        <EventCreateEditHubDialog
            v-model:visible="createBookingDialogVisible"
            :services="services"
            :patients="patients"
            :selection="pendingCalendarSelection"
            :prefill="createBookingPrefill"
            @close="closeCreateBookingDialog"
            @create-booking="continueFromCreateChoice"
        />

        <BookingDialog
            v-model:detail-visible="bookingDialogVisible"
            :booking="selectedBooking"
            @edit-in-unified-form="openBookingInUnifiedEditor"
            @cancel-booking="cancelBooking"
            @duplicate-booking="duplicateBooking"
        />

        <AvailabilityRuleDialog
            v-model:visible="availabilityRuleDialogVisible"
            :rule="currentRule"
            :selected-rule-occurrence="selectedRuleOccurrence"
            :services="services"
            :repeat-unit-options="repeatUnitOptions"
            :loading="ruleForm.processing"
            @close="closeRuleDialogSafely"
            @edit-in-unified-form="openRuleInUnifiedEditor"
            @save="saveRules"
            @delete="deleteCurrentRuleByScope"
            @duplicate="duplicateCurrentRule"
        />

        <OccurrenceScopeDialog
            v-model:visible="ruleRescheduleScopeDialogVisible"
            mode="reschedule"
            subject-label="voľný čas"
            @select="saveRules"
        />

        <OccurrenceScopeDialog
            v-model:visible="bookingRescheduleScopeDialogVisible"
            mode="reschedule"
            subject-label="rezervácia"
            @select="submitPendingBookingRescheduleScope"
            @cancel="cancelPendingBookingReschedule"
        />

        <OccurrenceScopeDialog
            v-model:visible="capacityWindowRescheduleScopeDialogVisible"
            mode="reschedule"
            subject-label="skupinový termín"
            @select="submitPendingCapacityWindowRescheduleScope"
            @cancel="cancelPendingCapacityWindowReschedule"
        />

        <GroupEventDialog
            v-model:create-edit-visible="groupEventDialogVisible"
            v-model:occurrence-visible="groupEventOccurrenceDialogVisible"
            :group-event="selectedGroupEvent"
            :capacity-window="selectedCapacityWindow"
            :capacity-windows="calendarCapacityWindows"
            :services="services"
            :patients="patients"
            :repeat-unit-options="repeatUnitOptions"
            :booking-notes="bookingNotes"
            :loading="false"
            @close-create-edit="closeGroupEventDialog"
            @save="saveCapacityWindow"
            @duplicate="duplicateCapacityWindow"
            @edit-capacity-window="openCapacityWindowEditor"
            @duplicate-capacity-window="duplicateCapacityWindow"
            @reschedule-capacity-window="rescheduleCapacityWindow"
            @cancel-capacity-window="cancelCapacityWindow"
            @delete-capacity-window-occurrence="deleteCapacityWindowOccurrence"
            @delete-capacity-window-from-date="deleteCapacityWindowFromDate"
            @delete-capacity-window-series="deleteCapacityWindowSeries"
            @add-patient-to-capacity-window="addPatientToCapacityWindow"
            @cancel-booking="cancelBooking"
        />

        <ConfirmDialog
            :show="requestCancelDialogVisible"
            title="Zrušiť žiadosť"
            :message="requestCancelDialogMessage"
            confirm-label="Zrušiť žiadosť"
            cancel-label="Ponechať"
            confirm-severity="danger"
            @cancel="closeCancelAppointmentRequestDialog"
            @confirm="confirmCancelAppointmentRequest"
        />
    </AdminLayout>
</template>

<style scoped>
.booking-calendar :deep(.fc) {
    font-family: inherit;
}

.booking-calendar :deep(.fc-scrollgrid) {
    border-radius: 10px !important;
    overflow: hidden !important;
    border: 1px solid #FFE5E5 !important;
}

.booking-calendar :deep(.fc-toolbar-title) {
    font-size: 18px;
    font-weight: 700;
    color: #A75A5A;
}

.booking-calendar :deep(.fc-button) {
    border: 0;
    border-radius: 6px;
    background: #FFE5E5;
    color: #C17979;
    font-size: 14px;
    font-weight: 500;
    box-shadow: none;
}

.booking-calendar :deep(.fc-button:hover),
.booking-calendar :deep(.fc-button-primary:not(:disabled).fc-button-active) {
    background: #C17979;
    color: #ffffff;
}

.booking-calendar :deep(.fc-timegrid-slot) {
    height: 36px;
}

.booking-calendar :deep(.fc-col-header-cell) {
    padding: 10px 0;
    font-size: 13px;
    font-weight: 700;
    color: #A75A5A;
}

.booking-calendar :deep(.fc-timegrid-axis),
.booking-calendar :deep(.fc-timegrid-slot-label) {
    font-size: 12px;
    color: #A75A5A;
}

.booking-calendar :deep(.booking-rule-free-time) {
    border: 1px #FFE5E5;
    background: #FFE5E5;
    border-radius: 8px;
    padding: 2px 4px;
    overflow: hidden;
    z-index: 1;
    cursor: pointer;
}

.booking-calendar :deep(.booking-rule-free-time),
.booking-calendar :deep(.booking-rule-free-time .fc-event-main),
.booking-calendar :deep(.booking-rule-free-time .fc-event-time),
.booking-calendar :deep(.booking-rule-free-time .fc-event-title) {
    color: #C17979 !important;
}

.booking-calendar :deep(.booking-reservation-event) {
    border: 1px solid #C17979;
    background: #C17979;
    border-radius: 8px;
    padding: 2px 4px;
    z-index: 30;
    cursor: pointer;
}

.booking-calendar :deep(.booking-reservation-event),
.booking-calendar :deep(.booking-reservation-event .fc-event-main),
.booking-calendar :deep(.booking-reservation-event .fc-event-time),
.booking-calendar :deep(.booking-reservation-event .fc-event-title) {
    color: #ffffff !important;
}

.booking-calendar :deep(.booking-reservation-spotlight) {
    transform: scale(1.08);
    z-index: 90 !important;
    border-color: #8a3d3d !important;
    box-shadow:
        0 0 0 2px #ffffff,
        0 0 0 8px rgba(193, 121, 121, 0.5),
        0 0 24px rgba(193, 121, 121, 0.75),
        0 12px 24px rgba(167, 90, 90, 0.45);
    animation: booking-reservation-spotlight-pulse 0.65s ease-in-out 6;
}

@keyframes booking-reservation-spotlight-pulse {
    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.1);
    }

    100% {
        transform: scale(1.04);
    }
}

.booking-calendar :deep(.booking-capacity-window-event) {
    border: 1px solid #A75A5A;
    background: #A75A5A;
    border-radius: 8px;
    padding: 2px 4px;
    z-index: 20;
    cursor: pointer;
}

.booking-calendar :deep(.booking-capacity-window-event),
.booking-calendar :deep(.booking-capacity-window-event .fc-event-main),
.booking-calendar :deep(.booking-capacity-window-event .fc-event-time),
.booking-calendar :deep(.booking-capacity-window-event .fc-event-title) {
    color: #ffffff !important;
}

.booking-calendar :deep(.booking-capacity-window-full) {
    opacity: 0.75;
}

.booking-calendar :deep(.booking-disabled-day-event) {
    background: rgba(193, 121, 121, 0.12) !important;
    border: none !important;
    opacity: 1 !important;
}

.booking-calendar :deep(.booking-disabled-day-column) {
    background: repeating-linear-gradient(
        135deg,
        rgba(193, 121, 121, 0.14) 0,
        rgba(193, 121, 121, 0.14) 10px,
        rgba(193, 121, 121, 0.08) 10px,
        rgba(193, 121, 121, 0.08) 20px
    ) !important;
}

.booking-calendar :deep(.booking-disabled-day-header) {
    background: rgba(193, 121, 121, 0.08) !important;
}

.booking-calendar :deep(.fc-disabled-day-tag) {
    display: inline-block;
    border: 1px solid #C17979;
    border-radius: 999px;
    background: #ffffff;
    color: #C17979;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    padding: 4px 8px;
    max-width: min(100%, 84px);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: all 0.2s ease;
    cursor: pointer;
}

.booking-calendar :deep(.fc-disabled-day-tag.is-open:hover) {
    background: #FFE5E5;
}

.booking-calendar :deep(.fc-disabled-day-tag.is-closed) {
    background: #C17979;
    color: #ffffff;
}

.booking-calendar :deep(.fc-disabled-day-tag.is-locked),
.booking-calendar :deep(.fc-disabled-day-tag:disabled) {
    cursor: not-allowed;
    opacity: 0.6;
}

.booking-calendar :deep(.booking-capacity-window-event .fc-event-title) {
    font-weight: 800;
}

.booking-calendar :deep(.booking-request-preview-event) {
    border: 1px solid #C17979;
    background: #C17979;
    border-radius: 8px;
    padding: 2px 4px;
    z-index: 40;
    cursor: pointer;
}

.booking-calendar :deep(.booking-request-preview-event),
.booking-calendar :deep(.booking-request-preview-event .fc-event-main),
.booking-calendar :deep(.booking-request-preview-event .fc-event-time),
.booking-calendar :deep(.booking-request-preview-event .fc-event-title) {
    color: #ffffff !important;
}

.booking-calendar :deep(.fc-event-title) {
    font-size: 12px;
    font-weight: 600;
}

.booking-calendar :deep(.fc-event-time) {
    font-size: 11px;
}

.booking-calendar :deep(.fc-theme-standard tr > *:last-child) {
    border-right: none !important;
    border-bottom: none !important;
}

.booking-calendar :deep(.fc-timegrid-event-harness) {
    right: 8px !important;
}

.booking-calendar :deep(.fc-timegrid-event) {
    margin-right: 0 !important;
}

.dynamic-booking-panel {
    isolation: isolate;
}

.dynamic-booking-panel :deep(.p-inputtext) {
    width: 100%;
}

.appointment-request-card.request-search-spotlight {
    transform: scale(1.02);
    box-shadow:
        0 0 0 2px rgba(255, 255, 255, 0.95),
        0 0 0 6px rgba(59, 130, 246, 0.35),
        0 10px 24px rgba(30, 64, 175, 0.35);
    animation: request-search-spotlight-pulse 0.5s ease-in-out 4;
}

@keyframes request-search-spotlight-pulse {
    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.035);
    }

    100% {
        transform: scale(1.015);
    }
}
</style>