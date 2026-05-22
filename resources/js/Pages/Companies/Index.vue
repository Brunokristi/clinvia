<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyList from '@/Components/Companies/CompanyList.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    companies: Object,
});

const page = usePage();
const canCreateCompanies = computed(() => page.props.auth?.user?.global_role === 'super_admin');
</script>

<template>
    <AdminLayout>
        <CompanyList
            :companies="companies"
            description="Správa firiem v systéme."
            create-label="Pridať firmu"
            :show-create-button="canCreateCompanies"
        />
    </AdminLayout>
</template>