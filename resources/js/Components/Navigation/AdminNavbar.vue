<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const userMenuOpen = ref(false);

const user = computed(() => page.props.auth?.user);
const branch = computed(() => page.props.branch ?? null);
const company = computed(() => page.props.company ?? branch.value?.company ?? null);

const userName = computed(() => {
    return user.value?.full_name
        || [user.value?.first_name, user.value?.last_name].filter(Boolean).join(' ')
        || 'Používateľ';
});

const userRole = computed(() => user.value?.global_role?.replace('_', ' ') ?? 'user');

const userInitials = computed(() => {
    return userName.value
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();
});

const contextTitle = computed(() => {
    if (branch.value) {
        return branch.value.name;
    }

    if (company.value) {
        return company.value.legal_name;
    }

    return 'Globálny prehľad';
});

const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin');

const globalLinks = computed(() => [
    {
        label: 'Dashboard',
        href: route('dashboard'),
        active: route().current('dashboard'),
        icon: 'pi pi-home',
    },
    {
        label: 'Správy',
        href: null,
        active: false,
        muted: true,
        icon: 'pi pi-comments',
    },
]);

const companyLinks = computed(() => {
    if (! company.value) {
        return [];
    }

    return [
        {
            label: 'Základné údaje',
            href: route('companies.edit', company.value),
            active: route().current('companies.edit'),
            icon: 'pi pi-info-circle',
        },
        {
            label: 'Pobočky',
            href: route('companies.branches', company.value),
            active: route().current('companies.branches'),
            icon: 'pi pi-map-marker',
        },
        ...(isSuperAdmin.value ? [
            {
                label: 'API kľúče',
                href: route('companies.api-clients', company.value),
                active: route().current('companies.api-clients'),
                icon: 'pi pi-key',
            },
        ] : []),
    ];
});

const branchLinks = computed(() => {
    if (! branch.value) {
        return [];
    }

    return [
        {
            label: 'Info',
            href: route('branches.edit', branch.value),
            active: route().current('branches.edit'),
            icon: 'pi pi-info-circle',
        },
        {
            label: 'Contacts',
            href: route('branches.contacts.page', branch.value),
            active: route().current('branches.contacts.page'),
            icon: 'pi pi-address-book',
        },
        {
            label: 'Opening hours',
            href: route('branches.opening-hours.page', branch.value),
            active: route().current('branches.opening-hours.page'),
            icon: 'pi pi-clock',
        },
        {
            label: 'Employees',
            href: route('branches.employees.page', branch.value),
            active: route().current('branches.employees.page'),
            icon: 'pi pi-user',
        },
        {
            label: 'Services',
            href: route('branches.services.page', branch.value),
            active: route().current('branches.services.page'),
            icon: 'pi pi-briefcase',
        },
        {
            label: 'Users',
            href: route('branches.users.page', branch.value),
            active: route().current('branches.users.page'),
            icon: 'pi pi-users',
        },
    ];
});

const closeUserMenu = () => {
    userMenuOpen.value = false;
};
</script>

<template>
    <aside class="flex h-screen w-80 shrink-0 flex-col border-r border-slate-200 bg-white/95 px-4 py-5 text-slate-700 shadow-[0_24px_60px_-30px_rgba(15,23,42,0.35)] backdrop-blur">
        <div class="flex h-full flex-col">
            <Link :href="route('dashboard')" class="mb-4 flex items-center gap-3 rounded-2xl p-2 transition hover:bg-slate-50">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">
                    C
                </div>

                <div class="min-w-0">
                    <div class="truncate text-lg font-bold tracking-tight text-slate-900">
                        Clinvia
                    </div>

                    <div class="text-xs text-slate-500">
                        Administrácia
                    </div>
                </div>
            </Link>

            <nav class="flex-1 space-y-4 overflow-y-auto border-t border-slate-200 pr-1 pt-4">
                <div>
                    <div class="space-y-1">
                        <template v-for="link in globalLinks" :key="link.label">
                            <Link v-if="link.href" :href="link.href" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition" :class="link.active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
                                <i :class="link.icon" class="text-sm" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>

                            <div v-else class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300">
                                <i :class="link.icon" class="text-sm" />
                                <span class="truncate">{{ link.label }}</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div v-if="company" class="border-t border-slate-200 pt-4">
                    <div class="mb-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                            Firma
                        </p>

                        <p class="mt-1 truncate text-sm font-semibold text-slate-900">
                            {{ company.legal_name }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <template v-for="link in companyLinks" :key="link.label">
                            <Link :href="link.href" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition" :class="link.active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
                                <i :class="link.icon" class="text-sm" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>
                        </template>
                    </div>
                </div>

                <div v-if="branch" class="border-t border-slate-200 pt-4">
                    <div class="mb-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                            Pobočka
                        </p>

                        <p class="mt-1 truncate text-sm font-semibold text-slate-900">
                            {{ branch.name }}
                        </p>

                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ company?.legal_name ?? 'Firma' }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <template v-for="link in branchLinks" :key="`branch-${link.label}`">
                            <Link :href="link.href" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition" :class="link.active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
                                <i :class="link.icon" class="text-sm" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>
                        </template>
                    </div>
                </div>
            </nav>

            <div class="relative border-t border-slate-200 pt-4">
                <button class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 px-3 py-2 text-left transition hover:border-slate-300 hover:bg-slate-50" @click="userMenuOpen = !userMenuOpen">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                        {{ userInitials }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-slate-900">
                            {{ userName }}
                        </div>

                        <div class="truncate text-xs text-slate-500">
                            {{ userRole }}
                        </div>
                    </div>

                    <i class="pi pi-chevron-up text-[10px] text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': userMenuOpen }" />
                </button>

                <div v-if="userMenuOpen" class="absolute bottom-full left-0 z-50 mb-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ userName }}
                        </div>

                        <div class="text-xs text-slate-500">
                            {{ contextTitle }}
                        </div>
                    </div>

                    <Link :href="route().has('profile.edit') ? route('profile.edit') : '#'" class="block px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="closeUserMenu">
                        Nastavenia účtu
                    </Link>

                    <Link :href="route().has('logout') ? route('logout') : '#'" method="post" as="button" class="block w-full px-4 py-3 text-left text-sm font-medium text-red-600 transition hover:bg-red-50" @click="closeUserMenu">
                        Odhlásiť sa
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>