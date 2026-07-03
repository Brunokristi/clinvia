<?php

namespace App\Actions;

use App\Events\BranchCalendarUpdated;
use App\Models\AppointmentRequest;
use App\Models\Booking;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertAppointmentRequestToBookingAction
{
    public function __construct(
        private readonly CreateBookingAction $createBookingAction,
    ) {
    }

    public function execute(Branch $branch, AppointmentRequest $appointmentRequest, array $data): Booking
    {
        $result = DB::transaction(function () use ($branch, $appointmentRequest, $data): array {
            $lockedAppointmentRequest = AppointmentRequest::query()
                ->where('branch_id', $branch->id)
                ->whereKey($appointmentRequest->id)
                ->with('services')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointmentRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'appointment_request_id' => 'Táto žiadosť už nie je čakajúca.',
                ]);
            }

            if ($lockedAppointmentRequest->services->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_ids' => 'Žiadosť nemá vybrané služby.',
                ]);
            }

            $startsAt = Carbon::parse($data['starts_at']);

            $durationMinutes = max(
                15,
                (int) $lockedAppointmentRequest->total_duration_minutes,
            );

            $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

            $services = $lockedAppointmentRequest->services;
            $selectedPatient = is_array($data['selected_patient'] ?? null)
                ? $data['selected_patient']
                : null;

            $patientName = trim((string) ($selectedPatient['patient_name'] ?? ''));
            $patientEmail = trim((string) ($selectedPatient['patient_email'] ?? ''));
            $patientPhone = trim((string) ($selectedPatient['patient_phone'] ?? ''));
            $patientBirthNumber = trim((string) ($selectedPatient['patient_birth_number'] ?? ''));

            $booking = $this->createBookingAction->execute($branch, [
                'service_id' => $services->first()->id,
                'service_ids' => $services
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'patient_name' => $patientName !== ''
                    ? $patientName
                    : $lockedAppointmentRequest->patient_name,
                'patient_email' => $patientEmail !== ''
                    ? $patientEmail
                    : $lockedAppointmentRequest->patient_email,
                'patient_phone' => $patientPhone !== ''
                    ? $patientPhone
                    : $lockedAppointmentRequest->patient_phone,
                'patient_birth_number' => $patientBirthNumber !== ''
                    ? $patientBirthNumber
                    : $lockedAppointmentRequest->patient_birth_number,
                'patient_note' => $lockedAppointmentRequest->patient_note,
                'status' => 'confirmed',
                'notify_patient' => $data['notify_patient'] ?? true,
            ]);

            $lockedAppointmentRequest->update([
                'status' => 'converted',
                'booking_id' => $booking->id,
            ]);

            return [
                'booking' => $booking,
                'appointment_request' => $lockedAppointmentRequest,
            ];
        });

        BranchCalendarUpdated::dispatch(
            branchId: $branch->id,
            action: 'appointment_request_converted',
            bookingId: $result['booking']->id,
            appointmentRequestId: $result['appointment_request']->id,
        );

        return $result['booking'];
    }
}