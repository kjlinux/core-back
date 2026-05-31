<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payroll\GeneratePayslipsRequest;
use App\Http\Requests\Payroll\SaveLatenessRulesRequest;
use App\Http\Requests\Payroll\SavePayrollConfigRequest;
use App\Http\Resources\LatenessRuleResource;
use App\Http\Resources\PayrollConfigResource;
use App\Http\Resources\PayslipResource;
use App\Models\Employee;
use App\Models\LatenessRule;
use App\Models\PayrollConfig;
use App\Models\Payslip;
use App\Services\EmployeeNotificationService;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends BaseApiController
{
    public function __construct(
        private PayrollService $payrollService,
        private EmployeeNotificationService $employeeNotifications,
    ) {}

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * Verifie que l'utilisateur a acces a la config de paie de l'entreprise donnee.
     */
    private function authorizeCompanyAccess(string $companyId): bool
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return true;
        }
        $activeCompanyId = $this->resolveActiveCompanyId();

        return (string) $activeCompanyId === (string) $companyId;
    }

    /**
     * GET /payroll/config/{companyId}
     * Retourne la config de paie de l'entreprise (creee a la volee si inexistante).
     */
    public function getConfig(string $companyId): JsonResponse
    {
        if (! $this->authorizeCompanyAccess($companyId)) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        $config = PayrollConfig::with('latenessRules')
            ->firstOrCreate(
                ['company_id' => $companyId],
                [
                    'default_payment_mode' => 'monthly',
                    'standard_daily_hours' => 8,
                    'working_days_per_month' => 26,
                    'payment_day' => 28,
                    'lateness_deduction_enabled' => true,
                    'overtime_enabled' => false,
                    'overtime_rate' => 1.25,
                ]
            );

        return $this->resourceResponse(new PayrollConfigResource($config));
    }

    /**
     * PUT /payroll/config/{companyId}
     * Enregistre les parametres generaux de paie.
     */
    public function saveConfig(SavePayrollConfigRequest $request, string $companyId): JsonResponse
    {
        if (! $this->authorizeCompanyAccess($companyId)) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        $config = PayrollConfig::with('latenessRules')
            ->updateOrCreate(
                ['company_id' => $companyId],
                $request->validated()
            );

        return $this->resourceResponse(new PayrollConfigResource($config));
    }

    /**
     * PUT /payroll/config/{companyId}/lateness-rules
     * Remplace toutes les regles de penalite retard de l'entreprise.
     */
    public function saveLatenessRules(SaveLatenessRulesRequest $request, string $companyId): JsonResponse
    {
        if (! $this->authorizeCompanyAccess($companyId)) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        // Supprimer les regles existantes et recreer dans une transaction
        // afin de ne jamais perdre les regles en cas d'echec en cours de route.
        $rules = DB::transaction(function () use ($request, $companyId) {
            LatenessRule::where('company_id', $companyId)->delete();

            return collect($request->input('rules'))->map(function ($rule) use ($companyId) {
                return LatenessRule::create([
                    'company_id' => $companyId,
                    'tolerance_minutes' => $rule['tolerance_minutes'],
                    'minutes_threshold' => $rule['minutes_threshold'],
                    'penalty_value' => $rule['penalty_value'],
                    'penalty_type' => $rule['penalty_type'],
                    'apply_per' => $rule['apply_per'],
                ]);
            });
        });

        return $this->successResponse(
            LatenessRuleResource::collection($rules),
            'Regles de penalite mises a jour'
        );
    }

    // =========================================================================
    // GENERATION DES FICHES DE PAIE
    // =========================================================================

    /**
     * POST /payroll/generate
     * Genere les fiches de paie pour la periode et le perimetre donnes.
     */
    public function generate(GeneratePayslipsRequest $request): JsonResponse
    {
        if (! $this->authorizeCompanyAccess($request->input('company_id'))) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        $payslips = $this->payrollService->generatePayslips(
            companyId: $request->input('company_id'),
            periodStart: $request->input('period_start'),
            periodEnd: $request->input('period_end'),
            siteId: $request->input('site_id'),
            departmentId: $request->input('department_id'),
        );

        foreach ($payslips as $payslip) {
            if (! $payslip->wasRecentlyCreated) {
                continue;
            }

            $payslip->loadMissing('employee');

            if ($payslip->employee) {
                $this->employeeNotifications->notifyEmployee(
                    $payslip->employee,
                    'payslip',
                    'Fiche de paie en préparation',
                    "Votre fiche de paie pour la période {$payslip->period} a été générée. Elle sera disponible après validation.",
                    [
                        'payslipId' => (string) $payslip->id,
                        'period' => $payslip->period,
                    ],
                    broadcast: false,
                );
            }
        }

        return $this->successResponse(
            PayslipResource::collection($payslips),
            $payslips->count().' fiche(s) de paie générée(s)'
        );
    }

    // =========================================================================
    // CONSULTATION
    // =========================================================================

    /**
     * GET /payroll/payslips
     * Liste les fiches de paie avec filtres optionnels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payslip::with(['employee', 'company', 'site', 'department']);
        $this->scopeByCompany($query);

        $query->when($request->input('site_id'), fn ($q, $v) => $q->where('site_id', $v));
        $query->when($request->input('department_id'), fn ($q, $v) => $q->where('department_id', $v));
        $query->when($request->input('employee_id'), fn ($q, $v) => $q->where('employee_id', $v));
        $query->when($request->input('period'), fn ($q, $v) => $q->where('period', $v));
        $query->when($request->input('status'), fn ($q, $v) => $q->where('status', $v));

        $payslips = $query->orderByDesc('period')->get();

        return $this->successResponse(PayslipResource::collection($payslips));
    }

    /**
     * GET /payroll/payslips/{id}
     */
    public function show(string $id): JsonResponse
    {
        $payslip = Payslip::with(['employee', 'company', 'site', 'department'])->findOrFail($id);

        if (! $this->authorizeCompanyAccess($payslip->company_id)) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        return $this->resourceResponse(new PayslipResource($payslip));
    }

    /**
     * PATCH /payroll/payslips/{id}/validate
     * Passe le statut de la fiche en "validated".
     */
    public function validate(string $id): JsonResponse
    {
        $payslip = Payslip::findOrFail($id);

        if (! $this->authorizeCompanyAccess($payslip->company_id)) {
            return $this->errorResponse('Accès non autorisé', 403);
        }

        $payslip->update(['status' => 'validated']);
        $payslip->load(['employee', 'company', 'site', 'department']);

        if ($payslip->employee) {
            $this->employeeNotifications->notifyEmployee(
                $payslip->employee,
                'payslip',
                'Nouvelle fiche de paie disponible',
                "Votre fiche de paie pour la période {$payslip->period} est disponible.",
                [
                    'payslipId' => (string) $payslip->id,
                    'period' => $payslip->period,
                ],
                sendEmail: true,
            );
        }

        return $this->resourceResponse(new PayslipResource($payslip), 'Fiche de paie validée');
    }

    // =========================================================================
    // PORTAIL EMPLOYE
    // =========================================================================

    /**
     * GET /payroll/employees/{employeeId}/payslips
     * Retourne les fiches de paie d'un employe specifique.
     * Un employe ne peut acceder qu'a ses propres fiches.
     */
    public function myPayslips(Request $request, string $employeeId): JsonResponse
    {
        $user = $request->user();

        // Employee porte le trait BelongsToCompany : pour un admin_enterprise, un employe
        // d'une autre entreprise est invisible (find renvoie null -> 404). super_admin et
        // support_it ont un scope global, d'ou la verification explicite ci-dessous.
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return $this->errorResponse('Employé introuvable', 404);
        }

        $isPrivileged = $user->role === 'super_admin' || $user->role === 'admin_enterprise';

        if ($isPrivileged) {
            // Un role privilegie reste cantonne a sa propre entreprise (super_admin : toutes).
            if (! $this->authorizeCompanyAccess((string) $employee->company_id)) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        } else {
            $ownEmployeeId = (string) ($user->employee_id ?? '');
            abort_if($ownEmployeeId === '' || $ownEmployeeId !== (string) $employeeId, 403, 'Acces interdit a ces fiches de paie');
        }

        // Payslip n'a pas de global scope company : on filtre explicitement sur l'entreprise
        // de l'employe pour eviter toute fuite inter-entreprise.
        $payslips = Payslip::with(['employee', 'company', 'site', 'department'])
            ->where('employee_id', $employeeId)
            ->where('company_id', $employee->company_id)
            ->orderByDesc('period')
            ->get();

        return $this->successResponse(PayslipResource::collection($payslips));
    }
}
