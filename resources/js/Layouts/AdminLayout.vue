<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-slate-900">
        <aside class="fixed left-0 top-0 h-screen w-64 border-r bg-white p-4">
            <div class="mb-8 text-xl font-bold">
                Clinvia
            </div>

            <nav class="space-y-2">
                <Link
                    :href="route('dashboard')"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                >
                    Dashboard
                </Link>

                <Link
                    v-if="page.props.auth?.user?.global_role === 'super_admin'"
                    :href="route('users.index')"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                >
                    Používatelia
                </Link>

                <Link
                    v-if="page.props.auth?.user?.global_role === 'super_admin'"
                    :href="route('companies.index')"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                >
                    Firmy
                </Link>

                <Link
                    v-if="['super_admin', 'admin', 'editor'].includes(page.props.auth?.user?.global_role)"
                    :href="route('branches.index')"
                    class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                >
                    Pobočky
                </Link>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="block w-full text-left rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
                >
                    Odhlásiť sa
                </Link>
            </nav>
        </aside>

        <main class="ml-64 p-8">
            <slot />
        </main>
    </div>
</template>