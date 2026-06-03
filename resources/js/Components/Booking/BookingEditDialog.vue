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
    date: null,
    starts_at: null,
    ends_at: null,
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

    if (!service) {
        return null;
    }

    return Number(
        service.duration_minutes
            ?? service.duration
            ?? service.length_minutes
            ?? service.minutes
            ?? 0,
    );
});

const hasPatientEmail = computed(() => {
    return Boolean(props.booking?.patient_email);
});

const canNotifyPatient = computed(() => {
    return hasPatientEmail.value;
});

const bookingDateModel = computed({
    get: () => form.date,
    set: (value) => {
        form.date = value;

        if (!value) {
            return;
        }

        if (form.starts_at) {
            form.starts_at = mergeDateAndTime(value, form.starts_at);
        }

        if (form.ends_at) {
            form.ends_at = mergeDateAndTime(value, form.ends_at);
        }
    },
});

const bookingStartTimeModel = computed({
    get: () => form.starts_at,
    set: (value) => {
        if (!value) {
            form.starts_at = null;

            return;
        }

        form.starts_at = mergeDateAndTime(form.date ?? value, value);
    },
});

const bookingEndTimeModel = computed({
    get: () => form.ends_at,
    set: (value) => {
        if (!value) {
            form.ends_at = null;

            return;
        }

        form.ends_at = mergeDateAndTime(form.date ?? value, value);
    },
});

const mergeDateAndTime = (dateValue, timeValue) => {
    const date = dateValue instanceof Date
        ? new Date(dateValue)
        : new Date(dateValue);

    const time = timeValue instanceof Date
        ? timeValue
        : new Date(timeValue);

    date.setHours(time.getHours(), time.getMinutes(), 0, 0);

    return date;
};

const calculateEndFromService = () => {
    if (!form.date || !form.starts_at || !selectedServiceDuration.value) {
        return;
    }

    const end = new Date(form.starts_at);
    end.setMinutes(end.getMinutes() + selectedServiceDuration.value);

    form.ends_at = end;
};

const formatDateTimeForBackend = (value) => {
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

const originalStartsAtForBackend = computed(() => {
    return props.booking?.starts_at
        ? formatDateTimeForBackend(props.booking.starts_at)
        : null;
});

const originalEndsAtForBackend = computed(() => {
    return props.booking?.ends_at
        ? formatDateTimeForBackend(props.booking.ends_at)
        : null;
});

const currentStartsAtForBackend = computed(() => {
    return form.starts_at
        ? formatDateTimeForBackend(form.starts_at)
        : null;
});

const currentEndsAtForBackend = computed(() => {
    return form.ends_at
        ? formatDateTimeForBackend(form.ends_at)
        : null;
});

const hasServiceChanged = computed(() => {
    return Boolean(props.booking)
        && Number(form.service_id) !== Number(props.booking.service_id);
});

const hasStartChanged = computed(() => {
    return Boolean(props.booking)
        && currentStartsAtForBackend.value !== originalStartsAtForBackend.value;
});

const hasEndChanged = computed(() => {
    return Boolean(props.booking)
        && currentEndsAtForBackend.value !== originalEndsAtForBackend.value;
});

const hasBookingChanges = computed(() => {
    return hasServiceChanged.value
        || hasStartChanged.value
        || hasEndChanged.value;
});

const canSaveChanges = computed(() => {
    return Boolean(props.booking)
        && Boolean(form.service_id)
        && Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at)
        && hasBookingChanges.value;
});

const resetForm = () => {
    form.service_id = props.booking?.service_id ?? null;

    const startsAt = props.booking?.starts_at
        ? new Date(props.booking.starts_at)
        : null;

    const endsAt = props.booking?.ends_at
        ? new Date(props.booking.ends_at)
        : null;

    form.date = startsAt;
    form.starts_at = startsAt;
    form.ends_at = endsAt;
    form.notify_patient = false;
    form.notification_reason = '';
};

watch(() => props.booking?.id, () => {
    resetForm();
}, { immediate: true });

watch(() => props.visible, (visible) => {
    if (visible) {
        resetForm();
    }
});

watch(() => props.booking?.patient_email, (email) => {
    if (!email) {
        form.notify_patient = false;
    }
});

watch(hasBookingChanges, (changed) => {
    if (!changed) {
        form.notify_patient = false;
        form.notification_reason = '';
    }
});

watch(
    () => [
        form.service_id,
        form.starts_at,
    ],
    () => {
        if (!hasServiceChanged.value && !hasStartChanged.value) {
            return;
        }

        calculateEndFromService();
    },
);

const closeDialog = () => {
    emit('update:visible', false);
};

const rescheduleBooking = () => {
    if (!props.booking || !canSaveChanges.value) {
        return;
    }

    emit('reschedule-booking', props.booking, {
        booking_slot_id: null,
        service_id: form.service_id,
        starts_at: formatDateTimeForBackend(form.starts_at),
        ends_at: formatDateTimeForBackend(form.ends_at),
        notify_patient: canNotifyPatient.value && form.notify_patient,
        notification_reason: form.notification_reason,
    });
};

const cancelBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: canNotifyPatient.value && form.notify_patient,
        notification_reason: form.notification_reason,
    });
};
</script>

<template>
    <EventDialog
        :visible="visible"
        title="Upraviť rezerváciu"
        v-model:date="bookingDateModel"
        v-model:starts-at="bookingStartTimeModel"
        v-model:ends-at="bookingEndTimeModel"
        width="max-w-3xl"
        save-label="Uložiť"
        :show-save="hasBookingChanges"
        :save-disabled="!canSaveChanges"
        show-delete
        delete-label="Odstrániť"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @save="rescheduleBooking"
        @delete-occurrence="cancelBooking"
    >
        <div
            v-if="booking"
        >
            <FormSection
                title="Pacient"
                columns="md:grid-cols-2"
            >
                <PatientCard
                    class="md:col-span-2"
                    :patient-name="booking.patient_name"
                    :patient-phone="booking.patient_phone"
                    :patient-email="booking.patient_email"
                >
                    <p
                        v-if="booking.patient_note"
                        class="mt-4 whitespace-pre-line rounded-lg border border-soft/60 bg-soft/40 p-3 text-sm leading-6 text-dark"
                    >
                        <span class="mb-1 block text-xs font-medium text-accent">
                            Poznámka od pacienta:
                        </span>

                        {{ booking.patient_note }}
                    </p>
                </PatientCard>
            </FormSection>

            <FormSection
                title="Rezervácia"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Názov služby"
                    for="reschedule_service_id"
                    required
                    span="md:col-span-2"
                >
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

                <FormField
                    label="Poznámka"
                    :for="`booking_admin_note_${booking.id}`"
                    span="md:col-span-2"
                >
                    <Textarea
                        :id="`booking_admin_note_${booking.id}`"
                        v-model="bookingNotes[booking.id]"
                        rows="3"
                        class="w-full"
                        placeholder="Zadajte internú poznámku..."
                    />
                </FormField>

                <div
                    v-if="hasBookingChanges"
                    class="col-span-2 flex items-center gap-2"
                >
                    <Checkbox
                        v-model="form.notify_patient"
                        binary
                        input-id="notify_patient_reschedule"
                        :disabled="!canNotifyPatient"
                    />

                    <label
                        for="notify_patient_reschedule"
                        class="cursor-pointer text-sm font-medium text-dark"
                        :class="{ 'opacity-50': !canNotifyPatient }"
                    >
                        Poslať pacientovi email o zmene termínu
                    </label>

                    <span
                        v-if="!canNotifyPatient"
                        class="ml-auto text-xs font-medium text-red-500"
                    >
                        Pacient nemá priradený email
                    </span>
                </div>

                <FormField
                    v-if="form.notify_patient"
                    label="Dôvod zmeny pre pacienta"
                    for="notification_reason"
                    required
                    span="md:col-span-2"
                >
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

        <div
            v-else
            class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-600"
        >
            Rezerváciu sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>