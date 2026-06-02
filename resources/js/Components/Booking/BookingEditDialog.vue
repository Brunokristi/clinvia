<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    booking: {
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
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const selectedSlotId = ref(null);
const notifyPatient = ref(true);
const notificationReason = ref('');

watch(() => props.booking?.id, () => {
    selectedSlotId.value = null;
    notifyPatient.value = true;
    notificationReason.value = '';
});

const slotOptions = computed(() => {
    return props.availableSlots.map((slot) => ({
        label: slot.label ?? formatSlotLabel(slot),
        value: slot.id,
    }));
});

const canReschedule = computed(() => {
    return Boolean(selectedSlotId.value);
});

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

const updateStatus = (status) => {
    if (!props.booking) {
        return;
    }

    emit('update-booking', props.booking, status, {
        notify_patient: false,
        notification_reason: null,
    });
};

const cancelBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: notifyPatient.value,
        notification_reason: notificationReason.value,
    });
};

const rescheduleBooking = () => {
    if (!props.booking || !selectedSlotId.value) {
        return;
    }

    emit('reschedule-booking', props.booking, {
        booking_slot_id: selectedSlotId.value,
        notify_patient: notifyPatient.value,
        notification_reason: notificationReason.value,
    });
};
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Upraviť rezerváciu"
        :style="{ width: '760px', maxWidth: '95vw' }"
    >
        <div
            v-if="booking"
            class="space-y-5"
        >
            <div class="rounded-md bg-soft p-4">
                <p class="text-lg font-semibold text-dark">
                    {{ booking.patient_name ?? 'Bez mena' }}
                </p>

                <p class="mt-1 text-sm text-accent">
                    {{ booking.service_name ?? 'Bez služby' }}
                </p>

                <p class="mt-1 text-sm text-accent">
                    {{ formatDateTime(booking.starts_at) }}
                    –
                    {{ formatDateTime(booking.ends_at) }}
                </p>

                <p class="mt-1 text-sm text-accent">
                    Stav: {{ booking.status ?? '—' }}
                </p>
            </div>

            <div
                v-if="booking.patient_email || booking.patient_phone"
                class="rounded-md border border-soft bg-white p-4"
            >
                <p class="mb-3 text-sm font-semibold text-dark">
                    Kontakt
                </p>

                <div class="space-y-2 text-sm text-accent">
                    <p v-if="booking.patient_email">
                        Email: {{ booking.patient_email }}
                    </p>

                    <p v-if="booking.patient_phone">
                        Telefón: {{ booking.patient_phone }}
                    </p>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-dark">
                    Admin poznámka
                </label>

                <Textarea
                    v-model="bookingNotes[booking.id]"
                    rows="3"
                    class="w-full"
                    placeholder="Admin poznámka"
                />
            </div>

            <div class="rounded-md border border-soft bg-white p-4">
                <p class="mb-3 text-sm font-semibold text-dark">
                    Presunúť rezerváciu
                </p>

                <div class="space-y-3">
                    <Select
                        v-model="selectedSlotId"
                        :options="slotOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte nový dostupný termín"
                        class="w-full"
                    />

                    <div class="flex items-center gap-2">
                        <Checkbox
                            v-model="notifyPatient"
                            binary
                            input-id="notify_patient_reschedule"
                        />

                        <label
                            for="notify_patient_reschedule"
                            class="cursor-pointer text-sm text-accent"
                        >
                            Poslať pacientovi email
                        </label>
                    </div>

                    <Textarea
                        v-model="notificationReason"
                        rows="2"
                        class="w-full"
                        placeholder="Dôvod zmeny pre pacienta"
                    />

                    <Button
                        type="button"
                        label="Presunúť rezerváciu"
                        icon="pi pi-calendar"
                        :disabled="!canReschedule"
                        @click="rescheduleBooking"
                    />
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-soft pt-5">
                <Button
                    type="button"
                    label="Potvrdené"
                    severity="success"
                    outlined
                    @click="updateStatus('confirmed')"
                />

                <Button
                    type="button"
                    label="Dokončené"
                    severity="secondary"
                    outlined
                    @click="updateStatus('completed')"
                />

                <Button
                    type="button"
                    label="No-show"
                    severity="warn"
                    outlined
                    @click="updateStatus('no_show')"
                />

                <Button
                    type="button"
                    label="Zrušiť a poslať email"
                    severity="danger"
                    outlined
                    @click="cancelBooking"
                />
            </div>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Rezerváciu sa nepodarilo načítať.
        </div>
    </Dialog>
</template>