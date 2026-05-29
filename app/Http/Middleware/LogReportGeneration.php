<?php

namespace App\Http\Middleware;

use App\Models\ReportAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trace dans report_audit_logs chaque appel à un endpoint de rapport.
 * À enregistrer sur les routes via ->middleware('log.report:<type>').
 * On loggue uniquement si la réponse est 2xx (rapport effectivement servi).
 */
class LogReportGeneration
{
    public function handle(Request $request, Closure $next, string $reportType = 'unknown'): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $user = $request->user();
        if (! $user || $response->getStatusCode() >= 300) {
            return $response;
        }

        $companyId = $request->input('company_id')
            ?: $request->input('_company_id')
            ?: ($user->company_id ?? null);

        // Filtre les champs sensibles avant persistance.
        $filters = collect($request->query())
            ->except(['_company_id', 'password', 'current_password', 'new_password', 'token', 'access_token', 'refresh_token'])
            ->toArray();

        try {
            ReportAuditLog::create([
                'user_id'     => $user->id,
                'company_id'  => $companyId,
                'report_type' => $reportType,
                'route'       => substr((string) $request->route()?->uri(), 0, 150),
                'filters'     => $filters,
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Le log d'audit ne doit jamais casser la requête utilisateur.
            \Log::warning('ReportAuditLog write failed: ' . $e->getMessage());
        }

        return $response;
    }
}
