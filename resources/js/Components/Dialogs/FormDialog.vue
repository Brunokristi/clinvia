<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';

const visible = defineModel('visible', {
    type: Boolean,
    default: false,
});

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    width: {
        type: String,
        default: 'max-w-3xl',
    },
    closeLabel: {
        type: String,
        default: 'Zavrieť',
    },
    showFooter: {
        type: Boolean,
        default: false,
    },
    dismissableMask: {
        type: Boolean,
        default: true,
    },
    closable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const closeDialog = () => {
    visible.value = false;
    emit('close');
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :closable="closable"
        :dismissable-mask="dismissableMask"
        :class="['w-[95vw]', width]"
        @hide="emit('close')"
    >
        <template #header>
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-dark">
                    {{ title }}
                </h2>
            </div>
        </template>

        <slot :close="closeDialog" />

        <template
            v-if="showFooter"
            #footer
        >
            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    :label="closeLabel"
                    severity="secondary"
                    outlined
                    @click="closeDialog"
                />

                <slot
                    name="footer"
                    :close="closeDialog"
                />
            </div>
        </template>
    </Dialog>
</template>