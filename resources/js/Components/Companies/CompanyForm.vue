<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    prefix: {
        type: String,
        default: '',
    },
    submitLabel: {
        type: String,
        default: 'Uložiť',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    showSubmit: {
        type: Boolean,
        default: true,
    },
    showSlugPreview: {
        type: Boolean,
        default: true,
    },
    showActiveToggle: {
        type: Boolean,
        default: false,
    },
    triedToSubmit: {
        type: Boolean,
        default: false,
    },
    requiredFields: {
        type: Array,
        default: () => [
            'legal_name',
            'id_number',
            'tax_id',
            'address_line_1',
            'city',
            'postal_code',
            'country',
        ],
    },
});

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

const slugify = (value) => {
    return (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const generatedSlug = computed(() => slugify(fieldValue('legal_name')));

const requiredMark = computed(() => {
    return props.requiredFields.length ? '*' : '';
});

const googleMapsApiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
const addressLine1Ref = ref(null);
let autocomplete = null;
let placeChangedListener = null;

const mapsAutocompleteEnabled = computed(() => Boolean(googleMapsApiKey));

let mapsScriptLoadingPromise = null;

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

const setField = (name, value) => {
    props.form[fieldKey(name)] = value;
};

const extractComponent = (components, type) => {
    return components.find((component) => component.types.includes(type))?.long_name ?? '';
};

const applySelectedPlace = (place) => {
    const components = place?.address_components ?? [];

    const streetNumber = extractComponent(components, 'street_number');
    const route = extractComponent(components, 'route');
    const subpremise = extractComponent(components, 'subpremise');
    const city = extractComponent(components, 'locality')
        || extractComponent(components, 'postal_town')
        || extractComponent(components, 'administrative_area_level_2');
    const postalCode = extractComponent(components, 'postal_code');
    const region = extractComponent(components, 'administrative_area_level_1');
    const country = extractComponent(components, 'country');

    const line1 = [streetNumber, route].filter(Boolean).join(' ').trim();

    if (line1) {
        setField('address_line_1', line1);
    } else if (place?.name) {
        setField('address_line_1', place.name);
    }

    if (subpremise && !String(fieldValue('address_line_2') || '').trim()) {
        setField('address_line_2', subpremise);
    }

    if (city) {
        setField('city', city);
    }

    if (postalCode) {
        setField('postal_code', postalCode);
    }

    if (region) {
        setField('region', region);
    }

    if (country) {
        setField('country', country);
    }
};

const initAddressAutocomplete = () => {
    const inputEl = addressLine1Ref.value?.$el?.querySelector('input')
        ?? addressLine1Ref.value?.$el
        ?? addressLine1Ref.value
        ?? null;

    if (!inputEl || !window.google?.maps?.places || typeof inputEl !== 'object' || !('tagName' in inputEl)) {
        return;
    }

    autocomplete = new window.google.maps.places.Autocomplete(inputEl, {
        types: ['address'],
        fields: ['address_components', 'name'],
    });

    placeChangedListener = autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        applySelectedPlace(place);
    });
};

onMounted(async () => {
    if (!mapsAutocompleteEnabled.value) {
        return;
    }

    try {
        await nextTick();
        await loadGoogleMapsPlacesScript();
        initAddressAutocomplete();
    } catch {
        // silently degrade to normal text input when API is unavailable
    }
});

onBeforeUnmount(() => {
    if (placeChangedListener) {
        window.google?.maps?.event?.removeListener(placeChangedListener);
    }

    placeChangedListener = null;
    autocomplete = null;
});
</script>

<template>
    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6 flex flex-col gap-2">
                <p class="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">
                    Firma
                </p>

                <h2 class="text-xl font-semibold text-slate-900">
                    Identifikácia firmy
                </h2>

                <p class="max-w-2xl text-sm leading-6 text-slate-600">
                    Základné právne údaje firmy. Tieto informácie sa používajú na identifikáciu firmy v systéme.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Oficiálny názov
                        <span v-if="isRequired('legal_name')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('legal_name')]"
                        :class="inputClasses('legal_name')"
                        placeholder="Napr. Klinická psychológia Lučenec s.r.o."
                    />

                    <p
                        v-if="errorMessage('legal_name', 'Oficiálny názov')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('legal_name', 'Oficiálny názov') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        IČO
                        <span v-if="isRequired('id_number')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('id_number')]"
                        :class="inputClasses('id_number')"
                        placeholder="12345678"
                    />

                    <p
                        v-if="errorMessage('id_number', 'IČO')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('id_number', 'IČO') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        DIČ
                        <span v-if="isRequired('tax_id')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('tax_id')]"
                        :class="inputClasses('tax_id')"
                        placeholder="2021234567"
                    />

                    <p
                        v-if="errorMessage('tax_id', 'DIČ')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('tax_id', 'DIČ') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        IČ DPH
                        <span v-if="isRequired('vat_id')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('vat_id')]"
                        :class="inputClasses('vat_id')"
                        placeholder="SK2021234567"
                    />

                    <p
                        v-if="errorMessage('vat_id', 'IČ DPH')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('vat_id', 'IČ DPH') }}
                    </p>
                </div>

                <div v-if="showSlugPreview">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Slug
                    </label>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        {{ generatedSlug || 'slug-sa-zobrazí-po-zadaní-názvu' }}
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        Slug sa vytvorí automaticky z oficiálneho názvu.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">
                    Adresa firmy
                </h2>

                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                    Registrovaná alebo fakturačná adresa firmy.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Adresa 1
                        <span v-if="isRequired('address_line_1')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        ref="addressLine1Ref"
                        v-model="form[fieldKey('address_line_1')]"
                        :class="inputClasses('address_line_1')"
                        placeholder="Ulica a číslo"
                        autocomplete="address-line1"
                    />

                    <p
                        v-if="mapsAutocompleteEnabled"
                        class="mt-1 text-xs text-slate-500"
                    >
                        Začni písať adresu a vyber návrh z Google Maps.
                    </p>

                    <p
                        v-if="errorMessage('address_line_1', 'Adresa 1')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('address_line_1', 'Adresa 1') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Adresa 2
                        <span v-if="isRequired('address_line_2')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('address_line_2')]"
                        :class="inputClasses('address_line_2')"
                        placeholder="Budova, poschodie, doplnok"
                    />

                    <p
                        v-if="errorMessage('address_line_2', 'Adresa 2')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('address_line_2', 'Adresa 2') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Mesto
                        <span v-if="isRequired('city')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('city')]"
                        :class="inputClasses('city')"
                        placeholder="Lučenec"
                    />

                    <p
                        v-if="errorMessage('city', 'Mesto')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('city', 'Mesto') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        PSČ
                        <span v-if="isRequired('postal_code')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('postal_code')]"
                        :class="inputClasses('postal_code')"
                        placeholder="984 01"
                    />

                    <p
                        v-if="errorMessage('postal_code', 'PSČ')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('postal_code', 'PSČ') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Región
                        <span v-if="isRequired('region')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('region')]"
                        :class="inputClasses('region')"
                        placeholder="Banskobystrický kraj"
                    />

                    <p
                        v-if="errorMessage('region', 'Región')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('region', 'Región') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Krajina
                        <span v-if="isRequired('country')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('country')]"
                        :class="inputClasses('country')"
                        placeholder="Slovensko"
                    />

                    <p
                        v-if="errorMessage('country', 'Krajina')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('country', 'Krajina') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">
                    Kontaktné údaje
                </h2>

                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">
                    Nepovinné kontaktné údaje, ktoré môžeš použiť vo verejnom profile alebo interne.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Email
                        <span v-if="isRequired('email')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('email')]"
                        :class="inputClasses('email')"
                        placeholder="info@firma.sk"
                    />

                    <p
                        v-if="errorMessage('email', 'Email')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('email', 'Email') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Telefón
                        <span v-if="isRequired('phone')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('phone')]"
                        :class="inputClasses('phone')"
                        placeholder="+421..."
                    />

                    <p
                        v-if="errorMessage('phone', 'Telefón')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('phone', 'Telefón') }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Web
                        <span v-if="isRequired('website')" class="text-red-500">
                            {{ requiredMark }}
                        </span>
                    </label>

                    <InputText
                        v-model="form[fieldKey('website')]"
                        :class="inputClasses('website')"
                        placeholder="https://..."
                    />

                    <p
                        v-if="errorMessage('website', 'Web')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errorMessage('website', 'Web') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="showActiveToggle"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Stav firmy
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">
                        Nové firmy sú štandardne aktívne. Tento prepínač používaj hlavne pri editácii.
                    </p>
                </div>

                <label class="flex cursor-pointer items-center gap-3">
                    <input
                        v-model="form[fieldKey('is_active')]"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                    >

                    <span class="text-sm font-medium text-slate-700">
                        Aktívna firma
                    </span>
                </label>
            </div>
        </div>

        <div
            v-if="showSubmit"
            class="flex items-center justify-end rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <Button
                type="submit"
                :label="submitLabel"
                :loading="loading"
            />
        </div>
    </div>
</template>