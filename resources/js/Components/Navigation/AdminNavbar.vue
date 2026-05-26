<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PanelMenu from 'primevue/panelmenu';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';

const page = usePage();

const userMenu = ref(null);

const user = computed(() => page.props.auth?.user);
const branch = computed(() => page.props.branch ?? null);
const company = computed(() => page.props.company ?? branch.value?.company ?? null);

const companies = computed(() => {
    // Don't show the full companies list in the sidebar when creating a branch
    if (route().current('branches.create')) {
        return company.value ? [company.value] : [];
    }

    if (Array.isArray(page.props.companies) && page.props.companies.length) {
        return page.props.companies;
    }

    return company.value ? [company.value] : [];
});

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

const expandedMenuKeys = computed(() => {
    const keys = {
        main: true,
    };

    companies.value.forEach((companyItem) => {
        keys[`company-${companyItem.id}`] = true;
    });

    if (branch.value) {
        keys.branch = true;
    }

    return keys;
});

const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin');

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

    companies.value.forEach((companyItem) => {
        items.push({
            key: `company-${companyItem.id}`,
            label: companyItem.legal_name,
            icon: 'pi pi-building',
            items: companyLinks(companyItem).map(makeMenuLink),
        });
    });

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

const userMenuItems = computed(() => {
    const items = [
        {
            label: userName.value,
            items: [
                {
                    label: contextTitle.value,
                    disabled: true,
                },
                {
                    label: userRole.value,
                    disabled: true,
                },
            ],
        },
    ];

    const actionItems = [];

    if (route().has('profile.edit')) {
        actionItems.push({
            label: 'Nastavenia účtu',
            icon: 'pi pi-cog',
            command: () => {
                router.visit(route('profile.edit'));
            },
        });
    }

    if (route().has('logout')) {
        actionItems.push({
            label: 'Odhlásiť sa',
            icon: 'pi pi-sign-out',
            command: () => {
                router.post(route('logout'));
            },
        });
    }

    if (actionItems.length) {
        items.push({
            separator: true,
        });

        items.push(...actionItems);
    }

    return items;
});

const toggleUserMenu = (event) => {
    userMenu.value.toggle(event);
};
</script>

<template>
    <aside class="flex h-screen w-80 shrink-0 flex-col p-4 bg-accent">
        <div class="mb-4">
            <Link :href="route('dashboard')">
                <ApplicationLogo class="h-14" />
            </Link>
        </div>

        <nav class="flex-1 overflow-y-auto">
            <PanelMenu
                v-model:expandedKeys="expandedMenuKeys"
                :model="navigationItems"
                multiple
            >
                <template #submenuicon>
                    <span />
                </template>
            </PanelMenu>
        </nav>

        <div class="mt-4">
            <Button
                type="button"
                severity="secondary"
                class="!w-full !bg-dark justify-start"
                @click="toggleUserMenu"
            >

                <span class="ml-2 flex-1 text-left">
                    {{ userName }}
                </span>

                <i class="pi pi-chevron-up ml-2" />
            </Button>

            <Menu
                ref="userMenu"
                :model="userMenuItems"
                popup
            />
        </div>
    </aside>
</template>