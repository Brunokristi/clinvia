<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchList from '@/Components/Branches/BranchList.vue';

defineProps({
    company: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AdminLayout>
        <BranchList
            :company="company"
            :branches="branches"
            title="Zoznam pobočiek"
            :description="company.legal_name"
            empty-message="Táto firma zatiaľ nemá žiadne pobočky."
            show-create-button
            create-label="Pridať pobočku"
        />
    </AdminLayout>
</template>