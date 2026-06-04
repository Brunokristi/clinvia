@extends('emails.layout', [
    'title' => 'Pozvánka do pobočky'
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        Pozvánka do pobočky
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Boli ste pozvaný/á na správu pobočky
        <strong>{{ $branchName }}</strong>.
    </p>

    <p style="margin: 0 0 28px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Kliknutím na tlačidlo nižšie prijmete pozvánku a získate prístup k správe tejto pobočky.
    </p>

    <table cellpadding="0" cellspacing="0" role="presentation" align="center" style="margin: 0 auto;">
        <tr>
            <td align="center" bgcolor="#C17979" style="border-radius: 8px;">
                <a
                    href="{{ $acceptUrl }}"
                    style="display: inline-block; padding: 13px 22px; font-size: 14px; font-weight: 700; line-height: 1; color: #ffffff; text-decoration: none; border-radius: 8px;"
                >
                    Prijať pozvánku
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 28px 0 0 0; font-size: 12px; line-height: 1.6; color: #C17979;">
        Ak tlačidlo nefunguje, skopírujte a vložte tento odkaz do prehliadača:
    </p>

    <p style="margin: 8px 0 0 0; font-size: 12px; line-height: 1.6; color: #A75A5A; word-break: break-all;">
        <a href="{{ $acceptUrl }}" style="color: #A75A5A; text-decoration: underline;">
            {{ $acceptUrl }}
        </a>
    </p>
@endsection