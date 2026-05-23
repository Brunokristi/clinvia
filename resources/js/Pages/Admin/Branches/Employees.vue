<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import EmployeeForm from '@/Components/Branches/EmployeeForm.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const props = defineProps({
    branch: Object,
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

const employeeInitials = (employee) => {
    return [
        employee.first_name?.charAt(0),
        employee.last_name?.charAt(0),
    ].filter(Boolean).join('').toUpperCase();
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
            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: 'Zamestnanec bol pridaný k pobočke.',
                life: 3000,
            });

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

    editEmployeeForm.post(route('branches.employees.update', [props.branch.id, editingEmployee.value.id]), {
        preserveScroll: true,
        forceFormData: true,
        data: {
            _method: 'put',
        },
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: 'Zamestnanec bol upravený.',
                life: 3000,
            });

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
        onConfirm: () => {
            router.delete(route('branches.employees.destroy', [props.branch.id, employee.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Úspech',
                        detail: 'Zamestnanec bol odstránený z pobočky.',
                        life: 3000,
                    });
                },
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
    <AdminLayout>
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Pobočka
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    Zamestnanci pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Spravujte zamestnancov tejto pobočky. Nového zamestnanca môžete pridať cez jednoduché okno.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                        Aktívna pobočka
                    </p>

                    <p class="mt-1 text-sm font-semibold text-slate-900">
                        {{ branch.name }}
                    </p>
                </div>

                <Button
                    label="Pridať zamestnanca"
                    icon="pi pi-plus"
                    @click="openCreateEmployeeDialog"
                />
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Existujúci zamestnanci
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Zamestnanci aktuálne priradení k tejto pobočke.
                        </p>
                    </div>

                    <Tag
                        :value="`${branch.employees?.length ?? 0} zamestnancov`"
                        severity="secondary"
                    />
                </div>
            </div>

            <div class="p-6">
                <div
                    v-if="branch.employees?.length"
                    class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="employee in branch.employees"
                        :key="employee.id"
                        class="group relative min-h-[22rem] overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div
                            v-if="employeePhotoUrl(employee)"
                            class="absolute inset-0 bg-cover bg-center transition duration-500 group-hover:scale-105"
                            :style="employeeCardBackground(employee)"
                        />

                        <div
                            v-else
                            class="absolute inset-0 bg-gradient-to-br from-slate-700 via-slate-900 to-slate-950"
                        />

                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/45 to-transparent" />

                        <div class="relative flex h-full min-h-[22rem] flex-col justify-between p-5">
                            <div class="flex justify-end">
                                <Tag
                                    :value="employee.is_active ? 'Aktívny' : 'Neaktívny'"
                                    :severity="employee.is_active ? 'success' : 'secondary'"
                                />
                            </div>

                            <div>
                                <div
                                    v-if="!employeePhotoUrl(employee)"
                                    class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-xl font-semibold text-white ring-1 ring-white/20"
                                >
                                    {{ employeeInitials(employee) }}
                                </div>

                                <p class="text-xl font-semibold text-white">
                                    {{ employeeDisplayName(employee) }}
                                </p>

                                <p class="mt-1 text-sm font-medium text-white/80">
                                    {{ employee.position || 'Pozícia nie je zadaná' }}
                                </p>

                                <p
                                    v-if="employee.bio"
                                    class="mt-4 line-clamp-3 text-sm leading-6 text-white/75"
                                >
                                    {{ employee.bio }}
                                </p>

                                <div class="mt-5 space-y-2">
                                    <p
                                        v-if="employee.email"
                                        class="flex items-center gap-2 text-sm text-white/80"
                                    >
                                        <i class="pi pi-envelope text-xs" />
                                        <span class="truncate">{{ employee.email }}</span>
                                    </p>

                                    <p
                                        v-if="employee.phone"
                                        class="flex items-center gap-2 text-sm text-white/80"
                                    >
                                        <i class="pi pi-phone text-xs" />
                                        <span class="truncate">{{ employee.phone }}</span>
                                    </p>
                                </div>

                                <div class="mt-5 flex justify-end gap-2">
                                    <Button
                                        label="Upraviť"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        icon="pi pi-pencil"
                                        class="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                        @click="openEditEmployeeDialog(employee)"
                                    />

                                    <Button
                                        label="Odobrať"
                                        size="small"
                                        severity="danger"
                                        outlined
                                        icon="pi pi-trash"
                                        class="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                        @click="removeEmployee(employee)"
                                    />
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center"
                >
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                        <i class="pi pi-users text-xl" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-slate-900">
                        Žiadni zamestnanci
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Táto pobočka zatiaľ nemá priradených zamestnancov.
                    </p>

                    <Button
                        label="Pridať prvého zamestnanca"
                        icon="pi pi-plus"
                        class="mt-5"
                        @click="openCreateEmployeeDialog"
                    />
                </div>
            </div>
        </section>

        <Dialog
            v-model:visible="createEmployeeDialogVisible"
            modal
            header="Pridať zamestnanca"
            class="w-[95vw] max-w-5xl"
            :draggable="false"
            :dismissable-mask="!createEmployeeForm.processing"
            @hide="resetCreateEmployeeForm"
        >
            <form @submit.prevent="addEmployee">
                <EmployeeForm
                    :form="createEmployeeForm"
                    heading="Nový zamestnanec"
                    description="Vyplňte základné údaje zamestnanca. Meno, priezvisko a pozícia sú povinné."
                    submit-label="Pridať zamestnanca"
                    :loading="createEmployeeForm.processing"
                />
            </form>
        </Dialog>

        <Dialog
            v-model:visible="editEmployeeDialogVisible"
            modal
            header="Upraviť zamestnanca"
            class="w-[95vw] max-w-5xl"
            :draggable="false"
            :dismissable-mask="!editEmployeeForm.processing"
            @hide="closeEditEmployeeDialog"
        >
            <form
                v-if="editingEmployee"
                @submit.prevent="saveEmployee"
            >
                <EmployeeForm
                    :form="editEmployeeForm"
                    heading="Upraviť profil zamestnanca"
                    :description="employeeDisplayName(editingEmployee)"
                    submit-label="Uložiť zmeny"
                    :loading="editEmployeeForm.processing"
                    :photo-preview-url="employeePhotoUrl(editingEmployee)"
                />
            </form>
        </Dialog>

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
    </AdminLayout>
</template>