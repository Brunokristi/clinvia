<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
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
    booking_phone: 'pi pi-phone',
    website: 'pi pi-globe',
}[type] ?? 'pi pi-info-circle');

const contactHref = (contact) => {
    if (contact.type === 'email') {
        return `mailto:${contact.value}`;
    }

    if (['phone', 'booking_phone'].includes(contact.type)) {
        return `tel:${contact.value}`;
    }

    if (contact.type === 'website') {
        return contact.value;
    }

    return null;
};

const contactLabel = (contact) => {
    if (contact.label) {
        return contact.label;
    }

    return {
        email: 'Email',
        phone: 'Telefón',
        booking_phone: 'Telefón na objednanie',
        website: 'Web',
    }[contact.type] ?? 'Kontakt';
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

const branchAddress = computed(() => {
    if (!props.branch.address) {
        return null;
    }

    return [
        [props.branch.address.line_1, props.branch.address.line_2].filter(Boolean).join(' '),
        [props.branch.address.postal_code, props.branch.address.city].filter(Boolean).join(' '),
        props.branch.address.country,
    ].filter(Boolean).join(', ');
});

const mapEmbedUrl = computed(() => {
    if (props.branch.location?.latitude && props.branch.location?.longitude) {
        return `https://www.google.com/maps?q=${props.branch.location.latitude},${props.branch.location.longitude}&output=embed`;
    }

    if (branchAddress.value) {
        return `https://www.google.com/maps?q=${encodeURIComponent(branchAddress.value)}&output=embed`;
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
            <div class="space-y-2">
                <h1 class="text-heading font-semibold text-dark">
                    Kontakt
                </h1>

                <p class="max-w-2xl text-normal leading-7 text-accent">
                    Napíšte nám správu alebo použite kontaktné údaje pobočky {{ branch.name }}.
                </p>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ flashSuccess }}
            </div>
        </section>

        <section class="grid gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-8">
                <div class="space-y-5">
                    <div class="space-y-1">
                        <h2 class="text-normal font-semibold text-dark">
                            Napíšte nám
                        </h2>

                        <p class="text-sm text-accent">
                            Vyplňte krátky formulár a ozveme sa vám čo najskôr.
                        </p>
                    </div>

                    <form
                        class="space-y-5"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Meno
                                </label>

                                <InputText
                                    v-model="form.sender_name"
                                    class="w-full"
                                    :class="form.errors.sender_name ? 'p-invalid' : ''"
                                />

                                <small
                                    v-if="form.errors.sender_name"
                                    class="mt-1 block text-red-600"
                                >
                                    {{ form.errors.sender_name }}
                                </small>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Email
                                </label>

                                <InputText
                                    v-model="form.sender_email"
                                    type="email"
                                    class="w-full"
                                    :class="form.errors.sender_email ? 'p-invalid' : ''"
                                />

                                <small
                                    v-if="form.errors.sender_email"
                                    class="mt-1 block text-red-600"
                                >
                                    {{ form.errors.sender_email }}
                                </small>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-dark">
                                Telefón
                            </label>

                            <InputText
                                v-model="form.sender_phone"
                                class="w-full"
                                :class="form.errors.sender_phone ? 'p-invalid' : ''"
                            />

                            <small
                                v-if="form.errors.sender_phone"
                                class="mt-1 block text-red-600"
                            >
                                {{ form.errors.sender_phone }}
                            </small>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-dark">
                                Správa
                            </label>

                            <Textarea
                                v-model="form.body"
                                rows="6"
                                class="w-full"
                                :class="form.errors.body ? 'p-invalid' : ''"
                                placeholder="Ako vám môžeme pomôcť?"
                            />

                            <small
                                v-if="form.errors.body"
                                class="mt-1 block text-red-600"
                            >
                                {{ form.errors.body }}
                            </small>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                label="Odoslať správu"
                                :loading="form.processing"
                            />
                        </div>
                    </form>
                </div>
            </div>

            <aside class="space-y-4">
                <div
                    v-if="branch.contacts?.length"
                    class="rounded-md border border-soft bg-accent p-5 text-white"
                >
                    <div class="space-y-3">
                        <component
                            :is="contactHref(contact) ? 'a' : 'div'"
                            v-for="(contact, index) in branch.contacts"
                            :key="index"
                            :href="contactHref(contact)"
                            class="flex items-center gap-4 rounded-md text-white transition "
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-soft text-accent">
                                <i :class="contactIcon(contact.type)" />
                            </span>

                            <span>
                                <span class="block text-sm font-semibold text-white">
                                    {{ contactLabel(contact) }}
                                </span>

                                <span class="block text-sm text-white/80">
                                    {{ contact.value }}
                                </span>
                            </span>
                        </component>
                    </div>
                </div>

                <div class="rounded-md border border-soft bg-white p-5">
                    <h2 class="text-normal font-semibold text-dark">
                        Otváracie hodiny
                    </h2>

                    <div
                        v-if="branch.opening_hours?.length"
                        class="mt-4 divide-y divide-soft"
                    >
                        <div
                            v-for="openingHour in branch.opening_hours"
                            :key="openingHour.day_of_week"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <p class="text-sm font-medium text-dark">
                                {{ dayNames[openingHour.day_of_week] }}
                            </p>

                            <p class="text-sm text-accent">
                                {{ openingHoursLabel(openingHour) }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-4 text-sm text-accent"
                    >
                        Otváracie hodiny momentálne nie sú uvedené.
                    </p>
                </div>

                <div
                    v-if="branchAddress || mapEmbedUrl"
                    class="overflow-hidden rounded-md border border-soft bg-white"
                >
                    <div class="p-5">
                        <h2 class="text-normal font-semibold text-dark">
                            Adresa
                        </h2>

                        <p
                            v-if="branchAddress"
                            class="mt-2 text-sm leading-6 text-accent"
                        >
                            {{ branchAddress }}
                        </p>
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
            </aside>
        </section>
    </PublicBranchLayout>
</template>