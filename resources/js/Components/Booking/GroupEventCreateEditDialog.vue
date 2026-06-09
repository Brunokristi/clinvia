<script setup>
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, watch } from 'vue';

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
    groupEvent: {
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
    'save',
]);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const notificationForm = reactive({
    notify_patient: true,
    notification_reason: '',
});

watch(() => props.visible, (visible) => {
    if (visible) {
        notificationForm.notify_patient = true;
        notificationForm.notification_reason = '';
    }
});

const createTimeDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const stringValue = String(value);

    if (stringValue.includes('T') || stringValue.includes(' ')) {
        return new Date(stringValue.replace(' ', 'T'));
    }

    const [hours, minutes] = stringValue.slice(0, 5).split(':');
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
        if (!props.groupEvent?.date) {
            return null;
        }

        if (props.groupEvent.date instanceof Date) {
            return props.groupEvent.date;
        }

        return new Date(`${props.groupEvent.date}T00:00:00`);
    },
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.date = formatDateForBackend(value);
    },
});

const startsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.groupEvent?.starts_at);
    },
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.starts_at = formatTimeForBackend(value);
    },
});

const endsAtPickerModel = computed({
    get: () => {
        return createTimeDate(props.groupEvent?.ends_at);
    },
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.ends_at = formatTimeForBackend(value);
    },
});

const isEditing = computed(() => {
    return Boolean(props.groupEvent?.capacity_window_id ?? props.groupEvent?.id);
});

const dialogTitle = computed(() => {
    if (!props.groupEvent) {
        return 'Skupinový termín';
    }

    const service = props.services.find((item) => {
        return Number(item.id) === Number(props.groupEvent.service_id);
    });

    return service?.name
        ? `${service.name} · skupinový termín`
        : 'Skupinový termín';
});

const canSave = computed(() => {
    return Boolean(props.groupEvent)
        && Boolean(props.groupEvent.service_id)
        && Boolean(props.groupEvent.date)
        && Boolean(props.groupEvent.starts_at)
        && Boolean(props.groupEvent.ends_at)
        && Number(props.groupEvent.capacity ?? props.groupEvent.bookable_places ?? 0) > 0
        && (!props.groupEvent.repeats || Boolean(props.groupEvent.repeat_ends_on));
});

const closeDialog = () => {
    emit('update:visible', false);
    emit('close');
};

const saveGroupEvent = () => {
    if (!canSave.value) {
        return;
    }

    emit('save', {
        ...props.groupEvent,
        notify_patient: notificationForm.notify_patient,
        notification_reason: notificationForm.notification_reason,
    });
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
        :save-disabled="loading || !canSave"
        :show-save="Boolean(groupEvent)"
        :show-delete="false"
        @close="closeDialog"
        @save="saveGroupEvent"
    >
        <FormPage
            v-if="groupEvent"
            submit-label="Uložiť"
            :loading="loading"
            :show-submit="false"
        >
            <FormSection
                title="Kapacita a služba"
                description="Toto vytvorí alebo upraví reálny skupinový termín v tabuľke capacity_windows."
                columns="md:grid-cols-1"
            >
                <FormField
                    label="Služba"
                    for="group_service_id"
                    required
                >
                    <Select
                        id="group_service_id"
                        v-model="groupEvent.service_id"
                        :options="services"
                        option-label="name"
                        option-value="id"
                        placeholder="Vyberte službu"
                        class="w-full"
                    />
                </FormField>

                <FormField
                    label="Počet rezervovateľných miest"
                    for="group_capacity"
                    required
                >
                    <InputNumber
                        id="group_capacity"
                        v-model="groupEvent.capacity"
                        :min="1"
                        class="w-full"
                        input-class="w-full"
                        placeholder="Napr. 10"
                    />
                </FormField>

                <FormField
                    label="Interná poznámka"
                    for="group_admin_note"
                >
                    <Textarea
                        id="group_admin_note"
                        v-model="groupEvent.admin_note"
                        rows="3"
                        class="w-full"
                        placeholder="Voliteľná poznámka pre administráciu"
                    />
                </FormField>
            </FormSection>

            <RepeatingSection
                v-if="!isEditing"
                :model="groupEvent"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                description="Pri opakovaní sa vytvoria samostatné capacity_windows záznamy v jednej sérii."
                enabled-id="group_window_is_enabled"
                repeats-id="group_window_repeats"
                repeat-every-id="group_repeat_every"
                repeat-unit-id="group_repeat_unit"
                enabled-label="Skupinový termín je aktívny a viditeľný pre pacientov"
                repeats-label="Opakovať tento skupinový termín periodicky"
            />

            <FormSection
                v-if="isEditing && groupEvent.series_uuid"
                title="Séria"
                description="Použite len pri zmene služby alebo kapacity celej série."
                columns="md:grid-cols-1"
            >
                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="groupEvent.apply_to_series"
                        binary
                        input-id="group_apply_to_series"
                    />

                    <label
                        for="group_apply_to_series"
                        class="cursor-pointer text-sm font-medium text-dark"
                    >
                        Použiť zmenu služby, kapacity a poznámky na celú sériu
                    </label>
                </div>
            </FormSection>

            <FormSection
                title="Upozornenie pacientov"
                description="Pri presune a rušení pacientom môžete poslať email v detaile konkrétneho skupinového termínu."
                columns="md:grid-cols-1"
            >
                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="notificationForm.notify_patient"
                        binary
                        input-id="group_notify_patient"
                    />

                    <label
                        for="group_notify_patient"
                        class="cursor-pointer text-sm font-medium text-dark"
                    >
                        Pripraviť upozornenie pacientom
                    </label>
                </div>

                <FormField
                    v-if="notificationForm.notify_patient"
                    label="Dôvod správy pre pacientov"
                    for="group_notification_reason"
                >
                    <Textarea
                        id="group_notification_reason"
                        v-model="notificationForm.notification_reason"
                        rows="3"
                        class="w-full"
                        placeholder="Napríklad: Skupinový termín sa presúva z organizačných dôvodov."
                    />
                </FormField>
            </FormSection>
        </FormPage>

        <div
            v-else
            class="rounded-xl border border-soft bg-white p-6 text-center text-sm text-accent"
        >
            <i class="pi pi-exclamation-circle mb-2 block text-2xl text-red-400"></i>
            Skupinový termín sa nepodarilo načítať.
        </div>
    </EventDialog>
</template>
