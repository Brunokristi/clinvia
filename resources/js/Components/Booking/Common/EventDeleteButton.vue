<script setup>
import Button from 'primevue/button';

const props = defineProps({
    label: {
        type: String,
        default: 'Odstrániť',
    },
    outlined: {
        type: Boolean,
        default: true,
    },
    severity: {
        type: String,
        default: 'danger',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['delete']);
</script>

<template>
    <Button
        type="button"
        :label="label"
        :outlined="outlined"
        :severity="severity"
        :disabled="disabled"
        @click="emit('delete')"
    />
</template>
