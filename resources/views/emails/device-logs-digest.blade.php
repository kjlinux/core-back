<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Récap logs terminaux</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; color: #1f2937; background: #f9fafb; padding: 20px; }
        .card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 24px 0 8px; color: #111827; }
        .period { color: #6b7280; font-size: 13px; margin-bottom: 16px; }
        .summary { margin: 8px 0 4px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase; margin-right: 6px; }
        .badge-debug { background: #e5e7eb; color: #374151; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fed7aa; color: #9a3412; }
        .badge-critical { background: #fecaca; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #f3f4f6; font-size: 13px; text-align: left; vertical-align: top; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .time { white-space: nowrap; color: #6b7280; width: 130px; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 16px; border-radius: 6px; text-align: center; font-weight: 600; }
        .device-meta { color: #6b7280; font-size: 12px; font-weight: 400; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Récapitulatif des logs terminaux</h1>
        <p class="period">
            Période : du {{ $periodStart->format('d/m/Y H:i') }} au {{ $periodEnd->format('d/m/Y H:i') }}
        </p>

        @if($totalCount === 0)
            <div class="ok">Aucun incident remonté par les terminaux sur les dernières 24h.</div>
        @else
            <div class="summary">
                <strong>{{ $totalCount }}</strong> log(s) sur {{ $byDevice->count() }} terminal(aux) :
            </div>
            <div style="margin-top: 8px;">
                @foreach(['critical', 'error', 'warning', 'info', 'debug'] as $lvl)
                    @if(!empty($levelCounts[$lvl]))
                        <span class="badge badge-{{ $lvl }}">{{ $levelCounts[$lvl] }} {{ $lvl }}</span>
                    @endif
                @endforeach
            </div>

            @foreach($byDevice as $serial => $logs)
                <h2>
                    {{ $serial }}
                    @if($logs->first()?->firmware_version)
                        <span class="device-meta">(firmware {{ $logs->first()->firmware_version }})</span>
                    @endif
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th class="time">Horodatage</th>
                            <th>Niveau</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td class="time">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td><span class="badge badge-{{ $log->level }}">{{ $log->level }}</span></td>
                                <td>{{ $log->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        <p style="margin-top: 24px; font-size: 12px; color: #6b7280;">
            Récapitulatif automatique envoyé chaque matin à 8h. Les terminaux ne remontent que les niveaux warning, error et critical.
        </p>
    </div>
</body>
</html>
