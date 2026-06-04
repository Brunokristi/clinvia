@extends('emails.layout', [
    'title' => 'Rezervácia bola upravená'
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        Rezervácia bola upravená
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dobrý deň, <strong>{{ $patientName }}</strong>,
    </p>

    <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        vaša rezervácia bola upravená.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px 0; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Služba
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $serviceName }}
            </td>
        </tr>

        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Pobočka
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $branchName }}
            </td>
        </tr>

        @if ($oldAppointmentLabel)
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                    Pôvodný termín
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                    {{ $oldAppointmentLabel }}
                </td>
            </tr>
        @endif

        @if ($newAppointmentLabel)
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                    Nový termín
                </td>
                <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                    {{ $newAppointmentLabel }}
                </td>
            </tr>
        @endif
    </table>

    @if (filled($reason))
        <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.6; color: #C17979; font-weight: 700;">
            Dôvod úpravy:
        </p>

        <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
            {{ $reason }}
        </p>
    @endif

    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        V prípade otázok nás prosím kontaktujte.
    </p>
@endsection