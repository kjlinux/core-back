<?php

use Illuminate\Support\Facades\Broadcast;

// Public channels: attendance, feelback
// No authorization needed for public channels

// Private channel for user-specific notifications
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Statut des terminaux d'une entreprise (online/offline, serial, site...).
// Roles transverses (support IT / super_admin / technicien) : toute entreprise (SAV/onboarding).
// Autres roles : uniquement leur propre entreprise.
Broadcast::channel('devices.{companyId}', function ($user, $companyId) {
    if (in_array($user->role, ['super_admin', 'support_it', 'technicien'], true)) {
        return true;
    }

    return (string) $user->company_id === (string) $companyId;
});

// Flux global de supervision du parc : reserve aux roles transverses.
Broadcast::channel('devices.all', function ($user) {
    return in_array($user->role, ['super_admin', 'support_it', 'technicien'], true);
});

// Canal prive d'enregistrement de carte (UID scanne en direct), scope par entreprise.
// super_admin / technicien : acces a toute entreprise (onboarding/SAV) — memes roles
// que la page d'enregistrement et l'endpoint de scan.
// admin_enterprise : uniquement sa propre entreprise.
Broadcast::channel('cards.{companyId}', function ($user, $companyId) {
    if (in_array($user->role, ['super_admin', 'technicien'], true)) {
        return true;
    }

    return (string) $user->company_id === (string) $companyId;
});
