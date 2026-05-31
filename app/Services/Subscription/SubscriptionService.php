<?php

namespace App\Services\Subscription;

use App\Mail\SubscriptionPaidMail;
use App\Models\Company;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\LigdiCashService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionService
{
    public function __construct(protected LigdiCashService $gateway) {}

    /**
     * Initie un premier abonnement payant.
     * Retourne ['payment' => SubscriptionPayment, 'payment_url' => ?string, 'token' => ?string].
     */
    public function subscribe(Company $company, string $planCode, ?User $actor = null): array
    {
        $plan = $this->resolvePlan($planCode);

        if ($plan->code === SubscriptionPlan::FREEMIUM) {
            throw new InvalidArgumentException('Le plan freemium ne se souscrit pas; utilisez expire() ou adminChange().');
        }

        if ($plan->requires_warranty && ! $company->isWarrantyActive()) {
            throw new RuntimeException('Ce plan exige une garantie matérielle active.');
        }

        return $this->createPayment($company, $plan, $plan->monthly_price_xof, false, $actor);
    }

    /**
     * Upgrade en cours d'abonnement. Prorata = (prixNouveau - prixAncien) * jours_restants / 30.
     * Downgrade : pas de remboursement, application en fin de cycle (cf. note).
     */
    public function upgrade(Company $company, string $planCode, ?User $actor = null): array
    {
        $newPlan = $this->resolvePlan($planCode);
        $currentPlan = $this->resolvePlan($company->subscription);

        if ($newPlan->code === $currentPlan->code) {
            throw new InvalidArgumentException('Le plan demandé est déjà actif.');
        }

        if (! $company->isSubscriptionActive()) {
            // Pas d'abonnement actif -> traiter comme une souscription neuve
            return $this->subscribe($company, $newPlan->code, $actor);
        }

        // Downgrade (incluant retour freemium) : appliquer en fin de cycle, pas de paiement.
        // Le plan vise est stocke dans subscription_pending_change_to pour que la commande
        // CheckSubscriptionExpiry applique le bon plan (pas systematiquement freemium).
        if ($newPlan->monthly_price_xof < $currentPlan->monthly_price_xof) {
            return DB::transaction(function () use ($company, $currentPlan, $newPlan, $actor) {
                SubscriptionHistory::create([
                    'company_id' => $company->id,
                    'event' => SubscriptionHistory::EVENT_DOWNGRADED,
                    'from_plan' => $currentPlan->code,
                    'to_plan' => $newPlan->code,
                    'actor_user_id' => $actor?->id,
                    'notes' => 'Downgrade planifie en fin de cycle vers '.$newPlan->code,
                    'created_at' => now(),
                ]);
                $company->subscription_pending_change_to = $newPlan->code;
                // Un downgrade annule un prepaiement (le client ne veut plus poursuivre au tarif actuel)
                $company->subscription_next_period_paid = false;
                $company->subscription_next_expires_at = null;
                $company->save();

                return ['payment' => null, 'payment_url' => null, 'token' => null, 'scheduled_at' => $company->subscription_expires_at];
            });
        }

        // Un upgrade payant annule tout downgrade en attente
        if ($company->subscription_pending_change_to) {
            $company->subscription_pending_change_to = null;
            $company->save();
        }

        // Upgrade : prorata
        $daysRemaining = $this->prorataDaysRemaining($company);
        $amount = (int) round(($newPlan->monthly_price_xof - $currentPlan->monthly_price_xof) * $daysRemaining / 30);

        return $this->createPayment($company, $newPlan, $amount, true, $actor);
    }

    /**
     * Devis (lecture seule) du montant qui serait debite pour un changement de plan, calcule
     * par le MEME chemin que upgrade()/subscribe(). Source de verite affichee au client, pour
     * eviter toute divergence avec un calcul cote frontend.
     *
     * @return array{amount_xof: int, days_remaining: int, is_prorata: bool}
     */
    public function quote(Company $company, string $planCode): array
    {
        $newPlan = $this->resolvePlan($planCode);

        if ($newPlan->code === SubscriptionPlan::FREEMIUM) {
            return ['amount_xof' => 0, 'days_remaining' => 0, 'is_prorata' => false];
        }

        // Pas d'abonnement actif -> souscription neuve au plein tarif.
        if (! $company->isSubscriptionActive()) {
            return ['amount_xof' => $newPlan->monthly_price_xof, 'days_remaining' => 30, 'is_prorata' => false];
        }

        $currentPlan = $this->resolvePlan($company->subscription);

        // Meme plan ou downgrade -> aucun paiement immediat (applique en fin de cycle).
        if ($newPlan->monthly_price_xof <= $currentPlan->monthly_price_xof) {
            return ['amount_xof' => 0, 'days_remaining' => 0, 'is_prorata' => false];
        }

        $daysRemaining = $this->prorataDaysRemaining($company);
        $amount = (int) round(($newPlan->monthly_price_xof - $currentPlan->monthly_price_xof) * $daysRemaining / 30);

        return ['amount_xof' => $amount, 'days_remaining' => $daysRemaining, 'is_prorata' => true];
    }

    /**
     * Jours restants servant d'assiette au prorata, bornes a [1, 30].
     */
    protected function prorataDaysRemaining(Company $company): int
    {
        // Garde défensive : sans date d'expiration valide ou si déjà expiré, aucun
        // jour de prorata (0). Évite de masquer silencieusement une date passée en
        // « 1 jour » (sous-facturation). Cas normal (abonnement actif, date future)
        // inchangé : borné à [1, 30].
        if (! $company->subscription_expires_at) {
            return 0;
        }

        $expiresAt = CarbonImmutable::parse($company->subscription_expires_at);
        if ($expiresAt->isPast()) {
            return 0;
        }

        $days = (int) ceil(now()->diffInDays($expiresAt, false));

        return max(1, min($days, 30));
    }

    /**
     * Paiement anticipe pour le mois suivant (autorise quand abonnement actif).
     */
    public function payNextPeriod(Company $company, ?User $actor = null): array
    {
        if (! $company->isSubscriptionActive()) {
            throw new RuntimeException('Aucun abonnement actif a renouveler.');
        }

        if ($company->subscription_next_period_paid) {
            throw new RuntimeException('Le mois suivant est déjà réglé.');
        }

        $plan = $this->resolvePlan($company->subscription);

        return $this->createPayment($company, $plan, $plan->monthly_price_xof, false, $actor, prepaid: true);
    }

    /**
     * Active une periode payee (appele depuis le webhook LigdiCash au succes).
     */
    public function activatePayment(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            /** @var Company $company */
            $company = Company::lockForUpdate()->findOrFail($payment->company_id);

            $now = CarbonImmutable::now();
            $newPlanCode = $payment->to_plan;

            // Le paiement a deja eu lieu : on n'annule jamais l'activation ici (sinon on
            // encaisserait sans livrer le service) — la garantie a ete validee a la
            // souscription. Mais si elle n'est plus active (revoquee entre l'initiation et
            // le webhook), on escalade en erreur ET on consigne une note auditable dans
            // l'historique d'abonnement pour que l'equipe regularise (au lieu d'un simple log).
            $warrantyNote = null;
            $plan = SubscriptionPlan::where('code', $newPlanCode)->first();
            if ($plan && $plan->requires_warranty && ! $company->isWarrantyActive()) {
                $warrantyNote = 'Active sans garantie materielle active (revoquee apres initiation) — a regulariser.';
                Log::error('Abonnement active alors que la garantie materielle requise est inactive', [
                    'company_id' => $company->id,
                    'plan' => $newPlanCode,
                    'payment_id' => $payment->id,
                ]);
            }

            $isPrepaid = $payment->is_prorata === false
                && $company->isSubscriptionActive()
                && $company->subscription === $newPlanCode;

            if ($isPrepaid) {
                // Empile sur la periode actuelle
                $base = CarbonImmutable::parse($company->subscription_expires_at);
                $company->subscription_next_period_paid = true;
                $company->subscription_next_expires_at = $base->addMonth();
                $event = SubscriptionHistory::EVENT_PREPAID;
            } elseif ($payment->is_prorata) {
                // Upgrade : nouveau plan, meme date d'echeance
                $event = SubscriptionHistory::EVENT_UPGRADED;
                $company->subscription = $newPlanCode;
            } else {
                // Premiere souscription ou renouvellement
                $event = $company->isSubscriptionActive() && $company->subscription === $newPlanCode
                    ? SubscriptionHistory::EVENT_RENEWED
                    : SubscriptionHistory::EVENT_SUBSCRIBED;
                $company->subscription = $newPlanCode;
                $company->subscription_starts_at = $company->subscription_starts_at ?? $now;
                $company->subscription_expires_at = $now->addMonth();
            }

            $company->save();

            $payment->payment_status = SubscriptionPayment::STATUS_PAID;
            $payment->period_start = $payment->period_start ?? $now;
            $payment->period_end = $payment->period_end ?? CarbonImmutable::parse($company->subscription_expires_at);
            $payment->save();

            SubscriptionHistory::create([
                'company_id' => $company->id,
                'event' => $event,
                'from_plan' => $payment->from_plan,
                'to_plan' => $payment->to_plan,
                'payment_id' => $payment->id,
                'actor_user_id' => $payment->triggered_by_user_id,
                'notes' => $warrantyNote,
                'created_at' => $now,
            ]);

            if ($company->email) {
                Mail::to($company->email)->queue(new SubscriptionPaidMail($payment));
            }
        });
    }

    /**
     * Expiration : bascule vers freemium et log.
     */
    public function expire(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $from = $company->subscription;
            // Si un downgrade vers un plan paye a ete programme, le respecter au lieu
            // de basculer aveuglement vers freemium.
            $target = $company->subscription_pending_change_to ?: SubscriptionPlan::FREEMIUM;

            $company->subscription = $target;
            $company->subscription_pending_change_to = null;
            $company->subscription_next_period_paid = false;
            $company->subscription_next_expires_at = null;
            if ($target === SubscriptionPlan::FREEMIUM) {
                $company->subscription_expires_at = null;
                $company->subscription_starts_at = null;
            } else {
                // Plan paye programme : on demarre une nouvelle periode d'un mois
                // sans paiement (le downgrade implique perte d'acces aux features du plan superieur).
                $company->subscription_starts_at = now();
                $company->subscription_expires_at = now()->addMonth();
            }
            $company->save();

            SubscriptionHistory::create([
                'company_id' => $company->id,
                'event' => $target === SubscriptionPlan::FREEMIUM
                    ? SubscriptionHistory::EVENT_EXPIRED
                    : SubscriptionHistory::EVENT_DOWNGRADED,
                'from_plan' => $from,
                'to_plan' => $target,
                'notes' => $target === SubscriptionPlan::FREEMIUM ? null : 'Downgrade programme applique',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Bascule un paiement prepaye en periode courante.
     */
    public function rolloverPrepaid(Company $company): void
    {
        if (! $company->subscription_next_period_paid || ! $company->subscription_next_expires_at) {
            return;
        }

        DB::transaction(function () use ($company) {
            $company->subscription_starts_at = $company->subscription_expires_at ?? now();
            $company->subscription_expires_at = $company->subscription_next_expires_at;
            $company->subscription_next_period_paid = false;
            $company->subscription_next_expires_at = null;
            $company->save();

            SubscriptionHistory::create([
                'company_id' => $company->id,
                'event' => SubscriptionHistory::EVENT_ROLLED_OVER,
                'from_plan' => $company->subscription,
                'to_plan' => $company->subscription,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Super-admin : change un plan sans paiement.
     */
    public function adminChange(Company $company, string $planCode, ?\DateTimeInterface $expiresAt, ?User $actor): void
    {
        $plan = $this->resolvePlan($planCode);

        DB::transaction(function () use ($company, $plan, $expiresAt, $actor) {
            $from = $company->subscription;
            $company->subscription = $plan->code;

            if ($plan->code === SubscriptionPlan::FREEMIUM) {
                $company->subscription_starts_at = null;
                $company->subscription_expires_at = null;
                $company->subscription_next_period_paid = false;
                $company->subscription_next_expires_at = null;
            } else {
                $company->subscription_starts_at = $company->subscription_starts_at ?? now();
                $company->subscription_expires_at = $expiresAt
                    ? CarbonImmutable::parse($expiresAt)
                    : now()->addMonth();
            }
            $company->save();

            SubscriptionHistory::create([
                'company_id' => $company->id,
                'event' => SubscriptionHistory::EVENT_ADMIN_CHANGED,
                'from_plan' => $from,
                'to_plan' => $plan->code,
                'actor_user_id' => $actor?->id,
                'notes' => $expiresAt ? 'Date echeance imposee : '.$expiresAt->format('Y-m-d') : null,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return array{payment: ?SubscriptionPayment, payment_url: ?string, token: ?string}
     */
    protected function createPayment(Company $company, SubscriptionPlan $toPlan, int $amount, bool $isProrata, ?User $actor, bool $prepaid = false): array
    {
        // Anti-doublon : empeche un second paiement tant qu'un precedent est en attente
        // (ex : double-clic, ou paiement initie non finalise). On laisse 30 min de fenetre.
        // Le controle + la creation sont atomiques sous un verrou par entreprise pour eviter
        // que deux requetes concurrentes ne creent chacune un paiement en attente. L'appel
        // HTTP a la passerelle (plus bas) reste HORS transaction pour ne pas tenir le verrou.
        $payment = DB::transaction(function () use ($company, $toPlan, $amount, $isProrata, $actor) {
            Company::whereKey($company->id)->lockForUpdate()->first();

            $existingPending = SubscriptionPayment::where('company_id', $company->id)
                ->where('payment_status', SubscriptionPayment::STATUS_PENDING)
                ->where('created_at', '>', now()->subMinutes(30))
                ->first();
            if ($existingPending) {
                throw new RuntimeException('Un paiement est déjà en cours. Veuillez le finaliser ou patienter quelques minutes.');
            }

            return SubscriptionPayment::create([
                'company_id' => $company->id,
                'from_plan' => $company->subscription,
                'to_plan' => $toPlan->code,
                'amount_xof' => $amount,
                'is_prorata' => $isProrata,
                'payment_status' => SubscriptionPayment::STATUS_PENDING,
                'triggered_by_user_id' => $actor?->id,
                'triggered_by_superadmin' => $actor?->role === 'super_admin',
            ]);
        });

        if ($amount <= 0) {
            // Cas limite : upgrade sans cout (jamais en theorie, mais on protege)
            $this->activatePayment($payment);

            return ['payment' => $payment, 'payment_url' => null, 'token' => null];
        }

        $result = $this->gateway->createPayment([
            'reference' => 'SUB-'.$payment->id,
            'amount' => $amount,
            'description' => sprintf(
                'Abonnement %s (%s)',
                $toPlan->name,
                $prepaid ? 'mois suivant' : ($isProrata ? 'prorata upgrade' : 'mois en cours')
            ),
            'type' => 'subscription_payment',
            'return_url' => config('ligdicash.subscription_return_url'),
            'cancel_url' => config('ligdicash.subscription_cancel_url'),
            'customer' => [
                'firstname' => $actor?->first_name ?? '',
                'lastname' => $actor?->last_name ?? $company->name,
                'email' => $actor?->email ?? $company->email,
                'phone' => $actor?->phone ?? $company->phone,
            ],
            'custom_data' => [
                'subscription_payment_id' => $payment->id,
                'company_id' => $company->id,
                'prepaid' => $prepaid,
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            $payment->payment_status = SubscriptionPayment::STATUS_FAILED;
            $payment->gateway_response = $result;
            $payment->save();

            throw new RuntimeException($result['message'] ?? 'Échec d\'initiation du paiement.');
        }

        $payment->gateway_token = $result['token'] ?? null;
        $payment->gateway_response = $result['raw'] ?? null;
        $payment->payment_method = 'ligdicash';
        $payment->save();

        return [
            'payment' => $payment,
            'payment_url' => $result['payment_url'] ?? null,
            'token' => $result['token'] ?? null,
        ];
    }

    protected function resolvePlan(string $code): SubscriptionPlan
    {
        $plan = SubscriptionPlan::where('code', $code)->where('is_active', true)->first();
        if (! $plan) {
            throw new InvalidArgumentException("Plan inconnu : {$code}");
        }

        return $plan;
    }
}
