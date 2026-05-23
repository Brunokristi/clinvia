<script setup>
import AddressFormSection from '@/Components/Forms/AddressFormSection.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

defineProps({
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

        <AddressFormSection
            :form="form"
            title="Adresa"
            :show-region="true"
        />

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