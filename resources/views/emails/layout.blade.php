<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Clinvia' }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8f5f2; font-family: Arial, sans-serif; color: #2f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f5f2; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td style="padding: 32px 32px 16px 32px; text-align: center;">
                            <img
                                src="{{ asset('brand/logo_accent.svg') }}"
                                alt="Clinvia"
                                style="height: 48px; width: auto;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 16px 32px 32px 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 32px; background-color: #f8f5f2; text-align: center;">
                            <p style="margin: 0; font-size: 13px; color: #9b6b6b;">
                                Clinvia
                            </p>

                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #9b6b6b;">
                                Tento email bol odoslaný automaticky.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>