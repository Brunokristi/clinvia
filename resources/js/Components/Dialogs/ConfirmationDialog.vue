<script setup>
import Modal from '@/Components/Modal.vue';
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
    icon: {
        type: String,
        default: 'pi pi-exclamation-triangle',
    },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" maxWidth="md" @close="emit('cancel')">
        <div class="p-6 sm:p-8">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                    <i :class="icon" class="text-lg" />
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-slate-900">
                        {{ title }}
                    </h3>

                    <p v-if="message" class="mt-2 text-sm leading-6 text-slate-600">
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
    </Modal>
</template>