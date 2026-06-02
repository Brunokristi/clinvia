<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

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

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const form = reactive({
    service_id: null,
    date: '',
    starts_at: '',
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_note: '',
    admin_note: '',
    notify_patient: true,
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable)
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

const calculatedEndsAt = computed(() => {
    if (!form.date || !form.starts_at || !selectedServiceDuration.value) {
        return '';
    }

    const start = new Date(`${form.date}T${form.starts_at}:00`);

    start.setMinutes(start.getMinutes() + selectedServiceDuration.value);

    const hours = String(start.getHours()).padStart(2, '0');
    const minutes = String(start.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
});

const startsAtForBackend = computed(() => {
    if (!form.date || !form.starts_at) {
        return null;
    }

    return `${form.date} ${form.starts_at}:00`;
});

const endsAtForBackend = computed(() => {
    if (!form.date || !calculatedEndsAt.value) {
        return null;
    }

    return `${form.date} ${calculatedEndsAt.value}:00`;
});

const canSubmit = computed(() => {
    return Boolean(form.service_id)
        && Boolean(form.date)
        && Boolean(form.starts_at)
        && Boolean(calculatedEndsAt.value)
        && Boolean(form.patient_name.trim());
});

const getDateFromDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const getTimeFromDate = (date) => {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const resetForm = () => {
    form.service_id = null;

    form.date = props.selection?.date
        ?? (props.selection?.start ? getDateFromDate(props.selection.start) : '');

    form.starts_at = props.selection?.starts_at
        ?? (props.selection?.start ? getTimeFromDate(props.selection.start) : '');

    form.patient_name = '';
    form.patient_email = '';
    form.patient_phone = '';
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

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    emit('create-booking', {
        service_id: form.service_id,
        booking_slot_id: null,
        starts_at: startsAtForBackend.value,
        ends_at: endsAtForBackend.value,
        patient_name: form.patient_name,
        patient_email: form.patient_email,
        patient_phone: form.patient_phone,
        patient_note: form.patient_note,
        admin_note: form.admin_note,
        notify_patient: form.notify_patient,
    });
};
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Vytvoriť rezerváciu"
        :style="{ width: '760px', maxWidth: '95vw' }"
        @hide="closeDialog"
    >
        <div class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Služba
                    </label>

                    <Select
                        v-model="form.service_id"
                        :options="serviceOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Dátum
                    </label>

                    <InputText
                        v-model="form.date"
                        type="date"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Začiatok
                    </label>

                    <InputText
                        v-model="form.starts_at"
                        type="time"
                        class="w-full"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Koniec
                    </label>

                    <InputText
                        :model-value="calculatedEndsAt"
                        type="time"
                        class="w-full"
                        readonly
                    />

                    <p class="mt-2 text-sm text-accent">
                        Koniec sa vypočíta automaticky podľa trvania vybranej služby.
                    </p>

                    <p
                        v-if="selectedService && !selectedServiceDuration"
                        class="mt-2 text-sm text-red-600"
                    >
                        Vybraná služba nemá nastavené trvanie. Skontrolujte pole duration_minutes, duration, length_minutes alebo minutes.
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Meno pacienta
                    </label>

                    <InputText
                        v-model="form.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Email
                    </label>

                    <InputText
                        v-model="form.patient_email"
                        class="w-full"
                        placeholder="email@example.com"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Telefón
                    </label>

                    <InputText
                        v-model="form.patient_phone"
                        class="w-full"
                        placeholder="+421..."
                    />
                </div>

                <div class="flex items-center gap-2 pt-7">
                    <Checkbox
                        v-model="form.notify_patient"
                        binary
                        input-id="notify_patient_create"
                    />

                    <label
                        for="notify_patient_create"
                        class="cursor-pointer text-sm text-accent"
                    >
                        Poslať pacientovi email
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Poznámka pacienta
                    </label>

                    <Textarea
                        v-model="form.patient_note"
                        rows="3"
                        class="w-full"
                        placeholder="Poznámka od pacienta"
                    />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Admin poznámka
                    </label>

                    <Textarea
                        v-model="form.admin_note"
                        rows="3"
                        class="w-full"
                        placeholder="Interná poznámka"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-soft pt-5">
                <Button
                    type="button"
                    label="Zavrieť"
                    severity="secondary"
                    outlined
                    @click="closeDialog"
                />

                <Button
                    type="button"
                    label="Vytvoriť rezerváciu"
                    :disabled="!canSubmit"
                    @click="submit"
                />
            </div>
        </div>
    </Dialog>
</template>