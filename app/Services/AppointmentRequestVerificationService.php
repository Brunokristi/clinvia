<?php

namespace App\Services;

use App\Models\AppointmentRequest;
use Illuminate\Support\Str;

class AppointmentRequestVerificationService
{
    private const DEFAULT_EXPIRY_HOURS = 24;

    public function issueToken(AppointmentRequest $request, int $expiryHours = self::DEFAULT_EXPIRY_HOURS): string
    {
        $token = Str::random(64);

        $request->forceFill([
            'verification_token_hash' => hash('sha256', $token),
            'verification_expires_at' => now()->addHours(max(1, $expiryHours)),
        ])->save();

        return $token;
    }

    public function verify(AppointmentRequest $request, string $token): bool
    {
        if (! $request->verification_token_hash || ! $request->verification_expires_at) {
            return false;
        }

        if ($request->verification_expires_at->isPast()) {
            $request->status = AppointmentRequest::STATUS_EXPIRED;
            $request->save();

            return false;
        }

        if (! hash_equals($request->verification_token_hash, hash('sha256', $token))) {
            return false;
        }

        $request->forceFill([
            'email_verified_at' => now(),
            'status' => AppointmentRequest::STATUS_PENDING_ADMIN_REVIEW,
            'verification_token_hash' => null,
            'verification_expires_at' => null,
        ])->save();

        return true;
    }
}
