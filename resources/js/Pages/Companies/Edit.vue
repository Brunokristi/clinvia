<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import { Link, useForm } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';

const props = defineProps({
    company: Object,
    branches: {
        type: Array,
        default: () => [],
    },
    apiClients: {
        type: Array,
        default: () => [],
    },
    canSeeApiKeys: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    legal_name: props.company.legal_name ?? '',
    company_id_number: props.company.company_id_number ?? '',
    tax_id: props.company.tax_id ?? '',
    vat_id: props.company.vat_id ?? '',
    address_line_1: props.company.address_line_1 ?? '',
    address_line_2: props.company.address_line_2 ?? '',
    city: props.company.city ?? '',
    postal_code: props.company.postal_code ?? '',
    region: props.company.region ?? '',
    country: props.company.country ?? '',
    email: props.company.email ?? '',
    phone: props.company.phone ?? '',
    website: props.company.website ?? '',
    is_active: Boolean(props.company.is_active),
});

const submit = () => {
    form.put(route('companies.update', props.company.id));
};

const formatDateTime = (value) => {
    if (! value) {
        return 'Nikdy';
    }

    return new Date(value).toLocaleString('sk-SK');
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-2 text-2xl font-semibold">
            Upraviť firmu
        </h1>

        <p class="mb-6 text-sm text-slate-500">
            Najprv uprav údaje firmy, potom spravuj pobočky a API prístupy.
        </p>

        <form class="max-w-3xl" @submit.prevent="submit">
            <CompanyForm
                :form="form"
                submit-label="Uložiť"
                :loading="form.processing"
            />
        </form>

        <section class="mt-10 rounded-lg border bg-white p-5">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">
                        Pobočky firmy
                    </h2>

                    <p class="text-sm text-gray-500">
                        Otvor detail pobočky pre základné údaje, kontakty, otváracie hodiny, služby, používateľov a zamestnancov.
                    </p>
                </div>

                <Link
                    :href="route('branches.create')"
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

        <section v-if="canSeeApiKeys" class="mt-10 rounded-lg border bg-white p-5">
            <div class="mb-6 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">
                        API kľúče firmy
                    </h2>

                    <p class="text-sm text-gray-500">
                        Túto sekciu vidí iba superadmin.
                    </p>
                </div>

                <Link
                    :href="route('api-clients.create')"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    <i class="pi pi-plus text-xs"></i>
                    Pridať API klienta
                </Link>
            </div>

            <DataTable :value="apiClients" tableStyle="min-width: 60rem">
                <Column field="name" header="Názov" />
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

                                <span v-if="!domain.is_active" class="text-red-600">
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
                        {{ formatDateTime(data.last_used_at) }}
                    </template>
                </Column>

                <Column header="Akcie">
                    <template #body="{ data }">
                        <Link
                            :href="route('api-clients.edit', data.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Upraviť API klienta
                        </Link>
                    </template>
                </Column>
            </DataTable>
        </section>
    </AdminLayout>
</template>