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
    availableSlots: {
        type: Array,
        default: () => [],
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
    booking_slot_id: null,
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

const slotOptions = computed(() => {
    return props.availableSlots
        .filter((slot) => Number(slot.service_id) === Number(form.service_id))
        .map((slot) => ({
            label: slot.label ?? formatSlotLabel(slot),
            value: slot.id,
        }));
});

const canSubmit = computed(() => {
    return Boolean(form.service_id)
        && Boolean(form.booking_slot_id)
        && Boolean(form.patient_name.trim());
});

watch(() => form.service_id, () => {
    form.booking_slot_id = null;
});

watch(() => props.visible, (visible) => {
    if (visible) {
        resetForm();
    }
});

const resetForm = () => {
    form.service_id = null;
    form.booking_slot_id = null;
    form.patient_name = '';
    form.patient_email = '';
    form.patient_phone = '';
    form.patient_note = '';
    form.admin_note = '';
    form.notify_patient = true;
};

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    emit('create-booking', {
        booking_slot_id: form.booking_slot_id,
        patient_name: form.patient_name,
        patient_email: form.patient_email,
        patient_phone: form.patient_phone,
        patient_note: form.patient_note,
        admin_note: form.admin_note,
        notify_patient: form.notify_patient,
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatSlotLabel = (slot) => {
    const start = formatDateTime(slot.starts_at);
    const end = new Date(slot.ends_at).toLocaleTimeString('sk-SK', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return `${start} - ${end}`;
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

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Termín
                    </label>

                    <Select
                        v-model="form.booking_slot_id"
                        :options="slotOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte dostupný termín"
                        class="w-full"
                        :disabled="!form.service_id"
                    />
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