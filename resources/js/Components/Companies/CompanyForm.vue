<script setup>
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { computed, ref, watch } from 'vue';

import AddressFormSection from '@/Components/Forms/AddressFormSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import PhoneInput from '@/Components/Forms/PhoneInput.vue';

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
            'company_id_number',
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

const phoneCountryCode = ref('SK');
const phoneLocalValue = ref('');
const phoneFullValue = ref('');

const getInitialPhoneLocalValue = () => {
    const value = String(props.form[fieldKey('phone')] || '').trim();

    return value
        .replace(/^\+421\s?/, '')
        .replace(/^\+420\s?/, '')
        .replace(/^\+43\s?/, '')
        .replace(/^\+36\s?/, '')
        .replace(/^\+48\s?/, '')
        .trim();
};

const getInitialPhoneCountryCode = () => {
    const value = String(props.form[fieldKey('phone')] || '').trim();

    if (value.startsWith('+420')) {
        return 'CZ';
    }

    if (value.startsWith('+43')) {
        return 'AT';
    }

    if (value.startsWith('+36')) {
        return 'HU';
    }

    if (value.startsWith('+48')) {
        return 'PL';
    }

    return 'SK';
};

phoneCountryCode.value = getInitialPhoneCountryCode();
phoneLocalValue.value = getInitialPhoneLocalValue();

watch(phoneFullValue, (value) => {
    props.form[fieldKey('phone')] = value;
});
</script>

<template>
    <FormPage
        :submit-label="submitLabel"
        :loading="loading"
        :show-submit="showSubmit"
    >
        <FormSection
            title="Základné údaje"
            description="Oficiálne údaje firmy, ktoré sa používajú v dokumentoch a verejnom profile."
        >
            <FormField
                label="Názov"
                :for="fieldKey('legal_name')"
                :required="isRequired('legal_name')"
                :error="errorMessage('legal_name', 'Oficiálny názov')"
                span="md:col-span-3"
            >
                <InputText
                    :id="fieldKey('legal_name')"
                    v-model="form[fieldKey('legal_name')]"
                    :class="inputClasses('legal_name')"
                />
            </FormField>

            <FormField
                label="IČO"
                :for="fieldKey('company_id_number')"
                    :required="isRequired('company_id_number')"
                    :error="errorMessage('company_id_number', 'IČO')"
            >
                <InputText
                        :id="fieldKey('company_id_number')"
                        v-model="form[fieldKey('company_id_number')]"
                        :class="inputClasses('company_id_number')"
                />
            </FormField>

            <FormField
                label="DIČ"
                :for="fieldKey('tax_id')"
                :required="isRequired('tax_id')"
                :error="errorMessage('tax_id', 'DIČ')"
            >
                <InputText
                    :id="fieldKey('tax_id')"
                    v-model="form[fieldKey('tax_id')]"
                    :class="inputClasses('tax_id')"
                />
            </FormField>

            <FormField
                label="IČ DPH"
                :for="fieldKey('vat_id')"
                :required="isRequired('vat_id')"
                :error="errorMessage('vat_id', 'IČ DPH')"
            >
                <InputText
                    :id="fieldKey('vat_id')"
                    v-model="form[fieldKey('vat_id')]"
                    :class="inputClasses('vat_id')"
                />
            </FormField>
        </FormSection>

        <AddressFormSection
            :form="form"
            :prefix="prefix"
            title="Adresa spoločnosti"
            description="Oficiálna adresa spoločnosti, ktorá sa používa v dokumentoch a verejnom profile."
            :show-region="true"
        />

        <FormSection
            title="Kontaktné údaje"
            description="Kontaktné údaje firmy, ktoré sa používajú v dokumentoch a verejnom profile."
        >
            <FormField
                label="Email"
                :for="fieldKey('email')"
                :required="isRequired('email')"
                :error="errorMessage('email', 'Email')"
            >
                <InputText
                    :id="fieldKey('email')"
                    v-model="form[fieldKey('email')]"
                    :class="inputClasses('email')"
                />
            </FormField>

            <FormField
                label="Telefón"
                :for="fieldKey('phone')"
                :required="isRequired('phone')"
                :error="errorMessage('phone', 'Telefón')"
            >
                <PhoneInput
                    v-model="phoneLocalValue"
                    v-model:country-code="phoneCountryCode"
                    v-model:full-value="phoneFullValue"
                    :invalid="hasError('phone')"
                />
            </FormField>

            <FormField
                label="Web"
                :for="fieldKey('website')"
                :required="isRequired('website')"
                :error="errorMessage('website', 'Web')"
            >
                <InputText
                    :id="fieldKey('website')"
                    v-model="form[fieldKey('website')]"
                    :class="inputClasses('website')"
                />
            </FormField>
        </FormSection>

        <FormSection
            v-if="showActiveToggle"
            title="Stav firmy"
            description="Nové firmy sú štandardne aktívne. Tento prepínač používaj hlavne pri editácii."
            columns="grid-cols-1"
        >
            <div class="flex items-center gap-3">
                <Checkbox
                    v-model="form[fieldKey('is_active')]"
                    :input-id="fieldKey('is_active')"
                    binary
                />

                <label
                    :for="fieldKey('is_active')"
                    class="text-sm font-medium text-accent"
                >
                    Aktívna firma
                </label>
            </div>
        </FormSection>
    </FormPage>
</template>