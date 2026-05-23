<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { computed } from 'vue';
import AddressFormSection from '@/Components/Forms/AddressFormSection.vue';

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

            <div class="grid gap-5 md:grid-cols-3">
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium text-slate-700">
                        Názov
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
            </div>
        </div>

        <AddressFormSection
            :form="form"
            title="Adresa firmy"
            description="Registrovaná alebo fakturačná adresa firmy."
            :show-region="true"
        />

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