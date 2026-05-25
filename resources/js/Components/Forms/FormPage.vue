<script setup>
import Button from 'primevue/button';

defineProps({
    submitLabel: {
        type: String,
        default: 'Uložiť',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    showSubmit: {
        type: Boolean,
        default: true,
    },
    submitAlign: {
        type: String,
        default: 'end',
    },
});
</script>

<template>
    <div class="space-y-10">
        <div class="divide-y divide-accent overflow-hidden bg-white">
            <slot />
        </div>

        <div
            v-if="showSubmit"
            class="flex"
            :class="submitAlign === 'end' ? 'justify-end' : 'justify-start'"
        >
            <Button
                type="submit"
                :label="submitLabel"
                :loading="loading"
                :disabled="loading"
            />
        </div>
    </div>
</template>