<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, ref } from 'vue';

const props = defineProps({
    branches: {
        type: [Array, Object],
        default: () => [],
    },
    company: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'Pobočky',
    },
    description: {
        type: String,
        default: 'Spravujte svoje pobočky a otvárajte ich nastavenia.',
    },
    showCreateButton: {
        type: Boolean,
        default: false,
    },
    createLabel: {
        type: String,
        default: 'Pridať pobočku',
    },
    emptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadne pobočky.',
    },
    showActions: {
        type: Boolean,
        default: true,
    },
});

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const selectedBranchToDelete = ref(null);

const rows = computed(() => {
    return Array.isArray(props.branches)
        ? props.branches
        : props.branches?.data ?? [];
});

const tableRows = computed(() => {
    return rows.value.map((branch) => ({
        ...branch,
        company_name: branch.company?.legal_name || branch.company_name || props.company?.legal_name || '',
    }));
});

const columns = computed(() => {
    const baseColumns = [
        {
            field: 'name',
            header: 'Názov',
            sortable: true,
        },
        {
            field: 'address_line_1',
            header: 'Ulica',
            sortable: true,
            emptyValue: '—',
        },
        {
            field: 'city',
            header: 'Mesto',
            sortable: true,
            emptyValue: '—',
        },
    ];

    if (!props.company) {
        baseColumns.splice(1, 0, {
            field: 'company_name',
            header: 'Firma',
            sortable: true,
            emptyValue: '—',
        });
    }

    return baseColumns;
});

const createHref = computed(() => {
    if (props.company) {
        return route('branches.create', {
            company: props.company.id,
        });
    }

    return route('branches.create');
});

const branchName = (branch) => {
    return branch.name || 'Bez názvu';
};

const openDeleteDialog = (branch) => {
    selectedBranchToDelete.value = branch;

    openDialog({
        title: 'Odstrániť pobočku',
        message: `Naozaj odstrániť pobočku ${branchName(branch)}?`,
        confirmLabel: 'Odstrániť',
        confirmSeverity: 'danger',
        onConfirm: deleteBranch,
    });
};

const closeDeleteDialog = () => {
    selectedBranchToDelete.value = null;
    closeDialog();
};

const deleteBranch = () => {
    if (!selectedBranchToDelete.value) {
        return;
    }

    router.delete(route('branches.destroy', selectedBranchToDelete.value.id), {
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
            :search-fields="['name', 'company_name', 'address_line_1', 'city']"
            :empty-message="emptyMessage"
            show-row-actions
        >
            <template #actions>
                <Link
                    v-if="showCreateButton"
                    :href="createHref"
                >
                    <Button
                        type="button"
                        :label="createLabel"
                    />
                </Link>
            </template>

            <template #row-actions="{ row }">
                <div
                    v-if="showActions && row.can_manage !== false"
                    class="flex items-center gap-2"
                >
                    <Link :href="route('branches.booking.dashboard.page', row.id)">
                        <Button
                            type="button"
                            label="Detail"
                            size="small"
                        />
                    </Link>

                    <Button
                        type="button"
                        label="Zmazať"
                        size="small"
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