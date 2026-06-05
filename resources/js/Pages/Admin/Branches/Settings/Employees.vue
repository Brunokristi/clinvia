<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import EmployeeForm from '@/Components/Branches/EmployeeForm.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const createEmployeeDialogVisible = ref(false);
const editEmployeeDialogVisible = ref(false);
const editingEmployee = ref(null);

const toast = useToast();

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const createEmployeeForm = useForm({
    create_new: true,
    first_name: '',
    last_name: '',
    title_before: '',
    title_after: '',
    position: '',
    bio: '',
    email: '',
    phone: '',
    photo: null,
    sort_order: 0,
});

const editEmployeeForm = useForm({
    first_name: '',
    last_name: '',
    title_before: '',
    title_after: '',
    position: '',
    bio: '',
    email: '',
    phone: '',
    photo: null,
    sort_order: 0,
});

const employeeDisplayName = (employee) => {
    return [
        employee.title_before,
        employee.first_name,
        employee.last_name,
        employee.title_after,
    ].filter(Boolean).join(' ');
};

const employeePhotoUrl = (employee) => {
    return employee.photo_url
        || (employee.photo_path ? `/storage/${employee.photo_path}` : null)
        || employee.photo
        || employee.image_url
        || employee.avatar_url
        || null;
};

const employeeCardBackground = (employee) => {
    const photoUrl = employeePhotoUrl(employee);

    if (!photoUrl) {
        return {};
    }

    return {
        backgroundImage: `linear-gradient(180deg, rgba(15, 23, 42, 0.1) 0%, rgba(15, 23, 42, 0.85) 100%), url('${photoUrl}')`,
    };
};

const resetCreateEmployeeForm = () => {
    createEmployeeForm.reset();
    createEmployeeForm.clearErrors();

    createEmployeeForm.create_new = true;
    createEmployeeForm.sort_order = 0;
};

const resetEditEmployeeForm = () => {
    editEmployeeForm.reset();
    editEmployeeForm.clearErrors();
};

const openCreateEmployeeDialog = () => {
    resetCreateEmployeeForm();
    createEmployeeDialogVisible.value = true;
};

const closeCreateEmployeeDialog = () => {
    createEmployeeDialogVisible.value = false;
    resetCreateEmployeeForm();
};

const fillEditEmployeeForm = (employee) => {
    editEmployeeForm.first_name = employee.first_name ?? '';
    editEmployeeForm.last_name = employee.last_name ?? '';
    editEmployeeForm.title_before = employee.title_before ?? '';
    editEmployeeForm.title_after = employee.title_after ?? '';
    editEmployeeForm.position = employee.position ?? '';
    editEmployeeForm.bio = employee.bio ?? '';
    editEmployeeForm.email = employee.email ?? '';
    editEmployeeForm.phone = employee.phone ?? '';
    editEmployeeForm.photo = null;
    editEmployeeForm.sort_order = employee.sort_order ?? 0;

    editEmployeeForm.clearErrors();
};

const openEditEmployeeDialog = (employee) => {
    editingEmployee.value = employee;
    fillEditEmployeeForm(employee);
    editEmployeeDialogVisible.value = true;
};

const closeEditEmployeeDialog = () => {
    editEmployeeDialogVisible.value = false;
    editingEmployee.value = null;
    resetEditEmployeeForm();
};

const addEmployee = () => {
    createEmployeeForm.sort_order = 0;

    createEmployeeForm.post(route('branches.employees.store', props.branch.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            closeCreateEmployeeDialog();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: 'Nepodarilo sa pridať zamestnanca.',
                life: 3000,
            });
        },
    });
};

const saveEmployee = () => {
    if (!editingEmployee.value) {
        return;
    }

    editEmployeeForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('branches.employees.update', [props.branch.id, editingEmployee.value.id]), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                closeEditEmployeeDialog();
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Chyba',
                    detail: 'Nepodarilo sa upraviť zamestnanca.',
                    life: 3000,
                });
            },
        });
};

const removeEmployee = (employee) => {
    openDialog({
        title: 'Odstrániť zamestnanca',
        message: `Odstrániť zamestnanca ${employeeDisplayName(employee)} z tejto pobočky?`,
        confirmLabel: 'Zmazať',
        confirmSeverity: 'danger',
        icon: 'pi pi-trash',
        onConfirm: () => {
            router.delete(route('branches.employees.destroy', [props.branch.id, employee.id]), {
                preserveScroll: true,
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Chyba',
                        detail: 'Nepodarilo sa odstrániť zamestnanca.',
                        life: 3000,
                    });
                },
            });
        },
    });
};
</script>

<template>
    <div class="space-y-6 py-10">
        <div class="flex items-center justify-end">
            <Button
                label="Pridať zamestnanca"
                @click="openCreateEmployeeDialog"
            />
        </div>

        <section>
            <div
                v-if="branch.employees?.length"
                class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="employee in branch.employees"
                    :key="employee.id"
                    class="group relative min-h-[22rem] overflow-hidden rounded-md bg-dark transition"
                >
                    <div
                        v-if="employeePhotoUrl(employee)"
                        class="absolute inset-0 bg-cover bg-center transition duration-500"
                        :style="employeeCardBackground(employee)"
                    />

                    <div
                        v-else
                        class="absolute inset-0 bg-gradient-to-br from-dark to-accent"
                    />

                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-dark/20 to-transparent" />

                    <div class="relative flex h-full min-h-[22rem] flex-col justify-between p-5">
                        <div />

                        <div>
                            <p class="text-heading text-white">
                                {{ employeeDisplayName(employee) }}
                            </p>

                            <p
                                v-if="employee.position"
                                class="mt-2 text-sm text-white/80"
                            >
                                {{ employee.position }}
                            </p>

                            <div class="mt-5 flex justify-start gap-2">
                                <Button
                                    label="Upraviť"
                                    size="small"
                                    severity="secondary"
                                    @click="openEditEmployeeDialog(employee)"
                                />

                                <Button
                                    label="Odobrať"
                                    size="small"
                                    severity="danger"
                                    outlined
                                    @click="removeEmployee(employee)"
                                />
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class=" p-6 text-center"
            >
                <p class="text-sm text-accent">
                    Táto pobočka zatiaľ nemá priradených zamestnancov.
                </p>
            </div>
        </section>

        <FormDialog
            v-model:visible="createEmployeeDialogVisible"
            title="Pridať zamestnanca"
            description="Vyplňte základné údaje zamestnanca. Meno, priezvisko a pozícia sú povinné."
            width="max-w-5xl"
            :dismissable-mask="!createEmployeeForm.processing"
            @close="closeCreateEmployeeDialog"
        >
            <form @submit.prevent="addEmployee">
                <EmployeeForm
                    :form="createEmployeeForm"
                    submit-label="Pridať zamestnanca"
                    :loading="createEmployeeForm.processing"
                />
            </form>
        </FormDialog>

        <FormDialog
            v-model:visible="editEmployeeDialogVisible"
            title="Upraviť zamestnanca"
            :description="editingEmployee ? employeeDisplayName(editingEmployee) : ''"
            width="max-w-5xl"
            :dismissable-mask="!editEmployeeForm.processing"
            @close="closeEditEmployeeDialog"
        >
            <form
                v-if="editingEmployee"
                @submit.prevent="saveEmployee"
            >
                <EmployeeForm
                    :form="editEmployeeForm"
                    submit-label="Uložiť zmeny"
                    :loading="editEmployeeForm.processing"
                    :photo-preview-url="employeePhotoUrl(editingEmployee)"
                />
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