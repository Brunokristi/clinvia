<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { computed } from 'vue';

const form = useForm({
    legal_name: '',
    company_id_number: '',
    tax_id: '',
    vat_id: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    postal_code: '',
    region: '',
    country: '',
    email: '',
    phone: '',
    website: '',
    is_active: true,
});

const slugify = (value) => value
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const generatedSlug = computed(() => slugify(form.legal_name));

const submit = () => {
    form.post(route('companies.store'));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Pridať firmu
        </h1>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Oficiálny názov</label>
                    <InputText v-model="form.legal_name" class="w-full" />
                    <p v-if="form.errors.legal_name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.legal_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Slug</label>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        {{ generatedSlug || 'slug-sa-zobrazí-po-zadaní-názvu' }}
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Slug sa vytvorí automaticky z oficiálneho názvu.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">IČO</label>
                    <InputText v-model="form.company_id_number" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">DIČ</label>
                    <InputText v-model="form.tax_id" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">IČ DPH</label>
                    <InputText v-model="form.vat_id" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Email</label>
                    <InputText v-model="form.email" class="w-full" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Telefón</label>
                    <InputText v-model="form.phone" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Web</label>
                    <InputText v-model="form.website" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Adresa 1</label>
                    <InputText v-model="form.address_line_1" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Adresa 2</label>
                    <InputText v-model="form.address_line_2" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Mesto</label>
                    <InputText v-model="form.city" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">PSČ</label>
                    <InputText v-model="form.postal_code" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Región</label>
                    <InputText v-model="form.region" class="w-full" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Krajina</label>
                    <InputText v-model="form.country" class="w-full" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" binary inputId="is_active" />
                <label for="is_active">Aktívna firma</label>
            </div>

            <Button type="submit" label="Vytvoriť" :loading="form.processing" />
        </form>
    </AdminLayout>
</template>