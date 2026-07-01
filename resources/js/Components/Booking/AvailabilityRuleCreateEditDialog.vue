<script setup>
import Button from 'primevue/button';
import { computed } from 'vue';

import ScopedEventDialog from '@/Components/Calendar/ScopedEventDialog.vue';
import FormSection from '@/Components/Forms/FormSection.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    rule: {
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
});

const emit = defineEmits([
    'update:visible',
    'close',
    'delete',
    'duplicate',
    'edit-in-unified-form',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const dialogTitle = computed(() => {
    if (!props.rule) {
        return 'Pravidlo rezervcácií';
    }

    return props.rule.id
        ? 'Pravidlo rezervácií'
        : 'Nové pravidlo rezervácií';
});

const createDateFromRule = (timeValue = null) => {
    if (!props.rule?.date) {
        return null;
    }

    const datePart = String(props.rule.date).slice(0, 10);
    const timePart = timeValue
        ? String(timeValue).slice(0, 5)
        : '00:00';

    const parsed = new Date(`${datePart}T${timePart}:00`);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const ruleDateModel = computed(() => {
    return createDateFromRule();
});

const ruleStartsAtModel = computed(() => {
    return createDateFromRule(props.rule?.starts_at ?? null);
});

const ruleEndsAtModel = computed(() => {
    return createDateFromRule(props.rule?.ends_at ?? null);
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const openUnifiedEditor = () => {
    if (!props.rule) {
        return;
    }

    emit('edit-in-unified-form', {
        rule: props.rule,
        selectedRuleOccurrence: props.selectedRuleOccurrence,
    });
};

const deleteOccurrence = () => {
    emit('delete', 'occurrence');
};

const deleteFromNowOn = () => {
    emit('delete', 'from_date');
};

const deleteAll = () => {
    emit('delete', 'series');
};

const duplicateRule = () => {
    emit('duplicate');
};
</script>

<template>
    <ScopedEventDialog
        v-model:visible="dialogVisible"
        :title="dialogTitle"
        width="max-w-3xl"
        :date="ruleDateModel"
        :starts-at="ruleStartsAtModel"
        :ends-at="ruleEndsAtModel"
        :date-time-disabled="true"
        :loading="loading"
        :show-save="false"
        show-delete
        :delete-disabled="!rule"
        :is-repeatable="Boolean(rule?.repeats)"
        :occurrence-date="selectedRuleOccurrence?.occurrenceDate ?? rule?.date"
        :show-duplicate="true"
        scope-mode="update"
        scope-subject-label="voľný čas"
        @close="closeDialog"
        @delete-occurrence="deleteOccurrence"
        @delete-from-now-on="deleteFromNowOn"
        @delete-all="deleteAll"
        @duplicate="duplicateRule"
    >
        <template #footer-start>
            <Button
                v-if="rule"
                type="button"
                label="Duplikovať"
                outlined
                @click="duplicateRule"
            />

            <Button
                v-if="rule"
                type="button"
                label="Upraviť"
                @click="openUnifiedEditor"
            />
        </template>

        <div v-if="rule" class="space-y-4">
            <FormSection title="Prehľad pravidla" columns="md:grid-cols-2">
                <div class="rounded-md bg-soft p-4 text-sm text-accent md:col-span-2">
                    <p><strong class="text-dark">Dátum:</strong> {{ rule.date }}</p>
                    <p><strong class="text-dark">Čas:</strong> {{ rule.starts_at }} - {{ rule.ends_at }}</p>
                    <p><strong class="text-dark">Služby:</strong> {{ services.filter((service) => rule.service_ids?.includes(service.id)).map((service) => service.name).join(', ') || '—' }}</p>
                    <p><strong class="text-dark">Opakovanie:</strong> {{ rule.repeats ? 'Opakuje sa' : 'Neopakuje sa' }}</p>
                </div>
            </FormSection>
        </div>

        <div
            v-else
            class="rounded-md bg-soft p-4 text-sm text-accent"
        >
            Pravidlo sa nepodarilo načítať.
        </div>
    </ScopedEventDialog>
</template>
