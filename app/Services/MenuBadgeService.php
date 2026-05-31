<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AppNotification;
use App\Models\BiometricDevice;
use App\Models\ClientFollowupCall;
use App\Models\DeviceAlert;
use App\Models\FeelbackAlert;
use App\Models\FeelbackDevice;
use App\Models\MenuBadgeSeen;
use App\Models\Order;
use App\Models\Payslip;
use App\Models\RfidDevice;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Calcule les compteurs "attention" affiches en pastille a cote des items du menu.
 *
 * Seules les cles pertinentes pour le role de l'utilisateur sont calculees : le menu
 * front etant deja filtre par role, un badge ne s'affiche que pour les profils qui
 * voient l'item correspondant. Le scoping entreprise reprend exactement le pattern de
 * DashboardController (companyId null = perimetre global pour super_admin/support_it).
 *
 * Le compteur AFFICHE est un delta : compteur live moins la base "vue" memorisee lors
 * de la derniere consultation de la page (cf. markSeen). Un badge disparait des qu'on
 * ouvre sa page et ne revient que si de nouveaux elements arrivent.
 */
class MenuBadgeService
{
    /**
     * Toutes les cles de badge connues (sert a valider POST /menu-badges/seen).
     *
     * @var list<string>
     */
    public const KEYS = [
        'monEspace',
        'rfidAbsences',
        'paieGenerer',
        'feelbackAlerts',
        'rfidDevices',
        'bioDevices',
        'feelbackDevices',
        'paramSupport',
        'mkOrders',
        'crmFollowups',
        'sitAlerts',
        'sitDevices',
        'sitTickets',
        'mkAdminOrders',
        'paramAdminAbonnements',
    ];

    /**
     * Compteurs a afficher (delta vu/non-vu, zeros filtres).
     *
     * @return array<string, int>
     */
    public function forUser(User $user, ?string $companyId): array
    {
        $live = $this->liveCountsForUser($user, $companyId);

        return $this->filterPositive($this->applySeen($user, $companyId, $live));
    }

    /**
     * Marque une section comme consultee : la base "vue" est calee sur le compteur
     * live actuel et le cache des badges est invalide pour un retour immediat.
     */
    public function markSeen(User $user, ?string $companyId, string $badgeKey): void
    {
        $live = $this->liveCountsForUser($user, $companyId);
        $scope = $companyId ?? 'all';

        MenuBadgeSeen::query()->updateOrCreate(
            ['user_id' => $user->id, 'badge_key' => $badgeKey, 'scope' => $scope],
            ['seen_count' => (int) ($live[$badgeKey] ?? 0)],
        );

        Cache::forget("menu-badges:{$user->id}:".$scope);
    }

    /**
     * Compteurs "live" bruts (avant soustraction du vu et filtrage des zeros),
     * selon le role et le scope entreprise.
     *
     * @return array<string, int>
     */
    private function liveCountsForUser(User $user, ?string $companyId): array
    {
        if ($user->isEmploye()) {
            return ['monEspace' => $this->employeeUpdates($user)];
        }

        if ($user->isSupportIt()) {
            return [
                'sitAlerts' => $this->openDeviceAlerts($companyId),
                'sitDevices' => $this->offlineDevices($companyId),
                'sitTickets' => $this->openTickets($companyId),
            ];
        }

        if ($user->isManager()) {
            return [
                'rfidAbsences' => $this->pendingAbsences($companyId),
                'mkOrders' => $this->pendingOrders($companyId),
                'paramSupport' => $this->openTickets($companyId),
            ];
        }

        if ($user->isTechnicien()) {
            return [
                'rfidDevices' => $this->offlineRfid($companyId),
                'bioDevices' => $this->offlineBiometric($companyId),
                'feelbackDevices' => $this->offlineFeelback($companyId),
                'crmFollowups' => $this->pendingFollowups($companyId),
            ];
        }

        if ($user->isAdminEnterprise()) {
            return [
                'rfidAbsences' => $this->pendingAbsences($companyId),
                'paieGenerer' => $this->pendingPayslips($companyId),
                'feelbackAlerts' => $this->unreadFeelbackAlerts($companyId),
                'rfidDevices' => $this->offlineRfid($companyId),
                'bioDevices' => $this->offlineBiometric($companyId),
                'feelbackDevices' => $this->offlineFeelback($companyId),
                'paramSupport' => $this->openTickets($companyId),
                'mkOrders' => $this->pendingOrders($companyId),
            ];
        }

        // super_admin : companyId vaut l'entreprise selectionnee via _company_id, sinon null (global).
        return [
            'rfidAbsences' => $this->pendingAbsences($companyId),
            'paieGenerer' => $this->pendingPayslips($companyId),
            'feelbackAlerts' => $this->unreadFeelbackAlerts($companyId),
            'rfidDevices' => $this->offlineRfid($companyId),
            'bioDevices' => $this->offlineBiometric($companyId),
            'feelbackDevices' => $this->offlineFeelback($companyId),
            'mkOrders' => $this->pendingOrders($companyId),
            'crmFollowups' => $this->pendingFollowups($companyId),
            'sitAlerts' => $this->openDeviceAlerts($companyId),
            'sitDevices' => $this->offlineDevices($companyId),
            'sitTickets' => $this->openTickets($companyId),
            'mkAdminOrders' => $this->pendingOrders($companyId),
            'paramAdminAbonnements' => $this->pendingSubscriptionPayments($companyId),
        ];
    }

    /**
     * Soustrait la base "vue" de chaque compteur live. Si le live repasse sous la base
     * (elements resolus entre-temps), on rabaisse la base pour que de futurs nouveaux
     * elements soient bien decomptes.
     *
     * @param  array<string, int>  $live
     * @return array<string, int>
     */
    private function applySeen(User $user, ?string $companyId, array $live): array
    {
        if ($live === []) {
            return [];
        }

        $scope = $companyId ?? 'all';

        $seen = MenuBadgeSeen::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->whereIn('badge_key', array_keys($live))
            ->pluck('seen_count', 'badge_key');

        $result = [];
        foreach ($live as $key => $liveCount) {
            $seenCount = (int) ($seen[$key] ?? 0);

            if ($seenCount > $liveCount) {
                MenuBadgeSeen::query()
                    ->where('user_id', $user->id)
                    ->where('badge_key', $key)
                    ->where('scope', $scope)
                    ->update(['seen_count' => $liveCount]);
                $seenCount = $liveCount;
            }

            $result[$key] = max(0, $liveCount - $seenCount);
        }

        return $result;
    }

    private function pendingAbsences(?string $companyId): int
    {
        $query = AbsenceRequest::query()->where('status', 'pending');
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function pendingPayslips(?string $companyId): int
    {
        $query = Payslip::query()->where('status', '!=', 'validated');
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function unreadFeelbackAlerts(?string $companyId): int
    {
        $query = FeelbackAlert::query()->where('is_read', false);
        if ($companyId !== null) {
            $query->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query->count();
    }

    private function offlineRfid(?string $companyId): int
    {
        return $this->countOfflineDevices(RfidDevice::query(), $companyId);
    }

    private function offlineBiometric(?string $companyId): int
    {
        return $this->countOfflineDevices(BiometricDevice::query(), $companyId);
    }

    private function offlineFeelback(?string $companyId): int
    {
        return $this->countOfflineDevices(FeelbackDevice::query(), $companyId);
    }

    private function offlineDevices(?string $companyId): int
    {
        return $this->offlineRfid($companyId)
            + $this->offlineBiometric($companyId)
            + $this->offlineFeelback($companyId);
    }

    private function countOfflineDevices(\Illuminate\Database\Eloquent\Builder $query, ?string $companyId): int
    {
        $query->where('is_online', false);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function openDeviceAlerts(?string $companyId): int
    {
        $query = DeviceAlert::query()
            ->whereIn('status', [DeviceAlert::STATUS_OPEN, DeviceAlert::STATUS_ACKNOWLEDGED]);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function openTickets(?string $companyId): int
    {
        $query = SupportTicket::query()
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS]);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function pendingOrders(?string $companyId): int
    {
        $query = Order::query()->where('status', 'pending');
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function pendingFollowups(?string $companyId): int
    {
        $query = ClientFollowupCall::query()->where('status', ClientFollowupCall::STATUS_PENDING);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function pendingSubscriptionPayments(?string $companyId): int
    {
        $query = SubscriptionPayment::query()->where('payment_status', SubscriptionPayment::STATUS_PENDING);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    /**
     * Total des nouveautes a signaler a un employe sur "Mon espace" :
     * notifications non lues + demandes de conge recemment traitees + fiche de paie recemment validee.
     */
    private function employeeUpdates(User $user): int
    {
        $unreadNotifications = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $employeeId = $user->employee_id;
        if ($employeeId === null) {
            return $unreadNotifications;
        }

        $since = now()->subDays(7);

        $reviewedAbsences = AbsenceRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '>=', $since)
            ->count();

        $newPayslips = Payslip::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'validated')
            ->where('updated_at', '>=', $since)
            ->count();

        return $unreadNotifications + $reviewedAbsences + $newPayslips;
    }

    /**
     * @param  array<string, int>  $badges
     * @return array<string, int>
     */
    private function filterPositive(array $badges): array
    {
        return array_filter($badges, fn (int $count): bool => $count > 0);
    }
}
