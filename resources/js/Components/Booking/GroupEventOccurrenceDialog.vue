<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { computed, reactive, ref, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import OccurrenceScopeDialog from '@/Components/Booking/OccurrenceScopeDialog.vue';
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
    'close',
    'edit-capacity-window',
    'duplicate-capacity-window',
    'cancel-booking',
    'cancel-capacity-window',
    'reschedule-capacity-window',
    'delete-capacity-window-occurrence',
    'delete-capacity-window-from-date',
    'delete-capacity-window-series',
    'add-patient-to-capacity-window',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const groupForm = reactive({
    date: null,
    starts_at: null,
    ends_at: null,
});

const patientForm = reactive({
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
});

const rescheduleChoiceVisible = ref(false);
const pendingReschedulePayload = ref(null);
const isResettingGroupForm = ref(false);
const hasManuallyChangedDateTime = ref(false);
const isDetailMode = ref(true);

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
        props.capacityWindow?.series_uuid
            || props.capacityWindow?.repeats
            || props.capacityWindow?.is_recurring,
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

const hasGroupEventTimeChanged = computed(() => {
    return hasManuallyChangedDateTime.value;
});

const canAddPatient = computed(() => {
    return Boolean(props.capacityWindow)
        && hasFreeCapacity.value
        && Boolean(patientForm.patient_name.trim());
});

const formatDateForDisplay = (value) => {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
};

const formatTimeForDisplay = (value) => {
    if (!value) {
        return '—';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const detailDateDisplay = computed(() => {
    return formatDateForDisplay(groupForm.date ?? capacityWindowDate.value);
});

const detailTimeDisplay = computed(() => {
    const startsAt = groupForm.starts_at
        ?? parseTimeValue(props.capacityWindow?.starts_datetime ?? props.capacityWindow?.starts_at, groupForm.date);
    const endsAt = groupForm.ends_at
        ?? parseTimeValue(props.capacityWindow?.ends_datetime ?? props.capacityWindow?.ends_at, groupForm.date);

    return `${formatTimeForDisplay(startsAt)} - ${formatTimeForDisplay(endsAt)}`;
});

const stripTimezoneFromDateTime = (value) => {
    if (!value) {
        return null;
    }

    return String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);
};

const parseDateValue = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const stringValue = stripTimezoneFromDateTime(value);

    if (!stringValue) {
        return null;
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(stringValue)) {
        return new Date(`${stringValue}T00:00:00`);
    }

    if (stringValue.includes('T')) {
        return new Date(`${stringValue.slice(0, 10)}T00:00:00`);
    }

    return null;
};

const parseTimeValue = (value, fallbackDate = null) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const stringValue = stripTimezoneFromDateTime(value);

    if (!stringValue) {
        return null;
    }

    if (stringValue.includes('T')) {
        return new Date(stringValue);
    }

    const [hours, minutes] = stringValue.slice(0, 5).split(':');
    const date = fallbackDate instanceof Date
        ? new Date(fallbackDate)
        : new Date();

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const formatDateOnlyForBackend = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

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

    if (Number.isNaN(date.getTime())) {
        return null;
    }

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

const resetGroupForm = () => {
    isResettingGroupForm.value = true;
    hasManuallyChangedDateTime.value = false;

    const pendingReschedule = props.capacityWindow?._pendingReschedule ?? null;
    const pendingStart = pendingReschedule?.starts_at ?? null;
    const pendingEnd = pendingReschedule?.ends_at ?? null;
    const pendingDate = pendingReschedule?.date ?? null;

    const dateString = pendingDate
        ?? capacityWindowDate.value;

    const date = dateString
        ? parseDateValue(dateString)
        : null;

    groupForm.date = date;

    groupForm.starts_at = pendingStart
        ? parseTimeValue(pendingStart, date)
        : parseTimeValue(
            props.capacityWindow?.starts_datetime
                ?? props.capacityWindow?.starts_at,
            date,
        );

    groupForm.ends_at = pendingEnd
        ? parseTimeValue(pendingEnd, date)
        : parseTimeValue(
            props.capacityWindow?.ends_datetime
                ?? props.capacityWindow?.ends_at,
            date,
        );

    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;

    setTimeout(() => {
        isResettingGroupForm.value = false;
    }, 0);
};

const resetPatientForm = () => {
    patientForm.patient_name = '';
    patientForm.patient_email = '';
    patientForm.patient_phone = '';
    patientForm.patient_phone_country = 'SK';
    patientForm.patient_phone_full = '';
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
            isDetailMode.value = true;
            resetGroupForm();
            resetPatientForm();
        }
    },
);

watch(
    () => [
        groupForm.date,
        groupForm.starts_at,
        groupForm.ends_at,
    ],
    () => {
        if (!props.visible || isResettingGroupForm.value) {
            return;
        }

        hasManuallyChangedDateTime.value = true;
    },
);

const editCapacityWindow = () => {
    emit('edit-capacity-window', props.capacityWindow);
};

const enableEditMode = () => {
    editCapacityWindow();
};

const openPatientManagementMode = () => {
    isDetailMode.value = false;
};

const duplicateCapacityWindow = () => {
    emit('duplicate-capacity-window', props.capacityWindow);
};

const closeDialog = () => {
    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;
    hasManuallyChangedDateTime.value = false;
    isDetailMode.value = true;

    emit('update:visible', false);
    emit('close');
};

const buildReschedulePayload = () => {
    return {
        date: selectedDateForBackend.value,
        starts_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        notify_patient: true,
    };
};

const rescheduleCapacityWindow = () => {
    if (!props.capacityWindow || !canSaveGroupEvent.value) {
        return;
    }

    if (!hasGroupEventTimeChanged.value) {
        return;
    }

    const payload = buildReschedulePayload();

    if (!isCapacityWindowRepeatable.value) {
        emit('reschedule-capacity-window', props.capacityWindow, {
            ...payload,
            reschedule_scope: 'occurrence',
        });

        hasManuallyChangedDateTime.value = false;

        return;
    }

    pendingReschedulePayload.value = payload;
    rescheduleChoiceVisible.value = true;
};

const submitRescheduleScope = (scope) => {
    if (!props.capacityWindow || !pendingReschedulePayload.value) {
        return;
    }

    emit('reschedule-capacity-window', props.capacityWindow, {
        ...pendingReschedulePayload.value,
        reschedule_scope: scope,
    });

    hasManuallyChangedDateTime.value = false;
    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;
};

const closeRescheduleChoice = () => {
    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;
};

const cancelCapacityWindow = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('cancel-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        notify_patient: true,
    });
};

const deleteCapacityWindowOccurrence = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('delete-capacity-window-occurrence', props.capacityWindow, {
        date: selectedDateForBackend.value,
        delete_scope: 'occurrence',
        notify_patient: true,
    });
};

const deleteCapacityWindowFromDate = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    if (!isCapacityWindowRepeatable.value) {
        deleteCapacityWindowOccurrence();

        return;
    }

    emit('delete-capacity-window-from-date', props.capacityWindow, {
        date: selectedDateForBackend.value,
        delete_scope: 'from_date',
        notify_patient: true,
    });
};

const deleteCapacityWindowSeries = () => {
    if (!props.capacityWindow || !selectedDateForBackend.value) {
        return;
    }

    emit('delete-capacity-window-series', props.capacityWindow, {
        date: selectedDateForBackend.value,
        delete_scope: 'series',
        notify_patient: true,
    });
};

const cancelPatientBooking = (booking) => {
    emit('cancel-booking', booking, {
        notify_patient: true,
    });
};

const addPatientToCapacityWindow = () => {
    if (!props.capacityWindow || !canAddPatient.value) {
        return;
    }

    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;

    emit('add-patient-to-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        starts_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        patient_name: patientForm.patient_name,
        patient_email: patientForm.patient_email,
        patient_phone: patientForm.patient_phone_full || patientForm.patient_phone,
        notify_patient: true,
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
        :date-time-disabled="isDetailMode"
        width="max-w-5xl"
        save-label="Presunúť"
        :show-save="!isDetailMode && Boolean(capacityWindow)"
        :save-disabled="!canSaveGroupEvent || !hasGroupEventTimeChanged"
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
        <template #footer-start>
            <Button
                v-if="capacityWindow"
                v-show="isDetailMode"
                type="button"
                label="Pacienti"
                outlined
                @click="openPatientManagementMode"
            />

            <Button
                v-if="capacityWindow"
                v-show="isDetailMode"
                type="button"
                label="Duplikovať"
                outlined
                @click="duplicateCapacityWindow"
            />

            <Button
                v-if="capacityWindow"
                v-show="isDetailMode"
                type="button"
                label="Upraviť"
                outlined
                @click="enableEditMode"
            />
        </template>

        <div
            v-if="capacityWindow && isDetailMode"
            class="space-y-4"
        >
            <div class="rounded-md bg-soft p-4 text-sm leading-6 text-accent md:col-span-2">
                <p><strong class="text-dark">Obsadenosť:</strong> {{ bookings.length }} / {{ capacity ?? '—' }}</p>
                <p><strong class="text-dark">Dátum:</strong> {{ detailDateDisplay }}</p>
                <p><strong class="text-dark">Čas:</strong> {{ detailTimeDisplay }}</p>
                <p><strong class="text-dark">Názov:</strong> {{ dialogTitle }}</p>
                <p><strong class="text-dark">Opakovanie:</strong> {{ isCapacityWindowRepeatable ? 'Opakuje sa' : 'Neopakuje sa' }}</p>
            </div>

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
                    />
                </div>
            </FormSection>
        </div>

        <FormPage
            v-else-if="capacityWindow"
            submit-label="Uložiť"
            :show-submit="false"
        >
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

            <FormSection
                title="Pridať pacienta"
                description="Pacienta pridáte priamo do tohto skupinového termínu."
                columns="md:grid-cols-2"
            >
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

                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="button"
                        label="Pridať pacienta"
                        icon="pi pi-user-plus"
                        :disabled="!canAddPatient"
                        @click.stop="addPatientToCapacityWindow"
                    />
                </div>
            </FormSection>

            <FormSection
                title="Upraviť termín"
                description="Tu zmeňte iba dátum alebo čas. Pri opakovanej sérii sa vás aplikácia opýta, či chcete presunúť iba tento termín alebo viac termínov."
                columns="md:grid-cols-2"
            >
                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="button"
                        label="Upraviť skupinu udalostí"
                        outlined
                        @click="editCapacityWindow"
                    />
                </div>
            </FormSection>
        </FormPage>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Skupinový termín sa nepodarilo načítať.
        </div>
    </EventDialog>

    <OccurrenceScopeDialog
        v-model:visible="rescheduleChoiceVisible"
        mode="reschedule"
        subject-label="skupinový termín"
        @select="submitRescheduleScope"
        @cancel="closeRescheduleChoice"
    />
</template>