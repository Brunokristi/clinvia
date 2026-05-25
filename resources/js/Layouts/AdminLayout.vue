<script setup>
import AdminNavigation from '@/Components/Navigation/AdminNavbar.vue';
import { router, usePage } from '@inertiajs/vue3';
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, ref, watch } from 'vue';

const toast = useToast();
const page = usePage();
const lastFlashSuccess = ref('');

const company = computed(() => page.props.company ?? page.props.branch?.company ?? null);
const branch = computed(() => page.props.branch ?? null);

const breadcrumbs = computed(() => {
    const items = [];

    if (route().current('dashboard') || route().current('companies.index')) {
        items.push({
            label: 'Hlavné',
        });

        if (route().current('dashboard')) {
            items.push({
                label: 'Dashboard',
            });
        }

        if (route().current('companies.index')) {
            items.push({
                label: 'Firmy',
            });
        }

        return items;
    }

    if (company.value) {
        items.push({
            label: company.value.legal_name,
        });

        if (route().current('companies.edit')) {
            items.push({
                label: 'Základné informácie',
            });
        }

        if (route().current('companies.branches')) {
            items.push({
                label: 'Pobočky',
            });
        }

        if (route().current('companies.api-clients')) {
            items.push({
                label: 'API kľúče',
            });
        }

        if (route().current('companies.users.page')) {
            items.push({
                label: 'Používatelia',
            });
        }
    }

    if (branch.value) {
        items.push({
            label: branch.value.name,
        });

        if (route().current('branches.edit')) {
            items.push({
                label: 'Info',
            });
        }

        if (route().current('branches.contacts.page')) {
            items.push({
                label: 'Kontakty',
            });
        }

        if (route().current('branches.opening-hours.page')) {
            items.push({
                label: 'Otváracie hodiny',
            });
        }

        if (route().current('branches.employees.page')) {
            items.push({
                label: 'Zamestnanci',
            });
        }

        if (route().current('branches.services.page')) {
            items.push({
                label: 'Služby',
            });
        }

        if (route().current('branches.users.page')) {
            items.push({
                label: 'Používatelia',
            });
        }
    }

    return items;
});

const homeBreadcrumb = computed(() => ({
    icon: 'pi pi-home',
    command: () => {
        router.visit(route('dashboard'));
    },
}));

const pageTitle = computed(() => {
    return breadcrumbs.value[breadcrumbs.value.length - 1]?.label ?? 'Dashboard';
});

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
    <div class="flex h-screen overflow-hidden bg-white text-slate-900">
        <AdminNavigation />

        <Toast />

        <main class="min-w-0 flex-1 overflow-y-auto">
            <header class="bg-white px-8 py-2 sticky top-0 z-10 border-b border-accent">
                <Breadcrumb
                    v-if="breadcrumbs.length"
                    :home="homeBreadcrumb"
                    :model="breadcrumbs"
                />
            </header>
            <div class="p-8">
                <slot />
            </div>
        </main>
    </div>
</template>