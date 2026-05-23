<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import ApiClientForm from '@/Components/ApiClients/ApiClientForm.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

defineProps({
    apiClients: Object,
    companies: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const createDialogOpen = ref(false);
const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const closeCreateDialog = () => {
    createDialogOpen.value = false;
};

const regenerateApiClient = (apiClient) => {
    openDialog({
        title: 'Pregenerovať API token',
        message: `Pregenerovať token pre ${apiClient.name}? Starý token prestane fungovať.`,
        confirmLabel: 'Pregenerovať',
        confirmSeverity: 'warning',
        icon: 'pi pi-refresh',
        onConfirm: () => {
            router.post(route('api-clients.regenerate', apiClient.id), {}, {
                preserveScroll: true,
            });
        },
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Nikdy';
    }

    return new Date(value).toLocaleString('sk-SK');
};
</script>

<template>
    <AdminLayout>
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                    Superadmin
                </p>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                    API kľúče
                </h1>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Správa API kľúčov pre verejné frontendy. Nový token sa zobrazí iba raz po vytvorení.
                </p>
            </div>

            <Button
                label="Vytvoriť API kľúč"
                icon="pi pi-plus"
                @click="createDialogOpen = true"
            />
        </div>

        <div
            v-if="page.props.flash?.api_token"
            class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5"
        >
            <div class="mb-3 flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-amber-950">
                        Nový API token
                    </h2>

                    <p class="mt-1 text-sm text-amber-800">
                        Tento token sa zobrazí iba raz. Skopírujte si ho teraz.
                    </p>
                </div>

                <Tag
                    value="Zobrazené iba raz"
                    severity="warning"
                />
            </div>

            <code class="block overflow-x-auto rounded-xl border border-amber-200 bg-white p-4 text-sm text-slate-900">
                {{ page.props.flash.api_token }}
            </code>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Existujúce API kľúče
                        </h2>

                        <p class="mt-1 text-sm text-slate-600">
                            Kľúče sú aktívne automaticky. Editácia nie je potrebná.
                        </p>
                    </div>

                    <Button
                        label="Nový kľúč"
                        icon="pi pi-plus"
                        outlined
                        @click="createDialogOpen = true"
                    />
                </div>
            </div>

            <DataTable
                :value="apiClients.data"
                tableStyle="min-width: 64rem"
                emptyMessage="Zatiaľ nie sú vytvorené žiadne API kľúče."
            >
                <Column header="Názov">
                    <template #body="{ data }">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ data.name }}
                            </p>

                            <p class="text-xs text-slate-500">
                                Limit: {{ data.rate_limit_per_minute ?? 'unlimited' }}/min
                            </p>
                        </div>
                    </template>
                </Column>

                <Column header="Firma">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-700">
                            {{ data.company?.legal_name ?? '—' }}
                        </span>
                    </template>
                </Column>

                <Column header="Domény">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Tag
                                v-for="domain in data.domains"
                                :key="domain.id"
                                :value="domain.domain"
                                severity="secondary"
                            />
                        </div>
                    </template>
                </Column>

                <Column header="Stav">
                    <template #body>
                        <Tag
                            value="Aktívny"
                            severity="success"
                        />
                    </template>
                </Column>

                <Column header="Naposledy použitý">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-700">
                            {{ formatDateTime(data.last_used_at) }}
                        </span>
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <Button
                            label="Regenerovať"
                            size="small"
                            severity="warning"
                            outlined
                            icon="pi pi-refresh"
                            @click="regenerateApiClient(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </section>

        <Dialog
            v-model:visible="createDialogOpen"
            modal
            header="Vytvoriť API kľúč"
            class="w-[95vw] max-w-3xl"
        >
            <ApiClientForm
                :companies="companies"
                @created="closeCreateDialog"
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