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
import ToggleSwitch from 'primevue/toggleswitch';

import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    services: {
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
        ],
        preserveState: true,
        preserveScroll: true,
    });
};

useBranchBroadcasting(props.branch.id, reloadCalendarData);

const requestToCancel = ref(null);

let requestDraggable = null;
let calendarResizeObserver = null;

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
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div
                    ref="bookingCalendar"
                    class="booking-calendar flex min-w-0 flex-col gap-4"
                >
                    <FullCalendar
                        ref="fullCalendar"
                        :options="calendarOptions"
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
                    class="flex min-h-0 flex-col gap-4 rounded-md bg-soft p-4"
                    :style="requestSidebarHeight ? { height: `${requestSidebarHeight}px` } : null"
                >
                    <div
                        v-if="pendingRequests.length"
                        class="min-h-0 flex-1 overflow-y-auto pr-1"
                    >
                        <div class="space-y-3">
                            <article
                                v-for="request in pendingRequests"
                                :key="request.id"
                                :data-request-id="request.id"
                                class="appointment-request-card cursor-grab rounded-md bg-accent p-4 transition active:cursor-grabbing"
                            >
                                <div class="space-y-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-semibold text-soft">
                                                {{ request.patient_name }}
                                            </h3>

                                            <div class="space-y-1 text-xs text-soft">
                                                <p v-if="request.patient_phone">
                                                    {{ request.patient_phone }}
                                                </p>

                                                <p v-if="request.patient_email">
                                                    {{ request.patient_email }}
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="rounded-md px-2 py-1 text-xs font-semibold text-dark transition hover:bg-red-50"
                                            @mousedown.stop
                                            @click.stop="openCancelAppointmentRequestDialog(request)"
                                        >
                                            Zrušiť
                                        </button>
                                    </div>

                                    <div class="grid gap-2 text-normal text-soft">
                                        <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-soft px-3 py-2 text-accent">
                                            <span class="text-right">
                                                {{ getRequestServicesLabel(request) }}
                                            </span>
                                        </div>

                                        <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-soft px-3 py-2 text-accent">
                                            <span class="font-medium">
                                                Preferovaný termín
                                            </span>

                                            <span class="text-right">
                                                {{ formatDate(getRequestPreferredDate(request)) }} · {{ getRequestPeriodLabel(request) }}
                                            </span>
                                        </div>

                                        <div class="request-card-soft-box flex items-center justify-end gap-3 text-soft">
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
                        class="flex min-h-0 flex-1 items-center justify-center text-center text-sm text-accent"
                    >
                        Žiadne čakajúce žiadosti.
                    </div>
                </aside>
            </div>
        </div>

        <EventCreateEditHubDialog
            v-model:visible="createBookingDialogVisible"
            :services="services"
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
</style>