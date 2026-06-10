<script setup>
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { computed, reactive, ref, watch } from 'vue';

import EventDialog from '@/Components/Calendar/EventDialog.vue';
import RepeatingSection from '@/Components/Calendar/RepeatingSection.vue';
import OccurrenceScopeDialog from '@/Components/Booking/OccurrenceScopeDialog.vue';
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

const updateChoiceVisible = ref(false);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const notificationForm = reactive({
    notify_patient: true,
    notification_reason: '',
});

const stripTimezoneFromDateTime = (value) => {
    if (!value) {
        return null;
    }

    return String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);
};

const createTimeDate = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const stringValue = stripTimezoneFromDateTime(value);

    if (!stringValue) {
        return null;
    }

    if (stringValue.includes('T')) {
        return new Date(stringValue);
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

    if (Number.isNaN(date.getTime())) {
        return '';
    }

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

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const addYearsToDate = (dateValue, years = 2) => {
    if (!dateValue) {
        return null;
    }

    const stringValue = dateValue instanceof Date
        ? formatDateForBackend(dateValue)
        : String(dateValue).slice(0, 10);

    const date = new Date(`${stringValue}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    date.setFullYear(date.getFullYear() + years);

    return formatDateForBackend(date);
};

const normalizeRepeatDefaults = () => {
    if (!props.groupEvent) {
        return;
    }

    props.groupEvent.repeat_every = Number(props.groupEvent.repeat_every || 1);
    props.groupEvent.repeat_unit = props.groupEvent.repeat_unit || 'weeks';

    if (props.groupEvent.repeats && !props.groupEvent.repeat_ends_on) {
        props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
    }
};

watch(() => props.visible, (visible) => {
    if (visible) {
        notificationForm.notify_patient = true;
        notificationForm.notification_reason = '';
        updateChoiceVisible.value = false;

        normalizeRepeatDefaults();
    }
});

watch(() => props.groupEvent?.repeats, () => {
    normalizeRepeatDefaults();
});

watch(() => props.groupEvent?.date, () => {
    if (props.groupEvent?.repeats && !props.groupEvent.repeat_ends_on) {
        props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
    }
});

const datePickerModel = computed({
    get: () => {
        if (!props.groupEvent?.date) {
            return null;
        }

        if (props.groupEvent.date instanceof Date) {
            return props.groupEvent.date;
        }

        return new Date(`${String(props.groupEvent.date).slice(0, 10)}T00:00:00`);
    },
    set: (value) => {
        if (!props.groupEvent) {
            return;
        }

        props.groupEvent.date = formatDateForBackend(value);

        if (props.groupEvent.repeats && !props.groupEvent.repeat_ends_on) {
            props.groupEvent.repeat_ends_on = addYearsToDate(props.groupEvent.date, 2);
        }
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

const isPartOfSeries = computed(() => {
    return Boolean(props.groupEvent?.series_uuid);
});

const canEditRepeating = computed(() => {
    return !isEditing.value || isPartOfSeries.value;
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

const hasValidRepeatSettings = computed(() => {
    if (!props.groupEvent?.repeats) {
        return true;
    }

    return Boolean(props.groupEvent.repeat_ends_on)
        && Number(props.groupEvent.repeat_every ?? 0) >= 1
        && ['days', 'weeks', 'months'].includes(props.groupEvent.repeat_unit);
});

const canSave = computed(() => {
    return Boolean(props.groupEvent)
        && Boolean(props.groupEvent.service_id)
        && Boolean(props.groupEvent.date)
        && Boolean(props.groupEvent.starts_at)
        && Boolean(props.groupEvent.ends_at)
        && Number(props.groupEvent.capacity ?? props.groupEvent.bookable_places ?? 0) > 0
        && hasValidRepeatSettings.value;
});

const closeDialog = () => {
    updateChoiceVisible.value = false;
    emit('update:visible', false);
    emit('close');
};

const buildSavePayload = (scope = 'occurrence') => {
    return {
        ...props.groupEvent,
        update_scope: scope,
        repeat_ends_on: props.groupEvent.repeats
            ? String(props.groupEvent.repeat_ends_on).slice(0, 10)
            : null,
        notify_patient: notificationForm.notify_patient,
        notification_reason: notificationForm.notification_reason,
    };
};

const saveGroupEvent = () => {
    if (!canSave.value) {
        return;
    }

    if (isEditing.value && isPartOfSeries.value) {
        updateChoiceVisible.value = true;

        return;
    }

    emit('save', buildSavePayload('occurrence'));
};

const submitUpdateScope = (scope) => {
    updateChoiceVisible.value = false;
    emit('save', buildSavePayload(scope));
};

const cancelUpdateScope = () => {
    updateChoiceVisible.value = false;
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
                v-if="canEditRepeating"
                :model="groupEvent"
                :repeat-unit-options="repeatUnitOptions"
                title="Opakovanie"
                :description="isEditing
                    ? 'Zmena opakovania upraví vybrané termíny v tejto sérii.'
                    : 'Pri opakovaní sa vytvoria samostatné capacity_windows záznamy v jednej sérii.'"
                enabled-id="group_window_is_enabled"
                repeats-id="group_window_repeats"
                repeat-every-id="group_repeat_every"
                repeat-unit-id="group_repeat_unit"
                enabled-label="Skupinový termín je aktívny a viditeľný pre pacientov"
                repeats-label="Opakovať tento skupinový termín periodicky"
            />

            <div
                v-if="groupEvent.repeats && !hasValidRepeatSettings"
                class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-600"
            >
                Pri opakovaní musí byť vyplnený dátum ukončenia opakovania, interval a jednotka opakovania.
            </div>

            <FormSection
                title="Upozornenie pacientov"
                description="Pri presune alebo úprave skupinového termínu môžete pacientom poslať email."
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

    <OccurrenceScopeDialog
        v-model:visible="updateChoiceVisible"
        mode="update"
        subject-label="skupinový termín"
        @select="submitUpdateScope"
        @cancel="cancelUpdateScope"
    />
</template>