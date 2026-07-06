<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import FormField from '@/Components/Forms/FormField.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { computed, ref } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const editingPatient = ref(null);

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const createForm = useForm({
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_birth_number: '',
});

const editForm = useForm({
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_birth_number: '',
});

const columns = [
    {
        field: 'patient_name',
        header: 'Meno pacienta',
        sortable: true,
    },
    {
        field: 'patient_email',
        header: 'Email',
        sortable: true,
    },
    {
        field: 'patient_phone',
        header: 'Telefón',
        sortable: true,
    },
    {
        field: 'patient_birth_number',
        header: 'Rodné číslo',
        sortable: true,
    },

];

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const patientRows = computed(() => {
    return [...(props.branch.patients ?? [])].map((patient) => ({
        ...patient,
        patient_email: patient.patient_email || '—',
        patient_phone: patient.patient_phone || '—',
        patient_birth_number: patient.patient_birth_number || '—',
        last_used_at_label: formatDateTime(patient.last_used_at),
    }));
});

const resetCreateForm = () => {
    createForm.reset();
    createForm.clearErrors();
};

const resetEditForm = () => {
    editForm.reset();
    editForm.clearErrors();
};

const openCreateDialog = () => {
    resetCreateForm();
    createDialogVisible.value = true;
};

const closeCreateDialog = () => {
    createDialogVisible.value = false;
    resetCreateForm();
};

const openEditDialog = (patient) => {
    editingPatient.value = patient;
    editForm.patient_name = patient.patient_name ?? '';
    editForm.patient_email = patient.patient_email === '—' ? '' : (patient.patient_email ?? '');
    editForm.patient_phone = patient.patient_phone === '—' ? '' : (patient.patient_phone ?? '');
    editForm.patient_birth_number = patient.patient_birth_number === '—' ? '' : (patient.patient_birth_number ?? '');
    editForm.clearErrors();
    editDialogVisible.value = true;
};

const closeEditDialog = () => {
    editDialogVisible.value = false;
    editingPatient.value = null;
    resetEditForm();
};

const createPatient = () => {
    createForm.post(route('branches.patients.store', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateDialog();
        },
    });
};

const updatePatient = () => {
    if (!editingPatient.value?.id) {
        return;
    }

    editForm.put(route('branches.patients.update', [props.branch.id, editingPatient.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            closeEditDialog();
        },
    });
};

const deletePatient = (patient) => {
    openDialog({
        title: 'Odstrániť pacienta',
        message: `Naozaj chcete odstrániť pacienta ${patient.patient_name}?`,
        confirmLabel: 'Zmazať',
        confirmSeverity: 'danger',
        onConfirm: () => {
            router.delete(route('branches.patients.destroy', [props.branch.id, patient.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    closeDialog();
                },
            });
        },
    });
};
</script>

<template>
    <div class="space-y-6 py-10">
        <TableCard
            title="Pacienti"
            description="Zoznam pacientov priradených k tejto pobočke."
            :rows="patientRows"
            :columns="columns"
            :search-fields="['patient_name', 'patient_email', 'patient_phone', 'patient_birth_number']"
            empty-message="Táto pobočka zatiaľ nemá žiadnych pacientov."
            show-row-actions
        >
            <template #actions>
                <Button
                    label="Pridať pacienta"
                    @click="openCreateDialog"
                />
            </template>

            <template #cell-patient_name="{ row }">
                <span class="text-sm font-medium text-dark">
                    {{ row.patient_name }}
                </span>
            </template>

            <template #row-actions="{ row }">
                <Button
                    v-tooltip.top="'Upraviť pacienta'"
                    type="button"
                    icon="pi pi-pencil"
                    size="small"
                    severity="secondary"
                    text
                    rounded
                    aria-label="Upraviť pacienta"
                    @click="openEditDialog(row)"
                />

                <Button
                    v-tooltip.top="'Odstrániť pacienta'"
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    text
                    rounded
                    aria-label="Odstrániť pacienta"
                    @click="deletePatient(row)"
                />
            </template>
        </TableCard>

        <FormDialog
            v-model:visible="createDialogVisible"
            title="Pridať pacienta"
            width="max-w-2xl"
            :dismissable-mask="!createForm.processing"
            @close="closeCreateDialog"
        >
            <form
                class="space-y-4"
                @submit.prevent="createPatient"
            >
                <FormField
                    label="Meno pacienta"
                    required
                    :error="createForm.errors.patient_name"
                >
                    <InputText
                        v-model="createForm.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                    />
                </FormField>

                <FormField
                    label="Email"
                    :error="createForm.errors.patient_email"
                >
                    <InputText
                        v-model="createForm.patient_email"
                        type="email"
                        class="w-full"
                        placeholder="pacient@email.sk"
                    />
                </FormField>

                <FormField
                    label="Telefón"
                    :error="createForm.errors.patient_phone"
                >
                    <InputText
                        v-model="createForm.patient_phone"
                        class="w-full"
                        placeholder="+421 900 123 456"
                    />
                </FormField>

                <FormField
                    label="Rodné číslo"
                    :error="createForm.errors.patient_birth_number"
                >
                    <InputText
                        v-model="createForm.patient_birth_number"
                        class="w-full"
                        placeholder="999999/9999"
                    />
                </FormField>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        label="Uložiť pacienta"
                        :loading="createForm.processing"
                        :disabled="createForm.processing"
                    />
                </div>
            </form>
        </FormDialog>

        <FormDialog
            v-model:visible="editDialogVisible"
            title="Upraviť pacienta"
            width="max-w-2xl"
            :dismissable-mask="!editForm.processing"
            @close="closeEditDialog"
        >
            <form
                v-if="editingPatient"
                class="space-y-4"
                @submit.prevent="updatePatient"
            >
                <FormField
                    label="Meno pacienta"
                    required
                    :error="editForm.errors.patient_name"
                >
                    <InputText
                        v-model="editForm.patient_name"
                        class="w-full"
                        placeholder="Meno a priezvisko"
                    />
                </FormField>

                <FormField
                    label="Email"
                    :error="editForm.errors.patient_email"
                >
                    <InputText
                        v-model="editForm.patient_email"
                        type="email"
                        class="w-full"
                        placeholder="pacient@email.sk"
                    />
                </FormField>

                <FormField
                    label="Telefón"
                    :error="editForm.errors.patient_phone"
                >
                    <InputText
                        v-model="editForm.patient_phone"
                        class="w-full"
                        placeholder="+421 900 123 456"
                    />
                </FormField>

                <FormField
                    label="Rodné číslo"
                    :error="editForm.errors.patient_birth_number"
                >
                    <InputText
                        v-model="editForm.patient_birth_number"
                        class="w-full"
                        placeholder="999999/9999"
                    />
                </FormField>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        label="Uložiť zmeny"
                        :loading="editForm.processing"
                        :disabled="editForm.processing"
                    />
                </div>
            </form>
        </FormDialog>

        <ConfirmationDialog
            :show="dialog.visible"
            :title="dialog.title"
            :message="dialog.message"
            :confirm-label="dialog.confirmLabel"
            :cancel-label="dialog.cancelLabel"
            :confirm-severity="dialog.confirmSeverity"
            :icon="dialog.icon"
            @cancel="closeDialog"
            @confirm="confirmDialog"
        />
    </div>
</template>
