<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchList from '@/Components/Branches/BranchList.vue';

defineProps({
    branches: Object,
});
</script>

<template>
    <AdminLayout>
        <BranchList
            :branches="branches"
            title="Pobočky"
            description="Spravujte svoje pobočky, otvárajte ich nastavenia alebo pridávajte nové."
            create-label="Vytvoriť pobočku"
        />
    </AdminLayout>
</template>