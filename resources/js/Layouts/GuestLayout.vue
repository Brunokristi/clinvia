<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="grid min-h-screen grid-cols-1 bg-white lg:grid-cols-2">
        <!-- Left side -->
        <div class="hidden bg-accent lg:flex lg:flex-col lg:items-center lg:justify-center">
            <div class="max-w-md px-10 text-center">
                <Link href="/" class="flex flex-col justify-center">
                    <ApplicationLogo class="h-80" />
                    <h1 class="font-heading text-white mt-20 text-4xl">
                        Clinvia
                    </h1>
                </Link>
            </div>
        </div>

        <!-- Right side -->
        <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
            <slot />
        </div>
    </div>
</template>