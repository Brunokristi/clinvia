<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import MultiSelect from 'primevue/multiselect';
import { computed, ref } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
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
    'save-scope',
    'delete-occurrence',
    'delete-from-now-on',
    'delete-all',
]);

const rescheduleChoiceVisible = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const isExistingRepeatedRule = computed(() => {
    return Boolean(
        props.currentRule?.id
            && props.currentRule?.repeats
            && props.selectedRuleOccurrence?.occurrenceDate,
    );
});

const createTimeDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const [hours, minutes] = String(value).slice(0, 5).split(':');

    const date = new Date();

    date.setHours(Number(hours), Number(minutes), 0, 0);

    return date;
};

const formatDateForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const formatTimeForBackend = (value) => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const datePickerModel = computed({
    get: () => {
        if (!props.currentRule?.date) {
            return null;
        }

        if (props.currentRule.date instanceof Date) {
            return props.currentRule.date;
        }

        return new Date(`${props.currentRule.date}T00:00:00`);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.date = formatDateForBackend(value);
    },
});

const startsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.currentRule?.starts_at);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.currentRule?.ends_at);
    },
    set: (value) => {
        if (!props.currentRule) {
            return;
        }

        props.currentRule.ends_at = formatTimeForBackend(value);
    },
});

const dialogTitle = computed(() => {
    if (!props.currentRule) {
        return 'Pravidlo dostupnosti';
    }

    return props.getRuleTitle(props.currentRule) || 'Pravidlo dostupnosti';
});

const closeDialog = () => {
    rescheduleChoiceVisible.value = false;
    emit('update:visible', false);
    emit('close');
};

const saveCurrentRule = () => {
    if (isExistingRepeatedRule.value) {
        rescheduleChoiceVisible.value = true;

        return;
    }

    emit('save');
};

const submitRescheduleScope = (scope) => {
    rescheduleChoiceVisible.value = false;
    emit('save-scope', {
        reschedule_scope: scope,
    });
};

const closeRescheduleChoice = () => {
    rescheduleChoiceVisible.value = false;
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
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        v-model:date="datePickerModel"
        v-model:starts-at="startsAtPickerModel"
        v-model:ends-at="endsAtPickerModel"
        width="max-w-3xl"
        save-label="Uložiť"
        :loading="loading"
        :save-disabled="loading"
        show-delete
        :is-repeatable="Boolean(currentRule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate"
        @close="closeDialog"
        @save="saveCurrentRule"
        @delete-occurrence="deleteCurrentRuleOccurrence"
        @delete-from-now-on="deleteCurrentRuleFromNowOn"
        @delete-all="deleteCurrentRuleEverywhere"
    >
        <FormPage
            v-if="currentRule"
            submit-label="Uložiť"
            :loading="loading"
            :show-submit="false"
        >
            <FormSection
                title="Služby"
                columns="md:grid-cols-2"
            >
                <FormField
                    label="Priradené rezervovateľné služby"
                    for="availability_service_ids"
                    required
                    span="md:col-span-2"
                >
                    <MultiSelect
                        id="availability_service_ids"
                        v-model="currentRule.service_ids"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        display="chip"
                        placeholder="Vyberte jednu alebo viac služieb"
                        class="w-full"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                :model="currentRule"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Nastavte, či sa má dostupnosť opakovať."
                enabled-id="availability_rule_is_enabled"
                repeats-id="availability_rule_repeats"
                repeat-every-id="availability_repeat_every"
                repeat-unit-id="availability_repeat_unit"
                enabled-label="Pravidlo dostupnosti je aktívne"
                repeats-label="Toto pravidlo sa opakuje periodicky"
            />
        </FormPage>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Pravidlo sa nepodarilo načítať.
        </div>
    </EventDialog>

    <Dialog
        v-model:visible="rescheduleChoiceVisible"
        modal
        header="Presunúť opakovanie"
        class="w-full max-w-md"
    >
        <div class="space-y-4">
            <p class="text-sm leading-6 text-accent">
                Toto pravidlo dostupnosti je opakované. Čo chcete presunúť?
            </p>

            <div class="grid gap-3">
                <Button
                    type="button"
                    label="Iba tento výskyt"
                    icon="pi pi-calendar"
                    outlined
                    @click="submitRescheduleScope('occurrence')"
                />

                <Button
                    type="button"
                    label="Tento a nasledujúce výskyty"
                    icon="pi pi-calendar-plus"
                    outlined
                    @click="submitRescheduleScope('from_date')"
                />

                <Button
                    type="button"
                    label="Celú sériu"
                    icon="pi pi-refresh"
                    severity="danger"
                    outlined
                    @click="submitRescheduleScope('series')"
                />
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Zrušiť"
                text
                @click="closeRescheduleChoice"
            />
        </template>
    </Dialog>
</template>
