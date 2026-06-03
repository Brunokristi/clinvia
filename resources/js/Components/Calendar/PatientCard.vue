<script setup>
import Avatar from 'primevue/avatar';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        default: null,
    },
    status: {
        type: String,
        default: '',
    },
    serviceName: {
        type: String,
        default: '',
    },
});

const initials = computed(() => {
    const name = props.patient?.name ?? props.patient?.patient_name ?? '';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || '?';
});

const patientName = computed(() => {
    return props.patient?.name ?? props.patient?.patient_name ?? 'Bez mena';
});

const patientEmail = computed(() => {
    return props.patient?.email ?? props.patient?.patient_email ?? '';
});

const patientPhone = computed(() => {
    return props.patient?.phone ?? props.patient?.patient_phone ?? props.patient?.patient_phone_full ?? '';
});
</script>

<template>
    <div class="rounded-xl border border-soft bg-white p-4">
        <div class="flex flex-wrap items-start gap-4">
            <Avatar
                :label="initials"
                shape="circle"
                size="large"
            />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold text-dark">
                        {{ patientName }}
                    </h3>

                    <Tag
                        v-if="status"
                        :value="status"
                        severity="secondary"
                    />
                </div>

                <p
                    v-if="serviceName"
                    class="mt-1 text-sm text-accent"
                >
                    {{ serviceName }}
                </p>

                <div class="mt-3 grid gap-2 text-sm text-accent md:grid-cols-2">
                    <div v-if="patientEmail" class="flex items-center gap-2">
                        <i class="pi pi-envelope text-xs"></i>
                        <span class="truncate">{{ patientEmail }}</span>
                    </div>

                    <div v-if="patientPhone" class="flex items-center gap-2">
                        <i class="pi pi-phone text-xs"></i>
                        <span>{{ patientPhone }}</span>
                    </div>

                    <div v-if="!patientEmail && !patientPhone" class="text-sm text-accent">
                        Kontakt pacienta nie je vyplnený.
                    </div>
                </div>
            </div>
        </div>

        <slot />
    </div>
</template>
