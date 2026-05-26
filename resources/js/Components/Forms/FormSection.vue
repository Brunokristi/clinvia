<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    columns: {
        type: String,
        default: 'md:grid-cols-3',
    },
});
</script>

<template>
    <section class="border-soft bg-white py-10">
        <div class="mb-6 flex flex-col gap-2">
            <h2 class="text-normal font-semibold text-dark">
                {{ title }}
            </h2>

            <p
                v-if="description"
                class="text-normal text-accent"
            >
                {{ description }}
            </p>
        </div>

        <div
            class="grid gap-5"
            :class="columns"
        >
            <slot />
        </div>
    </section>
</template>