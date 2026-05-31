<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PanelMenu from 'primevue/panelmenu';
import Menu from 'primevue/menu';
import Button from 'primevue/button';

const page = usePage();

const userMenu = ref(null);

const user = computed(() => page.props.auth?.user);
const branch = computed(() => page.props.branch ?? null);
const managedCompanies = computed(() => Array.isArray(page.props.managedCompanies) ? page.props.managedCompanies : []);

const currentRouteCompanyParam = computed(() => {
    const routeCompany = route().params?.company;

    if (routeCompany === undefined || routeCompany === null || routeCompany === '') {
        return null;
    }

    return String(routeCompany);
});

const currentRouteCompany = computed(() => {
    if (!currentRouteCompanyParam.value) {
        return null;
    }

    return managedCompanies.value.find((companyItem) => {
        return String(companyItem.id) === currentRouteCompanyParam.value
            || companyItem.slug === currentRouteCompanyParam.value;
    }) ?? null;
});

const company = computed(() => {
    return page.props.company
        ?? branch.value?.company
        ?? currentRouteCompany.value
        ?? null;
});

const canSeeCompanyLinks = computed(() => {
    return ['super_admin', 'admin'].includes(user.value?.global_role)
        && Boolean(company.value?.id);
});

const activeCompany = computed(() => {
    if (!canSeeCompanyLinks.value) {
        return null;
    }

    return {
        id: company.value.id,
        legal_name: company.value.legal_name,
        slug: company.value.slug,
    };
});

const userName = computed(() => {
    return user.value?.full_name
        || [user.value?.first_name, user.value?.last_name].filter(Boolean).join(' ')
        || 'Používateľ';
});

const userRole = computed(() => {
    return user.value?.global_role?.replace('_', ' ') ?? 'user';
});

const userInitials = computed(() => {
    return userName.value
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();
});

const contextTitle = computed(() => {
    if (['super_admin', 'admin'].includes(user.value?.global_role) && company.value) {
        return company.value.legal_name;
    }

    if (branch.value) {
        return branch.value.name;
    }

    if (company.value) {
        return company.value.legal_name;
    }

    return 'Globálny prehľad';
});

const expandedMenuKeys = computed(() => {
    const keys = {
        main: true,
    };

    if (activeCompany.value?.id) {
        keys[`company-${activeCompany.value.id}`] = true;
    }

    if (branch.value) {
        keys.branch = true;
    }

    return keys;
});

const isSuperAdmin = computed(() => {
    return user.value?.global_role === 'super_admin';
});

const makeMenuLink = (link) => {
    return {
        label: link.label,
        icon: link.icon,
        class: link.active ? 'p-focus' : '',
        command: () => {
            router.visit(link.href);
        },
    };
};

const mainLinks = computed(() => [
    {
        label: 'Dashboard',
        icon: 'pi pi-home',
        href: route('dashboard'),
        active: route().current('dashboard'),
    },
]);

const companyLinks = (companyItem) => [
    {
        label: 'Základné informácie',
        icon: 'pi pi-info-circle',
        href: route('companies.edit', companyItem),
        active: route().current('companies.edit'),
    },
    {
        label: 'Pobočky',
        icon: 'pi pi-sitemap',
        href: route('companies.branches', companyItem),
        active: route().current('companies.branches'),
    },
    ...(isSuperAdmin.value ? [
        {
            label: 'API kľúče',
            icon: 'pi pi-key',
            href: route('companies.api-clients', companyItem),
            active: route().current('companies.api-clients'),
        },
    ] : []),
    {
        label: 'Používatelia',
        icon: 'pi pi-users',
        href: route('companies.users.page', companyItem),
        active: route().current('companies.users.page'),
    },
];

const branchLinks = computed(() => {
    if (!branch.value) {
        return [];
    }

    return [
        {
            label: 'Info',
            icon: 'pi pi-info-circle',
            href: route('branches.edit', branch.value),
            active: route().current('branches.edit'),
        },
        {
            label: 'Kontakty',
            icon: 'pi pi-address-book',
            href: route('branches.contacts.page', branch.value),
            active: route().current('branches.contacts.page'),
        },
        {
            label: 'Otváracie hodiny',
            icon: 'pi pi-clock',
            href: route('branches.opening-hours.page', branch.value),
            active: route().current('branches.opening-hours.page'),
        },
        {
            label: 'Zamestnanci',
            icon: 'pi pi-id-card',
            href: route('branches.employees.page', branch.value),
            active: route().current('branches.employees.page'),
        },
        {
            label: 'Služby',
            icon: 'pi pi-briefcase',
            href: route('branches.services.page', branch.value),
            active: route().current('branches.services.page'),
        },
        {
            label: 'Používatelia',
            icon: 'pi pi-users',
            href: route('branches.users.page', branch.value),
            active: route().current('branches.users.page'),
        },
        {
            label: 'Verejná stránka',
            icon: 'pi pi-globe',
            href: route('branches.public-site.edit', branch.value),
            active: route().current('branches.public-site.edit'),
        },
    ];
});

const navigationItems = computed(() => {
    const items = [
        {
            key: 'main',
            label: 'Hlavné',
            icon: 'pi pi-folder',
            items: mainLinks.value.map(makeMenuLink),
        },
    ];

    if (activeCompany.value) {
        const companyItem = activeCompany.value;

        items.push({
            key: `company-${companyItem.id}`,
            label: companyItem.legal_name,
            icon: 'pi pi-building',
            items: companyLinks(companyItem).map(makeMenuLink),
        });
    }

    if (branch.value) {
        items.push({
            key: 'branch',
            label: branch.value.name,
            icon: 'pi pi-map-marker',
            items: branchLinks.value.map(makeMenuLink),
        });
    }

    return items;
});

const panelMenuKey = computed(() => {
    const companyKey = activeCompany.value?.id ? `company-${activeCompany.value.id}` : 'company-none';
    const branchKey = branch.value?.id ? `branch-${branch.value.id}` : 'branch-none';

    return `${companyKey}|${branchKey}`;
});

const profileDialogVisible = ref(false);


const userMenuItems = computed(() => {
    const items = [];

    if (user.value && route().has('profile.edit')) {
        items.push({
            label: 'Nastavenia',
            icon: 'pi pi-cog',
            command: () => {
                router.visit(route('profile.edit'));
            },
        });
    }

    if (route().has('logout')) {
        items.push({
            label: 'Odhlásiť sa',
            icon: 'pi pi-sign-out',
            command: () => {
                router.post(route('logout'));
            },
        });
    }

    return items;
});

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};
</script>

<template>
    <aside class="flex h-screen w-80 shrink-0 flex-col bg-accent p-4">
        <div class="mb-4 flex items-center justify-center">
            <Link :href="route('dashboard')">
                <ApplicationLogo class="h-8" type="textual" />
            </Link>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <PanelMenu
                :key="panelMenuKey"
                v-model:expandedKeys="expandedMenuKeys"
                :model="navigationItems"
                multiple
            >
                <template #submenuicon>
                    <span />
                </template>
            </PanelMenu>
        </nav>

        <div class="mt-4 space-y-3">
            <Button
                type="button"
                class="!flex !w-full !items-center !justify-start !gap-3 !rounded-md !border !border-white/10 !bg-dark !px-3 !py-3 !text-white hover:!bg-dark/90"
                @click="toggleUserMenu"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white/10 text-sm font-semibold text-white">
                    {{ userInitials }}
                </span>

                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold text-white">
                        {{ userName }}
                    </span>

                    <span class="block truncate text-xs text-white/60">
                        {{ contextTitle }}
                    </span>
                </span>

                <i class="pi pi-chevron-up text-xs text-white" />
            </Button>

            <Menu
                ref="userMenu"
                :model="userMenuItems"
                popup
                unstyled
            >
                <template #start>
                    <div class="w-72 overflow-hidden rounded-md border border-soft bg-white shadow-lg">
                        <div class="border-b border-soft bg-soft/40 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-white text-base font-semibold text-accent">
                                    {{ userInitials }}
                                </div>

                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-dark">
                                        {{ userName }}
                                    </p>

                                    <p class="mt-0.5 truncate text-xs text-accent">
                                        {{ userRole }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-2">
                            <template
                                v-for="item in userMenuItems"
                                :key="item.label"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-md px-3 py-3 text-left text-sm font-medium transition"
                                    :class="item.danger
                                        ? 'text-red-500 hover:bg-red-50'
                                        : 'text-accent hover:bg-soft'"
                                    @click="item.command"
                                >
                                    <i
                                        :class="item.icon"
                                        class="text-sm"
                                    />

                                    <span>
                                        {{ item.label }}
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <template #item>
                    <span />
                </template>
            </Menu>
        </div>
    </aside>
</template>