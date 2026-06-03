import { ref } from 'vue';

export function useBookingCalendarDialogs({ dateTime, isSelectionInsideOpeningHours }) {
    const {
        getDateFromDate,
        getTimeFromDate,
    } = dateTime;

    const createChoiceDialogVisible = ref(false);
    const createBookingDialogVisible = ref(false);
    const bookingDialogVisible = ref(false);
    const availabilityRuleDialogVisible = ref(false);
    const groupEventDialogVisible = ref(false);
    const groupEventOccurrenceDialogVisible = ref(false);
    const deleteRuleDialogVisible = ref(false);

    const selectedRuleIndex = ref(null);
    const selectedBooking = ref(null);
    const selectedCapacityWindow = ref(null);
    const selectedRuleOccurrence = ref(null);
    const pendingCalendarSelection = ref(null);

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
        return {
            start: new Date(`${data.date}T${data.starts_at}:00`),
            end: new Date(`${data.date}T${data.ends_at}:00`),
            date: data.date,
            starts_at: data.starts_at,
            ends_at: data.ends_at,
        };
    };

    const openCreateChoiceDialog = (selectionInfo) => {
        if (!isSelectionInsideOpeningHours(selectionInfo)) {
            return;
        }

        pendingCalendarSelection.value = selectionInfo;
        createChoiceDialogVisible.value = true;
    };

    const closeCreateChoiceDialog = () => {
        pendingCalendarSelection.value = null;
        createChoiceDialogVisible.value = false;
    };

    const closeCreateBookingDialog = () => {
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
        pendingCalendarSelection.value = getDefaultSelectionForCreateChoice();
        createChoiceDialogVisible.value = true;
    };

    const closeRuleDialog = () => {
        selectedRuleIndex.value = null;
        selectedRuleOccurrence.value = null;
        availabilityRuleDialogVisible.value = false;
        groupEventDialogVisible.value = false;
    };

    const openDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = true;
    };

    const closeDeleteRuleDialog = () => {
        deleteRuleDialogVisible.value = false;
    };

    return {
        createChoiceDialogVisible,
        createBookingDialogVisible,
        bookingDialogVisible,
        availabilityRuleDialogVisible,
        groupEventDialogVisible,
        groupEventOccurrenceDialogVisible,
        deleteRuleDialogVisible,

        selectedRuleIndex,
        selectedBooking,
        selectedCapacityWindow,
        selectedRuleOccurrence,
        pendingCalendarSelection,

        getSelectionFromCreateChoiceData,
        getSelectionFromDateClick,
        openCreateChoiceDialog,
        closeCreateChoiceDialog,
        closeCreateBookingDialog,
        openCreateChoiceFromButton,
        closeRuleDialog,
        openDeleteRuleDialog,
        closeDeleteRuleDialog,
    };
}
