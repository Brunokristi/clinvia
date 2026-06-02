<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

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
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const rescheduleForms = reactive({});

const groupForm = reactive({
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
    groupForm.starts_at = null;
    groupForm.ends_at = null;
    groupForm.notify_patient = true;
    groupForm.notification_reason = '';
};

watch(
    () => props.capacityWindow?.id,
    () => {
        resetGroupForm();
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
    if (!props.capacityWindow || !capacityWindowDate.value) {
        return;
    }

    emit('cancel-capacity-window', props.capacityWindow, {
        date: capacityWindowDate.value,
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const rescheduleCapacityWindow = () => {
    if (!props.capacityWindow || !capacityWindowDate.value || !groupForm.starts_at || !groupForm.ends_at) {
        return;
    }

    emit('reschedule-capacity-window', props.capacityWindow, {
        date: capacityWindowDate.value,
        starts_at: formatDateForBackend(groupForm.starts_at),
        ends_at: formatDateForBackend(groupForm.ends_at),
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Kapacitné okno"
        :style="{ width: '980px', maxWidth: '95vw' }"
    >
        <div
            v-if="capacityWindow"
            class="space-y-5"
        >
            <div class="rounded-md bg-soft p-4">
                <p class="text-lg font-semibold text-dark">
                    {{ capacityWindow.service_name }}
                </p>

                <p class="mt-1 text-sm text-accent">
                    {{ formatDateTime(capacityWindow.starts_at) }}
                    –
                    {{ formatDateTime(capacityWindow.ends_at) }}
                </p>

                <p class="mt-1 text-sm text-accent">
                    Obsadené:
                    {{ bookings.length }}/{{ capacityWindow.capacity }}
                </p>
            </div>

            <div class="rounded-md border border-soft bg-white p-4">
                <p class="text-base font-semibold text-dark">
                    Hromadné akcie pre celé kapacitné okno
                </p>

                <p class="mt-1 text-sm text-accent">
                    Tieto akcie sa použijú na všetkých pacientov v tomto kapacitnom okne.
                </p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-dark">
                            Nový začiatok
                        </label>

                        <DatePicker
                            v-model="groupForm.starts_at"
                            show-time
                            hour-format="24"
                            class="w-full"
                            input-class="w-full"
                            placeholder="Vyberte nový začiatok"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-dark">
                            Nový koniec
                        </label>

                        <DatePicker
                            v-model="groupForm.ends_at"
                            show-time
                            hour-format="24"
                            class="w-full"
                            input-class="w-full"
                            placeholder="Vyberte nový koniec"
                        />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <Checkbox
                        v-model="groupForm.notify_patient"
                        binary
                        input-id="notify_capacity_window_patients"
                    />

                    <label
                        for="notify_capacity_window_patients"
                        class="cursor-pointer text-sm text-accent"
                    >
                        Poslať email všetkým pacientom
                    </label>
                </div>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Dôvod pre pacientov
                    </label>

                    <Textarea
                        v-model="groupForm.notification_reason"
                        rows="3"
                        class="w-full"
                        placeholder="Napríklad: Termín musíme presunúť z organizačných dôvodov."
                    />
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <Button
                        type="button"
                        label="Presunúť celé okno"
                        icon="pi pi-calendar"
                        :disabled="!groupForm.starts_at || !groupForm.ends_at || !bookings.length"
                        @click="rescheduleCapacityWindow"
                    />

                    <Button
                        type="button"
                        label="Zrušiť celé okno"
                        icon="pi pi-times"
                        severity="danger"
                        outlined
                        :disabled="!bookings.length"
                        @click="cancelCapacityWindow"
                    />
                </div>
            </div>

            <div
                v-if="bookings.length"
                class="space-y-4"
            >
                <div
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="rounded-md border border-soft bg-white p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-dark">
                                {{ booking.patient_name ?? 'Bez mena' }}
                            </p>

                            <p class="mt-1 text-sm text-accent">
                                Stav: {{ booking.status ?? '—' }}
                            </p>

                            <p
                                v-if="booking.patient_email"
                                class="mt-1 text-sm text-accent"
                            >
                                Email: {{ booking.patient_email }}
                            </p>

                            <p
                                v-if="booking.patient_phone"
                                class="mt-1 text-sm text-accent"
                            >
                                Telefón: {{ booking.patient_phone }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
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
                    </div>

                    <div class="mt-4">
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

                    <div class="mt-4 rounded-md bg-soft/50 p-4">
                        <p class="mb-3 text-sm font-semibold text-dark">
                            Presunúť rezerváciu
                        </p>

                        <div class="space-y-3">
                            <Select
                                v-model="ensureForm(booking).booking_slot_id"
                                :options="slotOptionsForBooking(booking)"
                                option-label="label"
                                option-value="value"
                                placeholder="Vyberte nový dostupný termín"
                                class="w-full"
                            />

                            <div class="flex items-center gap-2">
                                <Checkbox
                                    v-model="ensureForm(booking).notify_patient"
                                    binary
                                    :input-id="`notify_patient_${booking.id}`"
                                />

                                <label
                                    :for="`notify_patient_${booking.id}`"
                                    class="cursor-pointer text-sm text-accent"
                                >
                                    Poslať pacientovi email
                                </label>
                            </div>

                            <Textarea
                                v-model="ensureForm(booking).notification_reason"
                                rows="2"
                                class="w-full"
                                placeholder="Dôvod zmeny pre pacienta"
                            />

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
                </div>
            </div>

            <div
                v-else
                class="rounded-md bg-soft p-4 text-sm text-accent"
            >
                V tomto kapacitnom okne zatiaľ nie sú žiadne rezervácie.
            </div>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Kapacitné okno sa nepodarilo načítať.
        </div>
    </Dialog>
</template>