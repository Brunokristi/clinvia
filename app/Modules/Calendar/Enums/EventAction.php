<?php

namespace App\Modules\Calendar\Enums;

enum EventAction: string
{
    case EventCreated = 'event_created';
    case EventUpdated = 'event_updated';
    case EventRescheduled = 'event_rescheduled';
    case EventResized = 'event_resized';
    case EventCancelled = 'event_cancelled';
    case EventDeleted = 'event_deleted';
    case EventRestored = 'event_restored';
    case EventDuplicated = 'event_duplicated';
    case EventServicesUpdated = 'event_services_updated';
    case EventParticipantAdded = 'event_participant_added';
    case EventParticipantRemoved = 'event_participant_removed';
    case EventSeriesUpdated = 'event_series_updated';
    case EventSeriesSplit = 'event_series_split';
    case EventSeriesDeleted = 'event_series_deleted';
    case EventOccurrenceUpdated = 'event_occurrence_updated';
    case EventOccurrenceCancelled = 'event_occurrence_cancelled';
    case EventOccurrenceDeleted = 'event_occurrence_deleted';
}
