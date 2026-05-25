<script setup>
import Message from 'primevue/message';
import FormLabel from '@/Components/FormLabel.vue';

defineProps({
    label: {
        type: String,
        required: true,
    },
    for: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    span: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div :class="span">
        <FormLabel :for="for">
            {{ label }}

            <span
                v-if="required"
                class="text-red-500"
            >
                *
            </span>
        </FormLabel>

        <slot />

        <Message
            v-if="error"
            severity="error"
            size="small"
            variant="simple"
            class="mt-2"
        >
            {{ error }}
        </Message>
    </div>
</template>