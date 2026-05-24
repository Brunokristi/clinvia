<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import BranchServiceForm from '@/Components/Branches/BranchServiceForm.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const props = defineProps({
    branch: Object,
    categories: {
        type: Array,
        default: () => [],
    },
});

const toast = useToast();

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const editingService = ref(null);

const newCategoryValue = '__new_category__';

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const makeEmptyServiceData = () => ({
    category_id: null,
    new_category_name: '',
    name: '',
    slug: '',
    short_description: '',
    description: '',
    icon: '',
    duration_sessions: 1,
    duration_minutes: null,
    is_available: true,
    sort_order: 0,
    insurance_amount: null,
    insurance_note: '',
    self_pay_amount: null,
    self_pay_note: '',
});

const createForm = useForm(makeEmptyServiceData());
const editForm = useForm(makeEmptyServiceData());

const categoryOptions = computed(() => {
    return [
        ...props.categories,
        {
            id: newCategoryValue,
            name: 'Pridať novú kategóriu',
        },
    ];
});

const slugify = (value) => {
    return (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const generatedCreateSlug = computed(() => slugify(createForm.name));

const serviceTitle = (service) => service.name || '—';
const serviceCategoryName = (service) => service.category?.name || 'Bez kategórie';
const serviceIcon = (service) => service.icon || 'pi pi-briefcase';

const formatPrice = (amount) => {
    if (amount === null || amount === undefined) return '—';
    return `${amount} EUR`;
};

const serviceDuration = (service) => {
    const sessions = service.duration_sessions;
    const minutes = service.duration_minutes;

    if (!minutes) return '—';
    if (!sessions || sessions === 1) return `${minutes} min`;
    return `${sessions} × ${minutes} min`;
};

const resetForm = (form) => {
    const empty = makeEmptyServiceData();
    form.clearErrors();
    Object.assign(form, empty);
};

const openCreateDialog = () => {
    resetForm(createForm);
    createDialogVisible.value = true;
};

const closeCreateDialog = () => {
    createDialogVisible.value = false;
    resetForm(createForm);
};

const fillEditForm = (service) => {
    editForm.clearErrors();
    editForm.category_id = service.category_id ?? service.category?.id ?? null;
    editForm.new_category_name = '';
    editForm.name = service.name ?? '';
    editForm.slug = service.slug ?? '';
    editForm.short_description = service.short_description ?? '';
    editForm.description = service.description ?? '';
    editForm.icon = service.icon ?? '';
    editForm.duration_sessions = service.duration_sessions ?? 1;
    editForm.duration_minutes = service.duration_minutes ?? null;
    editForm.is_available = Boolean(service.is_active ?? true);
    editForm.sort_order = service.sort_order ?? 0;
    editForm.insurance_amount = service.insurance_amount ?? null;
    editForm.insurance_note = service.insurance_note ?? '';
    editForm.self_pay_amount = service.self_pay_amount ?? null;
    editForm.self_pay_note = service.self_pay_note ?? '';
};

const openEditDialog = (service) => {
    editingService.value = service;
    fillEditForm(service);
    editDialogVisible.value = true;
};

const closeEditDialog = () => {
    editDialogVisible.value = false;
    editingService.value = null;
    resetForm(editForm);
};

const createService = () => {
    const payload = {
        category_id: createForm.category_id === newCategoryValue ? null : createForm.category_id,
        new_category_name: createForm.new_category_name,
        name: createForm.name,
        slug: generatedCreateSlug.value,
        short_description: createForm.short_description,
        description: createForm.description,
        icon: createForm.icon,
        duration_sessions: createForm.duration_sessions,
        duration_minutes: createForm.duration_minutes,
        is_available: createForm.is_available,
        sort_order: createForm.sort_order,
        insurance_amount: createForm.insurance_amount,
        insurance_note: createForm.insurance_note,
        self_pay_amount: createForm.self_pay_amount,
        self_pay_note: createForm.self_pay_note,
    };

    createForm
        .transform(() => payload)
        .post(route('branches.services.store', props.branch.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Úspech', detail: 'Služba bola vytvorená.', life: 3000 });
                closeCreateDialog();
            },
            onError: () => {
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa vytvoriť službu.', life: 3000 });
            },
        });
};

const updateService = () => {
    if (!editingService.value) return;

    const payload = {
        name: editForm.name,
        short_description: editForm.short_description,
        description: editForm.description,
        icon: editForm.icon,
        duration_sessions: editForm.duration_sessions,
        duration_minutes: editForm.duration_minutes,
        is_available: editForm.is_available,
        sort_order: editForm.sort_order,
        insurance_amount: editForm.insurance_amount,
        insurance_note: editForm.insurance_note,
        self_pay_amount: editForm.self_pay_amount,
        self_pay_note: editForm.self_pay_note,
    };

    editForm
        .transform(() => payload)
        .put(route('branches.services.update', [props.branch.id, editingService.value.id]), {
            preserveScroll: true,
            onSuccess: () => {
                toast.add({ severity: 'success', summary: 'Úspech', detail: 'Služba bola upravená.', life: 3000 });
                closeEditDialog();
            },
            onError: () => {
                toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa upraviť službu.', life: 3000 });
            },
        });
};

const removeService = (service) => {
    openDialog({
        title: 'Odstrániť službu',
        message: `Odstrániť službu „${serviceTitle(service)}" z tejto pobočky?`,
        confirmLabel: 'Zmazať',
        onConfirm: () => {
            router.delete(route('branches.services.destroy', [props.branch.id, service.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Úspech', detail: 'Služba bola odstránená.', life: 3000 });
                },
                onError: () => {
                    toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa odstrániť službu.', life: 3000 });
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
                    Služby pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Spravujte služby tejto pobočky.
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
                    label="Pridať službu"
                    icon="pi pi-plus"
                    @click="openCreateDialog"
                />
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Služby pobočky
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Všetky služby priradené k tejto pobočke.
                        </p>
                    </div>

                    <Tag
                        :value="`${branch.services?.length ?? 0} služieb`"
                        severity="secondary"
                    />
                </div>
            </div>

            <DataTable
                :value="branch.services ?? []"
                tableStyle="min-width: 64rem"
                emptyMessage="Táto pobočka zatiaľ nemá žiadne služby."
            >
                <Column header="Služba">
                    <template #body="{ data }">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <i :class="serviceIcon(data)" />
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ serviceTitle(data) }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    Kategória: {{ serviceCategoryName(data) }}
                                </p>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column header="Trvanie">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-700">
                            {{ serviceDuration(data) }}
                        </span>
                    </template>
                </Column>

                <Column header="Ceny">
                    <template #body="{ data }">
                        <div class="space-y-1 text-sm text-slate-700">
                            <p>Poisťovňa: {{ formatPrice(data.insurance_amount) }}</p>
                            <p>Samoplatca: {{ formatPrice(data.self_pay_amount) }}</p>
                        </div>
                    </template>
                </Column>

                <Column header="Dostupnosť">
                    <template #body="{ data }">
                        <Tag
                            :value="data.is_active ? 'Aktívna' : 'Neaktívna'"
                            :severity="data.is_active ? 'success' : 'secondary'"
                        />
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                label="Upraviť"
                                size="small"
                                severity="secondary"
                                outlined
                                icon="pi pi-pencil"
                                @click="openEditDialog(data)"
                            />

                            <Button
                                label="Odstrániť"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="removeService(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </section>

        <Dialog
            v-model:visible="createDialogVisible"
            modal
            header="Pridať službu"
            class="w-[95vw] max-w-6xl"
            :draggable="false"
            :dismissable-mask="!createForm.processing"
            @hide="resetForm(createForm)"
        >
            <BranchServiceForm
                :form="createForm"
                mode="create"
                :categories="categoryOptions"
                :new-category-value="newCategoryValue"
                title="Vytvoriť novú službu"
                description="Kategória a názov služby sú povinné. Ostatné údaje môžete doplniť podľa potreby."
                submit-label="Vytvoriť službu"
                :loading="createForm.processing"
                @submit="createService"
            />
        </Dialog>

        <Dialog
            v-model:visible="editDialogVisible"
            modal
            header="Upraviť službu"
            class="w-[95vw] max-w-6xl"
            :draggable="false"
            :dismissable-mask="!editForm.processing"
            @hide="closeEditDialog"
        >
            <BranchServiceForm
                v-if="editingService"
                :form="editForm"
                mode="edit"
                :categories="categoryOptions"
                :new-category-value="newCategoryValue"
                title="Upraviť službu"
                :description="serviceTitle(editingService)"
                submit-label="Uložiť zmeny"
                :loading="editForm.processing"
                @submit="updateService"
            />
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