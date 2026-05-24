<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';
import FormLabel from '@/Components/FormLabel.vue';


defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <form class="space-y-5 w-full max-w-md" @submit.prevent="submit">
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

            <div>
                <FormLabel for="password">
                    Heslo
                </FormLabel>

                <Password
                    id="password"
                    v-model="form.password"
                    class="w-full"
                    inputClass="w-full"
                    :feedback="false"
                    :toggleMask="true"
                    :invalid="!!form.errors.password"
                    required
                    autocomplete="current-password"
                />

                <Message
                    v-if="form.errors.password"
                    severity="error"
                    size="small"
                    variant="simple"
                    class="mt-2"
                >
                    {{ form.errors.password }}
                </Message>
            </div>

            <div class="flex items-center">
                <Checkbox
                    v-model="form.remember"
                    inputId="remember"
                    name="remember"
                    binary
                />

                <FormLabel for="remember" class="ml-2">
                    Zapamätať si prihlásenie
                </FormLabel>
            </div>

            <div class="flex flex-col items-center justify-center gap-4">
                <Button
                    type="submit"
                    label="Prihlásiť sa"
                    :loading="form.processing"
                    :disabled="form.processing"
                />

                <div class="w-full flex items-center justify-center gap-1">
                    <p class="text-sm text-accent">
                        Zabudli ste heslo?
                    </p>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm text-accent underline transition hover:text-accent/80"
                    >
                        Resetovať
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>