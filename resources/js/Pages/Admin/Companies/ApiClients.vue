<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmationDialog from '@/Components/Dialogs/ConfirmationDialog.vue';
import FormDialog from '@/Components/Dialogs/FormDialog.vue';
import ApiClientForm from '@/Components/ApiClients/ApiClientsForm.vue';
import TableCard from '@/Components/Tables/TableCard.vue';
import { useConfirmationDialog } from '@/Composables/useConfirmationDialog';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    company: {
        type: Object,
        required: true,
    },
    apiClients: {
        type: Object,
        required: true,
    },
    companies: {
        type: Array,
        default: () => [],
    },
});

const toast = useToast();

const createDialogOpen = ref(false);

const { dialog, openDialog, closeDialog, confirmDialog } = useConfirmationDialog();

const closeCreateDialog = () => {
    createDialogOpen.value = false;
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Nikdy';
    }

    return new Date(value).toLocaleString('sk-SK');
};

const apiClientList = computed(() => {
    if (Array.isArray(props.apiClients)) {
        return props.apiClients;
    }

    if (Array.isArray(props.apiClients?.data)) {
        return props.apiClients.data;
    }

    return [];
});

const rows = computed(() => {
    return apiClientList.value.map((apiClient) => {
        const domains = apiClient.domains ?? [];
        const domainList = domains.length
            ? domains.map((domain) => domain.domain).join(', ')
            : '—';

        const token = apiClient.plain_text_token ?? '';

        return {
            ...apiClient,
            token,
            token_label: token || '—',
            company_name: apiClient.company?.legal_name ?? '—',
            domain_list: domainList,
            status: 'Aktívny',
            last_used_label: formatDateTime(apiClient.last_used_at),
            rate_limit_label: `${apiClient.rate_limit_per_minute ?? 'unlimited'}/min`,
        };
    });
});

const columns = [
    {
        field: 'name',
        header: 'Názov',
        sortable: true,
    },
    {
        field: 'token_label',
        header: 'API kľúč',
        sortable: true,
    },
    {
        field: 'domain_list',
        header: 'Domény',
        sortable: true,
    },
    {
        field: 'last_used_label',
        header: 'Naposledy použitý',
        sortable: true,
    },
];

const copyApiToken = async (apiClient) => {
    if (!apiClient.token) {
        toast.add({
            severity: 'warn',
            summary: 'API kľúč nie je dostupný',
            detail: 'Tento API kľúč sa nedá skopírovať.',
            life: 2500,
        });

        return;
    }

    try {
        await navigator.clipboard.writeText(apiClient.token);

        toast.add({
            severity: 'success',
            summary: 'Skopírované',
            detail: 'API kľúč bol skopírovaný do schránky.',
            life: 2500,
        });
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Kopírovanie zlyhalo',
            detail: 'API kľúč sa nepodarilo skopírovať.',
            life: 3000,
        });
    }
};

const regenerateApiClient = (apiClient) => {
    openDialog({
        title: 'Pregenerovať API token',
        message: `Pregenerovať token pre ${apiClient.name}? Starý token prestane fungovať.`,
        confirmLabel: 'Pregenerovať',
        confirmSeverity: 'warning',
        onConfirm: () => {
            router.post(route('api-clients.regenerate', apiClient.id), {}, {
                preserveScroll: true,
            });
        },
    });
};

const deleteApiClient = (apiClient) => {
    openDialog({
        title: 'Vymazať API kľúč',
        message: `Naozaj chcete vymazať API kľúč ${apiClient.name}? Táto akcia sa nedá vrátiť späť.`,
        confirmLabel: 'Vymazať',
        confirmSeverity: 'danger',
        onConfirm: () => {
            router.delete(route('api-clients.destroy', apiClient.id), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <TableCard
                title="Existujúce API kľúče"
                description="Kľúče sú aktívne automaticky. Hodnota API kľúča je uložená tak, aby sa dala zobraziť a skopírovať v tabuľke."
                :rows="rows"
                :columns="columns"
                empty-message="Zatiaľ nie sú vytvorené žiadne API kľúče."
                show-row-actions
            >
                <template #actions>
                    <Button
                        label="Vytvoriť API kľúč"
                        @click="createDialogOpen = true"
                    />
                </template>

                <template #cell-name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-dark">
                            {{ row.name }}
                        </p>
                    </div>
                </template>

                <template #cell-token_label="{ row }">
                    <div class="flex min-w-0 items-center gap-2">
                        <code class="max-w-[100px] truncate rounded-md bg-soft px-2 py-1 text-xs text-dark">
                            {{ row.token_label }}
                        </code>

                        <Button
                            v-if="row.token"
                            type="button"
                            size="small"
                            text
                            icon="pi pi-copy"
                            @click="copyApiToken(row)"
                        />
                    </div>
                </template>

                <template #cell-company_name="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.company_name }}
                    </span>
                </template>

                <template #cell-domain_list="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.domain_list }}
                    </span>
                </template>

                <template #cell-status="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.status }}
                    </span>
                </template>

                <template #cell-last_used_label="{ row }">
                    <span class="text-sm text-accent">
                        {{ row.last_used_label }}
                    </span>
                </template>

                <template #row-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Button
                            label="Regenerovať"
                            size="small"
                            severity="warning"
                            outlined
                            @click="regenerateApiClient(row)"
                        />

                        <Button
                            label="Vymazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteApiClient(row)"
                        />
                    </div>
                </template>
            </TableCard>

            <FormDialog
                v-model:visible="createDialogOpen"
                title="Vytvoriť API kľúč"
                description="Vytvorte nový API kľúč a povoľte domény, z ktorých ho môže frontend používať."
                width="max-w-3xl"
                @close="closeCreateDialog"
            >
                <ApiClientForm
                    :companies="companies"
                    :fixed-company-id="company.id"
                    :show-company-select="false"
                    @created="closeCreateDialog"
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