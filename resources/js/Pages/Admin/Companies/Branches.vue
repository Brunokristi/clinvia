<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

defineProps({
    company: Object,
    branches: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AdminLayout>
        <div class="mb-6 max-w-3xl">
            <h1 class="mb-2 text-2xl font-semibold">
                Pobočky firmy
            </h1>

            <p class="text-sm text-slate-500">
                Tu vidíš iba pobočky tejto firmy. Detail pobočky otvára samostatné stránky pre základné údaje, zamestnancov a služby.
            </p>
        </div>

        <section class="rounded-lg border bg-white p-5">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">
                        Zoznam pobočiek
                    </h2>

                    <p class="text-sm text-gray-500">
                        {{ company.legal_name }}
                    </p>
                </div>

                <Link
                    :href="route('branches.create', { company: company.id })"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="pi pi-plus text-xs"></i>
                    Pridať pobočku
                </Link>
            </div>

            <DataTable :value="branches" tableStyle="min-width: 50rem">
                <Column field="name" header="Názov" />
                <Column field="slug" header="Slug" />
                <Column field="city" header="Mesto" />

                <Column header="Aktívna">
                    <template #body="{ data }">
                        {{ data.is_active ? 'Áno' : 'Nie' }}
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <Link
                            :href="route('branches.edit', data.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Otvoriť detail
                        </Link>
                    </template>
                </Column>
            </DataTable>
        </section>
    </AdminLayout>
</template>