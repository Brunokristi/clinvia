<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

defineProps({
    users: Object,
});

const deleteUser = (user) => {
    if (! confirm(`Naozaj chceš odstrániť používateľa ${user.name}?`)) {
        return;
    }

    router.delete(route('users.destroy', user.id));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">
                    Používatelia
                </h1>
                <p class="text-sm text-gray-500">
                    Správa admin účtov.
                </p>
            </div>

            <Link :href="route('users.create')">
                <Button label="Pridať používateľa" icon="pi pi-plus" />
            </Link>
        </div>

        <DataTable :value="users.data" tableStyle="min-width: 50rem">
            <Column field="name" header="Meno" />
            <Column field="email" header="Email" />
            <Column field="global_role" header="Rola" />
            <Column header="Aktívny">
                <template #body="{ data }">
                    {{ data.is_active ? 'Áno' : 'Nie' }}
                </template>
            </Column>
            <Column header="Akcie">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Link :href="route('users.edit', data.id)">
                            <Button label="Upraviť" size="small" outlined />
                        </Link>

                        <Button
                            label="Zmazať"
                            size="small"
                            severity="danger"
                            outlined
                            @click="deleteUser(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </AdminLayout>
</template>