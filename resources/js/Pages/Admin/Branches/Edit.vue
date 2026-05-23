<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BranchForm from '@/Components/Branches/BranchForm.vue';
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    branch: {
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
});

const form = useForm({
    company_id: props.branch.company_id ?? props.company?.id ?? null,
    name: props.branch.name ?? '',
    type: props.branch.type ?? '',
    description: props.branch.description ?? '',
    address_line_1: props.branch.address_line_1 ?? '',
    address_line_2: props.branch.address_line_2 ?? '',
    city: props.branch.city ?? '',
    postal_code: props.branch.postal_code ?? '',
    region: props.branch.region ?? '',
    country: props.branch.country ?? 'Slovensko',
    website: props.branch.website ?? '',
    invite_email: props.branch.invite_email ?? '',
    is_active: Boolean(props.branch.is_active),
});

const toast = useToast();

const submit = () => {
    form.put(route('branches.update', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Úspech',
                detail: 'Pobočka bola úspešne upravená.',
                life: 3000,
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: 'Nepodarilo sa upraviť pobočku.',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Upraviť pobočku
        </h1>

        <form class="space-y-6" @submit.prevent="submit">
            <BranchForm
                :form="form"
                :company="company"
                :companies="companies"
                :show-company-select="!company"
                show-active-toggle
                submit-label="Uložiť"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>