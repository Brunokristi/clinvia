<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

defineProps({
    companies: Array,
});

const form = useForm({
    company_id: null,
    name: '',
    rate_limit_per_minute: 60,
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

const submit = () => {
    form.post(route('api-clients.store'));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Pridať API klienta
        </h1>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <div class="rounded-lg border bg-white p-5">
                <h2 class="mb-4 text-lg font-semibold">
                    Základné údaje
                </h2>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Firma
                        </label>

                        <Select
                            v-model="form.company_id"
                            :options="companies"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Vyber firmu"
                            class="w-full"
                        />

                        <p v-if="form.errors.company_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.company_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Názov klienta
                        </label>

                        <InputText v-model="form.name" class="w-full" />

                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Rate limit za minútu
                        </label>

                        <InputNumber
                            v-model="form.rate_limit_per_minute"
                            class="w-full"
                            inputClass="w-full"
                        />

                        <p v-if="form.errors.rate_limit_per_minute" class="mt-1 text-sm text-red-600">
                            {{ form.errors.rate_limit_per_minute }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 pt-7">
                        <Checkbox v-model="form.is_active" binary inputId="is_active" />

                        <label for="is_active">
                            Aktívny API klient
                        </label>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        Povolené domény
                    </h2>

                    <Button
                        type="button"
                        label="Pridať doménu"
                        icon="pi pi-plus"
                        outlined
                        @click="addDomain"
                    />
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(domain, index) in form.domains"
                        :key="index"
                        class="grid gap-4 md:grid-cols-[1fr_auto_auto]"
                    >
                        <div>
                            <InputText
                                v-model="domain.domain"
                                class="w-full"
                                placeholder="https://humanitasrs.sk"
                            />

                            <p
                                v-if="form.errors[`domains.${index}.domain`]"
                                class="mt-1 text-sm text-red-600"
                            >
                                {{ form.errors[`domains.${index}.domain`] }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="domain.is_active"
                                binary
                                :inputId="`domain_active_${index}`"
                            />

                            <label :for="`domain_active_${index}`">
                                Aktívna
                            </label>
                        </div>

                        <Button
                            type="button"
                            label="Odstrániť"
                            severity="danger"
                            outlined
                            @click="removeDomain(index)"
                        />
                    </div>
                </div>
            </div>

            <Button
                type="submit"
                label="Vytvoriť API klienta"
                icon="pi pi-save"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>