<script setup>
import Button from 'primevue/button';
import { computed } from 'vue';

import ScopedEventDialog from '@/Components/Calendar/ScopedEventDialog.vue';
import PatientCard from '@/Components/Calendar/PatientCard.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    booking: {
        type: Object,
        default: null,
    },
    bookingNotes: {
        type: Object,
        default: () => ({}),
    },
    services: {
        type: Array,
        default: () => [],
    },
    availableSlots: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'update:visible',
    'cancel-booking',
    'duplicate-booking',
    'edit-in-unified-form',
]);

const parseDateTime = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const normalized = String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const bookingDateModel = computed(() => {
    return parseDateTime(props.booking?.starts_at);
});

const bookingStartsAtModel = computed(() => {
    return parseDateTime(props.booking?.starts_at);
});

const bookingEndsAtModel = computed(() => {
    return parseDateTime(props.booking?.ends_at);
});

const closeDialog = () => {
    emit('update:visible', false);
};

const openUnifiedEditor = () => {
    if (!props.booking) {
        return;
    }

    emit('edit-in-unified-form', props.booking);
};

const cancelBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
    });
};

const duplicateBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('duplicate-booking', props.booking);
};
</script>

<template>
    <ScopedEventDialog
        :visible="visible"
        title="Rezervácia"
        width="max-w-3xl"
        :date="bookingDateModel"
        :starts-at="bookingStartsAtModel"
        :ends-at="bookingEndsAtModel"
        :date-time-disabled="true"
        :show-save="false"
        :show-delete="false"
        :is-repeatable="Boolean(booking?.series_uuid || booking?.recurrence)"
        :show-duplicate="true"
        @update:visible="emit('update:visible', $event)"
        @close="closeDialog"
        @duplicate="duplicateBooking"
    >
        <template #footer-start>
            <Button
                v-if="booking"
                type="button"
                label="Odstrániť rezerváciu"
                severity="danger"
                outlined
                @click="cancelBooking"
            />

            <Button
                v-if="booking"
                type="button"
                label="Duplikovať"
                outlined
                @click="duplicateBooking"
            />

            <Button
                v-if="booking"
                type="button"
                label="Upraviť"
                @click="openUnifiedEditor"
            />
        </template>

        <div v-if="booking" class="space-y-4">
            <PatientCard
                :patient-name="booking.patient_name"
                :patient-phone="booking.patient_phone"
                :patient-email="booking.patient_email"
            />

            <FormSection title="Prehľad rezervácie" columns="md:grid-cols-2">
                <div class="rounded-md bg-soft p-4 text-sm text-accent md:col-span-2">
                    <p><strong class="text-dark">Termín:</strong> {{ booking.starts_at }} - {{ booking.ends_at }}</p>
                    <p><strong class="text-dark">Služby:</strong> {{ booking.services?.map((service) => service.name).join(', ') || booking.service?.name || '—' }}</p>
                    <p><strong class="text-dark">Opakovanie:</strong> {{ booking.recurrence ? 'Opakuje sa' : 'Neopakuje sa' }}</p>
                </div>
            </FormSection>
        </div>

        <div
            v-else
            class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-600"
        >
            Rezerváciu sa nepodarilo načítať.
        </div>
    </ScopedEventDialog>
</template>
