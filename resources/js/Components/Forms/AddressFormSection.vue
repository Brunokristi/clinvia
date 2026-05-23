<script setup>
import AutoComplete from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: 'Adresa',
    },
    description: {
        type: String,
        default: '',
    },
});

const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const addressSuggestions = ref([]);
const selectedAddress = ref(props.form.address_line_1 || '');
const isLoadingAddressSuggestions = ref(false);
const addressSuggestionError = ref('');

let autocompleteService = null;
let autocompleteSessionToken = null;
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

const applySelectedPlace = (place) => {
    const components = place?.addressComponents ?? place?.address_components ?? [];

    const streetNumber = extractComponent(components, 'street_number');
    const route = extractComponent(components, 'route');
    const subpremise = extractComponent(components, 'subpremise');

    const city = extractComponent(components, 'locality')
        || extractComponent(components, 'postal_town')
        || extractComponent(components, 'sublocality')
        || extractComponent(components, 'sublocality_level_1');

    const postalCode = extractComponent(components, 'postal_code');
    const region = extractComponent(components, 'administrative_area_level_1');
    const country = extractComponent(components, 'country');

    const addressLine1 = [route, streetNumber].filter(Boolean).join(' ').trim();

    if (addressLine1) {
        props.form.address_line_1 = addressLine1;
    } else if (place?.formattedAddress) {
        props.form.address_line_1 = place.formattedAddress;
    } else if (place?.displayName) {
        props.form.address_line_1 = place.displayName;
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

    if (region) {
        props.form.region = region;
    }

    if (country) {
        props.form.country = country;
    }
};

const searchAddressSuggestions = async (event) => {
    const query = String(event.query || '').trim();

    addressSuggestionError.value = '';

    if (!mapsAutocompleteEnabled.value || !query || query.length < 3) {
        addressSuggestions.value = [];
        return;
    }

    if (!ensurePlacesServices()) {
        addressSuggestions.value = [];
        return;
    }

    isLoadingAddressSuggestions.value = true;

    try {
        const predictions = await new Promise((resolve, reject) => {
            autocompleteService.getPlacePredictions(
                {
                    input: query,
                    types: ['address'],
                    componentRestrictions: {
                        country: ['sk'],
                    },
                    sessionToken: autocompleteSessionToken,
                },
                (results, status) => {
                    if (
                        status !== window.google.maps.places.PlacesServiceStatus.OK
                        && status !== window.google.maps.places.PlacesServiceStatus.ZERO_RESULTS
                    ) {
                        reject(new Error(status));
                        return;
                    }

                    resolve(results ?? []);
                },
            );
        });

        addressSuggestions.value = predictions.map((prediction) => ({
            label: prediction.description,
            placeId: prediction.place_id,
            mainText: prediction.structured_formatting?.main_text ?? prediction.description,
            secondaryText: prediction.structured_formatting?.secondary_text ?? '',
        }));
    } catch {
        addressSuggestions.value = [];
        addressSuggestionError.value = 'Návrhy adries sa nepodarilo načítať.';
    } finally {
        isLoadingAddressSuggestions.value = false;
    }
};

const selectAddressSuggestion = async (event) => {
    const suggestion = event.value;

    if (!suggestion?.placeId) {
        return;
    }

    selectedAddress.value = suggestion.label;
    props.form.address_line_1 = suggestion.label;

    try {
        const place = await lookupPlaceDetails(suggestion.placeId);

        applySelectedPlace(place);

        autocompleteSessionToken = null;
    } catch {
        addressSuggestionError.value = 'Adresu sa nepodarilo automaticky doplniť.';
    }
};

onMounted(async () => {
    if (!mapsAutocompleteEnabled.value) {
        return;
    }

    try {
        await loadGoogleMapsPlacesScript();
        ensurePlacesServices();
    } catch {
        addressSuggestionError.value = 'Google Maps sa nepodarilo načítať.';
    }
});

watch(
    () => props.form.address_line_1,
    (value) => {
        const nextValue = value || '';

        if (selectedAddress.value !== nextValue) {
            selectedAddress.value = nextValue;
        }
    },
);

watch(selectedAddress, (value) => {
    if (typeof value === 'string' && props.form.address_line_1 !== value) {
        props.form.address_line_1 = value;
    }
});

onBeforeUnmount(() => {
    autocompleteService = null;
    autocompleteSessionToken = null;
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-slate-900">
                {{ title }}
            </h2>

            <p
                v-if="description"
                class="mt-1 max-w-2xl text-sm leading-6 text-slate-600"
            >
                {{ description }}
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Address line 1
                </label>

                <AutoComplete
                    v-model="selectedAddress"
                    :suggestions="addressSuggestions"
                    option-label="label"
                    class="w-full"
                    input-class="w-full"
                    panel-class="rounded-xl border border-slate-200 shadow-lg"
                    placeholder="Start typing an address"
                    autocomplete="address-line1"
                    :loading="isLoadingAddressSuggestions"
                    :disabled="!mapsAutocompleteEnabled"
                    @complete="searchAddressSuggestions"
                    @item-select="selectAddressSuggestion"
                >
                    <template #option="{ option }">
                        <div class="flex items-start gap-3 py-1">
                            <i class="pi pi-map-marker mt-1 text-slate-400" />

                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-slate-800">
                                    {{ option.mainText }}
                                </div>

                                <div
                                    v-if="option.secondaryText"
                                    class="truncate text-xs text-slate-500"
                                >
                                    {{ option.secondaryText }}
                                </div>
                            </div>
                        </div>
                    </template>
                </AutoComplete>

                <p
                    v-if="addressSuggestionError"
                    class="mt-1 text-xs text-red-600"
                >
                    {{ addressSuggestionError }}
                </p>

                <p
                    v-else-if="mapsAutocompleteEnabled"
                    class="mt-1 text-xs text-slate-500"
                >
                    Start typing and choose an address from the list.
                </p>

                <p
                    v-if="form.errors.address_line_1"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.address_line_1 }}
                </p>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Address line 2
                </label>

                <InputText
                    v-model="form.address_line_2"
                    class="w-full"
                    placeholder="Apartment, floor, suite, etc."
                    autocomplete="address-line2"
                />

                <p
                    v-if="form.errors.address_line_2"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.address_line_2 }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    City
                </label>

                <InputText
                    v-model="form.city"
                    class="w-full"
                    placeholder="Handlová"
                    autocomplete="address-level2"
                />

                <p
                    v-if="form.errors.city"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.city }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    ZIP
                </label>

                <InputText
                    v-model="form.postal_code"
                    class="w-full"
                    placeholder="972 51"
                    autocomplete="postal-code"
                />

                <p
                    v-if="form.errors.postal_code"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.postal_code }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Region
                </label>

                <InputText
                    v-model="form.region"
                    class="w-full"
                    placeholder="Trenčiansky kraj"
                    autocomplete="address-level1"
                />

                <p
                    v-if="form.errors.region"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.region }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Country
                </label>

                <InputText
                    v-model="form.country"
                    class="w-full"
                    placeholder="Slovensko"
                    autocomplete="country-name"
                />

                <p
                    v-if="form.errors.country"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.country }}
                </p>
            </div>
        </div>
    </div>
</template>