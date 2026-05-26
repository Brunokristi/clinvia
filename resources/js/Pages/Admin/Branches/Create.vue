<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import BranchForm from '@/Components/Branches/BranchForm.vue';
import InvitationFormSection from '@/Components/Invitations/InvitationFormSection.vue';
import { useToast } from 'primevue/usetoast';

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
    region: '',
    country: 'Slovensko',
    website: '',
    invite_email: '',
});

const toast = useToast();

const submit = () => {
    form.post(route('branches.store'), {
        preserveScroll: true,
        // success handled via server flash message (rendered by layout)
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Chyba',
                detail: 'Nepodarilo sa vytvoriť pobočku.',
                life: 3000,
            });
        },
    });
};
</script>

<template>
    <AdminLayout>
        <form class="space-y-6" @submit.prevent="submit">
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