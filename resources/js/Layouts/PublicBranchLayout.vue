<script setup>
import PublicNavbar from '@/Pages/PublicBranch/Templates/default/Components/Navbar.vue';
import PublicFooter from '@/Pages/PublicBranch/Templates/default/Components/Footer.vue';

defineProps({
    branch: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="min-h-screen bg-white text-dark">
        <PublicNavbar :branch="branch" />

        <main class="flex-1 p-6 max-w-6xl mx-auto">
            <slot />
        </main>

        <PublicFooter :branch="branch" />
    </div>
</template>