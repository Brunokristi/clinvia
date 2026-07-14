<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Tooltip from 'primevue/tooltip';

// Registers the directive locally so we can use `v-tooltip` in the template
// without relying on it being registered globally in app.js.
const vTooltip = Tooltip;

const page = usePage();

const userMenu = ref(null);

// Collapsed state for the whole sidebar, including the brand block above the
// nav. Everything in this component reacts to this one flag now.
const collapsed = ref(false);

const toggleCollapse = () => {
    collapsed.value = !collapsed.value;
};

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

const isSuperAdmin = computed(() => {
    return user.value?.global_role === 'super_admin';
});

const isBranchBookingEnabled = computed(() => {
    return Boolean(branch.value?.booking_settings?.is_enabled);
});

const makeMenuLink = (link) => {
    return {
        label: link.label,
        icon: link.icon,
        active: link.active,
        command: () => {
            router.visit(link.href);
        },
    };
};

const mainLinks = computed(() => [
    {
        label: 'Prehľad',
        icon: 'pi pi-th-large',
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
            label: 'Prehľad',
            icon: 'pi pi-th-large',
            href: route('branches.booking.dashboard.page', branch.value),
            active: route().current('branches.booking.dashboard.page'),
        },
        ...(isBranchBookingEnabled.value
            ? [{
                label: 'Rezervácie',
                icon: 'pi pi-calendar',
                href: route('branches.booking.agenda.page', branch.value),
                active: route().current('branches.booking.agenda.page')
                    || route().current('branches.booking.inbox.page'),
            }]
            : []),
        {
            label: 'Správy',
            icon: 'pi pi-inbox',
            href: route('branches.inbox.index', branch.value),
            active: route().current('branches.inbox.index')
                || route().current('branches.inbox.show'),
        },
        {
            label: 'Nastavenia',
            icon: 'pi pi-cog',
            href: route('branches.settings.page', branch.value),
            active: route().current('branches.settings.page')
                || route().current('branches.booking.settings.page'),
        },
    ];
});

// Single source of truth for both the expanded accordion and the collapsed
// icon groups: key, label, group icon, and the items inside that group.
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

// Which groups are expanded in the *expanded* sidebar. Defaults every group
// to open the first time it appears, but remembers manual toggles after
// that (e.g. if the user collapses "Hlavné" on purpose, switching branch
// shouldn't force it back open).
const expandedGroups = ref({});

watch(navigationItems, (groups) => {
    const next = { ...expandedGroups.value };

    groups.forEach((group) => {
        if (!(group.key in next)) {
            next[group.key] = true;
        }
    });

    expandedGroups.value = next;
}, { immediate: true });

const toggleGroup = (key) => {
    expandedGroups.value = {
        ...expandedGroups.value,
        [key]: !expandedGroups.value[key],
    };
};

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
    <aside
        class="flex h-full shrink-0 flex-col bg-accent transition-all duration-200"
        :class="collapsed ? 'w-20' : 'w-72'"
    >
        <!-- Brand block: lives in the sidebar so it collapses together with
             the nav. Uses py-4 to line up with the breadcrumb header's own
             py-4 in the layout. -->
        <Link
            :href="route('dashboard')"
            class="flex w-full shrink-0 items-center justify-center bg-dark py-4 shadow-[0_1px_0_0] shadow-dark/40"
            title="Dashboard"
        >
            <ApplicationLogo :class="collapsed ? 'h-8 w-auto' : 'h-10 w-auto'" />
        </Link>

        <div class="flex min-h-0 flex-1 flex-col p-4" :class="collapsed ? 'items-center' : ''">
            <div class="mb-3 flex w-full" :class="collapsed ? 'justify-center' : 'justify-end'">
                <Button
                    type="button"
                    text
                    rounded
                    v-tooltip.right="collapsed ? 'Rozbaliť menu' : 'Zbaliť menu'"
                    class="!h-9 !w-9 !bg-white/10 !text-white hover:!bg-white/20"
                    @click="toggleCollapse"
                >
                    <i :class="collapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" class="text-sm" />
                </Button>
            </div>

            <nav class="w-full flex-1 overflow-y-auto">
                <!-- Expanded: labelled, collapsible groups -->
                <div v-if="!collapsed" class="flex flex-col gap-1">
                    <div
                        v-for="group in navigationItems"
                        :key="group.key"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition hover:bg-white/10"
                            @click="toggleGroup(group.key)"
                        >
                            <i :class="group.icon" class="text-sm text-white/70" />

                            <span class="min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide text-white/70">
                                {{ group.label }}
                            </span>

                            <i
                                class="pi pi-chevron-down text-[10px] text-white/50 transition-transform duration-200"
                                :class="expandedGroups[group.key] ? 'rotate-180' : ''"
                            />
                        </button>

                        <div
                            class="grid transition-[grid-template-rows] duration-200 ease-out"
                            :style="{ gridTemplateRows: expandedGroups[group.key] ? '1fr' : '0fr' }"
                        >
                            <ul class="space-y-0.5 overflow-hidden pt-0.5">
                                <li
                                    v-for="item in group.items"
                                    :key="item.label"
                                >
                                    <button
                                        type="button"
                                        class="relative flex w-full items-center gap-3 rounded-lg py-2 pl-4 pr-3 text-left text-sm transition"
                                        :class="item.active
                                            ? 'bg-white/15 font-medium text-white before:absolute before:left-1 before:top-1/2 before:h-4 before:w-1 before:-translate-y-1/2 before:rounded-full before:bg-white'
                                            : 'text-white/80 hover:bg-white/10 hover:text-white'"
                                        @click="item.command"
                                    >
                                        <i :class="item.icon" class="text-sm" />

                                        <span class="truncate">
                                            {{ item.label }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Collapsed: icon-only, but still grouped. The group icon
                     is now the same toggle as the expanded header — clicking
                     it hides/shows that group's items, sharing the exact
                     same `expandedGroups` state as the expanded sidebar, so
                     a group collapsed in one view stays collapsed in the
                     other. -->
                <div v-else class="flex w-full flex-col items-center">
                    <div
                        v-for="(group, groupIndex) in navigationItems"
                        :key="group.key"
                        class="flex w-full flex-col items-center gap-1"
                        :class="groupIndex > 0 ? 'mt-2 border-t border-white/10 pt-2' : ''"
                    >
                        <button
                            type="button"
                            v-tooltip.right="group.label"
                            class="flex h-9 w-9 items-center justify-center rounded-full transition"
                            :class="expandedGroups[group.key]
                                ? 'bg-white/15 text-white'
                                : 'text-white/40 hover:bg-white/10 hover:text-white/70'"
                            @click="toggleGroup(group.key)"
                        >
                            <i :class="group.icon" class="text-sm" />
                        </button>

                        <div
                            class="grid w-full justify-items-center transition-[grid-template-rows] duration-200 ease-out"
                            :style="{ gridTemplateRows: expandedGroups[group.key] ? '1fr' : '0fr' }"
                        >
                            <div class="flex w-full flex-col items-center gap-1 overflow-hidden pt-1">
                                <button
                                    v-for="item in group.items"
                                    :key="item.label"
                                    type="button"
                                    v-tooltip.right="item.label"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white transition"
                                    :class="item.active ? 'bg-white/20' : 'hover:bg-white/10'"
                                    @click="item.command"
                                >
                                    <i :class="item.icon" class="text-lg" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>


            <div class="mt-4 w-full space-y-3">
                <Button
                    v-if="!collapsed"
                    type="button"
                    class="!flex !w-full !items-center !justify-start !gap-3 !rounded-md !border !border-white/10 !bg-dark !px-3 !py-3 !text-white hover:!bg-dark/90"
                    @click="toggleUserMenu"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white">
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

                <button
                    v-else
                    type="button"
                    v-tooltip.right="userName"
                    class="mx-auto flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white transition hover:bg-white/20"
                    @click="toggleUserMenu"
                >
                    {{ userInitials }}
                </button>

                <Menu
                    ref="userMenu"
                    :model="userMenuItems"
                    popup
                    unstyled
                >
                    <template #start>
                        <div class="w-72 overflow-hidden rounded-lg border border-soft bg-white shadow-lg">
                            <div class="border-b border-soft bg-soft/40 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-base font-semibold text-accent">
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
        </div>
    </aside>
</template>
