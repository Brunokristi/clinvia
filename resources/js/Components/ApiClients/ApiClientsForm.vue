<script setup>
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
    rate_limit_per_minute: 1000000,
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
    form.rate_limit_per_minute = 1000000;
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
    form.rate_limit_per_minute = 1000000;
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
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="text-base font-semibold text-slate-900">
                Základné údaje
            </h3>

            <p class="mt-1 text-sm leading-6 text-slate-600">
                API kľúč bude aktívny automaticky a limit bude nastavený na vysokú hodnotu.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div v-if="showCompanySelect">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Firma <span class="text-red-500">*</span>
                </label>

                <Select
                    v-model="form.company_id"
                    :options="companies"
                    optionLabel="legal_name"
                    optionValue="id"
                    placeholder="Vyber firmu"
                    filter
                    class="w-full"
                />

                <p
                    v-if="form.errors.company_id"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.company_id }}
                </p>
            </div>

            <div :class="showCompanySelect ? '' : 'md:col-span-2'">
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Názov API kľúča <span class="text-red-500">*</span>
                </label>

                <InputText
                    v-model="form.name"
                    class="w-full"
                    placeholder="Napr. Web frontend"
                />

                <p
                    v-if="form.errors.name"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.name }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Povolené domény
                    </h3>

                    <p class="mt-1 text-sm text-slate-600">
                        Pridajte domény, z ktorých môže frontend používať tento API kľúč.
                    </p>
                </div>

                <Button
                    type="button"
                    label="Pridať doménu"
                    icon="pi pi-plus"
                    size="small"
                    outlined
                    @click="addDomain"
                />
            </div>

            <div class="space-y-3">
                <div
                    v-for="(domain, index) in form.domains"
                    :key="index"
                    class="grid gap-3 md:grid-cols-[1fr_auto]"
                >
                    <div>
                        <InputText
                            v-model="domain.domain"
                            class="w-full"
                            placeholder="https://www.mojweb.sk"
                        />

                        <p
                            v-if="form.errors[`domains.${index}.domain`]"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors[`domains.${index}.domain`] }}
                        </p>
                    </div>

                    <Button
                        v-if="form.domains.length > 1"
                        type="button"
                        icon="pi pi-trash"
                        severity="danger"
                        outlined
                        @click="removeDomain(index)"
                    />
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
            <Button
                type="submit"
                :label="submitLabel"
                icon="pi pi-key"
                :loading="form.processing"
            />
        </div>
    </form>
</template>