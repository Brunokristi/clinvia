<script setup>
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import TableCard from '@/Components/Tables/TableCard.vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { computed, ref } from 'vue';

const props = defineProps({
    branches: {
        type: [Array, Object],
        required: true,
    },
    title: {
        type: String,
        default: 'Pobočky',
    },
    description: {
        type: String,
        default: 'Správa pobočiek v systéme.',
    },
    showCreateButton: {
        type: Boolean,
        default: false,
    },
    createHref: {
        type: String,
        default: 'branches.create',
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

const rows = computed(() => {
    return Array.isArray(props.branches)
        ? props.branches
        : props.branches?.data ?? [];
});

const selectedBranchToDelete = ref(null);

const tableRows = computed(() => {
    return rows.value.map((branch) => ({
        ...branch,
        company_name: branch.company?.legal_name || branch.company_name || '',
    }));
});

const columns = [
    {
        field: 'name',
        header: 'Názov pobočky',
        sortable: true,
    },
    {
        field: 'company_name',
        header: 'Firma',
        sortable: true,
    },
    {
        field: 'city',
        header: 'Mesto',
        sortable: true,
        emptyValue: '—',
    },
];

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

    router.delete(route('branches.destroy', { branch: selectedBranchToDelete.value.id }), {
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
            :search-fields="['name', 'company_name', 'city']"
            :empty-message="emptyMessage"
            show-row-actions
        >
            <template #actions>
                <a
                    v-if="showCreateButton"
                    :href="route(createHref)"
                >
                    <Button :label="createLabel" />
                </a>
            </template>

            <template #row-actions="{ row }">
                <div
                    v-if="showActions && row.can_manage !== false"
                    class="flex gap-2"
                >
                    <Button
                        type="button"
                        label="Dashboard"
                        @click="router.visit(route('branches.booking.dashboard.page', { branch: row.id }))"
                    />

                    <Button
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