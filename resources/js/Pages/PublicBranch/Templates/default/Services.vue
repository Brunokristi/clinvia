<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    services: {
        type: Array,
        default: () => [],
    },
});

const groupedServices = computed(() => {
    return props.services.reduce((groups, service) => {
        const categoryName = service.category?.name ?? 'Ostatné';

        if (!groups[categoryName]) {
            groups[categoryName] = [];
        }

        groups[categoryName].push(service);

        return groups;
    }, {});
});

const durationLabel = (service) => {
    if (!service.duration_minutes) {
        return null;
    }

    const sessions = service.duration_sessions || 1;

    return `${sessions} × ${service.duration_minutes} min`;
};
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Služby | ${branch.name}`" />

        <section class="bg-soft/40">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <p class="text-normal font-semibold text-accent">
                    Služby
                </p>

                <h1 class="mt-4 text-4xl font-semibold text-dark">
                    Služby pobočky {{ branch.name }}
                </h1>

                <p class="mt-6 max-w-2xl text-normal leading-7 text-accent">
                    Prehľad služieb, ktoré sú dostupné v tejto pobočke.
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 py-16">
            <div
                v-if="services.length"
                class="space-y-12"
            >
                <div
                    v-for="(categoryServices, categoryName) in groupedServices"
                    :key="categoryName"
                    class="space-y-5"
                >
                    <div>
                        <h2 class="text-heading font-semibold text-dark">
                            {{ categoryName }}
                        </h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <Link
                            v-for="service in categoryServices"
                            :key="service.id"
                            :href="route('public.branch.services.show', [branch.slug, service.slug])"
                            class="rounded-md border border-soft bg-white p-5 transition hover:bg-soft/40"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
                                    <i
                                        v-if="service.icon"
                                        :class="service.icon"
                                    />

                                    <span
                                        v-else
                                        class="font-semibold"
                                    >
                                        S
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="text-normal font-semibold text-dark">
                                        {{ service.name }}
                                    </h3>

                                    <p
                                        v-if="service.short_description"
                                        class="mt-2 line-clamp-3 text-sm leading-6 text-accent"
                                    >
                                        {{ service.short_description }}
                                    </p>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span
                                            v-if="durationLabel(service)"
                                            class="rounded-md bg-soft px-3 py-1 text-xs font-medium text-accent"
                                        >
                                            {{ durationLabel(service) }}
                                        </span>

                                        <span
                                            v-if="service.self_pay_amount"
                                            class="rounded-md bg-soft px-3 py-1 text-xs font-medium text-accent"
                                        >
                                            Samoplatca {{ service.self_pay_amount }} €
                                        </span>

                                        <span
                                            v-if="service.insurance_amount"
                                            class="rounded-md bg-soft px-3 py-1 text-xs font-medium text-accent"
                                        >
                                            Poisťovňa {{ service.insurance_amount }} €
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-md bg-soft p-8 text-center"
            >
                <h2 class="text-heading font-semibold text-dark">
                    Zatiaľ tu nie sú žiadne služby
                </h2>

                <p class="mt-2 text-normal text-accent">
                    Služby tejto pobočky sa zobrazia po ich zverejnení.
                </p>
            </div>
        </section>
    </PublicBranchLayout>
</template>