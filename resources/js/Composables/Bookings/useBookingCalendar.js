import { computed, ref } from 'vue';

import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';

import { useBookingActions } from './useBookingActions';
import { useBookingCalendarDialogs } from './useBookingCalendarDialogs';
import { useBookingCalendarEvents } from './useBookingCalendarEvents';
import { useBookingDateTime } from './useBookingDateTime';
import { useBookingOpeningHours } from './useBookingOpeningHours';
import { useBookingRules } from './useBookingRules';
import { useCapacityWindowActions } from './useCapacityWindowActions';

export function useBookingCalendar(props) {
    const showAvailabilityRules = ref(true);
    const showReservations = ref(true);

    const currentCalendarDate = ref(new Date().toISOString().slice(0, 10));
    const currentCalendarView = ref('timeGridWeek');

    const dateTime = useBookingDateTime();

    const openingHours = useBookingOpeningHours({
        props,
        dateTime,
    });

    const dialogs = useBookingCalendarDialogs({
        dateTime,
        isSelectionInsideOpeningHours: openingHours.isSelectionInsideOpeningHours,
    });

    const rules = useBookingRules({
        props,
        dateTime,
        dialogs,
        isDateRangeInsideOpeningHours: openingHours.isDateRangeInsideOpeningHours,
    });

    const bookingActions = useBookingActions({
        props,
        dateTime,
        dialogs,
    });

    const capacityWindowActions = useCapacityWindowActions({
        props,
        dateTime,
        dialogs,
        ruleForm: rules.ruleForm,
    });

    const events = useBookingCalendarEvents({
        props,
        showAvailabilityRules,
        showReservations,
        freeTimeRules: rules.freeTimeRules,
        getDateTime: dateTime.getDateTime,
        getRuleOccurrences: rules.getRuleOccurrences,
        getRuleTitle: rules.getRuleTitle,
    });

    const continueFromCreateChoice = (data) => {
        const selectionInfo = dialogs.getSelectionFromCreateChoiceData(data);

        if (!openingHours.isSelectionInsideOpeningHours(selectionInfo)) {
            return;
        }

        dialogs.pendingCalendarSelection.value = {
            ...selectionInfo,
            date: data.date,
            starts_at: data.starts_at,
            ends_at: data.ends_at,
        };

        dialogs.createChoiceDialogVisible.value = false;

        if (data.create_type === 'booking') {
            dialogs.createBookingDialogVisible.value = true;

            return;
        }

        if (data.create_type === 'rule') {
            rules.ruleForm.rules.push({
                ...rules.emptyRule(),
                date: data.date,
                starts_at: data.starts_at,
                ends_at: data.ends_at,
                slot_mode: 'free_bookable_time',
            });

            dialogs.selectedRuleIndex.value = rules.ruleForm.rules.length - 1;
            dialogs.selectedRuleOccurrence.value = {
                ruleIndex: dialogs.selectedRuleIndex.value,
                occurrenceDate: data.date,
                isRepeatedOccurrence: false,
            };

            dialogs.availabilityRuleDialogVisible.value = true;

            return;
        }

        if (data.create_type === 'group_event') {
            rules.ruleForm.rules.push({
                ...rules.emptyRule(),
                date: data.date,
                starts_at: data.starts_at,
                ends_at: data.ends_at,
                slot_mode: 'single_service_many_clients',
                bookable_places: 5,
            });

            dialogs.selectedRuleIndex.value = rules.ruleForm.rules.length - 1;
            dialogs.selectedRuleOccurrence.value = {
                ruleIndex: dialogs.selectedRuleIndex.value,
                occurrenceDate: data.date,
                isRepeatedOccurrence: false,
            };

            dialogs.groupEventDialogVisible.value = true;
        }
    };

    const openEventDialog = (clickInfo) => {
        const type = clickInfo.event.extendedProps.type;

        if (type === 'rule') {
            dialogs.selectedRuleIndex.value = clickInfo.event.extendedProps.ruleIndex;

            dialogs.selectedRuleOccurrence.value = {
                ruleIndex: clickInfo.event.extendedProps.ruleIndex,
                occurrenceDate: clickInfo.event.extendedProps.occurrenceDate,
                isRepeatedOccurrence: clickInfo.event.extendedProps.isRepeatedOccurrence,
            };

            const rule = rules.ruleForm.rules[dialogs.selectedRuleIndex.value];

            if (rule?.slot_mode === 'single_service_many_clients') {
                dialogs.groupEventDialogVisible.value = true;

                return;
            }

            dialogs.availabilityRuleDialogVisible.value = true;

            return;
        }

        if (type === 'capacity_window') {
            dialogs.selectedCapacityWindow.value = clickInfo.event.extendedProps.capacityWindow;
            dialogs.groupEventOccurrenceDialogVisible.value = true;

            return;
        }

        if (type === 'booking') {
            dialogs.selectedBooking.value = clickInfo.event.extendedProps.booking;
            dialogs.bookingDialogVisible.value = true;
        }
    };

    const setEventLayer = (mountInfo) => {
        const type = mountInfo.event.extendedProps.type;
        const harness = mountInfo.el.closest('.fc-timegrid-event-harness');

        if (!harness) {
            return;
        }

        if (type === 'booking') {
            harness.style.zIndex = '30';
            mountInfo.el.style.zIndex = '30';
            mountInfo.el.style.cursor = 'grab';

            return;
        }

        if (type === 'capacity_window') {
            harness.style.zIndex = '20';
            mountInfo.el.style.zIndex = '20';
            mountInfo.el.style.cursor = 'grab';

            return;
        }

        if (type === 'rule') {
            harness.style.zIndex = '1';
            harness.style.width = '22%';
            harness.style.right = '0';
            harness.style.left = 'auto';

            mountInfo.el.style.zIndex = '1';
            mountInfo.el.style.cursor = 'pointer';
        }
    };

    const handleEventDropOrResize = (changeInfo) => {
        const type = changeInfo.event.extendedProps.type;

        if (type === 'rule') {
            rules.updateRuleFromDrop(changeInfo);

            return;
        }

        if (type === 'booking') {
            bookingActions.rescheduleBookingByCalendarChange(changeInfo);

            return;
        }

        if (type === 'capacity_window') {
            capacityWindowActions.rescheduleCapacityWindowByCalendarChange(changeInfo);
        }
    };

    const rememberCalendarPosition = (dateInfo) => {
        currentCalendarDate.value = dateInfo.startStr.slice(0, 10);
        currentCalendarView.value = dateInfo.view.type;
    };

    const calendarOptions = computed(() => {
        const branchHours = openingHours.getBranchOpeningHoursForCalendar();

        return {
            plugins: [
                timeGridPlugin,
                interactionPlugin,
            ],

            initialView: currentCalendarView.value,
            initialDate: currentCalendarDate.value,
            firstDay: 1,

            allDaySlot: false,
            selectable: true,
            editable: true,
            droppable: true,
            eventResizableFromStart: true,

            slotMinTime: branchHours.min,
            slotMaxTime: branchHours.max,
            slotDuration: '00:30:00',
            snapDuration: '00:05:00',

            businessHours: openingHours.getBusinessHours(),
            selectConstraint: 'businessHours',
            selectAllow: openingHours.isSelectionInsideOpeningHours,
            eventAllow: openingHours.isEventAllowed,

            eventDidMount: setEventLayer,
            eventOrderStrict: true,
            eventOrder: (firstEvent, secondEvent) => {
                const order = {
                    rule: 1,
                    capacity_window: 2,
                    booking: 3,
                };

                const firstOrder = order[firstEvent.extendedProps.type] ?? 0;
                const secondOrder = order[secondEvent.extendedProps.type] ?? 0;

                return firstOrder - secondOrder;
            },

            height: 'auto',
            locale: 'sk',
            nowIndicator: true,

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay',
            },

            buttonText: {
                today: 'Dnes',
                week: 'Týždeň',
                day: 'Deň',
            },

            events: events.calendarEvents.value,

            datesSet: rememberCalendarPosition,

            select: dialogs.openCreateChoiceDialog,
            dateClick: (clickInfo) => {
                dialogs.openCreateChoiceDialog(dialogs.getSelectionFromDateClick(clickInfo));
            },
            eventClick: openEventDialog,
            eventDrop: handleEventDropOrResize,
            eventResize: handleEventDropOrResize,
            eventReceive: bookingActions.convertAppointmentRequest,
        };
    });

    return {
        showAvailabilityRules,
        showReservations,

        createChoiceDialogVisible: dialogs.createChoiceDialogVisible,

        createBookingDialogVisible: dialogs.createBookingDialogVisible,
        bookingDialogVisible: dialogs.bookingDialogVisible,

        availabilityRuleDialogVisible: dialogs.availabilityRuleDialogVisible,
        groupEventDialogVisible: dialogs.groupEventDialogVisible,
        groupEventOccurrenceDialogVisible: dialogs.groupEventOccurrenceDialogVisible,

        deleteRuleDialogVisible: dialogs.deleteRuleDialogVisible,

        selectedBooking: dialogs.selectedBooking,
        selectedCapacityWindow: dialogs.selectedCapacityWindow,
        selectedRuleOccurrence: dialogs.selectedRuleOccurrence,
        pendingCalendarSelection: dialogs.pendingCalendarSelection,

        ruleForm: rules.ruleForm,
        currentRule: rules.currentRule,

        repeatUnitOptions: rules.repeatUnitOptions,

        bookingNotes: bookingActions.bookingNotes,
        calendarOptions,

        getRuleTitle: rules.getRuleTitle,
        getRepeatLabel: rules.getRepeatLabel,
        availableSlotsForBooking: bookingActions.availableSlotsForBooking,

        openCreateChoiceFromButton: dialogs.openCreateChoiceFromButton,
        closeCreateBookingDialog: dialogs.closeCreateBookingDialog,

        closeCreateChoiceDialog: dialogs.closeCreateChoiceDialog,
        continueFromCreateChoice,

        closeRuleDialog: dialogs.closeRuleDialog,
        deleteCurrentRule: rules.deleteCurrentRule,
        saveRules: rules.saveRules,

        createAdminBooking: bookingActions.createAdminBooking,
        updateBooking: bookingActions.updateBooking,
        cancelBooking: bookingActions.cancelBooking,
        rescheduleBooking: bookingActions.rescheduleBooking,
        cancelCapacityWindow: capacityWindowActions.cancelCapacityWindow,
        rescheduleCapacityWindow: capacityWindowActions.rescheduleCapacityWindow,

        openDeleteRuleDialog: dialogs.openDeleteRuleDialog,
        closeDeleteRuleDialog: dialogs.closeDeleteRuleDialog,
        deleteCurrentRuleOccurrence: rules.deleteCurrentRuleOccurrence,
        deleteCurrentRuleFromNowOn: rules.deleteCurrentRuleFromNowOn,
        deleteCurrentRuleEverywhere: rules.deleteCurrentRuleEverywhere,

        deleteCapacityWindowOccurrence: capacityWindowActions.deleteCapacityWindowOccurrence,
        deleteCapacityWindowFromDate: capacityWindowActions.deleteCapacityWindowFromDate,
        deleteCapacityWindowSeries: capacityWindowActions.deleteCapacityWindowSeries,
        addPatientToCapacityWindow: capacityWindowActions.addPatientToCapacityWindow,

        openCapacityWindowRuleEditor: capacityWindowActions.openCapacityWindowRuleEditor,
    };
}