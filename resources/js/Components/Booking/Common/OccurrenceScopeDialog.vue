<script setup>
import Button from 'primevue/button';
import { computed, nextTick, ref } from 'vue';

import FormDialog from '@/Components/Dialogs/FormDialog.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        required: true,
    },
    mode: {
        type: String,
        default: 'reschedule',
        validator: (value) => ['reschedule', 'delete', 'update'].includes(value),
    },
    subjectLabel: {
        type: String,
        default: 'termín',
    },
    countOccurrence: {
        type: Number,
        default: null,
    },
    countFromDate: {
        type: Number,
        default: null,
    },
    countSeries: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits([
    'update:visible',
    'select',
    'cancel',
]);

const suppressCancel = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);
    },
});

const title = computed(() => {
    if (props.mode === 'delete') {
        return 'Táto udalosť sa opakuje. Čo chcete vymazať?';
    }

    if (props.mode === 'update') {
        return 'Táto udalosť sa opakuje. Čo chcete upraviť?';
    }

    return 'Táto udalosť sa opakuje. Čo chcete presunúť?';
});

const actionVerb = computed(() => {
    if (props.mode === 'delete') {
        return 'Vymazať';
    }

    if (props.mode === 'update') {
        return 'Upraviť';
    }

    return 'Presunúť';
});

const description = computed(() => {
    if (props.mode === 'delete') {
        return `Vyberte rozsah, v ktorom chcete vymazať ${props.subjectLabel}.`;
    }

    if (props.mode === 'update') {
        return `Vyberte rozsah, v ktorom chcete upraviť ${props.subjectLabel}.`;
    }

    return `Vyberte rozsah, v ktorom chcete presunúť ${props.subjectLabel}.`;
});

const getOccurrenceWord = (count) => {
    if (count === 1) {
        return 'výskyt';
    }

    if (count >= 2 && count <= 4) {
        return 'výskyty';
    }

    return 'výskytov';
};

const formatCountLabel = (count) => {
    const number = Number(count);

    if (!Number.isFinite(number) || number <= 0) {
        return null;
    }

    return `${number} ${getOccurrenceWord(number)}`;
};

const occurrenceCountLabel = computed(() => formatCountLabel(props.countOccurrence));
const fromDateCountLabel = computed(() => formatCountLabel(props.countFromDate));
const seriesCountLabel = computed(() => formatCountLabel(props.countSeries));

const scopeOptions = computed(() => [
    {
        scope: 'occurrence',
        icon: 'pi pi-calendar',
        title: 'Iba tento termín',
        countLabel: occurrenceCountLabel.value,
        danger: false,
    },
    {
        scope: 'from_date',
        icon: 'pi pi-forward',
        title: 'Tento a nasledujúce termíny',
        countLabel: fromDateCountLabel.value,
        danger: false,
    },
    {
        scope: 'series',
        icon: props.mode === 'delete' ? 'pi pi-trash' : 'pi pi-refresh',
        title: 'Celú sériu',
        countLabel: seriesCountLabel.value,
        danger: props.mode === 'delete',
    },
]);

const choose = (scope) => {
    suppressCancel.value = true;

    emit('select', scope);
    emit('update:visible', false);

    nextTick(() => {
        suppressCancel.value = false;
    });
};

const cancelDialog = () => {
    if (suppressCancel.value) {
        return;
    }

    emit('cancel');
};

const closeDialog = () => {
    emit('update:visible', false);
    emit('cancel');
};
</script>

<template>
    <FormDialog
        v-model:visible="dialogVisible"
        :title="title"
        width="max-w-md"
        :show-footer="true"
        close-label="Zrušiť"
        @close="cancelDialog"
    >
        <div class="space-y-4">
            <p class="text-sm leading-6 text-accent">
                {{ description }}
            </p>

            <div class="space-y-2">
                <button
                    v-for="option in scopeOptions"
                    :key="option.scope"
                    type="button"
                    class="grid w-full grid-cols-[2.5rem_1fr] items-center gap-3 rounded-md border bg-white p-3 text-left transition"
                    :class="option.danger
                        ? 'border-red-100 hover:border-red-300 hover:bg-red-50'
                        : 'border-soft hover:border-accent hover:bg-soft'"
                    @click="choose(option.scope)"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-md"
                        :class="option.danger
                            ? 'bg-red-50 text-red-500'
                            : 'bg-soft text-accent'"
                    >
                        <i
                            :class="option.icon"
                            class="text-base"
                        />
                    </div>

                    <p
                        class="min-w-0 text-sm font-semibold"
                        :class="option.danger ? 'text-red-600' : 'text-dark'"
                    >
                        {{ option.title }}

                        <span
                            v-if="option.countLabel"
                            class="font-medium"
                            :class="option.danger ? 'text-red-500' : 'text-accent'"
                        >
                            ({{ option.countLabel }})
                        </span>
                    </p>
                </button>
            </div>
        </div>
    </FormDialog>
</template>