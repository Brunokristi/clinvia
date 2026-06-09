<script setup>
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Divider from 'primevue/divider';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import MultiSelect from 'primevue/multiselect';
import Paginator from 'primevue/paginator';
import Steps from 'primevue/steps';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
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
    availableOptionsPagination: {
        type: Object,
        default: null,
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

const today = new Date();
today.setHours(0, 0, 0, 0);

const currentStep = ref(1);
const submittedSuccessfully = ref(false);
const dateValue = ref(null);
const selectedServiceIds = ref([...props.selectedServiceIds]);

const bookingForm = useForm({
    mode: '',
    request_type: '',
    service_ids: [...props.selectedServiceIds],
    capacity_window_id: '',
    preferred_option_id: '',
    preferred_date: '',
    preferred_period: '',
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    patient_note: '',

    website: '',
    form_started_at: Date.now(),
});

const flashSuccess = computed(() => {
    return page.props.flash?.success ?? null;
});

const homeHref = computed(() => {
    return route('public.branch.home', props.branch.slug);
});

const stepItems = computed(() => [
    {
        label: 'Služby',
    },
    {
        label: 'Termín',
    },
    {
        label: 'Údaje',
    },
]);

const toPrimeVueDay = (dayOfWeek) => {
    const day = Number(dayOfWeek);

    if (day === 7) {
        return 0;
    }

    return day;
};

const disabledWeekdays = computed(() => {
    const openingHours = props.branch.opening_hours ?? [];

    return openingHours
        .filter((openingHour) => {
            return openingHour.is_closed
                || !openingHour.intervals?.length;
        })
        .map((openingHour) => {
            return toPrimeVueDay(openingHour.day_of_week);
        })
        .filter((day) => {
            return day >= 0 && day <= 6;
        });
});

const serviceOptions = computed(() => {
    return props.services
        .filter((service) => service.is_bookable !== false)
        .map((service) => ({
            id: Number(service.id),
            name: service.name,
            duration_minutes: Number(service.duration_minutes || 0),
            booking_type: service.booking_type,
            public_booking_type: service.public_booking_type,
        }));
});

const selectedServices = computed(() => {
    return serviceOptions.value.filter((service) => {
        return selectedServiceIds.value.includes(Number(service.id));
    });
});

const selectedServicesLabel = computed(() => {
    if (!selectedServices.value.length) {
        return 'Nie sú vybrané žiadne služby';
    }

    return selectedServices.value.map((service) => service.name).join(', ');
});

const totalDurationMinutes = computed(() => {
    return selectedServices.value.reduce((total, service) => {
        return total + Number(service.duration_minutes || 0);
    }, 0);
});

const hasSelectedServices = computed(() => {
    return selectedServiceIds.value.length > 0;
});

const hasExactSlots = computed(() => {
    return props.canBookExactSlots && props.availableSlots.length > 0;
});

const hasPreferredOptions = computed(() => {
    return props.availableOptions.length > 0;
});

const canUseGeneralRequest = computed(() => {
    return props.canSubmitGeneralRequest && hasSelectedServices.value;
});

const shouldShowGeneralRequest = computed(() => {
    return canUseGeneralRequest.value
        && !hasExactSlots.value
        && !hasPreferredOptions.value;
});

const selectedCapacityWindow = computed(() => {
    return props.availableSlots.find((slot) => {
        return Number(slot.capacity_window_id ?? slot.id) === Number(bookingForm.capacity_window_id);
    }) ?? null;
});

const selectedOption = computed(() => {
    return props.availableOptions.find((option) => {
        return option.id === bookingForm.preferred_option_id;
    }) ?? null;
});

const selectedCapacityWindowLabel = computed(() => {
    if (!selectedCapacityWindow.value) {
        return null;
    }

    return `${formatDate(selectedCapacityWindow.value.starts_at)} · ${formatTime(selectedCapacityWindow.value.starts_at)} – ${formatTime(selectedCapacityWindow.value.ends_at)}`;
});

const selectedOptionLabel = computed(() => {
    if (!selectedOption.value) {
        return null;
    }

    return `${formatDate(selectedOption.value.date)}, ${selectedOption.value.period_label}`;
});

const selectedRequestDateLabel = computed(() => {
    if (!bookingForm.preferred_date) {
        return null;
    }

    return formatDate(bookingForm.preferred_date);
});

const isGeneralRequestSelected = computed(() => {
    return bookingForm.mode === 'appointment_request'
        && bookingForm.request_type === 'general';
});

const canContinueFromServices = computed(() => {
    return hasSelectedServices.value;
});

const canContinueFromAvailability = computed(() => {
    if (bookingForm.mode === 'exact_slot') {
        return Boolean(bookingForm.capacity_window_id);
    }

    if (bookingForm.mode === 'appointment_request' && bookingForm.request_type === 'preferred_period') {
        return Boolean(bookingForm.preferred_option_id);
    }

    if (bookingForm.mode === 'appointment_request' && bookingForm.request_type === 'general') {
        return Boolean(bookingForm.preferred_date);
    }

    return false;
});

const canSubmit = computed(() => {
    if (bookingForm.processing) {
        return false;
    }

    if (!selectedServiceIds.value.length) {
        return false;
    }

    if (!canContinueFromAvailability.value) {
        return false;
    }

    if (!bookingForm.patient_name.trim()) {
        return false;
    }

    if (!bookingForm.patient_email.trim()) {
        return false;
    }

    return true;
});

const submitButtonLabel = computed(() => {
    if (bookingForm.mode === 'exact_slot') {
        return 'Rezervovať termín';
    }

    if (bookingForm.request_type === 'preferred_period') {
        return 'Odoslať požiadavku na termín';
    }

    return 'Odoslať požiadavku';
});

const availabilityHeading = computed(() => {
    if (hasExactSlots.value) {
        return 'Vyberte konkrétny termín';
    }

    if (hasPreferredOptions.value) {
        return 'Vyberte deň a časť dňa';
    }

    if (shouldShowGeneralRequest.value) {
        return 'Odoslať požiadavku';
    }

    return 'Momentálne bez dostupnosti';
});

const availabilityText = computed(() => {
    if (hasExactSlots.value) {
        return 'Tento termín si môžete rezervovať hneď.';
    }

    if (hasPreferredOptions.value) {
        return 'Vyberte dostupný deň a časť dňa. Presný čas vám následne potvrdíme.';
    }

    if (shouldShowGeneralRequest.value) {
        return 'Pre vybrané služby nie je dostupný online výber termínu. Môžete nám poslať požiadavku.';
    }

    return 'Skúste zmeniť dátum alebo vybrať inú kombináciu služieb.';
});

const pagination = computed(() => {
    return props.availableOptionsPagination ?? null;
});

const hasPagination = computed(() => {
    return pagination.value && pagination.value.last_page > 1;
});

const paginatorFirst = computed(() => {
    if (!pagination.value) {
        return 0;
    }

    return (pagination.value.current_page - 1) * pagination.value.per_page;
});

const primaryContact = computed(() => {
    const contacts = props.branch.contacts ?? [];

    return contacts.find((contact) => contact.is_primary)
        ?? contacts.find((contact) => ['phone', 'booking_phone'].includes(contact.type))
        ?? contacts.find((contact) => contact.type === 'email')
        ?? contacts[0]
        ?? null;
});

watch(selectedServiceIds, (value) => {
    bookingForm.service_ids = value;
    clearAvailabilitySelection();
});

const toDateString = (date) => {
    if (!date) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatDate = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
};

const formatTime = (value) => {
    if (!value) {
        return '';
    }

    return value.slice(11, 16);
};

const clearAvailabilitySelection = () => {
    bookingForm.mode = '';
    bookingForm.request_type = '';
    bookingForm.capacity_window_id = '';
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
};

const goToStep = (step) => {
    if (step === 2 && !canContinueFromServices.value) {
        return;
    }

    if (step === 3 && !canContinueFromAvailability.value) {
        return;
    }

    currentStep.value = step;
};

const goToAvailabilityStep = () => {
    if (!canContinueFromServices.value) {
        return;
    }

    applyFilters(1, 2);
};

const goToPatientStep = () => {
    if (!canContinueFromAvailability.value) {
        return;
    }

    currentStep.value = 3;
};

const applyFilters = (pageNumber = 1, nextStep = currentStep.value) => {
    router.get(route('public.branch.booking', props.branch.slug), {
        services: selectedServiceIds.value,
        date: toDateString(dateValue.value) || toDateString(today),
        page: pageNumber,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            currentStep.value = nextStep;
        },
    });
};

const onPageChange = (event) => {
    const pageNumber = event.page + 1;

    applyFilters(pageNumber, 2);
};

const selectCapacityWindow = (capacityWindow) => {
    bookingForm.mode = 'exact_slot';
    bookingForm.request_type = '';
    bookingForm.capacity_window_id = capacityWindow.capacity_window_id ?? capacityWindow.id;
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
    bookingForm.capacity_window_id = '';
};

const selectGeneralRequest = () => {
    const preferredDate = toDateString(dateValue.value);

    if (!preferredDate) {
        return;
    }

    bookingForm.mode = 'appointment_request';
    bookingForm.request_type = 'general';
    bookingForm.capacity_window_id = '';
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = preferredDate;
    bookingForm.preferred_period = '';
};

const resetBookingFlow = () => {
    currentStep.value = 1;
    selectedServiceIds.value = [];
    dateValue.value = null;
    clearAvailabilitySelection();

    bookingForm.reset(
        'mode',
        'request_type',
        'service_ids',
        'capacity_window_id',
        'preferred_option_id',
        'preferred_date',
        'preferred_period',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_note',
        'website',
        'form_started_at',
    );

    bookingForm.form_started_at = Date.now();
};

const submitBooking = () => {
    bookingForm.service_ids = selectedServiceIds.value;

    bookingForm.post(route('public.branch.booking.store', props.branch.slug), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            submittedSuccessfully.value = true;
            resetBookingFlow();
        },
    });
};
</script>

<template>
    <PublicBranchLayout :branch="branch">
        <Head :title="`Objednanie | ${branch.name}`" />

        <section
            v-if="submittedSuccessfully || flashSuccess"
            class="py-12"
        >
            <div class="mx-auto max-w-2xl">
                <div class="space-y-6 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">
                        ✓
                    </div>

                    <div class="space-y-2">
                        <h1 class="text-heading font-semibold text-dark">
                            Ďakujeme, vašu požiadavku spracujeme v čo najkratšom čase.
                        </h1>

                        <p class="text-normal leading-7 text-accent">
                            {{ flashSuccess || 'Vaše objednanie sme prijali. Potvrdenie vám pošleme podľa zvoleného typu termínu.' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <Link :href="homeHref">
                            <Button label="Späť na hlavnú stránku" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <template v-else>
            <section class="space-y-4">
                <div class="space-y-2">
                    <h1 class="text-heading font-semibold text-dark">
                        Objednanie termínu
                    </h1>

                    <p class="max-w-2xl text-normal leading-7 text-accent">
                        Vyberte služby, zvoľte dostupný termín a doplňte kontaktné údaje.
                    </p>
                </div>
            </section>

            <section class="grid gap-8 py-8 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-8">
                    <div
                        v-if="currentStep === 1"
                        class="flex flex-col"
                    >
                        <div class="space-y-5">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Ktoré služby by ste radi rezervovali? <span class="text-red-500">*</span>
                                </label>

                                <MultiSelect
                                    v-model="selectedServiceIds"
                                    :options="serviceOptions"
                                    option-label="name"
                                    option-value="id"
                                    display="chip"
                                    filter
                                    class="w-full"
                                    placeholder="Vyberte služby"
                                    empty-message="Žiadne služby"
                                    empty-filter-message="Nenašli sa žiadne služby"
                                >
                                    <template #option="{ option }">
                                        <div class="flex w-full items-center justify-between gap-4">
                                            <span>
                                                <span class="block font-semibold">
                                                    {{ option.name }}
                                                </span>

                                                <span
                                                    v-if="option.duration_minutes"
                                                    class="block text-xs text-accent"
                                                >
                                                    {{ option.duration_minutes }} min
                                                </span>
                                            </span>
                                        </div>
                                    </template>
                                </MultiSelect>
                            </div>
                        </div>

                        <div class="mt-auto flex justify-end pt-6">
                            <Button
                                label="Pokračovať na výber termínu"
                                :disabled="!canContinueFromServices"
                                @click="goToAvailabilityStep"
                            />
                        </div>
                    </div>

                    <div
                        v-if="currentStep === 2"
                        class="space-y-5"
                    >
                        <div class="space-y-1">
                            <h2 class="text-normal font-semibold text-dark">
                                {{ availabilityHeading }}
                            </h2>

                            <p class="text-sm text-accent">
                                {{ availabilityText }}
                            </p>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-if="hasExactSlots"
                                class="space-y-3"
                            >
                                <button
                                    v-for="capacityWindow in availableSlots"
                                    :key="capacityWindow.capacity_window_id ?? capacityWindow.id"
                                    type="button"
                                    class="w-full rounded-md border px-4 py-3 text-left transition"
                                    :class="Number(bookingForm.capacity_window_id) === Number(capacityWindow.capacity_window_id ?? capacityWindow.id)
                                        ? 'border-accent bg-accent text-white'
                                        : 'border-soft bg-white text-dark hover:bg-soft'"
                                    @click="selectCapacityWindow(capacityWindow)"
                                >
                                    <span class="flex items-center justify-between gap-4">
                                        <span>
                                            <span class="block font-semibold">
                                                {{ formatDate(capacityWindow.starts_at) }} · {{ formatTime(capacityWindow.starts_at) }} – {{ formatTime(capacityWindow.ends_at) }}
                                            </span>

                                            <span
                                                class="mt-1 block text-xs"
                                                :class="Number(bookingForm.capacity_window_id) === Number(capacityWindow.capacity_window_id ?? capacityWindow.id) ? 'text-white/80' : 'text-accent'"
                                            >
                                                {{ capacityWindow.free_capacity ?? capacityWindow.available_count }} voľné miesta z {{ capacityWindow.capacity }}
                                            </span>
                                        </span>

                                        <Tag
                                            :value="Number(bookingForm.capacity_window_id) === Number(capacityWindow.capacity_window_id ?? capacityWindow.id) ? 'Vybrané' : 'Vybrať'"
                                        />
                                    </span>
                                </button>
                            </div>

                            <div
                                v-else-if="hasPreferredOptions"
                                class="space-y-3"
                            >
                                <button
                                    v-for="option in availableOptions"
                                    :key="option.id"
                                    type="button"
                                    class="w-full rounded-md border px-4 py-3 text-left transition"
                                    :class="bookingForm.preferred_option_id === option.id
                                        ? 'border-accent bg-accent text-white'
                                        : 'border-soft bg-white text-dark hover:bg-soft'"
                                    @click="selectOption(option)"
                                >
                                    <span class="flex items-center justify-between gap-4">
                                        <span>
                                            <span class="block font-semibold">
                                                {{ formatDate(option.date) }}
                                            </span>

                                            <span class="block">
                                                {{ option.period_label }}
                                            </span>

                                            <span
                                                class="mt-1 block text-xs"
                                                :class="bookingForm.preferred_option_id === option.id ? 'text-white/80' : 'text-accent'"
                                            >
                                                Presný čas vám potvrdíme.
                                            </span>
                                        </span>

                                        <Tag
                                            :value="bookingForm.preferred_option_id === option.id ? 'Vybrané' : 'Vybrať'"
                                        />
                                    </span>
                                </button>

                                <Paginator
                                    v-if="hasPagination"
                                    :first="paginatorFirst"
                                    :rows="pagination.per_page"
                                    :total-records="pagination.total"
                                    @page="onPageChange"
                                />
                            </div>

                            <div
                                v-else-if="shouldShowGeneralRequest"
                                class="space-y-3"
                            >
                                <div
                                    class="w-full rounded-md border px-4 py-4 text-left transition"
                                    :class="isGeneralRequestSelected
                                        ? 'border-accent bg-accent text-white'
                                        : 'border-soft bg-white text-dark hover:bg-soft'"
                                >
                                    <div class="space-y-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="font-semibold">
                                                    Odoslať požiadavku na termín
                                                </p>

                                                <p
                                                    class="mt-1 text-sm"
                                                    :class="isGeneralRequestSelected ? 'text-white/80' : 'text-accent'"
                                                >
                                                    Vyberte preferovaný dátum a sestra vám navrhne dostupný termín.
                                                </p>
                                            </div>

                                            <Tag
                                                :value="isGeneralRequestSelected ? 'Vybrané' : 'Vybrať'"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="mb-2 block text-sm font-medium"
                                                :class="isGeneralRequestSelected ? 'text-white' : 'text-dark'"
                                            >
                                                Preferovaný dátum
                                            </label>

                                            <DatePicker
                                                v-model="dateValue"
                                                date-format="dd.mm.yy"
                                                :min-date="today"
                                                :disabled-days="disabledWeekdays"
                                                class="w-full"
                                                placeholder="Vyberte dátum"
                                                @date-select="selectGeneralRequest"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-else
                                class="text-center text-accent"
                            >
                                Pre vybrané služby momentálne nie je dostupná online možnosť.
                            </p>

                            <div class="flex flex-wrap justify-end gap-3">
                                <Button
                                    label="Späť na služby"
                                    severity="secondary"
                                    outlined
                                    @click="goToStep(1)"
                                />

                                <Button
                                    label="Pokračovať na údaje"
                                    :disabled="!canContinueFromAvailability"
                                    @click="goToPatientStep"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="currentStep === 3"
                        class="space-y-5"
                    >
                        <div class="space-y-1">
                            <h2 class="text-normal font-semibold text-dark">
                                Údaje pacienta
                            </h2>

                            <p class="text-sm text-accent">
                                Údaje použijeme na potvrdenie objednania.
                            </p>
                        </div>

                        <form
                            class="space-y-5"
                            @submit.prevent="submitBooking"
                        >
                            <div
                                class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden"
                                aria-hidden="true"
                            >
                                <label>
                                    Website
                                    <InputText
                                        v-model="bookingForm.website"
                                        tabindex="-1"
                                        autocomplete="off"
                                    />
                                </label>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Meno <span class="text-red-500">*</span>
                                </label>

                                <InputText
                                    v-model="bookingForm.patient_name"
                                    class="w-full"
                                />

                                <small
                                    v-if="bookingForm.errors.patient_name"
                                    class="text-red-600"
                                >
                                    {{ bookingForm.errors.patient_name }}
                                </small>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Email <span class="text-red-500">*</span>
                                    </label>

                                    <InputText
                                        v-model="bookingForm.patient_email"
                                        type="email"
                                        class="w-full"
                                    />

                                    <small
                                        v-if="bookingForm.errors.patient_email"
                                        class="text-red-600"
                                    >
                                        {{ bookingForm.errors.patient_email }}
                                    </small>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Telefón
                                    </label>

                                    <InputText
                                        v-model="bookingForm.patient_phone"
                                        class="w-full"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Poznámka
                                </label>

                                <Textarea
                                    v-model="bookingForm.patient_note"
                                    rows="4"
                                    class="w-full"
                                    placeholder="Môžete doplniť dôležité informácie k objednaniu."
                                />
                            </div>

                            <div class="space-y-1">
                                <small
                                    v-if="bookingForm.errors.form"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.form }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.mode"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.mode }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.request_type"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.request_type }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.service_ids"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.service_ids }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.capacity_window_id"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.capacity_window_id }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.preferred_option_id"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.preferred_option_id }}
                                </small>
                            </div>

                            <div class="flex flex-wrap justify-end gap-3">
                                <Button
                                    label="← Späť na termín"
                                    severity="secondary"
                                    outlined
                                    type="button"
                                    @click="goToStep(2)"
                                />

                                <Button
                                    :label="submitButtonLabel"
                                    type="submit"
                                    :loading="bookingForm.processing"
                                    :disabled="!canSubmit"
                                />
                            </div>
                        </form>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-md border border-soft bg-accent p-5 text-white">
                        <h2 class="text-normal font-semibold text-white">
                            Súhrn
                        </h2>

                        <div class="mt-4 space-y-3 text-normal text-white">
                            <p>
                                <span class="font-semibold">
                                    Služby:
                                </span>

                                {{ selectedServicesLabel }}
                            </p>

                            <p v-if="totalDurationMinutes">
                                <strong class="text-white">
                                    Trvanie:
                                </strong>

                                približne {{ totalDurationMinutes }} min
                            </p>

                            <p v-if="selectedCapacityWindowLabel">
                                <strong class="text-white">
                                    Termín:
                                </strong>

                                {{ selectedCapacityWindowLabel }}
                            </p>

                            <p v-if="selectedOptionLabel">
                                <strong class="text-white">
                                    Možnosť:
                                </strong>

                                {{ selectedOptionLabel }}
                            </p>

                            <p v-if="isGeneralRequestSelected">
                                <strong class="text-white">
                                    Typ:
                                </strong>

                                požiadavka na termín
                            </p>
                        </div>
                    </div>
                </aside>
            </section>
        </template>
    </PublicBranchLayout>
</template>