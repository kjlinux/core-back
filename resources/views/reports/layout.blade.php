<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 11px; margin: 0; }
        .header { background: #1e293b; color: #fff; padding: 16px 24px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header .meta { color: #94a3b8; font-size: 10px; margin-top: 4px; }
        .content { padding: 16px 24px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { background: #f1f5f9; padding: 8px 10px; border: 1px solid #e2e8f0; }
        .summary .label { color: #64748b; font-size: 9px; text-transform: uppercase; }
        .summary .value { font-size: 14px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 8px; padding: 6px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">
            @if(!empty($subtitle)){{ $subtitle }} — @endif
            Généré le {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    <div class="content">
        @yield('content')
    </div>
    <div class="footer">
        Document généré automatiquement — {{ config('app.name') }}
    </div>
</body>
</html>
