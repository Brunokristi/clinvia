<script setup>
import Button from 'primevue/button';
import AutoComplete from 'primevue/autocomplete';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

import EventCreateEditDialog from '@/Components/Booking/Common/EventCreateEditDialog.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import RecurrencePicker from '@/Components/Calendar/RecurrencePicker.vue';
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
    patients: {
        type: Array,
        default: () => [],
    },
    selection: {
        type: Object,
        default: null,
    },
    prefill: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'create-booking',
]);

const createTypeOptions = [
    { label: 'Rezervácia', value: 'booking' },
    { label: 'Pravidlo online rezervácií', value: 'rule' },
    { label: 'Skupinový termín', value: 'group_event' },
];

const bookingModeOptions = [
    { label: 'Okamžitá rezervácia', value: 'immediate_booking' },
    { label: 'Rezervácia na schválenie', value: 'appointment_request' },
];

const phoneCountries = [
    { value: 'SK', dialCode: '+421' },
    { value: 'CZ', dialCode: '+420' },
    { value: 'AT', dialCode: '+43' },
    { value: 'HU', dialCode: '+36' },
    { value: 'PL', dialCode: '+48' },
];

const form = reactive({
    create_type: 'booking',
    recurrence: null,
    is_enabled: true,
    public_booking_type: 'immediate_booking',
    capacity: 5,
    service_ids: [],
    date: null,
    starts_at: null,
    ends_at: null,
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_birth_number: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
    group_patients: [],
    group_patient_name: '',
    group_patient_email: '',
    group_patient_phone: '',
    group_patient_birth_number: '',
    group_patient_phone_country: 'SK',
    group_patient_phone_full: '',
});

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable ?? true)
        .map((service) => ({ label: service.name, value: service.id }));
});

const patientRecords = computed(() => {
    return (props.patients ?? [])
        .map((patient) => ({
            id: patient?.id ?? null,
            patient_name: String(patient?.patient_name ?? '').trim(),
            patient_email: String(patient?.patient_email ?? '').trim(),
            patient_phone: String(patient?.patient_phone ?? '').trim(),
            patient_birth_number: String(patient?.patient_birth_number ?? '').trim(),
        }))
        .filter((patient) => patient.patient_name);
});

const bookingPatientNameSuggestions = ref([]);
const bookingPatientEmailSuggestions = ref([]);
const groupPatientNameSuggestions = ref([]);

const formatPatientSuggestionLabel = (patient) => {
    const details = [patient.patient_birth_number, patient.patient_email, patient.patient_phone]
        .filter((value) => String(value ?? '').trim().length > 0)
        .join(' · ');

    return details
        ? `${patient.patient_name} (${details})`
        : patient.patient_name;
};

const normalizeSearch = (value) => String(value ?? '').trim().toLowerCase();

const uniqueValues = (values) => {
    const seen = new Set();

    return values.filter((value) => {
        const key = String(value ?? '').trim().toLowerCase();

        if (!key || seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
};

const getMatchingPatients = (query) => {
    const needle = normalizeSearch(query);

    return patientRecords.value.filter((patient) => {
        if (!needle) {
            return true;
        }

        return normalizeSearch(patient.patient_name).includes(needle)
            || normalizeSearch(patient.patient_email).includes(needle)
            || normalizeSearch(patient.patient_phone).includes(needle)
            || normalizeSearch(patient.patient_birth_number).includes(needle);
    });
};

const applyBookingPatient = (patient) => {
    if (!patient) {
        return;
    }

    if (patient.patient_name) {
        form.patient_name = patient.patient_name;
    }

    if (patient.patient_email) {
        form.patient_email = patient.patient_email;
    }

    if (patient.patient_phone) {
        const phone = parsePhoneValue(patient.patient_phone, form.patient_phone_country);
        form.patient_phone_country = phone.countryCode;
        form.patient_phone = phone.localNumber;
        form.patient_phone_full = phone.fullNumber;
    }

    if (patient.patient_birth_number) {
        form.patient_birth_number = patient.patient_birth_number;
    }
};

const applyGroupPatient = (patient) => {
    if (!patient) {
        return;
    }

    if (patient.patient_name) {
        form.group_patient_name = patient.patient_name;
    }

    if (patient.patient_email) {
        form.group_patient_email = patient.patient_email;
    }

    if (patient.patient_phone) {
        const phone = parsePhoneValue(patient.patient_phone, form.group_patient_phone_country);
        form.group_patient_phone_country = phone.countryCode;
        form.group_patient_phone = phone.localNumber;
        form.group_patient_phone_full = phone.fullNumber;
    }

    if (patient.patient_birth_number) {
        form.group_patient_birth_number = patient.patient_birth_number;
    }
};

const completeBookingPatientName = (event) => {
    const matches = getMatchingPatients(event?.query ?? '');

    bookingPatientNameSuggestions.value = matches.map((patient) => ({
        label: formatPatientSuggestionLabel(patient),
        value: patient.patient_name,
        patient,
    }));
};

const completeBookingPatientEmail = (event) => {
    const matches = getMatchingPatients(event?.query ?? '')
        .filter((patient) => patient.patient_email);

    bookingPatientEmailSuggestions.value = uniqueValues(
        matches.map((patient) => patient.patient_email),
    );
};

const completeGroupPatientName = (event) => {
    const matches = getMatchingPatients(event?.query ?? '');

    groupPatientNameSuggestions.value = matches.map((patient) => ({
        label: formatPatientSuggestionLabel(patient),
        value: patient.patient_name,
        patient,
    }));
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

const onBookingPatientNameSelect = (event) => {
    const selectedPatient = event?.value?.patient
        ?? findPatientByName(event?.value?.value ?? event?.value);

    applyBookingPatient(selectedPatient);
    form.patient_name = selectedPatient?.patient_name ?? '';
};

const onBookingPatientEmailSelect = (event) => {
    applyBookingPatient(findPatientByEmail(event?.value));
};

const onGroupPatientNameSelect = (event) => {
    const selectedPatient = event?.value?.patient
        ?? findPatientByName(event?.value?.value ?? event?.value);

    applyGroupPatient(selectedPatient);
    form.group_patient_name = selectedPatient?.patient_name ?? '';
};

const selectedServices = computed(() => {
    return props.services.filter((service) => {
        return form.service_ids.map(Number).includes(Number(service.id));
    });
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

    return selectedServices.value.map((service) => service.name).join(', ');
});

const isBookingType = computed(() => form.create_type === 'booking');
const isRuleType = computed(() => form.create_type === 'rule');
const isGroupEventType = computed(() => form.create_type === 'group_event');

const groupServiceModel = computed({
    get: () => form.service_ids[0] ?? null,
    set: (value) => {
        form.service_ids = value ? [value] : [];
    },
});

const isEditMode = computed(() => Boolean(props.prefill?.edit_mode));
const isRecurringScopedEdit = computed(() => {
    return Boolean(
        isEditMode.value
        && ['booking', 'rule', 'group_event'].includes(form.create_type)
        && ['booking', 'rule', 'group_event'].includes(props.prefill?.target_type)
        && props.prefill?.target_is_recurring,
    );
});

const currentEntityLabel = computed(() => {
    if (isRuleType.value) {
        return 'pravidlo online rezervácií';
    }

    if (isGroupEventType.value) {
        return 'skupinový termín';
    }

    return 'rezerváciu';
});

const createDialogTitle = computed(() => {
    return isEditMode.value
        ? `Upraviť ${currentEntityLabel.value}`
        : 'Vytvoriť udalosť';
});

const createSubmitLabel = computed(() => {
    return isEditMode.value ? 'Uložiť zmeny' : 'Vytvoriť udalosť';
});

const parseDateValue = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return new Date(value);
    }

    const normalized = String(value).includes('T')
        ? String(value)
        : String(value).replace(' ', 'T');

    // Keep wall-clock time stable by ignoring timezone suffixes in admin form prefills.
    const withoutTimezone = normalized
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '');

    const parsed = new Date(withoutTimezone);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
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

const formatDateForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);

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

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const createDateFromDateAndTime = (dateValue, timeValue) => {
    if (!dateValue || !timeValue) {
        return null;
    }

    const date = dateValue instanceof Date ? new Date(dateValue) : new Date(dateValue);

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

const canCreateSubmit = computed(() => {
    const hasBaseValues = Boolean(form.create_type)
        && Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at);

    if (!hasBaseValues) {
        return false;
    }

    if (isRuleType.value) {
        return Boolean(form.service_ids.length)
            && Boolean(form.public_booking_type);
    }

    if (isGroupEventType.value) {
        return Boolean(form.service_ids.length)
            && Number(form.capacity ?? 0) > 0
            && Boolean(form.public_booking_type);
    }

    return Boolean(form.service_ids.length)
        && Boolean(form.patient_name.trim())
        && selectedServicesDuration.value > 0;
});

const eventTypeHint = computed(() => {
    if (isBookingType.value) {
        return 'Rezervácia je konkrétny termín pre jedného pacienta. Použite ju vtedy, keď už poznáte pacienta a chcete mu vytvoriť pevný termín priamo v kalendári.';
    }

    if (isRuleType.value) {
        return 'Pravidlo online rezervácií určuje, kedy sa môžu pacienti objednávať cez verejnú rezervačnú stránku. Nejde o konkrétneho pacienta, ale o dostupný čas, ktorý sa môže podľa potreby opakovať.';
    }

    if (isGroupEventType.value) {
        return 'Skupinový termín je jeden spoločný termín pre viacerých pacientov. Použite ho napríklad pri skupinových vyšetreniach, kurzoch alebo termínoch s obmedzenou kapacitou.';
    }

    return null;
});

const bookingModeHint = computed(() => {
    if (form.public_booking_type === 'immediate_booking') {
        return 'Pri okamžitej rezervácii sa pacient po výbere voľného termínu automaticky objedná. Termín sa hneď zapíše do kalendára bez dodatočného schvaľovania.';
    }

    if (form.public_booking_type === 'appointment_request') {
        return 'Pri rezervácii na schválenie pacient odošle žiadosť o termín. Termín sa potvrdí až po tom, ako ho ambulancia schváli.';
    }

    return null;
});

const selectedServicesHint = computed(() => {
    if (!selectedServices.value.length) {
        return null;
    }

    return `Vybrané služby: ${selectedServicesLabel.value}`;
});

const canAddGroupPatient = computed(() => {
    return Boolean(form.group_patient_name.trim());
});

const canManageGroupPatientsOnCreate = computed(() => {
    if (!isGroupEventType.value) {
        return false;
    }

    // For recurring group-event creation, patients are added per concrete occurrence later.
    return isEditMode.value || !form.recurrence;
});

const remainingGroupCapacity = computed(() => {
    const capacity = Number(form.capacity ?? 0);

    if (!capacity) {
        return 0;
    }

    return Math.max(0, capacity - form.group_patients.length);
});

const canAddMoreGroupPatients = computed(() => {
    return remainingGroupCapacity.value > 0;
});

const resetGroupPatientDraft = () => {
    form.group_patient_name = '';
    form.group_patient_email = '';
    form.group_patient_phone = '';
    form.group_patient_birth_number = '';
    form.group_patient_phone_country = 'SK';
    form.group_patient_phone_full = '';
};

const addGroupPatient = () => {
    if (!canAddGroupPatient.value || !canAddMoreGroupPatients.value) {
        return;
    }

    form.group_patients.push({
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        patient_name: form.group_patient_name.trim(),
        patient_email: form.group_patient_email?.trim() || null,
        patient_phone: (form.group_patient_phone_full || form.group_patient_phone || '').trim() || null,
        patient_birth_number: form.group_patient_birth_number?.trim() || null,
    });

    resetGroupPatientDraft();
};

const removeGroupPatient = (index) => {
    if (index < 0 || index >= form.group_patients.length) {
        return;
    }

    form.group_patients.splice(index, 1);
};

const resetCreateForm = () => {
    form.create_type = 'booking';
    form.recurrence = null;
    form.public_booking_type = 'immediate_booking';
    form.capacity = 5;
    form.service_ids = [];
    form.ends_at = null;

    if (props.selection?.start) {
        const start = props.selection.start instanceof Date
            ? props.selection.start
            : new Date(props.selection.start);

        const end = props.selection?.end
            ? (props.selection.end instanceof Date
                ? props.selection.end
                : new Date(props.selection.end))
            : (() => {
                const fallbackEnd = new Date(start);
                fallbackEnd.setMinutes(fallbackEnd.getMinutes() + 30);

                return fallbackEnd;
            })();

        form.date = props.selection?.date
            ? new Date(`${props.selection.date}T00:00:00`)
            : start;

        form.starts_at = props.selection?.starts_at
            ? createDateFromDateAndTime(form.date, props.selection.starts_at)
            : start;

        form.ends_at = props.selection?.ends_at
            ? createDateFromDateAndTime(form.date, props.selection.ends_at)
            : end;
    } else {
        form.date = null;
        form.starts_at = null;
        form.ends_at = null;
    }

    form.patient_name = '';
    form.patient_email = '';
    form.patient_phone = '';
    form.patient_birth_number = '';
    form.patient_phone_country = 'SK';
    form.patient_phone_full = '';
    form.group_patients = [];
    resetGroupPatientDraft();

    if (props.prefill) {
        const prefillStartsAtSource = parseDateValue(props.prefill.starts_at);
        const prefillDate = props.prefill.date
            ? new Date(`${props.prefill.date}T00:00:00`)
            : (prefillStartsAtSource ? new Date(prefillStartsAtSource) : null);

        form.create_type = props.prefill.create_type ?? form.create_type;
        form.recurrence = props.prefill.recurrence ?? null;
        form.service_ids = [...(props.prefill.service_ids ?? form.service_ids)];
        form.capacity = Number(props.prefill.capacity ?? form.capacity ?? 5);

        if (prefillDate && !Number.isNaN(prefillDate.getTime())) {
            form.date = prefillDate;
            form.date.setHours(0, 0, 0, 0);
        }

        const prefillEndsAt = parseDateValue(props.prefill.ends_at);

        if (prefillStartsAtSource) {
            form.starts_at = prefillStartsAtSource;
        }

        if (prefillEndsAt) {
            form.ends_at = prefillEndsAt;
        }

        form.patient_name = props.prefill.patient_name ?? '';
        form.patient_email = props.prefill.patient_email ?? '';
        const patientPhone = parsePhoneValue(props.prefill.patient_phone, form.patient_phone_country);
        form.patient_phone_country = patientPhone.countryCode;
        form.patient_phone = patientPhone.localNumber;
        form.patient_phone_full = patientPhone.fullNumber;
        form.patient_birth_number = props.prefill.patient_birth_number ?? '';
        form.public_booking_type = props.prefill.public_booking_type ?? form.public_booking_type;
        form.group_patients = (props.prefill.group_patients ?? []).map((patient, index) => ({
            id: patient.id ?? `prefill-${index}`,
            patient_name: patient.patient_name ?? '',
            patient_email: patient.patient_email ?? null,
            patient_phone: patient.patient_phone ?? null,
            patient_birth_number: patient.patient_birth_number ?? null,
        }));
    }
};

watch(() => form.capacity, () => {
    const capacity = Number(form.capacity ?? 0);

    if (!capacity || form.group_patients.length <= capacity) {
        return;
    }

    form.group_patients = form.group_patients.slice(0, capacity);
});

watch(() => props.visible, (visible) => {
    if (visible) {
        resetCreateForm();
    }
});

watch(() => props.selection, () => {
    if (props.visible) {
        resetCreateForm();
    }
});

watch(
    () => [form.create_type, form.recurrence],
    () => {
        if (!isGroupEventType.value || canManageGroupPatientsOnCreate.value) {
            return;
        }

        form.group_patients = [];
        resetGroupPatientDraft();
    },
    { deep: true },
);

watch(calculatedEndsAtDate, (endsAt) => {
    if (!isBookingType.value || !endsAt) {
        return;
    }

    form.ends_at = endsAt;
});

watch(
    () => [form.service_ids, form.date, form.starts_at],
    () => {
        if (!isBookingType.value) {
            return;
        }

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
    { deep: true },
);

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const submitCreate = (saveScope = null) => {
    if (!canCreateSubmit.value) {
        return;
    }

    const recurrence = form.recurrence
        ? JSON.parse(JSON.stringify(form.recurrence))
        : null;
    const repeatEvery = recurrence
        ? Math.max(1, Number(recurrence.interval ?? 1))
        : 1;

    let repeatUnit = 'weeks';
    let normalizedRepeatEvery = repeatEvery;

    if (recurrence?.frequency === 'daily') {
        repeatUnit = 'days';
    } else if (recurrence?.frequency === 'monthly') {
        repeatUnit = 'months';
    } else if (recurrence?.frequency === 'yearly') {
        repeatUnit = 'months';
        normalizedRepeatEvery = repeatEvery * 12;
    }

    const submittedGroupPatients = canManageGroupPatientsOnCreate.value
        ? [...form.group_patients]
        : [];

    if (canManageGroupPatientsOnCreate.value && canAddGroupPatient.value) {
        submittedGroupPatients.push({
            id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            patient_name: form.group_patient_name.trim(),
            patient_email: form.group_patient_email?.trim() || null,
            patient_phone: (form.group_patient_phone_full || form.group_patient_phone || '').trim() || null,
            patient_birth_number: form.group_patient_birth_number?.trim() || null,
        });
    }

    emit('create-booking', {
        create_type: form.create_type,
        edit_mode: Boolean(props.prefill?.edit_mode),
        target_type: props.prefill?.target_type ?? null,
        target_id: props.prefill?.target_id ?? null,
        original_starts_at: props.prefill?.original_starts_at ?? null,
        original_ends_at: props.prefill?.original_ends_at ?? null,
        target_calendar_event_id: props.prefill?.target_calendar_event_id ?? null,
        target_occurrence_date: props.prefill?.target_occurrence_date ?? null,
        target_is_recurring: Boolean(props.prefill?.target_is_recurring),
        target_original_recurrence: props.prefill?.target_original_recurrence ?? null,
        save_scope: saveScope,
        recurrence,
        is_enabled: recurrence ? true : form.is_enabled,
        repeats: Boolean(recurrence),
        repeat_every: normalizedRepeatEvery,
        repeat_unit: repeatUnit,
        public_booking_type: form.public_booking_type,
        capacity: form.capacity,
        service_ids: form.service_ids,
        service_id: form.service_ids[0] ?? null,
        booking_slot_id: null,
        starts_at: startsAtForBackend.value,
        ends_at: endsAtForBackend.value,
        patient_name: form.patient_name,
        patient_email: form.patient_email,
        patient_phone: form.patient_phone_full || form.patient_phone,
        patient_birth_number: form.patient_birth_number,
        group_patients: submittedGroupPatients.map((patient) => ({
            patient_name: patient.patient_name,
            patient_email: patient.patient_email,
            patient_phone: patient.patient_phone,
            patient_birth_number: patient.patient_birth_number,
        })),
    });
};
</script>

<template>
    <EventCreateEditDialog
        v-model:visible="dialogVisible"
        v-model:date="form.date"
        v-model:starts-at="form.starts_at"
        v-model:ends-at="form.ends_at"
        width="max-w-3xl"
        :is-repeatable="isRecurringScopedEdit"
        :save-label="createSubmitLabel"
        :save-disabled="!canCreateSubmit"
        :show-delete="false"
        scope-mode="update"
        :scope-subject-label="currentEntityLabel"
        :title="createDialogTitle"
        @close="closeDialog"
        @save="submitCreate"
        @save-scope="submitCreate"
    >
        <RecurrencePicker
            v-model="form.recurrence"
            :date="form.date"
        />

        <FormField
            label="Typ udalosti"
            for="create_type"
            class="space-y-1"
        >
            <template v-if="isEditMode">
                <div class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 text-sm">
                    <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-soft text-dark">
                        <i class="pi pi-lock text-base" />
                    </div>

                    <div class="flex min-w-0 items-center rounded-md border border-stroke bg-white px-3 py-2 font-medium text-dark">
                        {{ currentEntityLabel }}
                    </div>
                </div>

                <p class="text-xs text-accent">
                    Typ udalosti sa pri úprave nedá meniť.
                </p>
            </template>

            <Select
                v-else
                id="create_type"
                v-model="form.create_type"
                :options="createTypeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
            />

            <div
                v-if="eventTypeHint"
                class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 text-xs text-accent"
            >
                <div class="flex h-full min-h-8 items-center justify-center rounded-md bg-soft text-dark">
                    <i class="pi pi-info-circle text-base" />
                </div>

                <p class="flex min-w-0 items-center font-medium leading-relaxed">
                    {{ eventTypeHint }}
                </p>
            </div>
        </FormField>

        <FormPage
            :submit-label="createSubmitLabel"
            :loading="false"
            :show-submit="false"
        >
            <template v-if="isBookingType">
                <FormSection
                    title="Pacient"
                    description="Zadajte pacienta, pre ktorého vytvárate konkrétny termín."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Meno pacienta"
                        for="patient_name"
                        required
                        span="md:col-span-2"
                    >
                        <AutoComplete
                            id="patient_name"
                            v-model="form.patient_name"
                            :suggestions="bookingPatientNameSuggestions"
                            option-label="label"
                            dropdown
                            complete-on-focus
                            class="w-full"
                            placeholder="Meno a priezvisko"
                            @complete="completeBookingPatientName"
                            @item-select="onBookingPatientNameSelect"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ option.value }}</span>
                                    <span class="text-xs text-accent">{{ option.label }}</span>
                                </div>
                            </template>
                        </AutoComplete>
                    </FormField>

                    <FormField
                        label="E-mail"
                        for="patient_email"
                    >
                        <AutoComplete
                            id="patient_email"
                            v-model="form.patient_email"
                            :suggestions="bookingPatientEmailSuggestions"
                            dropdown
                            complete-on-focus
                            class="w-full"
                            placeholder="napr. pacient@email.sk"
                            @complete="completeBookingPatientEmail"
                            @item-select="onBookingPatientEmailSelect"
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

                    <FormField
                        label="Rodné číslo"
                        for="patient_birth_number"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="patient_birth_number"
                            v-model="form.patient_birth_number"
                            class="w-full"
                            placeholder="napr. 900101/1234"
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    title="Služby"
                    description="Vyberte služby, ktoré budú súčasťou rezervácie."
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
                            placeholder="Vyberte jednu alebo viac služieb"
                            display="chip"
                            filter
                            filter-placeholder="Hľadať služby"
                            class="w-full"
                        />
                    </FormField>
                </FormSection>
            </template>

            <template v-else-if="isRuleType">
                <FormSection
                    title="Pravidlo online rezervácií"
                    description="Nastavte dostupnosť, do ktorej sa pacienti môžu objednávať cez online rezervácie."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Služby"
                        for="rule_service_ids"
                        required
                        span="md:col-span-2"
                    >
                        <MultiSelect
                            id="rule_service_ids"
                            v-model="form.service_ids"
                            :options="serviceOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Vyberte jednu alebo viac služieb"
                            display="chip"
                            filter
                            filter-placeholder="Hľadať služby"
                            class="w-full"
                        />
                    </FormField>

                    <FormField
                        label="Spôsob rezervácie"
                        for="rule_public_booking_type"
                        required
                        span="space-y-1 md:col-span-2"
                    >
                        <Select
                            id="rule_public_booking_type"
                            v-model="form.public_booking_type"
                            :options="bookingModeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />

                        <div
                            v-if="bookingModeHint"
                            class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 text-xs text-accent"
                        >
                            <div class="flex h-full min-h-8 items-center justify-center rounded-md bg-soft text-dark">
                                <i class="pi pi-info-circle text-base" />
                            </div>

                            <p class="flex min-w-0 items-center font-medium leading-relaxed">
                                {{ bookingModeHint }}
                            </p>
                        </div>
                    </FormField>
                </FormSection>
            </template>

            <template v-else-if="isGroupEventType">
                <FormSection
                    title="Skupinový termín"
                    description="Nastavte termín, kapacitu a voliteľne pridajte pacientov už pri vytvorení skupiny."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Služba"
                        for="group_service_id"
                        required
                        span="md:col-span-2"
                    >
                        <Select
                            id="group_service_id"
                            v-model="groupServiceModel"
                            :options="serviceOptions"
                            option-label="label"
                            option-value="value"
                            placeholder="Vyberte službu"
                            class="w-full"
                        />
                    </FormField>

                    <FormField
                        label="Kapacita"
                        for="group_capacity"
                        required
                        span="md:col-span-2"
                    >
                        <InputNumber
                            id="group_capacity"
                            v-model="form.capacity"
                            :min="1"
                            class="w-full"
                            input-class="w-full"
                            placeholder="Napr. 5"
                        />
                    </FormField>

                    <FormField
                        label="Spôsob rezervácie"
                        for="group_public_booking_type"
                        required
                        span="space-y-1 md:col-span-2"
                    >
                        <Select
                            id="group_public_booking_type"
                            v-model="form.public_booking_type"
                            :options="bookingModeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />

                        <div
                            v-if="bookingModeHint"
                            class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3 text-xs text-accent"
                        >
                            <div class="flex h-full min-h-8 items-center justify-center rounded-md bg-soft text-dark">
                                <i class="pi pi-info-circle text-base" />
                            </div>

                            <p class="flex min-w-0 items-center font-medium leading-relaxed">
                                {{ bookingModeHint }}
                            </p>
                        </div>
                    </FormField>
                </FormSection>

                <FormSection
                    v-if="canManageGroupPatientsOnCreate"
                    title="Pacienti v skupine"
                    description="Pacientov môžete pridať hneď teraz, nie je to povinné."
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Meno pacienta"
                        for="group_patient_name"
                        span="md:col-span-2"
                    >
                        <AutoComplete
                            id="group_patient_name"
                            v-model="form.group_patient_name"
                            :suggestions="groupPatientNameSuggestions"
                            option-label="label"
                            dropdown
                            complete-on-focus
                            class="w-full"
                            placeholder="Meno a priezvisko"
                            :disabled="!canAddMoreGroupPatients"
                            @complete="completeGroupPatientName"
                            @item-select="onGroupPatientNameSelect"
                        >
                            <template #option="{ option }">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ option.value }}</span>
                                    <span class="text-xs text-accent">{{ option.label }}</span>
                                </div>
                            </template>
                        </AutoComplete>
                    </FormField>

                    <FormField
                        label="E-mail"
                        for="group_patient_email"
                    >
                        <InputText
                            id="group_patient_email"
                            v-model="form.group_patient_email"
                            type="email"
                            class="w-full"
                            placeholder="napr. pacient@email.sk"
                            :disabled="!canAddMoreGroupPatients"
                        />
                    </FormField>

                    <FormField
                        label="Telefón"
                        for="group_patient_phone"
                    >
                        <PhoneInput
                            v-model="form.group_patient_phone"
                            v-model:country-code="form.group_patient_phone_country"
                            v-model:full-value="form.group_patient_phone_full"
                            :disabled="!canAddMoreGroupPatients"
                        />
                    </FormField>

                    <FormField
                        label="Rodné číslo"
                        for="group_patient_birth_number"
                        span="md:col-span-2"
                    >
                        <InputText
                            id="group_patient_birth_number"
                            v-model="form.group_patient_birth_number"
                            class="w-full"
                            placeholder="napr. 900101/1234"
                            :disabled="!canAddMoreGroupPatients"
                        />
                    </FormField>

                    <div class="flex justify-end md:col-span-2">
                        <Button
                            type="button"
                            label="Pridať pacienta"
                            icon="pi pi-user-plus"
                            :disabled="!canAddGroupPatient || !canAddMoreGroupPatients"
                            @click="addGroupPatient"
                        />
                    </div>

                    <div
                        v-if="form.group_patients.length"
                        class="space-y-2 md:col-span-2"
                    >
                        <PatientCard
                            v-for="(patient, index) in form.group_patients"
                            :key="patient.id"
                            :patient-name="patient.patient_name"
                            :patient-phone="patient.patient_phone"
                            :patient-email="patient.patient_email"
                            :patient-birth-number="patient.patient_birth_number"
                        >
                            <div class="mt-4">
                                <Button
                                    type="button"
                                    label="Odstrániť pacienta"
                                    severity="danger"
                                    outlined
                                    size="small"
                                    @click="removeGroupPatient(index)"
                                />
                            </div>
                        </PatientCard>
                    </div>

                    <div class="rounded-md bg-soft p-3 text-sm text-accent md:col-span-2">
                        Obsadenosť pri vytvorení: {{ form.group_patients.length }} / {{ Number(form.capacity ?? 0) || '0' }}
                    </div>
                </FormSection>

                <FormSection
                    v-else
                    title="Pacienti v skupine"
                    description="Pri opakovanom skupinovom termíne sa pacienti pridávajú až do konkrétneho výskytu po vytvorení série."
                    columns="md:grid-cols-1"
                >
                    <div class="rounded-md bg-soft p-3 text-sm text-accent">
                        Najprv uložte opakovanie, potom otvorte konkrétny termín a pridajte pacienta tam.
                    </div>
                </FormSection>
            </template>
        </FormPage>
    </EventCreateEditDialog>
</template>