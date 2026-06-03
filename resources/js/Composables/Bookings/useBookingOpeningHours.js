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
            return startTime >= formatTime(interval.opens_at)
                && endTime <= formatTime(interval.closes_at);
        });
    };

    const isSelectionInsideOpeningHours = (selectInfo) => {
        return isDateRangeInsideOpeningHours(selectInfo.start, selectInfo.end);
    };

    const isEventAllowed = (dropInfo, draggedEvent) => {
        const type = draggedEvent?.extendedProps?.type;

        if (type === 'booking') {
            return true;
        }

        if (type === 'capacity_window') {
            return true;
        }

        if (type === 'rule') {
            return isDateRangeInsideOpeningHours(dropInfo.start, dropInfo.end);
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
                    startTime: formatTime(interval.opens_at),
                    endTime: formatTime(interval.closes_at),
                }));
            });
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
            .map((interval) => formatTime(interval.opens_at))
            .filter(Boolean)
            .sort()[0];

        const max = intervals
            .map((interval) => formatTime(interval.closes_at))
            .filter(Boolean)
            .sort()
            .reverse()[0];

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
