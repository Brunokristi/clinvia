<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    companies: Object,
});

const deleteCompany = (company) => {
    if (! confirm(`Naozaj chceš odstrániť firmu ${company.legal_name}?`)) {
        return;
    }

    router.delete(route('companies.destroy', company.id));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    Firmy
                </h1>
                <p class="text-sm text-gray-500">
                    Správa firiem v systéme.
                </p>
            </div>

            <Link :href="route('companies.create')">
                <Button label="Pridať firmu" icon="pi pi-plus" />
            </Link>
        </div>

        <DataTable :value="companies.data" tableStyle="min-width: 60rem">
            <Column field="legal_name" header="Oficiálny názov" />
            <Column field="slug" header="Slug" />
            <Column field="city" header="Mesto" />
            <Column field="country" header="Krajina" />
            <Column field="email" header="Email" />
            <Column field="phone" header="Telefón" />

            <Column header="Aktívna">
                <template #body="{ data }">
                    {{ data.is_active ? 'Áno' : 'Nie' }}
                </template>
            </Column>

            <Column header="Akcie">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Link :href="route('companies.edit', data.id)">
                            <Button label="Upraviť" size="small" outlined />
                        </Link>

                        <Button
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteCompany(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </AdminLayout>
</template>