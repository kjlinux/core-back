<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payroll\GeneratePayslipsRequest;
use App\Http\Requests\Payroll\SaveLatenessRulesRequest;
use App\Http\Requests\Payroll\SavePayrollConfigRequest;
use App\Http\Resources\LatenessRuleResource;
use App\Http\Resources\PayrollConfigResource;
use App\Http\Resources\PayslipResource;
use App\Models\LatenessRule;
use App\Models\Payslip;
use App\Models\PayrollConfig;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends BaseApiController
{
    public function __construct(private PayrollService $payrollService) {}

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * GET /payroll/config/{companyId}
     * Retourne la config de paie de l'entreprise (creee a la volee si inexistante).
     */
    public function getConfig(string $companyId): JsonResponse
    {
        $config = PayrollConfig::with('latenessRules')
            ->firstOrCreate(
                ['company_id' => $companyId],
                [
                    'default_payment_mode'      => 'monthly',
                    'standard_daily_hours'       => 8,
                    'working_days_per_month'     => 26,
                    'payment_day'                => 28,
                    'lateness_deduction_enabled' => true,
                    'overtime_enabled'           => false,
                    'overtime_rate'              => 1.25,
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
        // Supprimer les regles existantes et recreer
        LatenessRule::where('company_id', $companyId)->delete();

        $rules = collect($request->input('rules'))->map(function ($rule) use ($companyId) {
            return LatenessRule::create([
                'company_id'        => $companyId,
                'tolerance_minutes' => $rule['tolerance_minutes'],
                'minutes_threshold' => $rule['minutes_threshold'],
                'penalty_value'     => $rule['penalty_value'],
                'penalty_type'      => $rule['penalty_type'],
                'apply_per'         => $rule['apply_per'],
            ]);
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
        $payslips = $this->payrollService->generatePayslips(
            companyId:    $request->input('company_id'),
            periodStart:  $request->input('period_start'),
            periodEnd:    $request->input('period_end'),
            siteId:       $request->input('site_id'),
            departmentId: $request->input('department_id'),
        );

        return $this->successResponse(
            PayslipResource::collection($payslips),
            $payslips->count() . ' fiche(s) de paie generee(s)'
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

        $query->when($request->input('site_id'),       fn ($q, $v) => $q->where('site_id', $v));
        $query->when($request->input('department_id'), fn ($q, $v) => $q->where('department_id', $v));
        $query->when($request->input('employee_id'),   fn ($q, $v) => $q->where('employee_id', $v));
        $query->when($request->input('period'),        fn ($q, $v) => $q->where('period', $v));
        $query->when($request->input('status'),        fn ($q, $v) => $q->where('status', $v));

        $payslips = $query->orderByDesc('period')->get();

        return $this->successResponse(PayslipResource::collection($payslips));
    }

    /**
     * GET /payroll/payslips/{id}
     */
    public function show(string $id): JsonResponse
    {
        $payslip = Payslip::with(['employee', 'company', 'site', 'department'])->findOrFail($id);

        return $this->resourceResponse(new PayslipResource($payslip));
    }

    /**
     * PATCH /payroll/payslips/{id}/validate
     * Passe le statut de la fiche en "validated".
     */
    public function validate(string $id): JsonResponse
    {
        $payslip = Payslip::findOrFail($id);
        $payslip->update(['status' => 'validated']);
        $payslip->load(['employee', 'company', 'site', 'department']);

        return $this->resourceResponse(new PayslipResource($payslip), 'Fiche de paie validee');
    }


    // =========================================================================
    // PORTAIL EMPLOYE
    // =========================================================================

    /**
     * GET /payroll/employees/{employeeId}/payslips
     * Retourne les fiches de paie d'un employe specifique.
     */
    public function myPayslips(string $employeeId): JsonResponse
    {
        $payslips = Payslip::with(['employee', 'company', 'site', 'department'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('period')
            ->get();

        return $this->successResponse(PayslipResource::collection($payslips));
    }
}
