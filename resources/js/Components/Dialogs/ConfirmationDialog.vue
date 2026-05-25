<script setup>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Potvrdiť',
    },
    cancelLabel: {
        type: String,
        default: 'Zrušiť',
    },
    confirmSeverity: {
        type: String,
        default: 'danger',
    },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Dialog modal :visible="show" :closable="false" class="w-auto max-w-md" @hide="emit('cancel')  ">
        <div class="p-2">
            <div class="flex items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h3 class="text-heading text-dark">
                        {{ title }}
                    </h3>

                    <p v-if="message" class="mt-2 text-normal text-accent">
                        {{ message }}
                    </p>

                    <slot />
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <Button
                    :label="cancelLabel"
                    outlined
                    severity="secondary"
                    @click="emit('cancel')"
                />

                <Button
                    :label="confirmLabel"
                    :severity="confirmSeverity"
                    @click="emit('confirm')"
                />
            </div>
        </div>
    </Dialog>
</template>