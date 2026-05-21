<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';

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

        <form class="max-w-xl space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium">First name</label>
                <InputText v-model="form.first_name" class="w-full" />
                <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">
                    {{ form.errors.first_name }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Last name</label>
                <InputText v-model="form.last_name" class="w-full" />
                <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">
                    {{ form.errors.last_name }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <InputText v-model="form.email" class="w-full" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Heslo</label>
                <Password v-model="form.password" class="w-full" inputClass="w-full" toggleMask />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Rola</label>
                <Select
                    v-model="form.global_role"
                    :options="roles"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_active" binary inputId="is_active" />
                <label for="is_active">Aktívny používateľ</label>
            </div>

            <Button type="submit" label="Vytvoriť" :loading="form.processing" />
        </form>
    </AdminLayout>
</template>