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
            for="event_date"
            required
        >
            <DatePicker
                input-id="event_date"
                v-model="dateModel"
                date-format="dd.mm.yy"
                class="w-full"
                input-class="w-full"
                placeholder="Vyberte dátum"
            />
        </FormField>

        <FormField
            label="Začiatok"
            for="event_starts_at"
            required
        >
            <DatePicker
                input-id="event_starts_at"
                v-model="startsAtModel"
                time-only
                hour-format="24"
                icon-display="input"
                class="w-full"
                input-class="w-full"
                placeholder="08:00"
            />
        </FormField>

        <FormField
            label="Koniec"
            for="event_ends_at"
            required
        >
            <DatePicker
                input-id="event_ends_at"
                v-model="endsAtModel"
                time-only
                hour-format="24"
                icon-display="input"
                class="w-full"
                input-class="w-full"
                placeholder="09:00"
            />
        </FormField>
    </div>
</template>