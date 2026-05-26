<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormSection from '@/Components/Forms/FormSection.vue';
import { useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
});

const toast = useToast();

const dayNames = [
    { value: 1, label: 'Pondelok' },
    { value: 2, label: 'Utorok' },
    { value: 3, label: 'Streda' },
    { value: 4, label: 'Štvrtok' },
    { value: 5, label: 'Piatok' },
    { value: 6, label: 'Sobota' },
    { value: 7, label: 'Nedeľa' },
];

const createTimeDate = (value) => {
    const [hours = '08', minutes = '00'] = String(value || '08:00').split(':');
    const date = new Date();

    date.setHours(Number(hours));
    date.setMinutes(Number(minutes));
    date.setSeconds(0);
    date.setMilliseconds(0);

    return date;
};

const formatTimeForSubmit = (value) => {
    if (!value) {
        return '';
    }

    if (typeof value === 'string') {
        return value.slice(0, 5);
    }

    const hours = String(value.getHours()).padStart(2, '0');
    const minutes = String(value.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
};

const createDefaultOpeningHours = () => dayNames.map((day) => {
    const existingDay = props.branch.opening_hours?.find((item) => item.day_of_week === day.value);

    if (existingDay) {
        return {
            day_of_week: existingDay.day_of_week,
            is_closed: Boolean(existingDay.is_closed),
            note: existingDay.note ?? '',
            sort_order: existingDay.sort_order ?? day.value,
            intervals: existingDay.intervals?.map((interval, index) => ({
                opens_at: createTimeDate(interval.opens_at?.slice(0, 5) ?? '08:00'),
                closes_at: createTimeDate(interval.closes_at?.slice(0, 5) ?? '16:00'),
                sort_order: interval.sort_order ?? index,
            })) ?? [],
        };
    }

    return {
        day_of_week: day.value,
        is_closed: day.value >= 6,
        note: '',
        sort_order: day.value,
        intervals: day.value >= 6 ? [] : [
            {
                opens_at: createTimeDate('08:00'),
                closes_at: createTimeDate('16:00'),
                sort_order: 0,
            },
        ],
    };
});

const openingHoursForm = useForm({
    opening_hours: createDefaultOpeningHours(),
});

const dayLabel = (dayOfWeek) => {
    return dayNames.find((day) => day.value === dayOfWeek)?.label ?? dayOfWeek;
};

const openingHoursPayload = () => {
    return openingHoursForm.opening_hours.map((day) => ({
        ...day,
        intervals: day.is_closed
            ? []
            : day.intervals.map((interval, index) => ({
                opens_at: formatTimeForSubmit(interval.opens_at),
                closes_at: formatTimeForSubmit(interval.closes_at),
                sort_order: index,
            })),
    }));
};

const saveOpeningHours = () => {
    openingHoursForm
        .transform(() => ({
            opening_hours: openingHoursPayload(),
        }))
        .put(route('branches.opening-hours.update', props.branch.id), {
            preserveScroll: true,
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Chyba',
                    detail: 'Nepodarilo sa uložiť otváracie hodiny.',
                    life: 3000,
                });
            },
        });
};

const addInterval = (day) => {
    day.is_closed = false;

    day.intervals.push({
        opens_at: createTimeDate('08:00'),
        closes_at: createTimeDate('16:00'),
        sort_order: day.intervals.length,
    });
};

const removeInterval = (day, intervalIndex) => {
    day.intervals.splice(intervalIndex, 1);

    day.intervals = day.intervals.map((interval, index) => ({
        ...interval,
        sort_order: index,
    }));
};

const cloneIntervals = (intervals) => {
    return intervals.map((interval, index) => ({
        opens_at: createTimeDate(formatTimeForSubmit(interval.opens_at)),
        closes_at: createTimeDate(formatTimeForSubmit(interval.closes_at)),
        sort_order: index,
    }));
};

const applyMondayToAllDays = () => {
    const monday = openingHoursForm.opening_hours.find((day) => day.day_of_week === 1);

    if (!monday) {
        return;
    }

    openingHoursForm.opening_hours = openingHoursForm.opening_hours.map((day) => ({
        ...day,
        is_closed: monday.is_closed,
        note: monday.note,
        intervals: cloneIntervals(monday.intervals),
    }));
};

const setWeekdaysFromMonday = () => {
    const monday = openingHoursForm.opening_hours.find((day) => day.day_of_week === 1);

    if (!monday) {
        return;
    }

    openingHoursForm.opening_hours = openingHoursForm.opening_hours.map((day) => {
        if (day.day_of_week >= 6) {
            return day;
        }

        return {
            ...day,
            is_closed: monday.is_closed,
            note: monday.note,
            intervals: cloneIntervals(monday.intervals),
        };
    });
};

const fieldError = (dayIndex, intervalIndex, field) => {
    return openingHoursForm.errors[`opening_hours.${dayIndex}.intervals.${intervalIndex}.${field}`] ?? '';
};

const noteError = (dayIndex) => {
    return openingHoursForm.errors[`opening_hours.${dayIndex}.note`] ?? '';
};
</script>

<template>
    <AdminLayout>
        <form @submit.prevent="saveOpeningHours">
            <FormPage
                submit-label="Uložiť otváracie hodiny"
                :loading="openingHoursForm.processing"
            >
                <FormSection
                    v-for="(day, dayIndex) in openingHoursForm.opening_hours"
                    :key="day.day_of_week"
                    :title="dayLabel(day.day_of_week)"
                    columns="grid-cols-1"
                >
                    <div :class="day.is_closed ? 'bg-soft/50' : 'bg-white'">

                        <div class="mb-5 flex items-center gap-2">
                            <Checkbox
                                v-model="day.is_closed"
                                binary
                                :input-id="`closed_${day.day_of_week}`"
                            />

                            <label
                                :for="`closed_${day.day_of_week}`"
                                class="text-sm font-medium text-accent"
                            >
                                Zatvorené
                            </label>
                        </div>

                        <div
                            v-if="!day.is_closed"
                            class="space-y-5"
                        >
                            <div
                                v-for="(interval, intervalIndex) in day.intervals"
                                :key="intervalIndex"
                                class="space-y-3"
                            >
                                <div class="grid gap-4 md:grid-cols-2">
                                    <FormField
                                        label="Od"
                                        :for="`opens_at_${day.day_of_week}_${intervalIndex}`"
                                        :error="fieldError(dayIndex, intervalIndex, 'opens_at')"
                                    >
                                        <DatePicker
                                            :id="`opens_at_${day.day_of_week}_${intervalIndex}`"
                                            v-model="interval.opens_at"
                                            time-only
                                            hour-format="24"
                                            fluid
                                            show-icon
                                            icon-display="input"
                                            :invalid="Boolean(fieldError(dayIndex, intervalIndex, 'opens_at'))"
                                        />
                                    </FormField>

                                    <FormField
                                        label="Do"
                                        :for="`closes_at_${day.day_of_week}_${intervalIndex}`"
                                        :error="fieldError(dayIndex, intervalIndex, 'closes_at')"
                                    >
                                        <DatePicker
                                            :id="`closes_at_${day.day_of_week}_${intervalIndex}`"
                                            v-model="interval.closes_at"
                                            time-only
                                            hour-format="24"
                                            fluid
                                            show-icon
                                            icon-display="input"
                                            :invalid="Boolean(fieldError(dayIndex, intervalIndex, 'closes_at'))"
                                        />
                                    </FormField>
                                </div>

                                <div
                                    v-if="day.intervals.length > 1"
                                    class="flex justify-end"
                                >
                                    <Button
                                        type="button"
                                        label="Odstrániť interval"
                                        severity="danger"
                                        outlined
                                        size="small"
                                        @click="removeInterval(day, intervalIndex)"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="!day.is_closed"
                            class="mt-5"
                        >
                            <Button
                                type="button"
                                label="Pridať interval"
                                outlined
                                size="small"
                                @click="addInterval(day)"
                            />
                        </div>

                        <FormField
                            label="Poznámka"
                            :for="`note_${day.day_of_week}`"
                            :error="noteError(dayIndex)"
                            span="mt-5"
                        >
                            <InputText
                                :id="`note_${day.day_of_week}`"
                                v-model="day.note"
                                class="w-full"
                                placeholder="Napr. len na objednávku"
                                :invalid="Boolean(noteError(dayIndex))"
                            />
                        </FormField>
                    </div>
                </FormSection>
            </FormPage>
        </form>
    </AdminLayout>
</template>