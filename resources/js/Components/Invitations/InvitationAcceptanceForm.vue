<script setup>
import FormLabel from '@/Components/FormLabel.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Password from 'primevue/password';

const props = defineProps({
    invitation: {
        type: Object,
        required: true,
    },
    submitRouteName: {
        type: String,
        required: true,
    },
});

const isExistingUserInvite = computed(() => props.invitation.mode === 'existing_user');

const form = useForm({
    first_name: '',
    last_name: '',
    email: props.invitation.email ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route(props.submitRouteName, props.invitation.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div v-if="!isExistingUserInvite">
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

        <div v-if="!isExistingUserInvite">
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
                readonly
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
                {{ isExistingUserInvite ? 'Heslo' : 'Heslo' }}
            </FormLabel>

            <Password
                id="password"
                v-model="form.password"
                class="w-full"
                input-class="w-full"
                :feedback="!isExistingUserInvite"
                :toggle-mask="true"
                :invalid="!!form.errors.password"
                required
                :autocomplete="isExistingUserInvite ? 'current-password' : 'new-password'"
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

        <div v-if="!isExistingUserInvite">
            <FormLabel for="password_confirmation">
                Potvrdenie hesla
            </FormLabel>

            <Password
                id="password_confirmation"
                v-model="form.password_confirmation"
                class="w-full"
                input-class="w-full"
                :feedback="false"
                :toggle-mask="true"
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

        <div class="pt-2">
            <Button
                type="submit"
                :label="isExistingUserInvite ? 'Prihlásiť sa a prijať pozvánku' : 'Dokončiť registráciu'"
                :loading="form.processing"
                :disabled="form.processing"
            />
        </div>
    </form>
</template>