<?php

namespace App\Http\Middleware;

use App\Models\SubscriptionPlan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifie que la compagnie de l'utilisateur dispose d'un des plans listes
 * et que l'abonnement est actif (sauf freemium qui n'a pas d'echeance).
 *
 * Usage : ->middleware('plan:garantie,premium')
 * Le super_admin bypasse toujours.
 */
class RequirePlanMiddleware
{
    public function handle(Request $request, Closure $next, string ...$allowedPlans): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Non authentifie'], 401);
        }

        // Bypass : super_admin, support_it et technicien operent cross-company
        // (technicien peut pousser du firmware OTA chez n'importe quel client meme freemium ;
        // c'est le client qui ne verra pas la feature dans son UI, pas TANGA en intervention).
        if (in_array($user->role, ['super_admin', 'support_it', 'technicien'], true)) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Aucune compagnie associee'], 403);
        }

        $currentPlan = $company->subscription;

        // Si le plan demande est freemium ou si la compagnie a freemium et c'est autorise
        if ($currentPlan === SubscriptionPlan::FREEMIUM && in_array(SubscriptionPlan::FREEMIUM, $allowedPlans, true)) {
            return $next($request);
        }

        // Plan payant : doit etre actif et dans la liste autorisee
        if (in_array($currentPlan, $allowedPlans, true) && $company->isSubscriptionActive()) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cette fonctionnalite necessite un abonnement superieur.',
            'error_code' => 'subscription_required',
            'required_plans' => $allowedPlans,
            'current_plan' => $currentPlan,
        ], 403);
    }
}
