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

const createServiceDialogVisible = ref(false);
const editServiceDialogVisible = ref(false);
const editingBranchService = ref(null);

const newCategoryValue = '__new_category__';

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const makeEmptyServiceData = () => ({
    service: {
        category_id: null,
        new_category_name: '',
        name: '',
        slug: '',
        short_description: '',
        description: '',
        icon: '',
        duration_sessions: 1,
        duration_minutes: null,
        is_active: true,
        sort_order: 0,
    },

    branch_service: {
        custom_title: '',
        custom_description: '',
        is_available: true,
        sort_order: 0,
    },

    prices: {
        insurance_amount: null,
        insurance_note: '',
        self_pay_amount: null,
        self_pay_note: '',
    },

    information: [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    necessities: [
        {
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    steps: [
        {
            number: 1,
            title: '',
            text: '',
            is_active: true,
            sort_order: 0,
        },
    ],

    tags: [],
    tag_name: '',
    files: [],
});

const createServiceForm = useForm(makeEmptyServiceData());
const editServiceForm = useForm(makeEmptyServiceData());

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
    return slugify(createServiceForm.service.name);
});

const branchServiceTitle = (branchService) => {
    return branchService.custom_title || branchService.service?.name || '—';
};

const serviceDisplayName = (service) => {
    return service?.name ?? '—';
};

const getServicePrice = (branchService, type) => {
    return branchService.prices?.find((price) => price.price_type === type) ?? null;
};

const formatPrice = (price) => {
    if (!price || price.amount === null || price.amount === undefined) {
        return '—';
    }

    return `${price.amount} ${price.currency ?? 'EUR'}`;
};

const serviceCategoryName = (branchService) => {
    return branchService.service?.category?.name || 'Bez kategórie';
};

const serviceIcon = (branchService) => {
    return branchService.service?.icon || 'pi pi-briefcase';
};

const serviceDuration = (branchService) => {
    const sessions = branchService.service?.duration_sessions;
    const minutes = branchService.service?.duration_minutes;

    if (!minutes) {
        return '—';
    }

    if (!sessions || sessions === 1) {
        return `${minutes} min`;
    }

    return `${sessions} × ${minutes} min`;
};

const resetServiceForm = (form) => {
    const emptyData = makeEmptyServiceData();

    form.clearErrors();

    form.service = { ...emptyData.service };
    form.branch_service = { ...emptyData.branch_service };
    form.prices = { ...emptyData.prices };
    form.information = [...emptyData.information];
    form.necessities = [...emptyData.necessities];
    form.steps = [...emptyData.steps];
    form.tags = [];
    form.tag_name = '';
    form.files = [];
};

const openCreateServiceDialog = () => {
    resetServiceForm(createServiceForm);
    createServiceDialogVisible.value = true;
};

const closeCreateServiceDialog = () => {
    createServiceDialogVisible.value = false;
    resetServiceForm(createServiceForm);
};

const fillEditServiceForm = (branchService) => {
    const service = branchService.service ?? {};
    const insurancePrice = getServicePrice(branchService, 'insurance');
    const selfPayPrice = getServicePrice(branchService, 'self_pay');

    editServiceForm.clearErrors();

    editServiceForm.service = {
        category_id: service.category_id ?? service.category?.id ?? null,
        new_category_name: '',
        name: service.name ?? '',
        slug: service.slug ?? '',
        short_description: service.short_description ?? '',
        description: service.description ?? '',
        icon: service.icon ?? '',
        duration_sessions: service.duration_sessions ?? 1,
        duration_minutes: service.duration_minutes ?? null,
        is_active: Boolean(service.is_active ?? true),
        sort_order: service.sort_order ?? 0,
    };

    editServiceForm.branch_service = {
        custom_title: branchService.custom_title ?? '',
        custom_description: branchService.custom_description ?? '',
        is_available: Boolean(branchService.is_available ?? true),
        sort_order: branchService.sort_order ?? 0,
    };

    editServiceForm.prices = {
        insurance_amount: insurancePrice?.amount ?? null,
        insurance_note: insurancePrice?.note ?? '',
        self_pay_amount: selfPayPrice?.amount ?? null,
        self_pay_note: selfPayPrice?.note ?? '',
    };

    editServiceForm.information = service.information?.length
        ? service.information.map((item, index) => ({
            text: item.text ?? '',
            is_active: Boolean(item.is_active ?? true),
            sort_order: item.sort_order ?? index,
        }))
        : [
            {
                text: '',
                is_active: true,
                sort_order: 0,
            },
        ];

    editServiceForm.necessities = service.necessities?.length
        ? service.necessities.map((item, index) => ({
            text: item.text ?? '',
            is_active: Boolean(item.is_active ?? true),
            sort_order: item.sort_order ?? index,
        }))
        : [
            {
                text: '',
                is_active: true,
                sort_order: 0,
            },
        ];

    editServiceForm.steps = service.steps?.length
        ? service.steps.map((step, index) => ({
            number: step.number ?? index + 1,
            title: step.title ?? '',
            text: step.text ?? '',
            is_active: Boolean(step.is_active ?? true),
            sort_order: step.sort_order ?? index,
        }))
        : [
            {
                number: 1,
                title: '',
                text: '',
                is_active: true,
                sort_order: 0,
            },
        ];

    editServiceForm.tags = service.tags?.length
        ? service.tags.map((tag, index) => ({
            name: tag.name ?? '',
            sort_order: tag.sort_order ?? index,
        }))
        : [];

    editServiceForm.tag_name = '';
    editServiceForm.files = [];
};

const openEditServiceDialog = (branchService) => {
    editingBranchService.value = branchService;
    fillEditServiceForm(branchService);
    editServiceDialogVisible.value = true;
};

const closeEditServiceDialog = () => {
    editServiceDialogVisible.value = false;
    editingBranchService.value = null;
    resetServiceForm(editServiceForm);
};

const makeCreateServicePayload = (form, slug) => {
    const payload = {
        create_new: true,
        category_id: form.service.category_id === newCategoryValue ? null : form.service.category_id,
        new_category_name: form.service.new_category_name,
        name: form.service.name,
        slug,
        short_description: form.service.short_description,
        description: form.service.description,
        icon: form.service.icon,
        duration_sessions: form.service.duration_sessions,
        duration_minutes: form.service.duration_minutes,

        custom_title: form.branch_service.custom_title,
        custom_description: form.branch_service.custom_description,
        is_available: form.branch_service.is_available,
        sort_order: form.branch_service.sort_order,
    };

    return payload;
};

const makeUpdateServicePayload = (form) => {
    return {
        custom_title: form.branch_service.custom_title,
        custom_description: form.branch_service.custom_description,
        is_available: form.branch_service.is_available,
        sort_order: form.branch_service.sort_order,

        insurance_amount: form.prices.insurance_amount,
        insurance_note: form.prices.insurance_note,
        self_pay_amount: form.prices.self_pay_amount,
        self_pay_note: form.prices.self_pay_note,
    };
};

const createFullService = () => {
    const payload = makeCreateServicePayload(createServiceForm, generatedCreateSlug.value);

    createServiceForm
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

                closeCreateServiceDialog();
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

const updateFullService = () => {
    if (!editingBranchService.value) {
        return;
    }

    const payload = makeUpdateServicePayload(editServiceForm);

    editServiceForm
        .transform(() => payload)
        .post(route('branches.services.update', [props.branch.id, editingBranchService.value.id]), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.add({
                    severity: 'success',
                    summary: 'Úspech',
                    detail: 'Služba bola upravená.',
                    life: 3000,
                });

                closeEditServiceDialog();
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

const removeBranchService = (branchService) => {
    openDialog({
        title: 'Odstrániť službu',
        message: `Odstrániť službu ${branchServiceTitle(branchService)} z tejto pobočky?`,
        confirmLabel: 'Zmazať',
        onConfirm: () => {
            router.delete(route('branches.services.destroy', [props.branch.id, branchService.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.add({
                        severity: 'success',
                        summary: 'Úspech',
                        detail: 'Služba bola odstránená z pobočky.',
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
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Pobočka
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    Služby pobočky
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Spravujte služby tejto pobočky. Vytvorenie aj úprava služby prebieha v samostatnom okne.
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
                    @click="openCreateServiceDialog"
                />
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Existujúce služby v pobočke
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Služby, ktoré sú už priradené k tejto pobočke.
                        </p>
                    </div>

                    <Tag
                        :value="`${branch.branch_services?.length ?? 0} služieb`"
                        severity="secondary"
                    />
                </div>
            </div>

            <DataTable
                :value="branch.branch_services ?? []"
                tableStyle="min-width: 64rem"
                emptyMessage="Táto pobočka zatiaľ nemá priradené služby."
            >
                <Column header="Služba">
                    <template #body="{ data }">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                <i :class="serviceIcon(data)" />
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ branchServiceTitle(data) }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    Pôvodný názov: {{ serviceDisplayName(data.service) }}
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
                            <p>
                                Poisťovňa: {{ formatPrice(getServicePrice(data, 'insurance')) }}
                            </p>

                            <p>
                                Samoplatca: {{ formatPrice(getServicePrice(data, 'self_pay')) }}
                            </p>
                        </div>
                    </template>
                </Column>

                <Column header="Dostupnosť">
                    <template #body="{ data }">
                        <Tag
                            :value="data.is_available ? 'Dostupná' : 'Nedostupná'"
                            :severity="data.is_available ? 'success' : 'secondary'"
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
                                @click="openEditServiceDialog(data)"
                            />

                            <Button
                                label="Odstrániť"
                                size="small"
                                severity="danger"
                                outlined
                                icon="pi pi-trash"
                                @click="removeBranchService(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </section>

        <Dialog
            v-model:visible="createServiceDialogVisible"
            modal
            header="Pridať službu"
            class="w-[95vw] max-w-6xl"
            :draggable="false"
            :dismissable-mask="!createServiceForm.processing"
            @hide="resetServiceForm(createServiceForm)"
        >
            <BranchServiceForm
                :form="createServiceForm"
                mode="create"
                :categories="categoryOptions"
                :new-category-value="newCategoryValue"
                title="Vytvoriť novú službu"
                description="Kategória a názov služby sú povinné. Ostatné údaje môžete doplniť podľa potreby."
                submit-label="Vytvoriť službu"
                :loading="createServiceForm.processing"
                @submit="createFullService"
            />
        </Dialog>

        <Dialog
            v-model:visible="editServiceDialogVisible"
            modal
            header="Upraviť službu"
            class="w-[95vw] max-w-6xl"
            :draggable="false"
            :dismissable-mask="!editServiceForm.processing"
            @hide="closeEditServiceDialog"
        >
            <BranchServiceForm
                v-if="editingBranchService"
                :form="editServiceForm"
                mode="edit"
                :categories="categoryOptions"
                :new-category-value="newCategoryValue"
                title="Upraviť službu"
                :description="branchServiceTitle(editingBranchService)"
                submit-label="Uložiť zmeny"
                :loading="editServiceForm.processing"
                @submit="updateFullService"
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