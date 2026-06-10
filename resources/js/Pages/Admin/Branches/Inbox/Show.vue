<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import ReplyTemplateDialog from '@/Components/Branches/ReplyTemplateDialog.vue';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    message: {
        type: Object,
        required: true,
    },
    replyTemplates: {
        type: Array,
        default: () => [],
    },
});

const selectedTemplateId = ref(null);
const reply = ref(false);
const templateDialogVisible = ref(false);

const replyForm = useForm({
    subject: `Re: ${props.message.title || 'Správa'}`,
    body: '',
});

const periodLabels = {
    morning: 'Ráno',
    forenoon: 'Dopoludnia',
    afternoon: 'Popoludní',
    evening: 'Večer',
    rano: 'Ráno',
    dopoludnia: 'Dopoludnia',
    popoludni: 'Popoludní',
    vecer: 'Večer',
};

const replies = computed(() => {
    return props.message.replies ?? [];
});

const isContactForm = computed(() => {
    return props.message.type === 'contact_form';
});

const isBooking = computed(() => {
    return props.message.type === 'booking';
});

const isAppointmentRequest = computed(() => {
    return props.message.type === 'appointment_request';
});

const canReply = computed(() => {
    return isContactForm.value && Boolean(props.message.sender_email);
});

const booking = computed(() => {
    return props.message.booking ?? null;
});

const appointmentRequest = computed(() => {
    return props.message.appointment_request ?? props.message.appointmentRequest ?? null;
});

const openTemplateDialog = () => {
    templateDialogVisible.value = true;
};

const clearSelectedTemplate = () => {
    selectedTemplateId.value = null;
};

const typeLabel = (type) => {
    return {
        contact_form: 'Kontaktný formulár',
        booking: 'Rezervácia',
        appointment_request: 'Žiadosť o rezerváciu',
    }[type] ?? 'Správa';
};

const statusLabel = (message) => {
    return message.read_at ? 'Prečítaná' : 'Nová';
};

const createdLabel = (message) => {
    return formatDateTime(message.created_at);
};

const formatDate = (value) => {
    if (!value) {
        return 'Bez dátumu';
    }

    const date = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Bez dátumu';
    }

    return date.toLocaleDateString('sk-SK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const formatDateTime = (value) => {
    if (!value) {
        return 'Bez termínu';
    }

    const normalizedValue = String(value);

    const match = normalizedValue.match(
        /^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})/,
    );

    if (!match) {
        return 'Bez termínu';
    }

    const [, year, month, day, hour, minute] = match;

    return `${day}.${month}.${year} ${hour}:${minute}`;
};
const bookingServicesLabel = computed(() => {
    const services = booking.value?.services ?? [];

    if (services.length > 0) {
        return services.map((service) => service.name).join(', ');
    }

    return booking.value?.service?.name || 'Bez služby';
});

const bookingStartsAt = computed(() => {
    return booking.value?.starts_at
        ?? booking.value?.startsAt
        ?? booking.value?.capacity_window?.starts_at
        ?? booking.value?.capacityWindow?.starts_at
        ?? booking.value?.booking_slot?.starts_at
        ?? booking.value?.bookingSlot?.starts_at
        ?? null;
});

const bookingEndsAt = computed(() => {
    return booking.value?.ends_at
        ?? booking.value?.endsAt
        ?? booking.value?.capacity_window?.ends_at
        ?? booking.value?.capacityWindow?.ends_at
        ?? booking.value?.booking_slot?.ends_at
        ?? booking.value?.bookingSlot?.ends_at
        ?? null;
});

const bookingDateLabel = computed(() => {
    return formatDateTime(bookingStartsAt.value);
});

const bookingDurationLabel = computed(() => {
    if (!bookingStartsAt.value || !bookingEndsAt.value) {
        return null;
    }

    const start = new Date(bookingStartsAt.value);
    const end = new Date(bookingEndsAt.value);

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return null;
    }

    const minutes = Math.round((end - start) / 60000);

    if (minutes <= 0) {
        return null;
    }

    return `${minutes} min`;
});

const requestServicesLabel = computed(() => {
    const services = appointmentRequest.value?.services ?? [];

    if (services.length === 0) {
        return 'Bez služby';
    }

    return services.map((service) => service.name).join(', ');
});

const requestPreferredDateLabel = computed(() => {
    const date = appointmentRequest.value?.preferred_date
        ?? appointmentRequest.value?.preferredDate
        ?? appointmentRequest.value?.requested_date
        ?? null;

    return formatDate(date);
});

const requestPeriodLabel = computed(() => {
    const period = appointmentRequest.value?.preferred_period;

    return periodLabels[period] ?? period ?? 'Bez časti dňa';
});

const requestDurationLabel = computed(() => {
    const minutes = appointmentRequest.value?.total_duration_minutes;

    if (!minutes) {
        return null;
    }

    return `${minutes} min`;
});

const patientName = computed(() => {
    return props.message.sender_name
        ?? booking.value?.patient_name
        ?? appointmentRequest.value?.patient_name
        ?? 'Neznámy pacient';
});

const patientEmail = computed(() => {
    return props.message.sender_email
        ?? booking.value?.patient_email
        ?? appointmentRequest.value?.patient_email
        ?? null;
});

const patientPhone = computed(() => {
    return props.message.sender_phone
        ?? booking.value?.patient_phone
        ?? appointmentRequest.value?.patient_phone
        ?? null;
});

const patientNote = computed(() => {
    return booking.value?.patient_note
        ?? appointmentRequest.value?.patient_note
        ?? null;
});

watch(selectedTemplateId, (templateId) => {
    const template = props.replyTemplates.find((item) => {
        return Number(item.id) === Number(templateId);
    });

    if (!template) {
        return;
    }

    replyForm.subject = template.subject || replyForm.subject;
    replyForm.body = template.body;
});

const goBack = () => {
    router.get(route('branches.inbox.index', props.branch.id));
};

const goToCalendar = () => {
    router.get(route('branches.booking.agenda.page', props.branch.id));
};

const sendReply = () => {
    replyForm.post(route('branches.inbox.reply', [props.branch.id, props.message.id]), {
        preserveScroll: true,
        onSuccess: () => {
            selectedTemplateId.value = null;
            reply.value = false;
            replyForm.reset('body');
        },
    });
};

const deleteMessage = () => {
    router.delete(
        route('branches.inbox.destroy', [props.branch.id, props.message.id]),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Správa | ${branch.name}`" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-normal font-semibold text-dark">
                        {{ message.title || 'Správa' }}
                    </h1>

                    <p class="mt-2 text-normal text-accent">
                        {{ typeLabel(message.type) }} · {{ statusLabel(message) }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button
                        label="Späť na správy"
                        severity="secondary"
                        outlined
                        @click="goBack"
                    />

                    <Button
                        label="Zmazať"
                        severity="danger"
                        outlined
                        @click="deleteMessage"
                    />
                </div>
            </div>

            <section v-if="isBooking">
                <div class="flex flex-wrap items-start justify-end gap-4">
                    <Button
                        label="Otvoriť kalendár"
                        icon="pi pi-calendar"
                        @click="goToCalendar"
                    />
                </div>

                <article class="mt-5 rounded-md border border-soft bg-soft p-4">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-dark">
                                    {{ patientName }}
                                </h3>

                                <div class="space-y-1 text-xs text-accent">
                                    <p v-if="patientPhone">
                                        {{ patientPhone }}
                                    </p>

                                    <p v-if="patientEmail">
                                        {{ patientEmail }}
                                    </p>
                                </div>
                            </div>

                            <span class="rounded-md bg-white/70 px-2 py-1 text-xs font-semibold text-accent">
                                Rezervácia
                            </span>
                        </div>

                        <div class="grid gap-2 text-normal text-soft">
                            <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                <span class="font-medium">
                                    Služba
                                </span>

                                <span class="text-right">
                                    {{ bookingServicesLabel }}
                                </span>
                            </div>

                            <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                <span class="font-medium">
                                    Termín
                                </span>

                                <span class="text-right">
                                    {{ bookingDateLabel }}
                                </span>
                            </div>

                            <div
                                v-if="bookingDurationLabel"
                                class="request-card-soft-box flex items-center justify-end gap-3 text-accent"
                            >
                                <span class="font-medium">
                                    Trvanie
                                </span>

                                <span>
                                    {{ bookingDurationLabel }}
                                </span>
                            </div>
                        </div>

                        <p
                            v-if="patientNote"
                            class="request-card-soft-box rounded-md bg-white/60 p-3 text-xs leading-5 text-accent"
                        >
                            {{ patientNote }}
                        </p>
                    </div>
                </article>
            </section>

            <section v-if="isAppointmentRequest">
                <div class="flex flex-wrap items-start justify-end gap-4">
                    <Button
                        label="Otvoriť kalendár"
                        icon="pi pi-calendar"
                        @click="goToCalendar"
                    />
                </div>

                <article class="mt-5 rounded-md border border-soft bg-soft p-4">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-dark">
                                    {{ patientName }}
                                </h3>

                                <div class="space-y-1 text-xs text-accent">
                                    <p v-if="patientPhone">
                                        {{ patientPhone }}
                                    </p>

                                    <p v-if="patientEmail">
                                        {{ patientEmail }}
                                    </p>
                                </div>
                            </div>

                            <span class="rounded-md bg-white/70 px-2 py-1 text-xs font-semibold text-accent">
                                Žiadosť
                            </span>
                        </div>

                        <div class="grid gap-2 text-normal text-soft">
                            <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                <span class="font-medium">
                                    Služby
                                </span>

                                <span class="text-right">
                                    {{ requestServicesLabel }}
                                </span>
                            </div>

                            <div class="request-card-soft-box flex items-center justify-between gap-3 rounded-md bg-accent px-3 py-2 text-soft">
                                <span class="font-medium">
                                    Preferovaný termín
                                </span>

                                <span class="text-right">
                                    {{ requestPreferredDateLabel }} · {{ requestPeriodLabel }}
                                </span>
                            </div>

                            <div
                                v-if="requestDurationLabel"
                                class="request-card-soft-box flex items-center justify-end gap-3 text-accent"
                            >
                                <span class="font-medium">
                                    Trvanie
                                </span>

                                <span>
                                    {{ requestDurationLabel }}
                                </span>
                            </div>
                        </div>

                        <p
                            v-if="patientNote"
                            class="request-card-soft-box rounded-md bg-white/60 p-3 text-xs leading-5 text-accent"
                        >
                            {{ patientNote }}
                        </p>
                    </div>
                </article>
            </section>

            <section v-if="!isBooking && !isAppointmentRequest">
                <div class="flex items-start gap-1">
                    <h1 class="text-normal font-semibold text-dark">
                        {{ patientName }}
                    </h1>

                    <span class="text-accent text-normal">
                        •
                    </span>

                    <a
                        v-if="patientEmail"
                        class="text-normal text-accent"
                        :href="`mailto:${patientEmail}`"
                    >
                        {{ patientEmail }}
                    </a>

                    <span
                        v-else
                        class="text-normal text-accent"
                    >
                        Bez e-mailu
                    </span>
                </div>

                <div class="mt-6 space-y-4">
                    <article class="rounded-md border border-soft bg-soft p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-dark">
                                    {{ patientName }}
                                </p>

                                <p class="text-xs text-accent">
                                    {{ createdLabel(message) }}
                                </p>
                            </div>

                            <span class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-accent">
                                Pôvodná správa
                            </span>
                        </div>

                        <p class="whitespace-pre-line text-normal leading-7 text-accent">
                            {{ message.body }}
                        </p>
                    </article>

                    <article
                        v-for="replyItem in replies"
                        :key="replyItem.id"
                        class="rounded-md border border-soft bg-white p-4"
                    >
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-dark">
                                    {{ branch.name }}
                                </p>

                                <p class="text-xs text-accent">
                                    {{ formatDateTime(replyItem.sent_at ?? replyItem.created_at) }}
                                </p>
                            </div>

                            <span class="rounded-md bg-soft px-2 py-1 text-xs font-semibold text-accent">
                                Odoslaná odpoveď
                            </span>
                        </div>

                        <p class="mb-2 text-sm font-semibold text-dark">
                            {{ replyItem.subject }}
                        </p>

                        <p class="whitespace-pre-line text-normal leading-7 text-accent">
                            {{ replyItem.body }}
                        </p>
                    </article>
                </div>
                
                <div class="flex justify-end">
                    <Button
                        v-if="isContactForm && !reply"
                        class="mt-6"
                        label="Odpovedať"
                        @click="reply = true"
                    />
                </div>
            </section>

            <section
                v-if="isContactForm && reply"
                class="rounded-md border border-soft bg-white p-6"
            >
                <h2 class="text-normal font-semibold text-dark">
                    Odpoveď
                </h2>

                <p
                    v-if="!message.sender_email"
                    class="mt-3 rounded-md bg-soft px-4 py-3 text-sm text-accent"
                >
                    Na túto správu nie je možné odpovedať e-mailom, pretože odosielateľ neuviedol e-mailovú adresu.
                </p>

                <form
                    v-else
                    class="mt-5 space-y-4"
                    @submit.prevent="sendReply"
                >
                    <Select
                        v-model="selectedTemplateId"
                        :options="replyTemplates"
                        option-label="name"
                        option-value="id"
                        placeholder="Použiť predvolenú odpoveď"
                        show-clear
                        class="w-full"
                    >
                        <template #footer>
                            <div class="border-t border-soft p-2">
                                <Button
                                    type="button"
                                    label="Spravovať šablóny odpovedí"
                                    text
                                    class="w-full justify-start"
                                    @mousedown.prevent.stop
                                    @click.prevent.stop="openTemplateDialog"
                                />
                            </div>
                        </template>
                    </Select>

                    <div>
                        <label
                            for="subject"
                            class="mb-2 block text-sm font-medium text-dark"
                        >
                            Predmet
                        </label>

                        <InputText
                            id="subject"
                            v-model="replyForm.subject"
                            class="w-full"
                            :invalid="Boolean(replyForm.errors.subject)"
                        />

                        <p
                            v-if="replyForm.errors.subject"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ replyForm.errors.subject }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="body"
                            class="mb-2 block text-sm font-medium text-dark"
                        >
                            Správa
                        </label>

                        <Textarea
                            id="body"
                            v-model="replyForm.body"
                            rows="7"
                            auto-resize
                            class="w-full"
                            :invalid="Boolean(replyForm.errors.body)"
                        />

                        <p
                            v-if="replyForm.errors.body"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ replyForm.errors.body }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            label="Zrušiť"
                            outlined
                            :disabled="replyForm.processing"
                            @click="reply = false"
                        />

                        <Button
                            type="submit"
                            label="Odoslať odpoveď"
                            :loading="replyForm.processing"
                            :disabled="replyForm.processing || !canReply"
                        />
                    </div>
                </form>
            </section>
        </div>

        <ReplyTemplateDialog
            v-model:visible="templateDialogVisible"
            label="Šablóny odpovedí"
            :branch="branch"
            :templates="replyTemplates"
            :selected-template-id="selectedTemplateId"
            @deleted-selected-template="clearSelectedTemplate"
        />
    </AdminLayout>
</template>

<style scoped>
.request-card-soft-box {
    transition:
        background-color 150ms ease,
        color 150ms ease;
}
</style>