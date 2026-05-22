<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyList from '@/Components/Companies/CompanyList.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    companies: Object,
});

const page = usePage();

const canManageCompanies = computed(() => ['super_admin', 'admin'].includes(page.props.auth?.user?.global_role));
const canCreateCompanies = computed(() => page.props.auth?.user?.global_role === 'super_admin');
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div class="max-w-2xl space-y-3">
                        <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                            Dashboard
                        </p>

                        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
                            Vitaj v Clinvia administrácii.
                        </h1>
                    </div>
                </div>
            </div>

            <CompanyList
                v-if="canManageCompanies"
                :companies="companies"
                title="Moje firmy"
                description="Rýchly prehľad firiem, ku ktorým máš prístup."
                create-label="Onboardovať firmu"
                create-href="companies.onboard"
                :show-create-button="canCreateCompanies"
            />
        </div>
    </AdminLayout>
</template>