<script setup>
defineProps({
    steps: {
        type: Array,
        required: true,
    },
    currentStep: {
        type: Number,
        required: true,
    },
    canOpenAdminStep: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['select']);
</script>

<template>
    <div class="mb-8 max-w-5xl rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-2">
            <button
                v-for="step in steps"
                :key="step.number"
                type="button"
                class="flex items-center gap-4 rounded-xl border p-4 text-left transition"
                :class="[
                    currentStep === step.number
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                    step.number === 2 && !canOpenAdminStep
                        ? 'cursor-not-allowed opacity-60'
                        : ''
                ]"
                @click="emit('select', step.number)"
            >
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                    :class="currentStep === step.number
                        ? 'bg-white text-slate-900'
                        : 'bg-slate-100 text-slate-700'"
                >
                    {{ step.number }}
                </span>

                <span>
                    <span class="block text-sm font-semibold">
                        {{ step.title }}
                    </span>

                    <span
                        class="mt-1 block text-xs"
                        :class="currentStep === step.number ? 'text-slate-200' : 'text-slate-500'"
                    >
                        {{ step.description }}
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>