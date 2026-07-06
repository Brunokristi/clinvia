<script setup>
import { computed } from 'vue';

import EventDetailDialog from '@/Components/Booking/Common/EventDetailDialog.vue';
import EventOccurrenceActions from '@/Components/Booking/Common/EventOccurrenceActions.vue';

const props = defineProps({
    detailVisible: {
        type: Boolean,
        required: true,
    },
    booking: {
        type: Object,
        default: null,
    },
    seriesBookings: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'update:detailVisible',
    'edit-in-unified-form',
    'cancel-booking',
    'duplicate-booking',
]);

const parseDateTime = (value) => {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const normalized = String(value)
        .trim()
        .replace(' ', 'T')
        .replace(/Z$/, '')
        .replace(/([+-]\d{2}:?\d{2})$/, '')
        .slice(0, 19);

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatSlovakDate = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleDateString('sk-SK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatSlovakTime = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return parsed.toLocaleTimeString('sk-SK', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const bookingDateModel = computed(() => parseDateTime(props.booking?.starts_at));
const bookingStartsAtModel = computed(() => parseDateTime(props.booking?.starts_at));
const bookingEndsAtModel = computed(() => parseDateTime(props.booking?.ends_at));

const parseDateOnly = (value) => {
    if (!value) {
        return null;
    }

    const datePart = String(value).slice(0, 10);
    const parsed = new Date(`${datePart}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const getRecurrenceFrequency = (recurrence) => {
    const frequency = recurrence?.frequency ?? recurrence?.repeat_unit ?? recurrence?.unit;

    if (frequency === 'days' || frequency === 'daily') {
        return 'daily';
    }

    if (frequency === 'months' || frequency === 'monthly') {
        return 'monthly';
    }

    if (frequency === 'years' || frequency === 'yearly') {
        return 'yearly';
    }

    return 'weekly';
};

const addRecurrenceInterval = (date, frequency, interval) => {
    const next = new Date(date);

    if (frequency === 'daily') {
        next.setDate(next.getDate() + interval);

        return next;
    }

    if (frequency === 'monthly') {
        next.setMonth(next.getMonth() + interval);

        return next;
    }

    if (frequency === 'yearly') {
        next.setFullYear(next.getFullYear() + interval);

        return next;
    }

    next.setDate(next.getDate() + (7 * interval));

    return next;
};

const countOccurrencesBetween = (startDate, endDate, recurrence) => {
    if (!startDate || !endDate || endDate < startDate) {
        return null;
    }

    const frequency = getRecurrenceFrequency(recurrence);
    const interval = Math.max(1, Number(recurrence?.interval ?? recurrence?.repeat_every ?? 1));
    let cursor = new Date(startDate);
    let count = 0;

    while (cursor <= endDate && count < 2000) {
        count += 1;
        cursor = addRecurrenceInterval(cursor, frequency, interval);
    }

    return count;
};

const bookingOccurrenceDate = computed(() => {
    return props.booking?.occurrence_original_date
        ?? props.booking?.occurrence_date
        ?? String(props.booking?.starts_at ?? '').slice(0, 10)
        ?? null;
});

const seriesOccurrenceDates = computed(() => {
    if (!props.booking) {
        return [];
    }

    const selectedRootEventId = Number(props.booking?.root_event_id ?? props.booking?.id ?? 0);
    const selectedSeriesUuid = props.booking?.series_uuid ?? null;
    const occurrences = [];

    (props.seriesBookings ?? []).forEach((booking) => {
        const bookingRootEventId = Number(booking?.root_event_id ?? booking?.id ?? 0);
        const bookingSeriesUuid = booking?.series_uuid ?? null;

        const belongsToSameSeries = (selectedRootEventId > 0 && bookingRootEventId > 0 && bookingRootEventId === selectedRootEventId)
            || (selectedSeriesUuid && bookingSeriesUuid && bookingSeriesUuid === selectedSeriesUuid);

        if (!belongsToSameSeries) {
            return;
        }

        const dateOnly = String(
            booking?.occurrence_original_date
            ?? booking?.occurrence_date
            ?? booking?.starts_at
            ?? '',
        ).slice(0, 10);

        if (!dateOnly) {
            return;
        }

        occurrences.push(dateOnly);
    });

    return [...new Set(occurrences)].sort();
});

const deleteCountOccurrence = computed(() => 1);

const getFallbackSeriesEndDate = (fromDate) => {
    if (!fromDate) {
        return null;
    }

    const fallback = new Date(fromDate);
    fallback.setFullYear(fallback.getFullYear() + 2);

    return fallback;
};

const deleteCountSeries = computed(() => {
    if (seriesOccurrenceDates.value.length > 0) {
        return seriesOccurrenceDates.value.length;
    }

    if (!props.booking?.recurrence) {
        return 1;
    }

    const recurrence = props.booking.recurrence;

    if (recurrence?.ends?.type === 'after' && Number(recurrence?.ends?.count) > 0) {
        return Number(recurrence.ends.count);
    }

    const startDate = parseDateOnly(
        recurrence?.starts_on
        ?? recurrence?.start_date
        ?? props.booking?.series_starts_at
        ?? props.booking?.starts_at,
    );
    const endDate = parseDateOnly(recurrence?.ends?.until)
        ?? getFallbackSeriesEndDate(startDate);

    return countOccurrencesBetween(startDate, endDate, recurrence);
});

const deleteCountFromDate = computed(() => {
    if (seriesOccurrenceDates.value.length > 0) {
        const fromDate = String(bookingOccurrenceDate.value ?? '').slice(0, 10);

        if (!fromDate) {
            return seriesOccurrenceDates.value.length;
        }

        const count = seriesOccurrenceDates.value.filter((dateOnly) => dateOnly >= fromDate).length;

        return count > 0 ? count : null;
    }

    if (!props.booking?.recurrence) {
        return 1;
    }

    const recurrence = props.booking.recurrence;

    if (recurrence?.ends?.type === 'after' && Number(recurrence?.ends?.count) > 0) {
        const total = Number(recurrence.ends.count);
        const frequency = getRecurrenceFrequency(recurrence);
        const interval = Math.max(1, Number(recurrence?.interval ?? recurrence?.repeat_every ?? 1));
        const startDate = parseDateOnly(
            recurrence?.starts_on
            ?? recurrence?.start_date
            ?? props.booking?.series_starts_at
            ?? props.booking?.starts_at,
        );
        const fromDate = parseDateOnly(bookingOccurrenceDate.value);

        if (!startDate || !fromDate || fromDate < startDate) {
            return total;
        }

        let index = 1;
        let cursor = new Date(startDate);

        while (cursor < fromDate && index < 2000) {
            cursor = addRecurrenceInterval(cursor, frequency, interval);
            index += 1;
        }

        if (cursor > fromDate) {
            return null;
        }

        return Math.max(1, total - index + 1);
    }

    const fromDate = parseDateOnly(bookingOccurrenceDate.value);
    const endDate = parseDateOnly(recurrence?.ends?.until)
        ?? getFallbackSeriesEndDate(fromDate);

    return countOccurrencesBetween(fromDate, endDate, recurrence);
});

const bookingInfoItems = computed(() => {
    if (!props.booking) {
        return [];
    }

    const services = props.booking.services?.length
        ? props.booking.services
        : (props.booking.service ? [props.booking.service] : []);
    const contactItems = [
        props.booking.patient_phone,
        props.booking.patient_email,
    ].filter(Boolean);

    return [
        {
            key: 'date',
            icon: 'pi pi-calendar',
            value: formatSlovakDate(bookingDateModel.value),
        },
        {
            key: 'duration',
            icon: 'pi pi-clock',
            value: bookingStartsAtModel.value && bookingEndsAtModel.value
                ? `${formatSlovakTime(bookingStartsAtModel.value)} – ${formatSlovakTime(bookingEndsAtModel.value)}`
                : '—',
        },
        {
            key: 'services',
            icon: 'pi pi-briefcase',
            value: services.length ? services : '—',
            type: 'services',
        },
        {
            key: 'patient',
            icon: 'pi pi-user',
            value: props.booking.patient_name || '—',
        },
        {
            key: 'contact',
            icon: 'pi pi-phone',
            value: contactItems.length ? contactItems : '—',
            type: 'contact',
        },
        {
            key: 'repetition',
            icon: 'pi pi-refresh',
            value: props.booking.recurrence ? 'Opakuje sa' : 'Neopakuje sa',
        },
    ];
});

const closeDetailDialog = () => {
    emit('update:detailVisible', false);
};

const openUnifiedEditor = () => {
    if (!props.booking) {
        return;
    }

    emit('edit-in-unified-form', props.booking);
};

const cancelBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
    });
};

const deleteOccurrence = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
        delete_scope: 'occurrence',
        date: bookingOccurrenceDate.value,
    });
};

const deleteFromNowOn = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
        delete_scope: 'from_date',
        date: bookingOccurrenceDate.value,
    });
};

const deleteAll = () => {
    if (!props.booking) {
        return;
    }

    emit('cancel-booking', props.booking, {
        notify_patient: true,
        delete_scope: 'series',
        date: bookingOccurrenceDate.value,
    });
};

const duplicateBooking = () => {
    if (!props.booking) {
        return;
    }

    emit('duplicate-booking', props.booking);
};
</script>

<template>
    <EventDetailDialog
        :visible="detailVisible"
        title="Rezervácia"
        width="max-w-3xl"
        :date="bookingDateModel"
        :starts-at="bookingStartsAtModel"
        :ends-at="bookingEndsAtModel"
        show-delete
        :delete-disabled="!booking"
        :is-repeatable="Boolean(booking?.series_uuid || booking?.recurrence)"
        :occurrence-date="bookingOccurrenceDate"
        :delete-count-occurrence="deleteCountOccurrence"
        :delete-count-from-date="deleteCountFromDate"
        :delete-count-series="deleteCountSeries"
        :show-duplicate="false"
        :show-date-time-fields="false"
        scope-mode="delete"
        scope-subject-label="rezervácia"
        @update:visible="emit('update:detailVisible', $event)"
        @close="closeDetailDialog"
        @delete-occurrence="deleteOccurrence"
        @delete-from-now-on="deleteFromNowOn"
        @delete-all="deleteAll"
        @duplicate="duplicateBooking"
    >
        <template #footer-start>
            <EventOccurrenceActions v-if="booking" @duplicate="duplicateBooking" @edit="openUnifiedEditor" />
        </template>

        <div v-if="booking" class="space-y-4">
            <div class="space-y-4">
                <div
                    v-for="item in bookingInfoItems"
                    :key="item.key"
                    class="grid grid-cols-[2.5rem_1fr] items-stretch gap-3"
                >
                    <div class="flex h-full min-h-10 items-center justify-center rounded-md bg-soft text-accent">
                        <i :class="item.icon" class="text-base" />
                    </div>

                    <div class="flex min-w-0 items-center">
                        <div
                            v-if="item.type === 'services' && Array.isArray(item.value)"
                            class="w-full space-y-1"
                        >
                            <div
                                v-for="service in item.value"
                                :key="service.id"
                                class="flex items-center rounded-md bg-white text-sm font-medium text-dark"
                            >
                                <span class="min-w-0 flex-1 truncate">
                                    {{ service.name }}
                                </span>
                            </div>
                        </div>

                        <div
                            v-else-if="item.type === 'contact' && Array.isArray(item.value)"
                            class="w-full space-y-1"
                        >
                            <p
                                v-for="contact in item.value"
                                :key="contact"
                                class="break-words text-sm font-medium text-dark"
                            >
                                {{ contact }}
                            </p>
                        </div>

                        <p v-else class="break-words text-sm font-medium text-dark">
                            {{ item.value }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-600">
            Rezerváciu sa nepodarilo načítať.
        </div>
    </EventDetailDialog>
</template>
