@component('mail::message')
# Votre abonnement expire dans {{ $daysLeft }} jour(s)

Bonjour {{ $company->name }},

Votre abonnement **{{ ucfirst($plan) }}** TangaFlow arrive à échéance le **{{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d/m/Y') }}**.

Pour éviter toute interruption de service (paie automatisée, rapports RH, support dédié, mises à jour...), pensez à renouveler dès maintenant.

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Renouveler mon abonnement
@endcomponent

Si vous ne renouvelez pas, votre compte basculera automatiquement vers le plan **Freemium** le jour de l'échéance.

Merci de votre confiance,
**L'équipe Tangaflow**
@endcomponent
