<script setup>
import Button from 'primevue/button';
import AutoComplete from 'primevue/autocomplete';
import FormDialog from '@/Components/Dialogs/FormDialog.vue';
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
import { useRecurringImpactPreview } from '@/Composables/Bookings/useRecurringImpactPreview';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';
import PatientLookupField from '@/Components/Patients/PatientLookupField.vue';
import PatientFormDialog from '@/Components/Patients/PatientFormDialog.vue';

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
    patients: {
        type: Array,
        default: () => [],
    },
    branchId: {
        type: [Number, String],
        default: null,
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

const phoneCountries = [
    { value: 'SK', dialCode: '+421' },
    { value: 'CZ', dialCode: '+420' },
    { value: 'AT', dialCode: '+43' },
    { value: 'HU', dialCode: '+36' },
    { value: 'PL', dialCode: '+48' },
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

const selectedServiceDurationMinutes = computed(() => {
    const serviceId = Number(props.groupEvent?.service_id ?? 0);

    if (!serviceId) {
        return 0;
    }

    const selectedService = (props.services ?? []).find((service) => Number(service?.id) === serviceId);

    if (!selectedService) {
        return 0;
    }

    return Number(
        selectedService.duration_minutes
            ?? selectedService.duration
            ?? selectedService.length_minutes
            ?? selectedService.minutes
            ?? 0,
    );
});

watch(
    () => [props.groupEvent?.service_id, props.groupEvent?.date, props.groupEvent?.starts_at],
    () => {
        if (!props.groupEvent || !props.groupEvent.date || !props.groupEvent.starts_at) {
            return;
        }

        if (selectedServiceDurationMinutes.value <= 0) {
            return;
        }

        const baseDate = formatDateForBackend(props.groupEvent.date);

        if (!baseDate) {
            return;
        }

        const start = new Date(`${baseDate}T${String(props.groupEvent.starts_at).slice(0, 5)}:00`);

        if (Number.isNaN(start.getTime())) {
            return;
        }

        start.setMinutes(start.getMinutes() + selectedServiceDurationMinutes.value);
        props.groupEvent.ends_at = formatTimeForBackend(start);
    },
);

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
    const recurrence = props.groupEvent?.recurrence ?? (props.groupEvent?.repeats
        ? {
            frequency: props.groupEvent.repeat_unit === 'days'
                ? 'daily'
                : (props.groupEvent.repeat_unit === 'months' ? 'monthly' : 'weekly'),
            interval: Math.max(1, Number(props.groupEvent.repeat_every ?? 1)),
            weekdays: props.groupEvent.repeat_unit === 'weeks'
                ? [...(props.groupEvent.repeat_weekdays ?? [])]
                : [],
            ends: {
                type: props.groupEvent.repeat_ends_on ? 'on' : 'never',
                count: null,
                until: props.groupEvent.repeat_ends_on ? String(props.groupEvent.repeat_ends_on).slice(0, 10) : null,
            },
        }
        : null);

    return {
        ...props.groupEvent,
        update_scope: scope,
        repeat_ends_on: props.groupEvent.repeats ? String(props.groupEvent.repeat_ends_on).slice(0, 10) : null,
        recurrence,
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
    pendingDuplicatePayload.value = props.groupEvent;
    duplicatePatientsPromptVisible.value = true;
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
    patient_birth_number: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
});

const patientNameSuggestions = ref([]);
const patientEmailSuggestions = ref([]);

const normalizeSearch = (value) => String(value ?? '').trim().toLowerCase();

const patientRecords = computed(() => {
    return (props.patients ?? [])
        .map((patient) => ({
            patient_name: String(patient?.patient_name ?? '').trim(),
            patient_email: String(patient?.patient_email ?? '').trim(),
            patient_phone: String(patient?.patient_phone ?? '').trim(),
            patient_birth_number: String(patient?.patient_birth_number ?? '').trim(),
        }))
        .filter((patient) => patient.patient_name);
});

const formatPatientSuggestionLabel = (patient) => {
    const details = [patient.patient_birth_number, patient.patient_email, patient.patient_phone]
        .filter((value) => String(value ?? '').trim().length > 0)
        .join(' · ');

    return details
        ? `${patient.patient_name} (${details})`
        : patient.patient_name;
};

const uniqueValues = (values) => {
    const seen = new Set();

    return values.filter((value) => {
        const key = normalizeSearch(value);

        if (!key || seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
};

const parsePhoneValue = (value, fallbackCountry = 'SK') => {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return {
            countryCode: fallbackCountry,
            localNumber: '',
            fullNumber: '',
        };
    }

    const normalizedRaw = raw.replace(/\s+/g, ' ').trim();
    const matchedCountry = phoneCountries.find((country) => normalizedRaw.startsWith(country.dialCode));

    if (!matchedCountry) {
        return {
            countryCode: fallbackCountry,
            localNumber: normalizedRaw,
            fullNumber: normalizedRaw,
        };
    }

    const localNumber = normalizedRaw
        .slice(matchedCountry.dialCode.length)
        .trim();

    return {
        countryCode: matchedCountry.value,
        localNumber,
        fullNumber: normalizedRaw,
    };
};

const findPatientByName = (name) => {
    const normalizedName = normalizeSearch(name);

    if (!normalizedName) {
        return null;
    }

    return patientRecords.value.find((patient) => {
        return normalizeSearch(patient.patient_name) === normalizedName;
    }) ?? null;
};

const findPatientByEmail = (email) => {
    const normalizedEmail = normalizeSearch(email);

    if (!normalizedEmail) {
        return null;
    }

    return patientRecords.value.find((patient) => {
        return normalizeSearch(patient.patient_email) === normalizedEmail;
    }) ?? null;
};

const applySelectedPatient = (patient) => {
    if (!patient) {
        return;
    }

    if (patient.patient_name) {
        patientForm.patient_name = patient.patient_name;
    }

    if (patient.patient_email) {
        patientForm.patient_email = patient.patient_email;
    }

    if (patient.patient_phone) {
        const parsedPhone = parsePhoneValue(patient.patient_phone, patientForm.patient_phone_country);
        patientForm.patient_phone_country = parsedPhone.countryCode;
        patientForm.patient_phone = parsedPhone.localNumber;
        patientForm.patient_phone_full = parsedPhone.fullNumber;
    }

    if (patient.patient_birth_number) {
        patientForm.patient_birth_number = patient.patient_birth_number;
    }
};

const completePatientName = (event) => {
    const query = normalizeSearch(event?.query ?? '');
    const matches = patientRecords.value.filter((patient) => {
        if (!query) {
            return true;
        }

        return normalizeSearch(patient.patient_name).includes(query)
            || normalizeSearch(patient.patient_email).includes(query)
            || normalizeSearch(patient.patient_phone).includes(query)
            || normalizeSearch(patient.patient_birth_number).includes(query);
    });

    patientNameSuggestions.value = matches.map((patient) => ({
        label: formatPatientSuggestionLabel(patient),
        value: patient.patient_name,
        patient,
    }));
};

const completePatientEmail = (event) => {
    const query = normalizeSearch(event?.query ?? '');
    const matches = patientRecords.value
        .filter((patient) => patient.patient_email)
        .filter((patient) => {
            if (!query) {
                return true;
            }

            return normalizeSearch(patient.patient_email).includes(query)
                || normalizeSearch(patient.patient_name).includes(query);
        });

    patientEmailSuggestions.value = uniqueValues(matches.map((patient) => patient.patient_email));
};

const onPatientNameSelected = (event) => {
    const selectedPatient = event?.value?.patient
        ?? findPatientByName(event?.value?.value ?? event?.value);

    applySelectedPatient(selectedPatient);
    patientForm.patient_name = selectedPatient?.patient_name ?? '';
};

const onPatientEmailSelected = (event) => {
    applySelectedPatient(findPatientByEmail(event?.value));
};

const rescheduleChoiceVisible = ref(false);
const pendingReschedulePayload = ref(null);
const isResettingGroupForm = ref(false);
const hasManuallyChangedDateTime = ref(false);
const isDetailMode = ref(true);
const addPatientDialogVisible = ref(false);
const duplicatePatientsPromptVisible = ref(false);
const pendingDuplicatePayload = ref(null);
const selectedCapacityPatient = ref(null);
const patientEditorVisible = ref(false);
const editingCapacityPatient = ref(null);
const patientEditorPrefillName = ref('');

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

const parseDateOnly = (value) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date
        ? new Date(value.getFullYear(), value.getMonth(), value.getDate())
        : new Date(`${String(value).slice(0, 10)}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date;
};

const getRecurrenceFrequency = (recurrence) => {
    const frequency = recurrence?.frequency ?? recurrence?.repeat_unit ?? recurrence?.unit;

    if (['daily', 'weekly', 'monthly', 'yearly'].includes(frequency)) {
        return frequency;
    }

    if (frequency === 'days') {
        return 'daily';
    }

    if (frequency === 'months') {
        return 'monthly';
    }

    return 'weekly';
};

const addRecurrenceInterval = (date, frequency, interval) => {
    const next = new Date(date);

    if (frequency === 'daily') {
        next.setDate(next.getDate() + interval);

        return next;
    }

    if (frequency === 'monthly') {
        next.setMonth(next.getMonth() + interval);

        return next;
    }

    if (frequency === 'yearly') {
        next.setFullYear(next.getFullYear() + interval);

        return next;
    }

    next.setDate(next.getDate() + (7 * interval));

    return next;
};

const countOccurrencesBetween = (startDate, endDate, recurrence) => {
    if (!startDate || !endDate || endDate < startDate) {
        return null;
    }

    const frequency = getRecurrenceFrequency(recurrence);
    const interval = Math.max(1, Number(recurrence?.interval ?? recurrence?.repeat_every ?? 1));
    let cursor = new Date(startDate);
    let count = 0;

    while (cursor <= endDate && count < 2000) {
        count += 1;
        cursor = addRecurrenceInterval(cursor, frequency, interval);
    }

    return count;
};

const getFallbackSeriesEndDate = (fromDate) => {
    if (!fromDate) {
        return null;
    }

    const fallback = new Date(fromDate);
    fallback.setFullYear(fallback.getFullYear() + 2);

    return fallback;
};

const currentCapacityWindowRecurrence = computed(() => {
    return props.capacityWindow?.recurrence
        ?? props.capacityWindow?.recurrence_rule
        ?? null;
});

const groupEventDeleteOccurrenceDate = computed(() => {
    return String(
        props.capacityWindow?.occurrence_original_date
        ?? props.capacityWindow?.occurrence_date
        ?? props.capacityWindow?.date
        ?? props.capacityWindow?.starts_at
        ?? '',
    ).slice(0, 10) || null;
});

const groupEventDeleteBranchId = computed(() => Number(
    props.branchId
    ?? props.capacityWindow?.branch_id
    ?? props.capacityWindow?.branch?.id
    ?? 0,
) || null);

const {
    impactPreview: groupEventDeleteImpactPreview,
    fetchImpactPreview: fetchGroupEventDeleteImpactPreview,
    clearImpactPreview: clearGroupEventDeleteImpactPreview,
} = useRecurringImpactPreview(groupEventDeleteBranchId);

const groupEventDeleteSelectedOccurrence = computed(() => {
    if (!props.capacityWindow?.id) {
        return null;
    }

    const timeStart = String(props.capacityWindow?.starts_datetime ?? props.capacityWindow?.starts_at ?? '').slice(11, 19);
    const timeEnd = String(props.capacityWindow?.ends_datetime ?? props.capacityWindow?.ends_at ?? '').slice(11, 19);
    const datePart = String(groupEventDeleteOccurrenceDate.value ?? '').slice(0, 10);

    const occurrenceStartsAt = datePart && timeStart ? `${datePart}T${timeStart}` : null;
    const occurrenceEndsAt = datePart && timeEnd ? `${datePart}T${timeEnd}` : null;

    return {
        capacity_window_id: props.capacityWindow.id,
        event_id: props.capacityWindow.id,
        root_event_id: props.capacityWindow.root_event_id ?? null,
        occurrence_starts_at: occurrenceStartsAt,
        occurrence_ends_at: occurrenceEndsAt,
        occurrence_original_starts_at: occurrenceStartsAt,
        starts_at: occurrenceStartsAt,
        ends_at: occurrenceEndsAt,
        display_key: props.capacityWindow.display_key ?? null,
    };
});

watch(
    () => [props.occurrenceVisible, props.capacityWindow?.id, groupEventDeleteOccurrenceDate.value],
    async ([visible]) => {
        if (!visible || !props.capacityWindow || !isCapacityWindowRepeatable.value) {
            clearGroupEventDeleteImpactPreview();

            return;
        }

        await fetchGroupEventDeleteImpactPreview({
            action: 'delete',
            selectedOccurrence: groupEventDeleteSelectedOccurrence.value,
        });
    },
    { immediate: true },
);

const deleteCountOccurrence = computed(() => groupEventDeleteImpactPreview.value?.occurrence?.count ?? 1);

const deleteCountSeries = computed(() => groupEventDeleteImpactPreview.value?.series?.count ?? null);

const deleteCountFromDate = computed(() => groupEventDeleteImpactPreview.value?.from_date?.count ?? null);
const deleteMessageOccurrence = computed(() => groupEventDeleteImpactPreview.value?.occurrence?.message ?? null);
const deleteMessageFromDate = computed(() => groupEventDeleteImpactPreview.value?.from_date?.message ?? null);
const deleteMessageSeries = computed(() => groupEventDeleteImpactPreview.value?.series?.message ?? null);

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
const occurrenceReferenceDateForBackend = computed(() => {
    return String(
        props.capacityWindow?.occurrence_original_date
        ?? props.capacityWindow?.occurrence_date
        ?? capacityWindowDate.value
        ?? '',
    ).slice(0, 10) || null;
});

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

const canAddSelectedExistingPatient = computed(() => {
    return Boolean(props.capacityWindow)
        && hasFreeCapacity.value
        && Boolean(selectedCapacityPatient.value?.patient_name);
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
    patientForm.patient_birth_number = '';
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

const openAddPatientMode = () => {
    if (!props.capacityWindow || !hasFreeCapacity.value) {
        return;
    }

    selectedCapacityPatient.value = null;
    addPatientDialogVisible.value = true;
};

const closeAddPatientDialog = () => {
    addPatientDialogVisible.value = false;
    selectedCapacityPatient.value = null;
};

const openPatientCreateDialog = ({ prefillName = '' } = {}) => {
    editingCapacityPatient.value = null;
    patientEditorPrefillName.value = String(prefillName ?? '').trim();
    patientEditorVisible.value = true;
};

const openPatientEditDialog = (patient) => {
    editingCapacityPatient.value = patient ?? null;
    patientEditorPrefillName.value = '';
    patientEditorVisible.value = true;
};

const handleCapacityPatientSelected = (patient) => {
    selectedCapacityPatient.value = patient;
};

const handleCapacityPatientModelValueUpdate = (value) => {
    if (!value) {
        selectedCapacityPatient.value = null;
    }
};

const handleCapacityPatientSaved = (patient) => {
    if (!patient) {
        return;
    }

    selectedCapacityPatient.value = patient;
};

const duplicateCapacityWindow = () => {
    pendingDuplicatePayload.value = props.capacityWindow;
    duplicatePatientsPromptVisible.value = true;
};

const confirmDuplicateWithPatients = (copyPatients) => {
    if (!pendingDuplicatePayload.value) {
        duplicatePatientsPromptVisible.value = false;

        return;
    }

    emit('duplicate', pendingDuplicatePayload.value, Boolean(copyPatients));

    pendingDuplicatePayload.value = null;
    duplicatePatientsPromptVisible.value = false;
};

const cancelDuplicatePatientsPrompt = () => {
    pendingDuplicatePayload.value = null;
    duplicatePatientsPromptVisible.value = false;
};

const closeOccurrenceDialog = () => {
    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;
    addPatientDialogVisible.value = false;
    patientEditorVisible.value = false;
    hasManuallyChangedDateTime.value = false;
    isDetailMode.value = true;

    emit('update:occurrenceVisible', false);
};

const buildReschedulePayload = () => {
    return {
        date: occurrenceReferenceDateForBackend.value,
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
    emit('cancel-booking', {
        ...booking,
        capacity_window_id: props.capacityWindow?.capacity_window_id ?? props.capacityWindow?.id ?? null,
    }, {
        notify_patient: true,
    });
};

const normalizePatientValue = (value) => String(value ?? '').trim().toLowerCase();

const resolvePatientForBooking = (booking) => {
    const name = normalizePatientValue(booking?.patient_name);
    const email = normalizePatientValue(booking?.patient_email);
    const phone = normalizePatientValue(String(booking?.patient_phone ?? '').replace(/\s+/g, ''));

    const exactMatch = (props.patients ?? []).find((patient) => {
        const patientName = normalizePatientValue(patient?.patient_name);
        const patientEmail = normalizePatientValue(patient?.patient_email);
        const patientPhone = normalizePatientValue(String(patient?.patient_phone ?? '').replace(/\s+/g, ''));

        return Boolean(
            (email && patientEmail && patientEmail === email)
            || (phone && patientPhone && patientPhone === phone)
            || (name && patientName && patientName === name),
        );
    });

    if (exactMatch) {
        return exactMatch;
    }

    if (!name) {
        return null;
    }

    return (props.patients ?? []).find((patient) => {
        return normalizePatientValue(patient?.patient_name).includes(name)
            || name.includes(normalizePatientValue(patient?.patient_name));
    }) ?? null;
};

const openPatientEditorForBooking = (booking) => {
    const matchedPatient = resolvePatientForBooking(booking);

    if (matchedPatient) {
        openPatientEditDialog(matchedPatient);

        return;
    }

    openPatientCreateDialog({ prefillName: booking?.patient_name ?? '' });
};

const addPatientToCapacityWindow = (patient = null) => {
    const patientName = patient?.patient_name ?? patientForm.patient_name;
    const patientEmail = patient?.patient_email ?? patientForm.patient_email;
    const patientPhone = patient?.patient_phone ?? (patientForm.patient_phone_full || patientForm.patient_phone);
    const patientBirthNumber = patient?.patient_birth_number ?? patientForm.patient_birth_number;

    if (!props.capacityWindow || !hasFreeCapacity.value || !String(patientName ?? '').trim()) {
        return false;
    }

    pendingReschedulePayload.value = null;
    rescheduleChoiceVisible.value = false;

    emit('add-patient-to-capacity-window', props.capacityWindow, {
        date: selectedDateForBackend.value,
        starts_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.starts_at)),
        ends_at: formatDateTimeForBackend(mergeDateAndTime(groupForm.date, groupForm.ends_at)),
        patient_name: String(patientName ?? '').trim(),
        patient_email: String(patientEmail ?? '').trim(),
        patient_phone: String(patientPhone ?? '').trim(),
        patient_birth_number: String(patientBirthNumber ?? '').trim() || null,
        notify_patient: true,
    });

    resetPatientForm();

    return true;
};

const submitPatientFromMiniDialog = () => {
    const wasAdded = addPatientToCapacityWindow(selectedCapacityPatient.value);

    if (wasAdded) {
        addPatientDialogVisible.value = false;
        selectedCapacityPatient.value = null;
    }
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
        :delete-message-occurrence="deleteMessageOccurrence"
        :delete-message-from-date="deleteMessageFromDate"
        :delete-message-series="deleteMessageSeries"
        @close="closeOccurrenceDialog"
        @save="rescheduleCapacityWindow"
        @delete-occurrence="deleteCapacityWindowOccurrence"
        @delete-from-now-on="deleteCapacityWindowFromDate"
        @delete-all="deleteCapacityWindowSeries"
    >
        <template #footer-start>
            <EventOccurrenceActions
                v-if="capacityWindow && isDetailMode"
                show-patients
                patients-label="+ Pacient"
                @duplicate="duplicateCapacityWindow"
                @patients="openAddPatientMode"
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
                        :patient-birth-number="booking.patient_birth_number"
                    >
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                label="Upraviť pacienta"
                                severity="secondary"
                                outlined
                                size="small"
                                @click="openPatientEditorForBooking(booking)"
                            />

                            <Button
                                type="button"
                                label="Odstrániť pacienta"
                                severity="danger"
                                outlined
                                size="small"
                                @click="cancelPatientBooking(booking)"
                            />
                        </div>
                    </PatientCard>
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
                        :patient-birth-number="booking.patient_birth_number"
                    >
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                label="Upraviť pacienta"
                                severity="secondary"
                                outlined
                                size="small"
                                @click="openPatientEditorForBooking(booking)"
                            />

                            <Button
                                type="button"
                                label="Odstrániť pacienta"
                                severity="danger"
                                outlined
                                size="small"
                                @click="cancelPatientBooking(booking)"
                            />
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
                    <AutoComplete
                        id="capacity_new_patient_name"
                        v-model="patientForm.patient_name"
                        :suggestions="patientNameSuggestions"
                        option-label="label"
                        dropdown
                        complete-on-focus
                        class="w-full"
                        placeholder="Meno a priezvisko"
                        :disabled="!hasFreeCapacity"
                        @complete="completePatientName"
                        @item-select="onPatientNameSelected"
                    >
                        <template #option="{ option }">
                            <div class="flex flex-col">
                                <span class="font-medium">{{ option.value }}</span>
                                <span class="text-xs text-accent">{{ option.label }}</span>
                            </div>
                        </template>
                    </AutoComplete>
                </FormField>

                <FormField label="Email" for="capacity_new_patient_email">
                    <AutoComplete
                        id="capacity_new_patient_email"
                        v-model="patientForm.patient_email"
                        :suggestions="patientEmailSuggestions"
                        dropdown
                        complete-on-focus
                        class="w-full"
                        placeholder="email@example.com"
                        :disabled="!hasFreeCapacity"
                        @complete="completePatientEmail"
                        @item-select="onPatientEmailSelected"
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

                <FormField label="Rodné číslo" for="capacity_new_patient_birth_number" span="md:col-span-2">
                    <InputText
                        id="capacity_new_patient_birth_number"
                        v-model="patientForm.patient_birth_number"
                        class="w-full"
                        placeholder="napr. 900101/1234"
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

    <FormDialog
        v-model:visible="addPatientDialogVisible"
        title="Pridať pacienta"
        width="max-w-xl"
        :dismissable-mask="true"
        @close="closeAddPatientDialog"
    >
        <form class="space-y-4" @submit.prevent="submitPatientFromMiniDialog">
            <PatientLookupField
                input-id="capacity_dialog_existing_patient"
                :model-value="selectedCapacityPatient?.patient_name ?? ''"
                :patients="patients"
                placeholder="Vyberte pacienta"
                add-button-label="Pridať pacienta"
                footer-add-button-label="Pridať nového pacienta"
                edit-button-label="Upraviť pacienta"
                @update:model-value="handleCapacityPatientModelValueUpdate"
                @select-patient="handleCapacityPatientSelected"
                @request-add-patient="openPatientCreateDialog"
                @request-edit-patient="openPatientEditDialog"
            />

            <div class="flex justify-end">
                <Button
                    type="submit"
                    label="Pridať pacienta"
                    icon="pi pi-user-plus"
                    :disabled="!canAddSelectedExistingPatient"
                />
            </div>
        </form>
    </FormDialog>

    <PatientFormDialog
        v-model:visible="patientEditorVisible"
        :branch-id="branchId"
        :patient="editingCapacityPatient"
        :prefill-name="patientEditorPrefillName"
        @saved="handleCapacityPatientSaved"
    />

    <FormDialog
        v-model:visible="duplicatePatientsPromptVisible"
        title="Duplikovať skupinový termín"
        description="Vyberte, či sa majú do nového termínu skopírovať aj pacienti."
        width="max-w-lg"
        :show-footer="true"
        close-label="Zrušiť"
        @close="cancelDuplicatePatientsPrompt"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 rounded-md bg-soft p-3 text-sm text-accent">
                <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-white text-accent">
                    <i class="pi pi-copy text-base" />
                </div>

                <div class="min-w-0">
                    <p class="font-semibold text-dark">
                        Chcete skopírovať aj pacientov?
                    </p>

                    <p class="mt-1 text-xs leading-5 text-accent">
                        Ak ich neskopírujete, vytvorí sa iba nový skupinový termín so službou, časom a opakovaním.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Bez pacientov"
                severity="secondary"
                outlined
                @click="confirmDuplicateWithPatients(false)"
            />

            <Button
                type="button"
                label="S pacientmi"
                @click="confirmDuplicateWithPatients(true)"
            />
        </template>
    </FormDialog>
</template>
