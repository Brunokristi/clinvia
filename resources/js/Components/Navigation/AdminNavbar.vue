<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const mobileMenuOpen = ref(false);
const userMenuOpen = ref(false);

const user = computed(
    () => page.props.auth?.user ?? null
);

const branch = computed(
    () => page.props.branch ?? null
);

const managedCompanies = computed(
    () => Array.isArray(page.props.managedCompanies)
        ? page.props.managedCompanies
        : []
);

const currentRouteCompanyParam = computed(
    () => {
        const value =
            route().params?.company;

        if (
            value === undefined ||
            value === null ||
            value === ''
        ) {
            return null;
        }

        return String(value);
    }
);

const currentRouteCompany = computed(
    () => {
        if (
            !currentRouteCompanyParam.value
        ) {
            return null;
        }

        return managedCompanies.value.find(
            (companyItem) =>
                String(companyItem.id) ===
                    currentRouteCompanyParam.value ||
                companyItem.slug ===
                    currentRouteCompanyParam.value
        ) ?? null;
    }
);

const company = computed(
    () =>
        page.props.company ??
        branch.value?.company ??
        currentRouteCompany.value ??
        null
);

const globalRole = computed(
    () =>
        user.value?.global_role ??
        null
);

const isSuperAdmin = computed(
    () =>
        globalRole.value === 'super_admin'
);

const canSeeCompanyLinks = computed(
    () =>
        ['super_admin', 'admin'].includes(
            globalRole.value
        ) &&
        Boolean(company.value?.id)
);

const isBranchBookingEnabled = computed(
    () =>
        Boolean(
            branch.value
                ?.booking_settings
                ?.is_enabled
        )
);

const userName = computed(
    () =>
        user.value?.full_name ||
        [
            user.value?.first_name,
            user.value?.last_name,
        ]
            .filter(Boolean)
            .join(' ') ||
        'Používateľ'
);

const userRole = computed(
    () =>
        String(
            globalRole.value ?? 'user'
        ).replace(
            '_',
            ' '
        )
);

const userInitials = computed(
    () =>
        userName.value
            .split(/\s+/)
            .filter(Boolean)
            .map(
                (part) =>
                    part.charAt(0)
            )
            .join('')
            .slice(0, 2)
            .toUpperCase()
);

const contextTitle = computed(
    () => {
        if (
            ['super_admin', 'admin'].includes(
                globalRole.value
            ) &&
            company.value
        ) {
            return company.value.legal_name;
        }

        if (branch.value) {
            return branch.value.name;
        }

        if (company.value) {
            return company.value.legal_name;
        }

        return 'Globálny prehľad';
    }
);

const mainLinks = computed(
    () => [
        {
            label: 'Prehľad',
            href: route('dashboard'),
            active:
                route().current(
                    'dashboard'
                ),
        },
    ]
);

const companyLinks = computed(
    () => {
        if (
            !canSeeCompanyLinks.value
        ) {
            return [];
        }

        const companyItem =
            company.value;

        return [
            {
                label: 'Základné informácie',
                href:
                    route(
                        'companies.edit',
                        companyItem
                    ),
                active:
                    route().current(
                        'companies.edit'
                    ),
            },
            {
                label: 'Pobočky',
                href:
                    route(
                        'companies.branches',
                        companyItem
                    ),
                active:
                    route().current(
                        'companies.branches'
                    ),
            },
            ...(isSuperAdmin.value
                ? [
                    {
                        label: 'API kľúče',
                        href:
                            route(
                                'companies.api-clients',
                                companyItem
                            ),
                        active:
                            route().current(
                                'companies.api-clients'
                            ),
                    },
                ]
                : []),
            {
                label: 'Používatelia',
                href:
                    route(
                        'companies.users.page',
                        companyItem
                    ),
                active:
                    route().current(
                        'companies.users.page'
                    ),
            },
        ];
    }
);

const branchLinks = computed(
    () => {
        if (!branch.value) {
            return [];
        }

        return [
            {
                label: 'Prehľad',
                href:
                    route(
                        'branches.booking.dashboard.page',
                        branch.value
                    ),
                active:
                    route().current(
                        'branches.booking.dashboard.page'
                    ),
            },
            ...(isBranchBookingEnabled.value
                ? [
                    {
                        label: 'Rezervácie',
                        href:
                            route(
                                'branches.booking.agenda.page',
                                branch.value
                            ),
                        active:
                            route().current(
                                'branches.booking.agenda.page'
                            ) ||
                            route().current(
                                'branches.booking.inbox.page'
                            ),
                    },
                ]
                : []),
            {
                label: 'Správy',
                href:
                    route(
                        'branches.inbox.index',
                        branch.value
                    ),
                active:
                    route().current(
                        'branches.inbox.index'
                    ) ||
                    route().current(
                        'branches.inbox.show'
                    ),
            },
            {
                label: 'Nastavenia',
                href:
                    route(
                        'branches.settings.page',
                        branch.value
                    ),
                active:
                    route().current(
                        'branches.settings.page'
                    ) ||
                    route().current(
                        'branches.booking.settings.page'
                    ),
            },
        ];
    }
);

const navigationGroups = computed(
    () => {
        const groups = [
            {
                key: 'general',
                context: 'Všeobecné',
                links:
                    mainLinks.value,
            },
        ];

        if (
            companyLinks.value.length
        ) {
            groups.push({
                key: 'company',
                label: 'Spoločnosť',
                context:
                    company.value
                        ?.legal_name ?? '',
                links:
                    companyLinks.value,
            });
        }

        if (
            branchLinks.value.length
        ) {
            groups.push({
                key: 'branch',
                label: 'Pobočka',
                context:
                    branch.value
                        ?.name ?? '',
                links:
                    branchLinks.value,
            });
        }

        return groups;
    }
);

const navigate = (href) => {
    closeMobileMenu();
    closeUserMenu();

    router.visit(href);
};

const toggleMobileMenu = () => {
    mobileMenuOpen.value =
        !mobileMenuOpen.value;

    if (
        mobileMenuOpen.value
    ) {
        userMenuOpen.value =
            false;
    }
};

const closeMobileMenu = () => {
    mobileMenuOpen.value =
        false;
};

const toggleUserMenu = () => {
    userMenuOpen.value =
        !userMenuOpen.value;

    if (
        userMenuOpen.value
    ) {
        mobileMenuOpen.value =
            false;
    }
};

const closeUserMenu = () => {
    userMenuOpen.value =
        false;
};

const goToProfile = () => {
    closeUserMenu();
    closeMobileMenu();

    if (
        route().has(
            'profile.edit'
        )
    ) {
        router.visit(
            route(
                'profile.edit'
            )
        );
    }
};

const logout = () => {
    closeUserMenu();
    closeMobileMenu();

    if (
        route().has('logout')
    ) {
        router.post(
            route('logout')
        );
    }
};

const handleDocumentClick = (
    event
) => {
    const target =
        event.target;

    if (
        !target.closest(
            '[data-user-menu]'
        )
    ) {
        closeUserMenu();
    }
};

watch(
    () =>
        route().current(),
    () => {
        closeMobileMenu();
        closeUserMenu();
    }
);

if (
    typeof document !== 'undefined'
) {
    document.addEventListener(
        'click',
        handleDocumentClick
    );
}
</script>

<template>
    <header
        class="
            fixed
            inset-x-0
            top-0
            z-50
            flex
            h-14
            items-center
            bg-accent
            px-4
            text-white

        "
    >
        <div
            class="
                flex
                min-w-0
                flex-1
                items-center
                gap-4
            "
        >

            <Link
                :href="
                    route('dashboard')
                "
                class="
                    flex
                    shrink-0
                    items-center
                    transition-opacity
                    hover:opacity-70
                "
                title="Dashboard"
            >
                <ApplicationLogo
                    class="
                        h-9
                        w-auto
                        
                    "
                />
            </Link>

            <button
                type="button"
                class="
                    grid
                    h-9
                    w-9
                    shrink-0
                    place-items-center
                    rounded-md
                    text-white
                    transition
                    active:scale-95
                    lg:hidden
                "
                :aria-expanded="
                    mobileMenuOpen
                "
                aria-controls="
                    admin-navigation
                "
                :aria-label="
                    mobileMenuOpen
                        ? 'Zavrieť menu'
                        : 'Otvoriť menu'
                "
                @click="
                    toggleMobileMenu
                "
            >
                <span
                    class="
                        relative
                        block
                        h-4
                        w-5
                    "
                >
                    <span
                        class="
                            absolute
                            left-0
                            top-1/2
                            h-px
                            w-5
                            -translate-y-1/2
                            bg-current
                            transition-transform
                            duration-200
                        "
                        :class="
                            mobileMenuOpen
                                ? 'rotate-45'
                                : '-translate-y-[3px]'
                        "
                    ></span>

                    <span
                        class="
                            absolute
                            left-0
                            top-1/2
                            h-px
                            w-5
                            -translate-y-1/2
                            bg-current
                            transition-transform
                            duration-200
                        "
                        :class="
                            mobileMenuOpen
                                ? '-rotate-45'
                                : 'translate-y-[3px]'
                        "
                    ></span>
                </span>
            </button>

            <div
                v-if="
                    props.breadcrumbs.length
                "
                class="
                    hidden
                    min-w-0
                    items-end
                    lg:flex
                "
            >
                <nav
                    class="
                        flex
                        min-w-0
                        items-end
                        gap-2
                        text-sm
                    "
                    aria-label="
                        Breadcrumb
                    "
                >
                    <template
                        v-for="
                            (
                                item,
                                index
                            ) in props.breadcrumbs
                        "
                        :key="
                            `${item.label}-${index}`
                        "
                    >
                        <Link
                            v-if="
                                item.url
                            "
                            :href="
                                item.url
                            "
                            class="
                                max-w-[18rem]
                                truncate
                                text-white
                                transition
                                hover:text-white
                            "
                        >
                            {{
                                item.label
                            }}
                        </Link>

                        <span
                            v-else
                            class="
                                max-w-[18rem]
                                truncate
                                font-medium
                                text-white
                            "
                            aria-current="
                                page
                            "
                        >
                            {{
                                item.label
                            }}
                        </span>

                        <span
                            v-if="
                                index <
                                props.breadcrumbs.length -
                                    1
                            "
                            class="
                                text-white
                            "
                            aria-hidden="
                                true
                            "
                        >
                            /
                        </span>
                    </template>
                </nav>
            </div>
        </div>

        <div
            class="
                relative
                shrink-0
            "
            data-user-menu
        >
            <button
                type="button"
                class="
                    flex
                    items-center
                    gap-2
                    rounded-md
                    px-2
                    py-1.5
                    text-left
                    transition
                    hover:bg-dark
                    active:scale-[0.99]
                "
                :aria-expanded="
                    userMenuOpen
                "
                aria-haspopup="menu"
                @click.stop="
                    toggleUserMenu
                "
            >
                <span
                    class="
                        flex
                        h-8
                        w-8
                        shrink-0
                        items-center
                        justify-center
                        rounded-full
                        bg-white
                        text-xs
                        font-semibold
                        text-accent
                    "
                >
                    {{
                        userInitials
                    }}
                </span>

                <span
                    class="
                        hidden
                        max-w-48
                        flex-col
                        sm:flex
                    "
                >
                    <span
                        class="
                            truncate
                            text-xs
                            font-semibold
                            text-white
                        "
                    >
                        {{
                            userName
                        }}
                    </span>

                    <span
                        class="
                            truncate
                            text-[10px]
                            uppercase
                            tracking-wide
                            text-white
                        "
                    >
                        {{
                            contextTitle
                        }}
                    </span>
                </span>
            </button>

            <Transition
                enter-active-class="
                    transition
                    duration-150
                    ease-out
                "
                enter-from-class="
                    translate-y-1
                    opacity-0
                "
                enter-to-class="
                    translate-y-0
                    opacity-100
                "
                leave-active-class="
                    transition
                    duration-100
                    ease-in
                "
                leave-from-class="
                    translate-y-0
                    opacity-100
                "
                leave-to-class="
                    translate-y-1
                    opacity-0
                "
            >
                <div
                    v-if="
                        userMenuOpen
                    "
                    class="
                        absolute
                        right-0
                        top-[calc(100%+0.5rem)]
                        w-64
                        overflow-hidden
                        rounded-md
                        border
                        border-soft
                        bg-white
                        shadow-xl
                    "
                    role="menu"
                >
                    <div
                        class="
                            bg-soft
                            px-3
                            py-3
                        "
                    >
                        <p
                            class="
                                truncate
                                text-sm
                                font-semibold
                                text-accent
                            "
                        >
                            {{
                                userName
                            }}
                        </p>

                        <p
                            class="
                                mt-0.5
                                truncate
                                text-xs
                                font-medium
                                text-dark
                            "
                        >
                            {{
                                userRole
                            }}
                        </p>
                    </div>

                    <div
                        class="
                            p-1.5
                        "
                    >
                        <button
                            v-if="
                                user &&
                                route().has(
                                    'profile.edit'
                                )
                            "
                            type="button"
                            class="
                                block
                                w-full
                                rounded-md
                                px-3
                                py-2.5
                                text-left
                                text-sm
                                font-medium
                                text-dark
                                transition
                                hover:bg-soft
                                hover:text-accent
                            "
                            role="menuitem"
                            @click="
                                goToProfile
                            "
                        >
                            Nastavenia
                        </button>

                        <button
                            v-if="
                                route().has(
                                    'logout'
                                )
                            "
                            type="button"
                            class="
                                block
                                w-full
                                rounded-md
                                px-3
                                py-2.5
                                text-left
                                text-sm
                                font-medium
                                text-dark
                                transition
                                hover:bg-soft
                                hover:text-accent
                            "
                            role="menuitem"
                            @click="
                                logout
                            "
                        >
                            Odhlásiť sa
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </header>

    <button
        v-if="
            mobileMenuOpen
        "
        type="button"
        class="
            fixed
            inset-0
            z-40
            bg-dark/10
            backdrop-blur-[2px]
            lg:hidden
        "
        aria-label="
            Zavrieť navigáciu
        "
        @click="
            closeMobileMenu
        "
    ></button>

    <aside
        id="admin-navigation"
        class="
            fixed
            bottom-0
            left-0
            top-14
            z-40
            flex
            w-[min(86vw,280px)]
            flex-col
            bg-soft
            text-dark
            shadow-xl
            transition-transform
            duration-200
            ease-out
            lg:w-[250px]
            lg:translate-x-0
            lg:shadow-none
        "
        :class="
            mobileMenuOpen
                ? 'translate-x-0'
                : '-translate-x-full'
        "
    >
        <div
            class="
                flex
                min-h-0
                flex-1
                flex-col
            "
        >
            <nav
                class="
                    min-h-0
                    flex-1
                    overflow-y-auto
                    px-3
                    py-5
                "
                aria-label="
                    Hlavná navigácia
                "
            >
                <div
                    class="
                        space-y-6
                    "
                >
                    <section
                        v-for="
                            group in navigationGroups
                        "
                        :key="
                            group.key
                        "
                        class="
                            rounded-lg
                            p-2
                        "
                    >
                        <div
                            class="
                                mb-1
                                rounded-md
                                bg-accent
                                px-2.5
                                py-2
                            "
                        >
                            <p
                                v-if="
                                    group.context
                                "
                                class="
                                    mt-0.5
                                    truncate
                                    text-sm
                                    font-semibold
                                    text-white
                                "
                            >
                                {{
                                    group.context
                                }}
                            </p>
                        </div>

                        <div
                            class="
                                space-y-0.5
                            "
                        >
                            <button
                                v-for="
                                    item in group.links
                                "
                                :key="
                                    item.label
                                "
                                type="button"
                                class="
                                    group
                                    relative
                                    block
                                    w-full
                                    rounded-md
                                    px-3
                                    py-2.5
                                    text-left
                                    text-sm
                                    transition
                                    duration-150
                                "
                                :class="
                                    item.active
                                        ? 'bg-soft font-semibold text-accent'
                                        : 'text-dark hover:bg-soft hover:text-accent'
                                "
                                @click="
                                    navigate(
                                        item.href
                                    )
                                "
                            >
                               <span
                                    class="
                                        block
                                        truncate
                                    "
                                >
                                    {{
                                        item.label
                                    }}
                                </span>
                            </button>
                        </div>
                    </section>
                </div>
            </nav>

        </div>
    </aside>
</template>
