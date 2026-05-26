<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { Link, router } from '@inertiajs/vue3';

import Button from 'primevue/button';

defineProps({
    company: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
});

const columns = [
    { field: 'name', header: 'Názov', sortable: true },
    { field: 'address_line_1', header: 'Ulica', sortable: true },
    { field: 'city', header: 'Mesto', sortable: true },
];

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const deleteBranch = (branch) => {
    openDialog({
        title: 'Odstrániť pobočku',
        message: `Naozaj odstrániť pobočku ${branch.name}?`,
        confirmLabel: 'Odstrániť',
        onConfirm: () => {
            router.delete(route('branches.destroy', branch.id), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <TableCard
            title="Zoznam pobočiek"
            :description="company.legal_name"
            :rows="branches"
            :columns="columns"
            empty-message="Táto firma zatiaľ nemá žiadne pobočky."
            show-row-actions
        >
            <template #actions>
                <Link :href="route('branches.create', { company: company.id })">
                    <Button
                        type="button"
                        label="Pridať pobočku"
                        icon="pi pi-plus"
                    />
                </Link>
            </template>

            <template #row-actions="{ row }">
                <div class="flex items-center gap-2">
                    <Link :href="route('branches.edit', row.id)">
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
                        @click="deleteBranch(row)"
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
            @cancel="closeDialog"
            @confirm="confirmDialog"
        />
    </AdminLayout>
</template>