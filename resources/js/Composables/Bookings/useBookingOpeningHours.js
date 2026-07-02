export function useBookingOpeningHours({ props, dateTime }) {
    const {
        formatCalendarTime,
        formatTime,
        getDateFromDate,
        getTimeFromDate,
    } = dateTime;

    const getOpeningHours = () => {
        return props.branch.opening_hours ?? props.branch.openingHours ?? [];
    };

    const getOpeningDayValue = (openingDay) => {
        return Number(openingDay.day_of_week);
    };

    const getFullCalendarDayValue = (openingDay) => {
        const day = getOpeningDayValue(openingDay);

        return day === 7 ? 0 : day;
    };

    const getDatabaseDayFromDate = (date) => {
        const day = date.getDay();

        return day === 0 ? 7 : day;
    };

    const getOpeningIntervals = (openingDay) => {
        if (!openingDay || openingDay.is_closed) {
            return [];
        }

        return openingDay.intervals ?? [];
    };

    const getIntervalStartTime = (interval) => {
        return formatTime(interval?.opens_at ?? interval?.starts_at ?? null);
    };

    const getIntervalEndTime = (interval) => {
        return formatTime(interval?.closes_at ?? interval?.ends_at ?? null);
    };

    const isDateDisabled = (date) => {
        if (!date) {
            return false;
        }

        const dateOnly = getDateFromDate(date);

        return (props.disabledDays ?? []).some((disabledDay) => {
            return String(disabledDay.date).slice(0, 10) === dateOnly;
        });
    };

    const getDayOpeningHours = (date) => {
        const databaseDay = getDatabaseDayFromDate(date);

        return getOpeningHours().find((openingDay) => {
            return getOpeningDayValue(openingDay) === databaseDay;
        }) ?? null;
    };

    const getAllOpeningIntervals = () => {
        return getOpeningHours()
            .flatMap((openingDay) => getOpeningIntervals(openingDay));
    };

    const isDateRangeInsideOpeningHours = (start, end) => {
        if (!start || !end) {
            return false;
        }

        if (isDateDisabled(start) || isDateDisabled(end)) {
            return false;
        }

        if (!(getOpeningHours() ?? []).length) {
            return true;
        }

        if (getDateFromDate(start) !== getDateFromDate(end)) {
            return false;
        }

        const openingDay = getDayOpeningHours(start);
        const intervals = getOpeningIntervals(openingDay);

        if (!intervals.length) {
            return false;
        }

        const startTime = getTimeFromDate(start);
        const endTime = getTimeFromDate(end);

        return intervals.some((interval) => {
            const intervalStart = getIntervalStartTime(interval);
            const intervalEnd = getIntervalEndTime(interval);

            if (!intervalStart || !intervalEnd) {
                return false;
            }

            return startTime >= intervalStart
                && endTime <= intervalEnd;
        });
    };

    const isSelectionInsideOpeningHours = (selectInfo) => {
        return isDateRangeInsideOpeningHours(selectInfo.start, selectInfo.end);
    };

    const isEventAllowed = (dropInfo, draggedEvent) => {
        const end = dropInfo.end ?? draggedEvent?.end ?? dropInfo.start;

        if (isDateDisabled(dropInfo.start) || isDateDisabled(end)) {
            return false;
        }

        if (!isDateRangeInsideOpeningHours(dropInfo.start, end)) {
            return false;
        }

        const type = draggedEvent?.extendedProps?.type;

        if (type === 'booking') {
            return true;
        }

        if (type === 'capacity_window') {
            return true;
        }

        if (type === 'appointment_request') {
            return true;
        }

        if (type === 'rule') {
            return true;
        }

        return false;
    };

    const getBusinessHours = () => {
        return getOpeningHours()
            .flatMap((openingDay) => {
                return getOpeningIntervals(openingDay).map((interval) => ({
                    daysOfWeek: [
                        getFullCalendarDayValue(openingDay),
                    ],
                    startTime: getIntervalStartTime(interval),
                    endTime: getIntervalEndTime(interval),
                }));
            })
            .filter((interval) => interval.startTime && interval.endTime);
    };

    const getBranchOpeningHoursForCalendar = () => {
        const intervals = getAllOpeningIntervals();

        if (!intervals.length) {
            return {
                min: '06:00:00',
                max: '20:00:00',
            };
        }

        const min = intervals
            .map((interval) => getIntervalStartTime(interval))
            .filter(Boolean)
            .sort()[0];

        const max = intervals
            .map((interval) => getIntervalEndTime(interval))
            .filter(Boolean)
            .sort()
            .reverse()[0];

        if (!min || !max) {
            return {
                min: '06:00:00',
                max: '20:00:00',
            };
        }

        return {
            min: formatCalendarTime(min),
            max: formatCalendarTime(max),
        };
    };

    return {
        getBusinessHours,
        getBranchOpeningHoursForCalendar,
        isDateRangeInsideOpeningHours,
        isEventAllowed,
        isSelectionInsideOpeningHours,
    };
}
