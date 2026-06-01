<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const dayNames = {
    1: 'Pondelok',
    2: 'Utorok',
    3: 'Streda',
    4: 'Štvrtok',
    5: 'Piatok',
    6: 'Sobota',
    7: 'Nedeľa',
};

const contactIcon = (type) => {
    return {
        email: 'pi pi-envelope',
        phone: 'pi pi-phone',
        website: 'pi pi-globe',
    }[type] ?? 'pi pi-info-circle';
};

const contactHref = (contact) => {
    if (contact.type === 'email') {
        return `mailto:${contact.value}`;
    }

    if (contact.type === 'phone') {
        return `tel:${contact.value}`;
    }

    if (contact.type === 'website') {
        return contact.value;
    }

    return null;
};

const employeeName = (employee) => {
    return [
        employee.title_before,
        employee.first_name,
        employee.last_name,
        employee.title_after,
    ].filter(Boolean).join(' ');
};

const openingHoursLabel = (openingHour) => {
    if (openingHour.is_closed) {
        return 'Zatvorené';
    }

    if (!openingHour.intervals?.length) {
        return 'Neuvedené';
    }

    return openingHour.intervals
        .map((interval) => `${interval.opens_at.slice(0, 5)} – ${interval.closes_at.slice(0, 5)}`)
        .join(', ');
};

const mapEmbedUrl = computed(() => {
    if (props.branch.location?.latitude && props.branch.location?.longitude) {
        return `https://www.google.com/maps?q=${props.branch.location.latitude},${props.branch.location.longitude}&output=embed`;
    }

    if (props.branch.address) {
        const address = [
            props.branch.address.line_1,
            props.branch.address.line_2,
            props.branch.address.postal_code,
            props.branch.address.city,
            props.branch.address.country,
        ].filter(Boolean).join(', ');

        return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
    }

    return null;
});

const mapOpenUrl = computed(() => {
    if (props.branch.location?.latitude && props.branch.location?.longitude) {
        return `https://www.google.com/maps/search/?api=1&query=${props.branch.location.latitude},${props.branch.location.longitude}`;
    }

    if (props.branch.address) {
        const address = [
            props.branch.address.line_1,
            props.branch.address.line_2,
            props.branch.address.postal_code,
            props.branch.address.city,
            props.branch.address.country,
        ].filter(Boolean).join(', ');

        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
    }

    return null;
});
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Kontakt | ${branch.name}`" />

        <section>
            <h1 class="text-heading font-semibold text-dark">
                Kontakty
            </h1>

            <p class="mt-3 max-w-2xl text-normal leading-7 text-accent">
                V {{ branch.name }} sme tu pre vás
            </p>       
        </section>

        

        <section class="grid gap-8 lg:grid-cols-2 my-8">
            <div class="space-y-8">
                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Kontakty
                    </h2>

                    <div
                        v-if="branch.contacts?.length"
                        class="mt-5 space-y-3"
                    >
                        <component
                            :is="contactHref(contact) ? 'a' : 'div'"
                            v-for="(contact, index) in branch.contacts"
                            :key="index"
                            :href="contactHref(contact)"
                            class="flex items-center gap-3 rounded-md bg-soft px-4 py-3 text-accent transition hover:bg-soft/80"
                        >
                            <i :class="contactIcon(contact.type)" />

                            <div>
                                <p class="text-sm font-semibold text-dark">
                                    {{ contact.label || contact.type }}
                                </p>

                                <p class="text-sm text-accent">
                                    {{ contact.value }}
                                </p>
                            </div>
                        </component>
                    </div>

                    <p
                        v-else
                        class="mt-4 text-normal text-accent"
                    >
                        Kontaktné údaje zatiaľ nie sú uvedené.
                    </p>
                </div>


                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Profesionáli o vašu starostlivosť
                    </h2>

                    <div
                        v-if="branch.employees?.length"
                        class="mt-5 space-y-4"
                    >
                        <div
                            v-for="employee in branch.employees"
                            :key="employee.id"
                            class="flex gap-4 rounded-md bg-soft p-4"
                        >
                            <img
                                v-if="employee.photo_url"
                                :src="employee.photo_url"
                                :alt="employeeName(employee)"
                                class="h-16 w-16 shrink-0 rounded-md object-cover"
                            >

                            <div
                                v-else
                                class="flex h-16 w-16 shrink-0 items-center justify-center rounded-md bg-white text-lg font-semibold text-accent"
                            >
                                {{ employee.first_name?.charAt(0) }}{{ employee.last_name?.charAt(0) }}
                            </div>

                            <div>
                                <h3 class="text-normal font-semibold text-dark">
                                    {{ employeeName(employee) }}
                                </h3>

                                <p
                                    v-if="employee.position"
                                    class="mt-1 text-sm text-accent"
                                >
                                    {{ employee.position }}
                                </p>

                                <p
                                    v-if="employee.bio"
                                    class="mt-2 line-clamp-3 text-sm leading-6 text-accent"
                                >
                                    {{ employee.bio }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-4 text-normal text-accent"
                    >
                        Tím zatiaľ nie je uvedený.
                    </p>
                </div>
            </div>

            <div class="space-y-8">
                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Otváracie hodiny
                    </h2>

                    <div
                        v-if="branch.opening_hours?.length"
                        class="mt-5 divide-y divide-soft"
                    >
                        <div
                            v-for="openingHour in branch.opening_hours"
                            :key="openingHour.day_of_week"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <p class="text-normal text-dark">
                                {{ dayNames[openingHour.day_of_week] }}
                            </p>

                            <p class="text-normal text-accent">
                                {{ openingHoursLabel(openingHour) }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-4 text-normal text-accent"
                    >
                        Otváracie hodiny zatiaľ nie sú uvedené.
                    </p>
                </div>

                <div class="overflow-hidden rounded-md bg-white">
                    <h2 class="text-normal font-semibold text-dark">
                        Adresa
                    </h2>
                    
                    <div class="text-normal leading-7 text-accent">
                        <p>{{ branch.address.line_1 }} {{ branch.address.line_2 }},  {{ branch.address.postal_code }} {{ branch.address.city }}, {{ branch.address.country }}</p>
                    </div>
                    <div
                        v-if="mapEmbedUrl"
                        class="border-t border-soft"
                    >
                        <iframe
                            :src="mapEmbedUrl"
                            class="h-72 w-full"
                            style="border: 0;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        />
                    </div>
                </div>
            </div>
        </section>
    </PublicBranchLayout>
</template>