<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

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
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ title }}
            </h2>

            <p v-if="description" class="mt-1 text-sm leading-6 text-slate-600">
                {{ description }}
            </p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
                {{ inputLabel }}
            </label>

            <InputText
                v-model="form.invite_email"
                class="w-full"
                :placeholder="placeholder"
                autocomplete="email"
            />

            <p v-if="form.errors.invite_email" class="mt-1 text-sm text-red-600">
                {{ form.errors.invite_email }}
            </p>
        </div>

        <div v-if="showButton" class="mt-5 flex justify-end">
            <Button type="submit" :label="submitLabel" :loading="loading" />
        </div>
    </section>
</template>