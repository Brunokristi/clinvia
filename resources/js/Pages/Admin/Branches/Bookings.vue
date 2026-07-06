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
import { useToast } from 'primevue/usetoast';

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

const toast = useToast();

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
            onError: (errors) => {
                const firstError = Object.values(errors ?? {})?.[0];

                toast.add({
                    severity: 'error',
                    summary: 'Chyba',
                    detail: Array.isArray(firstError)
                        ? firstError[0]
                        : (firstError || 'Deň sa nepodarilo zatvoriť.'),
                    life: 5000,
                });

                callbacks.onError?.();
            },
        });

        return;
    }

    if (!existing?.id) {
        if (existing?.source === 'holiday') {
            router.post(route('branches.booking.disabled-days.store', props.branch.id), {
                date: dateOnly,
                title: 'Otvoreny sviatok',
                type: 'holiday_open',
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
        }

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
const sideSegmentedControl = ref(null);
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

const openSearchResultGroups = ref({
    reservation: false,
    group_reservation: false,
    request: false,
});

const sidePanelModes = [
    {
        key: 'requests',
        label: 'Žiadosti',
        icon: 'pi pi-inbox',
        type: 'mode',
    },
    {
        key: 'reservations',
        label: 'Hľadanie',
        icon: 'pi pi-search',
        type: 'mode',
    },
    {
        key: 'create',
        label: 'Vytvoriť',
        icon: 'pi pi-plus',
        type: 'action',
    },
];

const handleSidePanelModeClick = (mode) => {
    if (mode.key === 'create') {
        openCreateChoiceFromButton();

        return;
    }

    sidePanelMode.value = mode.key;
};

const searchResultGroupDefinitions = [
    {
        type: 'reservation',
        label: 'Rezervácie',
        icon: 'pi pi-calendar',
    },
    {
        type: 'group_reservation',
        label: 'Skupinové rezervácie',
        icon: 'pi pi-users',
    },
    {
        type: 'request',
        label: 'Žiadosti',
        icon: 'pi pi-inbox',
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
const pendingRequestConversion = ref(null);
const selectedPatientCandidateKey = ref('new');

const normalizeSearchValue = (value) => {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
};

const normalizePhoneValue = (value) => {
    return String(value ?? '').replace(/\s+/g, '').trim();
};

const normalizeBirthNumberValue = (value) => {
    return String(value ?? '').trim();
};

const toLocalDateTimeString = (value) => {
    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}:00`;
};

const hasExactPatientIdentifierMatch = (appointmentRequest, patient) => {
    const requestBirthNumber = normalizeBirthNumberValue(appointmentRequest?.patient_birth_number);
    const requestEmail = normalizeSearchValue(appointmentRequest?.patient_email);
    const requestPhone = normalizePhoneValue(appointmentRequest?.patient_phone);

    if (requestBirthNumber && requestBirthNumber === normalizeBirthNumberValue(patient?.patient_birth_number)) {
        return true;
    }

    if (requestEmail && requestEmail === normalizeSearchValue(patient?.patient_email)) {
        return true;
    }

    if (requestPhone && requestPhone === normalizePhoneValue(patient?.patient_phone)) {
        return true;
    }

    return false;
};

const getSimilarPatientsForRequest = (appointmentRequest) => {
    const requestName = normalizeSearchValue(appointmentRequest?.patient_name);

    if (!requestName) {
        return [];
    }

    const sameNamePatients = (props.patients ?? []).filter((patient) => {
        return normalizeSearchValue(patient?.patient_name) === requestName;
    });

    if (!sameNamePatients.length) {
        return [];
    }

    if (sameNamePatients.some((patient) => hasExactPatientIdentifierMatch(appointmentRequest, patient))) {
        return [];
    }

    return sameNamePatients
        .map((patient, index) => ({
            ...patient,
            __candidateKey: String(patient?.id ?? `similar-${index}`),
        }));
};

const formatPatientCandidateDetails = (patient) => {
    const details = [
        patient?.patient_birth_number,
        patient?.patient_email,
        patient?.patient_phone,
    ].filter((value) => String(value ?? '').trim().length > 0);

    return details.length ? details.join(' · ') : 'Bez doplňujúcich údajov';
};

const requestConversionDialogVisible = computed(() => {
    return Boolean(pendingRequestConversion.value);
});

const similarPatientCandidates = computed(() => {
    return pendingRequestConversion.value?.similarPatients ?? [];
});

const selectedPatientCandidate = computed(() => {
    if (!pendingRequestConversion.value || selectedPatientCandidateKey.value === 'new') {
        return null;
    }

    return similarPatientCandidates.value.find((patient) => {
        return String(patient.__candidateKey) === String(selectedPatientCandidateKey.value);
    }) ?? null;
});

const requestConversionDialogMessage = computed(() => {
    if (!pendingRequestConversion.value) {
        return '';
    }

    return `Našli sme podobných pacientov s menom ${pendingRequestConversion.value.appointmentRequest.patient_name}. Vyberte, či chcete použiť existujúci profil alebo ponechať údaje zo žiadosti.`;
});

const closeRequestConversionDialog = () => {
    pendingRequestConversion.value = null;
    selectedPatientCandidateKey.value = 'new';
};

const submitRequestConversion = ({ appointmentRequestId, startsAt, selectedPatient = null }) => {
    if (!appointmentRequestId || !startsAt) {
        return;
    }

    const payload = {
        starts_at: startsAt,
    };

    if (selectedPatient) {
        payload.selected_patient = {
            patient_name: selectedPatient.patient_name ?? null,
            patient_email: selectedPatient.patient_email ?? null,
            patient_phone: selectedPatient.patient_phone ?? null,
            patient_birth_number: selectedPatient.patient_birth_number ?? null,
        };
    }

    router.post(route('branches.booking.appointment-requests.convert', [
        props.branch.id,
        appointmentRequestId,
    ]), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showReservations.value = true;
            reloadCalendarData();
            closeRequestConversionDialog();
        },
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})?.[0];

            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: Array.isArray(firstError)
                    ? firstError[0]
                    : (firstError || 'Žiadosť sa nepodarilo premeniť na rezerváciu.'),
                life: 5000,
            });

            reloadCalendarData();
        },
    });
};

const confirmRequestConversion = () => {
    if (!pendingRequestConversion.value) {
        return;
    }

    submitRequestConversion({
        appointmentRequestId: pendingRequestConversion.value.appointmentRequest.id,
        startsAt: pendingRequestConversion.value.startsAt,
        selectedPatient: selectedPatientCandidate.value,
    });
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
    if (capacityWindow?.calendar_event_id) {
        return String(capacityWindow.calendar_event_id);
    }

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
                timeLabel: null,
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
                dateLabel: formatDate(getBookingStartValue(booking)),
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
                dateLabel: formatDate(getBookingStartValue(booking)),
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

const groupedSearchResultSections = computed(() => {
    return searchResultGroupDefinitions
        .map((group) => {
            return {
                ...group,
                results: mixedSearchResults.value.filter((result) => result.type === group.type),
            };
        })
        .filter((group) => group.results.length > 0);
});

const toggleSearchResultGroup = (type) => {
    openSearchResultGroups.value[type] = !openSearchResultGroups.value[type];
};

const getSearchResultTagClass = (type) => {
    return 'bg-soft text-accent';
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

    const calendarHeight = Math.round(bookingCalendar.value.getBoundingClientRect().height);
    const segmentedHeight = Math.round(sideSegmentedControl.value?.getBoundingClientRect()?.height ?? 0);
    const sideGap = 16;

    requestSidebarHeight.value = Math.max(0, calendarHeight - segmentedHeight - sideGap);
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
    const originalEventReceive = resolvedCalendarOptions.eventReceive;

    return {
        ...resolvedCalendarOptions,
        eventDidMount: (info) => {
            info.el.dataset.eventId = info.event.id;

            if (info.event.extendedProps?.booking?.id) {
                info.el.dataset.bookingId = String(info.event.extendedProps.booking.id);
            }

            originalEventDidMount?.(info);
        },
        eventReceive: (receiveInfo) => {
            const appointmentRequest = receiveInfo.event?.extendedProps?.appointmentRequest;
            const startsAt = toLocalDateTimeString(receiveInfo.event?.start);

            if (!appointmentRequest?.id || !startsAt) {
                originalEventReceive?.(receiveInfo);

                return;
            }

            const similarPatients = getSimilarPatientsForRequest(appointmentRequest);

            if (!similarPatients.length) {
                originalEventReceive?.(receiveInfo);

                return;
            }

            receiveInfo.revert();

            pendingRequestConversion.value = {
                appointmentRequest,
                startsAt,
                similarPatients,
            };
            selectedPatientCandidateKey.value = 'new';
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
            <div class="grid gap-4 grid-cols-[minmax(0,1fr)_300px]">
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

                <div class="booking-side-column">
                    <div ref="sideSegmentedControl" class="booking-side-segmented-control">
                        <button
                            v-for="mode in sidePanelModes"
                            :key="mode.key"
                            type="button"
                            class="booking-side-segmented-button"
                            :class="{
                                'is-active': sidePanelMode === mode.key && mode.type === 'mode',
                                'is-action': mode.type === 'action',
                            }"
                            :title="mode.label"
                            @click="handleSidePanelModeClick(mode)"
                        >
                            <i :class="mode.icon" class="text-xs" />

                            <span
                                v-if="sidePanelMode === mode.key && mode.type === 'mode'"
                                class="truncate"
                            >
                                {{ mode.label }}
                            </span>

                            <span
                                v-if="mode.key === 'requests' && pendingRequests.length"
                                class="booking-side-segmented-count"
                                :class="sidePanelMode === mode.key ? 'is-active' : ''"
                            >
                                {{ pendingRequests.length }}
                            </span>
                        </button>
                    </div>

                    <aside
                        ref="requestSidebar"
                        class="dynamic-booking-panel flex min-h-0 flex-col rounded-md bg-soft p-3"
                        :style="requestSidebarHeight ? { height: `${requestSidebarHeight}px` } : null"
                    >
                        <div class="min-h-0 flex-1 overflow-hidden">
                            <section
                                v-if="sidePanelMode === 'requests'"
                                class="flex h-full min-h-0 flex-col"
                            >
                                <div
                                    v-if="pendingRequests.length"
                                    class="min-h-0 flex-1 overflow-y-auto pr-1"
                                >
                                    <div class="space-y-2">
                                        <article
                                            v-for="request in pendingRequests"
                                            :key="request.id"
                                            :data-request-id="request.id"
                                            class="appointment-request-card cursor-grab rounded-md border border-soft bg-white p-3 text-left shadow-sm transition hover:border-accent active:cursor-grabbing"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="mb-2">
                                                        <p class="truncate text-sm font-semibold text-dark">
                                                            {{ request.patient_name }}
                                                        </p>

                                                        <p class="mt-1 truncate text-xs font-semibold text-accent">
                                                            {{ formatDate(getRequestPreferredDate(request)) }} · {{ getRequestPeriodLabel(request) }}
                                                        </p>

                                                        <p class="mt-0.5 text-xs font-semibold text-accent">
                                                            {{ request.total_duration_minutes }} min
                                                        </p>
                                                    </div>

                                                    <p
                                                        v-if="request.patient_phone || request.patient_email"
                                                        class="truncate text-xs text-accent"
                                                    >
                                                        {{ request.patient_phone ?? request.patient_email }}
                                                    </p>

                                                    <p class="mt-1 truncate text-xs text-accent">
                                                        {{ getRequestServicesLabel(request) }}
                                                    </p>
                                                </div>

                                                <div class="flex shrink-0 flex-col items-end">
                                                <span class="inline-flex rounded-md bg-soft px-2 py-0.5 text-xs text-accent">
                                                    Žiadosť
                                                </span>
                                            </div>
                                            </div>

                                            <p
                                                v-if="request.patient_note"
                                                class="mt-3 rounded-md bg-soft p-2 text-xs leading-5 text-accent"
                                            >
                                                {{ request.patient_note }}
                                            </p>

                                            <div class="mt-3 flex justify-end border-t border-soft pt-3">
                                                <button
                                                    type="button"
                                                    class="rounded-md bg-soft px-3 py-1.5 text-xs font-semibold text-accent transition hover:bg-red-50 hover:text-red-700"
                                                    @mousedown.stop
                                                    @click.stop="openCancelAppointmentRequestDialog(request)"
                                                >
                                                    Zrušiť žiadosť
                                                </button>
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
                                            class="w-full !rounded-md !pl-9 !bg-white"
                                            placeholder="Meno, telefón, e-mail alebo služba"
                                        />
                                    </span>
                                </div>

                                <div
                                    v-if="hasReservationSearch"
                                    class="min-h-0 flex-1 overflow-y-auto pr-1"
                                >
                                    <div
                                        v-if="groupedSearchResultSections.length"
                                        class="space-y-3"
                                    >
                                        <section
                                            v-for="group in groupedSearchResultSections"
                                            :key="group.type"
                                            class="overflow-hidden rounded-md border border-soft bg-white"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-3 bg-soft text-accent hover:text-dark px-3 py-2 text-left "
                                                @click="toggleSearchResultGroup(group.type)"
                                            >
                                                <div class="flex min-w-0 items-stretch gap-2">
                                                    <div class="min-w-0 py-0.5">
                                                        <p class="truncate text-sm font-semibold leading-5">
                                                            {{ group.label }}
                                                        </p>

                                                        <p class="text-xs leading-4">
                                                            {{ group.results.length }} výsledkov
                                                        </p>
                                                    </div>
                                                </div>

                                                <i
                                                    class="pi text-xs text-accent transition-transform duration-200"
                                                    :class="openSearchResultGroups[group.type] ? 'pi-chevron-up' : 'pi-chevron-down'"
                                                />
                                            </button>

                                            <Transition name="search-curtain">
                                                <div
                                                    v-if="openSearchResultGroups[group.type]"
                                                    class="space-y-2 bg-soft p-2"
                                                >
                                                    <button
                                                        v-for="result in group.results"
                                                        :key="result.key"
                                                        type="button"
                                                        class="w-full rounded-md border border-soft bg-white p-3 text-left transition hover:border-accent hover:bg-white"
                                                        :class="result.eventIdCandidates?.includes(highlightedReservationEventId)
                                                            ? 'border-accent'
                                                            : ''"
                                                        @click="openSearchResult(result)"
                                                    >
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p class="mb-2 truncate text-sm font-semibold text-dark">
                                                                    {{ result.patientName }}
                                                                </p>

                                                                <p
                                                                    v-if="result.dateLabel"
                                                                    class="truncate text-sm font-semibold text-accent"
                                                                >
                                                                    {{ result.dateLabel }}
                                                                </p>

                                                                <p
                                                                    v-if="result.timeLabel"
                                                                    class="text-xs font-semibold text-accent"
                                                                >
                                                                    {{ result.timeLabel }}
                                                                </p>

                                                                <p
                                                                    v-if="result.contactLabel"
                                                                    class="mt-2 truncate text-xs text-accent"
                                                                >
                                                                    {{ result.contactLabel }}
                                                                </p>

                                                                <p
                                                                    v-if="result.serviceLabel"
                                                                    class="mt-1 truncate text-xs text-accent"
                                                                >
                                                                    {{ result.serviceLabel }}
                                                                </p>
                                                            </div>

                                                            <span
                                                                class="inline-flex shrink-0 rounded-md px-2 py-0.5 text-xs"
                                                                :class="getSearchResultTagClass(result.type)"
                                                            >
                                                                {{ result.tagLabel }}
                                                            </span>
                                                        </div>
                                                    </button>
                                                </div>
                                            </Transition>
                                        </section>
                                    </div>

                                    <p
                                        v-else
                                        class="p-6 text-center text-sm text-accent"
                                    >
                                        Pre zadané hľadanie neexistujú žiadne rezervácie ani žiadosti.
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="flex h-full min-h-[220px] items-center justify-center p-6 text-center text-sm text-accent"
                                >
                                    Začnite písať meno pacienta, kontakt alebo názov služby.
                                </div>
                            </section>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <EventCreateEditHubDialog
            v-model:visible="createBookingDialogVisible"
            :services="services"
            :patients="patients"
            :branch-id="props.branch.id"
            :selection="pendingCalendarSelection"
            :prefill="createBookingPrefill"
            @close="closeCreateBookingDialog"
            @create-booking="continueFromCreateChoice"
        />

        <BookingDialog
            v-model:detail-visible="bookingDialogVisible"
            :booking="selectedBooking"
            :series-bookings="props.calendarBookings"
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
            :branch-id="props.branch.id"
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

        <ConfirmDialog
            :show="requestConversionDialogVisible"
            title="Podobný pacient už existuje"
            :message="requestConversionDialogMessage"
            confirm-label="Pokračovať"
            cancel-label="Zrušiť"
            confirm-severity="primary"
            @cancel="closeRequestConversionDialog"
            @confirm="confirmRequestConversion"
        >
            <div class="mt-4 space-y-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-soft bg-soft p-3 text-sm text-accent">
                    <input
                        v-model="selectedPatientCandidateKey"
                        type="radio"
                        value="new"
                        class="mt-1"
                    >

                    <span>
                        <span class="block font-semibold text-dark">
                            Ponechať údaje zo žiadosti
                        </span>

                        <span class="block text-xs text-accent">
                            Vytvorí sa nový alebo samostatný profil podľa údajov zo žiadosti.
                        </span>
                    </span>
                </label>

                <label
                    v-for="patient in similarPatientCandidates"
                    :key="patient.__candidateKey"
                    class="flex cursor-pointer items-start gap-3 rounded-md border border-soft bg-white p-3 text-sm text-accent"
                >
                    <input
                        v-model="selectedPatientCandidateKey"
                        type="radio"
                        :value="patient.__candidateKey"
                        class="mt-1"
                    >

                    <span>
                        <span class="block font-semibold text-dark">
                            {{ patient.patient_name }}
                        </span>

                        <span class="block text-xs text-accent">
                            {{ formatPatientCandidateDetails(patient) }}
                        </span>
                    </span>
                </label>
            </div>
        </ConfirmDialog>
    </AdminLayout>
</template>

<style scoped>

.booking-side-column {
    display: flex;
    min-height: 0;
    flex-direction: column;
    gap: 16px;
}

.booking-side-segmented-control {
    display: flex;
    height: 32px;
    overflow: hidden;
    border-radius: 6px;
    background: #FFE5E5;
}

.booking-side-segmented-button {
    position: relative;
    display: inline-flex;
    flex: 0 0 42px;
    align-items: center;
    justify-content: center;
    min-width: 0;
    height: 32px;
    gap: 6px;
    border: 0;
    border-right: 1px solid rgba(193, 121, 121, 0.18);
    background: transparent;
    color: #C17979;
    font-size: 14px;
    font-weight: 500;
    line-height: 1;
    padding: 0;
    box-shadow: none;
    white-space: nowrap;
    transition:
        flex-basis 0.2s ease,
        background 0.18s ease,
        color 0.18s ease;
}

.booking-side-segmented-button:last-child {
    border-right: 0;
}

.booking-side-segmented-button:hover {
    background: rgba(193, 121, 121, 0.08);
    color: #A75A5A;
}

.booking-side-segmented-button.is-active {
    flex: 1 1 auto;
    justify-content: center;
    background: #C17979;
    color: #ffffff;
    padding: 0 10px;
}

.booking-side-segmented-button.is-action {
    flex: 0 0 42px;
    font-weight: 500;
}

.booking-side-segmented-count {
    position: absolute;
    top: 3px;
    right: 3px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 15px;
    height: 15px;
    padding: 0 4px;
    border-radius: 999px;
    background: rgba(193, 121, 121, 0.16);
    color: #C17979;
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
}

.booking-side-segmented-count.is-active {
    position: static;
    min-width: 17px;
    height: 17px;
    padding: 0 5px;
    background: rgba(255, 255, 255, 0.92);
    color: #C17979;
    font-size: 10px;
}

.booking-calendar :deep(.fc) {
    font-family: inherit;
}

.booking-calendar :deep(.fc-scrollgrid) {
    border-radius: 8px !important;
    overflow: hidden !important;
    border: 1px solid #FFE5E5 !important;
}

.booking-calendar :deep(.fc-theme-standard td),
.booking-calendar :deep(.fc-theme-standard th) {
    border-color: #FFE5E5 !important;
}

.booking-calendar :deep(.fc-theme-standard .fc-scrollgrid) {
    border-color: #FFE5E5 !important;
}

.booking-calendar :deep(.fc-theme-standard .fc-scrollgrid-section > *) {
    border-color: #FFE5E5 !important;
}

.booking-calendar :deep(.fc-theme-standard .fc-timegrid-slot),
.booking-calendar :deep(.fc-theme-standard .fc-timegrid-axis),
.booking-calendar :deep(.fc-theme-standard .fc-col-header-cell),
.booking-calendar :deep(.fc-theme-standard .fc-daygrid-day) {
    border-color: #FFE5E5 !important;
}

.booking-calendar :deep(.fc-toolbar-title) {
    font-size: 18px;
    font-weight: 700;
    color: #A75A5A;
}

.booking-calendar :deep(.fc .fc-toolbar.fc-header-toolbar) {
    margin-bottom: 1rem;
}

.booking-calendar :deep(.fc-button) {
    height: 32px;
    border: 0;
    border-radius: 6px;
    background: #FFE5E5;
    color: #C17979;
    font-size: 14px;
    font-weight: 500;
    line-height: 1;
    padding: 0 10px;
    box-shadow: none;
}

.booking-calendar :deep(.fc-button:hover),
.booking-calendar :deep(.fc-button-primary:not(:disabled).fc-button-active) {
    background: #C17979;
    color: #ffffff;
}

.booking-calendar :deep(.fc-button-group) {
    height: 32px;
    overflow: hidden;
    border-radius: 6px;
    background: #FFE5E5;
}

.booking-calendar :deep(.fc-button-group .fc-button) {
    border-radius: 0;
    border-right: 1px solid rgba(193, 121, 121, 0.18);
}

.booking-calendar :deep(.fc-button-group .fc-button:last-child) {
    border-right: 0;
}

.booking-calendar :deep(.fc-button-group .fc-button:first-child) {
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
}

.booking-calendar :deep(.fc-button-group .fc-button:last-child) {
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
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
    border-radius: 6px;
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
    z-index: 10 !important;
    animation: booking-reservation-spotlight-pulse 0.5s ease-in-out;
}

@keyframes booking-reservation-spotlight-pulse {
    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.06);
    }

    100% {
        transform: scale(1.02);
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

.booking-calendar :deep(.fc-non-business) {
    background: repeating-linear-gradient(
        135deg,
        rgba(193, 121, 121, 0.14) 0,
        rgba(193, 121, 121, 0.14) 10px,
        rgba(193, 121, 121, 0.08) 10px,
        rgba(193, 121, 121, 0.08) 20px
    ) !important;
}

.booking-calendar :deep(.booking-disabled-day-column .fc-non-business) {
    background: transparent !important;
}

.booking-calendar :deep(.fc-disabled-day-tag) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    min-width: 3rem;
    border: 1px solid #C17979;
    border-radius: 6px;
    background: #ffffff;
    color: #C17979;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    padding: 2px 4px;
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
        0 0 0 5px rgba(193, 121, 121, 0.28),
        0 10px 24px rgba(193, 121, 121, 0.24);
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

.search-curtain-enter-active,
.search-curtain-leave-active {
    max-height: 600px;
    overflow: hidden;
    transition:
        max-height 0.22s ease,
        opacity 0.18s ease;
}

.search-curtain-enter-from,
.search-curtain-leave-to {
    max-height: 0;
    opacity: 0;
}

.search-curtain-enter-to,
.search-curtain-leave-from {
    max-height: 600px;
    opacity: 1;
}

.booking-calendar :deep(.fc-day-today:not(.booking-disabled-day-column):not(.booking-disabled-day-header)) {
    background: transparent !important;
}

.booking-calendar :deep(.fc-timegrid-col.fc-day-today:not(.booking-disabled-day-column)) {
    background: transparent !important;
}

.booking-calendar :deep(.fc-daygrid-day.fc-day-today:not(.booking-disabled-day-column)) {
    background: transparent !important;
}

.booking-calendar :deep(.fc-col-header-cell.fc-day-today:not(.booking-disabled-day-header)) {
    background: transparent !important;
}

.booking-calendar :deep(.fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    min-width: 2rem;
    border-radius: 8px;
    background: #C17979;
    color: #ffffff !important;
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

.booking-calendar :deep(.fc-col-header-cell.fc-day-today .fc-col-header-cell-cushion) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    min-width: 2rem;
    border-radius: 8px;
    background: #C17979;
    color: #ffffff !important;
}
</style>