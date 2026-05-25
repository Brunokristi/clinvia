<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section>
        <div class="border-b border-slate-200">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <div>
                        <h2 class="text-heading text-accent">
                            {{ title }}
                        </h2>

                        <p
                            v-if="description"
                            class="mt-1 text-normal text-dark"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <div
                        v-if="$slots.actions"
                        class="flex flex-wrap items-center gap-3 justify-end py-4"
                    >
                        <slot name="actions" />
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-slate-200 p-6"
        >
            <slot name="footer" />
        </div>
    </section>
</template>