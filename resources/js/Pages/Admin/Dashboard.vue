<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchList from '@/Components/Branches/BranchList.vue';
import CompanyList from '@/Components/Companies/CompanyList.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    companies: {
        type: Object,
        default: () => ({ data: [] }),
    },
    branches: {
        type: Object,
        default: () => ({ data: [] }),
    },
});

const page = usePage();

const userRole = computed(() => {
    return page.props.auth?.user?.global_role;
});

const showCompanies = computed(() => {
    return ['super_admin', 'admin'].includes(userRole.value);
});

const showBranches = computed(() => {
    return userRole.value === 'editor';
});

const canCreateCompanies = computed(() => {
    return userRole.value === 'super_admin';
});
</script>

<template>
    <AdminLayout>
        <CompanyList
            v-if="showCompanies"
            :companies="companies"
            title="Firmy"
            description="Spravujte svoje firmy, pridávajte nové alebo upravujte existujúce."
            create-label="Onboardovať firmu"
            create-href="companies.onboard"
            :show-create-button="canCreateCompanies"
        />

        <BranchList
            v-else-if="showBranches"
            :branches="branches"
            title="Pobočky"
            description="Spravujte svoje pobočky a otvárajte ich nastavenia."
        />
    </AdminLayout>
</template>