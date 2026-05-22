<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pozvánka do pobočky Clinvia</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #0f172a; background: #f8fafc; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px;">
        <h1 style="margin: 0 0 16px; font-size: 24px;">Pozvánka do pobočky Clinvia</h1>
        <p style="margin: 0 0 12px;">Boli ste pozvaný do pobočky <strong>{{ $branchName }}</strong>.</p>
        <p style="margin: 0 0 24px;">Kliknite na tlačidlo nižšie a dokončite registráciu svojho účtu.</p>
        <p style="margin: 0 0 24px;">
            <a href="{{ $acceptUrl }}" style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 10px;">Dokončiť registráciu</a>
        </p>
        <p style="margin: 0; font-size: 12px; color: #64748b;">Pozvánka vyprší {{ optional($expiresAt)->format('d.m.Y H:i') ?? 'o niekoľko dní' }}.</p>
    </div>
</body>
</html>
