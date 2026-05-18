<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    branches: Object,
});

const deleteBranch = (branch) => {
    if (! confirm(`Naozaj chceš odstrániť pobočku ${branch.name}?`)) {
        return;
    }

    router.delete(route('branches.destroy', branch.id));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    Pobočky
                </h1>
                <p class="text-sm text-gray-500">
                    Správa pobočiek jednotlivých firiem.
                </p>
            </div>

            <Link :href="route('branches.create')">
                <Button label="Pridať pobočku" icon="pi pi-plus" />
            </Link>
        </div>

        <DataTable :value="branches.data" tableStyle="min-width: 60rem">
            <Column field="name" header="Názov" />
            <Column field="company.name" header="Firma" />
            <Column field="slug" header="Slug" />
            <Column field="type" header="Typ" />
            <Column field="city" header="Mesto" />

            <Column header="Aktívna">
                <template #body="{ data }">
                    {{ data.is_active ? 'Áno' : 'Nie' }}
                </template>
            </Column>

            <Column field="sort_order" header="Poradie" />

            <Column header="Akcie">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Link :href="route('branches.edit', data.id)">
                            <Button label="Upraviť" size="small" outlined />
                        </Link>

                        <Button
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteBranch(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </AdminLayout>
</template>