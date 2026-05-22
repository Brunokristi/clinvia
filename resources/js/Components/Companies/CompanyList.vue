<script setup>
import { Link, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { computed } from 'vue';

const props = defineProps({
    companies: {
        type: [Array, Object],
        required: true,
    },
    title: {
        type: String,
        default: 'Firmy',
    },
    description: {
        type: String,
        default: 'Správa firiem v systéme.',
    },
    showCreateButton: {
        type: Boolean,
        default: true,
    },
    createHref: {
        type: String,
        default: 'companies.create',
    },
    createLabel: {
        type: String,
        default: 'Pridať firmu',
    },
    emptyMessage: {
        type: String,
        default: 'Zatiaľ tu nie sú žiadne firmy.',
    },
    showActions: {
        type: Boolean,
        default: true,
    },
});

const rows = computed(() => Array.isArray(props.companies) ? props.companies : (props.companies?.data ?? []));
const paginationLinks = computed(() => Array.isArray(props.companies?.links) ? props.companies.links : []);

const deleteCompany = (company) => {
    if (! confirm(`Naozaj chceš odstrániť firmu ${company.legal_name}?`)) {
        return;
    }

    router.delete(route('companies.destroy', company.id));
};

const linkClass = (link) => {
    return [
        'inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium transition',
        link.active
            ? 'border-slate-900 bg-slate-900 text-white'
            : 'border-slate-300 text-slate-700 hover:bg-slate-50',
    ].join(' ');
};
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    {{ title }}
                </h1>

                <p class="text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>

            <Link
                v-if="showCreateButton"
                :href="route(createHref)"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                <i class="pi pi-plus text-xs"></i>
                {{ createLabel }}
            </Link>
        </div>

        <DataTable v-if="rows.length > 0" :value="rows" tableStyle="min-width: 60rem">
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

            <Column v-if="showActions" header="Akcie">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Link
                            :href="route('companies.edit', data.id)"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Upraviť
                        </Link>

                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
                            @click="deleteCompany(data)"
                        >
                            Zmazať
                        </button>
                    </div>
                </template>
            </Column>
        </DataTable>

        <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-sm text-slate-500">
            {{ emptyMessage }}
        </div>

        <div v-if="paginationLinks.length > 3" class="mt-6 flex flex-wrap gap-2">
            <template v-for="link in paginationLinks" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="linkClass(link)"
                    v-html="link.label"
                />

                <span
                    v-else
                    :class="linkClass(link)"
                    v-html="link.label"
                />
            </template>
        </div>
    </section>
</template>