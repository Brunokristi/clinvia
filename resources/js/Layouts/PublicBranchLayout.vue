<script setup>
import PublicNavbar from '@/Pages/PublicBranch/Templates/default/Components/Navbar.vue';
import PublicFooter from '@/Pages/PublicBranch/Templates/default/Components/Footer.vue';
import Toast from 'primevue/toast';

defineProps({
    branch: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="min-h-screen bg-white text-dark">
        <Toast />
        <PublicNavbar :branch="branch" />

        <main class="flex-1 p-6 max-w-6xl mx-auto min-h-[500px]">
            <slot />
        </main>

        <PublicFooter :branch="branch" />
    </div>
</template>