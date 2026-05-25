<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

defineProps({
    form: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    submitLabel: {
        type: String,
        default: 'Poslať pozvánku',
    },
    inputLabel: {
        type: String,
        default: 'Email pozvaného používateľa',
    },
    placeholder: {
        type: String,
        default: 'admin@firma.sk',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    showButton: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <FormSection
        :title="title"
        :description="description"
        columns="grid-cols-1"
    >
        <FormField
            :label="inputLabel"
            for="invite_email"
            :error="form.errors.invite_email"
        >
            <InputText
                id="invite_email"
                v-model="form.invite_email"
                class="w-full"
                :placeholder="placeholder"
                autocomplete="email"
                :invalid="!!form.errors.invite_email"
            />
        </FormField>

        <div
            v-if="showButton"
            class="flex justify-end"
        >
            <Button
                type="submit"
                :label="submitLabel"
                :loading="loading"
                :disabled="loading"
            />
        </div>
    </FormSection>
</template>