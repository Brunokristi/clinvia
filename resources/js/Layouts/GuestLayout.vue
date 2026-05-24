<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="grid min-h-screen grid-cols-1 bg-gray-100 lg:grid-cols-2">
        <!-- Left side -->
        <div class="hidden bg-slate-900 lg:flex lg:flex-col lg:items-center lg:justify-center">
            <div class="max-w-md px-10 text-center">
                <Link href="/" class="inline-flex justify-center">
                    <ApplicationLogo class="h-24 w-24 fill-current text-white" />
                </Link>

                <h1 class="mt-8 text-3xl font-bold text-white">
                    Welcome to Clinvia
                </h1>

                <p class="mt-4 text-base leading-7 text-slate-300">
                    Manage your clinic, services, documents and client experience from one clean dashboard.
                </p>
            </div>
        </div>

        <!-- Right side -->
        <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <div class="mb-8 lg:hidden">
                <Link href="/">
                    <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white px-6 py-6 shadow-md">
                <slot />
            </div>
        </div>
    </div>
</template>