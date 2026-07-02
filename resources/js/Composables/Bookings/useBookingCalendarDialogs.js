import { ref } from 'vue';

export function useBookingCalendarDialogs({ dateTime, isSelectionInsideOpeningHours }) {
    const {
        getDateFromDate,
        getTimeFromDate,
    } = dateTime;

    const createBookingDialogVisible = ref(false);
    const bookingDialogVisible = ref(false);
    const availabilityRuleDialogVisible = ref(false);
    const groupEventDialogVisible = ref(false);
    const groupEventOccurrenceDialogVisible = ref(false);
    const deleteRuleDialogVisible = ref(false);
    const ruleRescheduleScopeDialogVisible = ref(false);
    const bookingRescheduleScopeDialogVisible = ref(false);
    const capacityWindowRescheduleScopeDialogVisible = ref(false);

    const selectedRuleIndex = ref(null);
    const selectedBooking = ref(null);
    const selectedCapacityWindow = ref(null);
    const selectedGroupEvent = ref(null);
    const selectedRuleOccurrence = ref(null);
    const pendingCalendarSelection = ref(null);
    const createBookingPrefill = ref(null);
    const suppressEventClickUntil = ref(0);
    const skipNextEventClick = ref(false);

    const suppressEventClicksFor = (milliseconds = 700) => {
        suppressEventClickUntil.value = Date.now() + milliseconds;
    };

    const isEventClickSuppressed = () => {
        return skipNextEventClick.value || Date.now() < suppressEventClickUntil.value;
    };

    const suppressNextEventClick = () => {
        skipNextEventClick.value = true;
    };

    const consumeSuppressedEventClick = () => {
        if (!skipNextEventClick.value) {
            return false;
        }

        skipNextEventClick.value = false;

        return true;
    };

    const getDatePart = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return getDateFromDate(value);
        }

        return String(value).slice(0, 10);
    };

    const getTimePart = (value) => {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return getTimeFromDate(value);
        }

        const stringValue = String(value).replace('T', ' ');

        if (stringValue.includes(' ')) {
            return stringValue.slice(11, 16);
        }

        return stringValue.slice(0, 5);
    };

    const getSelectionFromDateClick = (clickInfo) => {
        const start = clickInfo.date;
        const end = new Date(start);

        end.setMinutes(end.getMinutes() + 30);

        return {
            start,
            end,
            startStr: start.toISOString(),
            endStr: end.toISOString(),
            allDay: false,
        };
    };

    const getSelectionFromCreateChoiceData = (data) => {
        const date = data.date
            ?? getDatePart(data.starts_at)
            ?? getDatePart(data.ends_at);

        if (!date) {
            return null;
        }

        const startsAt = getTimePart(data.starts_at) ?? '09:00';
        let endsAt = getTimePart(data.ends_at);

        if (!endsAt) {
            const fallbackEnd = new Date(`${date}T${startsAt}:00`);
            fallbackEnd.setMinutes(fallbackEnd.getMinutes() + 30);
            endsAt = getTimeFromDate(fallbackEnd);
        }

        const start = new Date(`${date}T${startsAt}:00`);
        let end = new Date(`${date}T${endsAt}:00`);

        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
            return null;
        }

        if (end <= start) {
            end = new Date(start);
            end.setMinutes(end.getMinutes() + 30);
            endsAt = getTimeFromDate(end);
        }

        return {
            start,
            end,
            date,
            starts_at: startsAt,
            ends_at: endsAt,
        };
    };

    const openCreateChoiceDialog = (selectionInfo) => {
        if (!isSelectionInsideOpeningHours(selectionInfo)) {
            return;
        }

        createBookingPrefill.value = null;
        pendingCalendarSelection.value = selectionInfo;
        createBookingDialogVisible.value = true;
    };

    const closeCreateChoiceDialog = () => {
        pendingCalendarSelection.value = null;
        createBookingPrefill.value = null;
        createBookingDialogVisible.value = false;
    };

    const closeCreateBookingDialog = () => {
        pendingCalendarSelection.value = null;
        createBookingPrefill.value = null;
        createBookingDialogVisible.value = false;
    };

    const getDefaultSelectionForCreateChoice = () => {
        const now = new Date();
        const minutes = now.getMinutes();

        if (minutes > 0 && minutes <= 15) {
            now.setMinutes(15);
        } else if (minutes > 15 && minutes <= 30) {
            now.setMinutes(30);
        } else if (minutes > 30 && minutes <= 45) {
            now.setMinutes(45);
        } else if (minutes > 45) {
            now.setHours(now.getHours() + 1);
            now.setMinutes(0);
        }

        now.setSeconds(0, 0);

        const end = new Date(now);
        end.setMinutes(end.getMinutes() + 30);

        return {
            start: now,
            end,
            date: getDateFromDate(now),
            starts_at: getTimeFromDate(now),
            ends_at: getTimeFromDate(end),
            allDay: false,
        };
    };

    const openCreateChoiceFromButton = () => {
        createBookingPrefill.value = null;
        pendingCalendarSelection.value = getDefaultSelectionForCreateChoice();
        createBookingDialogVisible.value = true;
    };

    const openCreateBookingWithPrefill = (prefill = {}) => {
        const date = prefill.date
            ?? getDatePart(prefill.starts_at)
            ?? getDatePart(prefill.ends_at)
            ?? getDateFromDate(new Date());

        const startsAt = getTimePart(prefill.starts_at) ?? '09:00';
        const endsAt = getTimePart(prefill.ends_at) ?? '09:30';

        createBookingPrefill.value = {
            ...prefill,
            date,
            starts_at: prefill.starts_at ?? `${date} ${startsAt}:00`,
            ends_at: prefill.ends_at ?? `${date} ${endsAt}:00`,
        };

        pendingCalendarSelection.value = {
            start: new Date(`${date}T${startsAt}:00`),
            end: new Date(`${date}T${endsAt}:00`),
            date,
            starts_at: startsAt,
            ends_at: endsAt,
            allDay: false,
        };

        createBookingDialogVisible.value = true;
    };

    const closeRuleDialog = () => {
        selectedRuleIndex.value = null;
        selectedRuleOccurrence.value = null;
        availabilityRuleDialogVisible.value = false;
    };

    const closeGroupEventDialog = () => {
        selectedGroupEvent.value = null;
        groupEventDialogVisible.value = false;
    };

    const openDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = true;
    };

    const closeDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = false;
    };

    return {
        createBookingDialogVisible,
        bookingDialogVisible,
        availabilityRuleDialogVisible,
        groupEventDialogVisible,
        groupEventOccurrenceDialogVisible,
        deleteRuleDialogVisible,
        ruleRescheduleScopeDialogVisible,
        bookingRescheduleScopeDialogVisible,
        capacityWindowRescheduleScopeDialogVisible,

        selectedRuleIndex,
        selectedBooking,
        selectedCapacityWindow,
        selectedGroupEvent,
        selectedRuleOccurrence,
        pendingCalendarSelection,
        createBookingPrefill,
        suppressEventClickUntil,
        skipNextEventClick,

        getSelectionFromCreateChoiceData,
        getSelectionFromDateClick,
        consumeSuppressedEventClick,
        isEventClickSuppressed,
        openCreateChoiceDialog,
        closeCreateChoiceDialog,
        closeCreateBookingDialog,
        openCreateChoiceFromButton,
        openCreateBookingWithPrefill,
        suppressNextEventClick,
        suppressEventClicksFor,
        closeRuleDialog,
        closeGroupEventDialog,
        openDeleteRuleDialog,
        closeDeleteRuleDialog,

    };
}