export const RECURRENCE_SCOPES = Object.freeze({
    occurrence: 'occurrence',
    fromDate: 'from_date',
    series: 'series',
});

export const normalizeRecurrenceForCompare = (recurrence = null) => {
    if (!recurrence) {
        return null;
    }

    return {
        frequency: recurrence.frequency ?? null,
        interval: Math.max(1, Number(recurrence.interval ?? 1)),
        weekdays: [...(recurrence.weekdays ?? [])].sort(),
        ends: {
            type: recurrence.ends?.type ?? 'never',
            count: recurrence.ends?.count ?? null,
            until: recurrence.ends?.until ?? null,
        },
    };
};

export const hasRecurringRuleChanged = (previousRecurrence = null, nextRecurrence = null) => {
    return JSON.stringify(normalizeRecurrenceForCompare(previousRecurrence))
        !== JSON.stringify(normalizeRecurrenceForCompare(nextRecurrence));
};

export const isRecurrenceRemoved = (previousRecurrence = null, nextRecurrence = null) => {
    return Boolean(previousRecurrence) && !nextRecurrence;
};

export const isRecurringEntity = (entity = null) => {
    if (!entity) {
        return false;
    }

    return Boolean(
        entity.series_uuid
        || entity.recurrence
        || entity.recurrence_rule
        || entity.is_recurring
        || entity.target_is_recurring
        || entity.repeats,
    );
};

export const getRecurringOccurrenceDate = (entity = null) => {
    if (!entity) {
        return null;
    }

    return entity.occurrence_original_date
        ?? entity.occurrence_date
        ?? entity.date
        ?? entity.starts_at
        ?? null;
};

export const inferRecurringScope = ({
    entity = null,
    occurrenceDate = null,
    requestedScope = null,
    defaultScope = RECURRENCE_SCOPES.series,
    recurrenceChanged = false,
    recurrenceRemoved = false,
}) => {
    if (!isRecurringEntity(entity)) {
        return null;
    }

    let resolvedScope = requestedScope ?? defaultScope;

    if (recurrenceChanged && resolvedScope === RECURRENCE_SCOPES.occurrence) {
        resolvedScope = RECURRENCE_SCOPES.series;
    }

    if (recurrenceRemoved) {
        resolvedScope = RECURRENCE_SCOPES.fromDate;
    }

    if (!resolvedScope && occurrenceDate) {
        resolvedScope = RECURRENCE_SCOPES.occurrence;
    }

    return resolvedScope;
};
