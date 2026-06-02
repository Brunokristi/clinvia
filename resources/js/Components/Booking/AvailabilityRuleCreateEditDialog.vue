<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed, ref } from 'vue';

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
    <Dialog
        v-model:visible="dialogVisible"
        modal
        header="Pravidlo dostupnosti"
        :style="{ width: '780px', maxWidth: '95vw' }"
    >
        <div
            v-if="currentRule"
            class="space-y-5"
        >
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Dátum
                    </label>

                    <InputText
                        v-model="currentRule.date"
                        type="date"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Od
                    </label>

                    <InputText
                        v-model="currentRule.starts_at"
                        type="time"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Do
                    </label>

                    <InputText
                        v-model="currentRule.ends_at"
                        type="time"
                        class="w-full"
                    />
                </div>
            </div>

            <div class="space-y-4 rounded-md border border-soft bg-soft/40 p-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-dark">
                        Rezervovateľné služby
                    </label>

                    <MultiSelect
                        v-model="currentRule.service_ids"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        display="chip"
                        placeholder="Vyberte služby"
                        class="w-full"
                    />
                </div>

                <p class="text-sm leading-6 text-accent">
                    Tento režim použite pre voľný čas v kalendári. Klient si vyberie jednu
                    z povolených služieb a systém obsadí potrebný čas podľa trvania služby.
                </p>
            </div>

            <div class="rounded-md border border-soft bg-white p-4">
                <label class="flex items-center gap-2 text-sm font-medium text-dark">
                    <Checkbox
                        v-model="currentRule.repeats"
                        binary
                    />

                    Opakovať
                </label>

                <div
                    v-if="currentRule.repeats"
                    class="mt-4 grid gap-4 md:grid-cols-3"
                >
                    <div>
                        <label class="mb-2 block text-sm font-medium text-dark">
                            Opakovať každých
                        </label>

                        <InputNumber
                            v-model="currentRule.repeat_every"
                            :min="1"
                            class="w-full"
                            input-class="w-full"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-dark">
                            Obdobie
                        </label>

                        <Select
                            v-model="currentRule.repeat_unit"
                            :options="repeatUnitOptions"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                        />
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-dark">
                <Checkbox
                    v-model="currentRule.is_enabled"
                    binary
                />

                Pravidlo je aktívne
            </label>

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
                        @click="$emit('close')"
                    />

                    <Button
                        type="button"
                        label="Uložiť pravidlo"
                        icon="pi pi-save"
                        :loading="loading"
                        @click="$emit('save')"
                    />
                </div>
            </div>
        </div>
    </Dialog>

    <Dialog
        v-model:visible="deleteRuleDialogVisible"
        modal
        header="Vymazať pravidlo"
        :style="{ width: '520px', maxWidth: '95vw' }"
    >
        <div class="space-y-4">
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
                    label="Vymazať celé pravidlo"
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
    </Dialog>
</template>