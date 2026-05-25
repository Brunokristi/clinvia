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
        <CompanyList
            v-if="canManageCompanies"
            :companies="companies"
            title="Firmy"
            description="Spravujte svoje firmy, pridávajte nové alebo upravujte existujúce."
            create-label="Onboardovať firmu"
            create-href="companies.onboard"
            :show-create-button="canCreateCompanies"
        />
    </AdminLayout>
</template>