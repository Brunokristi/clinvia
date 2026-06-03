<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    capacityWindow: {
        type: Object,
        default: null,
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
    availableSlots: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'update:visible',
    'update-booking',
    'cancel-booking',
    'reschedule-booking',
    'cancel-capacity-window',
    'reschedule-capacity-window',
    'delete-capacity-window-occurrence',
    'delete-capacity-window-from-date',
    'delete-capacity-window-series',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const rescheduleForms = reactive({});

const groupForm = reactive({
    date: null,
    starts_at: null,
    ends_at: null,
    notify_patient: true,
    notification_reason: '',
});

const bookings = computed(() => {
    return props.capacityWindow?.bookings ?? [];
});

const capacityWindowDate = computed(() => {
    if (!props.capacityWindow?.date && !props.capacityWindow?.starts_at) {
        return null;
    }

    return String(props.capacityWindow.date ?? props.capacityWindow.starts_at).slice(0, 10);
});

const resetGroupForm = () => {
    groupForm.date = capacityWindowDate.value ? new Date(`${capacityWindowDate.value}T00:00:00`) : null;
    groupForm.starts_at = props.capacityWindow?.starts_at ? new Date(props.capacityWindow.starts_at) : null;
    groupForm.ends_at = props.capacityWindow?.ends_at ? new Date(props.capacityWindow.ends_at) : null;
    groupForm.notify_patient = true;
    groupForm.notification_reason = '';
};

watch(
    () => props.capacityWindow?.id,
    () => {
        resetGroupForm();
    },
    { immediate: true },
);

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetGroupForm();
        }
    },
);

const ensureForm = (booking) => {
    if (!rescheduleForms[booking.id]) {
        rescheduleForms[booking.id] = {
            booking_slot_id: null,
            notify_patient: true,
            notification_reason: '',
        };
    }

    return rescheduleForms[booking.id];
};

const slotOptionsForBooking = (booking) => {
    return props.availableSlots
        .filter((slot) => {
            return Number(slot.service_id) === Number(booking.service_id)
                && Number(slot.id) !== Number(booking.booking_slot_id);
        })
        .map((slot) => ({
            label: slot.label ?? formatSlotLabel(slot),
            value: slot.id,
        }));
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatSlotLabel = (slot) => {
    const start = formatDateTime(slot.starts_at);
    const end = new Date(slot.ends_at).toLocaleTimeString('sk-SK', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return `${start} - ${end}`;
};

const formatDateOnlyForBackend = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const mergeDateAndTime = (dateValue, timeValue) => {
    if (!dateValue || !timeValue) {
        return null;
    }

    const date = dateValue instanceof Date
        ? new Date(dateValue)
        : new Date(dateValue);

    const time = timeValue instanceof Date
        ? timeValue
        : new Date(timeValue);

    date.setHours(time.getHours(), time.getMinutes(), 0, 0);

    return date;
};

const selectedDateForBackend = computed(() => {
    return formatDateOnlyForBackend(groupForm.date) ?? capacityWindowDate.value;
});

const isCapacityWindowRepeatable = computed(() => {
    return Boolean(
        props.capacityWindow?.repeats
            ?? props.capacityWindow?.is_recurring
            ?? props.capacityWindow?.repeat_unit
            ?? props.capacityWindow?.series_id
            ?? props.capacityWindow?.rule_id
            ?? true,
    );
});

const formatDateForBackend = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day} ${hours}:${minutes}:00`;
};

const closeDialog = () => {
    emit('update:visible', false);
};

const updateStatus = (booking, status) => {
    emit('update-booking', booking, status, {
        notify_patient: false,
        notification_reason: null,
    });
};

const cancelBooking = (booking) => {
    const form = ensureForm(booking);

    emit('cancel-booking', booking, {
        notify_patient: form.notify_patient,
        notification_reason: form.notification_reason,
    });
};

const rescheduleBooking = (booking) => {
    const form = ensureForm(booking);

    if (!form.booking_slot_id) {
        return;
    }

    emit('reschedule-booking', booking, {
        booking_slot_id: form.booking_slot_id,
        notify_patient: form.notify_patient,
        notification_reason: form.notification_reason,
    });
};

const cancelCapacityWindow = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('cancel-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const rescheduleCapacityWindow = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value || !groupForm.starts_at || !groupForm.ends_at) {
        return;
    }

    emit('reschedule-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        starts_at: formatDateForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const deleteCapacityWindowOccurrence = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('delete-capacity-window-occurrence', props.capacityWindow, {
        date: selectedDateForBackend.value,
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const deleteCapacityWindowFromDate = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('delete-capacity-window-from-date', props.capacityWindow, {
        date: selectedDateForBackend.value,
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const deleteCapacityWindowSeries = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('delete-capacity-window-series', props.capacityWindow, {
        date: selectedDateForBackend.value,
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};
</script>

<template>
    <EventDialog
        v-model:visible="dialogVisible"
        v-model:date="groupForm.date"
        v-model:starts-at="groupForm.starts_at"
        v-model:ends-at="groupForm.ends_at"
        width="max-w-5xl"
        date-id="capacity_window_date"
        starts-at-id="capacity_window_starts_at"
        ends-at-id="capacity_window_ends_at"
        save-label="Uložiť"
        :show-save="Boolean(capacityWindow)"
        :save-disabled="!groupForm.starts_at || !groupForm.ends_at"
        show-delete
        :delete-disabled="!capacityWindow"
        :is-repeatable="isCapacityWindowRepeatable"
        :occurrence-date="selectedDateForBackend"
        delete-dialog-title="Odstrániť skupinový termín"
        delete-dialog-description="Vyberte, ako chcete odstrániť tento skupinový termín."
        delete-one-label="Odstrániť iba tento termín"
        delete-future-label="Odstrániť tento a všetky budúce termíny"
        delete-all-label="Odstrániť celú sériu"
        @close="closeDialog"
        @save="rescheduleCapacityWindow"
        @delete-occurrence="deleteCapacityWindowOccurrence"
        @delete-from-now-on="deleteCapacityWindowFromDate"
        @delete-all="deleteCapacityWindowSeries"
    >
        <div v-if="capacityWindow" class="space-y-6">
            <FormSection
                title="Skupinový termín"
                description="Tu upravíte celý skupinový termín a správanie oznámení pre pacientov."
                columns="md:grid-cols-2"
            >
                <div class="rounded-md bg-soft p-4 text-sm leading-6 text-accent md:col-span-2">
                    <strong class="text-dark">Obsadenosť:</strong>
                    {{ bookings.length }} / {{ capacityWindow.bookable_places ?? capacityWindow.capacity ?? '—' }}
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <Checkbox
                        v-model="groupForm.notify_patient"
                        binary
                        input-id="capacity_notify_patient"
                        :disabled="!bookings.length"
                    />

                    <label
                        for="capacity_notify_patient"
                        class="cursor-pointer text-sm text-accent"
                        :class="{ 'opacity-50': !bookings.length }"
                    >
                        Poslať pacientom email pri zmene alebo odstránení
                    </label>
                </div>

                <FormField label="Dôvod zmeny" for="capacity_notification_reason" span="md:col-span-2">
                    <Textarea
                        id="capacity_notification_reason"
                        v-model="groupForm.notification_reason"
                        rows="3"
                        class="w-full"
                        placeholder="Napríklad: Termín musíme presunúť z organizačných dôvodov."
                    />
                </FormField>

                <div class="md:col-span-2">
                    <Button
                        type="button"
                        label="Zrušiť celý termín"
                        icon="pi pi-times"
                        severity="danger"
                        outlined
                        :disabled="!bookings.length"
                        @click="cancelCapacityWindow"
                    />
                </div>
            </FormSection>

            <FormSection
                v-if="bookings.length"
                title="Rezervácie v skupinovom termíne"
                description="Tu môžete upraviť stav, poznámku alebo presunúť jednotlivé rezervácie."
                columns="md:grid-cols-1"
            >
                <div class="space-y-4">
                    <PatientCard
                        v-for="booking in bookings"
                        :key="booking.id"
                        :patient="booking"
                        :status="booking.status ?? '—'"
                    >
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                label="Potvrdené"
                                severity="success"
                                outlined
                                size="small"
                                @click="updateStatus(booking, 'confirmed')"
                            />

                            <Button
                                type="button"
                                label="Dokončené"
                                severity="secondary"
                                outlined
                                size="small"
                                @click="updateStatus(booking, 'completed')"
                            />

                            <Button
                                type="button"
                                label="No-show"
                                severity="warn"
                                outlined
                                size="small"
                                @click="updateStatus(booking, 'no_show')"
                            />

                            <Button
                                type="button"
                                label="Zrušiť"
                                severity="danger"
                                outlined
                                size="small"
                                @click="cancelBooking(booking)"
                            />
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <FormField label="Admin poznámka" :for="`capacity_booking_note_${booking.id}`" span="md:col-span-2">
                                <Textarea
                                    :id="`capacity_booking_note_${booking.id}`"
                                    v-model="bookingNotes[booking.id]"
                                    rows="3"
                                    class="w-full"
                                    placeholder="Admin poznámka"
                                />
                            </FormField>

                            <FormField label="Nový dostupný termín" :for="`capacity_booking_slot_${booking.id}`" span="md:col-span-2">
                                <Select
                                    :id="`capacity_booking_slot_${booking.id}`"
                                    v-model="ensureForm(booking).booking_slot_id"
                                    :options="slotOptionsForBooking(booking)"
                                    option-label="label"
                                    option-value="value"
                                    placeholder="Vyberte nový dostupný termín"
                                    class="w-full"
                                />
                            </FormField>

                            <div class="md:col-span-2 flex items-center gap-2">
                                <Checkbox
                                    v-model="ensureForm(booking).notify_patient"
                                    binary
                                    :input-id="`notify_patient_${booking.id}`"
                                />

                                <label :for="`notify_patient_${booking.id}`" class="cursor-pointer text-sm text-accent">
                                    Poslať pacientovi email
                                </label>
                            </div>

                            <FormField label="Dôvod zmeny" :for="`capacity_booking_notification_reason_${booking.id}`" span="md:col-span-2">
                                <Textarea
                                    :id="`capacity_booking_notification_reason_${booking.id}`"
                                    v-model="ensureForm(booking).notification_reason"
                                    rows="2"
                                    class="w-full"
                                    placeholder="Dôvod zmeny pre pacienta"
                                />
                            </FormField>

                            <div class="md:col-span-2">
                                <Button
                                    type="button"
                                    label="Presunúť rezerváciu"
                                    icon="pi pi-calendar"
                                    size="small"
                                    :disabled="!ensureForm(booking).booking_slot_id"
                                    @click="rescheduleBooking(booking)"
                                />
                            </div>
                        </div>
                    </PatientCard>
                </div>
            </FormSection>

            <div v-else class="rounded-md bg-soft p-4 text-sm text-accent">
                V tomto skupinovom termíne zatiaľ nie sú žiadne rezervácie.
            </div>
        </div>

        <div v-else class="rounded-md bg-soft p-4 text-sm text-accent">
            Skupinový termín sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>
