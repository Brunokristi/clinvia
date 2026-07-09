import { ref, unref } from 'vue';

const emptyImpact = () => ({
    occurrence: {
        count: null,
        countLabel: null,
        isCountCapped: false,
        participantCount: null,
        participantCountLabel: null,
        isParticipantCountCapped: false,
        message: null,
        raw: null,
    },
    from_date: {
        count: null,
        countLabel: null,
        isCountCapped: false,
        participantCount: null,
        participantCountLabel: null,
        isParticipantCountCapped: false,
        message: null,
        raw: null,
    },
    series: {
        count: null,
        countLabel: null,
        isCountCapped: false,
        participantCount: null,
        participantCountLabel: null,
        isParticipantCountCapped: false,
        message: null,
        raw: null,
    },
});

const normalizeScopeKey = (scope) => {
    if (scope === 'this' || scope === 'occurrence') {
        return 'occurrence';
    }

    if (scope === 'this_and_following' || scope === 'from_date') {
        return 'from_date';
    }

    return 'series';
};

export function useRecurringImpactPreview(branchId) {
    const impactPreview = ref(emptyImpact());
    const loading = ref(false);

    const clearImpactPreview = () => {
        impactPreview.value = emptyImpact();
    };

    const buildSelectedOccurrence = (occurrence = {}) => {
        const eventId = Number(
            occurrence?.event_id
            ?? occurrence?.id
            ?? occurrence?.rule_id
            ?? occurrence?.capacity_window_id
            ?? occurrence?.booking_id
            ?? 0,
        );

        return {
            event_id: eventId,
            root_event_id: occurrence?.root_event_id ?? occurrence?.logical_root_event_id ?? null,
            occurrence_starts_at: occurrence?.occurrence_starts_at ?? occurrence?.starts_at ?? null,
            occurrence_ends_at: occurrence?.occurrence_ends_at ?? occurrence?.ends_at ?? null,
            occurrence_original_starts_at: occurrence?.occurrence_original_starts_at
                ?? (occurrence?.occurrence_original_date
                    ? `${String(occurrence.occurrence_original_date).slice(0, 10)}T${String(occurrence?.starts_at ?? '').slice(11, 19) || '00:00:00'}`
                    : null),
            starts_at: occurrence?.starts_at ?? null,
            ends_at: occurrence?.ends_at ?? null,
            display_key: occurrence?.display_key ?? null,
        };
    };

    const requestScopePreview = async ({
        action,
        scope,
        selectedOccurrence,
        changes = {},
    }) => {
        const resolvedBranchId = unref(branchId);

        if (!resolvedBranchId) {
            return null;
        }

        if (!selectedOccurrence?.event_id) {
            return null;
        }

        const response = await window.axios.post(
            route('branches.booking.impact-preview', resolvedBranchId),
            {
                action,
                scope,
                selected_occurrence: selectedOccurrence,
                changes,
            },
        );

        return response?.data?.data ?? null;
    };

    const fetchImpactPreview = async ({
        action,
        selectedOccurrence,
        changes = {},
        scopes = ['occurrence', 'from_date', 'series'],
    }) => {
        loading.value = true;
        clearImpactPreview();

        const normalizedSelectedOccurrence = buildSelectedOccurrence(selectedOccurrence);

        if (!normalizedSelectedOccurrence.event_id) {
            loading.value = false;

            return impactPreview.value;
        }

        try {
            const results = await Promise.all(scopes.map(async (scope) => {
                const preview = await requestScopePreview({
                    action,
                    scope,
                    selectedOccurrence: normalizedSelectedOccurrence,
                    changes,
                });

                return {
                    key: normalizeScopeKey(scope),
                    preview,
                };
            }));

            const next = emptyImpact();

            results.forEach(({ key, preview }) => {
                next[key] = {
                    count: preview?.affected_occurrence_count ?? null,
                    countLabel: preview?.affected_occurrence_count_label
                        ?? (preview?.affected_occurrence_count !== null && preview?.affected_occurrence_count !== undefined
                            ? String(preview.affected_occurrence_count)
                            : null),
                    isCountCapped: Boolean(preview?.is_affected_count_capped ?? false),
                    participantCount: preview?.affected_participant_count ?? null,
                    participantCountLabel: preview?.affected_participant_count_label
                        ?? (preview?.affected_participant_count !== null && preview?.affected_participant_count !== undefined
                            ? String(preview.affected_participant_count)
                            : null),
                    isParticipantCountCapped: Boolean(preview?.is_affected_participant_count_capped ?? false),
                    message: preview?.message ?? null,
                    raw: preview,
                };
            });

            impactPreview.value = next;

            return impactPreview.value;
        } finally {
            loading.value = false;
        }
    };

    return {
        impactPreview,
        impactPreviewLoading: loading,
        fetchImpactPreview,
        clearImpactPreview,
    };
}
