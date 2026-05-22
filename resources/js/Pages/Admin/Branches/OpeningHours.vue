<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';
import InputText from 'primevue/inputtext';

const props = defineProps({
    branch: Object,
});

const dayNames = [
    { value: 1, label: 'Pondelok' },
    { value: 2, label: 'Utorok' },
    { value: 3, label: 'Streda' },
    { value: 4, label: 'Štvrtok' },
    { value: 5, label: 'Piatok' },
    { value: 6, label: 'Sobota' },
    { value: 7, label: 'Nedeľa' },
];

const createDefaultOpeningHours = () => dayNames.map((day) => {
    const existingDay = props.branch.opening_hours?.find((item) => item.day_of_week === day.value);

    if (existingDay) {
        return {
            day_of_week: existingDay.day_of_week,
            is_closed: Boolean(existingDay.is_closed),
            note: existingDay.note ?? '',
            sort_order: existingDay.sort_order ?? day.value,
            intervals: existingDay.intervals?.map((interval, index) => ({
                opens_at: interval.opens_at?.slice(0, 5) ?? '08:00',
                closes_at: interval.closes_at?.slice(0, 5) ?? '16:00',
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
                opens_at: '08:00',
                closes_at: '16:00',
                sort_order: 0,
            },
        ],
    };
});

const openingHoursForm = useForm({
    opening_hours: createDefaultOpeningHours(),
});

const toast = useToast();

const saveOpeningHours = () => {
    openingHoursForm.put(route('branches.opening-hours.update', props.branch.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Úspech', detail: 'Otváracie hodiny boli úspešne uložené.', life: 3000 });
        },
        onError: () => {
            toast.add({ severity: 'error', summary: 'Chyba', detail: 'Nepodarilo sa uložiť otváracie hodiny.', life: 3000 });
        },
    });
};

const addInterval = (day) => {
    day.is_closed = false;

    day.intervals.push({
        opens_at: '08:00',
        closes_at: '16:00',
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

const dayLabel = (dayOfWeek) => {
    return dayNames.find((day) => day.value === dayOfWeek)?.label ?? dayOfWeek;
};

const applyMondayToAllDays = () => {
    const monday = openingHoursForm.opening_hours.find((day) => day.day_of_week === 1);

    if (!monday) {
        return;
    }

    openingHoursForm.opening_hours = openingHoursForm.opening_hours.map((day) => {
        return {
            ...day,
            is_closed: monday.is_closed,
            note: monday.note,
            intervals: monday.intervals.map((interval, index) => ({
                opens_at: interval.opens_at,
                closes_at: interval.closes_at,
                sort_order: index,
            })),
        };
    });
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
            intervals: monday.intervals.map((interval, index) => ({
                opens_at: interval.opens_at,
                closes_at: interval.closes_at,
                sort_order: index,
            })),
        };
    });
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <h1 class="mb-2 text-2xl font-semibold text-slate-900">
                    Otváracie hodiny
                </h1>

                <p class="text-sm text-slate-500">
                    Nastavte týždenný rozpis pobočky. Vyplňte pondelok a môžete ho použiť aj pre ostatné dni.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    label="Použiť pondelok na pracovné dni"
                    icon="pi pi-copy"
                    severity="secondary"
                    outlined
                    @click="setWeekdaysFromMonday"
                />

                <Button
                    type="button"
                    label="Použiť pondelok na všetky dni"
                    icon="pi pi-copy"
                    severity="secondary"
                    outlined
                    @click="applyMondayToAllDays"
                />
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="space-y-4" @submit.prevent="saveOpeningHours">
                <div
                    v-for="day in openingHoursForm.opening_hours"
                    :key="day.day_of_week"
                    class="rounded-2xl border border-slate-200 p-4"
                    :class="day.is_closed ? 'bg-slate-50' : 'bg-white'"
                >
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-900">
                                {{ dayLabel(day.day_of_week) }}
                            </h3>

                            <p class="text-xs text-slate-500">
                                {{ day.is_closed ? 'Tento deň je zatvorený.' : 'Nastavte časové intervaly.' }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox
                                v-model="day.is_closed"
                                binary
                                :inputId="`closed_${day.day_of_week}`"
                            />

                            <label
                                :for="`closed_${day.day_of_week}`"
                                class="text-sm font-medium text-slate-700"
                            >
                                Zatvorené
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="!day.is_closed"
                        class="space-y-3"
                    >
                        <div
                            v-for="(interval, intervalIndex) in day.intervals"
                            :key="intervalIndex"
                            class="grid items-end gap-4 md:grid-cols-[1fr_1fr_auto]"
                        >
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Od
                                </label>

                                <input
                                    v-model="interval.opens_at"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
                                >
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Do
                                </label>

                                <input
                                    v-model="interval.closes_at"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
                                >
                            </div>

                            <Button
                                v-if="day.intervals.length > 1"
                                type="button"
                                label="Odstrániť"
                                severity="danger"
                                outlined
                                @click="removeInterval(day, intervalIndex)"
                            />
                        </div>

                        <Button
                            type="button"
                            label="Pridať interval"
                            icon="pi pi-plus"
                            outlined
                            size="small"
                            @click="addInterval(day)"
                        />
                    </div>

                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            Poznámka
                        </label>

                        <InputText
                            v-model="day.note"
                            class="w-full"
                            placeholder="Napr. len na objednávku"
                        />
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 pt-5">
                    <Button
                        type="submit"
                        label="Uložiť otváracie hodiny"
                        icon="pi pi-save"
                        :loading="openingHoursForm.processing"
                    />
                </div>
            </form>
        </section>
    </AdminLayout>
</template>