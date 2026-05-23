<script setup>
import AdminNavigation from '@/Components/Navigation/AdminNavbar.vue';
import { usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { nextTick, ref, watch } from 'vue';

const toast = useToast();
const page = usePage();
const lastFlashSuccess = ref('');

watch(
    () => page.props.flash?.success,
    (message) => {
        if (!message || message === lastFlashSuccess.value) {
            return;
        }

        lastFlashSuccess.value = message;

        nextTick(() => {
            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: message,
                life: 3000,
            });
        });
    },
    { immediate: true },
);
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