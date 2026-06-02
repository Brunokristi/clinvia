<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import BookingRuleDialog from '@/Components/Booking/BookingRuleDialog.vue';
import BookingDialog from '@/Components/Booking/BookingDialog.vue';
import CapacityWindowDialog from '@/Components/Booking/CapacityWindowDialog.vue';
import AdminBookingCreateDialog from '@/Components/Booking/AdminBookingCreateDialog.vue';
import { useBookingCalendar } from '@/Composables/useBookingCalendar';

import FullCalendar from '@fullcalendar/vue3';

import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';

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

    ruleDialogVisible,
    bookingDialogVisible,
    capacityWindowDialogVisible,
    createBookingDialogVisible,

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

    deleteCurrentRuleOccurrence,
    deleteCurrentRuleFromNowOn,
    deleteCurrentRuleEverywhere,

    cancelCapacityWindow,
    rescheduleCapacityWindow,
} = useBookingCalendar(props);
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-md bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-accent/60">
                        Dnešné rezervácie
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-dark">
                        {{ todayBookingsCount }}
                    </p>
                </div>

                <div class="rounded-md bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-accent/60">
                        Pravidlá dostupnosti
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-dark">
                        {{ ruleForm.rules.length }}
                    </p>
                </div>

                <div class="rounded-md bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-accent/60">
                        Neprečítané správy
                    </p>

                    <p class="mt-2 text-2xl font-semibold text-dark">
                        {{ unreadMessagesCount }}
                    </p>
                </div>
            </div>

            <FormSection
                title="Kalendár rezervácií"
                description="Pre kapacitné služby sa v kalendári zobrazuje jedno časové okno s počtom prihlásených klientov."
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-md bg-white p-4 shadow-sm">
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
                            label="Vytvoriť rezerváciu"
                            icon="pi pi-plus"
                            @click="openCreateBookingDialog"
                        />
                    </div>

                    <div class="booking-calendar rounded-md bg-white p-4 shadow-sm">
                        <FullCalendar :options="calendarOptions" />
                    </div>
                </div>
            </FormSection>

            <BookingRuleDialog
                v-model:visible="ruleDialogVisible"
                :current-rule="currentRule"
                :selected-rule-occurrence="selectedRuleOccurrence"
                :services="services"
                :slot-mode-options="slotModeOptions"
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

            <BookingDialog
                v-model:visible="bookingDialogVisible"
                :booking="selectedBooking"
                :booking-notes="bookingNotes"
                :available-slots="selectedBooking ? availableSlotsForBooking(selectedBooking) : []"
                @update-booking="updateBooking"
                @cancel-booking="cancelBooking"
                @reschedule-booking="rescheduleBooking"
            />

            <CapacityWindowDialog
                v-model:visible="capacityWindowDialogVisible"
                :capacity-window="selectedCapacityWindow"
                :booking-notes="bookingNotes"
                :available-slots="availableRescheduleSlots"
                @update-booking="updateBooking"
                @cancel-booking="cancelBooking"
                @reschedule-booking="rescheduleBooking"
                @cancel-capacity-window="cancelCapacityWindow"
                @reschedule-capacity-window="rescheduleCapacityWindow"
            />

            <AdminBookingCreateDialog
                v-model:visible="createBookingDialogVisible"
                :services="services"
                :available-slots="availableRescheduleSlots"
                @close="closeCreateBookingDialog"
                @create-booking="createAdminBooking"
            />
        </div>
    </AdminLayout>
</template>

<style scoped>
.booking-calendar :deep(.fc) {
    font-family: inherit;
}

.booking-calendar :deep(.fc-toolbar-title) {
    font-size: 18px;
    font-weight: 700;
    color: #2f172a;
}

.booking-calendar :deep(.fc-button) {
    border: 0;
    border-radius: 8px;
    background: #ffe5e5;
    color: #a75a5a;
    font-size: 13px;
    font-weight: 700;
    box-shadow: none;
}

.booking-calendar :deep(.fc-button:hover),
.booking-calendar :deep(.fc-button-primary:not(:disabled).fc-button-active) {
    background: #c17979;
    color: #ffffff;
}

.booking-calendar :deep(.fc-timegrid-slot) {
    height: 36px;
}

.booking-calendar :deep(.fc-col-header-cell) {
    padding: 10px 0;
    font-size: 13px;
    font-weight: 700;
    color: #2f172a;
}

.booking-calendar :deep(.fc-timegrid-axis),
.booking-calendar :deep(.fc-timegrid-slot-label) {
    font-size: 12px;
    color: #a75a5a;
}

.booking-calendar :deep(.booking-rule-free-time) {
    border: 1px solid #a75a5a;
    background: #fff4f4;
    color: #2f172a;
    border-radius: 8px;
    padding: 2px 4px;
}

.booking-calendar :deep(.booking-reservation-event) {
    border: 1px solid #2f172a;
    background: #c17979;
    color: #ffffff;
    border-radius: 8px;
    padding: 2px 4px;
}

.booking-calendar :deep(.booking-capacity-window-event) {
    border: 1px solid #7c2d2d;
    background: #a75a5a;
    color: #ffffff;
    border-radius: 8px;
    padding: 2px 4px;
}

.booking-calendar :deep(.booking-capacity-window-full) {
    opacity: 0.75;
}

.booking-calendar :deep(.booking-capacity-window-event .fc-event-title) {
    font-weight: 800;
}

.booking-calendar :deep(.fc-event-title) {
    font-size: 12px;
    font-weight: 600;
}

.booking-calendar :deep(.fc-event-time) {
    font-size: 11px;
}

.booking-calendar :deep(.booking-rule-free-time) {
    z-index: 1;
}

.booking-calendar :deep(.booking-capacity-window-event) {
    z-index: 20;
    cursor: pointer;
}

.booking-calendar :deep(.booking-reservation-event) {
    z-index: 30;
    cursor: pointer;
}
</style>