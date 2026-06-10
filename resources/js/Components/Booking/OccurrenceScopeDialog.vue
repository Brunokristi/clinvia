<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed } from 'vue';

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
});

const emit = defineEmits([
    'update:visible',
    'select',
    'cancel',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);

        if (!value) {
            emit('cancel');
        }
    },
});

const title = computed(() => {
    if (props.mode === 'delete') {
        return 'Vymazať opakovanie';
    }

    if (props.mode === 'update') {
        return 'Upraviť opakovanie';
    }

    return 'Presunúť opakovanie';
});

const description = computed(() => {
    if (props.mode === 'delete') {
        return `Tento ${props.subjectLabel} je súčasťou opakovanej série. Čo chcete vymazať?`;
    }

    if (props.mode === 'update') {
        return `Tento ${props.subjectLabel} je súčasťou opakovanej série. Čo chcete upraviť?`;
    }

    return `Tento ${props.subjectLabel} je súčasťou opakovanej série. Čo chcete presunúť?`;
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

const choose = (scope) => {
    emit('select', scope);
    emit('update:visible', false);
};
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :header="title"
        class="w-full max-w-lg"
    >
        <div class="space-y-4">
            <p class="text-sm leading-6 text-accent">
                {{ description }}
            </p>

            <div class="grid gap-3">
                <button
                    type="button"
                    class="rounded-md border border-soft bg-white px-4 py-3 text-left transition hover:border-accent hover:bg-soft"
                    @click="choose('occurrence')"
                >
                    <span class="block text-sm font-semibold text-dark">
                        {{ actionVerb }} iba tento výskyt
                    </span>

                    <span class="mt-1 block text-xs leading-5 text-accent">
                        Ostatné výskyty v sérii ostanú bez zmeny.
                    </span>
                </button>

                <button
                    type="button"
                    class="rounded-md border border-soft bg-white px-4 py-3 text-left transition hover:border-accent hover:bg-soft"
                    @click="choose('from_date')"
                >
                    <span class="block text-sm font-semibold text-dark">
                        {{ actionVerb }} tento a nasledujúce výskyty
                    </span>

                    <span class="mt-1 block text-xs leading-5 text-accent">
                        Predchádzajúce výskyty ostanú pôvodné.
                    </span>
                </button>

                <button
                    type="button"
                    class="rounded-md border border-red-100 bg-white px-4 py-3 text-left transition hover:border-red-300 hover:bg-red-50"
                    @click="choose('series')"
                >
                    <span class="block text-sm font-semibold text-red-600">
                        {{ actionVerb }} celú sériu
                    </span>

                    <span class="mt-1 block text-xs leading-5 text-red-500">
                        Zmena sa použije na všetky výskyty série.
                    </span>
                </button>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                label="Zrušiť"
                text
                @click="dialogVisible = false"
            />
        </template>
    </Dialog>
</template>