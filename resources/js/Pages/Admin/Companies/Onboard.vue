<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const currentStep = ref(1);
const triedToContinue = ref(false);
const triedToSubmit = ref(false);

const form = useForm({
    company_legal_name: '',
    company_id_number: '',
    company_tax_id: '',
    company_vat_id: '',
    company_address_line_1: '',
    company_address_line_2: '',
    company_city: '',
    company_postal_code: '',
    company_region: '',
    company_country: 'Slovensko',
    company_email: '',
    company_phone: '',
    company_website: '',
    company_is_active: true,

    admin_first_name: '',
    admin_last_name: '',
    admin_email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const slugify = (value) => {
    return value
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const generatedSlug = computed(() => slugify(form.company_legal_name));

const steps = [
    {
        number: 1,
        title: 'Údaje firmy',
        description: 'Identifikácia, fakturačné údaje a adresa',
    },
    {
        number: 2,
        title: 'Prvý administrátor',
        description: 'Používateľ, ktorý bude firmu spravovať',
    },
];

const isStepOne = computed(() => currentStep.value === 1);
const isStepTwo = computed(() => currentStep.value === 2);

const companyRequiredFields = computed(() => {
    return {
        company_legal_name: form.company_legal_name,
        company_id_number: form.company_id_number,
        company_tax_id: form.company_tax_id,
        company_address_line_1: form.company_address_line_1,
        company_city: form.company_city,
        company_postal_code: form.company_postal_code,
        company_country: form.company_country,
    };
});

const adminRequiredFields = computed(() => {
    return {
        admin_first_name: form.admin_first_name,
        admin_last_name: form.admin_last_name,
        admin_email: form.admin_email,
        admin_password: form.admin_password,
        admin_password_confirmation: form.admin_password_confirmation,
    };
});

const isCompanyStepValid = computed(() => {
    return Object.values(companyRequiredFields.value).every((value) => String(value).trim() !== '');
});

const isAdminStepValid = computed(() => {
    return Object.values(adminRequiredFields.value).every((value) => String(value).trim() !== '')
        && form.admin_password === form.admin_password_confirmation;
});

const passwordsDoNotMatch = computed(() => {
    return form.admin_password
        && form.admin_password_confirmation
        && form.admin_password !== form.admin_password_confirmation;
});

const showCompanyRequiredError = (field) => {
    return triedToContinue.value && !String(form[field]).trim();
};

const showAdminRequiredError = (field) => {
    return triedToSubmit.value && !String(form[field]).trim();
};

const goToStep = (stepNumber) => {
    if (stepNumber === 2 && !isCompanyStepValid.value) {
        triedToContinue.value = true;
        return;
    }

    currentStep.value = stepNumber;
};

const goToNextStep = () => {
    triedToContinue.value = true;

    if (!isCompanyStepValid.value) {
        return;
    }

    currentStep.value = 2;
};

const goToPreviousStep = () => {
    currentStep.value = 1;
};

const submit = () => {
    triedToSubmit.value = true;

    if (!isCompanyStepValid.value) {
        currentStep.value = 1;
        return;
    }

    if (!isAdminStepValid.value) {
        currentStep.value = 2;
        return;
    }

    form.post(route('companies.onboard.store'));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-8">
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                Onboarding
            </p>

            <h1 class="mt-3 text-2xl font-semibold text-slate-900">
                Vytvoriť firmu
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Najprv vyplňte povinné údaje firmy. Až potom môžete vytvoriť prvého administrátora.
            </p>
        </div>

        <div class="mb-8 max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-2">
                <button
                    v-for="step in steps"
                    :key="step.number"
                    type="button"
                    class="flex items-center gap-4 rounded-xl border p-4 text-left transition"
                    :class="[
                        currentStep === step.number
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                        step.number === 2 && !isCompanyStepValid
                            ? 'cursor-not-allowed opacity-60'
                            : ''
                    ]"
                    @click="goToStep(step.number)"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                        :class="currentStep === step.number
                            ? 'bg-white text-slate-900'
                            : 'bg-slate-100 text-slate-700'"
                    >
                        {{ step.number }}
                    </span>

                    <span>
                        <span class="block text-sm font-semibold">
                            {{ step.title }}
                        </span>

                        <span
                            class="mt-1 block text-xs"
                            :class="currentStep === step.number ? 'text-slate-200' : 'text-slate-500'"
                        >
                            {{ step.description }}
                        </span>
                    </span>
                </button>
            </div>
        </div>

        <form class="max-w-5xl space-y-6" @submit.prevent="submit">
            <section
                v-if="isStepOne"
                class="space-y-6"
            >
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">
                            Identifikácia firmy
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Základné oficiálne údaje firmy.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Oficiálny názov <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_legal_name"
                                class="w-full"
                                placeholder="Napr. Klinická psychológia Lučenec s.r.o."
                            />

                            <p
                                v-if="showCompanyRequiredError('company_legal_name')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Oficiálny názov je povinný.
                            </p>

                            <p
                                v-if="form.errors.company_legal_name"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.company_legal_name }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                IČO <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_id_number"
                                class="w-full"
                                placeholder="12345678"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_id_number')"
                                class="mt-1 text-sm text-red-600"
                            >
                                IČO je povinné.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                DIČ <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_tax_id"
                                class="w-full"
                                placeholder="2021234567"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_tax_id')"
                                class="mt-1 text-sm text-red-600"
                            >
                                DIČ je povinné.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                IČ DPH
                            </label>

                            <InputText
                                v-model="form.company_vat_id"
                                class="w-full"
                                placeholder="SK2021234567"
                            />
                        </div>

                        <div>
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
                        <h2 class="text-lg font-semibold text-slate-900">
                            Adresa firmy
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Fakturačná alebo registrovaná adresa firmy.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Adresa 1 <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_address_line_1"
                                class="w-full"
                                placeholder="Ulica a číslo"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_address_line_1')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Adresa je povinná.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Adresa 2
                            </label>

                            <InputText
                                v-model="form.company_address_line_2"
                                class="w-full"
                                placeholder="Budova, poschodie, doplnok"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Mesto <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_city"
                                class="w-full"
                                placeholder="Lučenec"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_city')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Mesto je povinné.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                PSČ <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_postal_code"
                                class="w-full"
                                placeholder="984 01"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_postal_code')"
                                class="mt-1 text-sm text-red-600"
                            >
                                PSČ je povinné.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Región
                            </label>

                            <InputText
                                v-model="form.company_region"
                                class="w-full"
                                placeholder="Banskobystrický kraj"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Krajina <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.company_country"
                                class="w-full"
                            />

                            <p
                                v-if="showCompanyRequiredError('company_country')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Krajina je povinná.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">
                            Kontaktné údaje
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Nepovinné verejné alebo interné kontakty firmy.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Email
                            </label>

                            <InputText
                                v-model="form.company_email"
                                class="w-full"
                                placeholder="info@firma.sk"
                            />

                            <p
                                v-if="form.errors.company_email"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.company_email }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Telefón
                            </label>

                            <InputText
                                v-model="form.company_phone"
                                class="w-full"
                                placeholder="+421..."
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Web
                            </label>

                            <InputText
                                v-model="form.company_website"
                                class="w-full"
                                placeholder="https://..."
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isStepTwo"
                class="space-y-6"
            >
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">
                                Firma
                            </p>

                            <p class="mt-1 text-base font-semibold text-slate-900">
                                {{ form.company_legal_name }}
                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                IČO: {{ form.company_id_number }} · DIČ: {{ form.company_tax_id }}
                            </p>
                        </div>

                        <Button
                            type="button"
                            label="Upraviť firmu"
                            severity="secondary"
                            outlined
                            size="small"
                            @click="goToPreviousStep"
                        />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">
                            Osobné údaje administrátora
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Používateľ, ktorý dostane admin prístup k tejto firme.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Meno <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.admin_first_name"
                                class="w-full"
                                placeholder="Ján"
                            />

                            <p
                                v-if="showAdminRequiredError('admin_first_name')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Meno je povinné.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Priezvisko <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.admin_last_name"
                                class="w-full"
                                placeholder="Novák"
                            />

                            <p
                                v-if="showAdminRequiredError('admin_last_name')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Priezvisko je povinné.
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Email <span class="text-red-500">*</span>
                            </label>

                            <InputText
                                v-model="form.admin_email"
                                class="w-full"
                                placeholder="admin@firma.sk"
                            />

                            <p
                                v-if="showAdminRequiredError('admin_email')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Email je povinný.
                            </p>

                            <p
                                v-if="form.errors.admin_email"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.admin_email }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">
                            Prihlasovacie údaje
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Heslo musí byť zadané dvakrát.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Heslo <span class="text-red-500">*</span>
                            </label>

                            <Password
                                v-model="form.admin_password"
                                class="w-full"
                                inputClass="w-full"
                                toggleMask
                            />

                            <p
                                v-if="showAdminRequiredError('admin_password')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Heslo je povinné.
                            </p>

                            <p
                                v-if="form.errors.admin_password"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors.admin_password }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Potvrdenie hesla <span class="text-red-500">*</span>
                            </label>

                            <Password
                                v-model="form.admin_password_confirmation"
                                class="w-full"
                                inputClass="w-full"
                                toggleMask
                                :feedback="false"
                            />

                            <p
                                v-if="showAdminRequiredError('admin_password_confirmation')"
                                class="mt-1 text-sm text-red-600"
                            >
                                Potvrdenie hesla je povinné.
                            </p>

                            <p
                                v-if="passwordsDoNotMatch"
                                class="mt-1 text-sm text-red-600"
                            >
                                Heslá sa nezhodujú.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <Button
                    v-if="isStepTwo"
                    type="button"
                    label="Späť"
                    severity="secondary"
                    outlined
                    @click="goToPreviousStep"
                />

                <div v-else />

                <div class="flex items-center gap-3">
                    <Button
                        v-if="isStepOne"
                        type="button"
                        label="Pokračovať"
                        :disabled="!isCompanyStepValid"
                        @click="goToNextStep"
                    />

                    <Button
                        v-if="isStepTwo"
                        type="submit"
                        label="Vytvoriť firmu a admina"
                        :loading="form.processing"
                        :disabled="!isAdminStepValid"
                    />
                </div>
            </div>
        </form>
    </AdminLayout>
</template>