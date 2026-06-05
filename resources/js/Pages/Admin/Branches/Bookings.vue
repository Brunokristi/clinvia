<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

import CalendarCreateChoiceDialog from '@/Components/Booking/CalendarCreateChoiceDialog.vue';
import BookingCreateDialog from '@/Components/Booking/BookingCreateDialog.vue';
import BookingEditDialog from '@/Components/Booking/BookingEditDialog.vue';
import AvailabilityRuleCreateEditDialog from '@/Components/Booking/AvailabilityRuleCreateEditDialog.vue';
import GroupEventCreateEditDialog from '@/Components/Booking/GroupEventCreateEditDialog.vue';
import GroupEventOccurrenceDialog from '@/Components/Booking/GroupEventOccurrenceDialog.vue';
import ConfirmDialog from '@/Components/Dialogs/ConfirmationDialog.vue';

import { useBookingCalendar } from '@/Composables/Bookings/useBookingCalendar';

import FullCalendar from '@fullcalendar/vue3';
import { Draggable } from '@fullcalendar/interaction';

import { router } from '@inertiajs/vue3';

import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';

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

const {
    showAvailabilityRules,
    showReservations,

    createChoiceDialogVisible,

    createBookingDialogVisible,
    bookingDialogVisible,

    availabilityRuleDialogVisible,
    groupEventDialogVisible,
    groupEventOccurrenceDialogVisible,

    selectedBooking,
    selectedCapacityWindow,
    selectedRuleOccurrence,
    pendingCalendarSelection,

    ruleForm,
    currentRule,

    repeatUnitOptions,

    bookingNotes,
    calendarOptions,

    getRuleTitle,
    getRepeatLabel,
    availableSlotsForBooking,

    openCreateChoiceFromButton,
    closeCreateBookingDialog,

    closeCreateChoiceDialog,
    continueFromCreateChoice,

    closeRuleDialog,
    saveRules,

    createAdminBooking,
    updateBooking,
    cancelBooking,
    rescheduleBooking,

    deleteCurrentRuleOccurrence,
    deleteCurrentRuleFromNowOn,
    deleteCurrentRuleEverywhere,

    cancelCapacityWindow,
    rescheduleCapacityWindow,

    deleteCapacityWindowOccurrence,
    deleteCapacityWindowFromDate,
    deleteCapacityWindowSeries,
    addPatientToCapacityWindow,
} = useBookingCalendar(props);

const bookingCalendar = ref(null);
const requestSidebar = ref(null);
const requestSidebarHeight = ref(null);

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

    const calendarElement = bookingCalendar.value.querySelector('.fc') ?? bookingCalendar.value;

    requestSidebarHeight.value = Math.round(calendarElement.getBoundingClientRect().height);
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
        preserveState: false,
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

        if (bookingCalendar.value) {
            calendarResizeObserver = new ResizeObserver(() => {
                updateRequestSidebarHeight();
            });

            calendarResizeObserver.observe(bookingCalendar.value);
        }
    });

    window.addEventListener('resize', updateRequestSidebarHeight);
});

onBeforeUnmount(() => {
    requestDraggable?.destroy();
    calendarResizeObserver?.disconnect();

    window.removeEventListener('resize', updateRequestSidebarHeight);
});
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <FormSection columns="grid-cols-1">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                        <div
                            ref="bookingCalendar"
                            class="booking-calendar min-w-0"
                        >
                            <FullCalendar :options="calendarOptions" />
                        </div>

                        <aside
                            ref="requestSidebar"
                            class="flex min-h-0 flex-col gap-4"
                            :style="requestSidebarHeight ? { height: `${requestSidebarHeight}px` } : null"
                        >
                            <h1 class="text-normal text-dark font-semibold">Žiadosti o rezerváciu</h1>

                            <div
                                v-if="pendingRequests.length"
                                class="min-h-0 flex-1 overflow-y-auto pr-1"
                            >
                                <div class="space-y-3">
                                    <article
                                        v-for="request in pendingRequests"
                                        :key="request.id"
                                        :data-request-id="request.id"
                                        class="appointment-request-card cursor-grab rounded-md border border-soft bg-soft p-4 transition hover:bg-soft/80 active:cursor-grabbing active:bg-accent"
                                    >
                                        <div class="space-y-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <h3 class="font-semibold text-dark">
                                                        {{ request.patient_name }}
                                                    </h3>

                                                    <div class="space-y-1 text-xs text-accent">
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
                                                    class="rounded-md px-2 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                                    @mousedown.stop
                                                    @click.stop="openCancelAppointmentRequestDialog(request)"
                                                >
                                                    Zrušiť
                                                </button>
                                            </div>

                                            <div class="grid gap-2 text-normal text-soft ">
                                                <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                                    <span class="text-right">
                                                        {{ getRequestServicesLabel(request) }}
                                                    </span>
                                                </div>

                                                <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                                    <span class="font-medium">
                                                        Preferovaný termín
                                                    </span>

                                                    <span class="text-right">
                                                        {{ formatDate(getRequestPreferredDate(request)) }} · {{ getRequestPeriodLabel(request) }}
                                                    </span>
                                                </div>

                                                <div class="request-card-soft-box flex items-center justify-between gap-3 text-accent">
                                                    <span class="font-medium">
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
                                class="min-h-0 flex-1 rounded-md border border-soft bg-soft p-4 text-sm text-accent"
                            >
                                Žiadne čakajúce žiadosti.
                            </div>
                        </aside>
                    </div>

                    <div class="flex flex-wrap items-center justify-start gap-4">
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm text-dark">
                                <ToggleSwitch v-model="showAvailabilityRules" />
                                Zobraziť pravidlá
                            </label>

                            <label class="flex items-center gap-2 text-sm text-dark">
                                <ToggleSwitch v-model="showReservations" />
                                Zobraziť rezervácie
                            </label>
                        </div>

                        <Button
                            type="button"
                            label="Vytvoriť udalosť"
                            @click="openCreateChoiceFromButton"
                        />
                    </div>
                </div>
            </FormSection>

            <CalendarCreateChoiceDialog
                v-model:visible="createChoiceDialogVisible"
                :selection="pendingCalendarSelection"
                @close="closeCreateChoiceDialog"
                @continue="continueFromCreateChoice"
            />

            <BookingCreateDialog
                v-model:visible="createBookingDialogVisible"
                :services="services"
                :selection="pendingCalendarSelection"
                @close="closeCreateBookingDialog"
                @create-booking="createAdminBooking"
            />

            <BookingEditDialog
                v-model:visible="bookingDialogVisible"
                :booking="selectedBooking"
                :booking-notes="bookingNotes"
                :services="services"
                :available-slots="selectedBooking ? availableSlotsForBooking(selectedBooking) : []"
                @update-booking="updateBooking"
                @cancel-booking="cancelBooking"
                @reschedule-booking="rescheduleBooking"
            />

            <AvailabilityRuleCreateEditDialog
                v-model:visible="availabilityRuleDialogVisible"
                :current-rule="currentRule"
                :selected-rule-occurrence="selectedRuleOccurrence"
                :services="services"
                :repeat-unit-options="repeatUnitOptions"
                :loading="ruleForm.processing"
                :get-rule-title="getRuleTitle"
                :get-repeat-label="getRepeatLabel"
                @close="closeRuleDialog"
                @save="saveRules"
                @delete-occurrence="deleteCurrentRuleOccurrence"
                @delete-from-now-on="deleteCurrentRuleFromNowOn"
                @delete-all="deleteCurrentRuleEverywhere"
            />

            <GroupEventCreateEditDialog
                v-model:visible="groupEventDialogVisible"
                :current-rule="currentRule"
                :selected-rule-occurrence="selectedRuleOccurrence"
                :services="services"
                :repeat-unit-options="repeatUnitOptions"
                :loading="ruleForm.processing"
                :get-rule-title="getRuleTitle"
                :get-repeat-label="getRepeatLabel"
                @close="closeRuleDialog"
                @save="saveRules"
                @delete-occurrence="deleteCurrentRuleOccurrence"
                @delete-from-now-on="deleteCurrentRuleFromNowOn"
                @delete-all="deleteCurrentRuleEverywhere"
            />

            <GroupEventOccurrenceDialog
                v-model:visible="groupEventOccurrenceDialogVisible"
                :capacity-window="selectedCapacityWindow"
                :booking-notes="bookingNotes"
                :available-slots="availableRescheduleSlots"
                @add-patient-to-capacity-window="addPatientToCapacityWindow"
                @cancel-booking="cancelBooking"
                @cancel-capacity-window="cancelCapacityWindow"
                @reschedule-capacity-window="rescheduleCapacityWindow"
                @delete-capacity-window-occurrence="deleteCapacityWindowOccurrence"
                @delete-capacity-window-from-date="deleteCapacityWindowFromDate"
                @delete-capacity-window-series="deleteCapacityWindowSeries"
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
        </div>
    </AdminLayout>
</template>

<style scoped>
.appointment-request-card:active,
.appointment-request-card:active * {
    color: #ffffff !important;
}

.appointment-request-card:active .request-card-soft-box {
    background: rgba(255, 255, 255, 0.15);
}

.appointment-request-card:active :deep(.p-tag) {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

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
    border: 1px dashed #FFE5E5;
    background: #FFE5E5;
    border-radius: 8px;
    padding: 2px 4px;
    opacity: 0.75;
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
</style>