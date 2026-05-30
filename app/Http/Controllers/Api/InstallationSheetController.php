<?php

namespace App\Http\Controllers\Api;

use App\Models\ClientFollowupCall;
use App\Models\InstallationSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InstallationSheetController extends BaseApiController
{
    private const SOLUTION_LABELS = [
        'presenseRH_rfid' => 'PresenseRH - RFID',
        'presenseRH_fp' => 'PresenseRH - Empreinte',
        'presenseRH_qr' => 'PresenseRH - QR Code',
        'feelback' => 'Feelback',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = InstallationSheet::with(['company', 'technician'])->latest('installed_at');
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
        $sheet = InstallationSheet::with(['company', 'technician', 'followups'])->findOrFail($id);

        return $this->successResponse($sheet);
    }

    public function pdf(string $id): Response
    {
        $sheet = InstallationSheet::with(['company', 'technician', 'followups'])->findOrFail($id);

        $reference = strtoupper(substr($sheet->id, 0, 8));

        $pdf = Pdf::loadView('pdf.installation-sheet', [
            'sheet' => $sheet,
            'reference' => $reference,
            'solutionLabels' => self::SOLUTION_LABELS,
            'clientSignature' => $this->signatureDataUri($sheet->client_signature_path),
            'technicianSignature' => $this->signatureDataUri($sheet->technician_signature_path),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("fiche-installation-{$reference}.pdf");
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
            'client_contact_name' => 'nullable|string|max:255',
            'client_contact_role' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:64',
            'client_email' => 'nullable|email|max:255',
            'site_address' => 'nullable|string|max:500',
            'materials' => 'required|array|min:1',
            'materials.*.solution' => 'required|in:'.implode(',', InstallationSheet::SOLUTIONS),
            'materials.*.serial_number' => 'required|string|max:128',
            'materials.*.quantity' => 'nullable|string|max:128',
            'materials.*.firmware_version' => 'nullable|string|max:64',
            'materials.*.wifi_ssid' => 'nullable|string|max:128',
            'materials.*.static_ip' => 'nullable|string|max:64',
            'materials.*.remote_access' => 'nullable|string|max:64',
            'checklist' => 'required|array',
            'training_rating' => 'nullable|integer|min:1|max:5',
            'observations' => 'nullable|string|max:5000',
            'client_signature_base64' => 'required|string',
            'technician_signature_base64' => 'required|string',
            'installed_at' => 'nullable|date',
        ]);

        $user = $request->user();

        $sheet = DB::transaction(function () use ($data, $user) {
            $sheet = new InstallationSheet(array_merge($data, [
                'technician_user_id' => $user->id,
                'installed_at' => $data['installed_at'] ?? now(),
            ]));
            $sheet->client_signature_path = $this->storeSignature($data['client_signature_base64'], 'client');
            $sheet->technician_signature_path = $this->storeSignature($data['technician_signature_base64'], 'tech');
            $sheet->save();

            $this->generateFollowups($sheet);

            return $sheet;
        });

        return $this->successResponse($sheet, 201);
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
        $path = sprintf('installation-signatures/%s/%s.png', now()->format('Y/m'), $kind.'-'.Str::uuid());
        Storage::disk('public')->put($path, $bin);

        return $path;
    }

    protected function generateFollowups(InstallationSheet $sheet): void
    {
        foreach ([
            ClientFollowupCall::TYPE_J2 => 2,
            ClientFollowupCall::TYPE_J7 => 7,
            ClientFollowupCall::TYPE_J30 => 30,
        ] as $type => $days) {
            ClientFollowupCall::create([
                'company_id' => $sheet->company_id,
                'installation_sheet_id' => $sheet->id,
                'call_type' => $type,
                'scheduled_at' => Carbon::parse($sheet->installed_at)->addDays($days),
                'status' => ClientFollowupCall::STATUS_PENDING,
            ]);
        }
    }
}
