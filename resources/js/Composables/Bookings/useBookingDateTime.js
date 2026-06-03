export function useBookingDateTime() {
    const formatDate = (value) => {
        if (!value) {
            return null;
        }

        return String(value).slice(0, 10);
    };

    const formatTime = (value) => {
        if (!value) {
            return null;
        }

        return String(value).slice(0, 5);
    };

    const formatCalendarTime = (value) => {
        const time = formatTime(value);

        if (!time) {
            return null;
        }

        return `${time}:00`;
    };

    const getDateFromDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const getTimeFromDate = (date) => {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${hours}:${minutes}`;
    };

    const getDateTime = (date, time) => {
        return `${date}T${time}:00`;
    };

    const toLocalDateTimeString = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:00`;
    };

    return {
        formatDate,
        formatTime,
        formatCalendarTime,
        getDateFromDate,
        getTimeFromDate,
        getDateTime,
        toLocalDateTimeString,
    };
}
