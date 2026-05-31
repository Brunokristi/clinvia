<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    branch: {
        type: Object,
        required: true,
    },
    featuredServices: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head>
            <title>{{ branch.public_site?.meta_title || branch.name }}</title>

            <meta
                v-if="branch.public_site?.meta_description"
                name="description"
                :content="branch.public_site.meta_description"
            >
        </Head>

        <section class="bg-soft/40">
            <div class="mx-auto max-w-6xl px-6 py-20">
                <p class="text-normal font-semibold text-accent">
                    Vitajte
                </p>

                <h1 class="mt-4 max-w-3xl text-4xl font-semibold text-dark">
                    {{ branch.name }}
                </h1>

                <p class="mt-6 max-w-2xl text-normal leading-7 text-accent">
                    {{ branch.description || 'Profesionálna starostlivosť, jasné informácie a jednoduchý kontakt.' }}
                </p>

                <div class="mt-8 flex gap-3">
                    <Link
                        :href="route('public.branch.services', branch.slug)"
                        class="rounded-md bg-accent px-5 py-3 text-sm font-semibold text-white"
                    >
                        Pozrieť služby
                    </Link>

                    <Link
                        :href="route('public.branch.contact', branch.slug)"
                        class="rounded-md bg-white px-5 py-3 text-sm font-semibold text-accent"
                    >
                        Kontakt
                    </Link>
                </div>
            </div>
        </section>

        <section
            v-if="featuredServices.length"
            class="mx-auto max-w-6xl px-6 py-16"
        >
            <div class="mb-8">
                <h2 class="text-heading font-semibold text-dark">
                    Služby
                </h2>

                <p class="mt-2 text-normal text-accent">
                    Vybrané služby tejto pobočky.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Link
                    v-for="service in featuredServices"
                    :key="service.id"
                    :href="route('public.branch.services.show', [branch.slug, service.slug])"
                    class="rounded-md border border-soft bg-white p-5 transition hover:bg-soft/40"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
                            <i
                                v-if="service.icon"
                                :class="service.icon"
                            />

                            <span v-else>S</span>
                        </div>

                        <div>
                            <h3 class="text-normal font-semibold text-dark">
                                {{ service.name }}
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-accent">
                                {{ service.short_description }}
                            </p>
                        </div>
                    </div>
                </Link>
            </div>
        </section>
    </PublicBranchLayout>
</template>