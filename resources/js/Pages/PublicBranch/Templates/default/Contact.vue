<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const form = useForm({
    sender_name: '',
    sender_email: '',
    sender_phone: '',
    body: '',
});

const submit = () => {
    form.post(route('public.branch.contact-message.store', props.branch.slug), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const dayNames = {
    1: 'Pondelok',
    2: 'Utorok',
    3: 'Streda',
    4: 'Štvrtok',
    5: 'Piatok',
    6: 'Sobota',
    7: 'Nedeľa',
};

const contactIcon = (type) => ({
    email: 'pi pi-envelope',
    phone: 'pi pi-phone',
    website: 'pi pi-globe',
}[type] ?? 'pi pi-info-circle');

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

const employeeName = (employee) => [
    employee.title_before,
    employee.first_name,
    employee.last_name,
    employee.title_after,
].filter(Boolean).join(' ');

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

const primaryContact = computed(() => {
    const contacts = props.branch.contacts ?? [];

    return contacts.find((contact) => contact.is_primary)
        ?? contacts.find((contact) => ['phone', 'booking_phone'].includes(contact.type))
        ?? contacts.find((contact) => contact.type === 'email')
        ?? contacts[0]
        ?? null;
});
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Kontakt | ${branch.name}`" />

        <section class="space-y-4">
            <h1 class="text-heading font-semibold text-dark">
                Kontakty
            </h1>

            <p class="max-w-2xl text-normal leading-7 text-accent">
                V {{ branch.name }} sme tu pre vás.
            </p>

            <div
                v-if="flashSuccess"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ flashSuccess }}
            </div>
        </section>

        <section class="grid gap-8 py-8 lg:grid-cols-2">
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
                </div>

                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Napíšte nám
                    </h2>

                    <form
                        class="mt-5 space-y-4 rounded-md border border-soft bg-white p-5"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-dark">Meno</span>
                                <input
                                    v-model="form.sender_name"
                                    type="text"
                                    class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                                    :class="form.errors.sender_name ? 'border-red-400' : ''"
                                >
                                <p v-if="form.errors.sender_name" class="mt-1 text-xs text-red-600">{{ form.errors.sender_name }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-dark">Email</span>
                                <input
                                    v-model="form.sender_email"
                                    type="email"
                                    class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                                    :class="form.errors.sender_email ? 'border-red-400' : ''"
                                >
                                <p v-if="form.errors.sender_email" class="mt-1 text-xs text-red-600">{{ form.errors.sender_email }}</p>
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Telefón</span>
                            <input
                                v-model="form.sender_phone"
                                type="text"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                                :class="form.errors.sender_phone ? 'border-red-400' : ''"
                            >
                            <p v-if="form.errors.sender_phone" class="mt-1 text-xs text-red-600">{{ form.errors.sender_phone }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Správa</span>
                            <textarea
                                v-model="form.body"
                                rows="5"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                                :class="form.errors.body ? 'border-red-400' : ''"
                            />
                            <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white"
                            :disabled="form.processing"
                        >
                            Odoslať správu
                        </button>
                    </form>
                </div>

                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Profesionáli o vašu starostlivosť
                    </h2>

                    <div v-if="branch.employees?.length" class="mt-5 space-y-4">
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

                                <p v-if="employee.position" class="mt-1 text-sm text-accent">
                                    {{ employee.position }}
                                </p>

                                <p v-if="employee.bio" class="mt-2 line-clamp-3 text-sm leading-6 text-accent">
                                    {{ employee.bio }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div>
                    <h2 class="text-normal font-semibold text-dark">
                        Otváracie hodiny
                    </h2>

                    <div v-if="branch.opening_hours?.length" class="mt-5 divide-y divide-soft">
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
                </div>

                <div class="overflow-hidden rounded-md bg-white">
                    <h2 class="text-normal font-semibold text-dark">
                        Adresa
                    </h2>

                    <div class="text-normal leading-7 text-accent">
                        <p>{{ branch.address.line_1 }} {{ branch.address.line_2 }}, {{ branch.address.postal_code }} {{ branch.address.city }}, {{ branch.address.country }}</p>
                    </div>

                    <div v-if="mapEmbedUrl" class="border-t border-soft">
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

                <div v-if="primaryContact?.value" class="rounded-md border border-accent/10 bg-accent p-5 text-white">
                    <p class="text-xs uppercase tracking-wide text-white/60">Hlavný kontakt</p>
                    <p class="mt-2 text-lg font-semibold">{{ primaryContact.label || primaryContact.type }}</p>
                    <p class="mt-1 text-sm text-white/80">{{ primaryContact.value }}</p>
                </div>
            </div>
        </section>
    </PublicBranchLayout>
</template>