<?php

namespace App\Http\Controllers\Api;

use App\Models\TechnicienReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicienReportController extends BaseApiController
{
    /**
     * Persiste un snapshot du rapport et renvoie la signature HMAC à embarquer dans le PDF.
     * Restreint aux techniciens et super_admin.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isTechnicien() && ! $user->isSuperAdmin())) {
            return $this->errorResponse('Acces reserve aux techniciens', 403);
        }

        $validated = $request->validate([
            'company_id'      => 'required|uuid|exists:companies,id',
            'company_name'    => 'required|string|max:200',
            'technicien_name' => 'required|string|max:200',
            'global_score'    => 'required|integer|min:0|max:100',
            'payload'         => 'required|array',
            'payload.sections' => 'required|array',
        ]);

        // Vérifie qu'un technicien ne signe que pour sa company active.
        if ($user->isTechnicien()) {
            $activeCompanyId = $request->input('_company_id') ?? $user->company_id;
            if ($activeCompanyId && $activeCompanyId !== $validated['company_id']) {
                return $this->errorResponse('Company hors perimetre actif', 403);
            }
        }

        $payloadHash = TechnicienReport::canonicalHash($validated['payload']);
        $signature   = TechnicienReport::sign($payloadHash);

        $report = TechnicienReport::create([
            'user_id'         => $user->id,
            'company_id'      => $validated['company_id'],
            'company_name'    => $validated['company_name'],
            'technicien_name' => $validated['technicien_name'],
            'global_score'    => $validated['global_score'],
            'payload'         => $validated['payload'],
            'payload_hash'    => $payloadHash,
            'signature'       => $signature,
            'signed_at'       => now(),
        ]);

        return $this->successResponse([
            'id'           => (string) $report->id,
            'signature'    => $signature,
            'payloadHash'  => $payloadHash,
            'signedAt'     => $report->signed_at->toIso8601String(),
            'verifyUrl'    => url("/api/technicien-reports/{$report->id}/verify"),
        ], 'Rapport signe', 201);
    }

    /**
     * Vérification publique de l'intégrité — accessible sans auth pour permettre
     * à un tiers (audit, conformité) de valider un PDF via le QR.
     */
    public function verify(string $id): JsonResponse
    {
        $report = TechnicienReport::find($id);
        if (! $report) {
            return $this->errorResponse('Rapport introuvable', 404);
        }

        $recomputedHash = TechnicienReport::canonicalHash($report->payload);
        $recomputedSig  = TechnicienReport::sign($recomputedHash);
        $valid          = hash_equals($report->signature, $recomputedSig)
            && hash_equals($report->payload_hash, $recomputedHash);

        return $this->successResponse([
            'valid'          => $valid,
            'reportId'       => (string) $report->id,
            'companyName'    => $report->company_name,
            'technicienName' => $report->technicien_name,
            'globalScore'    => $report->global_score,
            'signedAt'       => $report->signed_at->toIso8601String(),
        ]);
    }
}
