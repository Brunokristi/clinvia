<!doctype html>
<html lang="sk">
<body>
    <h1>Potvrdenie rezervácie</h1>

    <p>Vaša rezervácia bola prijatá.</p>

    <p><strong>Pobočka:</strong> {{ $booking->branch->name }}</p>
    <p><strong>Služba:</strong> {{ $booking->service->name }}</p>
    <p><strong>Dátum:</strong> {{ $booking->bookingSlot->starts_at->format('d.m.Y') }}</p>
    <p><strong>Čas:</strong> {{ $booking->bookingSlot->starts_at->format('H:i') }} - {{ $booking->bookingSlot->ends_at->format('H:i') }}</p>

    @if($booking->branch->address_line_1 || $booking->branch->city)
        <p>
            <strong>Adresa:</strong>
            {{ collect([
                $booking->branch->address_line_1,
                $booking->branch->address_line_2,
                $booking->branch->postal_code,
                $booking->branch->city,
                $booking->branch->country,
            ])->filter()->join(', ') }}
        </p>
    @endif

    @php
        $primaryContact = $booking->branch->contacts?->firstWhere('is_primary')
            ?? $booking->branch->contacts?->firstWhere('type', 'phone')
            ?? $booking->branch->contacts?->firstWhere('type', 'email');
    @endphp

    @if($primaryContact)
        <p>
            <strong>Kontakt pobočky:</strong>
            {{ $primaryContact->label ?: $primaryContact->type }} - {{ $primaryContact->value }}
        </p>
    @endif

    <p>
        Ak potrebujete niečo upraviť, kontaktujte priamo pobočku.
    </p>
</body>
</html>