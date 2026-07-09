<?php

namespace Tests\Feature\Calendar\RecurringEvents;

use App\Models\Patient;
use App\Modules\Calendar\Enums\EventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingRecurringTest extends RecurringEventsTestCase
{
    public function test_booking_recurring_update_rejects_patient_reassignment_for_all_supported_scopes(): void
    {
        $fixture = $this->createCalendarFixture();
        $originalPatient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Original Recurring Patient',
            'patient_email' => 'original.recurring.patient@example.com',
            'patient_phone' => '+421900112233',
        ]);
        $otherPatient = Patient::query()->create([
            'branch_id' => $fixture['branch']->id,
            'patient_name' => 'Another Recurring Patient',
            'patient_email' => 'another.recurring.patient@example.com',
            'patient_phone' => '+421900445566',
        ]);

        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'booking_detail' => [
                'patient_id' => $originalPatient->id,
                'patient_name' => $originalPatient->patient_name,
                'patient_email' => $originalPatient->patient_email,
                'patient_phone' => $originalPatient->patient_phone,
            ],
        ]);

        foreach (['this', 'this_and_following', 'series'] as $scope) {
            try {
                $this->mutationService()->update($master, [
                    'occurrence_date' => '2026-07-13',
                    'starts_at' => '2026-07-13 12:00:00',
                    'booking_detail' => [
                        'patient_id' => $otherPatient->id,
                    ],
                ], $fixture['user']->id, $scope);

                $this->fail('Expected ValidationException for scope ' . $scope);
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Pacienta existujúcej rezervácie nie je možné zmeniť.',
                    $exception->errors()['booking_detail.patient_id'][0] ?? null,
                    'Unexpected validation message for scope ' . $scope
                );
            }
        }

        $this->assertDatabaseMissing('booking_event_details', [
            'patient_id' => $otherPatient->id,
        ]);
    }

    public function test_booking_recurring_update_without_patient_id_preserves_existing_patient_for_all_supported_scopes(): void
    {
        $fixture = $this->createCalendarFixture();

        foreach (['this', 'this_and_following', 'series'] as $scope) {
            $patient = Patient::query()->create([
                'branch_id' => $fixture['branch']->id,
                'patient_name' => 'Preserved Patient ' . $scope,
                'patient_email' => 'preserved.' . $scope . '@example.com',
                'patient_phone' => '+421900778899',
            ]);

            $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
                'booking_detail' => [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->patient_name,
                    'patient_email' => $patient->patient_email,
                    'patient_phone' => $patient->patient_phone,
                ],
            ]);

            $this->mutationService()->update($master, [
                'occurrence_date' => '2026-07-20',
                'starts_at' => '2026-07-20 16:00:00',
            ], $fixture['user']->id, $scope);

            $unexpectedPatientRows = DB::table('booking_event_details')
                ->join('events', 'events.id', '=', 'booking_event_details.event_id')
                ->where('events.root_event_id', (int) $master->root_event_id)
                ->whereNotNull('booking_event_details.patient_id')
                ->where('booking_event_details.patient_id', '!=', $patient->id)
                ->count();

            $expectedPatientRows = DB::table('booking_event_details')
                ->join('events', 'events.id', '=', 'booking_event_details.event_id')
                ->where('events.root_event_id', (int) $master->root_event_id)
                ->where('booking_event_details.patient_id', $patient->id)
                ->count();

            $this->assertSame(0, $unexpectedPatientRows, 'Patient ID changed unexpectedly for scope ' . $scope);
            $this->assertGreaterThan(0, $expectedPatientRows, 'No preserved patient rows found for scope ' . $scope);
        }
    }

    public function test_booking_recurring_edit_this_creates_exception_and_keeps_patient_data(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking, [
            'booking_detail' => [
                'patient_name' => 'Recurring Patient',
                'patient_email' => 'recurring.patient@example.com',
            ],
        ]);

        $exception = $this->mutationService()->update($master, [
            'occurrence_date' => '2026-07-13',
            'starts_at' => '2026-07-13 12:00:00',
            'ends_at' => '2026-07-13 13:00:00',
        ], $fixture['user']->id, 'this');

        $this->assertSame('Recurring Patient', $exception->bookingDetail?->patient_name);

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertOccurrenceExists($snapshot, '2026-07-13 14:00');
        $this->assertOccurrenceMissing($snapshot, '2026-07-13 12:00');
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }

    public function test_booking_recurring_delete_this_and_following_keeps_past_occurrences(): void
    {
        $fixture = $this->createCalendarFixture();
        $master = $this->createBaseRecurringMaster($fixture, EventType::Booking);

        $this->withRequestBody([
            'occurrence_date' => '2026-07-20',
        ], fn () => $this->mutationService()->delete($master, 'this_and_following'));

        $snapshot = $this->calendarSnapshot($this->renderRange($fixture['branch']));
        $this->assertRenderedTimes($snapshot, [
            '2026-07-06 12:00',
            '2026-07-13 12:00',
        ]);
        $this->assertNoDuplicateRenderedEvents($snapshot);
    }
}
