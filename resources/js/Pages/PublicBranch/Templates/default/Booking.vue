<script setup>
import BirthNumberInput from '@/Components/Forms/BirthNumberInput.vue';
import PhoneNumberInput from '@/Components/Forms/PhoneInput.vue';
import PublicBranchLayout from '@/Layouts/PublicBranchLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Paginator from 'primevue/paginator';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
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
    isDirectBookingEligible: {
        type: Boolean,
        default: false,
    },
    flowInfoText: {
        type: Object,
        default: () => ({
            anonymous_request: '',
            verified_direct: '',
        }),
    },
    verifiedPatientContext: {
        type: Object,
        default: () => ({
            verified_patient_email: '',
        }),
    },
});

const page = usePage();
const toast = useToast();

const today = new Date();
today.setHours(0, 0, 0, 0);

const currentStep = ref(1);
const submittedSuccessfully = ref(false);
const submittedRequestSummary = ref(null);
const dateValue = ref(null);
const selectedServiceIds = ref([...props.selectedServiceIds]);
const availabilityPanel = ref('exact_slot');

const requesterPhoneValue = ref('');
const requesterPhoneCountryCode = ref('SK');
const patientPhoneValue = ref('');
const patientPhoneCountryCode = ref('SK');

const visibleAvailabilityLimit = ref(5);
const availabilityBatchSize = 5;

const serviceErrorKeys = ['service_id', 'service_ids'];
const availabilityErrorKeys = [
    'branch_id',
    'starts_at',
    'requested_starts_at',
    'preferred_starts_at',
    'capacity_window_id',
    'preferred_option_id',
    'preferred_date',
    'preferred_period',
    'request_type',
];
const consentErrorKeys = ['consent', 'privacy_consent'];
const selfNameErrorKeys = ['requester_name', 'patient_name'];
const selfEmailErrorKeys = ['requester_email', 'patient_email'];
const selfPhoneErrorKeys = ['requester_phone', 'patient_phone'];

const bookingForm = useForm({
    request_type: '',
    service_ids: [...props.selectedServiceIds],
    capacity_window_id: '',
    preferred_option_id: '',
    preferred_date: '',
    preferred_period: '',
    is_for_someone_else: false,
    requester_name: '',
    requester_email: '',
    requester_phone: '',
    patient_name: '',
    patient_email: '',
    patient_phone: '',
    verified_patient_email: props.verifiedPatientContext?.verified_patient_email ?? '',
    patient_birth_number: '',
    patient_note: '',
    privacy_consent: false,

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

const visibleAvailableSlots = computed(() => {
    return props.availableSlots.slice(0, visibleAvailabilityLimit.value);
});

const hiddenAvailableSlotsCount = computed(() => {
    return Math.max(0, props.availableSlots.length - visibleAvailabilityLimit.value);
});

const canShowMoreAvailableSlots = computed(() => {
    return hiddenAvailableSlotsCount.value > 0;
});

const visibleAvailableOptions = computed(() => {
    return props.availableOptions.slice(0, visibleAvailabilityLimit.value);
});

const hiddenAvailableOptionsCount = computed(() => {
    return Math.max(0, props.availableOptions.length - visibleAvailabilityLimit.value);
});

const canShowMoreAvailableOptions = computed(() => {
    return hiddenAvailableOptionsCount.value > 0;
});

const showMoreAvailability = () => {
    visibleAvailabilityLimit.value += availabilityBatchSize;
};

const canUseGeneralRequest = computed(() => {
    return props.canSubmitGeneralRequest && hasSelectedServices.value;
});

const hasRequestAvailability = computed(() => {
    return hasPreferredOptions.value || canUseGeneralRequest.value;
});

const shouldShowGeneralRequest = computed(() => {
    return canUseGeneralRequest.value;
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
    return bookingForm.request_type === 'general';
});

const isExactSlotSelected = computed(() => {
    return Boolean(bookingForm.capacity_window_id);
});

const canContinueFromServices = computed(() => {
    return hasSelectedServices.value;
});

const canContinueFromAvailability = computed(() => {
    if (isExactSlotSelected.value) {
        return Boolean(bookingForm.capacity_window_id);
    }

    if (bookingForm.request_type === 'preferred_period') {
        return Boolean(bookingForm.preferred_option_id);
    }

    if (bookingForm.request_type === 'general') {
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

    if (!bookingForm.privacy_consent) {
        return false;
    }

    if (!canContinueFromAvailability.value) {
        return false;
    }

    if (!bookingForm.patient_name.trim()) {
        return false;
    }

    if (bookingForm.is_for_someone_else) {
        if (!bookingForm.requester_name.trim()) {
            return false;
        }

        if (!bookingForm.requester_email.trim()) {
            return false;
        }

        if (!bookingForm.requester_phone.trim()) {
            return false;
        }

        return true;
    }

    if (!bookingForm.patient_email.trim()) {
        return false;
    }

    if (!bookingForm.patient_phone.trim()) {
        return false;
    }

    return true;
});

const normalizedErrorEntries = computed(() => {
    return Object.entries(bookingForm.errors ?? {}).flatMap(([field, value]) => {
        if (Array.isArray(value)) {
            return value.map((message) => [field, String(message)]);
        }

        if (typeof value === 'string' && value.trim() !== '') {
            return [[field, value]];
        }

        return [];
    });
});

const firstValidationError = computed(() => {
    return normalizedErrorEntries.value[0]?.[1] ?? '';
});

const findFirstError = (keys) => {
    for (const key of keys) {
        const message = bookingForm.errors[key];

        if (typeof message === 'string' && message.trim() !== '') {
            return message;
        }
    }

    return '';
};

const serviceError = computed(() => findFirstError(serviceErrorKeys));
const availabilityError = computed(() => findFirstError(availabilityErrorKeys));
const selfNameError = computed(() => findFirstError(selfNameErrorKeys));
const selfEmailError = computed(() => findFirstError(selfEmailErrorKeys));
const selfPhoneError = computed(() => findFirstError(selfPhoneErrorKeys));
const consentError = computed(() => findFirstError(consentErrorKeys));

const generalErrorEntries = computed(() => {
    const inlineKeys = new Set([
        ...serviceErrorKeys,
        ...availabilityErrorKeys,
        ...consentErrorKeys,
        'requester_name',
        'requester_email',
        'requester_phone',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_birth_number',
        'patient_note',
        'note',
        'form',
    ]);

    return normalizedErrorEntries.value.filter(([field]) => !inlineKeys.has(field));
});

const syncStepWithErrors = (errors = bookingForm.errors ?? {}) => {
    const fields = Object.keys(errors ?? {});

    if (fields.some((field) => serviceErrorKeys.includes(field))) {
        currentStep.value = 1;

        return;
    }

    if (fields.some((field) => availabilityErrorKeys.includes(field))) {
        currentStep.value = 2;

        return;
    }

    if (fields.length > 0) {
        currentStep.value = 3;
    }
};

const submitButtonLabel = computed(() => {
    if (isExactSlotSelected.value && props.isDirectBookingEligible) {
        return 'Potvrdiť rezerváciu';
    }

    if (bookingForm.request_type === 'preferred_period') {
        return 'Odoslať požiadavku na termín';
    }

    return 'Odoslať požiadavku';
});

const availabilityHeading = computed(() => {
    if (hasExactSlots.value && hasRequestAvailability.value) {
        return 'Vyberte termín alebo odošlite požiadavku';
    }

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
        return 'Vyberte termín. Systém automaticky rozhodne, či bude termín potvrdený okamžite alebo vytvorí požiadavku.';
    }

    if (hasPreferredOptions.value) {
        return 'Vyberte dostupný deň a časť dňa. Presný čas vám následne potvrdíme.';
    }

    if (shouldShowGeneralRequest.value) {
        return 'Pre vybrané služby nie je dostupný online výber termínu. Môžete nám poslať požiadavku.';
    }

    return 'Skúste zmeniť dátum alebo vybrať inú kombináciu služieb.';
});

const submissionInfoText = computed(() => {
    if (isExactSlotSelected.value && props.isDirectBookingEligible) {
        return props.flowInfoText?.verified_direct || '';
    }

    return props.flowInfoText?.anonymous_request || '';
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

watch(
    () => [hasExactSlots.value, hasRequestAvailability.value],
    ([exactSlotsAvailable, requestAvailable]) => {
        if (exactSlotsAvailable) {
            availabilityPanel.value = 'exact_slot';

            return;
        }

        if (requestAvailable) {
            availabilityPanel.value = 'appointment_request';

            return;
        }

        availabilityPanel.value = 'exact_slot';
    },
    { immediate: true },
);

watch(
    () => props.selectedDate,
    (value) => {
        if (!value) {
            dateValue.value = null;

            return;
        }

        const parsedDate = new Date(`${value}T00:00:00`);

        dateValue.value = Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
    },
    { immediate: true },
);

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
    bookingForm.request_type = '';
    bookingForm.capacity_window_id = '';
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';

    visibleAvailabilityLimit.value = 5;
};

const goToStep = (step) => {
    if (step === 2 && !canContinueFromServices.value) {
        return;
    }

    if (step === 3 && !canContinueFromAvailability.value) {
        return;
    }

    currentStep.value = step;
    scrollToTop();
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

const scrollToTop = () => {
    requestAnimationFrame(() => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    });
};

const applyFilters = (pageNumber = 1, nextStep = currentStep.value) => {
    const selectedDate = toDateString(dateValue.value) || props.selectedDate || toDateString(today);

    router.get(route('public.branch.booking', props.branch.slug), {
        services: selectedServiceIds.value,
        date: selectedDate,
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
    availabilityPanel.value = 'exact_slot';

    bookingForm.request_type = 'group_event_request';
    bookingForm.capacity_window_id = capacityWindow.capacity_window_id ?? capacityWindow.id;
    bookingForm.preferred_option_id = '';
    bookingForm.preferred_date = '';
    bookingForm.preferred_period = '';
};

const selectOption = (option) => {
    availabilityPanel.value = 'appointment_request';

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

    availabilityPanel.value = 'appointment_request';

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

    requesterPhoneValue.value = '';
    requesterPhoneCountryCode.value = 'SK';
    patientPhoneValue.value = '';
    patientPhoneCountryCode.value = 'SK';

    bookingForm.reset(
        'request_type',
        'service_ids',
        'capacity_window_id',
        'preferred_option_id',
        'preferred_date',
        'preferred_period',
        'is_for_someone_else',
        'requester_name',
        'requester_email',
        'requester_phone',
        'patient_name',
        'patient_email',
        'patient_phone',
        'patient_birth_number',
        'patient_note',
        'privacy_consent',
        'website',
        'form_started_at',
    );
    bookingForm.form_started_at = Date.now();
};

const selectedRequestDateTimeLabel = computed(() => {
    if (selectedCapacityWindowLabel.value) {
        return selectedCapacityWindowLabel.value;
    }

    if (selectedOptionLabel.value) {
        return selectedOptionLabel.value;
    }

    if (selectedRequestDateLabel.value) {
        return `${selectedRequestDateLabel.value}`;
    }

    return 'Neuvedené';
});

const submitBooking = () => {
    bookingForm.service_ids = selectedServiceIds.value;

    const inferredRequestType = selectedCapacityWindow.value
        ? 'group_event_request'
        : 'appointment_request';

    const inferredSourceType = inferredRequestType === 'group_event_request'
        ? 'group_event'
        : 'reservation_rule';

    const selectedCapacityWindowId = selectedCapacityWindow.value
        ? Number(selectedCapacityWindow.value.capacity_window_id ?? selectedCapacityWindow.value.id)
        : null;

    const selectedCapacityWindowStartsAt = selectedCapacityWindow.value?.starts_at ?? null;
    const selectedCapacityWindowEndsAt = selectedCapacityWindow.value?.ends_at ?? null;

    bookingForm.transform((data) => ({
        ...data,
        request_type: inferredRequestType,
        source_type: inferredSourceType,
        branch_id: props.branch.id,
        service_id: selectedServiceIds.value.length === 1
            ? Number(selectedServiceIds.value[0])
            : null,
        service_ids: selectedServiceIds.value.map((id) => Number(id)),
        starts_at: selectedCapacityWindowStartsAt,
        capacity_window_id: selectedCapacityWindowId,
        group_event_id: inferredRequestType === 'group_event_request' ? selectedCapacityWindowId : null,
        reservation_rule_id: null,
        group_event_occurrence_original_start_at: selectedCapacityWindow.value?.recurrence_original_starts_at ?? null,
        requested_starts_at: selectedCapacityWindowStartsAt
            ?? (data.preferred_date ? `${data.preferred_date} 00:00:00` : null),
        requested_ends_at: selectedCapacityWindowEndsAt,
        requested_group_event_starts_at: inferredRequestType === 'group_event_request' ? selectedCapacityWindowStartsAt : null,
        requested_group_event_ends_at: inferredRequestType === 'group_event_request' ? selectedCapacityWindowEndsAt : null,
        consent: data.privacy_consent,
        note: data.patient_note,
        requester_name: data.is_for_someone_else
            ? data.requester_name
            : data.patient_name,
        requester_email: data.is_for_someone_else
            ? data.requester_email
            : data.patient_email,
        requester_phone: data.is_for_someone_else
            ? data.requester_phone
            : data.patient_phone,
    })).post(route('public.branch.booking.store', props.branch.slug), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const confirmationEmail = bookingForm.is_for_someone_else
                ? (bookingForm.requester_email || bookingForm.patient_email || '')
                : (bookingForm.patient_email || bookingForm.requester_email || '');

            submittedRequestSummary.value = {
                serviceLabel: selectedServicesLabel.value,
                dateTimeLabel: selectedRequestDateTimeLabel.value,
                confirmationEmail,
                isForSomeoneElse: Boolean(bookingForm.is_for_someone_else),
            };

            toast.add({
                severity: 'success',
                summary: 'Požiadavka odoslaná',
                detail: 'Požiadavka bola odoslaná. Skontrolujte si email a potvrďte ju.',
                life: 5000,
            });

            submittedSuccessfully.value = true;
            resetBookingFlow();
        },
        onError: (errors) => {
            syncStepWithErrors(errors);

            const firstError = Object.values(errors ?? {}).find((value) => {
                return typeof value === 'string' && value.trim() !== '';
            });

            if (!errors || Object.keys(errors).length === 0) {
                toast.add({
                    severity: 'error',
                    summary: 'Požiadavku sa nepodarilo odoslať',
                    detail: 'Skontrolujte údaje a skúste to prosím znova.',
                    life: 5000,
                });

                return;
            }

            toast.add({
                severity: 'warn',
                summary: 'Formulár obsahuje chyby',
                detail: firstError || 'Opravte vyznačené polia a skúste odoslať formulár znova.',
                life: 5000,
            });
        },
    });
};

watch(() => bookingForm.errors, (errors) => {
    syncStepWithErrors(errors);
}, { deep: true });
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
                            Požiadavka bola odoslaná
                        </h1>

                        <p class="text-normal leading-7 text-accent">
                            {{ flashSuccess || 'Na zadaný email sme Vám poslali potvrdzovací odkaz. Prosím, otvorte email a potvrďte požiadavku. Po potvrdení ju skontrolujeme a termín Vám potvrdíme.' }}
                        </p>

                        <p
                            v-if="submittedRequestSummary?.isForSomeoneElse"
                            class="text-sm text-accent"
                        >
                            Potvrdzovací email sme poslali kontaktnej osobe.
                        </p>
                    </div>

                    <div class="mx-auto w-full max-w-xl rounded-md border border-soft bg-soft p-4 text-left">
                        <dl class="space-y-2 text-sm text-accent">
                            <div>
                                <dt class="font-semibold text-dark">Služba / požiadavka</dt>
                                <dd>{{ submittedRequestSummary?.serviceLabel || selectedServicesLabel }}</dd>
                            </div>

                            <div>
                                <dt class="font-semibold text-dark">Požadovaný termín</dt>
                                <dd>{{ submittedRequestSummary?.dateTimeLabel || selectedRequestDateTimeLabel }}</dd>
                            </div>

                            <div>
                                <dt class="font-semibold text-dark">Email pre potvrdenie</dt>
                                <dd>{{ submittedRequestSummary?.confirmationEmail || 'Neuvedené' }}</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-xs text-accent">
                            Ak email nevidíte, skontrolujte priečinok Spam alebo Nevyžiadaná pošta.
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

                                <small
                                    v-if="serviceError"
                                    class="mt-2 block text-red-600"
                                >
                                    {{ serviceError }}
                                </small>
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
                                    v-for="capacityWindow in visibleAvailableSlots"
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
                                                Prihlásiť sa na skupinový termín.
                                            </span>

                                            <span
                                                class="mt-1 block text-xs"
                                                :class="Number(bookingForm.capacity_window_id) === Number(capacityWindow.capacity_window_id ?? capacityWindow.id) ? 'text-white/80' : 'text-accent'"
                                            >
                                                {{ capacityWindow.free_capacity ?? capacityWindow.available_count }} voľné miesta z {{ capacityWindow.capacity }}
                                            </span>
                                        </span>

                                        <Tag
                                            :value="Number(bookingForm.capacity_window_id) === Number(capacityWindow.capacity_window_id ?? capacityWindow.id) ? 'Vybrané' : 'Prihlásiť sa'"
                                        />
                                    </span>
                                </button>

                                <div
                                    v-if="canShowMoreAvailableSlots"
                                    class="flex justify-center pt-2"
                                >
                                    <Button
                                        type="button"
                                        :label="`Viac termínov (${hiddenAvailableSlotsCount})`"
                                        text
                                        @click="showMoreAvailability"
                                    />
                                </div>

                                <small
                                    v-if="availabilityError && isExactSlotSelected"
                                    class="block text-red-600"
                                >
                                    {{ availabilityError }}
                                </small>
                            </div>

                            <div
                                v-if="hasRequestAvailability"
                                class="space-y-3"
                            >
                                <div
                                    v-if="hasPreferredOptions"
                                    class="space-y-3"
                                >
                                    <button
                                        v-for="option in visibleAvailableOptions"
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
                                                    Požiadať o termín podľa preferencie.
                                                </span>
                                            </span>

                                            <Tag
                                                :value="bookingForm.preferred_option_id === option.id ? 'Vybrané' : 'Požiadať o termín'"
                                            />
                                        </span>
                                    </button>

                                    <div
                                        v-if="canShowMoreAvailableOptions"
                                        class="flex justify-center pt-2"
                                    >
                                        <Button
                                            type="button"
                                            :label="`Viac termínov (${hiddenAvailableOptionsCount})`"
                                            outlined
                                            @click="showMoreAvailability"
                                        />
                                    </div>

                                    <Paginator
                                        v-if="hasPagination"
                                        :first="paginatorFirst"
                                        :rows="pagination.per_page"
                                        :total-records="pagination.total"
                                        @page="onPageChange"
                                    />
                                </div>

                                <div
                                    v-if="shouldShowGeneralRequest"
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

                                                <small
                                                    v-if="availabilityError && isGeneralRequestSelected"
                                                    class="mt-2 block text-red-600"
                                                >
                                                    {{ availabilityError }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <small
                                v-if="availabilityError && !isGeneralRequestSelected && !isExactSlotSelected"
                                class="block text-red-600"
                            >
                                {{ availabilityError }}
                            </small>

                            <p
                                v-if="!hasExactSlots && !hasRequestAvailability"
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
                            <p
                                v-if="submissionInfoText"
                                class="rounded-md border border-soft bg-soft px-4 py-3 text-sm text-accent"
                            >
                                {{ submissionInfoText }}
                            </p>

                            <div
                                v-if="generalErrorEntries.length"
                                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                            >
                                <p class="font-medium">
                                    Niektoré chyby sa nepodarilo priradiť ku konkrétnemu poľu:
                                </p>

                                <ul class="mt-2 list-disc pl-5">
                                    <li
                                        v-for="([field, message], index) in generalErrorEntries"
                                        :key="`${field}-${index}`"
                                    >
                                        {{ message }}
                                    </li>
                                </ul>
                            </div>

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
                                    Objednávam
                                </label>

                                <div class="grid grid-cols-2 gap-2 rounded-md border border-soft bg-white p-1">
                                    <button
                                        type="button"
                                        class="rounded px-3 py-2 text-sm font-medium transition"
                                        :class="!bookingForm.is_for_someone_else ? 'bg-accent text-white' : 'text-dark hover:bg-soft'"
                                        @click="bookingForm.is_for_someone_else = false"
                                    >
                                        Seba
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded px-3 py-2 text-sm font-medium transition"
                                        :class="bookingForm.is_for_someone_else ? 'bg-accent text-white' : 'text-dark hover:bg-soft'"
                                        @click="bookingForm.is_for_someone_else = true"
                                    >
                                        Inú osobu
                                    </button>
                                </div>
                            </div>

                            <div v-if="bookingForm.is_for_someone_else" class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Vaše meno <span class="text-red-500">*</span>
                                    </label>

                                    <InputText
                                        v-model="bookingForm.requester_name"
                                        class="w-full"
                                    />

                                    <small
                                        v-if="bookingForm.errors.requester_name"
                                        class="text-red-600"
                                    >
                                        {{ bookingForm.errors.requester_name }}
                                    </small>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Váš email <span class="text-red-500">*</span>
                                    </label>

                                    <InputText
                                        v-model="bookingForm.requester_email"
                                        type="email"
                                        class="w-full"
                                    />

                                    <small
                                        v-if="bookingForm.errors.requester_email"
                                        class="text-red-600"
                                    >
                                        {{ bookingForm.errors.requester_email }}
                                    </small>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Váš telefón <span class="text-red-500">*</span>
                                    </label>

                                    <PhoneNumberInput
                                        v-model="requesterPhoneValue"
                                        v-model:country-code="requesterPhoneCountryCode"
                                        v-model:full-value="bookingForm.requester_phone"
                                        :invalid="Boolean(bookingForm.errors.requester_phone)"
                                    />

                                    <small
                                        v-if="bookingForm.errors.requester_phone"
                                        class="text-red-600"
                                    >
                                        {{ bookingForm.errors.requester_phone }}
                                    </small>
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-dark">
                                    Meno pacienta <span class="text-red-500">*</span>
                                </label>

                                <InputText
                                    v-model="bookingForm.patient_name"
                                    class="w-full"
                                />

                                <small
                                    v-if="bookingForm.is_for_someone_else ? bookingForm.errors.patient_name : selfNameError"
                                    class="text-red-600"
                                >
                                    {{ bookingForm.is_for_someone_else ? bookingForm.errors.patient_name : selfNameError }}
                                </small>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div v-if="!bookingForm.is_for_someone_else">
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Email <span class="text-red-500">*</span>
                                    </label>

                                    <InputText
                                        v-model="bookingForm.patient_email"
                                        type="email"
                                        class="w-full"
                                    />

                                    <small
                                        v-if="selfEmailError"
                                        class="text-red-600"
                                    >
                                        {{ selfEmailError }}
                                    </small>
                                </div>

                                <div v-if="!bookingForm.is_for_someone_else">
                                    <label class="mb-2 block text-sm font-medium text-dark">
                                        Telefón <span class="text-red-500">*</span>
                                    </label>

                                    <PhoneNumberInput
                                        v-model="patientPhoneValue"
                                        v-model:country-code="patientPhoneCountryCode"
                                        v-model:full-value="bookingForm.patient_phone"
                                        :invalid="Boolean(selfPhoneError)"
                                    />

                                    <small
                                        v-if="selfPhoneError"
                                        class="text-red-600"
                                    >
                                        {{ selfPhoneError }}
                                    </small>
                                </div>

                                <div>
                                    <label
                                        for="patient_birth_number"
                                        class="mb-2 block text-sm font-medium text-dark"
                                    >
                                        Rodné číslo
                                    </label>

                                    <BirthNumberInput
                                        id="patient_birth_number"
                                        v-model="bookingForm.patient_birth_number"
                                        class="w-full"
                                        :invalid="Boolean(bookingForm.errors.patient_birth_number)"
                                        placeholder="900101/1234"
                                    />

                                    <small
                                        v-if="bookingForm.errors.patient_birth_number"
                                        class="mt-1 block text-red-600"
                                    >
                                        {{ bookingForm.errors.patient_birth_number }}
                                    </small>
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

                            <div class="space-y-2">
                                <div class="flex items-start gap-3">
                                    <Checkbox
                                        v-model="bookingForm.privacy_consent"
                                        input-id="privacy_consent"
                                        binary
                                        :invalid="Boolean(consentError)"
                                        class="mt-0.5 shrink-0"
                                    />

                                    <label
                                        for="privacy_consent"
                                        class="cursor-pointer text-sm leading-6 text-dark"
                                    >
                                        Súhlasím so spracovaním osobných údajov na účely vybavenia objednania.
                                        <span class="text-red-500">*</span>
                                    </label>
                                </div>

                                <small
                                    v-if="consentError"
                                    class="block text-red-600"
                                >
                                    {{ consentError }}
                                </small>
                            </div>

                            <div class="space-y-1">
                                <small
                                    v-if="bookingForm.errors.form"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.form }}
                                </small>

                                <small
                                    v-if="bookingForm.errors.request_type"
                                    class="block text-red-600"
                                >
                                    {{ bookingForm.errors.request_type }}
                                </small>

                                <small
                                    v-if="firstValidationError && !bookingForm.errors.form"
                                    class="block text-red-600"
                                >
                                    {{ firstValidationError }}
                                </small>
                            </div>

                            <div class="flex flex-wrap justify-end gap-3">
                                <Button
                                    label="Späť na výber termínu"
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