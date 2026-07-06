<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    branchId: {
        type: [Number, String],
        default: null,
    },
    patient: {
        type: Object,
        default: null,
    },
    prefillName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits([
    'update:visible',
    'saved',
    'close',
]);

const form = useForm({
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_birth_number: '',
});

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const isEditMode = computed(() => Boolean(props.patient?.id));

const dialogTitle = computed(() => {
    return isEditMode.value ? 'Upravit pacienta' : 'Pridat pacienta';
});

const submitLabel = computed(() => {
    return isEditMode.value ? 'Ulozit zmeny' : 'Ulozit pacienta';
});

const hydrateForm = () => {
    form.reset();
    form.clearErrors();

    if (props.patient) {
        form.patient_name = String(props.patient.patient_name ?? '').trim();
        form.patient_email = String(props.patient.patient_email ?? '').trim();
        form.patient_phone = String(props.patient.patient_phone ?? '').trim();
        form.patient_birth_number = String(props.patient.patient_birth_number ?? '').trim();

        return;
    }

    form.patient_name = String(props.prefillName ?? '').trim();
};

const closeDialog = () => {
    dialogVisible.value = false;
    emit('close');
};

const submitForm = () => {
    if (!props.branchId) {
        return;
    }

    const patientPayload = {
        id: props.patient?.id ?? null,
        patient_name: String(form.patient_name ?? '').trim(),
        patient_email: String(form.patient_email ?? '').trim(),
        patient_phone: String(form.patient_phone ?? '').trim(),
        patient_birth_number: String(form.patient_birth_number ?? '').trim(),
    };

    const requestOptions = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved', patientPayload);
            closeDialog();
            router.reload({
                only: ['patients'],
                preserveState: true,
                preserveScroll: true,
            });
        },
    };

    if (isEditMode.value) {
        form.put(route('branches.patients.update', [props.branchId, props.patient.id]), requestOptions);

        return;
    }

    form.post(route('branches.patients.store', props.branchId), requestOptions);
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            hydrateForm();
        }
    },
);

watch(
    () => props.patient,
    () => {
        if (props.visible) {
            hydrateForm();
        }
    },
    { deep: true },
);
</script>

<template>
    <FormDialog
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        width="max-w-2xl"
        :dismissable-mask="!form.processing"
        @close="closeDialog"
    >
        <form
            class="space-y-4"
            @submit.prevent="submitForm"
        >
            <FormField
                label="Meno pacienta"
                required
                :error="form.errors.patient_name"
            >
                <InputText
                    v-model="form.patient_name"
                    class="w-full"
                    placeholder="Meno a priezvisko"
                />
            </FormField>

            <FormField
                label="Email"
                :error="form.errors.patient_email"
            >
                <InputText
                    v-model="form.patient_email"
                    type="email"
                    class="w-full"
                    placeholder="pacient@email.sk"
                />
            </FormField>

            <FormField
                label="Telefon"
                :error="form.errors.patient_phone"
            >
                <InputText
                    v-model="form.patient_phone"
                    class="w-full"
                    placeholder="+421 900 123 456"
                />
            </FormField>

            <FormField
                label="Rodne cislo"
                :error="form.errors.patient_birth_number"
            >
                <InputText
                    v-model="form.patient_birth_number"
                    class="w-full"
                    placeholder="999999/9999"
                />
            </FormField>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :label="submitLabel"
                    :loading="form.processing"
                    :disabled="form.processing"
                />
            </div>
        </form>
    </FormDialog>
</template>
