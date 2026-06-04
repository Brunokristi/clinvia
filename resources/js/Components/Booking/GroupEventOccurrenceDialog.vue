<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
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
    capacityWindow: {
        type: Object,
        default: null,
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    'update:visible',
    'add-patient-to-capacity-window',
    'cancel-booking',
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

const groupForm = reactive({
    date: null,
    starts_at: null,
    ends_at: null,
    notify_patient: true,
    notification_reason: '',
});

const patientForm = reactive({
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
    patient_note: '',
    admin_note: '',
    notify_patient: true,
});

const bookings = computed(() => {
    return props.capacityWindow?.bookings ?? [];
});

const capacity = computed(() => {
    return props.capacityWindow?.bookable_places
        ?? props.capacityWindow?.capacity
        ?? null;
});

const hasFreeCapacity = computed(() => {
    if (!capacity.value) {
        return true;
    }

    return bookings.value.length < Number(capacity.value);
});

const capacityWindowDate = computed(() => {
    if (!props.capacityWindow?.date && !props.capacityWindow?.starts_at) {
        return null;
    }

    return String(props.capacityWindow.date ?? props.capacityWindow.starts_at).slice(0, 10);
});

const dialogTitle = computed(() => {
    return props.capacityWindow?.title
        ?? props.capacityWindow?.service?.name
        ?? props.capacityWindow?.service_name
        ?? 'Skupinový termín';
});

const isCapacityWindowRepeatable = computed(() => {
    return Boolean(
        props.capacityWindow?.repeats
            || props.capacityWindow?.is_recurring
    );
});

const selectedDateForBackend = computed(() => {
    return formatDateOnlyForBackend(groupForm.date) ?? capacityWindowDate.value;
});

const canSaveGroupEvent = computed(() => {
    return Boolean(props.capacityWindow)
        && Boolean(groupForm.date)
        && Boolean(groupForm.starts_at)
        && Boolean(groupForm.ends_at);
});

const canNotifyNewPatient = computed(() => {
    return Boolean(patientForm.patient_email.trim());
});

const canAddPatient = computed(() => {
    return Boolean(props.capacityWindow)
        && hasFreeCapacity.value
        && Boolean(patientForm.patient_name.trim());
});

const resetGroupForm = () => {
    groupForm.date = capacityWindowDate.value
        ? new Date(`${capacityWindowDate.value}T00:00:00`)
        : null;

    groupForm.starts_at = props.capacityWindow?.starts_at
        ? new Date(props.capacityWindow.starts_at)
        : null;

    groupForm.ends_at = props.capacityWindow?.ends_at
        ? new Date(props.capacityWindow.ends_at)
        : null;

    groupForm.notify_patient = true;
    groupForm.notification_reason = '';
};

const resetPatientForm = () => {
    patientForm.patient_name = '';
    patientForm.patient_email = '';
    patientForm.patient_phone = '';
    patientForm.patient_phone_country = 'SK';
    patientForm.patient_phone_full = '';
    patientForm.patient_note = '';
    patientForm.admin_note = '';
    patientForm.notify_patient = true;
};

watch(
    () => props.capacityWindow?.id,
    () => {
        resetGroupForm();
        resetPatientForm();
    },
    { immediate: true },
);

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetGroupForm();
            resetPatientForm();
        }
    },
);

watch(
    () => patientForm.patient_email,
    (email) => {
        if (email.trim()) {
            patientForm.notify_patient = true;
        }
    },
);

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

const closeDialog = () => {
    emit('update:visible', false);
};

const rescheduleCapacityWindow = () => {
    if (!props.capacityWindow || !canSaveGroupEvent.value) {
        return;
    }

    emit('reschedule-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        starts_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
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

const deleteCapacityWindowOccurrence = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    if (!isCapacityWindowRepeatable.value) {
        deleteCapacityWindowSeries();

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

    if (!isCapacityWindowRepeatable.value) {
        deleteCapacityWindowSeries();

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

const cancelPatientBooking = (booking) => {
    emit('cancel-booking', booking, {
        notify_patient: groupForm.notify_patient,
        notification_reason: groupForm.notification_reason,
    });
};

const addPatientToCapacityWindow = () => {
    if (!props.capacityWindow || !canAddPatient.value) {
        return;
    }

    emit('add-patient-to-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        starts_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        patient_name: patientForm.patient_name,
        patient_email: patientForm.patient_email,
        patient_phone: patientForm.patient_phone_full || patientForm.patient_phone,
        patient_note: patientForm.patient_note,
        admin_note: patientForm.admin_note,
        notify_patient: canNotifyNewPatient.value && patientForm.notify_patient,
    });

    resetPatientForm();
};
</script>

<template>
    <EventDialog
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        v-model:date="groupForm.date"
        v-model:starts-at="groupForm.starts_at"
        v-model:ends-at="groupForm.ends_at"
        width="max-w-5xl"
        save-label="Uložiť"
        :show-save="Boolean(capacityWindow)"
        :save-disabled="!canSaveGroupEvent"
        show-delete
        :delete-disabled="!capacityWindow"
        :is-repeatable="isCapacityWindowRepeatable"
        :occurrence-date="selectedDateForBackend"
        @close="closeDialog"
        @save="rescheduleCapacityWindow"
        @delete-occurrence="deleteCapacityWindowOccurrence"
        @delete-from-now-on="deleteCapacityWindowFromDate"
        @delete-all="deleteCapacityWindowSeries"
    >
        <FormPage
            v-if="capacityWindow"
            submit-label="Uložiť"
            :show-submit="false"
        >
            <FormSection
                title="Skupinový termín"
                description="Tu upravíte celý skupinový termín a oznámenia pre pacientov."
                columns="md:grid-cols-2"
            >
                <div class="rounded-md bg-soft p-4 text-sm leading-6 text-accent md:col-span-2">
                    <strong class="text-dark">Obsadenosť:</strong>
                    {{ bookings.length }} / {{ capacity ?? '—' }}
                </div>

                <div class="flex items-center gap-2 md:col-span-2">
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

                <FormField
                    label="Dôvod zmeny"
                    for="capacity_notification_reason"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="capacity_notification_reason"
                        v-model="groupForm.notification_reason"
                        rows="3"
                        class="w-full"
                        placeholder="Napríklad: Termín musíme presunúť z organizačných dôvodov."
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Pridať pacienta"
                description="Pacienta pridáte priamo do tohto skupinového termínu."
                columns="md:grid-cols-2"
            >
                <div
                    v-if="!hasFreeCapacity"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-600 md:col-span-2"
                >
                    Tento skupinový termín je už naplnený.
                </div>

                <FormField
                    label="Meno pacienta"
                    for="capacity_new_patient_name"
                    required
                    span="md:col-span-2"
                >
                    <InputText
                        id="capacity_new_patient_name"
                        v-model="patientForm.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <FormField
                    label="Email"
                    for="capacity_new_patient_email"
                >
                    <InputText
                        id="capacity_new_patient_email"
                        v-model="patientForm.patient_email"
                        type="email"
                        class="w-full"
                        placeholder="email@example.com"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <FormField
                    label="Telefón"
                    for="capacity_new_patient_phone"
                >
                    <PhoneInput
                        v-model="patientForm.patient_phone"
                        v-model:country-code="patientForm.patient_phone_country"
                        v-model:full-value="patientForm.patient_phone_full"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <div class="flex items-center gap-2 md:col-span-2">
                    <Checkbox
                        v-model="patientForm.notify_patient"
                        binary
                        input-id="capacity_new_patient_notify"
                        :disabled="!canNotifyNewPatient || !hasFreeCapacity"
                    />

                    <label
                        for="capacity_new_patient_notify"
                        class="cursor-pointer text-sm text-accent"
                        :class="{ 'opacity-50': !canNotifyNewPatient || !hasFreeCapacity }"
                    >
                        Upozorniť pacienta o rezervácií a zmenách v nej
                    </label>
                </div>

                <FormField
                    label="Správa pre pacienta"
                    for="capacity_new_patient_note"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="capacity_new_patient_note"
                        v-model="patientForm.patient_note"
                        rows="3"
                        class="w-full"
                        placeholder="Správa pre pacienta"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <FormField
                    label="Interná poznámka"
                    for="capacity_new_admin_note"
                    span="md:col-span-2"
                >
                    <Textarea
                        id="capacity_new_admin_note"
                        v-model="patientForm.admin_note"
                        rows="3"
                        class="w-full"
                        placeholder="Interná poznámka"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="button"
                        label="Pridať pacienta"
                        icon="pi pi-user-plus"
                        :disabled="!canAddPatient"
                        @click="addPatientToCapacityWindow"
                    />
                </div>
            </FormSection>

            <FormSection
                v-if="bookings.length"
                title="Pacienti v skupinovom termíne"
                columns="md:grid-cols-1"
            >
                <div class="space-y-4">
                    <PatientCard
                        v-for="booking in bookings"
                        :key="booking.id"
                        :patient-name="booking.patient_name"
                        :patient-phone="booking.patient_phone"
                        :patient-email="booking.patient_email"
                    >
                        <div class="mt-4 grid gap-4">
                            <FormField
                                label="Admin poznámka"
                                :for="`capacity_booking_note_${booking.id}`"
                            >
                                <Textarea
                                    :id="`capacity_booking_note_${booking.id}`"
                                    v-model="bookingNotes[booking.id]"
                                    rows="3"
                                    class="w-full"
                                    placeholder="Admin poznámka"
                                />
                            </FormField>

                            <div>
                                <Button
                                    type="button"
                                    label="Odstrániť rezerváciu pacienta"
                                    severity="danger"
                                    outlined
                                    size="small"
                                    @click="cancelPatientBooking(booking)"
                                />
                            </div>
                        </div>
                    </PatientCard>
                </div>
            </FormSection>

            <div
                v-else
                class="rounded-md bg-soft p-4 text-sm text-accent"
            >
                V tomto skupinovom termíne zatiaľ nie sú žiadne rezervácie.
            </div>
        </FormPage>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Skupinový termín sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>