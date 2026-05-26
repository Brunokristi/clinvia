<script setup>
import AutoComplete from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    prefix: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: 'Adresa',
    },
    description: {
        type: String,
        default: '',
    },
    showRegion: {
        type: Boolean,
        default: true,
    },
    requiredFields: {
        type: Array,
        default: () => [
            'address_line_1',
            'city',
            'postal_code',
            'country',
        ],
    },
    triedToSubmit: {
        type: Boolean,
        default: false,
    },
});

const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

const fieldKey = (name) => `${props.prefix}${name}`;

const fieldValue = (name) => props.form[fieldKey(name)];

const fieldErrors = (name) => props.form.errors?.[fieldKey(name)];

const isRequired = (name) => props.requiredFields.includes(name);

const showRequiredError = (name) => {
    return props.triedToSubmit
        && isRequired(name)
        && !String(fieldValue(name) || '').trim();
};

const errorMessage = (name, label) => {
    if (fieldErrors(name)) {
        return fieldErrors(name);
    }

    if (showRequiredError(name)) {
        return `${label} je povinné pole.`;
    }

    return '';
};

const hasError = (name) => {
    return Boolean(fieldErrors(name) || showRequiredError(name));
};

const inputClasses = (name) => {
    return [
        'w-full',
        hasError(name) ? 'p-invalid' : '',
    ];
};

const addressSuggestions = ref([]);
const selectedAddress = ref(props.form[fieldKey('address_line_1')] || '');
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
        props.form[fieldKey('address_line_1')] = addressLine1;
    } else if (place?.formattedAddress) {
        props.form[fieldKey('address_line_1')] = place.formattedAddress;
    } else if (place?.displayName) {
        props.form[fieldKey('address_line_1')] = place.displayName;
    }

    if (subpremise && !String(props.form[fieldKey('address_line_2')] || '').trim()) {
        props.form[fieldKey('address_line_2')] = subpremise;
    }

    if (city) {
        props.form[fieldKey('city')] = city;
    }

    if (postalCode) {
        props.form[fieldKey('postal_code')] = postalCode;
    }

    if (region) {
        props.form[fieldKey('region')] = region;
    }

    if (country) {
        props.form[fieldKey('country')] = country;
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
    props.form[fieldKey('address_line_1')] = suggestion.label;

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
    () => props.form[fieldKey('address_line_1')],
    (value) => {
        const nextValue = value || '';

        if (selectedAddress.value !== nextValue) {
            selectedAddress.value = nextValue;
        }
    },
);

watch(selectedAddress, (value) => {
    if (typeof value === 'string' && props.form[fieldKey('address_line_1')] !== value) {
        props.form[fieldKey('address_line_1')] = value;
    }
});

onBeforeUnmount(() => {
    autocompleteService = null;
    autocompleteSessionToken = null;
});
</script>

<template>
    <FormSection
        :title="title"
        :description="description"
        columns="md:grid-cols-2"
    >
        <FormField
            label="Adresa"
            :for="fieldKey('address_line_1')"
            :required="isRequired('address_line_1')"
            :error="errorMessage('address_line_1', 'Adresa')"
            span="md:col-span-2"
        >
            <AutoComplete
                :id="fieldKey('address_line_1')"
                v-model="selectedAddress"
                :suggestions="addressSuggestions"
                option-label="label"
                :class="inputClasses('address_line_1')"
                input-class="w-full"
                placeholder="Začnite písať adresu"
                autocomplete="address-line1"
                :loading="isLoadingAddressSuggestions"
                :disabled="!mapsAutocompleteEnabled"
                @complete="searchAddressSuggestions"
                @item-select="selectAddressSuggestion"
            >
                <template #option="{ option }">
                    <div class="flex items-start gap-3">
                        <i class="pi pi-map-marker mt-1 text-accent/70" />

                        <div class="min-w-0">
                            <div class="truncate text-normal font-medium text-accent">
                                {{ option.mainText }}
                            </div>

                            <div
                                v-if="option.secondaryText"
                                class="truncate text-xs text-accent/70"
                            >
                                {{ option.secondaryText }}
                            </div>
                        </div>
                    </div>
                </template>
            </AutoComplete>

            <Message
                v-if="addressSuggestionError"
                severity="error"
                size="small"
                variant="simple"
                class="mt-2"
            >
                {{ addressSuggestionError }}
            </Message>
        </FormField>

        <FormField
            label="Doplnok adresy"
            :for="fieldKey('address_line_2')"
            :required="isRequired('address_line_2')"
            :error="errorMessage('address_line_2', 'Doplnok adresy')"
            span="md:col-span-2"
        >
            <InputText
                :id="fieldKey('address_line_2')"
                v-model="form[fieldKey('address_line_2')]"
                :class="inputClasses('address_line_2')"
                autocomplete="address-line2"
            />
        </FormField>

        <FormField
            label="Mesto"
            :for="fieldKey('city')"
            :required="isRequired('city')"
            :error="errorMessage('city', 'Mesto')"
        >
            <InputText
                :id="fieldKey('city')"
                v-model="form[fieldKey('city')]"
                :class="inputClasses('city')"
                autocomplete="address-level2"
            />
        </FormField>

        <FormField
            label="PSČ"
            :for="fieldKey('postal_code')"
            :required="isRequired('postal_code')"
            :error="errorMessage('postal_code', 'PSČ')"
        >
            <InputText
                :id="fieldKey('postal_code')"
                v-model="form[fieldKey('postal_code')]"
                :class="inputClasses('postal_code')"
                autocomplete="postal-code"
            />
        </FormField>

        <FormField
            v-if="showRegion"
            label="Kraj"
            :for="fieldKey('region')"
            :required="isRequired('region')"
            :error="errorMessage('region', 'Kraj')"
        >
            <InputText
                :id="fieldKey('region')"
                v-model="form[fieldKey('region')]"
                :class="inputClasses('region')"
ň                autocomplete="address-level1"
            />
        </FormField>

        <FormField
            label="Krajina"
            :for="fieldKey('country')"
            :required="isRequired('country')"
            :error="errorMessage('country', 'Krajina')"
        >
            <InputText
                :id="fieldKey('country')"
                v-model="form[fieldKey('country')]"
                :class="inputClasses('country')"
                autocomplete="country-name"
            />
        </FormField>
    </FormSection>
</template>