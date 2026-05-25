<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import OnboardingSteps from '@/Components/Companies/OnboardingSteps.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';

const currentStep = ref(1);
const triedToContinue = ref(false);

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

    invite_email: '',
});

const steps = [
    { number: 1, title: 'Údaje firmy', description: 'Identifikácia, fakturačné údaje a adresa' },
    { number: 2, title: 'Prvý administrátor', description: 'Používateľ, ktorý bude firmu spravovať' },
];

const isStepOne = computed(() => currentStep.value === 1);
const isStepTwo = computed(() => currentStep.value === 2);

const isCompanyStepValid = computed(() => {
    return !!form.company_legal_name?.trim() &&
           !!String(form.company_id_number ?? '').trim() &&
           !!String(form.company_tax_id ?? '').trim() &&
           !!form.company_address_line_1?.trim() &&
           !!form.company_city?.trim() &&
           !!form.company_postal_code?.trim() &&
           !!form.company_country?.trim();
});

const isAdminStepValid = computed(() => !!form.invite_email?.trim());

const showCompanyRequiredError = (field) => triedToContinue.value && !String(form[field]).trim();

const goToStep = (stepNumber) => {
    if (stepNumber === 2 && !isCompanyStepValid.value) {
        triedToContinue.value = true;
        return;
    }

    currentStep.value = stepNumber;
};

const goToNextStep = () => {
    triedToContinue.value = true;

    if (! isCompanyStepValid.value) {
        return;
    }

    currentStep.value = 2;
};

const goToPreviousStep = () => {
    currentStep.value = 1;
};

const submit = async () => {
    if (! isCompanyStepValid.value) {
        currentStep.value = 1;
        return;
    }

    if (! isAdminStepValid.value) {
        currentStep.value = 2;
        return;
    }

    form.post(route('companies.onboard.store'));
};
</script>

<template>
    <AdminLayout>
        <div class="mb-8">
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">Onboarding</p>
            <h1 class="mt-3 text-2xl font-semibold text-slate-900">Vytvoriť firmu</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Najprv vyplňte povinné údaje firmy. Až potom môžete vytvoriť prvého administrátora.
            </p>
        </div>

        <OnboardingSteps :steps="steps" :current-step="currentStep" :can-open-admin-step="isCompanyStepValid" @select="goToStep" />

        <form class="max-w-5xl space-y-6" @submit.prevent="submit">
            <section v-if="isStepOne" class="space-y-6">
                <CompanyForm
                    :form="form"
                    prefix="company_"
                    :show-submit="false"
                    :show-slug-preview="true"
                />
            </section>

            <section v-if="isStepTwo" class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Firma</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ form.company_legal_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">IČO: {{ form.company_id_number }} · DIČ: {{ form.company_tax_id }}</p>
                        </div>

                        <Button type="button" label="Upraviť firmu" severity="secondary" outlined size="small" @click="goToPreviousStep" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <InvitationFormSection
                        :form="form"
                        title="Pozvánka pre administrátora"
                        description="Zadáte iba email. Na tento email pošleme pozvánku, kde si administrátor dokončí registráciu."
                        input-label="Email administrátora"
                        submit-label="Poslať pozvánku"
                        :loading="form.processing"
                        :show-button="false"
                    />
                </div>
            </section>

            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <Button v-if="isStepTwo" type="button" label="Späť" severity="secondary" outlined @click="goToPreviousStep" />
                <div v-else />

                <div class="flex items-center gap-3">
                    <Button v-if="isStepOne" type="button" label="Pokračovať" :disabled="!isCompanyStepValid" @click="goToNextStep" />
                    <Button v-if="isStepTwo" type="submit" label="Poslať pozvánku" :loading="form.processing" :disabled="!isAdminStepValid" />
                </div>
            </div>
        </form>
    </AdminLayout>
</template>