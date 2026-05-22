<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import OnboardingSteps from '@/Components/Companies/OnboardingSteps.vue';
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

const currentStep = ref(1);
const triedToContinue = ref(false);
const triedToSubmit = ref(false);
const existingAdmin = ref(null);
const isLookingUpAdmin = ref(false);
const isLoadingSuggestions = ref(false);
const adminLookupError = ref('');
const adminSuggestionError = ref('');
const lastLookedUpEmail = ref('');
const adminSuggestions = ref([]);
const showAdminSuggestions = ref(false);
const suggestionRequestCounter = ref(0);
const suppressNextSuggestionFetch = ref(false);

let adminSuggestionDebounceTimer = null;

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

const steps = [
    { number: 1, title: 'Údaje firmy', description: 'Identifikácia, fakturačné údaje a adresa' },
    { number: 2, title: 'Prvý administrátor', description: 'Používateľ, ktorý bude firmu spravovať' },
];

const isStepOne = computed(() => currentStep.value === 1);
const isStepTwo = computed(() => currentStep.value === 2);

const companyRequiredFields = computed(() => ({
    company_legal_name: form.company_legal_name,
    company_id_number: form.company_id_number,
    company_tax_id: form.company_tax_id,
    company_address_line_1: form.company_address_line_1,
    company_city: form.company_city,
    company_postal_code: form.company_postal_code,
    company_country: form.company_country,
}));

const adminRequiredFields = computed(() => {
    const required = { admin_email: form.admin_email };

    if (! existingAdmin.value) {
        required.admin_first_name = form.admin_first_name;
        required.admin_last_name = form.admin_last_name;
        required.admin_password = form.admin_password;
        required.admin_password_confirmation = form.admin_password_confirmation;
    }

    return required;
});

const passwordsDoNotMatch = computed(() => {
    if (existingAdmin.value) {
        return false;
    }

    return form.admin_password && form.admin_password_confirmation && form.admin_password !== form.admin_password_confirmation;
});

const isCompanyStepValid = computed(() => Object.values(companyRequiredFields.value).every((value) => String(value).trim() !== ''));
const isAdminStepValid = computed(() => Object.values(adminRequiredFields.value).every((value) => String(value).trim() !== '') && !passwordsDoNotMatch.value);

const adminLookupNotice = computed(() => {
    if (isLookingUpAdmin.value) {
        return 'Overujem email administrátora...';
    }

    if (adminLookupError.value) {
        return adminLookupError.value;
    }

    if (existingAdmin.value) {
        return `Našiel sa existujúci používateľ: ${existingAdmin.value.first_name} ${existingAdmin.value.last_name}. Bude priradený k firme.`;
    }

    if (lastLookedUpEmail.value && lastLookedUpEmail.value === form.admin_email.trim().toLowerCase()) {
        return 'Používateľ s týmto emailom neexistuje. Vytvorí sa nový administrátor.';
    }

    return '';
});

const showCompanyRequiredError = (field) => triedToContinue.value && !String(form[field]).trim();
const showAdminRequiredError = (field) => triedToSubmit.value && !String(form[field]).trim();

const applyExistingAdmin = (user) => {
    existingAdmin.value = user;
    form.admin_first_name = user.first_name || '';
    form.admin_last_name = user.last_name || '';
    form.admin_password = '';
    form.admin_password_confirmation = '';
};

const resetAdminLookup = () => {
    existingAdmin.value = null;
    adminLookupError.value = '';
    lastLookedUpEmail.value = '';
    adminSuggestions.value = [];
    showAdminSuggestions.value = false;
    adminSuggestionError.value = '';
    isLoadingSuggestions.value = false;
};

const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

const fetchAdminSuggestions = async (normalizedQuery) => {
    const requestId = ++suggestionRequestCounter.value;
    isLoadingSuggestions.value = true;
    adminSuggestionError.value = '';

    try {
        const response = await window.axios.get(route('users.lookup-email-suggestions'), { params: { q: normalizedQuery } });

        if (requestId !== suggestionRequestCounter.value) {
            return;
        }

        adminSuggestions.value = response.data.users || [];
        showAdminSuggestions.value = adminSuggestions.value.length > 0 && !existingAdmin.value;
    } catch (error) {
        if (requestId !== suggestionRequestCounter.value) {
            return;
        }

        adminSuggestions.value = [];
        showAdminSuggestions.value = false;
        adminSuggestionError.value = 'Návrhy emailov sa nepodarilo načítať.';
    } finally {
        if (requestId === suggestionRequestCounter.value) {
            isLoadingSuggestions.value = false;
        }
    }
};

watch(() => form.admin_email, (newValue) => {
    const normalized = String(newValue || '').trim().toLowerCase();

    if (suppressNextSuggestionFetch.value) {
        suppressNextSuggestionFetch.value = false;
        return;
    }

    if (!normalized) {
        resetAdminLookup();
        return;
    }

    if (normalized !== lastLookedUpEmail.value) {
        if (existingAdmin.value) {
            form.admin_first_name = '';
            form.admin_last_name = '';
            form.admin_password = '';
            form.admin_password_confirmation = '';
        }

        existingAdmin.value = null;
        adminLookupError.value = '';
    }

    if (adminSuggestionDebounceTimer) {
        clearTimeout(adminSuggestionDebounceTimer);
    }

    if (normalized.length < 2) {
        adminSuggestions.value = [];
        showAdminSuggestions.value = false;
        adminSuggestionError.value = '';
        return;
    }

    adminSuggestionDebounceTimer = setTimeout(() => {
        fetchAdminSuggestions(normalized);
    }, 250);
});

const lookupAdminByEmail = async () => {
    const normalizedEmail = String(form.admin_email || '').trim().toLowerCase();

    if (!normalizedEmail || !isValidEmail(normalizedEmail)) {
        resetAdminLookup();
        return;
    }

    isLookingUpAdmin.value = true;
    adminLookupError.value = '';

    try {
        const response = await window.axios.get(route('users.lookup-by-email'), { params: { email: normalizedEmail } });
        const { exists, user } = response.data;
        lastLookedUpEmail.value = normalizedEmail;

        if (exists && user) {
            applyExistingAdmin(user);
        } else {
            existingAdmin.value = null;
        }
    } catch (error) {
        existingAdmin.value = null;
        adminLookupError.value = 'Email sa nepodarilo overiť. Skúste to znova.';
    } finally {
        isLookingUpAdmin.value = false;
    }
};

const selectAdminSuggestion = (user) => {
    suppressNextSuggestionFetch.value = true;

    if (adminSuggestionDebounceTimer) {
        clearTimeout(adminSuggestionDebounceTimer);
    }

    form.admin_email = user.email || '';
    lastLookedUpEmail.value = String(user.email || '').trim().toLowerCase();

    applyExistingAdmin(user);

    adminLookupError.value = '';
    adminSuggestionError.value = '';
    adminSuggestions.value = [];
    showAdminSuggestions.value = false;
    isLoadingSuggestions.value = false;
};

const handleAdminEmailFocus = () => {
    if (existingAdmin.value) {
        showAdminSuggestions.value = false;
        return;
    }

    showAdminSuggestions.value = adminSuggestions.value.length > 0;
};

const handleAdminEmailBlur = () => {
    setTimeout(() => {
        showAdminSuggestions.value = false;

        if (! existingAdmin.value) {
            lookupAdminByEmail();
        }
    }, 120);
};

const normalizedAdminEmail = () => String(form.admin_email || '').trim().toLowerCase();

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
    triedToSubmit.value = true;

    const email = normalizedAdminEmail();

    if (isValidEmail(email) && lastLookedUpEmail.value !== email) {
        await lookupAdminByEmail();
    }

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
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">Osobné údaje administrátora</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Najprv zadajte email. Ak používateľ už existuje, automaticky sa načíta a priradí k firme.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Email <span class="text-red-500">*</span></label>
                            <InputText v-model="form.admin_email" class="w-full" placeholder="admin@firma.sk" @focus="handleAdminEmailFocus" @blur="handleAdminEmailBlur" />

                            <div v-if="showAdminSuggestions && !existingAdmin" class="mt-2 max-h-52 overflow-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                                <button
                                    v-for="suggestion in adminSuggestions"
                                    :key="suggestion.id"
                                    type="button"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50"
                                    @mousedown.prevent="selectAdminSuggestion(suggestion)"
                                >
                                    <span class="text-sm font-medium text-slate-800">{{ suggestion.email }}</span>
                                    <span class="text-xs text-slate-500">{{ suggestion.first_name }} {{ suggestion.last_name }}</span>
                                </button>
                            </div>

                            <p v-if="isLoadingSuggestions && !existingAdmin" class="mt-1 text-sm text-slate-500">Hľadám existujúcich používateľov...</p>
                            <p v-if="adminSuggestionError" class="mt-1 text-sm text-red-600">{{ adminSuggestionError }}</p>
                            <p v-if="showAdminRequiredError('admin_email')" class="mt-1 text-sm text-red-600">Email je povinný.</p>
                            <p v-if="form.errors.admin_email" class="mt-1 text-sm text-red-600">{{ form.errors.admin_email }}</p>
                            <p v-if="adminLookupNotice" class="mt-1 text-sm" :class="{
                                'text-slate-500': isLookingUpAdmin,
                                'text-emerald-600': existingAdmin,
                                'text-amber-600': !existingAdmin && !isLookingUpAdmin && !adminLookupError,
                                'text-red-600': !!adminLookupError,
                            }">{{ adminLookupNotice }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Meno <span v-if="!existingAdmin" class="text-red-500">*</span></label>
                            <InputText v-model="form.admin_first_name" class="w-full" placeholder="Ján" :disabled="!!existingAdmin" />
                            <p v-if="!existingAdmin && showAdminRequiredError('admin_first_name')" class="mt-1 text-sm text-red-600">Meno je povinné.</p>
                            <p v-if="form.errors.admin_first_name" class="mt-1 text-sm text-red-600">{{ form.errors.admin_first_name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Priezvisko <span v-if="!existingAdmin" class="text-red-500">*</span></label>
                            <InputText v-model="form.admin_last_name" class="w-full" placeholder="Novák" :disabled="!!existingAdmin" />
                            <p v-if="!existingAdmin && showAdminRequiredError('admin_last_name')" class="mt-1 text-sm text-red-600">Priezvisko je povinné.</p>
                            <p v-if="form.errors.admin_last_name" class="mt-1 text-sm text-red-600">{{ form.errors.admin_last_name }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="!existingAdmin" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">Prihlasovacie údaje</h2>
                        <p class="mt-1 text-sm text-slate-500">Heslo musí byť zadané dvakrát.</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Heslo <span class="text-red-500">*</span></label>
                            <Password v-model="form.admin_password" class="w-full" inputClass="w-full" toggleMask />
                            <p v-if="showAdminRequiredError('admin_password')" class="mt-1 text-sm text-red-600">Heslo je povinné.</p>
                            <p v-if="form.errors.admin_password" class="mt-1 text-sm text-red-600">{{ form.errors.admin_password }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Potvrdenie hesla <span class="text-red-500">*</span></label>
                            <Password v-model="form.admin_password_confirmation" class="w-full" inputClass="w-full" toggleMask :feedback="false" />
                            <p v-if="showAdminRequiredError('admin_password_confirmation')" class="mt-1 text-sm text-red-600">Potvrdenie hesla je povinné.</p>
                            <p v-if="passwordsDoNotMatch" class="mt-1 text-sm text-red-600">Heslá sa nezhodujú.</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <Button v-if="isStepTwo" type="button" label="Späť" severity="secondary" outlined @click="goToPreviousStep" />
                <div v-else />

                <div class="flex items-center gap-3">
                    <Button v-if="isStepOne" type="button" label="Pokračovať" :disabled="!isCompanyStepValid" @click="goToNextStep" />
                    <Button v-if="isStepTwo" type="submit" :label="existingAdmin ? 'Vytvoriť firmu a priradiť admina' : 'Vytvoriť firmu a admina'" :loading="form.processing" :disabled="!isAdminStepValid" />
                </div>
            </div>
        </form>
    </AdminLayout>
</template>