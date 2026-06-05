<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    featuredServices: {
        type: Array,
        default: () => [],
    },
});

const durationLabel = (service) => {
    if (!service.duration_minutes) {
        return null;
    }

    const sessions = service.duration_sessions || 1;

    return `${sessions} × ${service.duration_minutes} min`;
};

const todayDayOfWeek = computed(() => {
    const day = new Date().getDay();

    return day === 0 ? 7 : day;
});

const todaysOpeningHours = computed(() => {
    return props.branch.opening_hours?.find((item) => {
        return item.day_of_week === todayDayOfWeek.value;
    }) ?? null;
});

const openingHoursTodayLabel = computed(() => {
    const openingHours = todaysOpeningHours.value;

    if (!openingHours) {
        return 'Dnes neuvedené';
    }

    if (openingHours.is_closed) {
        return 'Dnes zatvorené';
    }

    if (!openingHours.intervals?.length) {
        return 'Dnes neuvedené';
    }

    return openingHours.intervals
        .map((interval) => {
            return `${interval.opens_at.slice(0, 5)} – ${interval.closes_at.slice(0, 5)}`;
        })
        .join(', ');
});

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

const branchAddressLabel = computed(() => {
    const line1 = props.branch.address?.line_1;
    const line2 = props.branch.address?.line_2;
    const city = props.branch.address?.city;
    const postalCode = props.branch.address?.postal_code;
    const country = props.branch.address?.country;

    if (!line1 && !city) {
        return 'Adresa bude doplnená čoskoro.';
    }

    return [line1, line2, city, postalCode, country].filter(Boolean).join(', ');
});

const featuredServicesCountLabel = computed(() => {
    const count = props.featuredServices.length;

    if (count === 0) {
        return 'Ponuku služieb priebežne dopĺňame.';
    }

    if (count === 1) {
        return '1 zvýraznená služba';
    }

    if (count > 1 && count < 5) {
        return `${count} zvýraznené služby`;
    }

    return `${count} zvýraznených služieb`;
});

const generatedFaq = computed(() => {
    const customFaqItems = props.branch.public_site?.faq_items ?? [];

    const customQuestions = customFaqItems.map((item) => {
        return {
            icon: 'pi pi-question',
            question: item.question,
            answer: item.answer,
        };
    });

    const defaultQuestions = [
        {
            icon: 'pi pi-map-marker',
            question: 'Kde nás nájdete?',
            answer: 'Nájdete nás na adrese: ' + branchAddressLabel.value,
        },
        {
            icon: 'pi pi-phone',
            question: 'Ako sa objednať?',
            answer: primaryContact.value?.value
                ? `Najrýchlejšie sa s nami spojíte cez hlavný kontakt: ${primaryContact.value.value}.`
                : 'Použite sekciu Kontakt, kde nájdete všetky dostupné možnosti spojenia.',
        },
        {
            icon: 'pi pi-clock',
            question: 'Kedy máme otvorené?',
            answer: `Dnes máme otvorené v čase: ${openingHoursTodayLabel.value}`,
        },
    ];

    return [
        ...customQuestions,
        ...defaultQuestions,
    ];
});

const professionals = computed(() => {
    return props.branch.employees ?? props.branch.professionals ?? [];
});

const professionalName = (professional) => {
    return [
        professional.title_before,
        professional.first_name,
        professional.last_name,
        professional.title_after,
    ].filter(Boolean).join(' ');
};

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

        <section class="mb-8">
            <div class="overflow-hidden rounded-md bg-accent text-white">
                <div class="grid gap-8 p-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div>
                        <h1 class="max-w-3xl text-2xl font-semibold leading-tight text-white">
                            {{ branch.name }}
                        </h1>

                        <p class="mt-5 max-w-2xl text-base leading-7 text-white">
                            {{ branch.description || 'Profesionálna starostlivosť, jasné informácie a jednoduchý kontakt na jednom mieste.' }}
                        </p>

                        <div class="mt-7 flex flex-wrap gap-3">
                            <Link
                                :href="route('public.branch.services', branch.slug)"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-soft px-5 py-3 text-sm font-semibold text-accent transition hover:bg-white/90"
                            >
                                Ponúkané služby
                            </Link>

                            <Link
                                :href="route('public.branch.booking', branch.slug)"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-soft bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15"
                            >
                                Rezervovať termín
                            </Link>

                            <component
                                :is="primaryContactHref ? 'a' : 'div'"
                                v-if="primaryContact?.value"
                                :href="primaryContactHref"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-soft bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15"
                            >
                                {{ primaryContactButtonLabel }}
                            </component>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-8">
            <div>
                <div class="mb-3">
                    <h2 class="text-normal font-semibold text-dark">
                        Často sa pýtate
                    </h2>
                </div>

                <Accordion
                    value="0"
                    class="space-y-3"
                >
                    <AccordionPanel
                        v-for="(item, index) in generatedFaq"
                        :key="item.question"
                        :value="String(index)"
                    >
                        <AccordionHeader>
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-accent">
                                    <i :class="item.icon" />
                                </div>

                                <span class="text-sm font-semibold">
                                    {{ item.question }}
                                </span>
                            </div>
                        </AccordionHeader>

                        <AccordionContent>
                            <p>
                                {{ item.answer }}
                            </p>
                        </AccordionContent>
                    </AccordionPanel>
                </Accordion>
            </div>
        </section>

        <section class="mb-8">
            <div class="mb-3 flex items-center justify-between gap-4">
                <div>
                    <h2 class="mt-1 text-normal font-semibold text-dark">
                        Ponúkané služby
                    </h2>
                </div>

                <Link
                    :href="route('public.branch.services', branch.slug)"
                    class="hidden rounded-md bg-white px-4 py-2 text-sm font-semibold text-accent transition hover:bg-soft md:inline-flex"
                >
                    Zobraziť všetky
                </Link>
            </div>

            <div
                v-if="featuredServices.length"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="service in featuredServices"
                    :key="service.id"
                    :href="route('public.branch.services.show', [branch.slug, service.slug])"
                    class="rounded-md border border-accent bg-accent p-5 text-white transition hover:scale-[1.01] hover:bg-accent/90"
                >
                    <div class="flex items-center gap-3">
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

                        <h3 class="text-normal font-semibold text-white">
                            {{ service.name }}
                        </h3>
                    </div>

                    <div class="mt-3">
                        <p class="text-sm leading-6 text-white/80">
                            {{ service.short_description || service.description || 'Viac informácií nájdete v detaile služby.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-if="durationLabel(service)"
                                class="rounded-md bg-dark px-3 py-1 text-xs font-medium text-soft"
                            >
                                {{ durationLabel(service) }}
                            </span>

                            <span
                                v-if="service.self_pay_amount"
                                class="rounded-md bg-dark px-3 py-1 text-xs font-medium text-soft"
                            >
                                Samoplatca {{ service.self_pay_amount }} €
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="rounded-md border border-accent/10 bg-white p-6 text-sm text-accent"
            >
                Služby budú čoskoro doplnené. Kontaktujte pobočku pre aktuálne možnosti.
            </div>
        </section>

        <section class="mb-8">
            <div class="mb-3 flex items-center justify-between gap-4">
                <div>
                    <h2 class="mt-1 text-normal font-semibold text-dark">
                        Profesionáli
                    </h2>
                </div>
            </div>

            <div
                v-if="professionals.length"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="professional in professionals"
                    :key="professional.id"
                    :href="route('public.branch.contact', branch.slug)"
                    class="rounded-md bg-soft p-5 text-accent transition hover:scale-[1.01] hover:bg-accent/90"
                >
                    <div class="flex items-center gap-3">
                        <img
                            v-if="professional.photo_url"
                            :src="professional.photo_url"
                            :alt="professionalName(professional)"
                            class="h-14 w-14 shrink-0 rounded-md object-cover"
                        >

                        <div
                            v-else
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md bg-soft text-accent"
                        >
                            <span class="font-semibold">
                                {{ professional.first_name?.charAt(0) }}{{ professional.last_name?.charAt(0) }}
                            </span>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-normal font-semibold text-dark">
                                {{ professionalName(professional) }}
                            </h3>

                            <p
                                v-if="professional.position"
                                class="mt-1 text-sm leading-5 text-accent"
                            >
                                {{ professional.position }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="professional.bio"
                        class="mt-3 line-clamp-2 text-sm leading-6 text-white/80"
                    >
                        {{ professional.bio }}
                    </p>
                </Link>
            </div>

            <div
                v-else
                class="rounded-md border border-accent/10 bg-white p-6 text-sm text-accent"
            >
                Profesionáli budú čoskoro doplnení.
            </div>
        </section>


    </PublicBranchLayout>
</template>