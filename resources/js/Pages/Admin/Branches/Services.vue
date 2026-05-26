<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import BranchServiceForm from '@/Components/Branches/BranchServiceForm.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';

import Button from 'primevue/button';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
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
    information: [],
    steps: [],
    files: [],
});

const makeEmptyInformationItem = () => ({
    existing_id: null,
    text: '',
});

const makeEmptyStepItem = () => ({
    existing_id: null,
    number: null,
    title: '',
    text: '',
});

const makeEmptyFileItem = () => ({
    existing_id: null,
    label: '',
    file: null,
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

const generatedCreateSlug = computed(() => {
    return slugify(createForm.name);
});

const serviceTitle = (service) => {
    return service.name || '—';
};

const serviceCategoryName = (service) => {
    return service.category?.name || 'Bez kategórie';
};

const serviceIcon = (service) => {
    return service.icon || 'pi pi-briefcase';
};

const formatPrice = (amount) => {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return `${amount} EUR`;
};

const serviceDuration = (service) => {
    const sessions = service.duration_sessions;
    const minutes = service.duration_minutes;

    if (!minutes) {
        return '—';
    }

    return `${sessions || 1} × ${minutes} min`;
};

const fillInformationItems = (items = []) => {
    return items.map((item, index) => ({
        existing_id: item.id ?? null,
        text: item.text ?? '',
    }));
};

const fillStepItems = (items = []) => {
    return items.map((item, index) => ({
        existing_id: item.id ?? null,
        number: item.number ?? null,
        title: item.title ?? '',
        text: item.text ?? '',
    }));
};

const fillFileItems = (items = []) => {
    return items.map((item, index) => ({
        existing_id: item.id ?? null,
        label: item.label ?? '',
        file: null,
        existing_name: item.original_name ?? item.label ?? 'Súbor',
    }));
};

const services = computed(() => {
    return (props.branch.services ?? []).map((service) => ({
        ...service,
        title_label: serviceTitle(service),
        category_label: serviceCategoryName(service),
        duration_label: serviceDuration(service),
        insurance_price_label: formatPrice(service.insurance_amount),
        self_pay_price_label: formatPrice(service.self_pay_amount),
        availability_label: service.is_active ? 'Aktívna' : 'Neaktívna',
    }));
});

const columns = [
    {
        field: 'title_label',
        header: 'Služba',
        sortable: true,
    },
    {
        field: 'duration_label',
        header: 'Trvanie',
        sortable: true,
    },
    {
        field: 'insurance_price_label',
        header: 'Poisťovňa',
        sortable: true,
    },
    {
        field: 'self_pay_price_label',
        header: 'Samoplatca',
        sortable: true,
    },
];

const resetForm = (form) => {
    const empty = makeEmptyServiceData();

    form.clearErrors();

    Object.assign(form, empty);
};

const addInformation = (form) => {
    form.information.push(makeEmptyInformationItem());
};

const removeInformation = (form, index) => {
    form.information.splice(index, 1);
};

const addStep = (form) => {
    form.steps.push(makeEmptyStepItem());
};

const removeStep = (form, index) => {
    form.steps.splice(index, 1);
};

const addFile = (form) => {
    form.files.push(makeEmptyFileItem());
};

const removeFile = (form, index) => {
    form.files.splice(index, 1);
};

const onFileChange = (form, index, event) => {
    form.files[index].file = event.target.files?.[0] ?? null;
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
    editForm.information = fillInformationItems(service.information ?? []);
    editForm.steps = fillStepItems(service.steps ?? []);
    editForm.files = fillFileItems(service.files ?? []);
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
        information: createForm.information,
        steps: createForm.steps,
        files: createForm.files,
    };

    createForm
        .transform(() => payload)
        .post(route('branches.services.store', props.branch.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Úspech',
                    detail: 'Služba bola vytvorená.',
                    life: 3000,
                });

                closeCreateDialog();
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Chyba',
                    detail: 'Nepodarilo sa vytvoriť službu.',
                    life: 3000,
                });
            },
        });
};

const updateService = () => {
    if (!editingService.value) {
        return;
    }

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
        information: editForm.information,
        steps: editForm.steps,
        files: editForm.files,
    };

    editForm
        .transform(() => payload)
        .put(route('branches.services.update', [props.branch.id, editingService.value.id]), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Úspech',
                    detail: 'Služba bola upravená.',
                    life: 3000,
                });

                closeEditDialog();
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Chyba',
                    detail: 'Nepodarilo sa upraviť službu.',
                    life: 3000,
                });
            },
        });
};

const removeService = (service) => {
    openDialog({
        title: 'Odstrániť službu',
        message: `Odstrániť službu „${serviceTitle(service)}" z tejto pobočky?`,
        confirmLabel: 'Zmazať',
        confirmSeverity: 'danger',
        icon: 'pi pi-trash',
        onConfirm: () => {
            router.delete(route('branches.services.destroy', [props.branch.id, service.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Úspech',
                        detail: 'Služba bola odstránená.',
                        life: 3000,
                    });
                },
                onError: () => {
                    toast.add({
                        severity: 'error',
                        summary: 'Chyba',
                        detail: 'Nepodarilo sa odstrániť službu.',
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
        <div class="space-y-6">
            <TableCard
                title="Služby pobočky"
                description="Všetky služby priradené k tejto pobočke."
                :rows="services"
                :columns="columns"
                empty-message="Táto pobočka zatiaľ nemá žiadne služby."
                show-row-actions
            >
                <template #actions>
                    <Button
                        label="Pridať službu"
                        @click="openCreateDialog"
                    />
                </template>

                <template #cell-title_label="{ row }">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
                            <i :class="serviceIcon(row)" />
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-dark">
                                {{ row.title_label }}
                            </p>

                            <p class="truncate text-xs text-accent/70">
                                Kategória: {{ row.category_label }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #cell-duration_label="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.duration_label }}
                    </span>
                </template>

                <template #cell-insurance_price_label="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.insurance_price_label }}
                    </span>
                </template>

                <template #cell-self_pay_price_label="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.self_pay_price_label }}
                    </span>
                </template>

                <template #cell-availability_label="{ row }">
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                        :class="row.is_active ? 'bg-soft text-accent' : 'bg-soft/70 text-accent/60'"
                    >
                        {{ row.availability_label }}
                    </span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Upraviť"
                            size="small"
                            severity="secondary"
                            @click="openEditDialog(row)"
                        />

                        <Button
                            label="Odstrániť"
                            size="small"
                            severity="danger"
                            outlined
                            @click="removeService(row)"
                        />
                    </div>
                </template>
            </TableCard>

            <FormDialog
                v-model:visible="createDialogVisible"
                title="Pridať službu"
                description="Kategória a názov služby sú povinné. Ostatné údaje môžete doplniť podľa potreby."
                width="max-w-6xl"
                :dismissable-mask="!createForm.processing"
                @close="closeCreateDialog"
            >
                <BranchServiceForm
                    :form="createForm"
                    mode="create"
                    :categories="categoryOptions"
                    :new-category-value="newCategoryValue"
                    submit-label="Vytvoriť službu"
                    :loading="createForm.processing"
                    @submit="createService"
                />
            </FormDialog>

            <FormDialog
                v-model:visible="editDialogVisible"
                title="Upraviť službu"
                :description="editingService ? serviceTitle(editingService) : ''"
                width="max-w-6xl"
                :dismissable-mask="!editForm.processing"
                @close="closeEditDialog"
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
            </FormDialog>
        </div>

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