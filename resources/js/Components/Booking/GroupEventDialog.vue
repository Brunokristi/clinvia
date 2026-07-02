<script setup>
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

import EventCreateEditDialog from '@/Components/Booking/Common/EventCreateEditDialog.vue';
import EventOccurrenceActions from '@/Components/Booking/Common/EventOccurrenceActions.vue';
import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import OccurrenceScopeDialog from '@/Components/Booking/Common/OccurrenceScopeDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';

const props = defineProps({
    createEditVisible: {
        type: Boolean,
        required: true,
    },
    occurrenceVisible: {
        type: Boolean,
        required: true,
    },
    groupEvent: {
        type: Object,
        default: null,
    },
    capacityWindow: {
        type: Object,
        default: null,
    },
    capacityWindows: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        default: () => [],
    },
    repeatUnitOptions: {
        type: Array,
        default: () => [],
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:createEditVisible',
    'update:occurrenceVisible',
    'close-create-edit',
    'save',
    'duplicate',
    'edit-capacity-window',
    'duplicate-capacity-window',
    'reschedule-capacity-window',
    'cancel-capacity-window',
    'delete-capacity-window-occurrence',
    'delete-capacity-window-from-date',
    'delete-capacity-window-series',
    'add-patient-to-capacity-window',
    'cancel-booking',
]);

const bookingModeOptions = [
    { label: 'Priama rezervácia', value: 'immediate_booking' },
    { label: 'Len cez žiadosť', value: 'appointment_request' },
];

const createDialogVisible = computed({
    get: () => props.createEditVisible,
    set: (value) => emit('update:createEditVisible', value),
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

const createTimeDate = (value) => {
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
    const date = new Date();
    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const formatDateForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatTimeForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const addYearsToDate = (dateValue, years = 2) => {
    if (!dateValue) {
        return null;
    }

    const stringValue = dateValue instanceof Date
        ? formatDateForBackend(dateValue)
        : String(dateValue).slice(0, 10);

    const date = new Date(`${stringValue}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    date.setFullYear(date.getFullYear() + years);

    return formatDateForBackend(date);
};

const normalizeRepeatDefaults = () => {
    if (!props.groupEvent) {
        return;
    }

    props.groupEvent.repeat_every = Number(props.groupEvent.repeat_every || 1);
    props.groupEvent.repeat_unit = props.groupEvent.repeat_unit || 'weeks';

    if (props.groupEvent.repeats && !props.groupEvent.repeat_ends_on) {
        props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
    }
};

watch(() => props.createEditVisible, (visible) => {
    if (visible) {
        normalizeRepeatDefaults();

        if (props.groupEvent && !props.groupEvent.public_booking_type) {
            props.groupEvent.public_booking_type = 'immediate_booking';
        }
    }
});

watch(() => props.groupEvent?.repeats, () => {
    normalizeRepeatDefaults();
});

watch(() => props.groupEvent?.date, () => {
    if (props.groupEvent?.repeats && !props.groupEvent.repeat_ends_on) {
        props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
    }
});

const datePickerModel = computed({
    get: () => {
        if (!props.groupEvent?.date) {
            return null;
        }

        if (props.groupEvent.date instanceof Date) {
            return props.groupEvent.date;
        }

        return new Date(`${String(props.groupEvent.date).slice(0, 10)}T00:00:00`);
    },
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.date = formatDateForBackend(value);

        if (props.groupEvent.repeats && !props.groupEvent.repeat_ends_on) {
            props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
        }
    },
});

const startsAtPickerModel = computed({
    get: () => createTimeDate(props.groupEvent?.starts_at),
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => createTimeDate(props.groupEvent?.ends_at),
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.ends_at = formatTimeForBackend(value);
    },
});

const isEditing = computed(() => Boolean(props.groupEvent?.capacity_window_id ?? props.groupEvent?.id));
const isPartOfSeries = computed(() => Boolean(props.groupEvent?.series_uuid));
const canEditRepeating = computed(() => !isEditing.value || isPartOfSeries.value);

const createDialogTitle = computed(() => {
    return 'Skupinový termín';
});

const hasValidRepeatSettings = computed(() => {
    if (!props.groupEvent?.repeats) {
        return true;
    }

    return Boolean(props.groupEvent.repeat_ends_on)
        && Number(props.groupEvent.repeat_every ?? 0) >= 1
        && ['days', 'weeks', 'months'].includes(props.groupEvent.repeat_unit);
});

const canSave = computed(() => {
    return Boolean(props.groupEvent)
        && Boolean(props.groupEvent.service_id)
        && Boolean(props.groupEvent.date)
        && Boolean(props.groupEvent.starts_at)
        && Boolean(props.groupEvent.ends_at)
        && Boolean(props.groupEvent.public_booking_type)
        && Number(props.groupEvent.capacity ?? props.groupEvent.bookable_places ?? 0) > 0
        && hasValidRepeatSettings.value;
});

const closeCreateEditDialog = () => {
    emit('update:createEditVisible', false);
    emit('close-create-edit');
};

const buildSavePayload = (scope = 'occurrence') => {
    const groupPatients = Array.isArray(props.groupEvent?.group_patients)
        ? props.groupEvent.group_patients.map((patient) => ({
            patient_name: patient?.patient_name ?? '',
            patient_email: patient?.patient_email ?? null,
            patient_phone: patient?.patient_phone ?? null,
        }))
        : [];

    return {
        ...props.groupEvent,
        group_patients: groupPatients,
        update_scope: scope,
        repeat_ends_on: props.groupEvent.repeats ? String(props.groupEvent.repeat_ends_on).slice(0, 10) : null,
        admin_note: null,
        notify_patient: true,
        notification_reason: null,
    };
};

const saveGroupEvent = () => {
    if (!canSave.value) {
        return;
    }

    emit('save', buildSavePayload('occurrence'));
};

const submitUpdateScope = (scope) => {
    emit('save', buildSavePayload(scope));
};

const duplicateGroupEvent = () => {
    emit('duplicate', props.groupEvent);
};

const occurrenceDialogVisible = computed({
    get: () => props.occurrenceVisible,
    set: (value) => emit('update:occurrenceVisible', value),
});

const groupForm = reactive({ date: null, starts_at: null, ends_at: null });

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

const bookings = computed(() => props.capacityWindow?.bookings ?? []);
const capacity = computed(() => props.capacityWindow?.bookable_places ?? props.capacityWindow?.capacity ?? null);

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

const occurrenceDialogTitle = computed(() => {
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

const currentSeriesWindows = computed(() => {
    const currentWindow = props.capacityWindow;
    const seriesUuid = currentWindow?.series_uuid ?? null;

    if (!currentWindow) {
        return [];
    }

    if (!seriesUuid) {
        return [currentWindow];
    }

    return (props.capacityWindows ?? [])
        .filter((window) => window?.series_uuid === seriesUuid)
        .sort((first, second) => {
            const firstStart = new Date(first?.starts_at ?? first?.starts_datetime ?? 0).getTime();
            const secondStart = new Date(second?.starts_at ?? second?.starts_datetime ?? 0).getTime();

            return firstStart - secondStart;
        });
});

const deleteCountOccurrence = computed(() => 1);

const deleteCountSeries = computed(() => {
    if (!isCapacityWindowRepeatable.value) {
        return 1;
    }

    return currentSeriesWindows.value.length || null;
});

const deleteCountFromDate = computed(() => {
    if (!isCapacityWindowRepeatable.value) {
        return 1;
    }

    const selectedDate = selectedDateForBackend.value;

    if (!selectedDate) {
        return deleteCountSeries.value;
    }

    const filtered = currentSeriesWindows.value.filter((window) => {
        const dateOnly = String(window?.date ?? window?.starts_at ?? window?.starts_datetime ?? '').slice(0, 10);

        return dateOnly >= selectedDate;
    });

    return filtered.length || null;
});

const formatDateOnlyForBackend = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const selectedDateForBackend = computed(() => formatDateOnlyForBackend(groupForm.date) ?? capacityWindowDate.value);

const canSaveGroupEvent = computed(() => {
    return Boolean(props.capacityWindow)
        && Boolean(groupForm.date)
        && Boolean(groupForm.starts_at)
        && Boolean(groupForm.ends_at);
});

const hasGroupEventTimeChanged = computed(() => hasManuallyChangedDateTime.value);

const canAddPatient = computed(() => {
    return Boolean(props.capacityWindow)
        && hasFreeCapacity.value
        && Boolean(patientForm.patient_name.trim());
});

const deleteDialogTitle = computed(() => 'Delete this availability window?');
const deleteDialogDescription = computed(() => {
    const bookingCount = bookings.value.length;
    const bookingNoun = bookingCount === 1 ? 'booking' : 'bookings';

    return `This will cancel/remove ${bookingCount} ${bookingNoun} inside this window.`;
});
const deleteDialogImpactMessage = computed(() => 'It will not affect other calendar bookings.');

const formatDateForDisplay = (value) => {
    if (!value) {
        return '-';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
};

const formatTimeForDisplay = (value) => {
    if (!value) {
        return '-';
    }

    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const detailDateDisplay = computed(() => formatDateForDisplay(groupForm.date ?? capacityWindowDate.value));

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
    const date = fallbackDate instanceof Date ? new Date(fallbackDate) : new Date();

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const detailTimeDisplay = computed(() => {
    const startsAt = groupForm.starts_at
        ?? parseTimeValue(props.capacityWindow?.starts_datetime ?? props.capacityWindow?.starts_at, groupForm.date);
    const endsAt = groupForm.ends_at
        ?? parseTimeValue(props.capacityWindow?.ends_datetime ?? props.capacityWindow?.ends_at, groupForm.date);

    return `${formatTimeForDisplay(startsAt)} - ${formatTimeForDisplay(endsAt)}`;
});

const groupEventInfoItems = computed(() => {
    if (!props.capacityWindow) {
        return [];
    }

    return [
        {
            key: 'date',
            icon: 'pi pi-calendar',
            value: detailDateDisplay.value,
        },
        {
            key: 'duration',
            icon: 'pi pi-clock',
            value: detailTimeDisplay.value,
        },
        {
            key: 'occupancy',
            icon: 'pi pi-users',
            value: `${bookings.value.length} / ${capacity.value ?? '-'} miest`,
        },
        {
            key: 'title',
            icon: 'pi pi-briefcase',
            value: occurrenceDialogTitle.value,
        },
        {
            key: 'repetition',
            icon: 'pi pi-refresh',
            value: isCapacityWindowRepeatable.value ? 'Opakuje sa' : 'Neopakuje sa',
        },
    ];
});

const formatDateTimeForBackend = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

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

    const date = dateValue instanceof Date ? new Date(dateValue) : new Date(dateValue);
    const time = timeValue instanceof Date ? timeValue : new Date(timeValue);

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

    const dateString = pendingDate ?? capacityWindowDate.value;
    const date = dateString ? parseDateValue(dateString) : null;

    groupForm.date = date;

    groupForm.starts_at = pendingStart
        ? parseTimeValue(pendingStart, date)
        : parseTimeValue(props.capacityWindow?.starts_datetime ?? props.capacityWindow?.starts_at, date);

    groupForm.ends_at = pendingEnd
        ? parseTimeValue(pendingEnd, date)
        : parseTimeValue(props.capacityWindow?.ends_datetime ?? props.capacityWindow?.ends_at, date);

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
    () => props.occurrenceVisible,
    (visible) => {
        if (visible) {
            isDetailMode.value = true;
            resetGroupForm();
            resetPatientForm();
        }
    },
);

watch(
    () => [groupForm.date, groupForm.starts_at, groupForm.ends_at],
    () => {
        if (!props.occurrenceVisible || isResettingGroupForm.value) {
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

const duplicateCapacityWindow = () => {
    emit('duplicate-capacity-window', props.capacityWindow);
};

const closeOccurrenceDialog = () => {
    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;
    hasManuallyChangedDateTime.value = false;
    isDetailMode.value = true;

    emit('update:occurrenceVisible', false);
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
    if (!props.capacityWindow || !canSaveGroupEvent.value || !hasGroupEventTimeChanged.value) {
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
    <EventCreateEditDialog
        v-model:visible="createDialogVisible"
        :title="createDialogTitle"
        v-model:date="datePickerModel"
        v-model:starts-at="startsAtPickerModel"
        v-model:ends-at="endsAtPickerModel"
        width="max-w-3xl"
        save-label="Uložiť"
        :loading="loading"
        :save-disabled="loading || !canSave"
        :show-save="Boolean(groupEvent)"
        :show-delete="false"
        :is-repeatable="Boolean(groupEvent?.repeats)"
        scope-mode="update"
        scope-subject-label="skupinový termín"
        :show-duplicate="true"
        @close="closeCreateEditDialog"
        @save="saveGroupEvent"
        @save-scope="submitUpdateScope"
        @duplicate="duplicateGroupEvent"
    >
        <FormPage v-if="groupEvent" submit-label="Uložiť" :loading="loading" :show-submit="false">
            <FormSection
                title="Kapacita a služba"
                description="Toto vytvorí alebo upraví reálny skupinový termín v tabuľke capacity_windows."
                columns="md:grid-cols-1"
            >
                <FormField label="Služba" for="group_service_id" required>
                    <Select
                        id="group_service_id"
                        v-model="groupEvent.service_id"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        placeholder="Vyberte službu"
                        filter
                        filter-placeholder="Hľadať službu"
                        class="w-full"
                    />
                </FormField>

                <FormField label="Počet rezervovateľných miest" for="group_capacity" required>
                    <InputNumber
                        id="group_capacity"
                        v-model="groupEvent.capacity"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                        placeholder="Napr. 10"
                    />
                </FormField>

                <FormField label="Spôsob rezervácie" for="group_public_booking_type" required>
                    <Select
                        id="group_public_booking_type"
                        v-model="groupEvent.public_booking_type"
                        :options="bookingModeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                v-if="canEditRepeating"
                :model="groupEvent"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                :description="isEditing
                    ? 'Zmena opakovania upraví vybrané termíny v tejto sérii.'
                    : 'Pri opakovaní sa vytvoria samostatné capacity_windows záznamy v jednej sérii.'"
                enabled-id="group_window_is_enabled"
                repeats-id="group_window_repeats"
                repeat-every-id="group_repeat_every"
                repeat-unit-id="group_repeat_unit"
                enabled-label="Skupinový termín je aktívny a viditeľný pre pacientov"
                repeats-label="Opakovať tento skupinový termín periodicky"
            />

            <div
                v-if="groupEvent.repeats && !hasValidRepeatSettings"
                class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600"
            >
                Pri opakovaní musí byť vyplnený dátum ukončenia opakovania, interval a jednotka opakovania.
            </div>
        </FormPage>

        <div v-else class="rounded-xl border border-soft bg-white p-6 text-center text-sm text-accent">
            <i class="pi pi-exclamation-circle mb-2 block text-2xl text-red-400"></i>
            Skupinový termín sa nepodarilo načítať.
        </div>
    </EventCreateEditDialog>

    <EventDialog
        v-model:visible="occurrenceDialogVisible"
        :title="'Skupinový termín'"
        v-model:date="groupForm.date"
        v-model:starts-at="groupForm.starts_at"
        v-model:ends-at="groupForm.ends_at"
        :show-date-time-fields="!isDetailMode"
        :date-time-disabled="isDetailMode"
        width="max-w-5xl"
        save-label="Presunúť"
        :show-save="!isDetailMode && Boolean(capacityWindow)"
        :save-disabled="!canSaveGroupEvent || !hasGroupEventTimeChanged"
        show-delete
        :delete-disabled="!capacityWindow"
        :is-repeatable="isCapacityWindowRepeatable"
        :occurrence-date="selectedDateForBackend"
        :delete-dialog-title="deleteDialogTitle"
        :delete-dialog-description="deleteDialogDescription"
        :delete-dialog-impact-message="deleteDialogImpactMessage"
        :delete-count-occurrence="deleteCountOccurrence"
        :delete-count-from-date="deleteCountFromDate"
        :delete-count-series="deleteCountSeries"
        @close="closeOccurrenceDialog"
        @save="rescheduleCapacityWindow"
        @delete-occurrence="deleteCapacityWindowOccurrence"
        @delete-from-now-on="deleteCapacityWindowFromDate"
        @delete-all="deleteCapacityWindowSeries"
    >
        <template #footer-start>
            <EventOccurrenceActions
                v-if="capacityWindow && isDetailMode"
                @duplicate="duplicateCapacityWindow"
                @edit="enableEditMode"
            />
        </template>

        <div v-if="capacityWindow && isDetailMode" class="space-y-4">
            <div class="space-y-4">
                <div
                    v-for="item in groupEventInfoItems"
                    :key="item.key"
                    class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3"
                >
                    <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-soft text-accent">
                        <i :class="item.icon" class="text-base" />
                    </div>

                    <div class="flex min-w-0 items-center">
                        <p class="break-words text-sm font-medium text-dark truncate">
                            {{ item.value }}
                        </p>
                    </div>
                </div>
            </div>

            <FormSection v-if="bookings.length" title="Pacienti v skupinovom termíne" columns="md:grid-cols-1">
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

        <FormPage v-else-if="capacityWindow" submit-label="Uložiť" :show-submit="false">
            <FormSection v-if="bookings.length" title="Pacienti v skupinovom termíne" columns="md:grid-cols-1">
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
                <FormField label="Meno pacienta" for="capacity_new_patient_name" required span="md:col-span-2">
                    <InputText
                        id="capacity_new_patient_name"
                        v-model="patientForm.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <FormField label="Email" for="capacity_new_patient_email">
                    <InputText
                        id="capacity_new_patient_email"
                        v-model="patientForm.patient_email"
                        type="email"
                        class="w-full"
                        placeholder="email@example.com"
                        :disabled="!hasFreeCapacity"
                    />
                </FormField>

                <FormField label="Telefón" for="capacity_new_patient_phone">
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
                    <Button type="button" label="Upraviť skupinu udalostí" outlined @click="editCapacityWindow" />
                </div>
            </FormSection>
        </FormPage>

        <div v-else class="rounded-md bg-soft p-4 text-sm text-accent">
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
