<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserForm from '@/Components/Users/UserForm.vue';
import { useForm } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    user: Object,
});

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    password: '',
    global_role: props.user.global_role,
    is_active: Boolean(props.user.is_active),
});

const roles = [
    { label: 'Superadmin', value: 'super_admin' },
    { label: 'Admin', value: 'admin' },
    { label: 'Editor', value: 'editor' },
    { label: 'Viewer', value: 'viewer' },
];

const toast = useToast();

const submit = () => {
    form.put(route('users.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Úspech', detail: 'Používateľ bol úspešne upravený.', life: 3000 });
        },
        onError: () => { toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa upraviť používateľa.', life: 3000 }); },
    });
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Upraviť používateľa
        </h1>

        <form @submit.prevent="submit">
            <UserForm
                :form="form"
                :roles="roles"
                submit-label="Uložiť"
                password-label="Nové heslo"
                password-hint="Nechaj prázdne, ak ho nechceš meniť."
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>