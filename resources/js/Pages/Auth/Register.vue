<script setup>
import FormLabel from '@/Components/FormLabel.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registrácia" />

        <form class="w-full max-w-2xl space-y-5" @submit.prevent="submit">
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold text-accent">
                    Vytvoriť účet
                </h1>

                <p class="text-sm leading-6 text-gray-600">
                    Vyplňte údaje nižšie a vytvorte si nový účet.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <FormLabel for="first_name">
                        Meno
                    </FormLabel>

                    <InputText
                        id="first_name"
                        v-model="form.first_name"
                        type="text"
                        class="w-full"
                        :invalid="!!form.errors.first_name"
                        required
                        autofocus
                        autocomplete="given-name"
                    />

                    <Message
                        v-if="form.errors.first_name"
                        severity="error"
                        size="small"
                        variant="simple"
                        class="mt-2"
                    >
                        {{ form.errors.first_name }}
                    </Message>
                </div>

                <div>
                    <FormLabel for="last_name">
                        Priezvisko
                    </FormLabel>

                    <InputText
                        id="last_name"
                        v-model="form.last_name"
                        type="text"
                        class="w-full"
                        :invalid="!!form.errors.last_name"
                        required
                        autocomplete="family-name"
                    />

                    <Message
                        v-if="form.errors.last_name"
                        severity="error"
                        size="small"
                        variant="simple"
                        class="mt-2"
                    >
                        {{ form.errors.last_name }}
                    </Message>
                </div>
            </div>

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

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                        autocomplete="new-password"
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

                <div>
                    <FormLabel for="password_confirmation">
                        Potvrdenie hesla
                    </FormLabel>

                    <Password
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        class="w-full"
                        inputClass="w-full"
                        :feedback="false"
                        :toggleMask="true"
                        :invalid="!!form.errors.password_confirmation"
                        required
                        autocomplete="new-password"
                    />

                    <Message
                        v-if="form.errors.password_confirmation"
                        severity="error"
                        size="small"
                        variant="simple"
                        class="mt-2"
                    >
                        {{ form.errors.password_confirmation }}
                    </Message>
                </div>
            </div>

            <div class="flex flex-col items-center justify-center gap-4 pt-2">
                <Button
                    type="submit"
                    label="Registrovať sa"
                    :loading="form.processing"
                    :disabled="form.processing"
                />

                <div class="flex w-full items-center justify-center gap-1">
                    <p class="text-sm text-accent">
                        Už máte účet?
                    </p>

                    <Link
                        :href="route('login')"
                        class="text-sm text-accent underline transition hover:text-accent/80"
                    >
                        Prihlásiť sa
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>