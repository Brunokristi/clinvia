<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

import AppDialog from '@/Components/Dialogs/FormDialog.vue';
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
    <AppDialog
        :visible="visible"
        title="Detail rezervácie"
        width="max-w-3xl"
        show-footer
        close-label="Zavrieť"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
    >
        <div v-if="booking" class="space-y-6">
            
            <FormSection title="Termín" description="Upravte dátum a čas začiatku rezervácie.">
                <FormField label="Dátum a čas rezervácie" for="reschedule_starts_at" required>
                    <DatePicker
                        input-id="reschedule_starts_at"
                        v-model="form.starts_at"
                        show-time
                        hour-format="24"
                        date-format="dd.mm.yy"
                        class="w-full"
                        input-class="w-full"
                        placeholder="Vyberte nový začiatok"
                    />
                </FormField>
            </FormSection>

            <div class="rounded-xl border border-soft bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-xs uppercase tracking-wider font-semibold text-accent">Pacient</span>
                    <Tag :severity="bookingStatusSeverity">{{ bookingStatusLabel }}</Tag>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-4">
                        <Avatar
                            :label="patientInitials"
                            shape="circle"
                            size="large"
                            class="shrink-0 bg-soft text-dark font-semibold"
                        />
                        <div class="min-w-0">
                            <h3 class="text-xl font-semibold text-dark">
                                {{ booking.patient_name ?? 'Bez mena' }}
                            </h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Tag v-if="booking.patient_email" severity="secondary" icon="pi pi-envelope">
                                    {{ booking.patient_email }}
                                </Tag>
                                <Tag v-if="booking.patient_phone" severity="secondary" icon="pi pi-phone">
                                    {{ booking.patient_phone }}
                                </Tag>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="booking.patient_note" class="mt-4 whitespace-pre-line rounded-lg bg-soft/40 p-3 text-sm leading-6 text-dark border border-soft/60">
                    <span class="block font-medium text-xs text-accent mb-1">Poznámka od pacienta:</span>
                    "{{ booking.patient_note }}"
                </p>
            </div>

            <FormSection title="Služba" description="Vyberte typ poskytovanej služby.">
                <FormField label="Názov služby" for="reschedule_service_id" required>
                    <Select
                        id="reschedule_service_id"
                        v-model="form.service_id"
                        :options="serviceOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                    <div v-if="calculatedEndPreview" class="mt-2 text-xs text-muted-color">
                        Predpokladaný koniec: <span class="font-medium text-dark">{{ formatTime(calculatedEndPreview) }}</span> ({{ selectedServiceDuration }} min.)
                    </div>
                </FormField>
            </FormSection>

            <FormSection title="Interná poznámka" description="Poznámky prístupné iba pre personál.">
                <FormField label="Poznámka" :for="`booking_admin_note_${booking.id}`">
                    <Textarea
                        :id="`booking_admin_note_${booking.id}`"
                        v-model="bookingNotes[booking.id]"
                        rows="3"
                        class="w-full"
                        placeholder="Zadajte internú poznámku..."
                    />
                </FormField>
            </FormSection>

            <div v-if="hasBookingChanges" class="rounded-xl border border-primary/20 bg-primary/5 p-5 space-y-4">
                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="form.notify_patient"
                        binary
                        input-id="notify_patient_reschedule"
                        :disabled="!canNotifyPatient"
                    />
                    <label for="notify_patient_reschedule" class="cursor-pointer text-sm font-medium text-dark" :class="{ 'opacity-50': !canNotifyPatient }">
                        Poslať pacientovi email o zmene termínu
                    </label>
                    <span v-if="!canNotifyPatient" class="ml-auto text-xs text-red-500 font-medium">
                        Pacient nemá priradený email
                    </span>
                </div>

                <FormField v-if="form.notify_patient" label="Dôvod zmeny pre pacienta" for="notification_reason" required>
                    <Textarea
                        id="notification_reason"
                        v-model="form.notification_reason"
                        rows="2"
                        class="w-full"
                        placeholder="Napríklad: Termín presúvame z organizačných dôvodov."
                    />
                </FormField>

                <div class="flex items-center justify-between pt-2 border-t border-soft">
                    <span class="text-xs text-accent">Máte neuložené zmeny v rezervácii</span>
                    <Button
                        type="button"
                        label="Uložiť zmeny"
                        icon="pi pi-save"
                        :disabled="!canSaveChanges"
                        @click="rescheduleBooking"
                    />
                </div>
            </div>

            <div class="pt-4 border-t border-soft flex flex-wrap items-center justify-between gap-4">
                <details class="group rounded-xl border border-soft bg-white p-3 w-full md:w-auto md:min-w-[250px]">
                    <summary class="cursor-pointer text-sm font-semibold text-dark flex items-center justify-between list-none">
                        <span>Ďalšie rýchle akcie</span>
                        <i class="pi pi-chevron-down text-xs transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="mt-3 flex flex-col gap-2">
                        <Button type="button" label="Označiť ako Potvrdené" severity="success" text size="small" icon="pi pi-check" @click="updateStatus('confirmed')" />
                        <Button type="button" label="Označiť ako Dokončené" severity="secondary" text size="small" icon="pi pi-check-square" @click="updateStatus('completed')" />
                        <Button type="button" label="Označiť ako No-show" severity="warn" text size="small" icon="pi pi-eye-slash" @click="updateStatus('no_show')" />
                    </div>
                </details>

                <Button
                    type="button"
                    label="Zrušiť rezerváciu"
                    icon="pi pi-trash"
                    severity="danger"
                    outlined
                    class="w-full md:w-auto ml-auto"
                    @click="cancelBooking"
                />
            </div>

        </div>

        <div v-else class="rounded-md bg-red-50 p-4 text-sm text-red-600 border border-red-200">
            Rezerváciu sa nepodarilo načítať.
        </div>
    </AppDialog>
</template>