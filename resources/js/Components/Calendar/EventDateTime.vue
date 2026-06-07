<script setup>
import DatePicker from 'primevue/datepicker';
import { computed } from 'vue';

import FormField from '@/Components/Forms/FormField.vue';

const props = defineProps({
    date: {
        type: [Date, String],
        default: null,
    },
    startsAt: {
        type: [Date, String],
        default: null,
    },
    endsAt: {
        type: [Date, String],
        default: null,
    },
    dateId: {
        type: String,
        default: 'event_date',
    },
    startsAtId: {
        type: String,
        default: 'event_starts_at',
    },
    endsAtId: {
        type: String,
        default: 'event_ends_at',
    },
    datePlaceholder: {
        type: String,
        default: 'Vyberte dátum',
    },
    startsAtPlaceholder: {
        type: String,
        default: '08:00',
    },
    endsAtPlaceholder: {
        type: String,
        default: '09:00',
    },
});

const emit = defineEmits([
    'update:date',
    'update:startsAt',
    'update:endsAt',
]);

const dateModel = computed({
    get: () => props.date,
    set: (value) => emit('update:date', value),
});

const startsAtModel = computed({
    get: () => props.startsAt,
    set: (value) => emit('update:startsAt', value),
});

const endsAtModel = computed({
    get: () => props.endsAt,
    set: (value) => emit('update:endsAt', value),
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <FormField
            label="Dátum"
            :for="dateId"
            required
        >
            <DatePicker
                :input-id="dateId"
                v-model="dateModel"
                date-format="dd.mm.yy"
                class="w-full"
                input-class="w-full"
                :placeholder="datePlaceholder"
            />
        </FormField>

        <FormField
            label="Začiatok"
            :for="startsAtId"
            required
        >
            <DatePicker
                :input-id="startsAtId"
                v-model="startsAtModel"
                time-only
                hour-format="24"
                icon-display="input"
                class="w-full"
                input-class="w-full"
                :placeholder="startsAtPlaceholder"
            />
        </FormField>

        <FormField
            label="Koniec"
            :for="endsAtId"
            required
        >
            <DatePicker
                :input-id="endsAtId"
                v-model="endsAtModel"
                time-only
                hour-format="24"
                icon-display="input"
                class="w-full"
                input-class="w-full"
                :placeholder="endsAtPlaceholder"
            />
        </FormField>
    </div>
</template>