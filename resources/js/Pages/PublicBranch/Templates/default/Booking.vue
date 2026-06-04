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
    canBookExactSlots: {
        type: Boolean,
        default: false,
    },
    canSubmitGeneralRequest: {
        type: Boolean,
        default: false,
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
    request_type: '',
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

const canShowExactSlots = computed(() => {
    return props.canBookExactSlots;
});

const canShowGeneralRequest = computed(() => {
    return props.canSubmitGeneralRequest
        && selectedServices.value.length > 0;
});

const shouldHighlightGeneralRequest = computed(() => {
    return canShowGeneralRequest.value
        && !props.availableSlots.length
        && !props.availableOptions.length;
});

const totalDurationMinutes = computed(() => {
    return selectedServices.value.reduce((total, service) => {
        return total + Number(service.duration_minutes || 0);
    }, 0);
});

const selectedSlotLabel = computed(() => {
    const slot = props.availableSlots.find((item) => Number(item.id) === Number(bookingForm.booking_slot_id));

    if (!slot) {
        return null;
    }

    return `${slot.starts_at.slice(0, 10)} · ${slot.starts_at.slice(11, 16)} – ${slot.ends_at.slice(11, 16)}`;
});

const selectedOptionLabel = computed(() => {
    const option = props.availableOptions.find((item) => item.id === bookingForm.preferred_option_id);

    if (!option) {
        return null;
    }

    return `${option.date_label}, ${option.period_label}`;
});

const isGeneralRequestSelected = computed(() => {
    return bookingForm.mode === 'appointment_request'
        && bookingForm.request_type === 'general';
});

const canSubmit = computed(() => {
    if (bookingForm.processing) {
        return false;
    }

    if (!bookingForm.patient_name.trim()) {
        return false;
    }

    if (!bookingForm.patient_email.trim()) {
        return false;
    }

    if (!selectedServiceIds.value.length) {
        return false;
    }

    if (bookingForm.mode === 'exact_slot') {
        return Boolean(bookingForm.booking_slot_id);
    }

    if (bookingForm.mode === 'appointment_request' && bookingForm.request_type === 'preferred_period') {
        return Boolean(bookingForm.preferred_option_id);
    }

    if (bookingForm.mode === 'appointment_request' && bookingForm.request_type === 'general') {
        return true;
    }

    return false;
});

const submitButtonLabel = computed(() => {
    if (bookingForm.mode === 'exact_slot') {
        return 'Rezervovať termín';
    }

    if (bookingForm.request_type === 'general') {
        return 'Odoslať všeobecnú požiadavku';
    }

    return 'Odoslať požiadavku na termín';
});

watch(selectedServiceIds, (value) => {
    bookingForm.service_ids = value;
    bookingForm.mode = '';
    bookingForm.request_type = '';
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
    bookingForm.request_type = '';
    bookingForm.booking_slot_id = slot.id;
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
};

const selectOption = (option) => {
    bookingForm.mode = 'appointment_request';
    bookingForm.request_type = 'preferred_period';
    bookingForm.preferred_option_id = option.id;
    bookingForm.preferred_date = option.date;
    bookingForm.preferred_period = option.period;
    bookingForm.booking_slot_id = '';
};

const selectGeneralRequest = () => {
    bookingForm.mode = 'appointment_request';
    bookingForm.request_type = 'general';
    bookingForm.booking_slot_id = '';
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
};

const submitBooking = () => {
    bookingForm.service_ids = selectedServiceIds.value;

    bookingForm.post(route('public.branch.booking.store', props.branch.slug), {
        preserveScroll: true,
        onSuccess: () => {
            bookingForm.reset(
                'mode',
                'request_type',
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
            <h1 class="text-heading font-semibold text-dark">
                Objednanie termínu
            </h1>

            <p class="max-w-2xl text-normal leading-7 text-accent">
                Vyberte službu alebo služby. Ak je dostupný konkrétny skupinový termín, môžete sa objednať hneď.
                Pri individuálnych alebo kombinovaných službách odošlete požiadavku a presný čas vám potvrdí sestra.
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
                        <h2 class="text-normal font-semibold text-dark">
                            Vyberte službu alebo služby
                        </h2>

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

                <div
                    v-if="canShowExactSlots"
                    class="space-y-3"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Skupinové termíny
                    </h2>

                    <div
                        v-if="availableSlots.length"
                        class="space-y-3"
                    >
                        <button
                            v-for="slot in availableSlots"
                            :key="slot.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition"
                            :class="Number(bookingForm.booking_slot_id) === Number(slot.id)
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
                                    :class="Number(bookingForm.booking_slot_id) === Number(slot.id) ? 'text-white/80' : 'text-accent'"
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
                    <h2 class="text-normal font-semibold text-dark">
                        Požiadať o termín
                    </h2>

                    <div
                        v-if="availableOptions.length"
                        class="space-y-3"
                    >
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
                        Pre vybrané služby momentálne nemáme dostupnú online možnosť na výber konkrétneho dňa alebo časti dňa.
                    </div>
                </div>

                <div
                    v-if="canShowGeneralRequest"
                    class="space-y-3"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Všeobecná požiadavka
                    </h2>

                    <button
                        type="button"
                        class="w-full rounded-md border px-4 py-4 text-left transition"
                        :class="isGeneralRequestSelected
                            ? 'border-accent bg-accent text-white'
                            : shouldHighlightGeneralRequest
                                ? 'border-accent bg-white text-dark hover:bg-soft'
                                : 'border-soft bg-white text-dark hover:bg-soft'"
                        @click="selectGeneralRequest"
                    >
                        <div class="space-y-1">
                            <p class="font-semibold">
                                Chcem, aby mi termín navrhla sestra
                            </p>

                            <p
                                class="text-sm"
                                :class="isGeneralRequestSelected ? 'text-white/80' : 'text-accent'"
                            >
                                Vhodné pri kombinácii viacerých služieb alebo keď sa nedá automaticky ponúknuť spoločný termín.
                            </p>
                        </div>
                    </button>
                </div>

                <form
                    class="space-y-4 rounded-md border border-soft bg-white p-5"
                    @submit.prevent="submitBooking"
                >
                    <h2 class="text-normal font-semibold text-dark">
                        Údaje pacienta
                    </h2>

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

                    <div
                        v-if="isGeneralRequestSelected"
                        class="rounded-md bg-soft px-4 py-3 text-sm text-accent"
                    >
                        Vybraná všeobecná požiadavka. Sestra vám navrhne vhodný termín.
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">
                            Meno <span class="text-red-500">*</span>
                        </span>

                        <input
                            v-model="bookingForm.patient_name"
                            type="text"
                            class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                        >
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">
                                Email <span class="text-red-500">*</span>
                            </span>

                            <input
                                v-model="bookingForm.patient_email"
                                type="email"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                                required
                            >
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-medium text-dark">
                                Telefón
                            </span>

                            <input
                                v-model="bookingForm.patient_phone"
                                type="text"
                                class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                            >
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-dark">
                            Poznámka
                        </span>

                        <textarea
                            v-model="bookingForm.patient_note"
                            rows="4"
                            class="w-full rounded-md border border-soft px-3 py-2 text-sm"
                            placeholder="Môžete doplniť preferované dni, čas alebo ďalšie informácie."
                        />
                    </label>

                    <p
                        v-if="bookingForm.errors.mode"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.mode }}
                    </p>

                    <p
                        v-if="bookingForm.errors.request_type"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.request_type }}
                    </p>

                    <p
                        v-if="bookingForm.errors.service_ids"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.service_ids }}
                    </p>

                    <p
                        v-if="bookingForm.errors.booking_slot_id"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.booking_slot_id }}
                    </p>

                    <p
                        v-if="bookingForm.errors.preferred_option_id"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.preferred_option_id }}
                    </p>

                    <p
                        v-if="bookingForm.errors.patient_name"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.patient_name }}
                    </p>

                    <p
                        v-if="bookingForm.errors.patient_email"
                        class="text-xs text-red-600"
                    >
                        {{ bookingForm.errors.patient_email }}
                    </p>

                    <button
                        type="submit"
                        class="rounded-md bg-accent px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="!canSubmit"
                    >
                        {{ submitButtonLabel }}
                    </button>
                </form>
            </div>

            <aside class="space-y-4">
                <div class="rounded-md bg-accent p-5 text-white">
                    <h2 class="text-lg font-semibold">
                        Hlavný kontakt
                    </h2>

                    <p class="mt-2 text-sm text-white/80">
                        Konkrétny voľný termín je rezervovaný hneď. Pri požiadavke vám presný čas potvrdí sestra.
                    </p>

                    <p
                        v-if="primaryContact?.value"
                        class="mt-4 text-sm font-semibold"
                    >
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