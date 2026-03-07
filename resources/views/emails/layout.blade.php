<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $subject ?? 'Core Tanga Group' }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #334155; }
    .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .header { background-color: #1e293b; padding: 32px 40px; text-align: center; }
    .header h1 { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
    .header p { color: #94a3b8; font-size: 13px; margin-top: 4px; }
    .body { padding: 36px 40px; }
    .greeting { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px; }
    .text { font-size: 14px; line-height: 1.7; color: #475569; margin-bottom: 16px; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
    .info-box table { width: 100%; border-collapse: collapse; }
    .info-box td { padding: 6px 0; font-size: 14px; }
    .info-box td:first-child { color: #64748b; width: 45%; }
    .info-box td:last-child { color: #1e293b; font-weight: 600; }
    .password-box { background: #0f172a; border-radius: 6px; padding: 16px 24px; margin: 20px 0; text-align: center; }
    .password-box span { font-family: 'Courier New', monospace; font-size: 22px; font-weight: 700; color: #f1f5f9; letter-spacing: 3px; }
    .btn { display: inline-block; margin: 20px 0; background-color: #1e293b; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
    .divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }
    .footer { background: #f8fafc; padding: 20px 40px; text-align: center; border-top: 1px solid #e2e8f0; }
    .footer p { font-size: 12px; color: #94a3b8; line-height: 1.6; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .badge-admin { background: #fef3c7; color: #92400e; }
    .badge-manager { background: #dbeafe; color: #1e40af; }
    .badge-super { background: #fee2e2; color: #991b1b; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>Core Tanga Group</h1>
      <p>Plateforme de gestion d'entreprise</p>
    </div>
    <div class="body">
      @yield('content')
    </div>
    <div class="footer">
      <p>Cet email a ete envoye automatiquement par la plateforme Core Tanga Group.<br>
      Merci de ne pas repondre a cet email.</p>
    </div>
  </div>
</body>
</html>
