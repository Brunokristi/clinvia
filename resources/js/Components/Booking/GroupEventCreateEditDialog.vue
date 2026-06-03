<script setup>
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { computed } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/Dialogs/RepeatingSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    currentRule: {
        type: Object,
        default: null,
    },
    selectedRuleOccurrence: {
        type: Object,
        default: null,
    },
    services: {
        type: Array,
        default: () => [],
    },
    repeatUnitOptions: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    getRuleTitle: {
        type: Function,
        required: true,
    },
    getRepeatLabel: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    'update:visible',
    'close',
    'save',
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const deleteCurrentRuleOccurrence = () => {
    if (props.currentRule?.repeats) {
        emit('delete-occurrence');

        return;
    }

    emit('delete-all');
};

const deleteCurrentRuleFromNowOn = () => {
    emit('delete-from-now-on');
};

const deleteCurrentRuleEverywhere = () => {
    emit('delete-all');
};
</script>

<template>
    <EventDialog
        v-if="currentRule"
        v-model:visible="dialogVisible"
        v-model:date="currentRule.date"
        v-model:starts-at="currentRule.starts_at"
        v-model:ends-at="currentRule.ends_at"
        width="max-w-3xl"
        date-id="group_rule_date"
        starts-at-id="group_rule_starts_at"
        ends-at-id="group_rule_ends_at"
        ends-at-placeholder="12:00"
        save-label="Uložiť"
        :loading="loading"
        :save-disabled="loading || !currentRule"
        show-delete
        :is-repeatable="Boolean(currentRule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate"
        delete-dialog-title="Vymazať skupinovú rezerváciu"
        delete-dialog-description="Vyberte rozsah odstránenia skupinovej rezervácie."
        delete-all-label="Odstrániť celú skupinovú rezerváciu"
        @close="closeDialog"
        @save="emit('save')"
        @delete-occurrence="deleteCurrentRuleOccurrence"
        @delete-from-now-on="deleteCurrentRuleFromNowOn"
        @delete-all="deleteCurrentRuleEverywhere"
    >
        <div class="space-y-6">
            <FormSection
                title="Kapacita a služba"
                description="Pacienti sa budú prihlasovať do rovnakého času až do naplnenia kapacity."
                columns="md:grid-cols-1"
            >
                <FormField label="Služba" for="group_service_id" required>
                    <Select
                        id="group_service_id"
                        v-model="currentRule.service_id"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                </FormField>

                <FormField label="Počet rezervovateľných miest" for="group_bookable_places" required>
                    <InputNumber
                        id="group_bookable_places"
                        v-model="currentRule.bookable_places"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                        placeholder="Napr. 10"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                :model="currentRule"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Nastavte platnosť, prípadnú periodickú opakovateľnosť skupinovej rezervácie."
                enabled-id="group_rule_is_enabled"
                repeats-id="group_rule_repeats"
                repeat-every-id="group_repeat_every"
                repeat-unit-id="group_repeat_unit"
                enabled-label="Skupinová rezervácia je aktívna a viditeľná pre pacientov"
                repeats-label="Opakovať túto skupinovú rezerváciu periodicky"
            />
        </div>
    </EventDialog>

    <EventDialog
        v-else
        v-model:visible="dialogVisible"
        :show-save="false"
        :show-delete="false"
        @close="closeDialog"
    >
        <div class="rounded-xl border border-soft bg-white p-6 text-center text-sm text-accent">
            <i class="pi pi-exclamation-circle text-2xl block mb-2 text-red-400"></i>
            Skupinovú rezerváciu sa nepodarilo úspešne načítať.
        </div>
    </EventDialog>
</template>
