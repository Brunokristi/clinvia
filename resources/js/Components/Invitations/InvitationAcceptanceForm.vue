<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div v-if="!isExistingUserInvite">
            <InputLabel for="first_name" value="First name" />
            <TextInput id="first_name" v-model="form.first_name" class="mt-1 block w-full" required autofocus autocomplete="given-name" />
            <InputError class="mt-2" :message="form.errors.first_name" />
        </div>

        <div v-if="!isExistingUserInvite">
            <InputLabel for="last_name" value="Last name" />
            <TextInput id="last_name" v-model="form.last_name" class="mt-1 block w-full" required autocomplete="family-name" />
            <InputError class="mt-2" :message="form.errors.last_name" />
        </div>

        <div>
            <InputLabel for="email" value="Email" />
            <TextInput id="email" v-model="form.email" class="mt-1 block w-full" required readonly autocomplete="username" />
            <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <div>
            <InputLabel :for="'password'" :value="isExistingUserInvite ? 'Heslo' : 'Password'" />
            <TextInput id="password" v-model="form.password" class="mt-1 block w-full" type="password" required :autocomplete="isExistingUserInvite ? 'current-password' : 'new-password'" />
            <InputError class="mt-2" :message="form.errors.password" />
        </div>

        <div v-if="!isExistingUserInvite">
            <InputLabel for="password_confirmation" value="Confirm Password" />
            <TextInput id="password_confirmation" v-model="form.password_confirmation" class="mt-1 block w-full" type="password" required autocomplete="new-password" />
            <InputError class="mt-2" :message="form.errors.password_confirmation" />
        </div>

        <div class="pt-2">
            <PrimaryButton class="w-full justify-center" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ isExistingUserInvite ? 'Prihlásiť sa a prijať pozvánku' : 'Dokončiť registráciu' }}
            </PrimaryButton>
        </div>
    </form>
</template>
