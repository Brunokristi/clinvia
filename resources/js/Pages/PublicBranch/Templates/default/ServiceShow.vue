<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    branch: {
        type: Object,
        required: true,
    },
    service: {
        type: Object,
        required: true,
    },
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
        <Head :title="`${service.name} | ${branch.name}`" />

        <section class="bg-soft/40">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <Link
                    :href="route('public.branch.services', branch.slug)"
                    class="text-sm font-semibold text-accent transition hover:text-dark"
                >
                    ← Späť na služby
                </Link>

                <div class="mt-8 flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md bg-white text-accent">
                        <i
                            v-if="service.icon"
                            :class="service.icon"
                            class="text-xl"
                        />

                        <span
                            v-else
                            class="text-lg font-semibold"
                        >
                            S
                        </span>
                    </div>

                    <div>
                        <p
                            v-if="service.category"
                            class="text-normal font-semibold text-accent"
                        >
                            {{ service.category.name }}
                        </p>

                        <h1 class="mt-3 text-4xl font-semibold text-dark">
                            {{ service.name }}
                        </h1>

                        <p
                            v-if="service.short_description"
                            class="mt-5 max-w-2xl text-normal leading-7 text-accent"
                        >
                            {{ service.short_description }}
                        </p>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <span
                                v-if="durationLabel(service)"
                                class="rounded-md bg-white px-3 py-1 text-sm font-medium text-accent"
                            >
                                {{ durationLabel(service) }}
                            </span>

                            <span
                                v-if="service.self_pay_amount"
                                class="rounded-md bg-white px-3 py-1 text-sm font-medium text-accent"
                            >
                                Samoplatca {{ service.self_pay_amount }} €
                            </span>

                            <span
                                v-if="service.insurance_amount"
                                class="rounded-md bg-white px-3 py-1 text-sm font-medium text-accent"
                            >
                                Poisťovňa {{ service.insurance_amount }} €
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-8 px-6 py-16 lg:grid-cols-[1fr_320px]">
            <div class="space-y-10">
                <div
                    v-if="service.description"
                    class="rounded-md border border-soft bg-white p-6"
                >
                    <h2 class="text-heading font-semibold text-dark">
                        Popis služby
                    </h2>

                    <p class="mt-4 whitespace-pre-line text-normal leading-7 text-accent">
                        {{ service.description }}
                    </p>
                </div>

                <div
                    v-if="service.information?.length"
                    class="rounded-md border border-soft bg-white p-6"
                >
                    <h2 class="text-heading font-semibold text-dark">
                        Dôležité informácie
                    </h2>

                    <div class="mt-5 space-y-3">
                        <div
                            v-for="(item, index) in service.information"
                            :key="index"
                            class="rounded-md bg-soft p-4 text-normal leading-7 text-accent"
                        >
                            {{ item.text }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="service.steps?.length"
                    class="rounded-md border border-soft bg-white p-6"
                >
                    <h2 class="text-heading font-semibold text-dark">
                        Priebeh služby
                    </h2>

                    <div class="mt-6 space-y-4">
                        <div
                            v-for="step in service.steps"
                            :key="step.number"
                            class="flex gap-4"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-soft text-sm font-semibold text-accent">
                                {{ step.number }}
                            </div>

                            <div>
                                <h3 class="text-normal font-semibold text-dark">
                                    {{ step.title }}
                                </h3>

                                <p
                                    v-if="step.text"
                                    class="mt-1 text-sm leading-6 text-accent"
                                >
                                    {{ step.text }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="service.files?.length"
                    class="rounded-md border border-soft bg-white p-6"
                >
                    <h2 class="text-heading font-semibold text-dark">
                        Dokumenty
                    </h2>

                    <div class="mt-5 space-y-3">
                        <a
                            v-for="(file, index) in service.files"
                            :key="index"
                            :href="file.file_path ? `/storage/${file.file_path}` : '#'"
                            target="_blank"
                            class="flex items-center justify-between rounded-md bg-soft px-4 py-3 text-sm font-medium text-accent transition hover:bg-soft/80"
                        >
                            <span>{{ file.label || file.original_name }}</span>
                            <i class="pi pi-download" />
                        </a>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-md border border-soft bg-white p-6">
                    <h2 class="text-normal font-semibold text-dark">
                        Objednanie a kontakt
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-accent">
                        Pre viac informácií kontaktujte pobočku.
                    </p>

                    <Link
                        :href="route('public.branch.contact', branch.slug)"
                        class="mt-5 inline-flex rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white"
                    >
                        Kontaktovať pobočku
                    </Link>
                </div>

                <div
                    v-if="service.insurance_note || service.self_pay_note"
                    class="rounded-md border border-soft bg-white p-6"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Poznámky k cene
                    </h2>

                    <p
                        v-if="service.insurance_note"
                        class="mt-3 text-sm leading-6 text-accent"
                    >
                        <strong>Poisťovňa:</strong> {{ service.insurance_note }}
                    </p>

                    <p
                        v-if="service.self_pay_note"
                        class="mt-3 text-sm leading-6 text-accent"
                    >
                        <strong>Samoplatca:</strong> {{ service.self_pay_note }}
                    </p>
                </div>
            </aside>
        </section>
    </PublicBranchLayout>
</template>