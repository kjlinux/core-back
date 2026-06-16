<?php

namespace App\Http\Controllers\Api;

use App\Models\MaintenanceSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceSheetController extends BaseApiController
{
    private const SOLUTION_LABELS = [
        'presenseRH_rfid' => 'PresenseRH - RFID',
        'presenseRH_fp' => 'PresenseRH - Empreinte',
        'presenseRH_qr' => 'PresenseRH - QR Code',
        'feelback' => 'Feelback',
    ];

    private const TYPE_LABELS = [
        'preventive' => 'Maintenance préventive',
        'corrective' => 'Maintenance corrective',
        'emergency' => "Intervention d'urgence",
    ];

    private const STATUS_LABELS = [
        'operational' => 'Opérationnel',
        'repaired' => 'Réparé',
        'replaced' => 'Remplacé',
        'to_monitor' => 'À surveiller',
        'out_of_service' => 'Hors service',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceSheet::with(['company', 'technician'])->latest('maintained_at');
        if (! $request->user()->isSuperAdmin()) {
            $query->where('company_id', $this->resolveActiveCompanyId());
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        return $this->paginatedResponse($query->paginate(20));
    }

    public function show(string $id): JsonResponse
    {
        $sheet = MaintenanceSheet::with(['company', 'technician', 'installationSheet'])->findOrFail($id);

        return $this->successResponse($sheet);
    }

    public function pdf(string $id): Response
    {
        $sheet = MaintenanceSheet::with(['company', 'technician', 'installationSheet'])->findOrFail($id);

        $reference = strtoupper(substr($sheet->id, 0, 8));

        $pdf = Pdf::loadView('pdf.maintenance-sheet', [
            'sheet' => $sheet,
            'reference' => $reference,
            'solutionLabels' => self::SOLUTION_LABELS,
            'typeLabels' => self::TYPE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'clientSignature' => $this->signatureDataUri($sheet->client_signature_path),
            'technicianSignature' => $this->signatureDataUri($sheet->technician_signature_path),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("fiche-maintenance-{$reference}.pdf");
    }

    protected function signatureDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($path));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'installation_sheet_id' => 'nullable|exists:installation_sheets,id',
            'client_contact_name' => 'nullable|string|max:255',
            'client_contact_role' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:64',
            'client_email' => 'nullable|email|max:255',
            'site_address' => 'nullable|string|max:500',
            'maintenance_type' => 'required|in:'.implode(',', MaintenanceSheet::TYPES),
            'reported_issue' => 'nullable|string|max:5000',
            'equipments' => 'required|array|min:1',
            'equipments.*.solution' => 'required|in:'.implode(',', MaintenanceSheet::SOLUTIONS),
            'equipments.*.serial_number' => 'required|string|max:128',
            'equipments.*.operation' => 'nullable|string|max:1000',
            'equipments.*.status' => 'required|in:'.implode(',', MaintenanceSheet::EQUIPMENT_STATUSES),
            'checklist' => 'required|array',
            'resolved' => 'boolean',
            'duration_minutes' => 'nullable|integer|min:0|max:100000',
            'satisfaction_rating' => 'nullable|integer|min:1|max:5',
            'next_maintenance_at' => 'nullable|date',
            'observations' => 'nullable|string|max:5000',
            'client_signature_base64' => 'required|string',
            'technician_signature_base64' => 'required|string',
            'maintained_at' => 'nullable|date',
        ]);

        $user = $request->user();

        $sheet = DB::transaction(function () use ($data, $user) {
            $sheet = new MaintenanceSheet(array_merge($data, [
                'technician_user_id' => $user->id,
                'resolved' => $data['resolved'] ?? true,
                'maintained_at' => $data['maintained_at'] ?? now(),
            ]));
            $sheet->client_signature_path = $this->storeSignature($data['client_signature_base64'], 'client');
            $sheet->technician_signature_path = $this->storeSignature($data['technician_signature_base64'], 'tech');
            $sheet->save();

            return $sheet;
        });

        return $this->successResponse($sheet, '', 201);
    }

    protected function storeSignature(string $base64, string $kind): string
    {
        // accepte "data:image/png;base64,..." ou base64 brut
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }
        $bin = base64_decode($base64, true);
        if ($bin === false) {
            abort(422, 'Signature invalide');
        }
        $path = sprintf('maintenance-signatures/%s/%s.png', now()->format('Y/m'), $kind.'-'.Str::uuid());
        Storage::disk('public')->put($path, $bin);

        return $path;
    }
}
