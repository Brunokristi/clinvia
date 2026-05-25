<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Step from 'primevue/step';
import StepList from 'primevue/steplist';
import StepPanel from 'primevue/steppanel';
import StepPanels from 'primevue/steppanels';
import Stepper from 'primevue/stepper';

const currentStep = ref('1');
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

const isStepOne = computed(() => currentStep.value === '1');
const isStepTwo = computed(() => currentStep.value === '2');

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

const goToStep = (step) => {
    if (step === '2' && !isCompanyStepValid.value) {
        triedToContinue.value = true;
        currentStep.value = '1';

        return;
    }

    currentStep.value = step;
};

const goToNextStep = () => {
    triedToContinue.value = true;

    if (!isCompanyStepValid.value) {
        return;
    }

    currentStep.value = '2';
};

const goToPreviousStep = () => {
    currentStep.value = '1';
};

const submit = async () => {
    if (!isCompanyStepValid.value) {
        triedToContinue.value = true;
        currentStep.value = '1';

        return;
    }

    if (!isAdminStepValid.value) {
        currentStep.value = '2';

        return;
    }

    form.post(route('companies.onboard.store'));
};
</script>

<template>
    <AdminLayout>
        <form class="max-w-5xl space-y-6" @submit.prevent="submit">
            <Stepper
                :value="currentStep"
                linear
            >
                <StepList>
                    <Step
                        value="1"
                        @click="goToStep('1')"
                    >
                        Spoločnosť
                    </Step>

                    <Step
                        value="2"
                        :disabled="!isCompanyStepValid"
                        @click="goToStep('2')"
                    >
                        Administrátor
                    </Step>
                </StepList>

                <StepPanels>
                    <StepPanel value="1">
                        <section class="space-y-6">
                            <CompanyForm
                                :form="form"
                                prefix="company_"
                                :show-submit="false"
                                :show-slug-preview="true"
                            />
                        </section>

                        <div class="mt-6 flex items-center justify-end">
                            <Button
                                type="button"
                                label="Pokračovať"
                                :disabled="!isCompanyStepValid"
                                @click="goToNextStep"
                            />
                        </div>
                    </StepPanel>

                    <StepPanel value="2">
                        <section class="space-y-6">
                            <InvitationFormSection
                                :form="form"
                                title="Pozvánka pre administrátora"
                                description="Zadáte iba email. Na tento email pošleme pozvánku, kde si administrátor dokončí registráciu."
                                input-label="Email administrátora"
                                submit-label="Poslať pozvánku"
                                :loading="form.processing"
                                :show-button="false"
                            />
                        </section>

                        <div class="mt-6 flex items-center justify-between">
                            <Button
                                type="button"
                                label="Späť"
                                severity="secondary"
                                outlined
                                @click="goToPreviousStep"
                            />

                            <Button
                                type="submit"
                                label="Poslať pozvánku"
                                :loading="form.processing"
                                :disabled="!isAdminStepValid"
                            />
                        </div>
                    </StepPanel>
                </StepPanels>
            </Stepper>
        </form>
    </AdminLayout>
</template>