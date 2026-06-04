<script setup>
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    services: {
        type: Array,
        default: () => [],
    },
    selection: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'create-booking',
]);

const form = reactive({
    service_ids: [],
    date: null,
    starts_at: null,
    ends_at: null,
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
    patient_note: '',
    admin_note: '',
    notify_patient: true,
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable ?? true)
        .map((service) => ({
            label: service.name,
            value: service.id,
        }));
});

const selectedServices = computed(() => {
    return props.services.filter((service) => {
        return form.service_ids.map(Number).includes(Number(service.id));
    });
});

const hasPatientEmail = computed(() => {
    return Boolean(form.patient_email.trim());
});

const selectedServicesDuration = computed(() => {
    return selectedServices.value.reduce((total, service) => {
        return total + Number(
            service.duration_minutes
                ?? service.duration
                ?? service.length_minutes
                ?? service.minutes
                ?? 0,
        );
    }, 0);
});

const selectedServicesLabel = computed(() => {
    if (!selectedServices.value.length) {
        return '';
    }

    return selectedServices.value
        .map((service) => service.name)
        .join(', ');
});

const formatDateForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatTimeForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const createDateFromDateAndTime = (dateValue, timeValue) => {
    if (!dateValue || !timeValue) {
        return null;
    }

    const date = dateValue instanceof Date
        ? new Date(dateValue)
        : new Date(dateValue);

    if (timeValue instanceof Date) {
        date.setHours(timeValue.getHours(), timeValue.getMinutes(), 0, 0);

        return date;
    }

    const [hours, minutes] = String(timeValue).split(':');

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const calculatedEndsAtDate = computed(() => {
    if (!form.date || !form.starts_at || !selectedServicesDuration.value) {
        return null;
    }

    const start = createDateFromDateAndTime(form.date, form.starts_at);

    if (!start) {
        return null;
    }

    start.setMinutes(start.getMinutes() + selectedServicesDuration.value);

    return start;
});

const startsAtForBackend = computed(() => {
    if (!form.date || !form.starts_at) {
        return null;
    }

    return `${formatDateForBackend(form.date)} ${formatTimeForBackend(form.starts_at)}:00`;
});

const endsAtForBackend = computed(() => {
    if (!form.date || !form.ends_at) {
        return null;
    }

    return `${formatDateForBackend(form.date)} ${formatTimeForBackend(form.ends_at)}:00`;
});

const canSubmit = computed(() => {
    return Boolean(form.service_ids.length)
        && Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at)
        && Boolean(form.patient_name.trim())
        && selectedServicesDuration.value > 0;
});

const resetForm = () => {
    form.service_ids = [];
    form.ends_at = null;

    if (props.selection?.start) {
        const start = props.selection.start instanceof Date
            ? props.selection.start
            : new Date(props.selection.start);

        form.date = props.selection?.date
            ? new Date(`${props.selection.date}T00:00:00`)
            : start;

        form.starts_at = props.selection?.starts_at
            ? createDateFromDateAndTime(form.date, props.selection.starts_at)
            : start;
    } else {
        form.date = null;
        form.starts_at = null;
    }

    form.patient_name = '';
    form.patient_email = '';
    form.patient_phone = '';
    form.patient_phone_country = 'SK';
    form.patient_phone_full = '';
    form.patient_note = '';
    form.admin_note = '';
    form.notify_patient = true;
};

watch(() => props.visible, (visible) => {
    if (visible) {
        resetForm();
    }
});

watch(() => props.selection, () => {
    if (props.visible) {
        resetForm();
    }
});

watch(() => form.patient_email, (email) => {
    if (email.trim()) {
        form.notify_patient = true;
    }
});

watch(calculatedEndsAtDate, (endsAt) => {
    form.ends_at = endsAt;
});

watch(
    () => [
        form.service_ids,
        form.date,
        form.starts_at,
    ],
    () => {
        if (!form.date || !form.starts_at || !selectedServicesDuration.value) {
            return;
        }

        const start = createDateFromDateAndTime(form.date, form.starts_at);

        if (!start) {
            return;
        }

        start.setMinutes(start.getMinutes() + selectedServicesDuration.value);

        form.ends_at = start;
    },
    {
        deep: true,
    },
);

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    emit('create-booking', {
        service_ids: form.service_ids,
        service_id: form.service_ids[0] ?? null,
        booking_slot_id: null,
        starts_at: startsAtForBackend.value,
        ends_at: endsAtForBackend.value,
        patient_name: form.patient_name,
        patient_email: form.patient_email,
        patient_phone: form.patient_phone_full || form.patient_phone,
        patient_note: form.patient_note,
        admin_note: form.admin_note,
        notify_patient: form.notify_patient,
    });
};
</script>

<template>
    <EventDialog
        :visible="visible"
        v-model:date="form.date"
        v-model:starts-at="form.starts_at"
        v-model:ends-at="form.ends_at"
        width="max-w-3xl"
        save-label="Uložiť"
        :save-disabled="!canSubmit"
        :show-delete="false"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @save="submit"
        title="Vytvoriť rezerváciu"
    >
        <FormPage
            submit-label="Vytvoriť rezerváciu"
            :loading="false"
            :show-submit="false"
        >
            <FormSection
                title="Služby"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Služby"
                    for="service_ids"
                    required
                    span="md:col-span-2"
                >
                    <MultiSelect
                        id="service_ids"
                        v-model="form.service_ids"
                        :options="serviceOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte službu alebo služby"
                        display="chip"
                        class="w-full"
                    />
                </FormField>

                <div
                    v-if="selectedServices.length"
                    class="rounded-md bg-soft p-4 text-sm text-accent md:col-span-2"
                >
                    <p>
                        Vybrané služby: {{ selectedServicesLabel }}
                    </p>

                    <p>
                        Celkové trvanie: {{ selectedServicesDuration }} min
                    </p>
                </div>

                <div
                    v-if="selectedServices.length && !selectedServicesDuration"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-600 md:col-span-2"
                >
                    Vybrané služby nemajú nastavené trvanie. Skontrolujte pole duration_minutes.
                </div>
            </FormSection>

            <FormSection
                title="Pacient"
                description="Vyplňte kontaktné údaje pacienta."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Meno pacienta"
                    for="patient_name"
                    required
                    span="md:col-span-2"
                >
                    <InputText
                        id="patient_name"
                        v-model="form.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                    />
                </FormField>

                <FormField
                    label="Email"
                    for="patient_email"
                >
                    <InputText
                        id="patient_email"
                        v-model="form.patient_email"
                        type="email"
                        class="w-full"
                        placeholder="email@example.com"
                    />
                </FormField>

                <FormField
                    label="Telefón"
                    for="patient_phone"
                >
                    <PhoneInput
                        v-model="form.patient_phone"
                        v-model:country-code="form.patient_phone_country"
                        v-model:full-value="form.patient_phone_full"
                    />
                </FormField>

                <div class="md:col-span-2">
                    <PatientCard
                        :patient-name="form.patient_name"
                        :patient-phone="form.patient_phone_full || form.patient_phone"
                        :patient-email="form.patient_email"
                    />
                </div>

                <div class="flex items-center gap-2 md:col-span-2">
                    <Checkbox
                        v-model="form.notify_patient"
                        binary
                        input-id="notify_patient_create"
                        :disabled="!hasPatientEmail"
                    />

                    <label
                        for="notify_patient_create"
                        class="cursor-pointer text-sm text-accent"
                        :class="{ 'opacity-50': !hasPatientEmail }"
                    >
                        Poslať pacientovi email
                    </label>
                </div>

                <FormField
                    label="Správa pre pacienta"
                    for="patient_note"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="patient_note"
                        v-model="form.patient_note"
                        rows="3"
                        class="w-full"
                        placeholder="Správa pre pacienta"
                    />
                </FormField>

                <FormField
                    label="Poznámka"
                    for="admin_note"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="admin_note"
                        v-model="form.admin_note"
                        rows="3"
                        class="w-full"
                        placeholder="Interná poznámka"
                    />
                </FormField>
            </FormSection>
        </FormPage>
    </EventDialog>
</template>