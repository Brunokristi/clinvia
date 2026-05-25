<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import TableCard from '@/Components/Tables/TableCard.vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
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

const selectedCompanyToDelete = ref(null);

const tableRows = computed(() => {
    return rows.value.map((company) => ({
        ...company,
        legal_name: company.legal_name || company.name || '',
        company_number: String(company.id_number || company.company_id_number || ''),
    }));
});

const columns = [
    {
        field: 'legal_name',
        header: 'Názov firmy',
        sortable: true,
    },
    {
        field: 'company_number',
        header: 'IČO',
        sortable: true,
        emptyValue: '—',
    },
];

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

</script>

<template>
    <section>
        <TableCard
            :title="title"
            :description="description"
            :rows="tableRows"
            :columns="columns"
            :search-fields="['legal_name', 'company_number']"
            empty-message="Zatiaľ tu nie sú žiadne firmy."
            show-row-actions
        >
            <template #actions>
                <a
                    v-if="showCreateButton"
                    :href="route(createHref)"
                >
                    <Button
                        :label="createLabel"
                    />
                </a>
            </template>

            <template #row-actions="{ row }">
                <div class="flex gap-2">
                    <Button
                        @click="router.visit(route('companies.edit', { company: row.id }))"
                        label="Detail"
                    />

                    <Button
                        v-if="showActions"
                        type="button"
                        label="Zmazať"
                        severity="danger"
                        outlined
                        @click="openDeleteDialog(row)"
                    />
                </div>
            </template>
        </TableCard>

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