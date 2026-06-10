<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Email' }}</title>
</head>

<body style="margin: 0; padding: 0; background-color: #FFE5E5; font-family: Arial, sans-serif; color: #2f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #FFE5E5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    role="presentation"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 6px; border-collapse: separate; border-spacing: 0; overflow: hidden;"
                >
                    <tr>
                        <td style="padding: 32px 32px 12px 32px; text-align: center; background-color: #ffffff; border-top-left-radius: 6px; border-top-right-radius: 6px;">
                            <img
                                src="{{ asset('brand/logo_accent.png') }}"
                                alt="Logo aplikácie"
                                width="40"
                                style="display: block; width: 40px; max-width: 40px; height: auto; margin: 0 auto; border: 0;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 16px 32px 36px 32px; text-align: center; background-color: #ffffff;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 32px; background-color: #ffffff; text-align: center; border-bottom-left-radius: 6px; border-bottom-right-radius: 6px;">
                            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #C17979;">
                                Tento email bol odoslaný automaticky. Neodpovedajte naň.
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px;">
                    <tr>
                        <td style="padding: 16px 24px 0 24px; text-align: center;">
                            <p style="margin: 0; font-size: 11px; line-height: 1.5; color: #C17979;">
                                Ak ste túto správu neočakávali, môžete ju ignorovať.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>