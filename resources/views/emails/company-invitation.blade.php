@extends('emails.layout', [
    'title' => 'Pozvánka do firmy'
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 24px; color: #2f172a;">
        Pozvánka do firmy
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #6f4d4d;">
        Boli ste pozvaný/á na správu firmy <strong>{{ $companyName }}</strong>.
    </p>

    <p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #6f4d4d;">
        Kliknutím na tlačidlo nižšie prijmete pozvánku a dokončíte registráciu.
    </p>

    <a
        href="{{ $acceptUrl }}"
        style="display: inline-block; padding: 12px 20px; background-color: #b56b6b; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600;"
    >
        Prijať pozvánku
    </a>
@endsection