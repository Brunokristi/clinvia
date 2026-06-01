<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
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

const primaryContact = computed(() => {
    const contacts = props.branch.contacts ?? [];

    return (
        contacts.find((contact) => contact.is_primary)
        ?? contacts.find((contact) => ['phone', 'booking_phone'].includes(contact.type))
        ?? contacts.find((contact) => contact.type === 'email')
        ?? contacts[0]
        ?? null
    );
});

const primaryContactHref = computed(() => {
    if (!primaryContact.value?.value) {
        return null;
    }

    if (['phone', 'booking_phone'].includes(primaryContact.value.type)) {
        return `tel:${primaryContact.value.value.replace(/\s+/g, '')}`;
    }

    if (primaryContact.value.type === 'email') {
        return `mailto:${primaryContact.value.value}`;
    }

    if (['website', 'facebook', 'instagram'].includes(primaryContact.value.type)) {
        return primaryContact.value.value;
    }

    return null;
});

const primaryContactButtonLabel = computed(() => {
    if (!primaryContact.value) {
        return 'Kontaktujte nás';
    }

    if (['phone', 'booking_phone'].includes(primaryContact.value.type)) {
        return 'Zavolajte nám';
    }

    if (primaryContact.value.type === 'email') {
        return 'Napíšte nám';
    }

    return 'Kontaktujte nás';
});

const primaryContactValue = computed(() => {
    return primaryContact.value?.value ?? null;
});
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`${service.name} | ${branch.name}`" />

        <section>
            <Link
                :href="route('public.branch.services', branch.slug)"
                class="inline-flex items-center gap-2 text-sm text-accent transition hover:text-dark"
            >
                <i class="pi pi-arrow-left text-xs" />
                Späť na služby
            </Link>

            <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="rounded-md bg-accent p-6 text-white flex flex-col gap-4">
                    <p
                        v-if="service.category"
                        class="text-sm font-semibold text-white/70"
                    >
                        {{ service.category.name }}
                    </p>

                    <div class="flex items-center gap-4">  
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
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

                        <h1 class="mt-1 text-3xl font-semibold leading-tight text-white">
                            {{ service.name }}
                        </h1>
                    </div>

                    <div class="flex flex-col items-start gap-3 text-sm">
                        <p
                            v-if="service.short_description"
                            class="max-w-3xl text-sm leading-6 text-white/80"
                        >
                            {{ service.short_description }}
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="durationLabel(service)"
                                class="rounded-md bg-dark px-3 py-1 text-xs text-soft"
                            >
                                {{ durationLabel(service) }}
                            </span>

                            <span
                                v-if="service.self_pay_amount"
                                class="rounded-md bg-dark px-3 py-1 text-xs text-soft"
                            >
                                Samoplatca {{ service.self_pay_amount }} €
                            </span>

                            <span
                                v-if="service.insurance_amount"
                                class="rounded-md bg-dark px-3 py-1 text-xs text-soft"
                            >
                                Poisťovňa {{ service.insurance_amount }} €
                            </span>
                        </div>
                    </div>
                </div>

                <aside class="rounded-md border border-accent bg-white p-5">
                    <h2 class="text-normal font-semibold text-dark">
                        Objednanie
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-accent">
                        Pre viac informácií kontaktujte pobočku.
                    </p>

                    <div class="flex items-center">
                        <component
                            :is="primaryContactHref ? 'a' : 'div'"
                            v-if="primaryContactValue"
                            :href="primaryContactHref"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-accent/90"
                        >
                            <span>
                                {{ primaryContactButtonLabel }}
                            </span>
                        </component>
                    </div>

                    <Link
                        :href="route('public.branch.contact', branch.slug)"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-accent/90"
                    >
                        Kontakty
                    </Link>
                </aside>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px] mt-5">
            <div class="space-y-5">
                <div
                    v-if="service.description"
                    class="bg-white"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Popis služby
                    </h2>

                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-accent">
                        {{ service.description }}
                    </p>
                </div>

                <div
                    v-if="service.information?.length"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Dôležité informácie
                    </h2>

                     <div class="mt-4 grid gap-3">
                        <div
                            v-for="(item, index) in service.information"
                            :key="index"
                            class="grid grid-cols-[36px_minmax(0,1fr)] gap-3"
                        >
                            <div class="flex h-max w-9 items-center justify-center rounded-md bg-soft text-sm font-semibold text-accent">
                                +
                            </div>
                            
                            {{ item.text }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="service.steps?.length""
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Priebeh služby
                    </h2>

                    <div class="mt-4 grid gap-3">
                        <div
                            v-for="step in service.steps"
                            :key="step.number"
                            class="grid grid-cols-[36px_minmax(0,1fr)] gap-3"
                        >
                            <div class="flex h-max w-9 items-center justify-center rounded-md bg-soft text-sm font-semibold text-accent">
                                {{ step.number }}
                            </div>

                            <div>
                                <h3 class="text-sm font-semibold text-dark">
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
            </div>

            <aside class="space-y-5">
                <div
                    v-if="service.files?.length"
                    class="rounded-md border border-accent bg-white p-5"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Potrebné dokumenty
                    </h2>

                    <div class="mt-3 space-y-2">
                        <a
                            v-for="(file, index) in service.files"
                            :key="index"
                            :href="file.file_path ? `/storage/${file.file_path}` : '#'"
                            target="_blank"
                            class="flex items-center justify-between gap-3 rounded-md bg-soft px-3 py-2.5 text-sm font-medium text-accent transition hover:bg-soft"
                        >
                            <span class="truncate">
                                {{ file.label || file.original_name }}
                            </span>

                            <i class="pi pi-download shrink-0" />
                        </a>
                    </div>
                </div>

                <div
                    v-if="service.insurance_note || service.self_pay_note"
                    class="rounded-md border border-accent/10 bg-white p-5"
                >
                    <h2 class="text-lg font-semibold text-dark">
                        Poznámky k cene
                    </h2>

                    <div class="mt-3 space-y-3">
                        <p
                            v-if="service.insurance_note"
                            class="text-sm leading-6 text-accent"
                        >
                            <strong class="text-dark">Poisťovňa:</strong>
                            {{ service.insurance_note }}
                        </p>

                        <p
                            v-if="service.self_pay_note"
                            class="text-sm leading-6 text-accent"
                        >
                            <strong class="text-dark">Samoplatca:</strong>
                            {{ service.self_pay_note }}
                        </p>
                    </div>
                </div>
            </aside>
        </section>
    </PublicBranchLayout>
</template>