<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
    company: Object,
});

const form = useForm({
    name: props.company.name ?? '',
    slug: props.company.slug ?? '',
    legal_name: props.company.legal_name ?? '',
    company_id_number: props.company.company_id_number ?? '',
    tax_id: props.company.tax_id ?? '',
    vat_id: props.company.vat_id ?? '',
    description: props.company.description ?? '',
    email: props.company.email ?? '',
    phone: props.company.phone ?? '',
    website: props.company.website ?? '',
    is_active: Boolean(props.company.is_active),
    sort_order: props.company.sort_order ?? 0,
});

const submit = () => {
    form.put(route('companies.update', props.company.id));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Upraviť firmu
        </h1>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Názov</label>
                    <InputText v-model="form.name" class="w-full" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Slug</label>
                    <InputText v-model="form.slug" class="w-full" />
                    <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">
                        {{ form.errors.slug }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Oficiálny názov</label>
                    <InputText v-model="form.legal_name" class="w-full" />
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
                    <label class="mb-1 block text-sm font-medium">Poradie</label>
                    <InputNumber v-model="form.sort_order" class="w-full" inputClass="w-full" />
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Popis</label>
                <Textarea v-model="form.description" class="w-full" rows="5" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" binary inputId="is_active" />
                <label for="is_active">Aktívna firma</label>
            </div>

            <Button type="submit" label="Uložiť" :loading="form.processing" />
        </form>
    </AdminLayout>
</template>