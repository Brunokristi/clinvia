<script setup>
defineProps({
    for: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <label
        :for="for"
        class="block text-normal text-accent"
    >
        <slot />
    </label>
</template>