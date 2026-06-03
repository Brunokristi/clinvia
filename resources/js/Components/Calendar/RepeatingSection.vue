<script setup>
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    model: {
        type: Object,
        required: true,
    },
    repeatUnitOptions: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'Opakovanie',
    },
    description: {
        type: String,
        default: 'Nastavte, či sa má termín opakovať.',
    },
    enabledId: {
        type: String,
        default: 'event_is_enabled',
    },
    repeatsId: {
        type: String,
        default: 'event_repeats',
    },
    repeatEveryId: {
        type: String,
        default: 'event_repeat_every',
    },
    repeatUnitId: {
        type: String,
        default: 'event_repeat_unit',
    },
    enabledLabel: {
        type: String,
        default: 'Termín je aktívny',
    },
    repeatsLabel: {
        type: String,
        default: 'Opakovať tento termín periodicky',
    },
});
</script>

<template>
    <FormSection
        :title="title"
        :description="description"
        columns="md:grid-cols-1"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <Checkbox
                    v-model="model.is_enabled"
                    binary
                    :input-id="enabledId"
                />

                <label :for="enabledId" class="cursor-pointer text-sm font-medium text-dark">
                    {{ enabledLabel }}
                </label>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox
                    v-model="model.repeats"
                    binary
                    :input-id="repeatsId"
                />

                <label :for="repeatsId" class="cursor-pointer text-sm font-medium text-dark">
                    {{ repeatsLabel }}
                </label>
            </div>

            <div v-if="model.repeats" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <FormField label="Opakovať každých" :for="repeatEveryId">
                    <InputNumber
                        :id="repeatEveryId"
                        v-model="model.repeat_every"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                    />
                </FormField>

                <FormField label="Časové obdobie" :for="repeatUnitId" span="md:col-span-2">
                    <Select
                        :id="repeatUnitId"
                        v-model="model.repeat_unit"
                        :options="repeatUnitOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Vyberte jednotku"
                        class="w-full"
                    />
                </FormField>
            </div>
        </div>
    </FormSection>
</template>
