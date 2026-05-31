<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifie que la compagnie de l'utilisateur dispose de la fonctionnalite demandee
 * via le plan d'abonnement effectif (cf. Company::hasFeature qui gere l'expiration).
 *
 * Usage : ->middleware('feature:payroll') ou ->middleware('feature:hr_reports,advanced_analytics')
 * (l'acces est accorde si AU MOINS une des features listees est disponible).
 *
 * Le gating est data-driven : la grille tarifaire vit dans la colonne `features` du plan,
 * source de verite unique partagee avec l'affichage.
 *
 * Bypass : super_admin, support_it et technicien operent cross-company (le technicien peut
 * intervenir chez n'importe quel client meme freemium).
 */
class RequireFeatureMiddleware
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie'], 401);
        }

        if (in_array($user->role, ['super_admin', 'support_it', 'technicien'], true)) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Aucune compagnie associée'], 403);
        }

        foreach ($features as $feature) {
            if ($company->hasFeature($feature)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Cette fonctionnalite necessite un abonnement superieur.',
            'error_code' => 'subscription_required',
            'required_feature' => $features[0] ?? null,
            'required_plans' => $this->plansProviding($features),
            'current_plan' => $company->subscription,
        ], 403);
    }

    /**
     * Liste des codes de plans actifs qui fournissent au moins une des fonctionnalites demandees.
     *
     * @param  array<int, string>  $features
     * @return array<int, string>
     */
    protected function plansProviding(array $features): array
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (SubscriptionPlan $plan) => collect($features)->contains(fn ($f) => $plan->hasFeature($f)))
            ->pluck('code')
            ->values()
            ->all();
    }
}
