<script setup>
import AdminNavigation from '@/Components/Navigation/AdminNavbar.vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed, nextTick, ref, watch } from 'vue';

const toast = useToast();
const page = usePage();
const lastFlashSuccess = ref('');

const company = computed(() => page.props.company ?? page.props.branch?.company ?? null);
const branch = computed(() => page.props.branch ?? null);

const breadcrumbItem = (label, url = null) => ({
    label,
    ...(url ? { url } : {}),
});

const breadcrumbs = computed(() => {
    const items = [];

    if (route().current('profile.edit')) {
        items.push(breadcrumbItem('Hlavné', route('dashboard')));
        items.push(breadcrumbItem('Nastavenia'));

        return items;
    }

    if (route().current('dashboard') || route().current('companies.index') || route().current('companies.onboard')) {
        items.push(breadcrumbItem('Hlavné', route('dashboard')));

        if (route().current('dashboard')) {
            items.push(breadcrumbItem('Prehľad'));
        }

        if (route().current('companies.index')) {
            items.push(breadcrumbItem('Firmy'));
        }

        if (route().current('companies.onboard')) {
            items.push(breadcrumbItem('Prehľad', route('dashboard')));
            items.push(breadcrumbItem('Onboarding'));
        }

        return items;
    }

    if (company.value) {
        items.push(breadcrumbItem(company.value.legal_name, route('companies.edit', company.value)));

        if (route().current('companies.edit')) {
            items.push(breadcrumbItem('Základné informácie'));
        }

        if (route().current('companies.branches')) {
            items.push(breadcrumbItem('Pobočky'));
        }

        if (route().current('branches.create')) {
            items.push(breadcrumbItem('Pobočky', route('companies.branches', company.value)));
            items.push(breadcrumbItem('Nová pobočka'));
        }

        if (route().current('companies.api-clients')) {
            items.push(breadcrumbItem('API kľúče'));
        }

        if (route().current('companies.users.page')) {
            items.push(breadcrumbItem('Používatelia'));
        }
    }

    if (branch.value) {
        items.push(breadcrumbItem(branch.value.name, route('branches.edit', branch.value)));

        if (route().current('branches.booking.dashboard.page')) {
            items.push(breadcrumbItem('Prehľad'));
        }

        if (route().current('branches.booking.agenda.page') || route().current('branches.booking.inbox.page')) {
            items.push(breadcrumbItem('Rezervácie'));
        }

        if (route().current('branches.settings.page') || route().current('branches.booking.settings.page')) {
            items.push(breadcrumbItem('Nastavenia'));
        }

        if (
            route().current('branches.inbox.index')
            || route().current('branches.inbox.show')
        ) {
            items.push(breadcrumbItem('Správy', route('branches.inbox.index', branch.value)));

            if (route().current('branches.inbox.show')) {
                items.push(breadcrumbItem('Detail správy'));
            }
        }

        if (route().current('branches.edit')) {
            items.push(breadcrumbItem('Info'));
        }

        if (route().current('branches.public-site.edit')) {
            items.push(breadcrumbItem('Verejná stránka'));
        }

        if (route().current('branches.contacts.page')) {
            items.push(breadcrumbItem('Kontakty'));
        }

        if (route().current('branches.opening-hours.page')) {
            items.push(breadcrumbItem('Otváracie hodiny'));
        }

        if (route().current('branches.employees.page')) {
            items.push(breadcrumbItem('Zamestnanci'));
        }

        if (route().current('branches.services.page')) {
            items.push(breadcrumbItem('Služby'));
        }

        if (route().current('branches.users.page')) {
            items.push(breadcrumbItem('Používatelia'));
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
    return breadcrumbs.value[breadcrumbs.value.length - 1]?.label ?? 'Prehľad';
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
    <div class="flex h-screen flex-col overflow-hidden bg-white text-slate-900">
        <header class="z-10 flex w-full shrink-0 bg-accent">
            <Link
                :href="route('dashboard')"
                class="flex shrink-0 items-center justify-center w-72 bg-dark"
                title="Dashboard"
            >
                <ApplicationLogo
                    class="h-10 w-auto"
                />
            </Link>

            <div class="flex min-w-0 flex-1 items-center px-8 py-4 text-white">
                <Breadcrumb
                    v-if="breadcrumbs.length"
                    :model="breadcrumbs"
                />
            </div>
        </header>

        <div class="flex min-h-0 flex-1 overflow-hidden">
            <AdminNavigation />


            <main class="min-w-0 flex-1 overflow-y-auto">
                <div class="p-8">
                    <div class="pb-4">
                        <h1 class="text-heading font-semibold text-dark">
                            {{ pageTitle }}
                        </h1>
                    </div>

                    <slot />
                </div>
            </main>

        </div>

        <Toast />
    </div>
</template>