<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 1.4cm; }
        body { color: #1e293b; font-size: 11px; margin: 0; }

        .head { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1e293b; padding-bottom: 6px; }
        .head td { vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; letter-spacing: 1px; color: #1e293b; }
        .brand-sub { font-size: 10px; color: #64748b; }
        .head-meta { text-align: right; font-size: 10px; color: #475569; }
        .doc-title { font-size: 15px; font-weight: bold; color: #0f172a; margin-bottom: 3px; }

        h2 {
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #1e293b; border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px; margin: 18px 0 8px;
        }

        table.kv { width: 100%; border-collapse: collapse; }
        table.kv th, table.kv td { border: 1px solid #e2e8f0; padding: 4px 8px; text-align: left; vertical-align: top; }
        table.kv th { width: 16%; background: #f8fafc; color: #475569; font-weight: bold; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #1e293b; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.grid td { border: 1px solid #d1d5db; padding: 5px 6px; font-size: 10px; }
        table.grid tr:nth-child(even) td { background: #f8fafc; }
        .mono { font-family: DejaVu Sans Mono, monospace; }

        ul.checklist { list-style: none; padding: 0; margin: 0 0 8px; }
        ul.checklist li { margin-bottom: 3px; }
        .rating { margin: 6px 0; }
        .observations { margin-top: 6px; }
        .observations p { white-space: pre-line; margin: 2px 0 0; }
        .muted { color: #64748b; }

        .pv-text { font-size: 10px; color: #475569; margin-bottom: 10px; }
        table.sign { width: 100%; border-collapse: collapse; }
        table.sign td { width: 50%; border: 1px solid #d1d5db; padding: 8px; vertical-align: top; }
        .sign-label { font-weight: bold; color: #475569; margin-bottom: 6px; }
        .sign-img { height: 90px; text-align: center; }
        .sign-img img { max-height: 90px; max-width: 100%; }
        .sign-empty { height: 90px; color: #9ca3af; font-style: italic; text-align: center; line-height: 90px; }
        .sign-name { border-top: 1px solid #e2e8f0; margin-top: 6px; padding-top: 4px; font-size: 10px; color: #475569; }

        .footer { position: fixed; bottom: -0.8cm; left: 0; right: 0; text-align: center; color: #94a3b8; font-size: 8px; }
        .avoid-break { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">TANGAFLOW</div>
                <div class="brand-sub">Solution de gestion de présence</div>
            </td>
            <td class="head-meta">
                <div class="doc-title">Fiche d'installation</div>
                <div>Réf. {{ $reference }}</div>
                <div>Installé le {{ optional($sheet->installed_at)->translatedFormat('d F Y') ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <h2>Client &amp; site</h2>
    <table class="kv">
        <tr>
            <th>Entreprise</th><td>{{ $sheet->company->name ?? '-' }}</td>
            <th>Contact</th><td>{{ $sheet->client_contact_name ?: '-' }}</td>
        </tr>
        <tr>
            <th>Fonction</th><td>{{ $sheet->client_contact_role ?: '-' }}</td>
            <th>Téléphone</th><td>{{ $sheet->client_phone ?: '-' }}</td>
        </tr>
        <tr>
            <th>Email</th><td>{{ $sheet->client_email ?: '-' }}</td>
            <th>Technicien</th><td>{{ $sheet->technician->name ?? $sheet->technician->email ?? '-' }}</td>
        </tr>
        <tr>
            <th>Adresse du site</th><td colspan="3">{{ $sheet->site_address ?: '-' }}</td>
        </tr>
    </table>

    <h2>Matériels installés ({{ count($sheet->materials ?? []) }})</h2>
    @if(!empty($sheet->materials))
        <table class="grid">
            <thead>
                <tr>
                    <th>Solution</th>
                    <th>N° série</th>
                    <th>Qté</th>
                    <th>Firmware</th>
                    <th>SSID WiFi</th>
                    <th>IP statique</th>
                    <th>Accès distant</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheet->materials as $m)
                    <tr>
                        <td>{{ $solutionLabels[$m['solution'] ?? ''] ?? ($m['solution'] ?? '-') }}</td>
                        <td class="mono">{{ $m['serial_number'] ?? '-' }}</td>
                        <td>{{ $m['quantity'] ?? '-' }}</td>
                        <td>{{ $m['firmware_version'] ?? '-' }}</td>
                        <td>{{ $m['wifi_ssid'] ?? '-' }}</td>
                        <td>{{ $m['static_ip'] ?? '-' }}</td>
                        <td>{{ $m['remote_access'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Aucun matériel.</p>
    @endif

    <div class="avoid-break">
        <h2>Mise en service</h2>
        <ul class="checklist">
            @foreach($sheet->checklist ?? [] as $item)
                <li>{{ ($item['done'] ?? false) ? '☑' : '☐' }} {{ $item['label'] ?? '' }}</li>
            @endforeach
        </ul>
        <p class="rating"><strong>Note de formation :</strong> {{ $sheet->training_rating ? $sheet->training_rating.'/5' : 'non renseignée' }}</p>
        @if($sheet->observations)
            <div class="observations">
                <strong>Observations</strong>
                <p>{{ $sheet->observations }}</p>
            </div>
        @endif
    </div>

    <div class="avoid-break">
        <h2>Procès-verbal de réception</h2>
        <p class="pv-text">
            Le client reconnaît que les matériels ci-dessus ont été installés et mis en service à sa satisfaction,
            et qu'une formation à leur utilisation lui a été dispensée.
        </p>
        <table class="sign">
            <tr>
                <td>
                    <div class="sign-label">Le client</div>
                    @if($clientSignature)
                        <div class="sign-img"><img src="{{ $clientSignature }}" alt="Signature client"></div>
                    @else
                        <div class="sign-empty">Non signé</div>
                    @endif
                    <div class="sign-name">{{ $sheet->client_contact_name ?: '' }}</div>
                </td>
                <td>
                    <div class="sign-label">Le technicien</div>
                    @if($technicianSignature)
                        <div class="sign-img"><img src="{{ $technicianSignature }}" alt="Signature technicien"></div>
                    @else
                        <div class="sign-empty">Non signé</div>
                    @endif
                    <div class="sign-name">{{ $sheet->technician->name ?? $sheet->technician->email ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        TANGAFLOW · Fiche d'installation Réf. {{ $reference }} · Document généré le {{ now()->format('d/m/Y') }}
    </div>
</body>
</html>
