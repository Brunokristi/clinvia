<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import TableCard from '@/Components/Tables/TableCard.vue';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
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
    <section>
        <TableCard
            v-if="rows.length > 0"
            :title="title"
            :description="description"
        >
            <template #actions>
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="filters.global.value"
                        class="w-full md:max-w-md"
                        placeholder="Hľadať"
                    />
                </IconField>

                <Link
                    v-if="showCreateButton"
                    :href="route(createHref)"
                >
                    <Button
                        :label="createLabel"
                    />
                </Link>
            </template>

            <DataTable
                v-if="rows.length > 0"
                v-model:filters="filters"
                :value="tableRows"
                :globalFilterFields="[
                    'legal_name',
                    'company_number'
                ]"
                paginator
                :rows="20"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                removableSort
                stripedRows
                rowHover
                tableStyle="min-width: 40rem"
                emptyMessage="Nenašli sa žiadne firmy."
            >
                <Column
                    field="legal_name"
                    header="Názov firmy"
                    sortable
                />

                <Column
                    field="company_number"
                    header="IČO"
                    sortable
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
                            <Button
                                @click="route('companies.edit', { company: data.id })"
                                label="Zobraziť"
                            />

                            <Button
                                type="button"
                                label="Zmazať"
                                severity="danger"
                                outlined
                                @click="openDeleteDialog(data)"
                            />
                        </div>
                    </template>
                </Column>

                <template #paginatorcontainer="{ first, last, page, pageCount, prevPageCallback, nextPageCallback, totalRecords }">
                    <div class="flex items-center justify-between gap-4 bg-transparentw w-full py-1">
                        <Button icon="pi pi-chevron-left" class="!text-xs" text @click="prevPageCallback" :disabled="page === 0" />
                        <div class="text-color text-normal font-semibold w-full min-w-[500px] text-center">
                            <span class="hidden sm:block">Showing {{ first }} to {{ last }} of {{ totalRecords }}</span>
                            <span class="block sm:hidden">Page {{ page + 1 }} of {{ pageCount }}</span>
                        </div>
                        <Button icon="pi pi-chevron-right" class="!text-xs" text @click="nextPageCallback" :disabled="page === pageCount - 1" />
                    </div>
                </template>
            </DataTable>
        </TableCard>

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