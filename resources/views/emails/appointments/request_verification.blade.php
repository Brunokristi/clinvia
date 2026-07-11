@extends('emails.layout', [
    'title' => 'Potvrďte požiadavku na termín'
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        Potvrďte požiadavku na termín
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dobrý deň{{ !empty($contactName) ? ', ' . $contactName : '' }},
    </p>

    <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        prijali sme vašu požiadavku. Pre pokračovanie ju prosím potvrďte kliknutím na tlačidlo nižšie.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px 0; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Služba
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $serviceLabel ?? 'Neuvedené' }}
            </td>
        </tr>

        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Preferovaný termín
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $preferredLabel ?? 'Neuvedené' }}
            </td>
        </tr>

        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Pacient
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $patientName ?? 'Neuvedené' }}
            </td>
        </tr>

        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #C17979; text-align: left;">
                Kontakt
            </td>
            <td style="padding: 10px 0; border-bottom: 1px solid #FFE5E5; font-size: 13px; color: #A75A5A; text-align: right; font-weight: 700;">
                {{ $contactLabel ?? 'Neuvedené' }}
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px 0;">
        <tr>
            <td align="center">
                <a
                    href="{{ $verificationUrl }}"
                    style="display: inline-block; background-color: #C17979; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; line-height: 1; padding: 12px 20px; border-radius: 6px;"
                >
                    Potvrdiť požiadavku
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #A75A5A;">
        Ak ste túto požiadavku nevytvorili, tento email môžete ignorovať.
    </p>
@endsection
