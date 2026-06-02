<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    services: {
        type: Array,
        default: () => [],
    },
    availableSlots: {
        type: Array,
        default: () => [],
    },
    selectedServiceId: {
        type: [Number, String, null],
        default: null,
    },
    selectedDate: {
        type: String,
        default: '',
    },
    selectedService: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const serviceId = ref(props.selectedServiceId ?? props.services[0]?.id ?? '');
const dateValue = ref(props.selectedDate || new Date().toISOString().slice(0, 10));

const bookingForm = useForm({
    booking_slot_id: '',
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_note: '',
});

const applyFilters = () => {
    router.get(route('public.branch.booking', props.branch.slug), {
        service: serviceId.value || undefined,
        date: dateValue.value || undefined,
    }, {
        preserveState: false,
        preserveScroll: true,
    });
};

const submitBooking = () => {
    bookingForm.post(route('public.branch.booking.store', props.branch.slug), {
        preserveScroll: true,
        onSuccess: () => {
            bookingForm.reset('booking_slot_id', 'patient_name', 'patient_email', 'patient_phone', 'patient_note');
        },
    });
};

const primaryContact = computed(() => {
    const contacts = props.branch.contacts ?? [];

    return contacts.find((contact) => contact.is_primary)
        ?? contacts.find((contact) => ['phone', 'booking_phone'].includes(contact.type))
        ?? contacts.find((contact) => contact.type === 'email')
        ?? contacts[0]
        ?? null;
});

const selectSlot = (slot) => {
    bookingForm.booking_slot_id = slot.id;
};

const selectedSlotLabel = computed(() => {
    const slot = props.availableSlots.find((item) => item.id === bookingForm.booking_slot_id);

    if (!slot) {
        return null;
    }

    return `${slot.starts_at.slice(11, 16)} – ${slot.ends_at.slice(11, 16)}`;
});
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Booking | ${branch.name}`" />

        <section class="space-y-4">
            <h1 class="text-heading font-semibold text-dark">Rezervácia termínu</h1>
            <p class="max-w-2xl text-normal leading-7 text-accent">
                Vyberte službu, deň a dostupný slot. Kapacity sa kontrolujú na serveri pri potvrdení rezervácie.
            </p>

            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ flashSuccess }}
            </div>
        </section>

        <section class="grid gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <div class="rounded-md border border-soft bg-white p-5 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Služba</span>
                            <select v-model="serviceId" class="w-full rounded-md border border-soft px-3 py-2 text-sm">
                                <option value="">Vyberte službu</option>
                                <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Dátum</span>
                            <input v-model="dateValue" type="date" class="w-full rounded-md border border-soft px-3 py-2 text-sm">
                        </label>
                    </div>

                    <button type="button" class="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white" @click="applyFilters">
                        Zobraziť voľné sloty
                    </button>
                </div>

                <div v-if="selectedService" class="rounded-md bg-soft p-5">
                    <h2 class="text-normal font-semibold text-dark">{{ selectedService.name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-accent">{{ selectedService.short_description || selectedService.description }}</p>
                </div>

                <div v-if="availableSlots.length" class="space-y-3">
                    <h2 class="text-normal font-semibold text-dark">Dostupné sloty</h2>

                    <button
                        v-for="slot in availableSlots"
                        :key="slot.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition"
                        :class="bookingForm.booking_slot_id === slot.id ? 'border-accent bg-accent text-white' : 'border-soft bg-white text-dark hover:bg-soft'"
                        @click="selectSlot(slot)"
                    >
                        <div>
                            <p class="font-semibold">{{ slot.starts_at.slice(11, 16) }} – {{ slot.ends_at.slice(11, 16) }}</p>
                            <p class="text-xs" :class="bookingForm.booking_slot_id === slot.id ? 'text-white/80' : 'text-accent'">Kapacita {{ slot.confirmed_bookings_count }}/{{ slot.capacity }}</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide">Vybrať</span>
                    </button>
                </div>

                <div v-else class="rounded-md border border-soft bg-white p-5 text-sm text-accent">
                    Po zvolení služby a dátumu sa zobrazia dostupné sloty.
                </div>

                <form class="rounded-md border border-soft bg-white p-5 space-y-4" @submit.prevent="submitBooking">
                    <h2 class="text-normal font-semibold text-dark">Údaje pacienta</h2>

                    <div v-if="selectedSlotLabel" class="rounded-md bg-soft px-4 py-3 text-sm text-accent">
                        Vybraný slot: {{ selectedSlotLabel }}
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">Meno</span>
                        <input v-model="bookingForm.patient_name" type="text" class="w-full rounded-md border border-soft px-3 py-2 text-sm">
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Email</span>
                            <input v-model="bookingForm.patient_email" type="email" class="w-full rounded-md border border-soft px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Telefón</span>
                            <input v-model="bookingForm.patient_phone" type="text" class="w-full rounded-md border border-soft px-3 py-2 text-sm">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">Poznámka</span>
                        <textarea v-model="bookingForm.patient_note" rows="4" class="w-full rounded-md border border-soft px-3 py-2 text-sm" />
                    </label>

                    <p v-if="bookingForm.errors.booking_slot_id" class="text-xs text-red-600">{{ bookingForm.errors.booking_slot_id }}</p>

                    <button type="submit" class="rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white" :disabled="bookingForm.processing || !bookingForm.booking_slot_id">
                        Potvrdiť rezerváciu
                    </button>
                </form>
            </div>

            <aside class="space-y-4">
                <div class="rounded-md bg-accent p-5 text-white">
                    <h2 class="text-lg font-semibold">Hlavný kontakt</h2>
                    <p class="mt-2 text-sm text-white/80">Po rezervácii príde len informačný email. Zmeny riešte priamo s pobočkou.</p>
                    <p v-if="primaryContact?.value" class="mt-4 text-sm font-semibold">{{ primaryContact.value }}</p>
                </div>

                <Link :href="route('public.branch.contact', branch.slug)" class="block rounded-md border border-soft bg-white px-4 py-3 text-center text-sm font-semibold text-accent">
                    Kontakt pobočky
                </Link>
            </aside>
        </section>
    </PublicBranchLayout>
</template>