<script setup>
import AdminNavigation from '@/Components/Navigation/AdminNavbar.vue';
import Toast from 'primevue/toast';
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-50 text-slate-900">
        <AdminNavigation />

        <Toast />

        <main class="min-w-0 flex-1 overflow-y-auto p-8">
            <slot />
        </main>
    </div>
</template>