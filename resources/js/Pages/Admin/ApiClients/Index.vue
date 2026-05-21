<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    apiClients: Object,
});

const page = usePage();

const deleteApiClient = (apiClient) => {
    if (! confirm(`Naozaj chceš odstrániť API klienta ${apiClient.name}?`)) {
        return;
    }

    router.delete(route('api-clients.destroy', apiClient.id));
};

const regenerateApiClient = (apiClient) => {
    if (! confirm(`Pregenerovať token pre ${apiClient.name}? Starý token prestane fungovať.`)) {
        return;
    }

    router.post(route('api-clients.regenerate', apiClient.id));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    API klienti
                </h1>

                <p class="text-sm text-gray-500">
                    Správa API tokenov pre verejné frontendy.
                </p>
            </div>

            <Link :href="route('api-clients.create')">
                <Button label="Pridať API klienta" icon="pi pi-plus" />
            </Link>
        </div>

        <div
            v-if="page.props.flash?.api_token"
            class="mb-6 rounded-lg border border-yellow-300 bg-yellow-50 p-4"
        >
            <h2 class="mb-2 font-semibold">
                Nový API token
            </h2>

            <p class="mb-2 text-sm text-yellow-800">
                Tento token sa zobrazí iba raz. Skopíruj si ho teraz.
            </p>

            <code class="block overflow-x-auto rounded bg-white p-3 text-sm">
                {{ page.props.flash.api_token }}
            </code>
        </div>

        <DataTable :value="apiClients.data" tableStyle="min-width: 70rem">
            <Column field="name" header="Názov" />
            <Column field="company.name" header="Firma" />
            <Column field="rate_limit_per_minute" header="Limit/min" />

            <Column header="Domény">
                <template #body="{ data }">
                    <div class="space-y-1">
                        <div
                            v-for="domain in data.domains"
                            :key="domain.id"
                            class="text-sm"
                        >
                            {{ domain.domain }}

                            <span v-if="! domain.is_active" class="text-red-600">
                                (neaktívna)
                            </span>
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Aktívny">
                <template #body="{ data }">
                    {{ data.is_active ? 'Áno' : 'Nie' }}
                </template>
            </Column>

            <Column header="Naposledy použitý">
                <template #body="{ data }">
                    {{ data.last_used_at ?? 'Nikdy' }}
                </template>
            </Column>

            <Column header="Akcie">
                <template #body="{ data }">
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('api-clients.edit', data.id)">
                            <Button label="Upraviť" size="small" outlined />
                        </Link>

                        <Button
                            label="Regenerovať"
                            size="small"
                            severity="warning"
                            outlined
                            @click="regenerateApiClient(data)"
                        />

                        <Button
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteApiClient(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </AdminLayout>
</template>