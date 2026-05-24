<script setup>
import FormLabel from '@/Components/FormLabel.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Zabudnuté heslo" />

        <form class="w-full max-w-md space-y-5" @submit.prevent="submit">
            <Message
                v-if="status"
                severity="success"
                :closable="false"
            >
                {{ status }}
            </Message>

            <div>
                <FormLabel for="email">
                    Email
                </FormLabel>

                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    :invalid="!!form.errors.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <Message
                    v-if="form.errors.email"
                    severity="error"
                    size="small"
                    variant="simple"
                    class="mt-2"
                >
                    {{ form.errors.email }}
                </Message>
            </div>

            <div class="flex flex-col items-center justify-center gap-4">
                <Button
                    type="submit"
                    label="Odoslať odkaz na obnovu hesla"
                    :loading="form.processing"
                    :disabled="form.processing"
                />

                <div class="flex w-full items-center justify-center gap-1">
                    <Link
                        :href="route('login')"
                        class="text-sm text-accent underline transition hover:text-accent/80"
                    >
                        Späť na prihlásenie
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>