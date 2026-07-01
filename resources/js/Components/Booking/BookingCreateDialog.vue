<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
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
    {
        label: 'Rezervácia',
        value: 'booking',
    },
    {
        label: 'Pravidlo online rezervácií',
        value: 'rule',
    },
    {
        label: 'Skupinový termín',
        value: 'group_event',
    },
];

const bookingModeOptions = [
    {
        label: 'Priama rezervácia',
        value: 'immediate_booking',
    },
    {
        label: 'Len cez žiadosť',
        value: 'appointment_request',
    },
];

const recurrencePresetOptions = [
    { label: 'Neopakovať sa', value: 'never' },
    { label: 'Každý týždeň', value: 'weekly' },
    { label: 'Každé 2 týždne', value: 'biweekly' },
    { label: 'Každý mesiac', value: 'monthly' },
    { label: 'Vlastné opakovanie...', value: 'custom' },
];

const recurrenceUnitOptions = [
    {
        label: 'Dni',
        value: 'days',
    },
    {
        label: 'Týždne',
        value: 'weeks',
    },
    {
        label: 'Mesiace',
        value: 'months',
    },
];

const form = reactive({
    create_type: 'booking',
    recurrence: null,
    recurrence_preset: 'never',
    recurrence_frequency: 'weekly',
    recurrence_interval: 1,
    recurrence_weekdays: [],
    recurrence_ends_type: 'never',
    recurrence_ends_count: 1,
    recurrence_ends_until: null,
    is_enabled: true,
    repeats: false,
    repeat_every: 1,
    repeat_unit: 'weeks',
    public_booking_type: 'immediate_booking',
    capacity: 5,
    service_ids: [],
    date: null,
    starts_at: null,
    ends_at: null,
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_phone_country: 'SK',
    patient_phone_full: '',
});

const customRecurrenceVisible = ref(false);

const customRecurrence = reactive({
    frequency: 'weekly',
    interval: 1,
    weekdays: [],
    ends_type: 'never',
    ends_count: 1,
    ends_until: null,
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

const isBookingType = computed(() => form.create_type === 'booking');

const isRuleType = computed(() => form.create_type === 'rule');

const isGroupEventType = computed(() => form.create_type === 'group_event');

const groupServiceModel = computed({
    get: () => form.service_ids[0] ?? null,
    set: (value) => {
        form.service_ids = value ? [value] : [];
    },
});

const isEditMode = computed(() => {
    return Boolean(props.prefill?.edit_mode);
});

const currentEntityLabel = computed(() => {
    if (isRuleType.value) {
        return 'pravidlo';
    }

    if (isGroupEventType.value) {
        return 'skupinový termín';
    }

    return 'rezerváciu';
});

const dialogTitle = computed(() => {
    return isEditMode.value
        ? `Upraviť ${currentEntityLabel.value}`
        : 'Vytvoriť udalosť';
});

const submitLabel = computed(() => {
    return isEditMode.value ? 'Upraviť' : 'Vytvoriť udalosť';
});

const weekdayCodeForDate = (value) => {
    const date = value instanceof Date ? value : new Date(value);

    return ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'][date.getDay()];
};

const presetLabelForMode = (mode) => {
    return recurrencePresetOptions.find((option) => option.value === mode)?.label ?? 'Neopakovať sa';
};

const applyRecurrencePreset = (preset) => {
    const selectedWeekday = form.date ? weekdayCodeForDate(form.date) : 'MO';

    switch (preset) {
        case 'weekly':
            form.recurrence_frequency = 'weekly';
            form.recurrence_interval = 1;
            form.recurrence_weekdays = [selectedWeekday];
            break;
        case 'biweekly':
            form.recurrence_frequency = 'weekly';
            form.recurrence_interval = 2;
            form.recurrence_weekdays = [selectedWeekday];
            break;
        case 'monthly':
            form.recurrence_frequency = 'monthly';
            form.recurrence_interval = 1;
            form.recurrence_weekdays = [];
            break;
        case 'never':
        default:
            form.recurrence_frequency = 'weekly';
            form.recurrence_interval = 1;
            form.recurrence_weekdays = [];
            break;
    }

    form.repeats = preset !== 'never';
    form.repeat_every = form.recurrence_interval;
    form.repeat_unit = form.recurrence_frequency === 'monthly' ? 'months' : 'weeks';
};

const openCustomRecurrenceDialog = () => {
    customRecurrence.frequency = form.recurrence_frequency;
    customRecurrence.interval = form.recurrence_interval;
    customRecurrence.weekdays = [...form.recurrence_weekdays];
    customRecurrence.ends_type = form.recurrence_ends_type;
    customRecurrence.ends_count = form.recurrence_ends_count;
    customRecurrence.ends_until = form.recurrence_ends_until;

    customRecurrenceVisible.value = true;
};

const toggleCustomWeekday = (weekday) => {
    const index = customRecurrence.weekdays.indexOf(weekday);

    if (index === -1) {
        customRecurrence.weekdays.push(weekday);
        return;
    }

    customRecurrence.weekdays.splice(index, 1);
};

const saveCustomRecurrence = () => {
    form.recurrence_preset = 'custom';
    form.recurrence_frequency = customRecurrence.frequency;
    form.recurrence_interval = Math.max(1, Number(customRecurrence.interval || 1));
    form.recurrence_weekdays = [...customRecurrence.weekdays];
    form.recurrence_ends_type = customRecurrence.ends_type;
    form.recurrence_ends_count = Math.max(1, Number(customRecurrence.ends_count || 1));
    form.recurrence_ends_until = customRecurrence.ends_until;
    form.repeats = true;
    form.repeat_every = form.recurrence_interval;
    form.repeat_unit = form.recurrence_frequency === 'monthly' ? 'months' : 'weeks';
    customRecurrenceVisible.value = false;
};

const cancelCustomRecurrence = () => {
    customRecurrenceVisible.value = false;
};

const recurrenceSummary = computed(() => {
    if (!form.repeats) {
        return 'Neopakovať sa';
    }

    if (form.recurrence_preset === 'custom') {
        return 'Vlastné opakovanie';
    }

    return presetLabelForMode(form.recurrence_preset);
});

const recurrencePayload = computed(() => {
    if (!form.recurrence) {
        return null;
    }

    return {
        ...form.recurrence,
    };
});

const getRepeatUnitForRecurrence = (recurrence) => {
    if (!recurrence) {
        return 'weeks';
    }

    if (recurrence.frequency === 'monthly') {
        return 'months';
    }

    return 'weeks';
};

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

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

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
    const hasBaseValues = Boolean(form.create_type)
        && Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(form.ends_at);

    if (!hasBaseValues) {
        return false;
    }

    if (isRuleType.value) {
        return Boolean(form.service_ids.length)
            && Boolean(form.date)
            && Boolean(form.starts_at)
            && Boolean(form.ends_at)
            && Boolean(form.public_booking_type);
    }

    if (isGroupEventType.value) {
        return Boolean(form.service_ids.length)
            && Number(form.capacity ?? 0) > 0
            && Boolean(form.date)
            && Boolean(form.starts_at)
            && Boolean(form.ends_at)
            && Boolean(form.public_booking_type);
    }

    return Boolean(form.service_ids.length)
        && Boolean(form.patient_name.trim())
        && selectedServicesDuration.value > 0;
});

const resetForm = () => {
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
    form.patient_phone_country = 'SK';
    form.patient_phone_full = '';

    if (props.prefill) {
        const prefillStartsAtSource = parseDateValue(props.prefill.starts_at);
        const prefillDate = props.prefill.date
            ? new Date(`${props.prefill.date}T00:00:00`)
            : (prefillStartsAtSource
                ? new Date(prefillStartsAtSource)
                : null);

        form.create_type = props.prefill.create_type ?? form.create_type;
        form.recurrence = props.prefill.recurrence ?? null;
        form.service_ids = [...(props.prefill.service_ids ?? form.service_ids)];
        form.capacity = Number(props.prefill.capacity ?? form.capacity ?? 5);

        if (prefillDate && !Number.isNaN(prefillDate.getTime())) {
            form.date = prefillDate;
            form.date.setHours(0, 0, 0, 0);
        }

        const prefillStartsAt = prefillStartsAtSource;
        const prefillEndsAt = parseDateValue(props.prefill.ends_at);

        if (prefillStartsAt) {
            form.starts_at = prefillStartsAt;
        }

        if (prefillEndsAt) {
            form.ends_at = prefillEndsAt;
        }

        form.patient_name = props.prefill.patient_name ?? '';
        form.patient_email = props.prefill.patient_email ?? '';
        form.patient_phone = props.prefill.patient_phone ?? '';
        form.patient_phone_full = props.prefill.patient_phone ?? '';
        form.public_booking_type = props.prefill.public_booking_type ?? form.public_booking_type;
    }
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

watch(() => form.recurrence_preset, (preset) => {
    if (preset === 'custom') {
        openCustomRecurrenceDialog();
        return;
    }

    applyRecurrencePreset(preset);
});

watch(() => form.date, () => {
    if (form.recurrence_preset === 'weekly' || form.recurrence_preset === 'biweekly') {
        applyRecurrencePreset(form.recurrence_preset);
    }
});

watch(calculatedEndsAtDate, (endsAt) => {
    if (!isBookingType.value || !endsAt) {
        return;
    }

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

    const recurrence = recurrencePayload.value;

    emit('create-booking', {
        create_type: form.create_type,
        edit_mode: Boolean(props.prefill?.edit_mode),
        target_type: props.prefill?.target_type ?? null,
        target_id: props.prefill?.target_id ?? null,
        recurrence,
        is_enabled: recurrence ? true : form.is_enabled,
        repeats: Boolean(recurrence),
        repeat_every: recurrence?.interval ?? 1,
        repeat_unit: getRepeatUnitForRecurrence(recurrence),
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
    });
};
</script>

<template>
    <EventCreateEditDialog
        :visible="visible"
        v-model:date="form.date"
        v-model:starts-at="form.starts_at"
        v-model:ends-at="form.ends_at"
        width="max-w-3xl"
        :save-label="submitLabel"
        :save-disabled="!canSubmit"
        :show-delete="false"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @save="submit"
        :title="dialogTitle"
    >
        <FormPage
            :submit-label="submitLabel"
            :loading="false"
            :show-submit="false"
        >
            <FormSection
                title="Typ a opakovanie"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Typ udalosti"
                    for="create_type"
                    required
                    span="md:col-span-2"
                >
                    <Select
                        id="create_type"
                        v-model="form.create_type"
                        :options="createTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>

                <RecurrencePicker
                    v-model="form.recurrence"
                    :date="form.date"
                />
            </FormSection>

            <div
                v-if="isBookingType"
                class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
            >
                Vybraná je rezervácia. Nižšie vyplňte služby a údaje pacienta.
            </div>

            <div
                v-else-if="isRuleType"
                class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
            >
                Vybrané je pravidlo online rezervácií. Nižšie nastavte služby a opakovanie.
            </div>

            <div
                v-else-if="isGroupEventType"
                class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
            >
                Vybraný je skupinový termín. Nižšie nastavte službu, kapacitu a opakovanie.
            </div>

            <template v-if="isBookingType">
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
            </FormSection>
            </template>

            <template v-else-if="isRuleType">
                <FormSection
                    title="Pravidlo online rezervácií"
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
                            placeholder="Vyberte službu alebo služby"
                            display="chip"
                            class="w-full"
                        />
                    </FormField>

                    <div class="md:col-span-2 rounded-md bg-soft px-4 py-3 text-sm text-accent">
                        Po potvrdení sa otvorí editor pravidla s vyplnenými službami a opakovaním.
                    </div>

                    <FormField
                        label="Spôsob rezervácie"
                        for="rule_public_booking_type"
                        required
                        span="md:col-span-2"
                    >
                        <Select
                            id="rule_public_booking_type"
                            v-model="form.public_booking_type"
                            :options="bookingModeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </FormField>
                </FormSection>
            </template>

            <template v-else-if="isGroupEventType">
                <FormSection
                    title="Skupinový termín"
                    columns="md:grid-cols-2"
                >
                    <FormField
                        label="Služba"
                        for="group_service_id"
                        required
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
                        span="md:col-span-2"
                    >
                        <Select
                            id="group_public_booking_type"
                            v-model="form.public_booking_type"
                            :options="bookingModeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </FormField>

                    <div class="md:col-span-2 rounded-md bg-soft px-4 py-3 text-sm text-accent">
                        Po potvrdení sa otvorí editor skupinového termínu s vyplnenými údajmi.
                    </div>
                </FormSection>
            </template>
        </FormPage>
    </EventCreateEditDialog>
</template>