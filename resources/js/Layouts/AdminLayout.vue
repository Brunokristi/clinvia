<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const userMenuOpen = ref(false);

const user = computed(() => page.props.auth?.user);

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

const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin');

const canManageBranches = computed(() => {
    return ['super_admin', 'admin', 'editor'].includes(user.value?.global_role);
});

const closeUserMenu = () => {
    userMenuOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-slate-900">
        <aside class="fixed left-0 top-0 flex h-screen w-64 flex-col border-r border-gray-200 bg-white">
            <div class="flex h-full flex-col p-4">
                <div class="mb-8 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white">
                        C
                    </div>

                    <div>
                        <div class="text-lg font-bold tracking-tight">
                            Clinvia
                        </div>
                    </div>
                </div>

                <nav class="flex-1 space-y-1">
                    <Link
                        :href="route('dashboard')"
                        class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-slate-950"
                    >
                        Dashboard
                    </Link>

                    <Link
                        v-if="isSuperAdmin"
                        :href="route('users.index')"
                        class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-slate-950"
                    >
                        Používatelia
                    </Link>

                    <Link
                        v-if="isSuperAdmin"
                        :href="route('companies.index')"
                        class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-slate-950"
                    >
                        Firmy
                    </Link>

                    <Link
                        v-if="canManageBranches"
                        :href="route('branches.index')"
                        class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-slate-950"
                    >
                        Pobočky
                    </Link>

                    <Link
                        v-if="isSuperAdmin"
                        :href="route('api-clients.index')"
                        class="flex items-center rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 hover:text-slate-950"
                    >
                        API klienti
                    </Link>
                </nav>

                <div class="relative border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl p-2 text-left transition hover:bg-gray-100"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <img
                            v-if="user?.photo_url"
                            :src="user.photo_url"
                            :alt="userName"
                            class="h-10 w-10 rounded-xl object-cover"
                        >

                        <div
                            v-else
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white"
                        >
                            {{ userInitials }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-slate-900">
                                {{ userName }}
                            </div>

                            <div class="truncate text-xs capitalize text-gray-500">
                                {{ userRole }}
                            </div>
                        </div>

                        <i
                            class="pi pi-chevron-up text-[10px] text-gray-400 transition-transform duration-200"
                            :class="{ 'rotate-180': userMenuOpen }"
                        ></i>
                    </button>

                    <div
                        v-if="userMenuOpen"
                        class="absolute bottom-full left-0 z-50 mb-2 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg"
                    >
                        <Link
                            :href="route().has('profile.edit') ? route('profile.edit') : '#'"
                            class="block px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            Nastavenia účtu
                        </Link>

                        <Link
                            :href="route().has('logout') ? route('logout') : '#'"
                            method="post"
                            as="button"
                            class="block w-full px-4 py-3 text-left text-sm font-medium text-red-600 transition hover:bg-red-50"
                        >
                            Odhlásiť sa
                        </Link>
                    </div>
                </div>
            </div>
        </aside>

        <main class="ml-64 min-h-screen p-8">
            <slot />
        </main>
    </div>
</template>