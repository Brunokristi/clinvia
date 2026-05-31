<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const mobileMenuOpen = ref(false);

const todayDayOfWeek = computed(() => {
    const day = new Date().getDay();

    return day === 0 ? 7 : day;
});

const todaysOpeningHours = computed(() => {
    return props.branch.opening_hours?.find((item) => {
        return item.day_of_week === todayDayOfWeek.value;
    });
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

    return contacts.find((contact) => contact.is_primary)
        ?? contacts.find((contact) => ['phone', 'booking_phone'].includes(contact.type))
        ?? contacts.find((contact) => contact.type === 'email')
        ?? contacts[0]
        ?? null;
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

const primaryContactIcon = computed(() => {
    if (!primaryContact.value) {
        return 'pi pi-info-circle';
    }

    if (['phone', 'booking_phone'].includes(primaryContact.value.type)) {
        return 'pi pi-phone';
    }

    if (primaryContact.value.type === 'email') {
        return 'pi pi-envelope';
    }

    return 'pi pi-send';
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

const links = computed(() => [
    {
        label: 'Domov',
        href: route('public.branch.home', props.branch.slug),
        active: route().current('public.branch.home'),
    },
    {
        label: 'Služby',
        href: route('public.branch.services', props.branch.slug),
        active: route().current('public.branch.services') || route().current('public.branch.services.show'),
    },
    {
        label: 'Kontakt',
        href: route('public.branch.contact', props.branch.slug),
        active: route().current('public.branch.contact'),
    },
]);
</script>

<template>
    <header class="border-b border-accent/20 bg-white">
        <div class="mx-auto max-w-6xl px-6">
            <div class="hidden grid-cols-[auto_auto_minmax(0,1fr)_auto_auto] items-stretch lg:grid">
                <div class="flex min-w-0 items-center border-r border-accent/20 px-5 py-4">
                    <Link
                        :href="route('public.branch.home', branch.slug)"
                        class="block max-w-52 truncate text-heading font-semibold text-accent"
                    >
                        {{ branch.name }}
                    </Link>
                </div>

                <div class="flex max-w-64 items-center gap-2 border-r border-accent/20 px-5 py-4 text-accent">
                    <i class="pi pi-map-marker text-lg" />

                    <p
                        v-if="branch.address?.line_1"
                        class="truncate text-sm text-accent/70"
                    >
                        {{ branch.address.line_1 }}, {{ branch.address.city }}
                    </p>

                    <p
                        v-else
                        class="whitespace-nowrap text-sm text-accent/50"
                    >
                        Adresa neuvedená
                    </p>
                </div>

                <nav class="flex min-w-0 items-center justify-center gap-12 border-r border-accent/20 px-6 py-4 text-normal font-medium text-accent">
                    <Link
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        class="whitespace-nowrap transition hover:text-dark"
                        :class="link.active ? 'text-dark' : ''"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="flex items-center justify-end border-r border-accent/20 px-5 py-4 text-right">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-accent/60">
                            Dnes
                        </p>

                        <p class="whitespace-nowrap text-sm font-semibold text-dark">
                            {{ openingHoursTodayLabel }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end px-5 py-4">
                    <component
                        :is="primaryContactHref ? 'a' : 'div'"
                        v-if="primaryContactValue"
                        :href="primaryContactHref"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white transition hover:bg-accent/90"
                    >
                        <i
                            :class="primaryContactIcon"
                            class="text-xs"
                        />

                        <span>
                            {{ primaryContactButtonLabel }}
                        </span>
                    </component>
                </div>
            </div>

            <div class="flex items-center justify-between py-4 lg:hidden">
                <Link
                    :href="route('public.branch.home', branch.slug)"
                    class="block truncate text-heading font-semibold text-accent"
                >
                    {{ branch.name }}
                </Link>

                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-md border border-accent/20 text-accent transition hover:bg-soft"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <i
                        class="pi text-lg"
                        :class="mobileMenuOpen ? 'pi-times' : 'pi-bars'"
                    />
                </button>
            </div>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="border-t border-accent/20 lg:hidden"
        >
            <div class="mx-auto grid max-w-6xl divide-y divide-accent/10 px-6">
                <div
                    v-if="branch.address?.line_1"
                    class="flex items-center gap-2 py-4 text-sm text-accent/70"
                >
                    <i class="pi pi-map-marker text-accent" />

                    <span>
                        {{ branch.address.line_1 }}, {{ branch.address.city }}
                    </span>
                </div>

                <nav class="grid py-2 text-normal font-medium text-accent">
                    <Link
                        v-for="link in links"
                        :key="link.label"
                        :href="link.href"
                        class="rounded-md px-3 py-3 transition hover:bg-soft"
                        :class="link.active ? 'bg-soft text-dark' : ''"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="grid gap-3 py-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-accent/60">
                            Dnes
                        </p>

                        <p class="text-sm font-semibold text-dark">
                            {{ openingHoursTodayLabel }}
                        </p>
                    </div>

                    <component
                        :is="primaryContactHref ? 'a' : 'div'"
                        v-if="primaryContactValue"
                        :href="primaryContactHref"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-accent px-4 py-3 text-sm font-semibold text-white transition hover:bg-accent/90"
                        @click="mobileMenuOpen = false"
                    >
                        <i
                            :class="primaryContactIcon"
                            class="text-xs"
                        />

                        <span>
                            {{ primaryContactButtonLabel }}
                        </span>
                    </component>
                </div>
            </div>
        </div>
    </header>
</template>