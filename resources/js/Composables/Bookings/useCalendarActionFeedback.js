import { useToast } from 'primevue/usetoast';

const recentToastTimestamps = new Map();

const SCOPE_KEY = {
    occurrence: 'occurrence',
    this: 'occurrence',
    from_date: 'from_date',
    this_and_following: 'from_date',
    series: 'series',
    all: 'series',
};

const pickFirstValidationMessage = (errors = {}) => {
    const values = Object.values(errors ?? {});

    if (!values.length) {
        return null;
    }

    const first = values[0];

    if (Array.isArray(first)) {
        return first[0] ?? null;
    }

    return first ?? null;
};

const normalizeCount = (value) => {
    const count = Number(value);

    return Number.isFinite(count) && count > 0 ? Math.round(count) : null;
};

const withAffectedCount = (message, affectedCount) => {
    const count = normalizeCount(affectedCount);

    if (!count) {
        return message;
    }

    return `${message} (${count})`;
};

export function resolveCalendarErrorMessage(error, fallbackMessage = 'Akciu sa nepodarilo dokončiť. Skúste to znova.') {
    if (!error) {
        return fallbackMessage;
    }

    const responseMessage = error?.response?.data?.message;

    if (responseMessage) {
        return responseMessage;
    }

    const responseErrors = error?.response?.data?.errors;
    const responseValidationMessage = pickFirstValidationMessage(responseErrors);

    if (responseValidationMessage) {
        return responseValidationMessage;
    }

    const validationMessage = pickFirstValidationMessage(error);

    if (validationMessage) {
        return validationMessage;
    }

    const conflictCode = error?.response?.data?.error_code;

    if (conflictCode === 'staff_conflict') {
        return 'Vybraný pracovník už má v tomto čase inú rezerváciu.';
    }

    if (conflictCode === 'capacity_conflict') {
        return 'Kapacitu nie je možné znížiť pod počet prihlásených účastníkov.';
    }

    if (conflictCode === 'recurring_conflict') {
        return 'Tento presun by vytvoril konflikt v opakovaní.';
    }

    if (error?.response?.status === 403) {
        return 'Nemáte oprávnenie vykonať túto akciu.';
    }

    if (error?.response?.status === 404) {
        return 'Požadovaný termín už neexistuje.';
    }

    if (error?.response?.status === 409) {
        return fallbackMessage;
    }

    if (error?.message === 'Network Error') {
        return 'Server nie je dostupný. Skúste to znova.';
    }

    return fallbackMessage;
}

export function scopeSuccessMessage({ action, scope, affectedCount }) {
    const normalizedScope = SCOPE_KEY[scope] ?? 'occurrence';

    if (action === 'reschedule') {
        if (normalizedScope === 'series') {
            return withAffectedCount('Všetky termíny v sérii boli presunuté.', affectedCount);
        }

        if (normalizedScope === 'from_date') {
            return withAffectedCount('Tento a nasledujúce termíny boli presunuté.', affectedCount);
        }

        return 'Termín bol presunutý.';
    }

    if (action === 'update') {
        if (normalizedScope === 'series') {
            return withAffectedCount('Všetky termíny v sérii boli upravené.', affectedCount);
        }

        if (normalizedScope === 'from_date') {
            return withAffectedCount('Tento a nasledujúce termíny boli upravené.', affectedCount);
        }

        return 'Termín bol upravený.';
    }

    if (action === 'delete') {
        if (normalizedScope === 'series') {
            return withAffectedCount('Celá séria termínov bola zrušená.', affectedCount);
        }

        if (normalizedScope === 'from_date') {
            return withAffectedCount('Tento a nasledujúce termíny boli zrušené.', affectedCount);
        }

        return 'Termín bol zrušený.';
    }

    return 'Hotovo.';
}

export function useCalendarActionFeedback() {
    const toast = useToast();

    const addToast = ({ severity, summary, detail, life = 3000, dedupeKey = null }) => {
        if (!detail) {
            return;
        }

        const key = dedupeKey ?? `${severity}:${detail}`;
        const now = Date.now();
        const lastTimestamp = recentToastTimestamps.get(key) ?? 0;

        if (now - lastTimestamp < 1200) {
            return;
        }

        recentToastTimestamps.set(key, now);

        toast.add({
            severity,
            summary,
            detail,
            life,
        });
    };

    const success = (detail, options = {}) => {
        addToast({
            severity: 'success',
            summary: 'Hotovo',
            detail,
            life: 3000,
            dedupeKey: options.dedupeKey,
        });
    };

    const error = (errOrErrors, fallbackMessage, options = {}) => {
        addToast({
            severity: 'error',
            summary: 'Chyba',
            detail: resolveCalendarErrorMessage(errOrErrors, fallbackMessage),
            life: 7000,
            dedupeKey: options.dedupeKey,
        });
    };

    const warn = (detail, options = {}) => {
        addToast({
            severity: 'warn',
            summary: 'Upozornenie',
            detail,
            life: 5000,
            dedupeKey: options.dedupeKey,
        });
    };

    const info = (detail, options = {}) => {
        addToast({
            severity: 'info',
            summary: 'Info',
            detail,
            life: 3000,
            dedupeKey: options.dedupeKey,
        });
    };

    return {
        success,
        error,
        warn,
        info,
    };
}
