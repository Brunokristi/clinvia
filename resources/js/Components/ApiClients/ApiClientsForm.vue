<script setup>
import FormPage from '@/Components/Forms/FormPage.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import { useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    fixedCompanyId: {
        type: [Number, String, null],
        default: null,
    },
    showCompanySelect: {
        type: Boolean,
        default: true,
    },
    submitLabel: {
        type: String,
        default: 'Vytvoriť API kľúč',
    },
});

const emit = defineEmits(['created']);

const form = useForm({
    company_id: props.fixedCompanyId,
    name: '',
    rate_limit_per_minute: 10000,
    is_active: true,
    domains: [
        {
            domain: '',
            is_active: true,
        },
    ],
});

const addDomain = () => {
    form.domains.push({
        domain: '',
        is_active: true,
    });
};

const removeDomain = (index) => {
    form.domains.splice(index, 1);
};

const resetForm = () => {
    form.reset();

    form.company_id = props.fixedCompanyId;
    form.rate_limit_per_minute = 10000;
    form.is_active = true;
    form.domains = [
        {
            domain: '',
            is_active: true,
        },
    ];
};

const submit = () => {
    form.company_id = props.fixedCompanyId ?? form.company_id;
    form.rate_limit_per_minute = 10000;
    form.is_active = true;

    form.domains = form.domains.map((domain) => ({
        ...domain,
        is_active: true,
    }));

    form.post(route('api-clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('created');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit">
        <FormPage
            :submit-label="submitLabel"
            :loading="form.processing"
        >
            <FormSection
                title="Základné údaje"
                columns="md:grid-cols-2"
            >
                <FormField
                    v-if="showCompanySelect"
                    label="Firma"
                    required
                    :error="form.errors.company_id"
                >
                    <Select
                        v-model="form.company_id"
                        :options="companies"
                        option-label="legal_name"
                        option-value="id"
                        placeholder="Vyber firmu"
                        filter
                        class="w-full"
                    />
                </FormField>

                <FormField
                    label="Názov API kľúča"
                    required
                    :error="form.errors.name"
                    :span="showCompanySelect ? '' : 'md:col-span-2'"
                >
                    <InputText
                        v-model="form.name"
                        class="w-full"
                        placeholder="Napr. Web frontend"
                        value="Web frontend"
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Povolené domény"
                description="Pridajte domény, z ktorých môže frontend používať tento API kľúč."
                columns="grid-cols-1"
            >
                <div class="space-y-4">
                    <div
                        v-for="(domain, index) in form.domains"
                        :key="index"
                        class="grid gap-3 md:grid-cols-[1fr_auto]"
                    >
                        <FormField
                            :label="`Doména ${index + 1}`"
                            :error="form.errors[`domains.${index}.domain`]"
                        >
                            <InputText
                                v-model="domain.domain"
                                class="w-full"
                                placeholder="https://www.mojweb.sk"
                            />
                        </FormField>

                        <div class="flex items-end">
                            <Button
                                v-if="form.domains.length > 1"
                                type="button"
                                label="Odstrániť"
                                severity="danger"
                                outlined
                                aria-label="Odstrániť doménu"
                                @click="removeDomain(index)"
                            />
                        </div>
                    </div>
                    <div class="flex justify-start">
                        <Button
                            type="button"
                            label="Pridať doménu"
                            size="small"
                            @click="addDomain"
                        />
                    </div>
                </div>
            </FormSection>
        </FormPage>
    </form>
</template>