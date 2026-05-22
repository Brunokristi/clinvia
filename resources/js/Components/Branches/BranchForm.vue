<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
    companies: {
        type: Array,
        default: () => [],
    },
    showCompanySelect: {
        type: Boolean,
        default: true,
    },
    showActiveToggle: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Uložiť',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const branchTypes = [
    { label: 'Ambulancia', value: 'ambulance' },
    { label: 'Centrum', value: 'center' },
    { label: 'Kancelária', value: 'office' },
    { label: 'Iné', value: 'other' },
];

const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const addressSuggestions = ref([]);
const showAddressSuggestions = ref(false);
const isLoadingAddressSuggestions = ref(false);
const addressSuggestionError = ref('');
const suppressAddressWatch = ref(false);

let autocompleteService = null;
let autocompleteSessionToken = null;
let addressSuggestionDebounceTimer = null;
let mapsScriptLoadingPromise = null;

const mapsAutocompleteEnabled = computed(() => Boolean(googleMapsApiKey));

const loadGoogleMapsPlacesScript = () => {
    if (window.google?.maps?.places) {
        return Promise.resolve();
    }

    if (mapsScriptLoadingPromise) {
        return mapsScriptLoadingPromise;
    }

    mapsScriptLoadingPromise = new Promise((resolve, reject) => {
        const existingScript = document.querySelector('script[data-google-maps-places]');

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Google Maps script load failed.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsApiKey)}&libraries=places&v=weekly&loading=async`;
        script.async = true;
        script.defer = true;
        script.dataset.googleMapsPlaces = 'true';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Google Maps script load failed.'));

        document.head.appendChild(script);
    });

    return mapsScriptLoadingPromise;
};

const ensurePlacesServices = () => {
    if (!window.google?.maps?.places) {
        return false;
    }

    if (!autocompleteService && window.google.maps.places.AutocompleteService) {
        autocompleteService = new window.google.maps.places.AutocompleteService();
    }

    if (!autocompleteSessionToken && window.google.maps.places.AutocompleteSessionToken) {
        autocompleteSessionToken = new window.google.maps.places.AutocompleteSessionToken();
    }

    return Boolean(autocompleteService);
};

const extractComponent = (components, type) => {
    const component = components.find((item) => item.types.includes(type));

    return component?.longText
        || component?.long_name
        || '';
};

const clearAddressSuggestions = () => {
    addressSuggestions.value = [];
    showAddressSuggestions.value = false;
    addressSuggestionError.value = '';
};

const lookupPlaceDetails = async (placeId) => {
    if (!window.google?.maps?.places?.Place) {
        throw new Error('Place API unavailable');
    }

    const place = new window.google.maps.places.Place({
        id: placeId,
    });

    await place.fetchFields({
        fields: [
            'addressComponents',
            'displayName',
            'formattedAddress',
        ],
    });

    return place;
};

const fetchAddressSuggestions = async (query) => {
    if (!ensurePlacesServices()) {
        return;
    }

    isLoadingAddressSuggestions.value = true;
    addressSuggestionError.value = '';

    try {
        const response = await new Promise((resolve, reject) => {
            autocompleteService.getPlacePredictions(
                {
                    input: query,
                    types: ['address'],
                    componentRestrictions: {
                        country: ['sk'],
                    },
                    sessionToken: autocompleteSessionToken,
                },
                (predictions, status) => {
                    if (
                        status !== window.google.maps.places.PlacesServiceStatus.OK
                        && status !== window.google.maps.places.PlacesServiceStatus.ZERO_RESULTS
                    ) {
                        reject(new Error(status));
                        return;
                    }

                    resolve(predictions ?? []);
                },
            );
        });

        addressSuggestions.value = response.map((prediction) => ({
            label: prediction.description,
            value: prediction.place_id,
            place_id: prediction.place_id,
            description: prediction.description,
            main_text: prediction.structured_formatting?.main_text ?? prediction.description,
            secondary_text: prediction.structured_formatting?.secondary_text ?? '',
        }));
    } catch {
        addressSuggestions.value = [];
        addressSuggestionError.value = 'Návrhy adries sa nepodarilo načítať.';
    } finally {
        isLoadingAddressSuggestions.value = false;
    }
};

const applySelectedPlace = (place) => {
    const components = place?.addressComponents ?? place?.address_components ?? [];

    const streetNumber = extractComponent(components, 'street_number');
    const route = extractComponent(components, 'route');
    const subpremise = extractComponent(components, 'subpremise');
    const city = extractComponent(components, 'locality')
        || extractComponent(components, 'postal_town')
        || extractComponent(components, 'administrative_area_level_2');
    const postalCode = extractComponent(components, 'postal_code');
    const country = extractComponent(components, 'country');

    const line1 = [streetNumber, route].filter(Boolean).join(' ').trim();

    if (line1) {
        props.form.address_line_1 = line1;
    } else if (place?.displayName) {
        props.form.address_line_1 = place.displayName;
    } else if (place?.name) {
        props.form.address_line_1 = place.name;
    }

    if (subpremise && !String(props.form.address_line_2 || '').trim()) {
        props.form.address_line_2 = subpremise;
    }

    if (city) {
        props.form.city = city;
    }

    if (postalCode) {
        props.form.postal_code = postalCode;
    }

    if (country) {
        props.form.country = country;
    }
};

const selectAddressSuggestion = async (suggestion) => {
    if (!suggestion || typeof suggestion === 'string') {
        return;
    }

    suppressAddressWatch.value = true;
    props.form.address_line_1 = suggestion.description;
    showAddressSuggestions.value = false;

    try {
        const place = await lookupPlaceDetails(suggestion.place_id);
        applySelectedPlace(place);

        autocompleteSessionToken = null;
    } catch {
        // Keep selected text even if details lookup fails.
    }
};

const handleAddressInput = (event) => {
    const value = event?.target?.value ?? '';

    showAddressSuggestions.value = true;
    props.form.address_line_1 = value;
};

watch(
    () => props.form.address_line_1,
    (newValue) => {
        if (suppressAddressWatch.value) {
            suppressAddressWatch.value = false;
            return;
        }

        if (addressSuggestionDebounceTimer) {
            clearTimeout(addressSuggestionDebounceTimer);
        }

        const query = String(newValue || '').trim();

        if (!mapsAutocompleteEnabled.value || !query || query.length < 3) {
            clearAddressSuggestions();
            return;
        }

        addressSuggestionDebounceTimer = setTimeout(() => {
            fetchAddressSuggestions(query);
        }, 250);
    },
);

onMounted(async () => {
    if (!mapsAutocompleteEnabled.value) {
        return;
    }

    try {
        await loadGoogleMapsPlacesScript();
        ensurePlacesServices();
    } catch {
        // Silently degrade to normal text input when API is unavailable.
    }
});

onBeforeUnmount(() => {
    if (addressSuggestionDebounceTimer) {
        clearTimeout(addressSuggestionDebounceTimer);
    }

    autocompleteService = null;
    autocompleteSessionToken = null;
});
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-lg font-semibold">
                Základné údaje
            </h2>

            <div v-if="company" class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                    Firma
                </p>

                <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ company.legal_name }}
                </p>
            </div>

            <div v-else-if="showCompanySelect" class="mb-5">
                <label class="mb-1 block text-sm font-medium">
                    Firma
                </label>

                <Select
                    v-model="form.company_id"
                    :options="companies"
                    optionLabel="legal_name"
                    optionValue="id"
                    placeholder="Vyber firmu"
                    class="w-full"
                />

                <p v-if="form.errors.company_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.company_id }}
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Typ pobočky
                    </label>

                    <Select
                        v-model="form.type"
                        :options="branchTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Vyber typ"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Názov
                    </label>

                    <InputText v-model="form.name" class="w-full" />

                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>
                </div>
            </div>

            <div class="mt-5">
                <label class="mb-1 block text-sm font-medium">
                    Popis
                </label>

                <Textarea v-model="form.description" class="w-full" rows="5" />
            </div>
        </div>

        <div class="rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-lg font-semibold">
                Adresa
            </h2>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Adresa 1
                    </label>

                    <InputText
                        v-model="form.address_line_1"
                        class="w-full"
                        placeholder="Začnite písať adresu"
                        autocomplete="address-line1"
                        @input="handleAddressInput"
                    />

                    <div
                        v-if="showAddressSuggestions && addressSuggestions.length"
                        class="mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <button
                            v-for="suggestion in addressSuggestions"
                            :key="suggestion.place_id"
                            type="button"
                            class="flex w-full items-start gap-3 border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                            @mousedown.prevent="selectAddressSuggestion(suggestion)"
                        >
                            <i class="pi pi-map-marker mt-0.5 text-slate-400" />

                            <div>
                                <div class="text-sm font-medium text-slate-800">
                                    {{ suggestion.main_text }}
                                </div>

                                <div
                                    v-if="suggestion.secondary_text"
                                    class="text-xs text-slate-500"
                                >
                                    {{ suggestion.secondary_text }}
                                </div>
                            </div>
                        </button>
                    </div>

                    <p
                        v-if="addressSuggestionError"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ addressSuggestionError }}
                    </p>

                    <p
                        v-else-if="isLoadingAddressSuggestions"
                        class="mt-1 text-xs text-slate-500"
                    >
                        Hľadám adresy...
                    </p>

                    <p
                        v-else-if="mapsAutocompleteEnabled"
                        class="mt-1 text-xs text-slate-500"
                    >
                        Začnite písať adresu a vyberte návrh zo zoznamu.
                    </p>

                    <p v-if="form.errors.address_line_1" class="mt-1 text-sm text-red-600">
                        {{ form.errors.address_line_1 }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Adresa 2
                    </label>

                    <InputText v-model="form.address_line_2" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Mesto
                    </label>

                    <InputText v-model="form.city" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        PSČ
                    </label>

                    <InputText v-model="form.postal_code" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Krajina
                    </label>

                    <InputText v-model="form.country" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Web
                    </label>

                    <InputText v-model="form.website" class="w-full" />
                </div>
            </div>
        </div>

        <div v-if="showActiveToggle" class="rounded-lg border bg-white p-5">
            <h2 class="mb-4 text-lg font-semibold">
                Nastavenia
            </h2>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" binary inputId="is_active" />

                <label for="is_active">
                    Aktívna pobočka
                </label>
            </div>
        </div>

        <Button
            type="submit"
            :label="submitLabel"
            :loading="loading"
        />
    </div>
</template>