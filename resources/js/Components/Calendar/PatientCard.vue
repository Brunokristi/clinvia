<script setup>
import Avatar from 'primevue/avatar';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    patientName: {
        type: String,
        default: '',
    },
    patientPhone: {
        type: String,
        default: '',
    },
    patientEmail: {
        type: String,
        default: '',
    },
});

const displayName = computed(() => {
    return props.patientName || 'Bez mena';
});

const initials = computed(() => {
    return displayName.value
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || '?';
});

const hasContact = computed(() => {
    return Boolean(props.patientPhone || props.patientEmail);
});

const emailHref = computed(() => {
    if (!props.patientEmail) {
        return '';
    }

    return `mailto:${props.patientEmail}`;
});

const phoneHref = computed(() => {
    if (!props.patientPhone) {
        return '';
    }

    return `tel:${props.patientPhone.replace(/\s+/g, '')}`;
});
</script>

<template>
    <div class="rounded-md border border-soft bg-white p-4">
        <div class="flex items-center gap-3">
            <Avatar
                :label="initials"
                shape="circle"
                size="small"
            />

            <h3 class="min-w-0 truncate text-base font-semibold text-dark">
                {{ displayName }}
            </h3>
        </div>

        <div
            v-if="hasContact"
            class="mt-4 flex flex-wrap gap-2"
        >
            <a
                v-if="patientEmail"
                :href="emailHref"
                class="inline-flex"
            >
                <Tag
                    icon="pi pi-envelope"
                    :value="patientEmail"
                    class="cursor-pointer"
                />
            </a>

            <a
                v-if="patientPhone"
                :href="phoneHref"
                class="inline-flex"
            >
                <Tag
                    icon="pi pi-phone"
                    :value="patientPhone"
                    class="cursor-pointer"
                />
            </a>
        </div>

        <p
            v-else
            class="mt-4 text-sm text-accent"
        >
            Kontakt pacienta nie je vyplnený.
        </p>

        <slot />
    </div>
</template>