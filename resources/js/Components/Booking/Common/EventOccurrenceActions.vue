<script setup>
import Button from 'primevue/button';

const props = defineProps({
    showPatients: {
        type: Boolean,
        default: false,
    },
    showDuplicate: {
        type: Boolean,
        default: true,
    },
    showEdit: {
        type: Boolean,
        default: true,
    },
    patientsLabel: {
        type: String,
        default: 'Pacienti',
    },
    duplicateLabel: {
        type: String,
        default: 'Duplikovať',
    },
    editLabel: {
        type: String,
        default: 'Upraviť',
    },
});

const emit = defineEmits([
    'patients',
    'duplicate',
    'edit',
]);
</script>

<template>
    <Button
        v-if="showPatients"
        type="button"
        :label="patientsLabel"
        outlined
        @click="emit('patients')"
    />

    <Button
        v-if="showDuplicate"
        type="button"
        :label="duplicateLabel"
        outlined
        @click="emit('duplicate')"
    />

    <Button
        v-if="showEdit"
        type="button"
        :label="editLabel"
        @click="emit('edit')"
    />
</template>
