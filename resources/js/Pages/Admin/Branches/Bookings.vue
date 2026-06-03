<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

import CalendarCreateChoiceDialog from '@/Components/Booking/CalendarCreateChoiceDialog.vue';
import BookingCreateDialog from '@/Components/Booking/BookingCreateDialog.vue';
import BookingEditDialog from '@/Components/Booking/BookingEditDialog.vue';
import AvailabilityRuleCreateEditDialog from '@/Components/Booking/AvailabilityRuleCreateEditDialog.vue';
import GroupEventCreateEditDialog from '@/Components/Booking/GroupEventCreateEditDialog.vue';
import GroupEventOccurrenceDialog from '@/Components/Booking/GroupEventOccurrenceDialog.vue';

import { useBookingCalendar } from '@/Composables/Bookings/useBookingCalendar';

import FullCalendar from '@fullcalendar/vue3';
import { Draggable } from '@fullcalendar/interaction';

import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

const requestSidebar = ref(null);

let requestDraggable = null;

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

const getRequestPeriodLabel = (request) => {
    return periodLabels[request.preferred_period] ?? request.preferred_period;
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
    if (!requestSidebar.value) {
        return;
    }

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
});

onBeforeUnmount(() => {
    requestDraggable?.destroy();
});
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <FormSection
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-end gap-4">
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

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
                        <aside
                            ref="requestSidebar"
                            class="space-y-3 rounded-xl border border-soft bg-white p-4"
                        >
                            <div>
                                <h2 class="text-base font-semibold text-dark">
                                    Čakajúce žiadosti
                                </h2>

                                <p class="text-sm text-normal">
                                    Presuňte žiadosť do kalendára.
                                </p>
                            </div>

                            <div
                                v-if="pendingRequests.length"
                                class="space-y-3"
                            >
                                <article
                                    v-for="request in pendingRequests"
                                    :key="request.id"
                                    :data-request-id="request.id"
                                    class="appointment-request-card cursor-grab rounded-lg border border-soft bg-soft p-3 shadow-sm active:cursor-grabbing"
                                >
                                    <div class="space-y-2">
                                        <div>
                                            <h3 class="font-semibold text-dark">
                                                {{ request.patient_name }}
                                            </h3>

                                            <p class="text-xs text-normal">
                                                {{ getRequestServicesLabel(request) }}
                                            </p>
                                        </div>

                                        <div class="text-sm text-normal">
                                            <p>
                                                {{ request.total_duration_minutes }} min
                                            </p>

                                            <p>
                                                {{ request.preferred_date }} · {{ getRequestPeriodLabel(request) }}
                                            </p>
                                        </div>

                                        <div class="space-y-1 text-xs text-normal">
                                            <p v-if="request.patient_phone">
                                                {{ request.patient_phone }}
                                            </p>

                                            <p v-if="request.patient_email">
                                                {{ request.patient_email }}
                                            </p>
                                        </div>

                                        <p
                                            v-if="request.patient_note"
                                            class="rounded-md bg-white p-2 text-xs text-normal"
                                        >
                                            {{ request.patient_note }}
                                        </p>
                                    </div>
                                </article>
                            </div>

                            <div
                                v-else
                                class="rounded-lg bg-soft p-3 text-sm text-normal"
                            >
                                Žiadne čakajúce žiadosti.
                            </div>
                        </aside>

                        <div class="booking-calendar min-w-0">
                            <FullCalendar :options="calendarOptions" />
                        </div>
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
        </div>
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