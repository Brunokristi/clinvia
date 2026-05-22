<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import BranchForm from '@/Components/Branches/BranchForm.vue';

const props = defineProps({
    company: {
        type: Object,
        default: null,
    },
    companies: Array,
});

const form = useForm({
    company_id: props.company?.id ?? null,
    name: '',
    type: '',
    description: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    postal_code: '',
    country: 'Slovensko',
    website: '',
});

const submit = () => {
    form.post(route('branches.store'));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Pridať pobočku
        </h1>

        <form class="max-w-4xl" @submit.prevent="submit">
            <BranchForm
                :form="form"
                :company="company"
                :companies="companies"
                :show-company-select="!company"
                submit-label="Vytvoriť"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>