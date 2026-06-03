<script setup>
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
    booking: {
        type: Object,
        default: null,
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
    services: {
        type: Array,
        default: () => [],
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

const form = reactive({
    service_id: null,
    starts_at: null,
    notify_patient: false,
    notification_reason: '',
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable ?? true)
        .map((service) => ({
            label: service.name,
            value: service.id,
        }));
});

const selectedService = computed(() => {
    return props.services.find((service) => {
        return Number(service.id) === Number(form.service_id);
    }) ?? null;
});

const selectedServiceDuration = computed(() => {
    const service = selectedService.value;
    if (!service) return null;
    return Number(service.duration_minutes ?? service.duration ?? service.length_minutes ?? service.minutes ?? 0);
});

const calculatedEndPreview = computed(() => {
    if (!form.starts_at || !selectedServiceDuration.value) return null;
    const end = new Date(form.starts_at);
    end.setMinutes(end.getMinutes() + selectedServiceDuration.value);
    return end;
});

const bookingDateModel = computed({
    get: () => form.starts_at ? new Date(form.starts_at) : null,
    set: (value) => {
        if (!value) {
            form.starts_at = null;

            return;
        }

        const current = form.starts_at ? new Date(form.starts_at) : new Date(value);
        current.setFullYear(value.getFullYear(), value.getMonth(), value.getDate());
        form.starts_at = current;
    },
});

const bookingStartTimeModel = computed({
    get: () => form.starts_at ? new Date(form.starts_at) : null,
    set: (value) => {
        if (!value) {
            form.starts_at = null;

            return;
        }

        const current = form.starts_at ? new Date(form.starts_at) : new Date();
        current.setHours(value.getHours(), value.getMinutes(), 0, 0);
        form.starts_at = current;
    },
});

const hasPatientEmail = computed(() => Boolean(props.booking?.patient_email));
const canNotifyPatient = computed(() => hasPatientEmail.value);

const bookingStatusLabel = computed(() => {
    const labels = {
        pending: 'Čaká na potvrdenie',
        confirmed: 'Potvrdená',
        completed: 'Dokončená',
        cancelled: 'Zrušená',
        no_show: 'No-show',
    };
    return labels[props.booking?.status] ?? props.booking?.status ?? '—';
});

const bookingStatusSeverity = computed(() => {
    const severities = {
        pending: 'warn',
        confirmed: 'success',
        completed: 'secondary',
        cancelled: 'danger',
        no_show: 'contrast',
    };
    return severities[props.booking?.status] ?? 'secondary';
});

const patientInitials = computed(() => {
    const name = String(props.booking?.patient_name ?? '').trim();
    if (!name) return '?';
    return name.split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
});

const formatTime = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' });
};

const formatDateForBackend = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:00`;
};

const originalStartsAtForBackend = computed(() => props.booking?.starts_at ? formatDateForBackend(props.booking.starts_at) : null);
const currentStartsAtForBackend = computed(() => form.starts_at ? formatDateForBackend(form.starts_at) : null);

const hasServiceChanged = computed(() => Boolean(props.booking) && Number(form.service_id) !== Number(props.booking.service_id));
const hasStartChanged = computed(() => Boolean(props.booking) && currentStartsAtForBackend.value !== originalStartsAtForBackend.value);
const hasBookingChanges = computed(() => hasServiceChanged.value || hasStartChanged.value);
const canSaveChanges = computed(() => Boolean(props.booking) && Boolean(form.service_id) && Boolean(form.starts_at) && Boolean(selectedServiceDuration.value) && hasBookingChanges.value);

const resetForm = () => {
    form.service_id = props.booking?.service_id ?? null;
    form.starts_at = props.booking?.starts_at ? new Date(props.booking.starts_at) : null;
    form.notify_patient = false;
    form.notification_reason = '';
};

watch(() => props.booking?.id, () => { resetForm(); }, { immediate: true });
watch(() => props.visible, (visible) => { if (visible) resetForm(); });
watch(() => props.booking?.patient_email, (email) => { if (!email) form.notify_patient = false; });
watch(hasBookingChanges, (changed) => { if (!changed) { form.notify_patient = false; form.notification_reason = ''; } });

const closeDialog = () => { emit('update:visible', false); };
const rescheduleBooking = () => {
    if (!props.booking || !canSaveChanges.value) return;
    emit('reschedule-booking', props.booking, {
        booking_slot_id: null,
        service_id: form.service_id,
        starts_at: formatDateForBackend(form.starts_at),
        notify_patient: canNotifyPatient.value && form.notify_patient,
        notification_reason: form.notification_reason,
    });
};

const cancelBooking = () => {
    if (!props.booking) return;
    emit('cancel-booking', props.booking, {
        notify_patient: canNotifyPatient.value && form.notify_patient,
        notification_reason: form.notification_reason,
    });
};

const updateStatus = (status) => {
    if (!props.booking) return;
    emit('update-booking', props.booking, status, { notify_patient: false, notification_reason: null });
};
</script>

<template>
    <EventDialog
        :visible="visible"
        v-model:date="bookingDateModel"
        v-model:starts-at="bookingStartTimeModel"
        :ends-at="calculatedEndPreview"
        date-id="booking_edit_date"
        starts-at-id="booking_edit_starts_at"
        ends-at-id="booking_edit_ends_at"
        starts-at-label="Začiatok"
        ends-at-label="Koniec"
        ends-at-placeholder="Dopočíta sa zo služby"
        readonly-ends-at
        disabled-ends-at
        save-label="Uložiť"
        :show-save="hasBookingChanges"
        :save-disabled="!canSaveChanges"
        show-delete
        delete-label="Odstrániť"
        delete-dialog-title="Zrušiť rezerváciu"
        delete-dialog-description="Táto rezervácia je jednorazová, preto je dostupná iba jedna možnosť."
        delete-one-label="Zrušiť túto rezerváciu"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @save="rescheduleBooking"
        @delete-occurrence="cancelBooking"
    >
        <div v-if="booking" class="space-y-6">
            <FormSection title="Pacient a rezervácia" columns="md:grid-cols-2">
                <PatientCard
                    class="md:col-span-2"
                    :patient="booking"
                    :status="bookingStatusLabel"
                    :service-name="selectedService?.name"
                >
                    <p v-if="booking.patient_note" class="mt-4 whitespace-pre-line rounded-lg border border-soft/60 bg-soft/40 p-3 text-sm leading-6 text-dark">
                        <span class="mb-1 block text-xs font-medium text-accent">Poznámka od pacienta:</span>
                        {{ booking.patient_note }}
                    </p>
                </PatientCard>

                <FormField label="Názov služby" for="reschedule_service_id" required span="md:col-span-2">
                    <Select
                        id="reschedule_service_id"
                        v-model="form.service_id"
                        :options="serviceOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                </FormField>

                <FormField label="Poznámka" :for="`booking_admin_note_${booking.id}`" span="md:col-span-2">
                    <Textarea
                        :id="`booking_admin_note_${booking.id}`"
                        v-model="bookingNotes[booking.id]"
                        rows="3"
                        class="w-full"
                        placeholder="Zadajte internú poznámku..."
                    />
                </FormField>

                <div v-if="hasBookingChanges" class="col-span-2 flex items-center gap-2">
                    <Checkbox
                        v-model="form.notify_patient"
                        binary
                        input-id="notify_patient_reschedule"
                        :disabled="!canNotifyPatient"
                    />

                    <label for="notify_patient_reschedule" class="cursor-pointer text-sm font-medium text-dark" :class="{ 'opacity-50': !canNotifyPatient }">
                        Poslať pacientovi email o zmene termínu
                    </label>

                    <span v-if="!canNotifyPatient" class="ml-auto text-xs font-medium text-red-500">
                        Pacient nemá priradený email
                    </span>
                </div>

                <FormField v-if="form.notify_patient" label="Dôvod zmeny pre pacienta" for="notification_reason" required span="md:col-span-2">
                    <Textarea
                        id="notification_reason"
                        v-model="form.notification_reason"
                        rows="2"
                        class="w-full"
                        placeholder="Napríklad: Termín presúvame z organizačných dôvodov."
                    />
                </FormField>
            </FormSection>
        </div>

        <div v-else class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-600">
            Rezerváciu sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>
