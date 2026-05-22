<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    legal_name: '',
    id_number: '',
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

const submit = () => {
    form.post(route('companies.store'));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Pridať firmu
        </h1>

        <form class="max-w-3xl" @submit.prevent="submit">
            <CompanyForm
                :form="form"
                submit-label="Vytvoriť"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>