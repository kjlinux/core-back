@component('mail::message')
# Votre abonnement expire dans {{ $daysLeft }} jour(s)

Bonjour {{ $company->name }},

Votre abonnement **{{ ucfirst($plan) }}** TangaFlow arrive a echeance le **{{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d/m/Y') }}**.

Pour eviter toute interruption de service (paie automatisee, rapports RH, support dedie, mises a jour...), pensez a renouveler des maintenant.

@component('mail::button', ['url' => rtrim(config('app.frontend_url') ?? config('app.url'), '/').'/abonnement'])
Renouveler mon abonnement
@endcomponent

Si vous ne renouvelez pas, votre compte basculera automatiquement vers le plan **Freemium** le jour de l'echeance.

Merci de votre confiance,
**L'equipe TANGA GROUP**
@endcomponent
