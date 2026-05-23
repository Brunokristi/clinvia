<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CompanyForm from '@/Components/Companies/CompanyForm.vue';
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    company: Object,
});

const form = useForm({
    legal_name: props.company.legal_name ?? '',
    id_number: props.company.id_number ?? props.company.company_id_number ?? '',
    tax_id: props.company.tax_id ?? '',
    vat_id: props.company.vat_id ?? '',
    address_line_1: props.company.address_line_1 ?? '',
    address_line_2: props.company.address_line_2 ?? '',
    city: props.company.city ?? '',
    postal_code: props.company.postal_code ?? '',
    region: props.company.region ?? '',
    country: props.company.country ?? '',
    email: props.company.email ?? '',
    phone: props.company.phone ?? '',
    website: props.company.website ?? '',
    is_active: Boolean(props.company.is_active),
});

const toast = useToast();

const submit = () => {
    form.put(route('companies.update', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Úspech', detail: 'Firma bola úspešne upravená.', life: 3000 });
        },
        onError: () => { toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa upraviť firmu.', life: 3000 }); },
    });
};

</script>

<template>
    <AdminLayout>
        <h1 class="mb-2 text-2xl font-semibold">
            Upraviť firmu
        </h1>

        <p class="mb-6 text-sm text-slate-500">
            Upraviť môžeš len základné údaje firmy. Ďalšie sekcie nájdeš v hornej navigácii.
        </p>

        <form class="w-full" @submit.prevent="submit">
            <CompanyForm
                :form="form"
                submit-label="Uložiť"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>