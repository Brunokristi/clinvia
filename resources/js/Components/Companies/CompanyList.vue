<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import { FilterMatchMode } from '@primevue/core/api';
import { computed, ref } from 'vue';

const props = defineProps({
    companies: {
        type: [Array, Object],
        required: true,
    },
    title: {
        type: String,
        default: 'Firmy',
    },
    description: {
        type: String,
        default: 'Správa firiem v systéme.',
    },
    showCreateButton: {
        type: Boolean,
        default: true,
    },
    createHref: {
        type: String,
        default: 'companies.create',
    },
    createLabel: {
        type: String,
        default: 'Pridať firmu',
    },
    emptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadne firmy.',
    },
    showActions: {
        type: Boolean,
        default: true,
    },
});

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const rows = computed(() => {
    return Array.isArray(props.companies)
        ? props.companies
        : props.companies?.data ?? [];
});

const paginationLinks = computed(() => {
    return Array.isArray(props.companies?.links)
        ? props.companies.links
        : [];
});

const filters = ref({
    global: {
        value: null,
        matchMode: FilterMatchMode.CONTAINS,
    },
    legal_name: {
        value: null,
        matchMode: FilterMatchMode.CONTAINS,
    },
    company_number: {
        value: null,
        matchMode: FilterMatchMode.CONTAINS,
    },
});

const selectedCompanyToDelete = ref(null);

const tableRows = computed(() => {
    return rows.value.map((company) => ({
        ...company,
        legal_name: company.legal_name || company.name || '',
        company_number: String(company.id_number || company.company_id_number || ''),
    }));
});

const companyName = (company) => {
    return company.legal_name || company.name || 'Bez názvu';
};

const openDeleteDialog = (company) => {
    selectedCompanyToDelete.value = company;

    openDialog({
        title: 'Odstrániť firmu',
        message: `Naozaj chceš odstrániť firmu ${companyName(company)}?`,
        confirmLabel: 'Odstrániť',
        onConfirm: deleteCompany,
    });
};

const closeDeleteDialog = () => {
    selectedCompanyToDelete.value = null;
    closeDialog();
};

const deleteCompany = () => {
    if (!selectedCompanyToDelete.value) {
        return;
    }

    router.delete(route('companies.destroy', { company: selectedCompanyToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: closeDeleteDialog,
        onError: closeDeleteDialog,
    });
};

const clearFilters = () => {
    filters.value = {
        global: {
            value: null,
            matchMode: FilterMatchMode.CONTAINS,
        },
        legal_name: {
            value: null,
            matchMode: FilterMatchMode.CONTAINS,
        },
        company_number: {
            value: null,
            matchMode: FilterMatchMode.CONTAINS,
        },
    };
};

const linkClass = (link) => {
    return [
        'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-medium transition',
        link.active
            ? 'border-slate-900 bg-slate-900 text-white'
            : link.url
                ? 'border-slate-300 text-slate-700 hover:bg-slate-50'
                : 'cursor-not-allowed border-slate-200 text-slate-300',
    ].join(' ');
};
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    {{ title }}
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>

            <Link
                v-if="showCreateButton"
                :href="route(createHref)"
            >
                <Button
                    :label="createLabel"
                    icon="pi pi-plus"
                />
            </Link>
        </div>

        <DataTable
            v-if="rows.length > 0"
            v-model:filters="filters"
            :value="tableRows"
            :globalFilterFields="[
                'legal_name',
                'company_number'
            ]"
            filterDisplay="row"
            paginator
            :rows="10"
            :rowsPerPageOptions="[10, 25, 50, 100]"
            removableSort
            stripedRows
            rowHover
            tableStyle="min-width: 40rem"
            emptyMessage="Nenašli sa žiadne firmy."
        >
            <template #header>
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <InputText
                        v-model="filters.global.value"
                        class="w-full md:max-w-md"
                        placeholder="Hľadať podľa názvu alebo IČO..."
                    />

                    <Button
                        type="button"
                        label="Vyčistiť filtre"
                        icon="pi pi-filter-slash"
                        severity="secondary"
                        outlined
                        @click="clearFilters"
                    />
                </div>
            </template>

            <Column
                field="legal_name"
                header="Názov firmy"
                sortable
                filter
                filterPlaceholder="Filtrovať názov"
            />

            <Column
                field="company_number"
                header="IČO"
                sortable
                filter
                filterPlaceholder="Filtrovať IČO"
            >
                <template #body="{ data }">
                    <span>{{ data.company_number || '—' }}</span>
                </template>
            </Column>

            <Column
                v-if="showActions"
                header="Akcie"
                style="width: 180px"
            >
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Link
                            :href="route('companies.edit', { company: data.id })"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Zobraziť
                        </Link>

                        <Button
                            type="button"
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="openDeleteDialog(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>

        <div
            v-else
            class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500"
        >
            {{ emptyMessage }}
        </div>

        <div
            v-if="paginationLinks.length > 3"
            class="mt-6 flex flex-wrap gap-2"
        >
            <template
                v-for="link in paginationLinks"
                :key="link.label"
            >
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="linkClass(link)"
                    preserve-scroll
                    v-html="link.label"
                />

                <span
                    v-else
                    :class="linkClass(link)"
                    v-html="link.label"
                />
            </template>
        </div>

        <ConfirmationDialog
            :show="dialog.visible"
            :title="dialog.title"
            :message="dialog.message"
            :confirm-label="dialog.confirmLabel"
            :cancel-label="dialog.cancelLabel"
            :confirm-severity="dialog.confirmSeverity"
            :icon="dialog.icon"
            @cancel="closeDeleteDialog"
            @confirm="confirmDialog"
        />
    </section>
</template>