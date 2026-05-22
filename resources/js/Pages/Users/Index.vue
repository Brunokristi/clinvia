<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

defineProps({
    users: Object,
});

const deleteUser = (user) => {
    const userName = [user.first_name, user.last_name].filter(Boolean).join(' ');

    if (! confirm(`Naozaj chceš odstrániť používateľa ${userName}?`)) {
        return;
    }

    router.delete(route('users.destroy', user.id));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Používatelia</h1>
                <p class="text-sm text-gray-500">Správa admin účtov.</p>
            </div>

            <Link
                :href="route('users.create')"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                <i class="pi pi-plus text-xs"></i>
                Pridať používateľa
            </Link>
        </div>

        <DataTable :value="users.data" tableStyle="min-width: 50rem">
            <Column field="first_name" header="First name" />
            <Column field="last_name" header="Last name" />
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
                        <Link
                            :href="route('users.edit', data.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Upraviť
                        </Link>

                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
                            @click="deleteUser(data)"
                        >
                            Zmazať
                        </button>
                    </div>
                </template>
            </Column>
        </DataTable>
    </AdminLayout>
</template>