<script setup>
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, reactive, ref, watch } from 'vue';

import EventCreateEditDialog from '@/Components/Booking/Common/EventCreateEditDialog.vue';
import EventDeleteButton from '@/Components/Booking/Common/EventDeleteButton.vue';
import EventDetailDialog from '@/Components/Booking/Common/EventDetailDialog.vue';
import EventOccurrenceActions from '@/Components/Booking/Common/EventOccurrenceActions.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import RecurrencePicker from '@/Components/Calendar/RecurrencePicker.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';

const props = defineProps({
    createVisible: {
        type: Boolean,
        required: true,
    },
    detailVisible: {
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
    booking: {
        type: Object,
        default: null,
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
    availableSlots: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'update:createVisible',
    'update:detailVisible',
    'close-create',
    'create-booking',
    'edit-in-unified-form',
    'cancel-booking',
    'duplicate-booking',
]);

const createTypeOptions = [
    { label: 'Rezervácia', value: 'booking' },
    { label: 'Pravidlo online rezervácií', value: 'rule' },
    { label: 'Skupinový termín', value: 'group_event' },
];

const bookingModeOptions = [
    { label: 'Priama rezervácia', value: 'immediate_booking' },
    { label: 'Len cez žiadosť', value: 'appointment_request' },
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
    patient_phone_country: 'SK',
    patient_phone_full: '',
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable ?? true)
        .map((service) => ({ label: service.name, value: service.id }));
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

const currentEntityLabel = computed(() => {
    if (isRuleType.value) {
        return 'pravidlo';
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
    return isEditMode.value ? 'Upraviť' : 'Vytvoriť udalosť';
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

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
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
    form.patient_phone_country = 'SK';
    form.patient_phone_full = '';

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
        form.patient_phone = props.prefill.patient_phone ?? '';
        form.patient_phone_full = props.prefill.patient_phone ?? '';
        form.public_booking_type = props.prefill.public_booking_type ?? form.public_booking_type;
    }
};

watch(() => props.createVisible, (visible) => {
    if (visible) {
        resetCreateForm();
    }
});

watch(() => props.selection, () => {
    if (props.createVisible) {
        resetCreateForm();
    }
});

watch(calculatedEndsAtDate, (endsAt) => {
    if (!isBookingType.value || !endsAt) {
        return;
    }

    form.ends_at = endsAt;
});

watch(
    () => [form.service_ids, form.date, form.starts_at],
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
    { deep: true },
);

const closeCreateDialog = () => {
    emit('update:createVisible', false);
    emit('close-create');
};

const submitCreate = () => {
    if (!canCreateSubmit.value) {
        return;
    }

    const recurrence = form.recurrence ? { ...form.recurrence } : null;

    emit('create-booking', {
        create_type: form.create_type,
        edit_mode: Boolean(props.prefill?.edit_mode),
        target_type: props.prefill?.target_type ?? null,
        target_id: props.prefill?.target_id ?? null,
        recurrence,
        is_enabled: recurrence ? true : form.is_enabled,
        repeats: Boolean(recurrence),
        repeat_every: recurrence?.interval ?? 1,
        repeat_unit: recurrence?.frequency === 'monthly' ? 'months' : 'weeks',
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

const parseDateTime = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const normalized = String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatSlovakDate = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString('sk-SK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatSlovakTime = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleTimeString('sk-SK', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const bookingDateModel = computed(() => parseDateTime(props.booking?.starts_at));
const bookingStartsAtModel = computed(() => parseDateTime(props.booking?.starts_at));
const bookingEndsAtModel = computed(() => parseDateTime(props.booking?.ends_at));

const bookingInfoItems = computed(() => {
    if (!props.booking) {
        return [];
    }

    const services = props.booking.services?.length
        ? props.booking.services
        : (props.booking.service ? [props.booking.service] : []);

    return [
        {
            key: 'date',
            icon: 'pi pi-calendar',
            value: formatSlovakDate(bookingDateModel.value),
        },
        {
            key: 'duration',
            icon: 'pi pi-clock',
            value: bookingStartsAtModel.value && bookingEndsAtModel.value
                ? `${formatSlovakTime(bookingStartsAtModel.value)} – ${formatSlovakTime(bookingEndsAtModel.value)}`
                : '—',
        },
        {
            key: 'services',
            icon: 'pi pi-briefcase',
            value: services.length ? services : '—',
            type: 'services',
        },
        {
            key: 'patient',
            icon: 'pi pi-user',
            value: props.booking.patient_name || '—',
        },
        {
            key: 'contact',
            icon: 'pi pi-phone',
            value: props.booking.patient_phone || props.booking.patient_email || '—',
        },
        {
            key: 'repetition',
            icon: 'pi pi-refresh',
            value: props.booking.recurrence ? 'Opakuje sa' : 'Neopakuje sa',
        },
    ];
});

const closeDetailDialog = () => {
    emit('update:detailVisible', false);
};

const openUnifiedEditor = () => {
    if (!props.booking) {
        return;
    }

    emit('edit-in-unified-form', props.booking);
};

const cancelBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
    });
};

const duplicateBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('duplicate-booking', props.booking);
};
</script>

<template>
    <EventCreateEditDialog
        :visible="createVisible"
        v-model:date="form.date"
        v-model:starts-at="form.starts_at"
        v-model:ends-at="form.ends_at"
        width="max-w-3xl"
        :save-label="createSubmitLabel"
        :save-disabled="!canCreateSubmit"
        :show-delete="false"
        :title="createDialogTitle"
        @update:visible="emit('update:createVisible', $event)"
        @close="closeCreateDialog"
        @save="submitCreate"
    >
        <FormPage :submit-label="createSubmitLabel" :loading="false" :show-submit="false">
            <FormSection title="Typ a opakovanie" columns="md:grid-cols-2">
                <FormField label="Typ udalosti" for="create_type" required span="md:col-span-2">
                    <Select
                        id="create_type"
                        v-model="form.create_type"
                        :options="createTypeOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </FormField>

                <RecurrencePicker v-model="form.recurrence" :date="form.date" />
            </FormSection>

            <div v-if="isBookingType" class="rounded-md bg-soft px-4 py-3 text-sm text-accent">
                Vybraná je rezervácia. Nižšie vyplňte služby a údaje pacienta.
            </div>

            <div v-else-if="isRuleType" class="rounded-md bg-soft px-4 py-3 text-sm text-accent">
                Vybrané je pravidlo online rezervácií. Nižšie nastavte služby a opakovanie.
            </div>

            <div v-else-if="isGroupEventType" class="rounded-md bg-soft px-4 py-3 text-sm text-accent">
                Vybraný je skupinový termín. Nižšie nastavte službu, kapacitu a opakovanie.
            </div>

            <template v-if="isBookingType">
                <FormSection title="Služby" columns="md:grid-cols-2">
                    <FormField label="Služby" for="service_ids" required span="md:col-span-2">
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

                    <div v-if="selectedServices.length" class="rounded-md bg-soft p-4 text-sm text-accent md:col-span-2">
                        <p>Vybrané služby: {{ selectedServicesLabel }}</p>
                        <p>Celkové trvanie: {{ selectedServicesDuration }} min</p>
                    </div>

                    <div
                        v-if="selectedServices.length && !selectedServicesDuration"
                        class="rounded-md bg-red-50 p-4 text-sm text-red-600 md:col-span-2"
                    >
                        Vybrané služby nemajú nastavené trvanie. Skontrolujte pole duration_minutes.
                    </div>
                </FormSection>

                <FormSection title="Pacient" description="Vyplňte kontaktné údaje pacienta." columns="md:grid-cols-2">
                    <FormField label="Meno pacienta" for="patient_name" required span="md:col-span-2">
                        <InputText id="patient_name" v-model="form.patient_name" class="w-full" placeholder="Meno a priezvisko" />
                    </FormField>

                    <FormField label="Email" for="patient_email">
                        <InputText id="patient_email" v-model="form.patient_email" type="email" class="w-full" placeholder="email@example.com" />
                    </FormField>

                    <FormField label="Telefón" for="patient_phone">
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
                <FormSection title="Pravidlo online rezervácií" columns="md:grid-cols-2">
                    <FormField label="Služby" for="rule_service_ids" required span="md:col-span-2">
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

                    <FormField label="Spôsob rezervácie" for="rule_public_booking_type" required span="md:col-span-2">
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
                <FormSection title="Skupinový termín" columns="md:grid-cols-2">
                    <FormField label="Služba" for="group_service_id" required>
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

                    <FormField label="Kapacita" for="group_capacity" required>
                        <InputNumber id="group_capacity" v-model="form.capacity" :min="1" class="w-full" input-class="w-full" placeholder="Napr. 5" />
                    </FormField>

                    <FormField label="Spôsob rezervácie" for="group_public_booking_type" required span="md:col-span-2">
                        <Select
                            id="group_public_booking_type"
                            v-model="form.public_booking_type"
                            :options="bookingModeOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </FormField>
                </FormSection>
            </template>
        </FormPage>
    </EventCreateEditDialog>

    <EventDetailDialog
        :visible="detailVisible"
        title="Rezervácia"
        width="max-w-3xl"
        :date="bookingDateModel"
        :starts-at="bookingStartsAtModel"
        :ends-at="bookingEndsAtModel"
        :is-repeatable="Boolean(booking?.series_uuid || booking?.recurrence)"
        :show-duplicate="false"
        :show-date-time-fields="false"
        @update:visible="emit('update:detailVisible', $event)"
        @close="closeDetailDialog"
        @duplicate="duplicateBooking"
    >
        <template #footer-start>
            <EventDeleteButton v-if="booking" label="Odstrániť" @delete="cancelBooking" />
            <EventOccurrenceActions v-if="booking" @duplicate="duplicateBooking" @edit="openUnifiedEditor" />
        </template>

        <div v-if="booking" class="space-y-4">
            <div class="space-y-4">
                <div
                    v-for="item in bookingInfoItems"
                    :key="item.key"
                    class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3"
                >
                    <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-soft text-accent">
                        <i :class="item.icon" class="text-base" />
                    </div>

                    <div class="flex min-w-0 items-center">
                        <div
                            v-if="item.type === 'services' && Array.isArray(item.value)"
                            class="w-full space-y-1"
                        >
                            <div
                                v-for="service in item.value"
                                :key="service.id"
                                class="flex items-center rounded-md bg-white text-sm font-medium text-dark"
                            >
                                <span class="min-w-0 flex-1 truncate">
                                    {{ service.name }}
                                </span>
                            </div>
                        </div>

                        <p v-else class="break-words text-sm font-medium text-dark">
                            {{ item.value }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-600">
            Rezerváciu sa nepodarilo načítať.
        </div>
    </EventDetailDialog>
</template>
