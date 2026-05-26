<script setup>
import AddressFormSection from '@/Components/Forms/AddressFormSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

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
    showSubmit: {
        type: Boolean,
        default: true,
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
    <FormPage
        :submit-label="submitLabel"
        :loading="loading"
        :show-submit="showSubmit"
    >
        <FormSection
            title="Základné údaje"
            description="Zadajte základné informácie o pobočke, jej typ a krátky popis."
            columns="md:grid-cols-2"
        >
            <FormField
                label="Typ pobočky"
                for="type"
                :error="form.errors.type"
            >
                <Select
                    id="type"
                    v-model="form.type"
                    :options="branchTypes"
                    option-label="label"
                    option-value="value"
                    placeholder="Vyber typ"
                    class="w-full"
                    :invalid="!!form.errors.type"
                />
            </FormField>

            <FormField
                label="Názov"
                for="name"
                :required="true"
                :error="form.errors.name"
            >
                <InputText
                    id="name"
                    v-model="form.name"
                    class="w-full"
                    :invalid="!!form.errors.name"
                />
            </FormField>

            <FormField
                label="Popis"
                for="description"
                :error="form.errors.description"
                span="md:col-span-2"
            >
                <Textarea
                    id="description"
                    v-model="form.description"
                    class="w-full"
                    rows="5"
                    auto-resize
                    :invalid="!!form.errors.description"
                />
            </FormField>
        </FormSection>

        <AddressFormSection
            :form="form"
            title="Adresa"
            description="Adresa, na ktorej sa pobočka nachádza."
            :show-region="true"
        />

        <FormSection
            v-if="showActiveToggle"
            title="Nastavenia"
            description="Určite, či má byť pobočka aktívna v systéme."
            columns="grid-cols-1"
        >
            <div class="flex items-center gap-3">
                <Checkbox
                    v-model="form.is_active"
                    input-id="is_active"
                    binary
                />

                <label
                    for="is_active"
                    class="text-sm font-medium text-accent"
                >
                    Aktívna pobočka
                </label>
            </div>
        </FormSection>
    </FormPage>
</template>