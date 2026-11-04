<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Je verificatiecode</title>
</head>
<body style="margin:0;padding:24px;background:#eef1f4;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0"
                       style="max-width:480px;width:100%;background:#ffffff;border-top:6px solid #2c3e50;border-radius:12px;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px;font-size:22px;">Hoi {{ $userName }},</h1>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.5;">
                                Vul deze code in op {{ config('app.name') }} om je e-mailadres te bevestigen:
                            </p>
                            <p style="margin:0 0 20px;padding:16px;background:#eef1f4;border-radius:8px;text-align:center;font-size:34px;font-weight:bold;letter-spacing:10px;color:#2c3e50;">
                                {{ $code }}
                            </p>
                            <p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#555;">
                                De code is {{ $minutes }} minuten geldig.
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.5;color:#555;">
                                Heb je dit niet aangevraagd? Dan kun je deze mail negeren.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
