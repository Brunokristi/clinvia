<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import { computed, ref, watch } from 'vue';

import PatientCard from '@/Components/Calendar/PatientCard.vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    patients: {
        type: Array,
        default: () => [],
    },
    inputId: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Meno a priezvisko',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'Nenasiel sa ziadny pacient.',
    },
    addButtonLabel: {
        type: String,
        default: 'Pridat pacienta',
    },
    footerAddButtonLabel: {
        type: String,
        default: 'Pridat noveho pacienta',
    },
    showSelectedCard: {
        type: Boolean,
        default: true,
    },
    editButtonLabel: {
        type: String,
        default: 'Upravit pacienta',
    },
});

const emit = defineEmits([
    'update:modelValue',
    'select-patient',
    'request-add-patient',
    'request-edit-patient',
]);

const suggestions = ref([]);
const selectedPatient = ref(null);

const normalizeSearch = (value) => String(value ?? '').trim().toLowerCase();

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

const formatPatientSuggestionLabel = (patient) => {
    const details = [patient.patient_birth_number, patient.patient_email, patient.patient_phone]
        .filter((value) => String(value ?? '').trim().length > 0)
        .join(' · ');

    return details
        ? `${patient.patient_name} (${details})`
        : patient.patient_name;
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

const findPatientByName = (name) => {
    const normalizedName = normalizeSearch(name);

    if (!normalizedName) {
        return null;
    }

    return patientRecords.value.find((patient) => {
        return normalizeSearch(patient.patient_name) === normalizedName;
    }) ?? null;
};

const completePatients = (event) => {
    const matches = getMatchingPatients(event?.query ?? '');

    suggestions.value = matches.map((patient) => ({
        label: formatPatientSuggestionLabel(patient),
        value: patient.patient_name,
        patient,
    }));
};

const onPatientSelected = (event) => {
    const patient = event?.value?.patient
        ?? findPatientByName(event?.value?.value ?? event?.value);

    selectedPatient.value = patient;

    emit('select-patient', patient);

    if (patient?.patient_name) {
        emit('update:modelValue', patient.patient_name);
    }
};

const requestAddPatient = () => {
    emit('request-add-patient', {
        prefillName: String(props.modelValue ?? '').trim(),
    });
};

const requestEditSelectedPatient = () => {
    if (!selectedPatient.value) {
        return;
    }

    emit('request-edit-patient', selectedPatient.value);
};

watch(
    () => props.modelValue,
    (value) => {
        const matchedPatient = findPatientByName(value);

        if (matchedPatient) {
            selectedPatient.value = matchedPatient;
            emit('select-patient', matchedPatient);

            return;
        }

        if (selectedPatient.value) {
            selectedPatient.value = null;
            emit('select-patient', null);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-3">
        <AutoComplete
            :id="inputId"
            :model-value="modelValue"
            :suggestions="suggestions"
            option-label="label"
            dropdown
            complete-on-focus
            class="w-full"
            :placeholder="placeholder"
            :disabled="disabled"
            @update:model-value="emit('update:modelValue', $event)"
            @complete="completePatients"
            @item-select="onPatientSelected"
        >
            <template #option="{ option }">
                <div class="flex flex-col">
                    <span class="font-medium">{{ option.value }}</span>
                    <span class="text-xs text-accent">{{ option.label }}</span>
                </div>
            </template>

            <template #empty>
                <div class="space-y-2 p-2">
                    <p class="text-xs text-accent">
                        {{ emptyMessage }}
                    </p>

                    <Button
                        type="button"
                        size="small"
                        icon="pi pi-user-plus"
                        :label="addButtonLabel"
                        text
                        @click="requestAddPatient"
                    />
                </div>
            </template>

            <template #footer>
                <div class="p-2">
                    <Button
                        type="button"
                        size="small"
                        icon="pi pi-user-plus"
                        :label="footerAddButtonLabel"
                        class="w-full"
                        text
                        @click="requestAddPatient"
                    />
                </div>
            </template>
        </AutoComplete>

        <PatientCard
            v-if="showSelectedCard && selectedPatient"
            :patient-name="selectedPatient.patient_name"
            :patient-phone="selectedPatient.patient_phone"
            :patient-email="selectedPatient.patient_email"
            :patient-birth-number="selectedPatient.patient_birth_number"
        >
            <div class="mt-4">
                <Button
                    type="button"
                    :label="editButtonLabel"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="requestEditSelectedPatient"
                />
            </div>
        </PatientCard>
    </div>
</template>
