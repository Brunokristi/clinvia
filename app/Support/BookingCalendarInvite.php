<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\Carbon;

class BookingCalendarInvite
{
    public static function timezone(): string
    {
        return (string) config('booking.timezone', 'Europe/Bratislava');
    }

    public static function buildIcs(
        Booking $booking,
        Carbon $startsAt,
        ?Carbon $endsAt = null,
        string $method = 'REQUEST',
        string $status = 'CONFIRMED',
        int $sequence = 0,
    ): string {
        $timezone = self::timezone();

        $startsAt = $startsAt->copy();
        $endsAt ??= $startsAt->copy()->addMinutes(30);
        $endsAt = $endsAt->copy();

        $uid = sprintf('booking-%d@clinvia.local', (int) $booking->id);

        $summary = self::escapeText(self::serviceName($booking));
        $location = self::escapeText($booking->branch?->name ?? '');
        $description = self::escapeText(sprintf(
            'Rezervácia pre pacienta: %s',
            $booking->patient_name ?? 'Pacient',
        ));

        $dtStamp = now()->utc()->format('Ymd\THis\Z');
        $dtStart = $startsAt->format('Ymd\THis');
        $dtEnd = $endsAt->format('Ymd\THis');
        $sequence = max(0, (int) $sequence);
        $status = strtoupper($status);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clinvia//Booking//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-TIMEZONE:' . $timezone,
            'METHOD:' . strtoupper($method),
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStamp,
            'SEQUENCE:' . $sequence,
            'DTSTART;TZID=' . $timezone . ':' . $dtStart,
            'DTEND;TZID=' . $timezone . ':' . $dtEnd,
            'SUMMARY:' . $summary,
            'LOCATION:' . $location,
            'DESCRIPTION:' . $description,
            'STATUS:' . $status,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    private static function serviceName(Booking $booking): string
    {
        if ($booking->services->isNotEmpty()) {
            return $booking->services->pluck('name')->join(', ');
        }

        return $booking->service?->name ?? 'Rezervácia';
    }

    private static function escapeText(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }
}
