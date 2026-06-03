<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
    availableOptions: {
        type: Array,
        default: () => [],
    },
    selectedServiceIds: {
        type: Array,
        default: () => [],
    },
    selectedDate: {
        type: String,
        default: '',
    },
});

const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const selectedServiceIds = ref(
    props.selectedServiceIds.length
        ? props.selectedServiceIds.map((id) => Number(id))
        : []
);

const dateValue = ref(props.selectedDate || new Date().toISOString().slice(0, 10));

const bookingForm = useForm({
    mode: '',
    service_ids: selectedServiceIds.value,
    booking_slot_id: '',
    preferred_option_id: '',
    preferred_date: '',
    preferred_period: '',
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_note: '',
});

const selectedServices = computed(() => {
    return props.services.filter((service) => selectedServiceIds.value.includes(Number(service.id)));
});

const selectedSingleService = computed(() => {
    if (selectedServices.value.length !== 1) {
        return null;
    }

    return selectedServices.value[0];
});

const canShowExactSlots = computed(() => {
    return selectedSingleService.value?.booking_type === 'group';
});

const totalDurationMinutes = computed(() => {
    return selectedServices.value.reduce((total, service) => {
        return total + Number(service.duration_minutes || 0);
    }, 0);
});

const selectedSlotLabel = computed(() => {
    const slot = props.availableSlots.find((item) => item.id === bookingForm.booking_slot_id);

    if (!slot) {
        return null;
    }

    return `${slot.starts_at.slice(11, 16)} – ${slot.ends_at.slice(11, 16)}`;
});

const selectedOptionLabel = computed(() => {
    const option = props.availableOptions.find((item) => item.id === bookingForm.preferred_option_id);

    if (!option) {
        return null;
    }

    return `${option.date_label}, ${option.period_label}`;
});

const canSubmit = computed(() => {
    if (bookingForm.processing) {
        return false;
    }

    if (!bookingForm.patient_name) {
        return false;
    }

    if (!selectedServiceIds.value.length) {
        return false;
    }

    if (bookingForm.mode === 'exact_slot') {
        return Boolean(bookingForm.booking_slot_id);
    }

    if (bookingForm.mode === 'appointment_request') {
        return Boolean(bookingForm.preferred_option_id);
    }

    return false;
});

watch(selectedServiceIds, (value) => {
    bookingForm.service_ids = value;
    bookingForm.mode = '';
    bookingForm.booking_slot_id = '';
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
});

const toggleService = (service) => {
    const serviceId = Number(service.id);

    if (selectedServiceIds.value.includes(serviceId)) {
        selectedServiceIds.value = selectedServiceIds.value.filter((id) => id !== serviceId);
        return;
    }

    selectedServiceIds.value = [
        ...selectedServiceIds.value,
        serviceId,
    ];
};

const applyFilters = () => {
    router.get(route('public.branch.booking', props.branch.slug), {
        services: selectedServiceIds.value,
        date: dateValue.value || undefined,
    }, {
        preserveState: false,
        preserveScroll: true,
    });
};

const selectSlot = (slot) => {
    bookingForm.mode = 'exact_slot';
    bookingForm.booking_slot_id = slot.id;
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
};

const selectOption = (option) => {
    bookingForm.mode = 'appointment_request';
    bookingForm.preferred_option_id = option.id;
    bookingForm.preferred_date = option.date;
    bookingForm.preferred_period = option.period;
    bookingForm.booking_slot_id = '';
};

const submitBooking = () => {
    bookingForm.service_ids = selectedServiceIds.value;

    bookingForm.post(route('public.branch.booking.store', props.branch.slug), {
        preserveScroll: true,
        onSuccess: () => {
            bookingForm.reset(
                'mode',
                'booking_slot_id',
                'preferred_option_id',
                'preferred_date',
                'preferred_period',
                'patient_name',
                'patient_email',
                'patient_phone',
                'patient_note',
            );
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
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Booking | ${branch.name}`" />

        <section class="space-y-4">
            <h1 class="text-heading font-semibold text-dark">Objednanie termínu</h1>

            <p class="max-w-2xl text-normal leading-7 text-accent">
                Vyberte službu. Ak existuje konkrétny voľný termín, môžete sa objednať hneď. Ak ide o voľnú kapacitu, odošlete požiadavku a presný čas vám potvrdí sestra.
            </p>

            <div
                v-if="flashSuccess"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ flashSuccess }}
            </div>
        </section>

        <section class="grid gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <div class="space-y-5 rounded-md border border-soft bg-white p-5">
                    <div class="space-y-3">
                        <h2 class="text-normal font-semibold text-dark">Vyberte službu alebo služby</h2>

                        <button
                            v-for="service in services"
                            :key="service.id"
                            type="button"
                            class="flex w-full items-start justify-between rounded-md border px-4 py-3 text-left transition"
                            :class="selectedServiceIds.includes(Number(service.id))
                                ? 'border-accent bg-accent text-white'
                                : 'border-soft bg-white text-dark hover:bg-soft'"
                            @click="toggleService(service)"
                        >
                            <span>
                                <span class="block font-semibold">
                                    {{ service.name }}
                                </span>

                                <span
                                    class="block text-xs"
                                    :class="selectedServiceIds.includes(Number(service.id)) ? 'text-white/80' : 'text-accent'"
                                >
                                    {{ service.duration_minutes }} min
                                </span>
                            </span>

                            <span class="text-xs font-semibold uppercase tracking-wide">
                                {{ selectedServiceIds.includes(Number(service.id)) ? 'Vybrané' : 'Vybrať' }}
                            </span>
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-[1fr_auto]">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">
                                Hľadať od dátumu
                            </span>

                            <input
                                v-model="dateValue"
                                type="date"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                            >
                        </label>

                        <div class="flex items-end">
                            <button
                                type="button"
                                class="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="!selectedServiceIds.length"
                                @click="applyFilters"
                            >
                                Zobraziť dostupnosť
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="selectedServices.length"
                        class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
                    >
                        Vybrané služby: {{ selectedServices.map((service) => service.name).join(', ') }}

                        <span v-if="totalDurationMinutes">
                            · približne {{ totalDurationMinutes }} min
                        </span>
                    </div>
                </div>

                <div v-if="canShowExactSlots" class="space-y-3">
                    <h2 class="text-normal font-semibold text-dark">Skupinové termíny</h2>

                    <div v-if="availableSlots.length" class="space-y-3">
                        <button
                            v-for="slot in availableSlots"
                            :key="slot.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition"
                            :class="bookingForm.booking_slot_id === slot.id
                                ? 'border-accent bg-accent text-white'
                                : 'border-soft bg-white text-dark hover:bg-soft'"
                            @click="selectSlot(slot)"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{ slot.starts_at.slice(0, 10) }} · {{ slot.starts_at.slice(11, 16) }} – {{ slot.ends_at.slice(11, 16) }}
                                </p>

                                <p
                                    class="text-xs"
                                    :class="bookingForm.booking_slot_id === slot.id ? 'text-white/80' : 'text-accent'"
                                >
                                    {{ slot.free_capacity }} voľné miesta z {{ slot.capacity }}
                                </p>
                            </div>

                            <span class="text-xs font-semibold uppercase tracking-wide">
                                Rezervovať
                            </span>
                        </button>
                    </div>

                    <div
                        v-else
                        class="rounded-md border border-soft bg-white p-5 text-sm text-accent"
                    >
                        Pre vybranú službu momentálne nie sú dostupné skupinové termíny.
                    </div>
                </div>

                <div class="space-y-3">
                    <h2 class="text-normal font-semibold text-dark">Požiadať o termín</h2>

                    <div v-if="availableOptions.length" class="space-y-3">
                        <button
                            v-for="option in availableOptions"
                            :key="option.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition"
                            :class="bookingForm.preferred_option_id === option.id
                                ? 'border-accent bg-accent text-white'
                                : 'border-soft bg-white text-dark hover:bg-soft'"
                            @click="selectOption(option)"
                        >
                            <div>
                                <p class="font-semibold">
                                    {{ option.date_label }}
                                </p>

                                <p>
                                    {{ option.period_label }}
                                </p>

                                <p
                                    class="text-xs"
                                    :class="bookingForm.preferred_option_id === option.id ? 'text-white/80' : 'text-accent'"
                                >
                                    Presný čas potvrdí sestra.
                                </p>
                            </div>

                            <span class="text-xs font-semibold uppercase tracking-wide">
                                Vybrať
                            </span>
                        </button>
                    </div>

                    <div
                        v-else
                        class="rounded-md border border-soft bg-white p-5 text-sm text-accent"
                    >
                        Pre vybrané služby momentálne nemáme dostupnú online možnosť na požiadavku.
                    </div>
                </div>

                <form
                    class="space-y-4 rounded-md border border-soft bg-white p-5"
                    @submit.prevent="submitBooking"
                >
                    <h2 class="text-normal font-semibold text-dark">Údaje pacienta</h2>

                    <div
                        v-if="selectedSlotLabel"
                        class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
                    >
                        Vybraný konkrétny termín: {{ selectedSlotLabel }}
                    </div>

                    <div
                        v-if="selectedOptionLabel"
                        class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
                    >
                        Vybraná možnosť: {{ selectedOptionLabel }}. Presný čas vám potvrdíme.
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">Meno</span>

                        <input
                            v-model="bookingForm.patient_name"
                            type="text"
                            class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                        >
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Email</span>

                            <input
                                v-model="bookingForm.patient_email"
                                type="email"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">Telefón</span>

                            <input
                                v-model="bookingForm.patient_phone"
                                type="text"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                            >
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">Poznámka</span>

                        <textarea
                            v-model="bookingForm.patient_note"
                            rows="4"
                            class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                        />
                    </label>

                    <p v-if="bookingForm.errors.mode" class="text-xs text-red-600">
                        {{ bookingForm.errors.mode }}
                    </p>

                    <p v-if="bookingForm.errors.service_ids" class="text-xs text-red-600">
                        {{ bookingForm.errors.service_ids }}
                    </p>

                    <p v-if="bookingForm.errors.booking_slot_id" class="text-xs text-red-600">
                        {{ bookingForm.errors.booking_slot_id }}
                    </p>

                    <p v-if="bookingForm.errors.preferred_option_id" class="text-xs text-red-600">
                        {{ bookingForm.errors.preferred_option_id }}
                    </p>

                    <p v-if="bookingForm.errors.patient_name" class="text-xs text-red-600">
                        {{ bookingForm.errors.patient_name }}
                    </p>

                    <button
                        type="submit"
                        class="rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="!canSubmit"
                    >
                        {{ bookingForm.mode === 'exact_slot' ? 'Rezervovať termín' : 'Odoslať požiadavku na termín' }}
                    </button>
                </form>
            </div>

            <aside class="space-y-4">
                <div class="rounded-md bg-accent p-5 text-white">
                    <h2 class="text-lg font-semibold">Hlavný kontakt</h2>

                    <p class="mt-2 text-sm text-white/80">
                        Konkrétny voľný termín je rezervovaný hneď. Pri požiadavke vám presný čas potvrdí sestra.
                    </p>

                    <p v-if="primaryContact?.value" class="mt-4 text-sm font-semibold">
                        {{ primaryContact.value }}
                    </p>
                </div>

                <Link
                    :href="route('public.branch.contact', branch.slug)"
                    class="block rounded-md border border-soft bg-white px-4 py-3 text-center text-sm font-semibold text-accent"
                >
                    Kontakt pobočky
                </Link>
            </aside>
        </section>
    </PublicBranchLayout>
</template>