@extends('emails.layout', [
    'title' => 'Nová rezervácia'
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        Nová rezervácia
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dobrý deň, <strong>{{ $patientName }}</strong>,
    </p>

    <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dataily o vašej rezervácii nájdete nižšie.
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
                Miesto
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $branchName }}
            </td>
        </tr>

        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Termín
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $appointmentLabel }}
            </td>
        </tr>
    </table>

    @if (filled($patientNote))
        <p style="margin: 0 0 8px 0; font-size: 13px; line-height: 1.6; color: #C17979; font-weight: 700;">
            Vaša správa:
        </p>

        <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
            {{ $patientNote }}
        </p>
    @endif

    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Prosíme, aby ste prišli aspoň 15 minút pred začiatkom termínu. Ďakujeme.
    </p>
@endsection