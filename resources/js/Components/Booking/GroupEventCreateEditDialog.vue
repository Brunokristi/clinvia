<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { computed, ref } from 'vue';

import AppDialog from '@/Components/Dialogs/FormDialog.vue';
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

const deleteRuleDialogVisible = ref(false);

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const openDeleteRuleDialog = () => {
    deleteRuleDialogVisible.value = true;
};

const closeDeleteRuleDialog = () => {
    deleteRuleDialogVisible.value = false;
};

const deleteCurrentRuleOccurrence = () => {
    emit('delete-occurrence');
    closeDeleteRuleDialog();
};

const deleteCurrentRuleFromNowOn = () => {
    emit('delete-from-now-on');
    closeDeleteRuleDialog();
};

const deleteCurrentRuleEverywhere = () => {
    emit('delete-all');
    closeDeleteRuleDialog();
};
</script>

<template>
    <AppDialog
        v-model:visible="dialogVisible"
        title="Skupinová rezervácia"
        width="max-w-3xl"
        @close="closeDialog"
    >
        <div
            v-if="currentRule"
            class="space-y-8"
        >
            <FormSection
                title="Čas skupinovej rezervácie"
                description="Nastavte deň a časový interval, v ktorom bude skupinová rezervácia dostupná."
                columns="md:grid-cols-3"
            >
                <FormField
                    label="Dátum"
                    for="group_rule_date"
                    required
                >
                    <InputText
                        id="group_rule_date"
                        v-model="currentRule.date"
                        type="date"
                        class="w-full"
                    />
                </FormField>

                <FormField
                    label="Od"
                    for="group_rule_starts_at"
                    required
                >
                    <InputText
                        id="group_rule_starts_at"
                        v-model="currentRule.starts_at"
                        type="time"
                        class="w-full"
                    />
                </FormField>

                <FormField
                    label="Do"
                    for="group_rule_ends_at"
                    required
                >
                    <InputText
                        id="group_rule_ends_at"
                        v-model="currentRule.ends_at"
                        type="time"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Kapacita a služba"
                description="Tento termín vytvorí jedno skupinové okno v kalendári. Pacienti sa budú prihlasovať do rovnakého času až do naplnenia kapacity."
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Služba"
                    for="group_service_id"
                    required
                >
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

                <FormField
                    label="Počet rezervovateľných miest"
                    for="group_bookable_places"
                    required
                >
                    <InputNumber
                        id="group_bookable_places"
                        v-model="currentRule.bookable_places"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                    />
                </FormField>
            </FormSection>

            <FormSection
                title="Opakovanie"
                description="Nastavte, či sa má skupinová rezervácia opakovať."
                columns="md:grid-cols-3"
            >
                <div class="md:col-span-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-dark">
                        <Checkbox
                            v-model="currentRule.repeats"
                            binary
                            input-id="group_rule_repeats"
                        />

                        Opakovať
                    </label>
                </div>

                <template v-if="currentRule.repeats">
                    <FormField
                        label="Opakovať každých"
                        for="group_repeat_every"
                    >
                        <InputNumber
                            id="group_repeat_every"
                            v-model="currentRule.repeat_every"
                            :min="1"
                            class="w-full"
                            input-class="w-full"
                        />
                    </FormField>

                    <FormField
                        label="Obdobie"
                        for="group_repeat_unit"
                        span="md:col-span-2"
                    >
                        <Select
                            id="group_repeat_unit"
                            v-model="currentRule.repeat_unit"
                            :options="repeatUnitOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </FormField>
                </template>
            </FormSection>

            <FormSection
                title="Stav"
                columns="md:grid-cols-1"
            >
                <label class="flex items-center gap-2 text-sm text-dark">
                    <Checkbox
                        v-model="currentRule.is_enabled"
                        binary
                        input-id="group_rule_is_enabled"
                    />

                    Skupinová rezervácia je aktívna
                </label>
            </FormSection>

            <div class="rounded-md bg-soft p-4 text-sm leading-6 text-accent">
                <strong>Ukážka:</strong>
                {{ currentRule.date }},
                {{ currentRule.starts_at }} – {{ currentRule.ends_at }}.
                {{ getRuleTitle(currentRule) }}.
                {{ getRepeatLabel(currentRule) }}.

                <span v-if="selectedRuleOccurrence?.occurrenceDate">
                    Vybraný výskyt:
                    {{ selectedRuleOccurrence.occurrenceDate }}.
                </span>
            </div>

            <div class="flex flex-wrap justify-between gap-3 border-t border-soft pt-5">
                <Button
                    type="button"
                    label="Odstrániť"
                    icon="pi pi-trash"
                    severity="danger"
                    outlined
                    @click="openDeleteRuleDialog"
                />

                <div class="flex flex-wrap gap-3">
                    <Button
                        type="button"
                        label="Zrušiť"
                        severity="secondary"
                        outlined
                        @click="closeDialog"
                    />

                    <Button
                        type="button"
                        label="Uložiť skupinovú rezerváciu"
                        icon="pi pi-save"
                        :loading="loading"
                        :disabled="loading"
                        @click="emit('save')"
                    />
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Skupinovú rezerváciu sa nepodarilo načítať.
        </div>
    </AppDialog>

    <AppDialog
        v-model:visible="deleteRuleDialogVisible"
        title="Vymazať skupinovú rezerváciu"
        width="max-w-xl"
        @close="closeDeleteRuleDialog"
    >
        <div class="space-y-5">
            <p class="text-sm text-accent">
                Čo chcete vymazať?
            </p>

            <div
                v-if="selectedRuleOccurrence?.occurrenceDate"
                class="rounded-md bg-soft p-4 text-sm text-accent"
            >
                Vybraný deň:
                <strong class="text-dark">
                    {{ selectedRuleOccurrence.occurrenceDate }}
                </strong>
            </div>

            <div class="flex flex-col gap-3">
                <Button
                    type="button"
                    label="Vymazať iba tento deň"
                    icon="pi pi-calendar-times"
                    severity="warn"
                    outlined
                    :disabled="!selectedRuleOccurrence?.occurrenceDate"
                    @click="deleteCurrentRuleOccurrence"
                />

                <Button
                    type="button"
                    label="Vymazať od tohto dňa ďalej"
                    icon="pi pi-forward"
                    severity="danger"
                    outlined
                    :disabled="!selectedRuleOccurrence?.occurrenceDate"
                    @click="deleteCurrentRuleFromNowOn"
                />

                <Button
                    type="button"
                    label="Vymazať celú skupinovú rezerváciu"
                    icon="pi pi-trash"
                    severity="danger"
                    @click="deleteCurrentRuleEverywhere"
                />
            </div>

            <div class="flex justify-end">
                <Button
                    type="button"
                    label="Zavrieť"
                    severity="secondary"
                    outlined
                    @click="closeDeleteRuleDialog"
                />
            </div>
        </div>
    </AppDialog>
</template>