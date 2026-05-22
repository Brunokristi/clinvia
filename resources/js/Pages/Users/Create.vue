<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UserForm from '@/Components/Users/UserForm.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    global_role: 'admin',
    is_active: true,
});

const roles = [
    { label: 'Superadmin', value: 'super_admin' },
    { label: 'Admin', value: 'admin' },
    { label: 'Editor', value: 'editor' },
    { label: 'Viewer', value: 'viewer' },
];

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <AdminLayout>
        <h1 class="mb-6 text-2xl font-semibold">
            Pridať používateľa
        </h1>

        <form @submit.prevent="submit">
            <UserForm
                :form="form"
                :roles="roles"
                submit-label="Vytvoriť"
                :loading="form.processing"
            />
        </form>
    </AdminLayout>
</template>