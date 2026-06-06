@extends('emails.layout', [
    'title' => 'Vaša správa bola odoslaná',
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        Vaša správa bola odoslaná
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dobrý deň, <strong>{{ $senderName }}</strong>,
    </p>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        ďakujeme za vašu správu. Kontaktný formulár bol úspešne odoslaný.
    </p>

    <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Ozveme sa vám čo najskôr.
    </p>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        S pozdravom,<br>
        {{ $branch->name }}
    </p>
@endsection