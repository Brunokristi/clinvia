<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'textual'].includes(value),
    },
});

const logoSrc = computed(() => {
    if (props.type === 'textual') {
        return '/brand/clinvia_logo_text.svg';
    }

    return '/brand/clinvia_logo.svg';
});
</script>

<template>
    <img
        :src="logoSrc"
        alt="Clinvia Logo"
        class="w-auto"
    >
</template>