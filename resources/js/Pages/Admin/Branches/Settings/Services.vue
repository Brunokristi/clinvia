<script setup>
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

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const makeCategoryOption = (category) => {
    if (!category) {
        return null;
    }

    const id = category.id ?? category.value ?? null;
    const name = category.name ?? category.label ?? '';

    if (!name) {
        return null;
    }

    return {
        id,
        value: id,
        name,
        label: name,
        is_custom: Boolean(category.is_custom),
    };
};

const categoryPayload = (form) => {
    const selectedCategory = form.category;

    if (selectedCategory?.is_custom) {
        const categoryName = String(
            selectedCategory.value
                ?? selectedCategory.name
                ?? selectedCategory.label
                ?? '',
        ).trim();

        return {
            category_id: null,
            new_category_name: categoryName,
        };
    }

    return {
        category_id: selectedCategory?.value ?? selectedCategory?.id ?? form.category_id ?? null,
        new_category_name: '',
    };
};

const makeEmptyServiceData = () => ({
    category: null,
    category_id: null,
    new_category_name: '',
    name: '',
    slug: '',
    short_description: '',
    description: '',
    icon: '',
    duration_sessions: 1,
    duration_minutes: null,
    is_bookable: false,
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

const createForm = useForm(makeEmptyServiceData());
const editForm = useForm(makeEmptyServiceData());

const categoryOptions = computed(() => {
    return props.categories
        .map((category) => makeCategoryOption(category))
        .filter(Boolean);
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

const trimText = (value, maxLength = 42) => {
    const text = String(value ?? '').trim();

    if (text.length <= maxLength) {
        return text || '—';
    }

    return `${text.slice(0, maxLength).trim()}…`;
};

const serviceDisplayTitle = (service) => {
    return trimText(service.name, 42);
};

const branchBookingEnabled = computed(() => {
    return Boolean(props.branch.booking_settings?.is_enabled);
});

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

const bookingLabel = (service) => {
    if (!service.is_bookable) {
        return 'Nerezervovateľná';
    }

    if (!service.duration_minutes) {
        return 'Rezervovateľná';
    }

    return `Rezervovateľná`;
};

const fillInformationItems = (items = []) => {
    return items.map((item) => ({
        existing_id: item.id ?? null,
        text: item.text ?? '',
    }));
};

const fillStepItems = (items = []) => {
    return items.map((item) => ({
        existing_id: item.id ?? null,
        number: item.number ?? null,
        title: item.title ?? '',
        text: item.text ?? '',
    }));
};

const fillFileItems = (items = []) => {
    return items.map((item) => ({
        existing_id: item.id ?? null,
        label: item.label ?? '',
        file: null,
        existing_name: item.original_name ?? item.label ?? 'Súbor',
    }));
};

const services = computed(() => {
    return (props.branch.services ?? []).map((service) => ({
        ...service,
        title_label: serviceDisplayTitle(service),
        full_title_label: serviceTitle(service),
        category_label: serviceCategoryName(service),
        duration_label: serviceDuration(service),
        insurance_price_label: formatPrice(service.insurance_amount),
        self_pay_price_label: formatPrice(service.self_pay_amount),
        availability_label: service.is_active ? 'Aktívna' : 'Neaktívna',
        booking_label: bookingLabel(service),
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
        field: 'booking_label',
        header: 'Rezervácia',
        sortable: true,
    },
];

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

    editForm.category = makeCategoryOption(service.category ?? {
        id: service.category_id,
        name: service.category?.name,
    });
    editForm.category_id = service.category_id ?? service.category?.id ?? null;
    editForm.new_category_name = '';
    editForm.name = service.name ?? '';
    editForm.slug = service.slug ?? '';
    editForm.short_description = service.short_description ?? '';
    editForm.description = service.description ?? '';
    editForm.icon = service.icon ?? '';
    editForm.duration_sessions = service.duration_sessions ?? 1;
    editForm.duration_minutes = service.duration_minutes ?? null;
    editForm.is_bookable = branchBookingEnabled.value && Boolean(service.is_bookable);
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
    const category = categoryPayload(createForm);

    const payload = {
        ...category,
        name: createForm.name,
        slug: generatedCreateSlug.value,
        short_description: createForm.short_description,
        description: createForm.description,
        icon: createForm.icon,
        duration_sessions: createForm.duration_sessions,
        duration_minutes: createForm.duration_minutes,
        is_bookable: branchBookingEnabled.value ? createForm.is_bookable : false,
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

    const category = categoryPayload(editForm);

    const payload = {
        ...category,
        name: editForm.name,
        short_description: editForm.short_description,
        description: editForm.description,
        icon: editForm.icon,
        duration_sessions: editForm.duration_sessions,
        duration_minutes: editForm.duration_minutes,
        is_bookable: branchBookingEnabled.value ? editForm.is_bookable : false,
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

const printServicesPdf = () => {
    window.open(route('branches.services.pdf.show', props.branch.id), '_blank');
};

const downloadServicesPdf = () => {
    window.location.href = route('branches.services.pdf.download', props.branch.id);
};
</script>

<template>
    <div class="space-y-6">
        <TableCard
            title="Služby pobočky"
            description="Všetky služby priradené k tejto pobočke."
            :rows="services"
            :columns="columns"
            empty-message="Táto pobočka zatiaľ nemá žiadne služby."
            show-row-actions
            class="py-10"
        >
            <template #actions>
                <Button
                    type="button"
                    icon="pi pi-print"
                    outlined
                    @click="printServicesPdf"
                />

                <Button
                    type="button"
                    icon="pi pi-download"
                    outlined
                    @click="downloadServicesPdf"
                />

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
                        <p
                            class="truncate text-sm font-semibold text-dark"
                            :title="row.full_title_label"
                        >
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

            <template #cell-booking_label="{ row }">
                <span class="text-sm text-accent">
                    {{ row.booking_label }}
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
                :branch="branch"
                mode="create"
                :categories="categoryOptions"
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
                :branch="branch"
                mode="edit"
                :categories="categoryOptions"
                title="Upraviť službu"
                :description="serviceTitle(editingService)"
                submit-label="Uložiť zmeny"
                :loading="editForm.processing"
                @submit="updateService"
            />
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