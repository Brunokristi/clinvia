<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use App\Models\Branch;
use App\Models\EmailNotification;
use App\Modules\Calendar\Enums\EventType;
use App\Modules\Calendar\Models\Event;
use App\Modules\Calendar\Models\GroupEventParticipant;
use App\Modules\Calendar\Services\RecurringImpactService;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingChangeSummaryNotification;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\BookingReminderNotification;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BranchAdminNotification;
use App\Notifications\GroupEventReminderNotification;
use App\Notifications\RequestCreatedNotification;
use App\Notifications\RequestRejectedNotification;
use App\Notifications\RequestVerificationNotification;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class EmailNotificationService
{
    public function __construct(
        private readonly RecurringImpactService $recurringImpactService,
    ) {
    }

    public function dispatch(string $eventName, array $payload = []): void
    {
        match ($eventName) {
            'request.verification' => $this->handleRequestVerification($payload),
            'request.created' => $this->handleRequestCreated($payload),
            'request.accepted_as_booking' => $this->handleRequestAcceptedAsBooking($payload),
            'request.rejected' => $this->handleRequestRejected($payload),
            'reminder.booking_tomorrow' => $this->handleBookingReminder($payload),
            'reminder.group_event_tomorrow' => $this->handleGroupEventReminder($payload),
            'request.pending_digest' => $this->handlePendingRequestDigest($payload),
            default => null,
        };
    }

    public function dispatchEventMutation(Event $event, string $action, string $eventType, ?string $scope = null, array $context = []): void
    {
        if ($event->type === EventType::Booking) {
            $this->handleBookingMutation($event, $action, $scope, $context);

            return;
        }

        if ($event->type === EventType::GroupEvent) {
            $this->handleGroupMutation($event, $action, $scope, $context);
        }
    }

    private function handleBookingMutation(Event $event, string $action, ?string $scope, array $context): void
    {
        $event->loadMissing(['bookingDetail', 'branch', 'services']);

        $recipient = $event->bookingDetail?->patient_email;

        if (! $recipient) {
            return;
        }

        $legacyBooking = $this->toLegacyBookingPayload($event);

        if (! $legacyBooking) {
            return;
        }

        $scope = $scope ?: 'this';

        if (in_array($action, ['event_created', 'event_duplicated'], true)) {
            $this->sendOnce(
                dedupeKey: sprintf('booking:%d:created:%s:patient:%s', $event->id, $scope, $event->bookingDetail?->id ?? 'none'),
                notificationType: 'booking.created',
                recipientType: 'patient',
                recipientId: $event->bookingDetail?->id,
                recipientEmail: $recipient,
                notifiableType: Event::class,
                notifiableId: $event->id,
                rootEventId: (int) ($event->root_event_id ?? $event->id),
                occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
                scope: $scope,
                payload: $this->userFacingSnapshot($event),
                sender: fn () => Notification::route('mail', $recipient)->notify(new BookingCreatedNotification($legacyBooking)),
            );

            foreach ($this->branchBookingEmails($event->branch) as $branchEmail) {
                $this->sendOnce(
                    dedupeKey: sprintf('booking:%d:created:%s:branch:%s', $event->id, $scope, md5((string) $branchEmail)),
                    notificationType: 'booking.created.branch',
                    recipientType: 'branch',
                    recipientId: $event->branch_id,
                    recipientEmail: $branchEmail,
                    notifiableType: Event::class,
                    notifiableId: $event->id,
                    rootEventId: (int) ($event->root_event_id ?? $event->id),
                    occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
                    scope: $scope,
                    payload: $this->userFacingSnapshot($event),
                    sender: fn () => Notification::route('mail', $branchEmail)->notify(new BranchAdminNotification(
                        'Nova zmena v kalendari pobocky',
                        'Doslo k vytvoreniu rezervacie pre ' . ($event->bookingDetail?->patient_name ?: 'pacienta') . '.',
                    )),
                );
            }

            return;
        }

        if (in_array($action, ['event_cancelled', 'event_deleted', 'event_series_deleted', 'event_occurrence_cancelled', 'event_occurrence_deleted'], true)) {
            $type = $event->is_recurring && in_array($scope, ['this_and_following', 'series', 'all'], true)
                ? 'booking.recurring_cancelled'
                : 'booking.cancelled';

            $oldStartsAt = $this->toCarbon(Arr::get($context, 'old_snapshot.starts_at'));
            $oldEndsAt = $this->toCarbon(Arr::get($context, 'old_snapshot.ends_at'));
            $reason = $this->scopeMessage($scope, $this->resolveRecurringAffectedCount($event, $scope), 'booking');

            $this->sendOnce(
                dedupeKey: sprintf('booking:%d:cancelled:%s:root-%d:patient:%s', $event->id, $scope, (int) ($event->root_event_id ?? $event->id), $event->bookingDetail?->id ?? 'none'),
                notificationType: $type,
                recipientType: 'patient',
                recipientId: $event->bookingDetail?->id,
                recipientEmail: $recipient,
                notifiableType: Event::class,
                notifiableId: $event->id,
                rootEventId: (int) ($event->root_event_id ?? $event->id),
                occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
                scope: $scope,
                payload: $this->userFacingSnapshot($event),
                sender: fn () => Notification::route('mail', $recipient)->notify(new BookingCancelledNotification(
                    booking: $legacyBooking,
                    reason: $reason,
                    appointmentStartsAt: $oldStartsAt ?? $legacyBooking->starts_at,
                    appointmentEndsAt: $oldEndsAt ?? $legacyBooking->ends_at,
                )),
            );

            return;
        }

        $old = Arr::get($context, 'old_snapshot', []);
        $new = Arr::get($context, 'new_snapshot', $this->userFacingSnapshot($event));
        $diff = $this->buildUserFacingDiff($old, $new);

        if ($diff === []) {
            return;
        }

        $impactCount = $this->resolveRecurringAffectedCount($event, $scope);
        $scopeMessage = $this->scopeMessage($scope, $impactCount, 'booking');

        $notificationType = $event->is_recurring && in_array($scope, ['this_and_following', 'series', 'all'], true)
            ? 'booking.recurring_updated'
            : 'booking.updated';

        $oldStartsAt = $this->toCarbon($old['starts_at'] ?? null);
        $oldEndsAt = $this->toCarbon($old['ends_at'] ?? null);

        $this->sendOnce(
            dedupeKey: sprintf(
                'booking:root-%d:%s:%s:patient:%s:%s',
                (int) ($event->root_event_id ?? $event->id),
                $notificationType,
                $scope,
                $event->bookingDetail?->id ?? 'none',
                md5(json_encode($diff, JSON_THROW_ON_ERROR))
            ),
            notificationType: $notificationType,
            recipientType: 'patient',
            recipientId: $event->bookingDetail?->id,
            recipientEmail: $recipient,
            notifiableType: Event::class,
            notifiableId: $event->id,
            rootEventId: (int) ($event->root_event_id ?? $event->id),
            occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
            scope: $scope,
            payload: [
                'old' => $old,
                'new' => $new,
                'diff' => $diff,
                'scope_message' => $scopeMessage,
            ],
            sender: fn () => Notification::route('mail', $recipient)->notify(new BookingRescheduledNotification(
                booking: $legacyBooking,
                oldStartsAt: $oldStartsAt,
                oldEndsAt: $oldEndsAt,
                reason: $scopeMessage,
            )),
        );
    }

    private function handleGroupMutation(Event $event, string $action, ?string $scope, array $context): void
    {
        $event->loadMissing(['participants', 'groupDetail', 'branch']);

        $scope = $scope ?: 'this';

        if ($action === 'event_participant_added') {
            $recipientEmails = collect(Arr::get($context, 'recipient_emails', []))
                ->filter()
                ->values();

            if ($recipientEmails->isEmpty()) {
                $recipientEmails = $event->participants
                    ->where('status', 'confirmed')
                    ->pluck('participant_email')
                    ->filter()
                    ->values();
            }

            foreach ($recipientEmails as $email) {
                $participant = $event->participants->firstWhere('participant_email', $email);

                $this->sendOnce(
                    dedupeKey: sprintf('group-event:%d:participant-added:%s', $event->id, $email),
                    notificationType: 'group_event.participant_added',
                    recipientType: 'participant',
                    recipientId: $participant?->id,
                    recipientEmail: $email,
                    notifiableType: Event::class,
                    notifiableId: $event->id,
                    rootEventId: (int) ($event->root_event_id ?? $event->id),
                    occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
                    scope: 'this',
                    payload: $this->userFacingSnapshot($event),
                    sender: fn () => Notification::route('mail', $email)->notify(new BookingCreatedNotification(
                        $this->toLegacyGroupParticipantBookingPayload($event, $participant),
                    )),
                );
            }

            return;
        }

        $recipients = $this->resolveAffectedGroupRecipients($event, $scope, $context);

        if ($recipients->isEmpty()) {
            return;
        }

        $old = Arr::get($context, 'old_snapshot', []);
        $new = Arr::get($context, 'new_snapshot', $this->userFacingSnapshot($event));
        $diff = $this->buildUserFacingDiff($old, $new, true);

        $isCancellation = in_array($action, ['event_cancelled', 'event_deleted', 'event_series_deleted', 'event_occurrence_cancelled', 'event_occurrence_deleted'], true);

        foreach ($recipients as $recipient) {
            $participantId = (int) ($recipient['participant_id'] ?? 0) ?: null;
            $email = (string) ($recipient['participant_email'] ?? '');

            if (! $email) {
                continue;
            }

            $notificationType = $isCancellation
                ? 'group_event.cancelled'
                : (str_contains((string) ($action ?? ''), 'resched') ? 'group_event.rescheduled' : 'group_event.updated');

            $scopeMessage = $this->scopeMessage($scope, (int) ($recipient['affected_count'] ?? 0), 'group_event');
            $lines = $isCancellation
                ? ['Termín bol zrušený: ' . $this->dateTimeRangeLabel($event->starts_at, $event->ends_at)]
                : ($diff ?: ['Došlo k zmene skupinového termínu.']);
            $participant = $event->participants->firstWhere('id', $participantId);

            $this->sendOnce(
                dedupeKey: sprintf(
                    'group-event:root-%d:%s:%s:participant:%s:%s',
                    (int) ($event->root_event_id ?? $event->id),
                    $notificationType,
                    $scope,
                    $participantId ?? md5($email),
                    md5(json_encode($lines, JSON_THROW_ON_ERROR))
                ),
                notificationType: $notificationType,
                recipientType: 'participant',
                recipientId: $participantId,
                recipientEmail: $email,
                notifiableType: Event::class,
                notifiableId: $event->id,
                rootEventId: (int) ($event->root_event_id ?? $event->id),
                occurrenceDisplayKey: data_get($context, 'occurrence_display_key'),
                scope: $scope,
                payload: [
                    'old' => $old,
                    'new' => $new,
                    'diff' => $lines,
                    'scope_message' => $scopeMessage,
                ],
                sender: fn () => Notification::route('mail', $email)->notify(
                    $isCancellation
                        ? new BookingCancelledNotification(
                            booking: $this->toLegacyGroupParticipantBookingPayload($event, $participant, $email),
                            reason: $scopeMessage,
                            appointmentStartsAt: $this->toCarbon($old['starts_at'] ?? null) ?? $event->starts_at,
                            appointmentEndsAt: $this->toCarbon($old['ends_at'] ?? null) ?? $event->ends_at,
                        )
                        : new BookingChangeSummaryNotification(
                            subject: 'Skupinový termín bol upravený',
                            headline: 'Váš skupinový termín bol upravený.',
                            diffLines: $lines,
                            scopeMessage: $scopeMessage,
                        )
                ),
            );
        }
    }

    private function handleRequestCreated(array $payload): void
    {
        /** @var AppointmentRequest|null $request */
        $request = $payload['appointment_request'] ?? null;

        if (! $request) {
            return;
        }

        $request->loadMissing(['branch', 'services']);

        $skipPatientNotification = (bool) ($payload['skip_patient_notification'] ?? false);

        if ($request->patient_email && ! $skipPatientNotification) {
            $this->sendOnce(
                dedupeKey: sprintf('request:%d:created:patient:%s', $request->id, md5((string) $request->patient_email)),
                notificationType: 'request.created',
                recipientType: 'patient',
                recipientId: null,
                recipientEmail: $request->patient_email,
                notifiableType: AppointmentRequest::class,
                notifiableId: $request->id,
                rootEventId: null,
                occurrenceDisplayKey: null,
                scope: null,
                payload: [
                    'request_id' => $request->id,
                    'status' => $request->status,
                ],
                sender: fn () => Notification::route('mail', $request->patient_email)->notify(new RequestCreatedNotification($request)),
            );
        }

        $this->sendBranchRequestCreatedNotification($request);
    }

    private function handleRequestVerification(array $payload): void
    {
        /** @var AppointmentRequest|null $request */
        $request = $payload['appointment_request'] ?? null;
        $verificationUrl = Arr::get($payload, 'verification_url');

        if (! $request || ! $request->patient_email || ! $verificationUrl) {
            return;
        }

        $request->loadMissing(['branch', 'services']);

        $this->sendOnce(
            dedupeKey: sprintf('request:%d:verification:%s', $request->id, md5((string) $request->patient_email)),
            notificationType: 'request.verification',
            recipientType: 'patient',
            recipientId: null,
            recipientEmail: $request->patient_email,
            notifiableType: AppointmentRequest::class,
            notifiableId: $request->id,
            rootEventId: null,
            occurrenceDisplayKey: null,
            scope: null,
            payload: [
                'request_id' => $request->id,
                'status' => $request->status,
            ],
            sender: fn () => Notification::route('mail', $request->patient_email)
                ->notify(new RequestVerificationNotification($request, (string) $verificationUrl)),
        );
    }

    private function handleRequestAcceptedAsBooking(array $payload): void
    {
        /** @var AppointmentRequest|null $request */
        $request = $payload['appointment_request'] ?? null;

        /** @var Event|null $event */
        $event = $payload['event'] ?? null;

        if (! $request || ! $event || ! $request->patient_email) {
            return;
        }

        $legacyBooking = $this->toLegacyBookingPayload($event);

        if (! $legacyBooking) {
            return;
        }

        $this->sendOnce(
            dedupeKey: sprintf('request:%d:accepted:patient:%s', $request->id, md5((string) $request->patient_email)),
            notificationType: 'request.accepted_as_booking',
            recipientType: 'patient',
            recipientId: null,
            recipientEmail: $request->patient_email,
            notifiableType: AppointmentRequest::class,
            notifiableId: $request->id,
            rootEventId: (int) ($event->root_event_id ?? $event->id),
            occurrenceDisplayKey: null,
            scope: null,
            payload: [
                'request_id' => $request->id,
                'event_id' => $event->id,
            ],
            sender: fn () => Notification::route('mail', $request->patient_email)->notify(new BookingCreatedNotification($legacyBooking)),
        );
    }

    private function handleRequestRejected(array $payload): void
    {
        /** @var AppointmentRequest|null $request */
        $request = $payload['appointment_request'] ?? null;
        $reason = Arr::get($payload, 'reason');

        if (! $request || ! $request->patient_email) {
            return;
        }

        $this->sendOnce(
            dedupeKey: sprintf('request:%d:rejected:patient:%s', $request->id, md5((string) $request->patient_email)),
            notificationType: 'request.rejected',
            recipientType: 'patient',
            recipientId: null,
            recipientEmail: $request->patient_email,
            notifiableType: AppointmentRequest::class,
            notifiableId: $request->id,
            rootEventId: null,
            occurrenceDisplayKey: null,
            scope: null,
            payload: [
                'request_id' => $request->id,
                'reason' => $reason,
            ],
            sender: fn () => Notification::route('mail', $request->patient_email)->notify(new RequestRejectedNotification($request, $reason)),
        );
    }

    private function handleBookingReminder(array $payload): void
    {
        /** @var Event|null $event */
        $event = $payload['event'] ?? null;
        /** @var Carbon|null $startsAt */
        $startsAt = $payload['starts_at'] ?? null;
        /** @var Carbon|null $endsAt */
        $endsAt = $payload['ends_at'] ?? null;

        if (! $event || ! $startsAt) {
            return;
        }

        $event->loadMissing(['bookingDetail', 'branch', 'services']);

        $recipient = $event->bookingDetail?->patient_email;

        if (! $recipient) {
            return;
        }

        $legacyBooking = $this->toLegacyBookingPayload($event, $startsAt, $endsAt);

        if (! $legacyBooking) {
            return;
        }

        $this->sendOnce(
            dedupeKey: sprintf(
                'reminder:booking:%d:%s:patient:%s',
                $event->id,
                $startsAt->toDateString(),
                md5((string) $recipient)
            ),
            notificationType: 'reminder.booking_tomorrow',
            recipientType: 'patient',
            recipientId: null,
            recipientEmail: $recipient,
            notifiableType: Event::class,
            notifiableId: $event->id,
            rootEventId: (int) ($event->root_event_id ?? $event->id),
            occurrenceDisplayKey: null,
            scope: null,
            payload: [
                'starts_at' => $startsAt->toIso8601String(),
                'event_id' => $event->id,
            ],
            sender: fn () => Notification::route('mail', $recipient)->notify(new BookingReminderNotification(
                booking: $legacyBooking,
                startsAt: $startsAt,
                endsAt: $endsAt,
                isRecurring: (bool) $event->is_recurring,
            )),
        );
    }

    private function handleGroupEventReminder(array $payload): void
    {
        /** @var Event|null $event */
        $event = $payload['event'] ?? null;
        /** @var GroupEventParticipant|null $participant */
        $participant = $payload['participant'] ?? null;
        /** @var Carbon|null $startsAt */
        $startsAt = $payload['starts_at'] ?? null;
        /** @var Carbon|null $endsAt */
        $endsAt = $payload['ends_at'] ?? null;

        if (! $event || ! $participant || ! $startsAt || ! $participant->participant_email) {
            return;
        }

        $event->loadMissing(['branch', 'groupDetail']);

        $this->sendOnce(
            dedupeKey: sprintf(
                'reminder:group-event:%d:%s:participant:%d',
                $event->id,
                $startsAt->toDateString(),
                $participant->id
            ),
            notificationType: 'reminder.group_event_tomorrow',
            recipientType: 'participant',
            recipientId: $participant->id,
            recipientEmail: $participant->participant_email,
            notifiableType: Event::class,
            notifiableId: $event->id,
            rootEventId: (int) ($event->root_event_id ?? $event->id),
            occurrenceDisplayKey: null,
            scope: null,
            payload: [
                'starts_at' => $startsAt->toIso8601String(),
                'event_id' => $event->id,
                'participant_id' => $participant->id,
            ],
            sender: fn () => Notification::route('mail', $participant->participant_email)->notify(new GroupEventReminderNotification(
                participantName: $participant->participant_name ?? '',
                eventTitle: $event->title ?: $event->groupDetail?->service_name ?: 'Skupinový termín',
                branchName: $event->branch?->name ?? '—',
                startsAt: $startsAt,
                endsAt: $endsAt,
            )),
        );
    }

    private function handlePendingRequestDigest(array $payload): void
    {
        /** @var Branch|null $branch */
        $branch = $payload['branch'] ?? null;

        if (! $branch) {
            return;
        }

        $pending = AppointmentRequest::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [
                AppointmentRequest::STATUS_PENDING_EMAIL_VERIFICATION,
                AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
                AppointmentRequest::STATUS_MANUALLY_VERIFIED,
            ])
            ->orderBy('created_at')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $intervalHours = max(1, (int) config('notifications.pending_request_digest_interval_hours', 12));

        $alreadyRecent = EmailNotification::query()
            ->where('notification_type', 'request.pending_digest')
            ->where('recipient_type', 'branch')
            ->where('recipient_id', $branch->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subHours($intervalHours))
            ->exists();

        if ($alreadyRecent) {
            return;
        }

        $emails = $this->branchEmails($branch);

        if ($emails->isEmpty()) {
            return;
        }

        $oldest = $pending->first()?->created_at;
        $oldestLabel = $oldest ? $oldest->diffForHumans(now(), true) : '—';

        foreach ($emails as $email) {
            $this->sendOnce(
                dedupeKey: sprintf('request:pending-digest:branch:%d:%s', $branch->id, now()->format('Y-m-d-H')), 
                notificationType: 'request.pending_digest',
                recipientType: 'branch',
                recipientId: $branch->id,
                recipientEmail: $email,
                notifiableType: Branch::class,
                notifiableId: $branch->id,
                rootEventId: null,
                occurrenceDisplayKey: null,
                scope: null,
                payload: [
                    'pending_count' => $pending->count(),
                    'oldest_age' => $oldestLabel,
                ],
                sender: fn () => Notification::route('mail', $email)->notify(new BranchAdminNotification(
                    'Súhrn čakajúcich žiadostí',
                    "Čakajúce žiadosti: {$pending->count()}\nNajstaršia čaká: {$oldestLabel}\nInbox: /admin/branches/{$branch->id}/booking",
                )),
            );
        }
    }

    private function sendBranchRequestCreatedNotification(AppointmentRequest $request): void
    {
        $branch = $request->branch;

        if (! $branch) {
            return;
        }

        $throttleMinutes = max(1, (int) config('notifications.branch_request_throttle_minutes', 15));

        $recentlySent = EmailNotification::query()
            ->where('notification_type', 'request.created.branch')
            ->where('recipient_type', 'branch')
            ->where('recipient_id', $branch->id)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes($throttleMinutes))
            ->exists();

        if ($recentlySent) {
            return;
        }

        $emails = $this->branchEmails($branch);

        foreach ($emails as $email) {
            $this->sendOnce(
                dedupeKey: sprintf('request:%d:created:branch:%d', $request->id, $branch->id),
                notificationType: 'request.created.branch',
                recipientType: 'branch',
                recipientId: $branch->id,
                recipientEmail: $email,
                notifiableType: AppointmentRequest::class,
                notifiableId: $request->id,
                rootEventId: null,
                occurrenceDisplayKey: null,
                scope: null,
                payload: [
                    'request_id' => $request->id,
                    'branch_id' => $branch->id,
                ],
                sender: fn () => Notification::route('mail', $email)->notify(new BranchAdminNotification(
                    'Nová žiadosť o rezerváciu',
                    'Klient ' . $request->patient_name . ' požiadal o nový termín.',
                )),
            );
        }
    }

    private function branchEmails(Branch $branch): Collection
    {
        $settings = array_merge([
            'is_enabled' => false,
            'notification_emails' => [],
            'notify_new_appointment_request' => true,
        ], $branch->notification_settings ?? []);

        if (! ($settings['is_enabled'] ?? false)) {
            return collect();
        }

        if (! ($settings['notify_new_appointment_request'] ?? true)) {
            return collect();
        }

        return collect($settings['notification_emails'] ?? [])
            ->filter()
            ->unique()
            ->values();
    }

    private function branchBookingEmails(?Branch $branch): Collection
    {
        if (! $branch) {
            return collect();
        }

        $settings = array_merge([
            'is_enabled' => false,
            'notification_emails' => [],
            'notify_new_booking' => true,
        ], $branch->notification_settings ?? []);

        if (! ($settings['is_enabled'] ?? false)) {
            return collect();
        }

        if (! ($settings['notify_new_booking'] ?? false)) {
            return collect();
        }

        return collect($settings['notification_emails'] ?? [])
            ->filter()
            ->unique()
            ->values();
    }

    private function resolveAffectedGroupRecipients(Event $event, ?string $scope, array $context): Collection
    {
        $scope = match ($scope) {
            'series' => 'all',
            null, '' => 'this',
            default => $scope,
        };

        if ($scope === 'this') {
            return $event->participants
                ->where('status', 'confirmed')
                ->map(fn (GroupEventParticipant $participant): array => [
                    'participant_id' => $participant->id,
                    'participant_email' => $participant->participant_email,
                    'affected_count' => 1,
                ])
                ->values();
        }

        $selectedOccurrence = [
            'event_id' => $event->id,
            'root_event_id' => $event->root_event_id ?? $event->id,
            'occurrence_starts_at' => Arr::get($context, 'occurrence_starts_at', $event->starts_at?->toIso8601String()),
            'occurrence_ends_at' => Arr::get($context, 'occurrence_ends_at', $event->ends_at?->toIso8601String()),
            'occurrence_original_starts_at' => Arr::get($context, 'occurrence_original_starts_at', $event->recurrence_original_starts_at?->toIso8601String() ?? $event->starts_at?->toIso8601String()),
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'display_key' => Arr::get($context, 'occurrence_display_key'),
        ];

        $preview = $this->recurringImpactService->preview(
            branch: $event->branch,
            selectedOccurrence: $selectedOccurrence,
            action: 'edit',
            scope: $scope,
            changes: [],
        );

        $eventIds = collect($preview['affected_occurrences'] ?? [])
            ->pluck('event_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return collect();
        }

        return GroupEventParticipant::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', 'confirmed')
            ->get(['id', 'participant_email'])
            ->filter(fn (GroupEventParticipant $participant): bool => filled($participant->participant_email))
            ->map(fn (GroupEventParticipant $participant): array => [
                'participant_id' => $participant->id,
                'participant_email' => $participant->participant_email,
                'affected_count' => (int) ($preview['affected_occurrence_count'] ?? 0),
            ])
            ->unique('participant_email')
            ->values();
    }

    private function resolveRecurringAffectedCount(Event $event, ?string $scope): int
    {
        if (! $event->is_recurring || ! in_array($scope, ['this_and_following', 'series', 'all'], true)) {
            return 0;
        }

        $scope = $scope === 'series' ? 'all' : $scope;

        $preview = $this->recurringImpactService->preview(
            branch: $event->branch,
            selectedOccurrence: [
                'event_id' => $event->id,
                'root_event_id' => $event->root_event_id ?? $event->id,
                'occurrence_starts_at' => $event->starts_at?->toIso8601String(),
                'occurrence_ends_at' => $event->ends_at?->toIso8601String(),
                'occurrence_original_starts_at' => $event->recurrence_original_starts_at?->toIso8601String() ?? $event->starts_at?->toIso8601String(),
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'display_key' => null,
            ],
            action: 'edit',
            scope: $scope,
            changes: [],
        );

        return (int) ($preview['affected_occurrence_count'] ?? 0);
    }

    private function scopeMessage(?string $scope, int $affectedCount, string $entity): ?string
    {
        if (! in_array($scope, ['this_and_following', 'series', 'all'], true)) {
            return null;
        }

        $label = $entity === 'group_event' ? 'termínov' : 'rezervácií';

        if (in_array($scope, ['series', 'all'], true)) {
            return "Zmena sa týka celej série. Ovplyvnených je {$affectedCount} {$label}.";
        }

        return "Zmena sa týka termínov od vybraného dátumu. Ovplyvnených je {$affectedCount} {$label}.";
    }

    private function userFacingSnapshot(Event $event): array
    {
        $event->loadMissing(['services', 'branch', 'groupDetail']);

        return [
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'service_names' => $event->services->pluck('name')->filter()->values()->all(),
            'branch_name' => $event->branch?->name,
            'staff_name' => data_get($event->metadata, 'staff_name'),
            'group_title' => $event->title ?: $event->groupDetail?->service_name,
            'capacity' => $event->groupDetail?->capacity,
        ];
    }

    private function buildUserFacingDiff(array $old, array $new, bool $isGroupEvent = false): array
    {
        $lines = [];

        if (($old['starts_at'] ?? null) !== ($new['starts_at'] ?? null) || ($old['ends_at'] ?? null) !== ($new['ends_at'] ?? null)) {
            $lines[] = 'Pôvodný termín: ' . $this->dateTimeRangeLabel(
                $old['starts_at'] ?? null,
                $old['ends_at'] ?? null,
            );
            $lines[] = 'Nový termín: ' . $this->dateTimeRangeLabel(
                $new['starts_at'] ?? null,
                $new['ends_at'] ?? null,
            );
        }

        if (($old['service_names'] ?? []) !== ($new['service_names'] ?? [])) {
            $lines[] = 'Pôvodná služba: ' . $this->joinNames($old['service_names'] ?? []);
            $lines[] = 'Nová služba: ' . $this->joinNames($new['service_names'] ?? []);
        }

        if (($old['branch_name'] ?? null) !== ($new['branch_name'] ?? null)) {
            $lines[] = 'Pôvodná pobočka: ' . (($old['branch_name'] ?? '—'));
            $lines[] = 'Nová pobočka: ' . (($new['branch_name'] ?? '—'));
        }

        if (($old['staff_name'] ?? null) !== ($new['staff_name'] ?? null)) {
            $lines[] = 'Pôvodný personál: ' . (($old['staff_name'] ?? '—'));
            $lines[] = 'Nový personál: ' . (($new['staff_name'] ?? '—'));
        }

        if ($isGroupEvent && ($old['group_title'] ?? null) !== ($new['group_title'] ?? null)) {
            $lines[] = 'Pôvodný názov: ' . (($old['group_title'] ?? '—'));
            $lines[] = 'Nový názov: ' . (($new['group_title'] ?? '—'));
        }

        if ($isGroupEvent && ($old['capacity'] ?? null) !== ($new['capacity'] ?? null)) {
            $lines[] = 'Kapacita: ' . (($old['capacity'] ?? '—')) . ' -> ' . (($new['capacity'] ?? '—'));
        }

        return $lines;
    }

    private function dateTimeRangeLabel(mixed $startsAt, mixed $endsAt): string
    {
        $start = $startsAt ? Carbon::parse((string) $startsAt) : null;
        $end = $endsAt ? Carbon::parse((string) $endsAt) : null;

        if (! $start) {
            return '—';
        }

        $label = $start->format('d.m.Y H:i');

        if ($end) {
            $label .= ' - ' . $end->format('H:i');
        }

        return $label;
    }

    private function joinNames(array $names): string
    {
        $filtered = collect($names)->filter()->values()->all();

        return $filtered === [] ? '—' : implode(', ', $filtered);
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function toLegacyGroupParticipantBookingPayload(Event $event, ?GroupEventParticipant $participant = null, ?string $fallbackEmail = null): \App\Models\Booking
    {
        $event->loadMissing(['branch', 'services', 'groupDetail']);

        $legacyBooking = new \App\Models\Booking();

        $legacyBooking->id = (int) $event->id;
        $legacyBooking->branch_id = (int) $event->branch_id;
        $legacyBooking->starts_at = $event->starts_at;
        $legacyBooking->ends_at = $event->ends_at;
        $legacyBooking->patient_name = $participant?->participant_name ?? 'Pacient';
        $legacyBooking->patient_email = $participant?->participant_email ?? $fallbackEmail;
        $legacyBooking->patient_phone = $participant?->participant_phone;
        $legacyBooking->patient_birth_number = $participant?->participant_birth_number;
        $legacyBooking->status = $event->status;
        $legacyBooking->service_id = $event->services->first()?->id;

        $legacyBooking->setRelation('branch', $event->branch);
        $legacyBooking->setRelation('service', $event->services->first());
        $legacyBooking->setRelation('services', $event->services);

        return $legacyBooking;
    }

    private function sendOnce(
        string $dedupeKey,
        string $notificationType,
        ?string $recipientType,
        ?int $recipientId,
        ?string $recipientEmail,
        ?string $notifiableType,
        ?int $notifiableId,
        ?int $rootEventId,
        ?string $occurrenceDisplayKey,
        ?string $scope,
        array $payload,
        callable $sender,
    ): void {
        $existing = EmailNotification::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('status', 'sent')
            ->exists();

        if ($existing) {
            return;
        }

        $log = EmailNotification::query()->create([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'recipient_email' => $recipientEmail,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'root_event_id' => $rootEventId,
            'occurrence_display_key' => $occurrenceDisplayKey,
            'notification_type' => $notificationType,
            'scope' => $scope,
            'dedupe_key' => $dedupeKey,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'status' => 'processing',
        ]);

        try {
            $sender();

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }

    private function toLegacyBookingPayload(Event $event, ?Carbon $forcedStartsAt = null, ?Carbon $forcedEndsAt = null): ?\App\Models\Booking
    {
        if (! $event->bookingDetail || ! $event->starts_at) {
            return null;
        }

        $legacyBooking = new \App\Models\Booking();

        $legacyBooking->id = $event->id;
        $legacyBooking->branch_id = $event->branch_id;
        $legacyBooking->starts_at = $forcedStartsAt ?? $event->starts_at;
        $legacyBooking->ends_at = $forcedEndsAt ?? $event->ends_at;
        $legacyBooking->patient_name = $event->bookingDetail->patient_name;
        $legacyBooking->patient_email = $event->bookingDetail->patient_email;
        $legacyBooking->patient_phone = $event->bookingDetail->patient_phone;
        $legacyBooking->patient_birth_number = $event->bookingDetail->patient_birth_number;
        $legacyBooking->patient_note = $event->bookingDetail->public_notes;
        $legacyBooking->admin_note = null;
        $legacyBooking->status = $event->status;
        $legacyBooking->service_id = $event->services->first()?->id;
        $legacyBooking->recurrence = $event->recurrence_rule;
        $legacyBooking->recurrence_excluded_dates = collect(data_get($event->metadata, 'recurrence_excluded_dates', []))
            ->filter()
            ->values()
            ->all();

        $legacyBooking->setRelation('branch', $event->branch);
        $legacyBooking->setRelation('service', $event->services->first());
        $legacyBooking->setRelation('services', $event->services);

        return $legacyBooking;
    }
}
