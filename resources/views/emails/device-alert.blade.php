<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Alerte Support IT</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #1f2937; background: #f9fafb; padding: 20px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb; }
        .severity { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .severity-low { background: #dbeafe; color: #1e40af; }
        .severity-medium { background: #fef3c7; color: #92400e; }
        .severity-high { background: #fed7aa; color: #9a3412; }
        .severity-critical { background: #fecaca; color: #991b1b; }
        h1 { font-size: 20px; margin: 12px 0; }
        table { width: 100%; margin-top: 16px; border-collapse: collapse; }
        td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        td.label { color: #6b7280; width: 35%; }
    </style>
</head>
<body>
    <div class="card">
        <span class="severity severity-{{ $alert->severity }}">{{ $alert->severity }}</span>
        <h1>{{ $alert->title }}</h1>
        @if($alert->message)
            <p>{{ $alert->message }}</p>
        @endif
        <table>
            <tr><td class="label">Type</td><td>{{ $alert->type }}</td></tr>
            <tr><td class="label">Capteur</td><td>{{ $alert->device_kind }}{{ $alert->device_id ? ' / ' . $alert->device_id : '' }}</td></tr>
            <tr><td class="label">Entreprise</td><td>{{ $alert->company_id ?? '—' }}</td></tr>
            <tr><td class="label">Site</td><td>{{ $alert->site_id ?? '—' }}</td></tr>
            <tr><td class="label">Détecté à</td><td>{{ $alert->created_at?->format('d/m/Y H:i:s') }}</td></tr>
        </table>
        @if(!empty($alert->context))
            <h3 style="font-size: 14px; margin-top: 20px;">Contexte</h3>
            <pre style="background: #f3f4f6; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto;">{{ json_encode($alert->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
        <p style="margin-top: 24px; font-size: 12px; color: #6b7280;">
            Connectez-vous au tableau de bord Support IT pour traiter cette alerte.
        </p>
    </div>
</body>
</html>
